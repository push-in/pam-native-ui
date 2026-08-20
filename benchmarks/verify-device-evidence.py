#!/usr/bin/env python3
from __future__ import annotations

import argparse
import datetime
import json
import os
import re
import stat
from enum import IntEnum
from pathlib import Path

MAX_REPORT_BYTES = 1_048_576
MIN_SAMPLES = 1_000
REQUIRED_MEASUREMENTS = {
    "hostUpdate", "sliderMove", "calendarDraw", "dateTimeUpdate",
    "accordionToggle", "checkboxToggle", "radioSelection", "progressUpdate",
    "switchToggle", "tabsSelection", "sheetSnap", "sheetItemPress",
    "anchoredPosition", "menuSelection", "inputState", "inputSlotPress",
    "feedbackUpdate", "fileTreeToggle", "markdownUpdate", "tableLayout", "hostLifecycle",
}
HISTORICAL_MEASUREMENTS = {"imageNavigation", "branchNavigation", "promptSubmission"}
BUDGET_US = {name: 4_000 for name in REQUIRED_MEASUREMENTS | HISTORICAL_MEASUREMENTS}
BUDGET_US["hostLifecycle"] = 8_000


class PlatformCode(IntEnum):
    ANDROID = 1


class BuildTypeCode(IntEnum):
    DEBUG = 1
    RELEASE = 2


class MeasurementCoverageCode(IntEnum):
    P99_ONLY = 1
    COMPLETE_QUANTILES = 2


class ResultCode(IntEnum):
    PASSED = 1
    FAILED = 2


def read_report(path: Path) -> dict[str, object]:
    if path.is_symlink():
        raise ValueError(f"{path}: report must not be a symlink")
    try:
        descriptor = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))
    except OSError as error:
        raise ValueError(f"{path}: report must be a regular file") from error
    metadata = os.fstat(descriptor)
    if not stat.S_ISREG(metadata.st_mode) or not 1 <= metadata.st_size <= MAX_REPORT_BYTES:
        os.close(descriptor)
        raise ValueError(f"{path}: report must contain 1 byte to 1 MiB")
    with os.fdopen(descriptor, "rb") as handle:
        payload = handle.read()
    try:
        document = json.loads(payload)
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise ValueError(f"{path}: report is not valid JSON") from error
    if not isinstance(document, dict):
        raise ValueError(f"{path}: report root must be an object")
    return document


def exact_keys(value: dict[str, object], required: set[str], optional: set[str], label: str) -> None:
    keys = set(value)
    if not required <= keys or not keys <= required | optional:
        raise ValueError(f"{label}: missing or unknown fields")


def integer(value: object, minimum: int, maximum: int, label: str) -> int:
    if isinstance(value, bool) or not isinstance(value, int) or not minimum <= value <= maximum:
        raise ValueError(f"{label}: expected integer from {minimum} through {maximum}")
    return value


def verify(document: dict[str, object], label: str) -> None:
    required = {
        "schemaVersion", "platformCode", "device", "apiLevel", "buildTypeCode",
        "capturedDate", "sourceRevision", "functionalResultCode",
        "measurementCoverageCode", "samplesPerOperation", "measurements", "resultCode",
    }
    exact_keys(document, required, {"functionalTests"}, label)
    if document["schemaVersion"] != 1 or document["platformCode"] != PlatformCode.ANDROID.value:
        raise ValueError(f"{label}: unsupported schema or platform code")
    device = document["device"]
    if not isinstance(device, str) or not 3 <= len(device) <= 128 or device.strip() != device:
        raise ValueError(f"{label}: invalid device identity")
    integer(document["apiLevel"], 26, 100, f"{label}.apiLevel")
    integer(document["buildTypeCode"], 1, 2, f"{label}.buildTypeCode")
    if document["functionalResultCode"] != ResultCode.PASSED.value or document["resultCode"] != ResultCode.PASSED.value:
        raise ValueError(f"{label}: report or functional tests did not pass")
    coverage = integer(document["measurementCoverageCode"], 1, 2, f"{label}.measurementCoverageCode")
    maximum_samples = integer(document["samplesPerOperation"], MIN_SAMPLES, 1_000_000, f"{label}.samplesPerOperation")
    if not isinstance(document["capturedDate"], str) or re.fullmatch(r"\d{4}-\d{2}-\d{2}", document["capturedDate"]) is None:
        raise ValueError(f"{label}: invalid capture date")
    try:
        datetime.date.fromisoformat(document["capturedDate"])
    except ValueError as error:
        raise ValueError(f"{label}: invalid capture date") from error
    if not isinstance(document["sourceRevision"], str) or re.fullmatch(r"[0-9a-f]{40}", document["sourceRevision"]) is None:
        raise ValueError(f"{label}: invalid source revision")
    functional = document.get("functionalTests")
    if functional is not None:
        if not isinstance(functional, dict):
            raise ValueError(f"{label}.functionalTests: expected object")
        exact_keys(functional, {"passed", "failed"}, set(), f"{label}.functionalTests")
        integer(functional["passed"], 1, 100_000, f"{label}.functionalTests.passed")
        if functional["failed"] != 0:
            raise ValueError(f"{label}: functional test failure recorded")
    measurements = document["measurements"]
    inventory = set(measurements) if isinstance(measurements, dict) else set()
    if inventory not in (REQUIRED_MEASUREMENTS, REQUIRED_MEASUREMENTS | HISTORICAL_MEASUREMENTS):
        raise ValueError(f"{label}: measurement inventory does not match the current or historical contract")
    expected_keys = {"sampleCount", "p99Us"} if coverage == MeasurementCoverageCode.P99_ONLY.value else {"sampleCount", "p50Us", "p95Us", "p99Us", "maxUs"}
    observed_samples: list[int] = []
    for name in sorted(inventory):
        measurement = measurements[name]
        if not isinstance(measurement, dict) or set(measurement) != expected_keys:
            raise ValueError(f"{label}.{name}: quantiles do not match coverage code")
        sample_count = integer(measurement["sampleCount"], 100, 1_000_000, f"{label}.{name}.sampleCount")
        observed_samples.append(sample_count)
        for key in expected_keys - {"sampleCount"}:
            integer(measurement[key], 0, 60_000_000, f"{label}.{name}.{key}")
        if coverage == MeasurementCoverageCode.COMPLETE_QUANTILES.value:
            ordered = [measurement[key] for key in ("p50Us", "p95Us", "p99Us", "maxUs")]
            if ordered != sorted(ordered):
                raise ValueError(f"{label}.{name}: quantiles are not monotonic")
        if measurement["p99Us"] >= BUDGET_US[name]:
            raise ValueError(f"{label}.{name}: p99 exceeds the {BUDGET_US[name]} microsecond budget")
    if max(observed_samples) != maximum_samples:
        raise ValueError(f"{label}: samplesPerOperation must equal the largest metric sampleCount")


def verify_reports(reports: list[tuple[str, dict[str, object]]]) -> None:
    identities: set[tuple[str, int, str]] = set()
    for label, document in reports:
        verify(document, label)
        identity = (str(document["device"]), int(document["apiLevel"]), str(document["capturedDate"]))
        if identity in identities:
            raise ValueError(f"{label}: duplicate device/API/date evidence")
        identities.add(identity)


def main() -> int:
    parser = argparse.ArgumentParser(description="Verify PAM Mobile UI physical-device evidence")
    parser.add_argument("reports", type=Path, nargs="+")
    options = parser.parse_args()
    if len(options.reports) > 32:
        raise ValueError("at most 32 evidence reports may be verified at once")
    reports: list[tuple[str, dict[str, object]]] = []
    for path in options.reports:
        reports.append((str(path), read_report(path)))
    verify_reports(reports)
    print(f"Verified {len(options.reports)} PAM Mobile UI physical-device evidence report(s).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
