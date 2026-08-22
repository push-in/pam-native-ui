#!/usr/bin/env python3
from __future__ import annotations

import argparse
import datetime
import importlib.util
import json
import os
import stat
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SPEC = importlib.util.spec_from_file_location("device_evidence", ROOT / "verify-device-evidence.py")
assert SPEC is not None and SPEC.loader is not None
contract = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(contract)

PERFORMANCE_CLASS = "dev.pam.mobileui.MobileUiHostPerformanceInstrumentedTest"
PERFORMANCE_TEST = "uiThreadLifecycleAndSliderGestureStayInsideTheFrameBudget"
RAW_METRICS = {
    "update": "hostUpdate",
    "sliderMove": "sliderMove",
    "calendarDraw": "calendarDraw",
    "dateTimeUpdate": "dateTimeUpdate",
    "accordionToggle": "accordionToggle",
    "checkboxToggle": "checkboxToggle",
    "radioSelection": "radioSelection",
    "progressUpdate": "progressUpdate",
    "switchToggle": "switchToggle",
    "tabsSelection": "tabsSelection",
    "sheetSnap": "sheetSnap",
    "sheetItemPress": "sheetItemPress",
    "anchoredPosition": "anchoredPosition",
    "menuSelection": "menuSelection",
    "inputState": "inputState",
    "inputSlotPress": "inputSlotPress",
    "feedbackUpdate": "feedbackUpdate",
    "fileTreeToggle": "fileTreeToggle",
    "markdownUpdate": "markdownUpdate",
    "tableLayout": "tableLayout",
    "lifecycle": "hostLifecycle",
}
COUNTERS = {
    "sliderMoves", "bridgeEvents", "calendarBridgeEvents", "dateTimeBridgeEvents",
    "accordionBridgeEvents", "checkboxBridgeEvents", "radioBridgeEvents",
    "progressBridgeEvents", "switchBridgeEvents", "tabsBridgeEvents",
    "sheetBridgeEvents", "sheetItemBridgeEvents", "anchoredBridgeEvents",
    "menuBridgeEvents", "inputStateBridgeEvents", "inputSlotBridgeEvents",
    "feedbackBridgeEvents", "fileTreeBridgeEvents", "markdownBridgeEvents",
    "tableBridgeEvents",
}


def regular(path: Path, maximum: int, label: str) -> bytes:
    if path.is_symlink():
        raise ValueError(f"{label} must not be a symlink")
    try:
        descriptor = os.open(path, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0))
    except OSError as error:
        raise ValueError(f"{label} must be a regular file") from error
    metadata = os.fstat(descriptor)
    if not stat.S_ISREG(metadata.st_mode) or not 1 <= metadata.st_size <= maximum:
        os.close(descriptor)
        raise ValueError(f"{label} has an invalid size")
    with os.fdopen(descriptor, "rb") as handle:
        return handle.read()


def parse_logcat(path: Path) -> dict[str, object]:
    try:
        text = regular(path, 8_388_608, "logcat capture").decode("utf-8")
    except UnicodeDecodeError as error:
        raise ValueError("logcat capture is not UTF-8") from error
    payloads: list[dict[str, object]] = []
    for line in text.splitlines():
        if "PamMobileUiBench" not in line or "{" not in line:
            continue
        try:
            value = json.loads(line[line.index("{"):])
        except json.JSONDecodeError as error:
            raise ValueError("benchmark log contains malformed JSON") from error
        if isinstance(value, dict):
            payloads.append(value)
    if len(payloads) != 1:
        raise ValueError("logcat capture must contain exactly one benchmark payload")
    raw = payloads[0]
    expected = {"device", "android", "build"} | set(RAW_METRICS) | COUNTERS
    if set(raw) != expected:
        raise ValueError("benchmark payload contains missing or unknown fields")
    for counter in COUNTERS:
        contract.integer(raw[counter], 0, 1_000_000, f"benchmark.{counter}")
    measurements: dict[str, object] = {}
    for source, target in RAW_METRICS.items():
        value = raw[source]
        if not isinstance(value, dict) or set(value) != {"sampleCount", "p50Us", "p95Us", "p99Us", "maxUs"}:
            raise ValueError(f"benchmark.{source} contains invalid quantiles")
        contract.integer(value["sampleCount"], 100, 1_000_000, f"benchmark.{source}.sampleCount")
        measurements[target] = value
    return {
        "device": raw["device"],
        "apiLevel": raw["android"],
        "buildTypeCode": 1 if raw["build"] == "debug" else 2 if raw["build"] == "release" else 0,
        "measurements": measurements,
    }


def parse_junit(path: Path) -> dict[str, int]:
    if path.is_symlink() or not path.is_dir():
        raise ValueError("JUnit source must be a regular directory")
    files = sorted(path.rglob("*.xml"))
    if not 1 <= len(files) <= 256:
        raise ValueError("JUnit source must contain between 1 and 256 XML reports")
    identities: set[tuple[str, str]] = set()
    passed = 0
    performance = 0
    for file in files:
        try:
            root = ET.fromstring(regular(file, 8_388_608, "JUnit XML"))
        except ET.ParseError as error:
            raise ValueError("JUnit source contains malformed XML") from error
        for case in root.iter("testcase"):
            identity = (case.attrib.get("classname", ""), case.attrib.get("name", ""))
            if identity in identities:
                raise ValueError("JUnit source contains duplicate test cases")
            identities.add(identity)
            failed = any(case.find(node) is not None for node in ("failure", "error", "skipped"))
            if failed:
                raise ValueError("JUnit source contains a failed, errored, or skipped test")
            passed += 1
            if identity[0].endswith(PERFORMANCE_CLASS) and identity[1] == PERFORMANCE_TEST:
                performance += 1
    if passed < 1 or performance != 1:
        raise ValueError("JUnit source must contain the passing performance benchmark exactly once")
    return {"passed": passed, "failed": 0}


def produce(logcat: Path, junit: Path, revision: str, captured_date: str) -> dict[str, object]:
    raw = parse_logcat(logcat)
    document = {
        "schemaVersion": 1,
        "platformCode": 1,
        "device": raw["device"],
        "apiLevel": raw["apiLevel"],
        "buildTypeCode": raw["buildTypeCode"],
        "capturedDate": captured_date,
        "sourceRevision": revision,
        "functionalResultCode": 1,
        "measurementCoverageCode": 2,
        "functionalTests": parse_junit(junit),
        "samplesPerOperation": max(
            measurement["sampleCount"] for measurement in raw["measurements"].values()
        ),
        "measurements": raw["measurements"],
        "resultCode": 1,
    }
    contract.verify(document, "captured evidence")
    return document


def write(path: Path, document: dict[str, object]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.is_symlink() or path.exists():
        raise ValueError("output must be a new regular path")
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
    parser = argparse.ArgumentParser(description="Capture canonical PAM Native UI device evidence")
    parser.add_argument("--logcat", type=Path, required=True)
    parser.add_argument("--junit", type=Path, required=True)
    parser.add_argument("--source-revision", required=True)
    parser.add_argument("--captured-date", default=datetime.date.today().isoformat())
    parser.add_argument("--output", type=Path, required=True)
    options = parser.parse_args()
    document = produce(options.logcat, options.junit, options.source_revision, options.captured_date)
    write(options.output, document)
    print(json.dumps(document, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
