#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import unittest

SPEC = importlib.util.spec_from_file_location(
    "range_frame_diagnostic", Path(__file__).with_name("audit-range-slider-frames-android.py"),
)
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

SAMPLE = """Total frames rendered: 404
Janky frames: 54 (13.37%)
Janky frames (legacy): 0 (0.00%)
95th percentile: 16ms
99th percentile: 16ms
Number High input latency: 325
Number Slow UI thread: 0
Number Slow issue draw commands: 54
Number Frame deadline missed: 54
Number Frame deadline missed (legacy): 0
"""


class FrameMeasurementTest(unittest.TestCase):
    def test_stage_intervals_exclude_flags_and_unavailable_timestamps(self):
        result = MODULE.frame_stages("""---PROFILEDATA---
Flags,HandleInputStart,DrawStart,SwapBuffers,GpuCompleted,
0,1000000,2000000,3000000,8000000,
1,1000000,99000000,3000000,99000000,
0,0,2000000,3000000,0,
---PROFILEDATA---""")
        self.assertEqual(result["eligibleFrameCount"], 2)
        self.assertEqual(result["intervals"]["inputStartToDrawStart"]["sampleCount"], 1)
        self.assertEqual(result["intervals"]["swapToGpuCompletion"]["p95Ms"], 5)
        self.assertNotIn("syncQueueToSyncStart", result["intervals"])

    def test_missing_frame_data_is_not_invented(self):
        self.assertEqual(MODULE.frame_stages(SAMPLE), {"eligibleFrameCount": 0, "intervals": {}})

    def test_current_jank_is_not_hidden_by_legacy_zero(self):
        result = MODULE.frame_measurements(SAMPLE)
        self.assertEqual(result["jankyFrames"], 54)
        self.assertEqual(result["legacyJankyFrames"], 0)
        self.assertEqual(result["highInputLatencyFrames"], 325)
        self.assertEqual(result["missedDeadlineFrames"], 54)

    def test_missing_counter_is_not_treated_as_zero(self):
        with self.assertRaisesRegex(MODULE.MODULE.AuditFailure, "highInputLatencyFrames"):
            MODULE.frame_measurements(SAMPLE.replace("Number High input latency: 325\n", ""))

    def test_insufficient_frames_fail(self):
        with self.assertRaisesRegex(MODULE.MODULE.AuditFailure, "insufficient frames"):
            MODULE.frame_measurements(SAMPLE.replace("rendered: 404", "rendered: 2"))


if __name__ == "__main__":
    unittest.main()
