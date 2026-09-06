#!/usr/bin/env python3

from __future__ import annotations

import argparse
import hashlib
import json
import re
import struct
import sys
from enum import IntEnum
from pathlib import Path


class ResultStatus(IntEnum):
    PASSED = 1
    FAILED = 2


class AuditStatus(IntEnum):
    IN_PROGRESS = 1
    COMPLETE = 2


class ComponentStatus(IntEnum):
    NOT_VERIFIED = 1
    APPROVED = 2


SHA256 = re.compile(r"^[0-9a-f]{64}$")
EMULATOR_MARKERS = ("emulator", "sdk_gphone", "generic", "unknown")
REQUIRED_CHECKS = (
    "twoConsecutivePhysicalPasses",
    "manualVisualReview",
    "realInteractionRecording",
)


class ApprovalFailure(RuntimeError):
    pass


def read_json(path: Path) -> dict[str, object]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exception:
        raise ApprovalFailure(f"cannot read JSON {path}: {exception}") from exception
    if not isinstance(value, dict):
        raise ApprovalFailure(f"{path} must contain one JSON object")
    return value


def repository_file(root: Path, value: object, label: str) -> Path:
    if not isinstance(value, str) or not value:
        raise ApprovalFailure(f"{label} must be a non-empty repository-relative path")
    candidate = Path(value)
    if candidate.is_absolute() or ".." in candidate.parts:
        raise ApprovalFailure(f"{label} escapes the repository: {value!r}")
    resolved = (root / candidate).resolve()
    try:
        resolved.relative_to(root.resolve())
    except ValueError as exception:
        raise ApprovalFailure(f"{label} escapes the repository: {value!r}") from exception
    if not resolved.is_file():
        raise ApprovalFailure(f"{label} does not exist: {value}")
    return resolved


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def expect_hash(value: object, label: str) -> str:
    if not isinstance(value, str) or SHA256.fullmatch(value) is None:
        raise ApprovalFailure(f"{label} must be a lowercase SHA-256 digest")
    return value


def png_size(path: Path) -> tuple[int, int]:
    with path.open("rb") as stream:
        header = stream.read(24)
    if len(header) != 24 or header[:8] != b"\x89PNG\r\n\x1a\n":
        raise ApprovalFailure(f"screenshot is not a PNG: {path}")
    return struct.unpack(">II", header[16:24])


def validate_device(component: str, evidence: dict[str, object], report: dict[str, object]) -> None:
    device = report.get("device")
    if not isinstance(device, dict):
        raise ApprovalFailure(f"{component}: report device must be an object")
    manufacturer = device.get("manufacturer")
    model = device.get("model")
    if not isinstance(manufacturer, str) or not isinstance(model, str):
        raise ApprovalFailure(f"{component}: physical manufacturer and model are required")
    identity = f"{manufacturer} {model}".lower()
    if any(marker in identity for marker in EMULATOR_MARKERS):
        raise ApprovalFailure(f"{component}: emulator evidence cannot be approved")
    if evidence.get("device") != f"{manufacturer} {model}":
        raise ApprovalFailure(f"{component}: manifest and report device identities differ")
    for manifest_key, report_key in (("androidVersion", "androidVersion"), ("androidApi", "api")):
        if evidence.get(manifest_key) != device.get(report_key):
            raise ApprovalFailure(
                f"{component}: manifest {manifest_key} differs from report device {report_key}"
            )
    if evidence.get("viewport") != device.get("viewport"):
        raise ApprovalFailure(f"{component}: manifest and report viewports differ")


def validate_passes(component: str, evidence: dict[str, object], report: dict[str, object]) -> None:
    manifest_passes = evidence.get("passes")
    report_passes = report.get("passes")
    if not isinstance(manifest_passes, list) or not isinstance(report_passes, list):
        raise ApprovalFailure(f"{component}: pass lists are required")
    if len(manifest_passes) != 2 or len(report_passes) != 2:
        raise ApprovalFailure(f"{component}: exactly two independent physical passes are required")
    hashes: set[str] = set()
    for expected_index, (manifest_pass, report_pass) in enumerate(
        zip(manifest_passes, report_passes, strict=True), start=1
    ):
        if not isinstance(manifest_pass, dict) or not isinstance(report_pass, dict):
            raise ApprovalFailure(f"{component}: every pass must be an object")
        if manifest_pass.get("index") != expected_index or report_pass.get("index") != expected_index:
            raise ApprovalFailure(f"{component}: pass indices must be sequential 1, 2")
        if report_pass.get("resultStatus") != int(ResultStatus.PASSED):
            raise ApprovalFailure(f"{component}: pass {expected_index} is not explicitly passed")
        raw_hash = expect_hash(report_pass.get("rawReportSha256"), f"{component} pass hash")
        if manifest_pass.get("rawReportSha256") != raw_hash:
            raise ApprovalFailure(f"{component}: pass {expected_index} hash differs in manifest")
        hashes.add(raw_hash)
        if report_pass.get("geometryFailureCount") != 0:
            raise ApprovalFailure(f"{component}: pass {expected_index} has geometry failures")
    if len(hashes) != 2:
        raise ApprovalFailure(f"{component}: both physical passes reference the same report")


def validate_media(root: Path, component: str, evidence: dict[str, object], report: dict[str, object]) -> None:
    documentation = report.get("documentation")
    if not isinstance(documentation, dict):
        raise ApprovalFailure(f"{component}: documentation metadata is required")
    report_path = repository_file(root, evidence.get("report"), f"{component} report")
    media_directory = report_path.parent

    for kind, hash_key in (("recording", "recordingSha256"), ("gif", "gifSha256")):
        filename = documentation.get(kind)
        if not isinstance(filename, str) or Path(filename).name != filename:
            raise ApprovalFailure(f"{component}: {kind} must be a local filename")
        media = media_directory / filename
        if not media.is_file() or media.stat().st_size < 1024:
            raise ApprovalFailure(f"{component}: missing or empty {kind}: {media}")
        expected = expect_hash(documentation.get(hash_key), f"{component} {kind} hash")
        if sha256(media) != expected:
            raise ApprovalFailure(f"{component}: {kind} hash does not match {filename}")
        if repository_file(root, evidence.get(kind), f"{component} manifest {kind}") != media.resolve():
            raise ApprovalFailure(f"{component}: manifest and report {kind} paths differ")

    screenshot_names: list[str] = []
    for key in ("before", "during", "after"):
        value = documentation.get(key)
        if not isinstance(value, str) or Path(value).name != value:
            raise ApprovalFailure(f"{component}: documentation {key} screenshot is required")
        screenshot_names.append(value)
    additional = documentation.get("additionalScreenshots")
    if not isinstance(additional, list) or not all(isinstance(value, str) for value in additional):
        raise ApprovalFailure(f"{component}: additionalScreenshots must be a list")
    screenshot_names.extend(additional)
    if len(screenshot_names) != len(set(screenshot_names)):
        raise ApprovalFailure(f"{component}: documentation screenshots contain duplicates")

    manifest_screenshots = evidence.get("screenshots")
    if not isinstance(manifest_screenshots, list):
        raise ApprovalFailure(f"{component}: manifest screenshots must be a list")
    manifest_files = [
        repository_file(root, value, f"{component} screenshot")
        for value in manifest_screenshots
    ]
    if {path.name for path in manifest_files} != set(screenshot_names):
        raise ApprovalFailure(f"{component}: manifest and report screenshot sets differ")
    dimensions = [png_size(path) for path in manifest_files]
    if any(min(width, height) < 720 or max(width, height) < 1280 for width, height in dimensions):
        raise ApprovalFailure(f"{component}: screenshot resolution is below documentation quality")
    if len({sha256(path) for path in manifest_files}) < 3:
        raise ApprovalFailure(f"{component}: screenshots do not prove distinct interaction states")


def validate_component(root: Path, component: str, evidence: object) -> None:
    if not isinstance(evidence, dict):
        raise ApprovalFailure(f"{component}: component evidence must be an object")
    report_path = repository_file(root, evidence.get("report"), f"{component} report")
    report = read_json(report_path)
    if report.get("schemaVersion") != 2 or report.get("component") != component:
        raise ApprovalFailure(f"{component}: report identity/schema mismatch")
    if report.get("resultStatus") != int(ResultStatus.PASSED):
        raise ApprovalFailure(f"{component}: report is not explicitly passed")
    build_hash = expect_hash(report.get("buildSha256"), f"{component} build hash")
    if evidence.get("buildSha256") != build_hash:
        raise ApprovalFailure(f"{component}: manifest and report build hashes differ")
    checks = report.get("checks")
    if not isinstance(checks, dict) or not checks or not all(value is True for value in checks.values()):
        raise ApprovalFailure(f"{component}: every report check must be explicitly true")
    for required in REQUIRED_CHECKS:
        if checks.get(required) is not True:
            raise ApprovalFailure(f"{component}: missing required approval check {required}")
    validate_device(component, evidence, report)
    validate_passes(component, evidence, report)
    validate_media(root, component, evidence, report)


def validate(root: Path, manifest_path: Path) -> int:
    manifest = read_json(manifest_path)
    parity = read_json(root / "resources/material-parity.json")
    reference = parity.get("reference")
    modules = parity.get("modules")
    if not isinstance(reference, dict) or not isinstance(modules, list):
        raise ApprovalFailure("material parity inventory is invalid")
    inventory = {
        component
        for module in modules
        if isinstance(module, dict)
        for component in module.get("components", [])
        if isinstance(component, str)
    }
    if len(inventory) != reference.get("componentCount"):
        raise ApprovalFailure("material parity component count does not match its inventory")
    approved = manifest.get("approvedComponents")
    evidence = manifest.get("componentEvidence")
    if not isinstance(approved, list) or not all(isinstance(value, str) for value in approved):
        raise ApprovalFailure("approvedComponents must be a string list")
    if len(approved) != len(set(approved)) or approved != sorted(approved):
        raise ApprovalFailure("approvedComponents must be unique and alphabetically sorted")
    if not isinstance(evidence, dict) or set(evidence) != set(approved):
        raise ApprovalFailure("componentEvidence must exactly match approvedComponents")
    component_count = manifest.get("componentCount")
    if component_count != len(inventory):
        raise ApprovalFailure("componentCount does not match the public Material inventory")
    if not set(approved) <= inventory:
        raise ApprovalFailure("approvedComponents contains a non-public component")
    candidates = manifest.get("emulatorCandidateComponents")
    if not isinstance(candidates, list) or not all(
        isinstance(value, str) for value in candidates
    ):
        raise ApprovalFailure("emulatorCandidateComponents must be a string list")
    if len(candidates) != len(set(candidates)) or candidates != sorted(candidates):
        raise ApprovalFailure(
            "emulatorCandidateComponents must be unique and alphabetically sorted"
        )
    if not set(candidates) <= inventory:
        raise ApprovalFailure(
            "emulatorCandidateComponents contains a non-public component"
        )
    if set(candidates) & set(approved):
        raise ApprovalFailure(
            "a component cannot be both physically approved and an emulator candidate"
        )
    expected_audit_status = (
        AuditStatus.COMPLETE if len(approved) == component_count else AuditStatus.IN_PROGRESS
    )
    if manifest.get("status") != int(expected_audit_status):
        raise ApprovalFailure("audit status does not match the approved component count")
    if manifest.get("defaultComponentStatus") != int(ComponentStatus.NOT_VERIFIED):
        raise ApprovalFailure("default component status must be the not-verified integer enum")
    for component in approved:
        validate_component(root, component, evidence[component])
    print(
        f"Validated {len(approved)}/{component_count} Android component approvals; "
        "every approved component has two independent physical passes and hashed media."
    )
    return len(approved)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Validate approved Android component evidence.")
    parser.add_argument(
        "manifest",
        type=Path,
        nargs="?",
        default=Path("docs/android-component-audit.json"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    manifest = args.manifest.resolve()
    root = Path(__file__).resolve().parent.parent
    try:
        manifest.relative_to(root)
        validate(root, manifest)
    except (ApprovalFailure, ValueError) as exception:
        print(f"FAIL Android component approvals: {exception}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
