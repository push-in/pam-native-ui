#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import os
import stat
import tempfile
from enum import IntEnum
from pathlib import Path

MAX_REPORT_BYTES = 1_048_576
MAX_ARTIFACT_BYTES = 536_870_912
CHUNK_BYTES = 1_048_576


class ArtifactCode(IntEnum):
    IOS_SOURCE_ARCHIVE = 1
    ANDROID_AAR = 2
    PHP_SOURCE_ARCHIVE = 3


class ResultCode(IntEnum):
    PASSED = 1
    MISMATCHED = 2


def artifact_code(raw: str) -> ArtifactCode:
    try:
        return ArtifactCode(int(raw))
    except (TypeError, ValueError) as error:
        raise argparse.ArgumentTypeError(
            "artifact code must be an integer from 1 through 3"
        ) from error


def pair(raw: str) -> tuple[ArtifactCode, Path, Path]:
    fields = raw.split("=", 2)
    if len(fields) != 3 or not fields[1] or not fields[2]:
        raise argparse.ArgumentTypeError("pair must use CODE=PRIMARY=REBUILD")
    return artifact_code(fields[0]), Path(fields[1]), Path(fields[2])


def artifact(raw: str) -> tuple[ArtifactCode, Path]:
    fields = raw.split("=", 1)
    if len(fields) != 2 or not fields[1]:
        raise argparse.ArgumentTypeError("artifact must use CODE=PATH")
    return artifact_code(fields[0]), Path(fields[1])


def open_regular(path: Path, label: str, maximum: int) -> tuple[int, int]:
    if path.is_symlink():
        raise ValueError(f"{label} must be a non-empty regular file")
    try:
        descriptor = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))
    except OSError as error:
        raise ValueError(f"{label} must be a non-empty regular file") from error
    metadata = os.fstat(descriptor)
    if not stat.S_ISREG(metadata.st_mode) or not 1 <= metadata.st_size <= maximum:
        os.close(descriptor)
        raise ValueError(
            f"{label} must be a non-empty regular file within {maximum} bytes"
        )
    return descriptor, metadata.st_size


def digest(path: Path, label: str) -> tuple[int, str]:
    descriptor, size = open_regular(path, label, MAX_ARTIFACT_BYTES)
    hasher = hashlib.sha256()
    with os.fdopen(descriptor, "rb") as handle:
        while chunk := handle.read(CHUNK_BYTES):
            hasher.update(chunk)
    return size, hasher.hexdigest()


def produce(pairs: list[tuple[ArtifactCode, Path, Path]]) -> dict[str, object]:
    if not pairs or len({code for code, _, _ in pairs}) != len(pairs):
        raise ValueError("artifact pairs must be non-empty and use unique codes")
    entries: list[dict[str, object]] = []
    for code, primary, rebuild in sorted(pairs):
        size, primary_hash = digest(primary, f"artifact {code.value} primary")
        rebuild_size, rebuild_hash = digest(rebuild, f"artifact {code.value} rebuild")
        result = (
            ResultCode.PASSED
            if (size, primary_hash) == (rebuild_size, rebuild_hash)
            else ResultCode.MISMATCHED
        )
        entries.append(
            {
                "artifactCode": code.value,
                "resultCode": result.value,
                "bytes": size,
                "sha256": primary_hash,
            }
        )
    overall = (
        ResultCode.PASSED
        if all(entry["resultCode"] == ResultCode.PASSED for entry in entries)
        else ResultCode.MISMATCHED
    )
    return {"schemaVersion": 1, "resultCode": overall.value, "artifacts": entries}


def load_report(path: Path) -> object:
    descriptor, _ = open_regular(path, "reproducibility report", MAX_REPORT_BYTES)
    with os.fdopen(descriptor, "rb") as handle:
        try:
            return json.load(handle)
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise ValueError("reproducibility report is not valid JSON") from error


def validate(document: object) -> dict[ArtifactCode, dict[str, object]]:
    if not isinstance(document, dict) or set(document) != {
        "schemaVersion",
        "resultCode",
        "artifacts",
    }:
        raise ValueError("reproducibility report has an incompatible root shape")
    if type(document["schemaVersion"]) is not int or document["schemaVersion"] != 1:
        raise ValueError("schemaVersion must be integer 1")
    if type(document["resultCode"]) is not int or document["resultCode"] != ResultCode.PASSED:
        raise ValueError("resultCode must be passed integer 1")
    entries = document["artifacts"]
    if not isinstance(entries, list) or not 1 <= len(entries) <= len(ArtifactCode):
        raise ValueError("artifacts must contain between one and three entries")
    parsed: dict[ArtifactCode, dict[str, object]] = {}
    for entry in entries:
        if not isinstance(entry, dict) or set(entry) != {
            "artifactCode",
            "resultCode",
            "bytes",
            "sha256",
        }:
            raise ValueError("artifact evidence has an incompatible shape")
        if type(entry["artifactCode"]) is not int or type(entry["resultCode"]) is not int:
            raise ValueError("artifact codes must be integers")
        try:
            code = ArtifactCode(entry["artifactCode"])
        except ValueError as error:
            raise ValueError("artifactCode is invalid") from error
        if code in parsed or entry["resultCode"] != ResultCode.PASSED:
            raise ValueError("artifact codes must be unique and passed")
        if type(entry["bytes"]) is not int or not 1 <= entry["bytes"] <= MAX_ARTIFACT_BYTES:
            raise ValueError("artifact bytes are invalid")
        sha256 = entry["sha256"]
        if (
            not isinstance(sha256, str)
            or len(sha256) != 64
            or any(char not in "0123456789abcdef" for char in sha256)
        ):
            raise ValueError("artifact sha256 is invalid")
        parsed[code] = entry
    return parsed


def verify(artifacts: list[tuple[ArtifactCode, Path]], report_path: Path) -> None:
    if not artifacts or len({code for code, _ in artifacts}) != len(artifacts):
        raise ValueError("verification artifacts must be non-empty and unique")
    recorded = validate(load_report(report_path))
    if set(recorded) != {code for code, _ in artifacts}:
        raise ValueError("report does not describe the supplied artifact set")
    for code, path in artifacts:
        size, sha256 = digest(path, f"artifact {code.value}")
        if (recorded[code]["bytes"], recorded[code]["sha256"]) != (size, sha256):
            raise ValueError(f"artifact {code.value} does not match reproducibility evidence")


def write(path: Path, document: dict[str, object]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.is_symlink() or (path.exists() and not path.is_file()):
        raise ValueError("report output must be a regular path")
    descriptor, temporary = tempfile.mkstemp(prefix=f".{path.name}.", dir=path.parent)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8") as handle:
            descriptor = -1
            json.dump(document, handle, indent=2)
            handle.write("\n")
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary, path)
    finally:
        if descriptor >= 0:
            os.close(descriptor)
        try:
            os.unlink(temporary)
        except FileNotFoundError:
            pass


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Produce or verify PAM Mobile UI reproducibility evidence"
    )
    parser.add_argument("--pair", action="append", type=pair, default=[])
    parser.add_argument("--artifact", action="append", type=artifact, default=[])
    parser.add_argument("--output", type=Path)
    parser.add_argument("--verify-report", type=Path)
    options = parser.parse_args()
    producing = (
        bool(options.pair)
        and options.output is not None
        and not options.artifact
        and options.verify_report is None
    )
    verifying = (
        bool(options.artifact)
        and options.verify_report is not None
        and not options.pair
        and options.output is None
    )
    if producing == verifying:
        raise ValueError("choose exactly one producer or verifier mode")
    if producing:
        document = produce(options.pair)
        write(options.output, document)
        print(json.dumps(document, indent=2))
        return 0 if document["resultCode"] == ResultCode.PASSED else 1
    verify(options.artifact, options.verify_report)
    print(json.dumps(load_report(options.verify_report), indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
