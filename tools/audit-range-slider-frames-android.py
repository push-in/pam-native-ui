#!/usr/bin/env python3
"""Measure repeated real drags; emulator metrics are diagnostic, not approval."""
import argparse
import csv
import importlib.util
import io
import json
from pathlib import Path
import re
import sys
import statistics
import time

SPEC = importlib.util.spec_from_file_location(
    "range_frame_helpers", Path(__file__).with_name("audit-range-slider-android.py"),
)
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


def frame_measurements(metrics):
    patterns = {
        "renderedFrames": r"Total frames rendered:\s*(\d+)",
        "jankyFrames": r"Janky frames:\s*(\d+)",
        "legacyJankyFrames": r"Janky frames \(legacy\):\s*(\d+)",
        "frameP95Ms": r"95th percentile:\s*(\d+)ms",
        "frameP99Ms": r"99th percentile:\s*(\d+)ms",
        "highInputLatencyFrames": r"Number High input latency:\s*(\d+)",
        "slowUiThreadFrames": r"Number Slow UI thread:\s*(\d+)",
        "slowDrawCommandFrames": r"Number Slow issue draw commands:\s*(\d+)",
        "missedDeadlineFrames": r"Number Frame deadline missed:\s*(\d+)",
    }
    values = {}
    for name, pattern in patterns.items():
        match = re.search(pattern, metrics)
        if match is None:
            raise MODULE.AuditFailure(f"gfxinfo is missing {name}")
        values[name] = int(match.group(1))
    if values["renderedFrames"] < 12:
        raise MODULE.AuditFailure("insufficient frames to assess the drag sample")
    return values


def frame_stages(metrics):
    """Timestamp intervals, not attribution of GPU execution or input latency."""
    rows = []
    sections = metrics.split("---PROFILEDATA---")
    for index in range(1, len(sections), 2):
        rows.extend(row for row in csv.DictReader(io.StringIO(sections[index].strip()))
                    if row.get("Flags") == "0")
    stages = {}
    for label, start, end in (
        ("inputStartToDrawStart", "HandleInputStart", "DrawStart"),
        ("syncQueueToSyncStart", "SyncQueued", "SyncStart"),
        ("drawCommandsToSwap", "IssueDrawCommandsStart", "SwapBuffers"),
        ("swapToGpuCompletion", "SwapBuffers", "GpuCompleted"),
        ("intendedVsyncToCompletion", "IntendedVsync", "FrameCompleted"),
    ):
        values = []
        for row in rows:
            try:
                first, last = int(row[start]), int(row[end])
            except (KeyError, TypeError, ValueError):
                continue
            if 0 < first <= last < 2 ** 63 - 1:
                values.append((last - first) / 1_000_000)
        if values:
            values.sort()
            stages[label] = {
                "sampleCount": len(values),
                "medianMs": round(statistics.median(values), 3),
                "p95Ms": round(values[int((len(values) - 1) * .95)], 3),
                "maxMs": round(values[-1], 3),
            }
    return {"eligibleFrameCount": len(rows), "intervals": stages}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--serial", required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    audit = MODULE.RangeSliderAudit(
        args.serial, "dev.pam.mobileui.catalog", "dev.pam.nativeapp.PamActivity", args.output,
    )
    audit.prepare()
    try:
        # The interaction audit disables animations for geometry stability.
        # This measurement explicitly enables them and restores settings later.
        for setting in ("window_animation_scale", "transition_animation_scale", "animator_duration_scale"):
            audit.set_setting("global", setting, "1")
        audit.launch()
        root = audit.dump("baseline")
        area = MODULE.node_bounds(audit.slider_after(root, "Default"))
        audit.screenshot("baseline")
        before = audit.thumb_centers(args.output / "baseline.png", area)
        if len(before) != 2:
            raise MODULE.AuditFailure("baseline must expose two handles")
        track_y = audit.track_center_y(args.output / "baseline.png", area)
        span = (before[1] - before[0]) / 0.52
        target = before[0] + span * 0.16
        audit.shell("dumpsys", "gfxinfo", audit.package, "reset", timeout=30.0)
        for _ in range(6):
            for start, end in ((before[0], target), (target, before[0])):
                audit.shell("input", "swipe", str(round(start)), str(track_y),
                            str(round(end)), str(track_y), "600")
        time.sleep(0.5)
        # Collect metrics before screenshot/dump work adds unrelated frames.
        metrics = audit.shell("dumpsys", "gfxinfo", audit.package, "framestats", timeout=30.0)
        (args.output / "gfxinfo.txt").write_text(metrics, encoding="utf-8")
        audit.screenshot("after-drags")
        after_root = audit.dump("after-drags")
        after_area = MODULE.node_bounds(audit.slider_after(after_root, "Default"))
        after = audit.thumb_centers(args.output / "after-drags.png", after_area)
        if len(after) != 2 or any(abs(a - b) > 5 for a, b in zip(before, after)):
            raise MODULE.AuditFailure(f"repeated drags changed final geometry: {before} -> {after}")
        values = frame_measurements(metrics)
        report = {
            "schemaVersion": 1, "device": args.serial,
            "component": "p-range-slider", "drags": 12,
            "dragDurationMs": 600, "animationsEnabled": True,
            "finalGeometryPreserved": True, "measurements": values,
            "recentFrameStages": frame_stages(metrics),
            "approvalGranted": False,
            "limitations": "ADB-injected drags; gfxinfo is not touch latency. Emulator results do not establish physical-device smoothness.",
        }
        (args.output / "report.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
        print(json.dumps(report, indent=2))
    finally:
        audit.restore()


if __name__ == "__main__":
    main()
