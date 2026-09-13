#!/usr/bin/env python3
"""Measure repeated real drags; emulator metrics are diagnostic, not approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys
import time

SPEC = importlib.util.spec_from_file_location(
    "range_frame_helpers", Path(__file__).with_name("audit-range-slider-android.py"),
)
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


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
        metrics = audit.shell("dumpsys", "gfxinfo", audit.package, timeout=30.0)
        (args.output / "gfxinfo.txt").write_text(metrics, encoding="utf-8")
        audit.screenshot("after-drags")
        after_root = audit.dump("after-drags")
        after_area = MODULE.node_bounds(audit.slider_after(after_root, "Default"))
        after = audit.thumb_centers(args.output / "after-drags.png", after_area)
        if len(after) != 2 or any(abs(a - b) > 5 for a, b in zip(before, after)):
            raise MODULE.AuditFailure(f"repeated drags changed final geometry: {before} -> {after}")
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
        report = {
            "schemaVersion": 1, "device": args.serial,
            "component": "p-range-slider", "drags": 12,
            "dragDurationMs": 600, "animationsEnabled": True,
            "finalGeometryPreserved": True, "measurements": values,
            "approvalGranted": False,
            "limitations": "ADB-injected drags; gfxinfo is not touch latency. Emulator results do not establish physical-device smoothness.",
        }
        (args.output / "report.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
        print(json.dumps(report, indent=2))
    finally:
        audit.restore()


if __name__ == "__main__":
    main()
