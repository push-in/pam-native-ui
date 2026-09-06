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

from PIL import Image, ImageChops


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_button_group_audit", MODULE_PATH)
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


class ButtonToggleAudit(AutocompleteAudit):
    VARIATIONS = (
        "Single choice",
        "Multiple choice",
        "Full width",
        "Single optional",
        "Leading icons",
        "Compact density",
        "Two options",
        "Five options",
        "Disabled item",
        "Disabled group",
        "Long labels",
        "Tile",
        "RTL",
        "Success color",
    )
    LABELS = {
        "Single choice": ("Day", "Week", "Month"),
        "Multiple choice": ("Walk", "Ride", "Drive"),
        "Full width": ("List", "Board", "Calendar"),
        "Single optional": ("Grid", "List", "Compact"),
        "Leading icons": ("New", "Recent", "Saved"),
        "Compact density": ("Left", "Center", "Right"),
        "Two options": ("Monthly", "Yearly"),
        "Five options": ("Mon", "Tue", "Wed", "Thu", "Fri"),
        "Disabled item": ("View", "Edit", "Share"),
        "Disabled group": ("Read", "Write", "Admin"),
        "Long labels": ("Recent activity", "Assigned to me"),
        "Tile": ("One", "Two", "Three"),
        "RTL": ("Day", "Week", "Month"),
        "Success color": ("Draft", "Review", "Published"),
    }
    INITIAL_SELECTED = {
        "Single choice": {"Week"},
        "Multiple choice": {"Walk", "Ride"},
        "Full width": {"Board"},
        "Single optional": {"List"},
        "Leading icons": {"Recent"},
        "Compact density": {"Center"},
        "Two options": {"Monthly"},
        "Five options": {"Wed"},
        "Disabled item": {"View"},
        "Disabled group": {"Read"},
        "Long labels": {"Recent activity"},
        "Tile": {"Two"},
        "RTL": {"Week"},
        "Success color": {"Published"},
    }
    BLOCK = {"Full width", "Five options", "Long labels", "RTL"}
    OPTIONAL = {"Single optional"}
    MULTIPLE = {"Multiple choice"}
    DISABLED = {
        "Disabled item": {"Edit"},
        "Disabled group": {"Read", "Write", "Admin"},
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-btn-toggle",
            component_label="Button Toggle",
        )
        self.original_font_scale = ""

    def dump(self, name: str) -> ET.Element:
        # Keep decorative icon descendants so their 20 dp anatomy and 8 dp
        # label spacing can be measured without making them accessible twice.
        self.assert_foreground(f"hierarchy {name}")
        remote = f"/sdcard/pam-button-toggle-{name}.xml"
        local = self.output / f"{name}.xml"
        last_problem = "UiAutomator did not return an XML hierarchy"
        for attempt in range(3):
            self.shell("rm", "-f", remote)
            try:
                self.shell("uiautomator", "dump", remote, timeout=30.0)
            except (subprocess.TimeoutExpired, AuditFailure) as exception:
                self.adb("shell", "pkill", "-f", "uiautomator", check=False)
                last_problem = f"UiAutomator command failed: {exception}"
            else:
                payload = self.adb("exec-out", "cat", remote, timeout=10.0, check=False)
                assert isinstance(payload, str)
                if payload.lstrip().startswith("<?xml") and "<hierarchy" in payload:
                    try:
                        root = ET.fromstring(payload)
                    except ET.ParseError as exception:
                        last_problem = f"invalid UiAutomator XML: {exception}"
                    else:
                        local.write_text(payload, encoding="utf-8")
                        return root
                else:
                    last_problem = payload.strip() or "UiAutomator dump file was absent"
            self.adb("shell", "pkill", "-f", "uiautomator", check=False)
            if attempt < 2:
                time.sleep(0.8)
        raise AuditFailure(f"could not collect hierarchy {name!r}: {last_problem}")

    @staticmethod
    def descendants(node: ET.Element, class_name: str) -> list[ET.Element]:
        return [
            child for child in node.iter("node")
            if child is not node and child.attrib.get("class") == class_name
        ]

    def group_after(self, root: ET.Element, variation: str) -> ET.Element:
        captions = [
            node for node in self.exact(root, variation)
            if node.attrib.get("class") == "android.widget.TextView"
            and node_bounds(node).height > 0
        ]
        if not captions:
            raise AuditFailure(f"variation caption {variation!r} is missing")
        caption = min(captions, key=lambda node: node_bounds(node).top)
        caption_area = node_bounds(caption)
        groups = [
            node for node in self.nodes(root)
            if node.attrib.get("content-desc") == "Button Toggle preview"
            and node_bounds(node).top >= caption_area.bottom
            and node_bounds(node).height > 0
        ]
        if not groups:
            raise AuditFailure(f"button group after {variation!r} is missing")
        return min(groups, key=lambda node: node_bounds(node).top)

    def buttons(self, group: ET.Element) -> list[ET.Element]:
        return [
            node for node in group.iter("node")
            if node.attrib.get("class") == "android.widget.ToggleButton"
            and node_bounds(node).height > 0
        ]

    def scroll_step(self, width: int, height: int, upward: bool = True) -> None:
        self.assert_foreground("button-toggle scroll precondition")
        center = width // 2
        start, end = (
            (int(height * 0.78), int(height * 0.52))
            if upward
            else (int(height * 0.34), int(height * 0.61))
        )
        self.shell("input", "swipe", str(center), str(start), str(center), str(end), "420")
        time.sleep(0.55)
        self.assert_foreground("button-toggle scroll result")

    def scroll_to_group(
        self,
        variation: str,
        width: int,
        height: int,
        prefix: str,
        upward: bool = True,
    ) -> tuple[ET.Element, ET.Element]:
        density = self.density()
        for attempt in range(30):
            root = self.dump(
                f"{prefix}-{variation.lower().replace(' ', '-')}-{attempt:02d}"
            )
            scrolls = [
                node_bounds(node) for node in self.nodes(root)
                if node.attrib.get("class") == "android.widget.ScrollView"
                and node_bounds(node).height > 0
            ]
            viewport = max(
                scrolls,
                key=lambda area: area.width * area.height,
                default=Bounds(0, 0, width, height),
            )
            safe_top = viewport.top + int(round(12.0 * density))
            safe_bottom = min(
                viewport.bottom,
                self.physical_content_bottom(width, height),
            ) - int(round(12.0 * density))
            try:
                group = self.group_after(root, variation)
            except AuditFailure:
                self.scroll_step(width, height, upward=upward)
                continue
            area = node_bounds(group)
            if area.top >= safe_top and area.bottom <= safe_bottom:
                return root, group
            direction = area.bottom > safe_bottom
            self.scroll_step(width, height, upward=direction)
        raise AuditFailure(f"could not bring {variation!r} fully into the viewport")

    def selected_labels(self, group: ET.Element) -> set[str]:
        return {
            node.attrib.get("content-desc", "")
            for node in self.buttons(group)
            if node.attrib.get("selected") == "true"
        }

    def assert_selected(
        self,
        group: ET.Element,
        expected: set[str],
        context: str,
    ) -> None:
        actual = self.selected_labels(group)
        if actual != expected:
            raise AuditFailure(
                f"{context}: selected labels are {sorted(actual)!r}, "
                f"expected {sorted(expected)!r}"
            )

    def assert_geometry(
        self,
        root: ET.Element,
        group: ET.Element,
        variation: str,
        width: int,
        height: int,
    ) -> dict[str, float | int]:
        density = self.density()
        tolerance = max(2, int(round(1.25 * density)))
        group_area = node_bounds(group)
        captions = [
            node for node in self.exact(root, variation)
            if node.attrib.get("class") == "android.widget.TextView"
            and node_bounds(node).bottom <= group_area.top
        ]
        if not captions:
            raise AuditFailure(f"{variation}: caption is not above its group")
        caption_area = max(captions, key=lambda node: node_bounds(node).bottom)
        caption_bounds = node_bounds(caption_area)
        group_height_dp = group_area.height / density
        if abs(group_height_dp - 48.0) > 1.5:
            raise AuditFailure(f"{variation}: group is {group_height_dp:.1f}dp high")
        if abs(group_area.left - caption_bounds.left) > tolerance:
            raise AuditFailure(f"{variation}: group lost section-start alignment")
        caption_gap_dp = (group_area.top - caption_bounds.bottom) / density
        if abs(caption_gap_dp - 8.0) > 1.5:
            raise AuditFailure(
                f"{variation}: caption-to-target gap is {caption_gap_dp:.1f}dp, expected 8dp"
            )

        buttons = self.buttons(group)
        labels = tuple(button.attrib.get("content-desc", "") for button in buttons)
        if set(labels) != set(self.LABELS[variation]) or len(labels) != len(self.LABELS[variation]):
            raise AuditFailure(f"{variation}: button labels are {labels!r}")
        ordered = sorted(buttons, key=lambda node: node_bounds(node).left)
        expected_height = 32.0 if variation == "Compact density" else 40.0
        for button in buttons:
            area = node_bounds(button)
            if abs(area.height / density - expected_height) > 1.5:
                raise AuditFailure(
                    f"{variation}/{self.text(button)} is {area.height / density:.1f}dp high"
                )
            if area.width / density < 64.0:
                raise AuditFailure(
                    f"{variation}/{self.text(button)} is narrower than 64dp"
                )
            inset_top = (area.top - group_area.top) / density
            expected_inset = (48.0 - expected_height) / 2.0
            if abs(inset_top - expected_inset) > 1.5:
                raise AuditFailure(
                    f"{variation}/{self.text(button)} is not vertically centered in its 48dp target"
                )
            expected_enabled = self.text(button) not in self.DISABLED.get(variation, set())
            if (button.attrib.get("enabled") == "true") != expected_enabled:
                raise AuditFailure(f"{variation}/{self.text(button)} has the wrong enabled state")
            if (button.attrib.get("clickable") == "true") != expected_enabled:
                state = "actionable" if expected_enabled else "inert"
                raise AuditFailure(
                    f"{variation}/{self.text(button)} is not {state} in accessibility semantics"
                )
            texts = self.descendants(button, "android.widget.TextView")
            if len(texts) != 1:
                raise AuditFailure(f"{variation}/{self.text(button)} has {len(texts)} text nodes")
            text_area = node_bounds(texts[0])
            if (
                text_area.left < area.left
                or text_area.right > area.right
                or text_area.top < area.top
                or text_area.bottom > area.bottom
            ):
                raise AuditFailure(f"{variation}/{self.text(button)} text is clipped")

        # Material segmented buttons overlap neighboring one-dp outlines.
        expected_gap = -1.0
        gaps = [
            (node_bounds(right).left - node_bounds(left).right) / density
            for left, right in zip(ordered, ordered[1:])
        ]
        if any(abs(gap - expected_gap) > 1.25 for gap in gaps):
            raise AuditFailure(f"{variation}: horizontal gaps are {gaps!r}")
        if variation in self.BLOCK:
            left = min(node_bounds(button).left for button in buttons)
            right = max(node_bounds(button).right for button in buttons)
            widths = [node_bounds(button).width / density for button in buttons]
            if (
                abs(left - group_area.left) > tolerance
                or abs(right - group_area.right) > tolerance
                or max(widths) - min(widths) > 1.5
            ):
                raise AuditFailure(
                    f"{variation}: full-width children do not evenly fill the adaptive row"
                )
        if variation == "RTL":
            positions = {
                self.text(button): node_bounds(button).left
                for button in buttons
            }
            if not (
                positions["Month"] < positions["Week"] < positions["Day"]
            ):
                raise AuditFailure(
                    f"{variation}: logical order was not mirrored: {positions!r}"
                )
        if (
            group_area.left < 0
            or group_area.right > width
            or group_area.top < 0
            or group_area.bottom > height
        ):
            raise AuditFailure(f"{variation}: group escaped the physical viewport")
        return {
            "groupHeightDp": round(group_height_dp, 2),
            "buttonHeightDp": expected_height,
            "captionGapDp": round(caption_gap_dp, 2),
            "horizontalGapDp": expected_gap,
            "buttonCount": len(buttons),
        }

    @staticmethod
    def color_distance(left: tuple[int, int, int], right: tuple[int, int, int]) -> float:
        return math.sqrt(sum((a - b) ** 2 for a, b in zip(left, right)))

    @staticmethod
    def relative_luminance(color: tuple[int, int, int]) -> float:
        channels = []
        for channel in color:
            normalized = channel / 255.0
            channels.append(
                normalized / 12.92
                if normalized <= 0.04045
                else ((normalized + 0.055) / 1.055) ** 2.4
            )
        return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]

    @classmethod
    def contrast_ratio(
        cls,
        foreground: tuple[int, int, int],
        background: tuple[int, int, int],
    ) -> float:
        light, dark = sorted(
            (cls.relative_luminance(foreground), cls.relative_luminance(background)),
            reverse=True,
        )
        return (light + 0.05) / (dark + 0.05)

    def assert_selected_color(
        self,
        group: ET.Element,
        expected: tuple[int, int, int],
        screenshot_name: str,
    ) -> float:
        selected = [
            button for button in self.buttons(group)
            if button.attrib.get("selected") == "true"
        ]
        if not selected:
            raise AuditFailure("visual token check has no selected button")
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            for button in selected:
                area = node_bounds(button)
                pixels = [
                    image.getpixel((x, y))
                    for y in range(area.top, area.bottom)
                    for x in range(area.left, area.right)
                ]
                matching = sum(
                    1 for pixel in pixels
                    if self.color_distance(pixel, expected) <= 5.0
                )
                if matching < area.width * area.height * 0.40:
                    raise AuditFailure(
                        f"{self.text(button)} did not render the selected semantic container"
                    )
        contrast = self.contrast_ratio((255, 255, 255), expected)
        if contrast < 4.5:
            raise AuditFailure(f"selected token pair has only {contrast:.2f}:1 contrast")
        return round(contrast, 2)

    def assert_icon_anatomy(self, group: ET.Element) -> None:
        density = self.density()
        for button in self.buttons(group):
            icons = self.descendants(button, "android.view.View")
            texts = self.descendants(button, "android.widget.TextView")
            if len(icons) != 1 or len(texts) != 1:
                raise AuditFailure(f"{self.text(button)} does not have one icon and one label")
            icon = node_bounds(icons[0])
            text = node_bounds(texts[0])
            if abs(icon.width / density - 20.0) > 1.5 or abs(icon.height / density - 20.0) > 1.5:
                raise AuditFailure(f"{self.text(button)} icon is not 20dp")
            gap = (text.left - icon.right) / density
            if abs(gap - 8.0) > 1.5:
                raise AuditFailure(f"{self.text(button)} icon-label gap is {gap:.1f}dp")

    def assert_disabled_selection_visible(self, group: ET.Element) -> float:
        self.screenshot("04-disabled-selection")
        buttons = self.buttons(group)
        selected = [
            button for button in buttons
            if button.attrib.get("selected") == "true"
        ]
        unselected = [
            button for button in buttons
            if button.attrib.get("selected") != "true"
        ]
        if len(selected) != 1 or not unselected:
            raise AuditFailure("disabled group does not expose one selected state")

        density = self.density()
        with Image.open(self.output / "04-disabled-selection.png").convert("RGB") as image:
            def container_color(button: ET.Element) -> tuple[int, int, int]:
                area = node_bounds(button)
                top = area.top + int(round(4.0 * density))
                bottom = area.top + int(round(8.0 * density))
                left = area.left + int(round(area.width * 0.30))
                right = area.right - int(round(area.width * 0.30))
                pixels = [
                    image.getpixel((x, y))
                    for y in range(top, bottom)
                    for x in range(left, right)
                ]
                return tuple(
                    int(round(sum(pixel[channel] for pixel in pixels) / len(pixels)))
                    for channel in range(3)
                )

            selected_color = container_color(selected[0])
            distances = [
                self.color_distance(selected_color, container_color(button))
                for button in unselected
            ]
        minimum = min(distances)
        if minimum < 8.0:
            raise AuditFailure(
                "disabled selected item is not visually distinct from unselected items"
            )
        return round(minimum, 2)

    def hold_and_assert_state_layer(self, area: Bounds) -> None:
        self.screenshot("01-before-held-press")
        process = subprocess.Popen(
            [
                "adb", "-s", self.serial, "shell", "input", "swipe",
                str(area.center[0]), str(area.center[1]),
                str(area.center[0]), str(area.center[1]), "900",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
        )
        time.sleep(0.24)
        self.screenshot("02-held-press-state")
        try:
            _, stderr = process.communicate(timeout=2.0)
        except subprocess.TimeoutExpired as exception:
            process.kill()
            raise AuditFailure("Android did not finish the held group gesture") from exception
        if process.returncode != 0:
            raise AuditFailure(
                "held group gesture failed: " + stderr.decode(errors="replace").strip()
            )
        time.sleep(0.7)
        with Image.open(self.output / "01-before-held-press.png").convert("RGB") as before, Image.open(
            self.output / "02-held-press-state.png"
        ).convert("RGB") as pressed:
            difference = ImageChops.difference(before, pressed)
            changed = sum(
                1
                for y in range(area.top, area.bottom)
                for x in range(area.left, area.right)
                if sum(difference.getpixel((x, y))) >= 12
            )
        if changed < int(round(area.width * area.height * 0.02)):
            raise AuditFailure(f"held state layer changed only {changed} pixels")

    def exercise_group(
        self,
        variation: str,
        group: ET.Element,
        expected: set[str],
    ) -> tuple[ET.Element, set[str]]:
        labels = self.LABELS[variation]
        disabled = self.DISABLED.get(variation, set())
        multiple = variation in self.MULTIPLE
        optional = variation in self.OPTIONAL or multiple
        current_group = group
        current = set(expected)
        density = self.density()
        for index, label in enumerate(labels):
            button_map = {self.text(node): node for node in self.buttons(current_group)}
            button = button_map[label]
            area = node_bounds(button)
            point: Bounds | tuple[int, int] = area
            previous_bounds = {
                self.text(node): node.attrib.get("bounds", "")
                for node in self.buttons(current_group)
            }
            if variation == "Single choice" and index == 0:
                point = (area.center[0], area.top - int(round(3.0 * density)))
            elif variation == "Compact density" and index == 0:
                point = (area.center[0], area.top - int(round(6.0 * density)))
            self.tap(point)
            root = self.dump(
                f"tap-{variation.lower().replace(' ', '-')}-{index + 1:02d}"
            )
            current_group = self.group_after(root, variation)
            current_bounds = {
                self.text(node): node.attrib.get("bounds", "")
                for node in self.buttons(current_group)
            }
            if current_bounds != previous_bounds:
                raise AuditFailure(
                    f"{variation} shifted segment bounds after tapping {label}: "
                    f"{previous_bounds!r} -> {current_bounds!r}"
                )
            if label not in disabled:
                if multiple:
                    current.remove(label) if label in current else current.add(label)
                elif label in current and optional:
                    current.clear()
                else:
                    current = {label}
            self.assert_selected(
                current_group,
                current,
                f"{variation} after tapping {label}",
            )
        return current_group, current

    def audit_interactions(
        self,
        width: int,
        height: int,
    ) -> tuple[dict[str, dict[str, float | int]], dict[str, float]]:
        geometry: dict[str, dict[str, float | int]] = {}
        final_states: dict[str, set[str]] = {}
        contrasts: dict[str, float] = {}
        for index, variation in enumerate(self.VARIATIONS):
            root, group = self.scroll_to_group(variation, width, height, "interactive")
            geometry[variation] = self.assert_geometry(
                root, group, variation, width, height
            )
            self.assert_selected(group, self.INITIAL_SELECTED[variation], f"{variation} initial")
            if variation == "Single choice":
                self.screenshot("03-single-choice")
                contrasts[variation] = self.assert_selected_color(
                    group, (51, 65, 85), "03-single-choice"
                )
                single_buttons = {self.text(node): node for node in self.buttons(group)}
                self.hold_and_assert_state_layer(node_bounds(single_buttons["Week"]))
                root = self.dump("03-single-choice-after-held-press")
                group = self.group_after(root, variation)
                self.assert_selected(group, {"Week"}, "Single choice held press")
            if variation == "Leading icons":
                self.assert_icon_anatomy(group)
            if variation == "Disabled group":
                contrasts["Disabled selection distance"] = (
                    self.assert_disabled_selection_visible(group)
                )
            if variation == "Success color":
                self.screenshot("04-success-color")
                contrasts[variation] = self.assert_selected_color(
                    group, (21, 128, 61), "04-success-color"
                )
            group, final_states[variation] = self.exercise_group(
                variation,
                group,
                set(self.INITIAL_SELECTED[variation]),
            )
            if index in {4, 9, 13}:
                self.screenshot(f"05-progress-{index + 1:02d}")

        # Revisit the first instance after mutating every other group. Its final
        # state must remain Month and must not inherit a sibling selection.
        root, group = self.scroll_to_group(
            "Single choice", width, height, "isolation", upward=False
        )
        self.assert_selected(
            group,
            final_states["Single choice"],
            "Single choice isolation revisit",
        )
        return geometry, contrasts

    def audit_font_scale(self, width: int, height: int) -> None:
        self.set_setting("system", "font_scale", "1.3")
        self.launch()
        for variation in self.VARIATIONS:
            root, group = self.scroll_to_group(variation, width, height, "font130")
            self.assert_geometry(root, group, variation, width, height)
            if variation == "Leading icons":
                self.assert_icon_anatomy(group)
        self.screenshot("06-font-scale-130-bottom")
        self.set_setting("system", "font_scale", "1.0")

    def audit_landscape(self) -> None:
        self.launch()
        self.set_setting("system", "user_rotation", "1")
        self.wait_for_rotation(1, "button-toggle landscape")
        width, height = self.screenshot("07-landscape-top")
        if width <= height:
            raise AuditFailure("device did not enter landscape")
        for variation in self.VARIATIONS:
            root, group = self.scroll_to_group(variation, width, height, "landscape")
            self.assert_geometry(root, group, variation, width, height)
            if variation == "Long labels":
                buttons = {self.text(node): node for node in self.buttons(group)}
                self.tap(node_bounds(buttons["Assigned to me"]))
                changed = self.group_after(self.dump("08-landscape-interaction"), variation)
                self.assert_selected(changed, {"Assigned to me"}, "landscape interaction")
        self.screenshot("09-landscape-bottom")
        self.set_setting("system", "user_rotation", "0")
        self.wait_for_rotation(0, "button-toggle portrait restore")

    def audit_stress(self) -> dict[str, float | int]:
        self.launch()
        root = self.dump("10-stress-baseline")
        group = self.group_after(root, "Single choice")
        buttons = {self.text(node): node_bounds(node) for node in self.buttons(group)}
        # Prime Android's post-rotation drawing caches before resetting gfxinfo.
        # The measured window below still contains exactly twelve rapid presses.
        for label in ("Day", "Month") * 2:
            area = buttons[label]
            self.shell("input", "tap", str(area.center[0]), str(area.center[1]))
            time.sleep(0.15)
        time.sleep(0.8)
        warmed = self.dump("10-stress-warmed")
        self.assert_selected(
            self.group_after(warmed, "Single choice"), {"Month"}, "stress warm-up"
        )
        self.shell("dumpsys", "gfxinfo", self.package, "reset", timeout=30.0)
        time.sleep(0.25)
        sequence = ("Day", "Month") * 6
        for label in sequence:
            area = buttons[label]
            self.shell("input", "tap", str(area.center[0]), str(area.center[1]))
            time.sleep(0.12)
        time.sleep(0.9)
        stressed = self.dump("11-stress-result")
        self.assert_selected(
            self.group_after(stressed, "Single choice"), {"Month"}, "rapid stress"
        )
        self.screenshot("11-stress-result")
        report = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
        total_match = re.search(r"Total frames rendered:\s*(\d+)", report)
        legacy_match = re.search(r"Janky frames \(legacy\):\s*(\d+) \(([\d.]+)%\)", report)
        p99_match = re.search(r"99th percentile:\s*(\d+)ms", report)
        if total_match is None or legacy_match is None or p99_match is None:
            raise AuditFailure("Android gfxinfo did not expose button-toggle metrics")
        total = int(total_match.group(1))
        legacy_janky = int(legacy_match.group(1))
        legacy_percent = float(legacy_match.group(2))
        p99_ms = int(p99_match.group(1))
        # gfxinfo reports integer percentile buckets; a 60 Hz traversal can
        # land in the 18 ms bucket without Android classifying it as janky.
        if total < 12 or legacy_janky > 1 or p99_ms > 18:
            raise AuditFailure(
                "button-toggle stress missed its frame budget: "
                f"frames={total}, legacyJanky={legacy_janky}, p99={p99_ms}ms"
            )
        return {
            "warmupPresses": 4,
            "presses": 12,
            "renderedFrames": total,
            "legacyJankyFrames": legacy_janky,
            "legacyJankyPercent": legacy_percent,
            "frameP99Ms": p99_ms,
        }

    def assert_runtime_log(self) -> None:
        logs = self.shell("logcat", "-d", "-t", "4500", timeout=30.0)
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
            raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            width, height = self.screenshot("00-baseline")
            self.assert_window_count(1, "baseline")
            geometry, contrasts = self.audit_interactions(width, height)
            self.audit_font_scale(width, height)
            self.audit_landscape()
            stress = self.audit_stress()
            self.assert_runtime_log()
            report = {
                "schemaVersion": 2,
                "component": "p-btn-toggle",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allFourteenVariations": True,
                    "allFortyTwoTargetsExercised": True,
                    "singleOptionalSelection": True,
                    "mandatorySelection": True,
                    "multipleSelection": True,
                    "selectionClearsRetainedNativeState": True,
                    "instanceIsolation": True,
                    "disabledItemAndGroup": True,
                    "disabledSelectionRemainsVisible": True,
                    "exact48DpEffectiveTargets": True,
                    "materialOneDpOutlineOverlap": True,
                    "stableBoundsAcrossSelection": True,
                    "adaptiveEqualWidth": True,
                    "leadingIcons20DpAndGap8Dp": True,
                    "semanticColorsAndContrast": True,
                    "pressedStateLayer": True,
                    "fontScale130": True,
                    "landscapeSafeArea": True,
                    "landscapeInteraction": True,
                    "rapidTwelveTapStress": True,
                    "frameP99AtMost18Ms": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "density": self.density(),
                    "geometry": geometry,
                    "contrast": contrasts,
                    "rapidPress": stress,
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n", encoding="utf-8"
            )
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                try:
                    self.set_setting("system", "font_scale", self.original_font_scale)
                except Exception:
                    pass
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit p-btn-toggle on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output", type=Path, default=Path("/tmp/pam-button-toggle-android-audit")
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = ButtonToggleAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-btn-toggle; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-btn-toggle: {exception}")
        raise SystemExit(1)
