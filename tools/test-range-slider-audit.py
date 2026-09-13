#!/usr/bin/env python3
"""Geometry detection must distinguish handles from same-color value bubbles."""
import importlib.util
from pathlib import Path
import sys
import unittest
from unittest.mock import Mock, patch
from types import SimpleNamespace

SCRIPT = Path(__file__).with_name("audit-range-slider-android.py")
SPEC = importlib.util.spec_from_file_location("range_slider_geometry_test", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class PixelFixture:
    width = 300
    height = 100

    def __init__(self, handles=(60, 240), bubbles=True):
        self.handles = handles
        self.bubbles = bubbles

    def convert(self, mode):
        return self

    def __enter__(self):
        return self

    def __exit__(self, *args):
        pass

    def getpixel(self, coordinate):
        x, y = coordinate
        if 10 <= x <= 290 and 68 <= y <= 72:
            return (5, 125, 80)
        if any((x - center) ** 2 + (y - 70) ** 2 <= 12 ** 2
               for center in self.handles):
            return (5, 125, 80)
        if self.bubbles and 15 <= y <= 45 and 30 <= x <= 90:
            # Separate green regions around a light glyph must not become
            # extra handles, even though they exceed the thumb's pixel count.
            return (245, 246, 250) if 55 <= x <= 62 else (5, 125, 80)
        return (245, 246, 250)


class RangeGeometryTest(unittest.TestCase):
    def test_logs_use_exact_current_user_package_uid(self):
        shell = Mock(side_effect=[
            "package:dev.pam.catalog.debug uid:10244\n"
            "package:dev.pam.catalog uid:10228\n",
            "runtime log",
        ])
        audit = SimpleNamespace(package="dev.pam.catalog", shell=shell)
        self.assertEqual(MODULE.SliderAudit.application_logs(audit), "runtime log")
        shell.assert_called_with("logcat", "--uid=10228", "-d", "-t", "1200", timeout=30.0)

    def test_logs_reject_a_different_package(self):
        audit = SimpleNamespace(
            package="dev.pam.catalog",
            shell=Mock(return_value="package:dev.pam.catalog.debug uid:10244\n"),
        )
        with self.assertRaises(MODULE.AuditFailure):
            MODULE.SliderAudit.application_logs(audit)

    def detect(self, fixture):
        with patch.object(MODULE.Image, "open", return_value=fixture):
            return MODULE.RangeSliderAudit.thumb_centers(
                Path("fixture.png"), MODULE.Bounds(0, 0, 300, 100),
            )

    def test_value_bubble_does_not_invent_a_third_handle(self):
        self.assertEqual(self.detect(PixelFixture()), [60.0, 240.0])

    def test_plain_handles_have_the_same_centers(self):
        self.assertEqual(self.detect(PixelFixture(bubbles=False)), [60.0, 240.0])

    def test_missing_handle_is_not_filled_in_by_a_bubble(self):
        self.assertEqual(self.detect(PixelFixture(handles=(240,))), [240.0])

    def test_track_and_bubble_without_handles_fail(self):
        with self.assertRaises(MODULE.AuditFailure):
            self.detect(PixelFixture(handles=()))


if __name__ == "__main__":
    unittest.main()
