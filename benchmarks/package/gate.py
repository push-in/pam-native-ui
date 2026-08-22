#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
from enum import IntEnum
from pathlib import Path

from reproducibility import (
    MAX_ARTIFACT_BYTES,
    MAX_REPORT_BYTES,
    ArtifactCode,
    artifact,
    digest,
    open_regular,
    write,
)


class ResultCode(IntEnum):
    PASSED = 1
    EXCEEDED = 2


def load_json(path: Path, label: str) -> object:
    descriptor, _ = open_regular(path, label, MAX_REPORT_BYTES)
    with os.fdopen(descriptor, "rb") as handle:
        try:
            return json.load(handle)
        except (UnicodeDecodeError, json.JSONDecodeError) as error:
            raise ValueError(f"{label} is not valid JSON") from error


def load_budgets(path: Path) -> dict[ArtifactCode, int]:
    document = load_json(path, "package budget contract")
    if not isinstance(document, dict) or set(document) != {"schemaVersion", "budgets"}:
        raise ValueError("package budget contract has an incompatible root shape")
    if type(document["schemaVersion"]) is not int or document["schemaVersion"] != 1:
        raise ValueError("package budget contract must use schemaVersion 1")
    entries = document["budgets"]
    if not isinstance(entries, list) or len(entries) != len(ArtifactCode):
        raise ValueError("package budget contract must define every artifact code once")
    budgets: dict[ArtifactCode, int] = {}
    for entry in entries:
        if not isinstance(entry, dict) or set(entry) != {"artifactCode", "maximumBytes"}:
            raise ValueError("package budget entry has an incompatible shape")
        if type(entry["artifactCode"]) is not int:
            raise ValueError("package budget artifactCode must be an integer")
        try:
            code = ArtifactCode(entry["artifactCode"])
        except ValueError as error:
            raise ValueError("package budget artifactCode is invalid") from error
        maximum = entry["maximumBytes"]
        if code in budgets or type(maximum) is not int or not 1 <= maximum <= MAX_ARTIFACT_BYTES:
            raise ValueError("package budget code or maximumBytes is invalid")
        budgets[code] = maximum
    if set(budgets) != set(ArtifactCode):
        raise ValueError("package budget codes must be sequential from 1 through 3")
    return budgets


def evaluate(
    artifacts: list[tuple[ArtifactCode, Path]], budgets: dict[ArtifactCode, int]
) -> dict[str, object]:
    if not artifacts or len({code for code, _ in artifacts}) != len(artifacts):
        raise ValueError("package artifacts must be non-empty and use unique codes")
    results: list[dict[str, object]] = []
    for code, path in sorted(artifacts):
        actual, sha256 = digest(path, f"artifact {code.value}")
        maximum = budgets[code]
        result = ResultCode.PASSED if actual <= maximum else ResultCode.EXCEEDED
        results.append(
            {
                "artifactCode": code.value,
                "resultCode": result.value,
                "actualBytes": actual,
                "maximumBytes": maximum,
                "sha256": sha256,
            }
        )
    overall = (
        ResultCode.PASSED
        if all(item["resultCode"] == ResultCode.PASSED for item in results)
        else ResultCode.EXCEEDED
    )
    return {"schemaVersion": 1, "resultCode": overall.value, "artifacts": results}


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Enforce PAM Native UI release package size budgets"
    )
    parser.add_argument(
        "--budgets", type=Path, default=Path(__file__).with_name("budgets.json")
    )
    parser.add_argument("--artifact", action="append", type=artifact, default=[])
    parser.add_argument("--output", type=Path)
    parser.add_argument("--verify-report", type=Path)
    options = parser.parse_args()
    if options.output is not None and options.verify_report is not None:
        raise ValueError("--output and --verify-report are mutually exclusive")
    report = evaluate(options.artifact, load_budgets(options.budgets))
    if options.output is not None:
        write(options.output, report)
    if options.verify_report is not None:
        if load_json(options.verify_report, "package budget report") != report:
            raise ValueError("package budget report is stale or does not match the artifacts")
    print(json.dumps(report, indent=2))
    return 0 if report["resultCode"] == ResultCode.PASSED else 1


if __name__ == "__main__":
    raise SystemExit(main())
