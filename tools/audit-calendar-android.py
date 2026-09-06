#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import math
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from enum import IntEnum
from pathlib import Path

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_calendar_audit_shared", MODULE_PATH)
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


class CalendarAudit(AutocompleteAudit):
    VARIATIONS = (
        "Single date",
        "Multiple dates",
        "Date range",
        "Limits and unavailable dates",
        "Adjacent dates hidden",
        "Week numbers",
        "Monday first",
        "Dynamic four-week month",
        "Disabled",
        "Read only",
        "RTL",
        "Success color",
    )
    FIXED = set(VARIATIONS) - {"Dynamic four-week month"}

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-calendar",
            component_label="Calendar",
        )
        self.original_font_scale = ""

    def launch(self, scenario: str = "interactive") -> None:
        super().launch(scenario)
        self.start_task_lock()

    def dump(self, name: str) -> ET.Element:
        self.assert_foreground(f"calendar hierarchy {name}")
        run_name = re.sub(r"[^A-Za-z0-9_.-]", "-", self.output.name)
        remote = f"/sdcard/{run_name}-{name}.xml"
        local = self.output / f"{name}.xml"
        last_problem = "UiAutomator did not return a hierarchy"
        for attempt in range(5):
            self.assert_foreground(f"calendar hierarchy {name} attempt {attempt + 1}")
            try:
                self.adb("shell", "rm", "-f", remote, timeout=8.0)
            except subprocess.TimeoutExpired as exception:
                last_problem = f"ADB cleanup timeout: {exception}"
                if attempt < 4:
                    time.sleep(1.2)
                    continue
                break
            dump_report = ""
            try:
                dump_report = self.shell("uiautomator", "dump", "--compressed", remote, timeout=30.0)
            except (subprocess.TimeoutExpired, AuditFailure) as exception:
                self.adb("shell", "pkill", "-f", "uiautomator", check=False)
                last_problem = str(exception)
            else:
                payload = self.adb("exec-out", "cat", remote, timeout=10.0, check=False)
                assert isinstance(payload, str)
                if payload.lstrip().startswith("<?xml") and "<hierarchy" in payload:
                    try:
                        root = ET.fromstring(payload)
                    except ET.ParseError as exception:
                        last_problem = f"invalid XML: {exception}"
                    else:
                        local.write_text(payload, encoding="utf-8")
                        return root
                else:
                    details = payload.strip() or "missing dump file"
                    last_problem = f"{dump_report.strip()}; {details}".strip("; ")
                    self.adb("shell", "pkill", "-f", "uiautomator", check=False)
            if attempt < 4:
                time.sleep(1.2)
        raise AuditFailure(f"could not collect {name!r}: {last_problem}")

    @staticmethod
    def visible(nodes: list[ET.Element]) -> list[ET.Element]:
        return [node for node in nodes if node_bounds(node).width > 0 and node_bounds(node).height > 0]

    def calendars(self, root: ET.Element) -> list[ET.Element]:
        return self.visible([
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.CalendarView"
            and node.attrib.get("content-desc") == "Calendar preview"
        ])

    def calendar_after(self, root: ET.Element, variation: str) -> ET.Element:
        captions = self.visible([
            node for node in self.exact(root, variation)
            if node.attrib.get("class") == "android.widget.TextView"
        ])
        if not captions:
            raise AuditFailure(f"calendar caption {variation!r} is missing")
        caption = min(captions, key=lambda node: node_bounds(node).top)
        bottom = node_bounds(caption).bottom
        candidates = [
            node for node in self.calendars(root)
            if node_bounds(node).top >= bottom
        ]
        if not candidates:
            raise AuditFailure(f"calendar after {variation!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_step(self, width: int, height: int, upward: bool, distance: int | None = None) -> None:
        if distance is None:
            distance = int(round(height * 0.42))
        distance = max(1, min(distance, int(round(height * 0.48))))
        if upward:
            start = int(height * 0.80)
            end = max(int(height * 0.20), start - distance)
        else:
            start = int(height * 0.30)
            end = min(int(height * 0.82), start + distance)
        duration = "250" if distance < int(round(height * 0.15)) else "500"
        self.shell("input", "swipe", str(width // 2), str(start), str(width // 2), str(end), duration)
        time.sleep(0.6)

    def scroll_to_calendar(
        self,
        variation: str,
        width: int,
        height: int,
        prefix: str,
        require_full: bool = True,
    ) -> tuple[ET.Element, ET.Element]:
        density = self.density()
        for attempt in range(36):
            root = self.dump(f"{prefix}-{variation.lower().replace(' ', '-')}-{attempt:02d}")
            scrolls = self.visible([
                node for node in self.nodes(root)
                if node.attrib.get("class") == "android.widget.ScrollView"
            ])
            viewport = max(
                (node_bounds(node) for node in scrolls),
                key=lambda area: area.width * area.height,
                default=Bounds(0, 0, width, height),
            )
            safe_top = viewport.top + int(round(8 * density))
            safe_bottom = min(viewport.bottom, self.physical_content_bottom(width, height)) - int(round(8 * density))
            try:
                calendar = self.calendar_after(root, variation)
            except AuditFailure:
                self.scroll_step(width, height, True)
                continue
            area = node_bounds(calendar)
            expected_height = int(round((376.0 if variation in self.FIXED else 280.0) * density))
            height_complete = abs(area.height - expected_height) <= int(round(1.6 * density))
            if area.top >= safe_top and (not require_full or (area.bottom <= safe_bottom and height_complete)):
                return root, calendar
            if area.top < safe_top:
                self.scroll_step(
                    width,
                    height,
                    False,
                    safe_top - area.top + int(round(8 * density)),
                )
            else:
                desired_top = safe_bottom - expected_height
                self.scroll_step(
                    width,
                    height,
                    True,
                    max(int(round(16 * density)), area.top - desired_top),
                )
        raise AuditFailure(f"could not place {variation!r} in the viewport")

    @staticmethod
    def header_nodes(calendar: ET.Element) -> list[ET.Element]:
        prefixes = ("Previous month", "Select month,", "Select year,", "Next month")
        return [
            node for node in calendar.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc", "").startswith(prefixes)
            and node_bounds(node).height > 0
        ]

    @staticmethod
    def date_nodes(calendar: ET.Element) -> list[ET.Element]:
        header_prefixes = ("Previous month", "Select month,", "Select year,", "Next month")
        return [
            node for node in calendar.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
            and re.search(r", 20\d\d$", node.attrib.get("content-desc", ""))
            and not node.attrib.get("content-desc", "").startswith(header_prefixes)
            and node_bounds(node).height > 0
        ]

    def july(self, calendar: ET.Element, day: int) -> ET.Element:
        pattern = re.compile(rf"julho {day}, 2026$", re.IGNORECASE)
        matches = [
            node for node in self.date_nodes(calendar)
            if pattern.search(node.attrib.get("content-desc", ""))
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one July {day} node, found {len(matches)}")
        return matches[0]

    @staticmethod
    def selected(calendar: ET.Element) -> set[str]:
        return {
            node.attrib.get("content-desc", "")
            for node in CalendarAudit.date_nodes(calendar)
            if node.attrib.get("selected") == "true"
        }

    def assert_geometry(
        self,
        root: ET.Element,
        calendar: ET.Element,
        variation: str,
        width: int,
        height: int,
    ) -> dict[str, float | int]:
        density = self.density()
        tolerance = max(3, int(round(1.5 * density)))
        area = node_bounds(calendar)
        expected_height = 376.0 if variation in self.FIXED else 280.0
        if abs(area.height / density - expected_height) > 1.6:
            raise AuditFailure(
                f"{variation}: calendar height is {area.height / density:.1f}dp, expected {expected_height:.0f}dp"
            )
        captions = self.visible([
            node for node in self.exact(root, variation)
            if node.attrib.get("class") == "android.widget.TextView"
            and node_bounds(node).bottom <= area.top
        ])
        if not captions:
            raise AuditFailure(f"{variation}: caption is not above the calendar")
        caption = max(captions, key=lambda node: node_bounds(node).bottom)
        caption_area = node_bounds(caption)
        gap = (area.top - caption_area.bottom) / density
        if abs(gap - 8.0) > 1.6 or abs(area.left - caption_area.left) > tolerance:
            raise AuditFailure(f"{variation}: caption alignment/gap is invalid ({gap:.1f}dp)")

        headers = self.header_nodes(calendar)
        if len(headers) != 4:
            raise AuditFailure(f"{variation}: expected four accessible header controls, found {len(headers)}")
        for header in headers:
            header_area = node_bounds(header)
            if abs(header_area.height / density - 48.0) > 1.5:
                raise AuditFailure(f"{variation}: {header.attrib.get('content-desc')} is not 48dp")
            if header.attrib.get("focusable") != "true":
                raise AuditFailure(f"{variation}: header control lost focus semantics")

        dates = self.date_nodes(calendar)
        expected_dates = 31 if variation == "Adjacent dates hidden" else (28 if variation == "Dynamic four-week month" else 42)
        if len(dates) != expected_dates:
            raise AuditFailure(f"{variation}: found {len(dates)} visible date nodes, expected {expected_dates}")
        heights = [node_bounds(node).height / density for node in dates]
        if any(abs(value - 48.0) > 1.6 for value in heights):
            raise AuditFailure(f"{variation}: date rows are not exactly 48dp")
        first_top = min(node_bounds(node).top for node in dates)
        if abs((first_top - area.top) / density - 88.0) > 1.6:
            raise AuditFailure(f"{variation}: header plus weekday lane is not 88dp")
        columns = 8 if variation == "Week numbers" else 7
        expected_width = area.width / density / columns
        if any(abs(node_bounds(node).width / density - expected_width) > 1.7 for node in dates):
            raise AuditFailure(f"{variation}: date columns are not evenly distributed")
        if area.left < 0 or area.right > width or area.top < 0 or area.bottom > height:
            raise AuditFailure(f"{variation}: calendar escaped the viewport")
        return {
            "heightDp": round(area.height / density, 2),
            "captionGapDp": round(gap, 2),
            "headerTargetDp": 48,
            "weekdayLaneDp": 32,
            "dateRowDp": 48,
            "columns": columns,
            "dateNodes": len(dates),
        }

    def assert_selected_circle(self, calendar: ET.Element, name: str) -> dict[str, float | tuple[int, int, int]]:
        selected = [node for node in self.date_nodes(calendar) if node.attrib.get("selected") == "true"]
        if not selected:
            raise AuditFailure("selected-circle check has no selected date")
        self.screenshot(name)
        density = self.density()
        area = node_bounds(selected[0])
        with Image.open(self.output / f"{name}.png").convert("RGB") as image:
            sample_y = area.center[1] - int(round(8 * density))
            sample = image.getpixel((area.center[0], sample_y))
            if not (sample[1] > sample[0] * 1.7 and sample[1] > sample[2] * 1.25):
                raise AuditFailure(f"selected date does not use a semantic green container: {sample}")
            center_y = area.center[1]
            matching = []
            for x in range(area.left, area.right):
                pixel = image.getpixel((x, center_y))
                if pixel[1] > pixel[0] * 1.7 and pixel[1] > pixel[2] * 1.25:
                    matching.append(x)
        if not matching:
            raise AuditFailure("could not measure selected date container")
        diameter = (max(matching) - min(matching) + 1) / density
        if abs(diameter - 40.0) > 2.0:
            raise AuditFailure(f"selected circle diameter is {diameter:.1f}dp, expected 40dp")
        return {"diameterDp": round(diameter, 2), "containerRgb": sample}

    def assert_range_track(self, calendar: ET.Element, name: str) -> dict[str, object]:
        """Prove that range fill is continuous and joins both endpoint circles."""
        self.screenshot(name)
        density = self.density()
        start = node_bounds(self.july(calendar, 12))
        middle = node_bounds(self.july(calendar, 15))
        end = node_bounds(self.july(calendar, 20))
        plain = node_bounds(self.july(calendar, 22))
        offset_x = int(round(22 * density))
        offset_y = int(round(14 * density))

        with Image.open(self.output / f"{name}.png").convert("RGB") as image:
            def sample(area: Bounds, x_offset: int = 0) -> tuple[int, int, int]:
                x = max(area.left, min(area.right - 1, area.center[0] + x_offset))
                y = max(area.top, min(area.bottom - 1, area.center[1] + offset_y))
                return image.getpixel((x, y))

            def distance(left: tuple[int, int, int], right: tuple[int, int, int]) -> float:
                return math.sqrt(sum((a - b) ** 2 for a, b in zip(left, right)))

            background = sample(plain)
            middle_track = sample(middle)
            start_leading = sample(start, -offset_x)
            start_trailing = sample(start, offset_x)
            end_leading = sample(end, -offset_x)
            end_trailing = sample(end, offset_x)

        minimum_delta = 12.0
        filled = {
            "middle": distance(middle_track, background),
            "startTrailing": distance(start_trailing, background),
            "endLeading": distance(end_leading, background),
        }
        empty = {
            "startLeading": distance(start_leading, background),
            "endTrailing": distance(end_trailing, background),
        }
        if any(delta < minimum_delta for delta in filled.values()):
            raise AuditFailure(f"range track has a visual gap: {filled}")
        if any(delta > 8.0 for delta in empty.values()):
            raise AuditFailure(f"range track leaks outside its endpoints: {empty}")
        return {
            "backgroundRgb": background,
            "trackRgb": middle_track,
            "filledDelta": {key: round(value, 2) for key, value in filled.items()},
            "emptyDelta": {key: round(value, 2) for key, value in empty.items()},
        }

    def audit_all_geometry(self, width: int, height: int) -> dict[str, dict[str, float | int]]:
        geometry: dict[str, dict[str, float | int]] = {}
        for index, variation in enumerate(self.VARIATIONS):
            root, calendar = self.scroll_to_calendar(variation, width, height, "geometry")
            geometry[variation] = self.assert_geometry(root, calendar, variation, width, height)
            expected_selected = 2 if variation in {"Multiple dates", "Date range"} else 1
            if len(self.selected(calendar)) != expected_selected:
                raise AuditFailure(f"{variation}: wrong initial selected-date count")
            if index in {0, 2, 5, 7, 11}:
                self.screenshot(f"geometry-{index + 1:02d}")
        return geometry

    def audit_single_and_selectors(self, width: int, height: int) -> dict[str, object]:
        self.launch()
        root, calendar = self.scroll_to_calendar("Single date", width, height, "single")
        visual = self.assert_selected_circle(calendar, "single-baseline")
        self.tap(node_bounds(self.july(calendar, 22)))
        changed = self.calendar_after(self.dump("single-selected-22"), "Single date")
        if self.selected(changed) != {self.july(changed, 22).attrib["content-desc"]}:
            raise AuditFailure("single selection did not replace the previous date")
        self.screenshot("single-selected-22")

        headers = {node.attrib.get("content-desc", "").split(",", 1)[0]: node for node in self.header_nodes(changed)}
        self.tap(node_bounds(headers["Next month"]))
        august = self.calendar_after(self.dump("single-next-month"), "Single date")
        if not any(node.attrib.get("content-desc") == "Select month, agosto" for node in self.header_nodes(august)):
            raise AuditFailure("next-month control did not navigate to August")
        self.screenshot("single-next-month")
        previous = next(node for node in self.header_nodes(august) if node.attrib.get("content-desc") == "Previous month")
        self.tap(node_bounds(previous))
        july = self.calendar_after(self.dump("single-previous-month"), "Single date")

        month = next(node for node in self.header_nodes(july) if node.attrib.get("content-desc", "").startswith("Select month,"))
        self.tap(node_bounds(month))
        month_dialog = self.dump("month-dialog")
        if not self.exact(month_dialog, "Select month") or not self.exact(month_dialog, "julho"):
            raise AuditFailure("month selector dialog is incomplete")
        self.screenshot("month-dialog")
        cancel = self.visible(self.exact(month_dialog, "CANCEL"))
        if not cancel:
            raise AuditFailure("month selector does not expose Cancel")
        self.tap(node_bounds(cancel[-1]))

        july = self.calendar_after(self.dump("after-month-cancel"), "Single date")
        year = next(node for node in self.header_nodes(july) if node.attrib.get("content-desc", "").startswith("Select year,"))
        self.tap(node_bounds(year))
        year_dialog = self.dump("year-dialog")
        if not self.exact(year_dialog, "Select year") or not self.exact(year_dialog, "2026"):
            raise AuditFailure("year selector dialog is incomplete")
        self.screenshot("year-dialog")
        cancel = self.visible(self.exact(year_dialog, "CANCEL"))
        if not cancel:
            raise AuditFailure("year selector does not expose Cancel")
        self.tap(node_bounds(cancel[-1]))
        return visual

    def audit_selection_modes(self, width: int, height: int) -> dict[str, object]:
        self.launch()
        root, multiple = self.scroll_to_calendar("Multiple dates", width, height, "multiple")
        self.tap(node_bounds(self.july(multiple, 22)))
        multiple = self.calendar_after(self.dump("multiple-added-22"), "Multiple dates")
        if len(self.selected(multiple)) != 3:
            raise AuditFailure("multiple mode did not add a third date")
        self.tap(node_bounds(self.july(multiple, 8)))
        multiple = self.calendar_after(self.dump("multiple-removed-08"), "Multiple dates")
        expected = {self.july(multiple, 15).attrib["content-desc"], self.july(multiple, 22).attrib["content-desc"]}
        if self.selected(multiple) != expected:
            raise AuditFailure("multiple mode did not toggle one date independently")
        self.screenshot("multiple-final")

        self.launch()
        root, range_calendar = self.scroll_to_calendar("Date range", width, height, "range")
        self.tap(node_bounds(self.july(range_calendar, 12)))
        range_calendar = self.calendar_after(self.dump("range-start-12"), "Date range")
        if self.selected(range_calendar) != {self.july(range_calendar, 12).attrib["content-desc"]}:
            raise AuditFailure("range mode did not reset to one start date")
        self.tap(node_bounds(self.july(range_calendar, 20)))
        range_calendar = self.calendar_after(self.dump("range-end-20"), "Date range")
        expected = {self.july(range_calendar, 12).attrib["content-desc"], self.july(range_calendar, 20).attrib["content-desc"]}
        if self.selected(range_calendar) != expected:
            raise AuditFailure("range mode did not preserve both endpoints")
        return self.assert_range_track(range_calendar, "range-final")

    def audit_boundaries_and_direction(self, width: int, height: int) -> None:
        self.launch()
        root, limited = self.scroll_to_calendar("Limits and unavailable dates", width, height, "limits")
        for day in (4, 12, 19, 26):
            if self.july(limited, day).attrib.get("enabled") != "false":
                raise AuditFailure(f"limited calendar incorrectly enabled July {day}")
        if self.july(limited, 20).attrib.get("enabled") != "true":
            raise AuditFailure("limited calendar disabled an available date")
        before = self.selected(limited)
        self.tap(node_bounds(self.july(limited, 12)))
        limited = self.calendar_after(self.dump("limits-disabled-tap"), "Limits and unavailable dates")
        if self.selected(limited) != before:
            raise AuditFailure("disabled date changed selection")
        self.tap(node_bounds(self.july(limited, 20)))
        limited = self.calendar_after(self.dump("limits-enabled-tap"), "Limits and unavailable dates")
        if self.selected(limited) != {self.july(limited, 20).attrib["content-desc"]}:
            raise AuditFailure("available date did not select")

        self.launch()
        root, week = self.scroll_to_calendar("Week numbers", width, height, "week")
        area = node_bounds(week)
        first = min(self.date_nodes(week), key=lambda node: (node_bounds(node).top, node_bounds(node).left))
        first_area = node_bounds(first)
        week_column_x = area.left + int(round(area.width / 16))
        selected_before = self.selected(week)
        self.tap((week_column_x, first_area.center[1]))
        week = self.calendar_after(self.dump("week-number-tap"), "Week numbers")
        if self.selected(week) != selected_before:
            raise AuditFailure("week-number column stole a date selection")

        self.launch()
        root, monday = self.scroll_to_calendar("Monday first", width, height, "monday")
        first = min(self.date_nodes(monday), key=lambda node: (node_bounds(node).top, node_bounds(node).left))
        if "junho 29, 2026" not in first.attrib.get("content-desc", ""):
            raise AuditFailure("Monday-first calendar did not rotate the first column")

        self.launch()
        root, rtl = self.scroll_to_calendar("RTL", width, height, "rtl")
        june28 = next(node for node in self.date_nodes(rtl) if "junho 28, 2026" in node.attrib.get("content-desc", ""))
        july4 = self.july(rtl, 4)
        if node_bounds(june28).left <= node_bounds(july4).left:
            raise AuditFailure("RTL calendar did not mirror chronological columns")
        self.tap(node_bounds(self.july(rtl, 22)))
        rtl = self.calendar_after(self.dump("rtl-selected-22"), "RTL")
        if self.selected(rtl) != {self.july(rtl, 22).attrib["content-desc"]}:
            raise AuditFailure("RTL hit testing selected the wrong logical date")
        self.screenshot("rtl-selected-22")

    def audit_inert_states(self, width: int, height: int) -> None:
        for variation in ("Disabled", "Read only"):
            self.launch()
            root, calendar = self.scroll_to_calendar(variation, width, height, "inert")
            if any(node.attrib.get("enabled") == "true" for node in self.date_nodes(calendar)):
                raise AuditFailure(f"{variation} still exposes enabled date actions")
            before = self.selected(calendar)
            self.tap(node_bounds(self.july(calendar, 22)))
            after = self.calendar_after(self.dump(f"inert-{variation.lower().replace(' ', '-')}-tap"), variation)
            if self.selected(after) != before:
                raise AuditFailure(f"{variation} changed after tapping a date")

    def audit_font_scale(self, width: int, height: int) -> None:
        self.set_setting("system", "font_scale", "1.3")
        self.launch()
        for variation in self.VARIATIONS:
            root, calendar = self.scroll_to_calendar(variation, width, height, "font130")
            self.assert_geometry(root, calendar, variation, width, height)
            if not any(node.attrib.get("content-desc") == "Select month, julho" for node in self.header_nodes(calendar)) and variation != "Dynamic four-week month":
                raise AuditFailure(f"{variation}: month selector clipped at font scale 1.3")
        self.screenshot("font-scale-130-bottom")
        self.set_setting("system", "font_scale", "1.0")

    def audit_landscape(self) -> None:
        self.launch()
        self.set_setting("system", "user_rotation", "1")
        self.wait_for_rotation(1, "calendar landscape")
        width, height = self.screenshot("landscape-top")
        if width <= height:
            raise AuditFailure("calendar device did not rotate to landscape")
        root, calendar = self.scroll_to_calendar(
            "Dynamic four-week month", width, height, "landscape", require_full=True
        )
        self.assert_geometry(root, calendar, "Dynamic four-week month", width, height)
        dates = self.date_nodes(calendar)
        target = next(node for node in dates if "fevereiro 15, 2026" in node.attrib.get("content-desc", ""))
        self.tap(node_bounds(target))
        self.calendar_after(self.dump("landscape-selected"), "Dynamic four-week month")
        self.screenshot("landscape-selected")
        self.set_setting("system", "user_rotation", "0")
        self.wait_for_rotation(0, "calendar portrait restore")

    def audit_stress(self) -> dict[str, float | int]:
        self.launch()
        root = self.dump("stress-baseline")
        calendar = self.calendar_after(root, "Single date")
        fifteen = node_bounds(self.july(calendar, 15)).center
        twenty_two = node_bounds(self.july(calendar, 22)).center
        for point in (twenty_two, fifteen, twenty_two, fifteen):
            self.shell("input", "tap", str(point[0]), str(point[1]))
            time.sleep(0.14)
        time.sleep(0.8)
        self.shell("dumpsys", "gfxinfo", self.package, "reset", timeout=30.0)
        for point in (twenty_two, fifteen) * 6:
            self.shell("input", "tap", str(point[0]), str(point[1]))
            time.sleep(0.13)
        time.sleep(0.9)
        report = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
        stressed = self.calendar_after(self.dump("stress-result"), "Single date")
        if self.selected(stressed) != {self.july(stressed, 15).attrib["content-desc"]}:
            raise AuditFailure("rapid date stress ended in the wrong state")
        self.screenshot("stress-result")
        total_match = re.search(r"Total frames rendered:\s*(\d+)", report)
        janky_match = re.search(r"Janky frames \(legacy\):\s*(\d+) \(([\d.]+)%\)", report)
        p99_match = re.search(r"99th percentile:\s*(\d+)ms", report)
        if total_match is None or janky_match is None or p99_match is None:
            raise AuditFailure("gfxinfo did not expose calendar frame metrics")
        total = int(total_match.group(1))
        janky = int(janky_match.group(1))
        p99 = int(p99_match.group(1))
        # gfxinfo percentile buckets are integer milliseconds and a healthy
        # 60 Hz traversal can land in the 18 ms bucket without Android marking
        # the frame janky. Keep Android's jank signal authoritative and allow
        # that single rounding bucket at p99.
        if total < 12 or janky > 1 or p99 > 18:
            raise AuditFailure(f"calendar frame budget failed: frames={total}, janky={janky}, p99={p99}ms")
        return {
            "warmupTaps": 4,
            "measuredTaps": 12,
            "renderedFrames": total,
            "legacyJankyFrames": janky,
            "legacyJankyPercent": float(janky_match.group(2)),
            "frameP99Ms": p99,
        }

    def assert_runtime_log(self) -> None:
        logs = self.shell("logcat", "-d", "-t", "5000", timeout=30.0)
        markers = (
            "FATAL EXCEPTION",
            " E AndroidRuntime:",
            "Pam Native runtime error",
            "failed integrity verification",
            "Unknown native icon",
            "ANR in dev.pam.mobileui.catalog",
            "Input dispatching timed out",
            "requestLayout() improperly called",
        )
        errors = [line for line in logs.splitlines() if any(marker in line for marker in markers)]
        if errors:
            raise AuditFailure("runtime errors found: " + " | ".join(errors[-8:]))

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            width, height = self.screenshot("baseline")
            self.assert_window_count(1, "baseline")
            geometry = self.audit_all_geometry(width, height)
            selected_visual = self.audit_single_and_selectors(width, height)
            range_visual = self.audit_selection_modes(width, height)
            self.audit_boundaries_and_direction(width, height)
            self.audit_inert_states(width, height)
            self.audit_font_scale(width, height)
            self.audit_landscape()
            stress = self.audit_stress()
            self.assert_runtime_log()
            checks = {
                "allTwelveVariations": True,
                "singleSelection": True,
                "multipleToggleSelection": True,
                "rangeEndpointSelection": True,
                "continuousRangeTrack": True,
                "monthNavigation": True,
                "monthSelectorDialog": True,
                "yearSelectorDialog": True,
                "minimumMaximumAndDisabledDates": True,
                "adjacentDatesHidden": True,
                "weekNumberColumnNonInteractive": True,
                "mondayFirst": True,
                "dynamicFourWeekHeight": True,
                "disabledAndReadOnly": True,
                "rtlLayoutAndHitTesting": True,
                "accessibleHeaderAndDateNodes": True,
                "exact48DpDateRows": True,
                "selectedCircle40Dp": True,
                "fontScale130": True,
                "landscapeSafeAreaAndInteraction": True,
                "rapidTwelveTapStress": True,
                "frameP99AtMost18Ms": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-calendar",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": checks,
                "metrics": {
                    "density": self.density(),
                    "geometry": geometry,
                    "selectedVisual": selected_visual,
                    "rangeVisual": range_visual,
                    "rapidTap": stress,
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                try:
                    self.set_setting("system", "font_scale", self.original_font_scale)
                except Exception:
                    pass
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit p-calendar on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-calendar-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = CalendarAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-calendar; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (AuditFailure, subprocess.SubprocessError) as exception:
        print(f"FAIL p-calendar: {exception}")
        raise SystemExit(1)
