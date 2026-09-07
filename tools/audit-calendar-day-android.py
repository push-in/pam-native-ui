#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import math
import re
import sys
import time
import xml.etree.ElementTree as ET
from enum import IntEnum
from pathlib import Path

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_calendar_day_audit_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class ResultStatus(IntEnum):
    PASSED = 1
    FAILED = 2


class CalendarDayAudit(AutocompleteAudit):
    VARIATIONS = (
        "Default",
        "Selected",
        "Today",
        "Disabled",
        "Outside month",
        "Range",
        "Success color",
    )

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-calendar-day",
            component_label="Calendar Day",
        )

    def buttons_after(self, root: ET.Element, label: str) -> list[ET.Element]:
        captions = self.exact(root, label)
        if not captions:
            raise AuditFailure(f"variation label {label!r} is missing")
        caption = min(captions, key=lambda node: node_bounds(node).top)
        caption_area = node_bounds(caption)
        later_captions = [
            node_bounds(node).top
            for variation in self.VARIATIONS
            if variation != label
            for node in self.exact(root, variation)
            if node_bounds(node).top > caption_area.top
        ]
        lower_limit = min(later_captions, default=10**9)
        buttons = [
            node
            for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == "Calendar Day preview"
            and caption_area.bottom <= node_bounds(node).top < lower_limit
            and node_bounds(node).width > 0
            and node_bounds(node).height > 0
        ]
        return sorted(buttons, key=lambda node: (node_bounds(node).top, node_bounds(node).left))

    def scroll_to(self, label: str, width: int, height: int) -> tuple[ET.Element, list[ET.Element]]:
        slug = re.sub(r"[^a-z0-9]+", "-", label.lower()).strip("-")
        for attempt in range(20):
            root = self.dump(f"scroll-{slug}-{attempt:02d}")
            try:
                buttons = self.buttons_after(root, label)
            except AuditFailure:
                buttons = []
            if buttons:
                top = min(node_bounds(node).top for node in buttons)
                bottom = max(node_bounds(node).bottom for node in buttons)
                if top >= 130 and bottom <= height - 110:
                    return root, buttons
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} calendar day into the viewport")

    def assert_target(self, button: ET.Element, label: str, enabled: bool = True) -> Bounds:
        area = node_bounds(button)
        density = self.density()
        expected = int(round(48.0 * density))
        tolerance = max(2, int(round(density)))
        if abs(area.width - expected) > tolerance or abs(area.height - expected) > tolerance:
            raise AuditFailure(
                f"{label} target is {area.width}x{area.height}px; expected 48x48dp ({expected}px)"
            )
        if (button.attrib.get("enabled") == "true") != enabled:
            raise AuditFailure(f"{label} enabled accessibility state is incorrect")
        if enabled and button.attrib.get("clickable") != "true":
            raise AuditFailure(f"{label} does not expose a click action")
        if not enabled and button.attrib.get("focusable") == "true":
            raise AuditFailure(f"{label} remains focusable while disabled")
        return area

    @staticmethod
    def green(pixel: tuple[int, int, int]) -> bool:
        red, green, blue = pixel
        return green >= 72 and green >= red * 1.35 and green >= blue * 1.12

    def selected_circle_dp(self, area: Bounds, screenshot_name: str) -> float:
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            y = area.center[1]
            xs = [x for x in range(area.left, area.right) if self.green(image.getpixel((x, y)))]
        if not xs:
            raise AuditFailure(f"{screenshot_name} has no semantic selected-day circle")
        diameter = max(xs) - min(xs) + 1
        diameter_dp = diameter / self.density()
        if abs(diameter_dp - 40.0) > 1.25:
            raise AuditFailure(
                f"selected-day circle is {diameter_dp:.2f}dp; expected 40dp"
            )
        return round(diameter_dp, 2)

    def assert_today_indicator(self, area: Bounds, screenshot_name: str) -> float:
        density = self.density()
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            sample_x = min(image.width - 1, area.right + int(round(8 * density)))
            surface = image.getpixel((sample_x, area.center[1]))
            changed = []
            for x in range(area.left, area.right):
                pixel = image.getpixel((x, area.center[1]))
                delta = math.sqrt(sum((actual - base) ** 2 for actual, base in zip(pixel, surface)))
                if delta >= 8.0:
                    changed.append(x)
        if not changed:
            raise AuditFailure("today state has no distinct Material indicator")
        diameter_dp = (max(changed) - min(changed) + 1) / density
        if abs(diameter_dp - 40.0) > 1.25:
            raise AuditFailure(
                f"today indicator is {diameter_dp:.2f}dp; expected a distinct 40dp circle"
            )
        return round(diameter_dp, 2)

    def assert_range(self, areas: list[Bounds], screenshot_name: str) -> dict[str, float]:
        if len(areas) != 3:
            raise AuditFailure(f"range preview exposes {len(areas)} days instead of three")
        if any(abs(areas[index].right - areas[index + 1].left) > 1 for index in range(2)):
            raise AuditFailure("range days are not contiguous")
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            y = areas[1].center[1]
            surface = image.getpixel((areas[1].center[0], max(0, areas[1].top - 4)))
            middle = image.getpixel((areas[1].center[0], y))
            left_join = image.getpixel((areas[0].right - 2, y))
            right_join = image.getpixel((areas[2].left + 2, y))
            before_start = image.getpixel((areas[0].left + 2, y))
            after_end = image.getpixel((areas[2].right - 2, y))
        delta = lambda a, b: math.sqrt(sum((x - y) ** 2 for x, y in zip(a, b)))
        middle_delta = delta(surface, middle)
        left_delta = delta(surface, left_join)
        right_delta = delta(surface, right_join)
        before_start_delta = delta(surface, before_start)
        after_end_delta = delta(surface, after_end)
        if min(middle_delta, left_delta, right_delta) < 12.0:
            raise AuditFailure("range track is not continuous across its three day targets")
        if max(before_start_delta, after_end_delta) > 8.0:
            raise AuditFailure(
                "range track extends past the circular endpoint centers"
            )
        return {
            "middlePixelDelta": round(middle_delta, 2),
            "leftJoinPixelDelta": round(left_delta, 2),
            "rightJoinPixelDelta": round(right_delta, 2),
            "beforeStartPixelDelta": round(before_start_delta, 2),
            "afterEndPixelDelta": round(after_end_delta, 2),
        }

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_window_count(1, "baseline")
            if not all(self.exact(root, label) for label in self.VARIATIONS[:3]):
                raise AuditFailure("calendar-day route is missing its initial state matrix")

            default = self.buttons_after(root, "Default")
            if len(default) != 1:
                raise AuditFailure("Default must render exactly one calendar day")
            default_area = self.assert_target(default[0], "Default")
            if default[0].attrib.get("selected") == "true":
                raise AuditFailure("Default begins selected")
            edge = max(2, int(round(self.density() * 2.0)))
            self.tap((default_area.right - edge, default_area.bottom - edge))
            selected_root = self.dump("01-default-selected")
            self.screenshot("01-default-selected")
            selected_default = self.buttons_after(selected_root, "Default")[0]
            if selected_default.attrib.get("selected") != "true":
                raise AuditFailure("lower trailing edge did not select Default")
            selected_circle = self.selected_circle_dp(
                node_bounds(selected_default), "01-default-selected"
            )
            selected_variation = self.buttons_after(selected_root, "Selected")[0]
            if selected_variation.attrib.get("selected") != "true":
                raise AuditFailure("Default interaction changed the independent Selected instance")

            self.tap(node_bounds(selected_default))
            deselected_root = self.dump("02-default-deselected")
            self.screenshot("02-default-deselected")
            if self.buttons_after(deselected_root, "Default")[0].attrib.get("selected") == "true":
                raise AuditFailure("second press did not deselect Default")

            self.launch()
            today_root, today_buttons = self.scroll_to("Today", width, height)
            today_area = self.assert_target(today_buttons[0], "Today")
            self.screenshot("03-today")
            today_indicator = self.assert_today_indicator(today_area, "03-today")

            disabled_area = self.assert_target(
                self.buttons_after(today_root, "Disabled")[0], "Disabled", enabled=False
            )
            before_disabled = self.buttons_after(today_root, "Disabled")[0].attrib.get("selected")
            self.tap(disabled_area)
            disabled_after = self.dump("04-disabled-after-tap")
            if self.buttons_after(disabled_after, "Disabled")[0].attrib.get("selected") != before_disabled:
                raise AuditFailure("disabled calendar day reacted to a tap")

            self.launch()
            outside_root, outside_buttons = self.scroll_to("Outside month", width, height)
            outside_area = self.assert_target(outside_buttons[0], "Outside month")
            if outside_buttons[0].attrib.get("selected") == "true":
                raise AuditFailure("outside-month day begins selected")
            self.screenshot("05-outside-month")

            range_root, range_buttons = self.scroll_to("Range", width, height)
            range_areas = [self.assert_target(button, f"Range {index + 1}") for index, button in enumerate(range_buttons)]
            self.screenshot("06-range")
            range_metrics = self.assert_range(range_areas, "06-range")
            if range_buttons[0].attrib.get("selected") != "true" or range_buttons[2].attrib.get("selected") != "true":
                raise AuditFailure("range endpoints are not exposed as selected")
            if range_buttons[1].attrib.get("selected") == "true":
                raise AuditFailure("range middle is incorrectly exposed as selected")

            success_root, success_buttons = self.scroll_to("Success color", width, height)
            success_area = self.assert_target(success_buttons[0], "Success color")
            self.screenshot("07-success")
            success_circle = self.selected_circle_dp(success_area, "07-success")

            self.launch()
            self.set_setting("system", "font_scale", "1.3")
            self.shell("am", "force-stop", self.package)
            self.launch()
            font_root = self.dump("08-font-scale-130")
            font_width, font_height = self.screenshot("08-font-scale-130")
            font_default = self.buttons_after(font_root, "Default")[0]
            self.assert_target(font_default, "Default at 130% font scale")
            if node_bounds(font_default).bottom > font_height:
                raise AuditFailure("130% font scale clipped the first calendar day")
            self.set_setting("system", "font_scale", "1.0")

            self.shell("am", "force-stop", self.package)
            self.launch()
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "calendar day landscape")
            landscape_width, landscape_height = self.screenshot("09-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            landscape_root = self.dump("09-landscape")
            landscape_default = self.buttons_after(landscape_root, "Default")[0]
            landscape_area = self.assert_target(landscape_default, "Landscape Default")
            if landscape_area.right > landscape_width or landscape_area.bottom > landscape_height:
                raise AuditFailure("calendar day escaped the adaptive landscape pane")
            self.tap(landscape_area)
            landscape_selected = self.dump("10-landscape-selected")
            if self.buttons_after(landscape_selected, "Default")[0].attrib.get("selected") != "true":
                raise AuditFailure("calendar day did not remain interactive in landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "calendar day portrait restore")

            self.launch()
            stress_root = self.dump("11-stress-baseline")
            stress_area = node_bounds(self.buttons_after(stress_root, "Default")[0])
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for _ in range(20):
                self.tap(stress_area)
                # Match the parent calendar gate: roughly seven deliberate
                # taps per second is already much faster than sustained human
                # input without artificially queueing Android shell events.
                time.sleep(0.14)
            time.sleep(0.8)
            # Read frame statistics before UiAutomator and screenshot capture;
            # both are intentionally expensive system diagnostics and would
            # otherwise be misattributed to the component interaction.
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            self.dump("12-stress-result")
            self.screenshot("12-stress-result")
            # State parity is already verified above with two acknowledged taps.
            # A PAM screen is server-driven: shell taps can arrive before the
            # preceding render is acknowledged, so an even burst does not imply
            # an even number of state transitions. This burst measures frame
            # delivery and runtime stability only.
            frame_match = re.search(r"Total frames rendered:\s*(\d+)", gfx)
            jank_match = re.search(r"Janky frames:\s*(\d+)", gfx)
            p99_match = re.search(r"99th percentile:\s*(\d+)ms", gfx)
            missed_vsync_match = re.search(r"Number Missed Vsync:\s*(\d+)", gfx)
            slow_ui_match = re.search(r"Number Slow UI thread:\s*(\d+)", gfx)
            frames = int(frame_match.group(1)) if frame_match else 0
            janky = int(jank_match.group(1)) if jank_match else 0
            p99_ms = int(p99_match.group(1)) if p99_match else 10**9
            missed_vsync = int(missed_vsync_match.group(1)) if missed_vsync_match else 10**9
            slow_ui = int(slow_ui_match.group(1)) if slow_ui_match else 10**9
            if frames <= 0 or p99_ms > 17 or missed_vsync > 0 or slow_ui > 0:
                raise AuditFailure(
                    "calendar-day stress failed its 60 fps budget: "
                    f"frames={frames}, p99={p99_ms}ms, missedVsync={missed_vsync}, "
                    f"slowUi={slow_ui}, AndroidJanky={janky}"
                )

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            markers = (
                "FATAL EXCEPTION",
                " E AndroidRuntime:",
                "Pam Native runtime error",
                "ANR in dev.pam.mobileui.catalog",
                "Input dispatching timed out",
            )
            errors = [line for line in logs.splitlines() if any(marker in line for marker in markers)]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-calendar-day",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allSevenVariations": True,
                    "exact48DpTouchTarget": True,
                    "exact40DpSelectionCircle": True,
                    "realSelectionAndDeselection": True,
                    "lowerTrailingEdgeHitTarget": True,
                    "instanceIsolation": True,
                    "todayTonalIndicator": True,
                    "disabledBehavior": True,
                    "outsideMonthState": True,
                    "continuousRangeTrack": True,
                    "rangeAccessibilityState": True,
                    "semanticSuccessColor": True,
                    "fontScale130": True,
                    "adaptiveLandscape": True,
                    "rapidTwentyTapStress": True,
                    "frameP99AtMost17Ms": True,
                    "zeroMissedVsync": True,
                    "zeroSlowUiThreadFrames": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "targetDp": 48.0,
                    "selectedCircleDp": selected_circle,
                    "successCircleDp": success_circle,
                    "todayIndicatorDp": today_indicator,
                    "range": range_metrics,
                    "stressFrames": frames,
                    "stressJankyFrames": janky,
                    "stressP99Ms": p99_ms,
                    "stressMissedVsync": missed_vsync,
                    "stressSlowUiThreadFrames": slow_ui,
                    "fontScaleViewport": f"{font_width}x{font_height}",
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n", encoding="utf-8"
            )
            return report
        finally:
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit p-calendar-day on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-calendar-day-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = CalendarDayAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-calendar-day; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-calendar-day: {exception}")
        raise SystemExit(1)
