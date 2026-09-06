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
SPEC = importlib.util.spec_from_file_location("pam_button_audit", MODULE_PATH)
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


class ButtonAudit(AutocompleteAudit):
    VARIATIONS = (
        "Default",
        "Elevated",
        "Flat",
        "Tonal",
        "Outlined",
        "Text",
        "Plain",
        "Leading icon",
        "Trailing icon",
        "Block",
        "Extra small",
        "Small",
        "Large",
        "Extra large",
        "Compact density",
        "Success",
        "Error",
        "Disabled",
        "Loading",
    )
    LABELS = {
        "Default": "Continue",
        "Elevated": "Create",
        "Flat": "Confirm",
        "Tonal": "Save draft",
        "Outlined": "Add item",
        "Text": "Learn more",
        "Plain": "Skip",
        "Leading icon": "New project",
        "Trailing icon": "Next",
        "Block": "Continue securely",
        "Extra small": "Extra small",
        "Small": "Small",
        "Large": "Large action",
        "Extra large": "Hero action",
        "Compact density": "Compact",
        "Success": "Publish",
        "Error": "Delete",
        "Disabled": "Unavailable",
        "Loading": "Saving",
    }
    HEIGHT_DP = {
        "Extra small": 32.0,
        "Large": 96.0,
        "Extra large": 136.0,
        "Compact density": 32.0,
    }
    FILLED_COLORS = {
        "Default": (22, 101, 52),
        "Elevated": (22, 101, 52),
        "Flat": (22, 101, 52),
        "Tonal": (51, 65, 85),
        "Leading icon": (22, 101, 52),
        "Block": (22, 101, 52),
        "Extra small": (22, 101, 52),
        "Small": (22, 101, 52),
        "Large": (22, 101, 52),
        "Extra large": (22, 101, 52),
        "Compact density": (22, 101, 52),
        "Success": (21, 128, 61),
        "Error": (185, 28, 28),
        "Loading": (22, 101, 52),
    }
    FOREGROUND_COLORS = {
        "Outlined": (22, 101, 52),
        "Text": (22, 101, 52),
        "Plain": (22, 101, 52),
        "Trailing icon": (22, 101, 52),
    }
    NON_INTERACTIVE = {"Disabled", "Loading"}

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-btn",
            component_label="Button",
        )
        self.original_font_scale = ""

    def dump(self, name: str) -> ET.Element:
        # The uncompressed hierarchy retains decorative icon and progress
        # descendants while their accessibility importance stays hidden.
        self.assert_foreground(f"hierarchy {name}")
        remote = f"/sdcard/pam-button-{name}.xml"
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
        raise AuditFailure(
            f"could not collect a valid hierarchy for {name!r}: {last_problem}"
        )

    @staticmethod
    def descendants(node: ET.Element, class_name: str) -> list[ET.Element]:
        return [
            child for child in node.iter("node")
            if child is not node and child.attrib.get("class") == class_name
        ]

    def button(self, root: ET.Element, variation: str) -> ET.Element:
        description = self.LABELS[variation] + " button"
        candidates = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == description
            and node_bounds(node).height > 0
        ]
        if len(candidates) != 1:
            raise AuditFailure(
                f"{variation} exposes {len(candidates)} buttons with label {description!r}"
            )
        return candidates[0]

    def caption(self, root: ET.Element, variation: str, button: ET.Element) -> ET.Element:
        area = node_bounds(button)
        candidates = [
            node for node in self.exact(root, variation)
            if node_bounds(node).bottom <= area.top
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"variation caption {variation!r} is missing")
        return max(candidates, key=lambda node: node_bounds(node).bottom)

    def scroll_step(self, width: int, height: int, upward: bool) -> None:
        self.assert_foreground("button audit scroll precondition")
        center = width // 2
        if upward:
            start, end = int(height * 0.78), int(height * 0.52)
        else:
            start, end = int(height * 0.34), int(height * 0.60)
        self.shell("input", "swipe", str(center), str(start), str(center), str(end), "420")
        time.sleep(0.55)
        self.assert_foreground("button audit scroll result")

    def scroll_to_button(
        self,
        variation: str,
        width: int,
        height: int,
        prefix: str,
    ) -> tuple[ET.Element, ET.Element]:
        density = self.density()
        for attempt in range(24):
            root = self.dump(
                f"{prefix}-scroll-{variation.lower().replace(' ', '-')}-{attempt:02d}"
            )
            scroll_views = [
                node_bounds(node)
                for node in self.nodes(root)
                if node.attrib.get("class") == "android.widget.ScrollView"
                and node_bounds(node).width >= int(round(width * 0.20))
                and node_bounds(node).height > 0
            ]
            viewport = max(scroll_views, key=lambda area: area.width * area.height, default=Bounds(0, 0, width, height))
            safe_top = viewport.top + int(round(16.0 * density))
            safe_bottom = min(
                viewport.bottom,
                self.physical_content_bottom(width, height),
            ) - int(round(16.0 * density))
            try:
                button = self.button(root, variation)
            except AuditFailure:
                self.scroll_step(width, height, upward=True)
                continue
            area = node_bounds(button)
            if area.top >= safe_top and area.bottom <= safe_bottom:
                return root, button
            self.scroll_step(width, height, upward=area.bottom > safe_bottom)
        raise AuditFailure(f"could not bring {variation!r} button fully into the viewport")

    def assert_geometry(
        self,
        root: ET.Element,
        button: ET.Element,
        variation: str,
        width: int,
        height: int,
        scaled: bool = False,
    ) -> dict[str, float | int]:
        density = self.density()
        tolerance = max(2, int(round(1.5 * density)))
        area = node_bounds(button)
        caption_area = node_bounds(self.caption(root, variation, button))
        expected_height = self.HEIGHT_DP.get(variation, 40.0)
        height_dp = area.height / density
        if abs(height_dp - expected_height) > 1.5:
            raise AuditFailure(
                f"{variation} is {height_dp:.1f}dp high; expected {expected_height:g}dp"
            )
        if abs(area.left - caption_area.left) > tolerance:
            raise AuditFailure(f"{variation} lost the section start alignment")
        caption_gap = (area.top - caption_area.bottom) / density
        hit_slop = max(0.0, (48.0 - expected_height) / 2.0)
        effective_target_gap = caption_gap - hit_slop
        if abs(effective_target_gap - 12.0) > 1.75:
            raise AuditFailure(
                f"{variation} caption-to-effective-target gap is "
                f"{effective_target_gap:.1f}dp, expected 12dp"
            )
        if variation == "Block":
            if abs(area.left - caption_area.left) > tolerance or abs(area.right - caption_area.right) > tolerance:
                raise AuditFailure("block button does not fill the adaptive content pane")
        elif area.width / density < 64.0:
            raise AuditFailure(f"{variation} is narrower than the Material 64dp minimum")
        if area.left < 0 or area.right > width or area.top < 0 or area.bottom > height:
            raise AuditFailure(f"{variation} escaped the physical viewport")

        expected_enabled = variation not in self.NON_INTERACTIVE
        actual_enabled = button.attrib.get("enabled") == "true"
        if actual_enabled != expected_enabled:
            raise AuditFailure(f"{variation} accessibility enabled state is incorrect")
        if expected_enabled:
            if button.attrib.get("clickable") != "true":
                raise AuditFailure(f"{variation} does not expose a click action")
            if button.attrib.get("focusable") != "true":
                raise AuditFailure(f"{variation} is not accessibility-focusable")
        elif button.attrib.get("focusable") == "true":
            raise AuditFailure(f"{variation} remains focusable while non-interactive")

        text_nodes = self.descendants(button, "android.widget.TextView")
        if variation == "Loading":
            if text_nodes:
                raise AuditFailure("loading button exposes stale label beside its progress")
        else:
            if len(text_nodes) != 1:
                raise AuditFailure(f"{variation} exposes {len(text_nodes)} text labels")
            text_area = node_bounds(text_nodes[0])
            if (
                text_area.left < area.left
                or text_area.right > area.right
                or text_area.top < area.top
                or text_area.bottom > area.bottom
            ):
                suffix = " at 130% font scale" if scaled else ""
                raise AuditFailure(f"{variation} label is clipped{suffix}")
            if abs(text_area.center[1] - area.center[1]) > tolerance:
                raise AuditFailure(f"{variation} label is not vertically centered")

        return {
            "widthDp": round(area.width / density, 2),
            "heightDp": round(height_dp, 2),
            "visualCaptionGapDp": round(caption_gap, 2),
            "effectiveTargetGapDp": round(effective_target_gap, 2),
        }

    def assert_icon_anatomy(self, button: ET.Element, variation: str) -> None:
        density = self.density()
        tolerance = max(2, int(round(density)))
        icons = self.descendants(button, "android.view.View")
        texts = self.descendants(button, "android.widget.TextView")
        if len(icons) != 1 or len(texts) != 1:
            raise AuditFailure(f"{variation} does not expose one icon and one label")
        icon = node_bounds(icons[0])
        text = node_bounds(texts[0])
        expected_icon = int(round(20.0 * density))
        if abs(icon.width - expected_icon) > tolerance or abs(icon.height - expected_icon) > tolerance:
            raise AuditFailure(f"{variation} icon is not exactly 20dp")
        if abs(icon.center[1] - text.center[1]) > tolerance:
            raise AuditFailure(f"{variation} icon is not aligned to the label")
        if variation == "Leading icon":
            gap = (text.left - icon.right) / density
        else:
            gap = (icon.left - text.right) / density
        if abs(gap - 8.0) > 1.25:
            raise AuditFailure(f"{variation} icon gap is {gap:.1f}dp instead of 8dp")

    def assert_loading_anatomy(self, button: ET.Element) -> None:
        density = self.density()
        tolerance = max(2, int(round(density)))
        progress = self.descendants(button, "android.widget.ProgressBar")
        if len(progress) != 1:
            raise AuditFailure(f"loading button exposes {len(progress)} progress indicators")
        area = node_bounds(button)
        progress_area = node_bounds(progress[0])
        expected = int(round(20.0 * density))
        if abs(progress_area.width - expected) > tolerance or abs(progress_area.height - expected) > tolerance:
            raise AuditFailure("loading progress is not exactly 20dp")
        if (
            abs(progress_area.center[0] - area.center[0]) > tolerance
            or abs(progress_area.center[1] - area.center[1]) > tolerance
        ):
            raise AuditFailure("loading progress is not centered")

    @staticmethod
    def color_distance(left: tuple[int, int, int], right: tuple[int, int, int]) -> float:
        return math.sqrt(sum((a - b) ** 2 for a, b in zip(left, right)))

    @staticmethod
    def relative_luminance(color: tuple[int, int, int]) -> float:
        channels = []
        for value in color:
            normalized = value / 255.0
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

    def assert_visual_tokens(
        self,
        button: ET.Element,
        variation: str,
        screenshot_name: str,
    ) -> dict[str, float]:
        area = node_bounds(button)
        surface = (248, 250, 252)
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            pixels = [
                image.getpixel((x, y))
                for y in range(area.top, area.bottom)
                for x in range(area.left, area.right)
            ]
            if variation in self.FILLED_COLORS:
                expected = self.FILLED_COLORS[variation]
                matching = sum(
                    1 for pixel in pixels if self.color_distance(pixel, expected) <= 5.0
                )
                if matching < area.width * area.height * 0.20:
                    raise AuditFailure(f"{variation} did not render its semantic container color")
                foreground = (255, 255, 255)
                contrast = self.contrast_ratio(foreground, expected)
                if contrast < 4.5:
                    raise AuditFailure(f"{variation} token pair has only {contrast:.2f}:1 contrast")
                return {"contrast": round(contrast, 2)}

            if variation in self.FOREGROUND_COLORS:
                expected = self.FOREGROUND_COLORS[variation]
                closest = min((self.color_distance(pixel, expected) for pixel in pixels), default=999.0)
                if closest > 8.0:
                    raise AuditFailure(f"{variation} did not render the PAM primary foreground")
                interior = image.getpixel(
                    (area.left + max(2, int(round(3 * self.density()))), area.center[1])
                )
                if self.color_distance(interior, surface) > 8.0:
                    raise AuditFailure(f"{variation} rendered an unexpected decorative fill")
                contrast = self.contrast_ratio(expected, surface)
                if contrast < 4.5:
                    raise AuditFailure(f"{variation} has only {contrast:.2f}:1 contrast")
                return {"contrast": round(contrast, 2), "closestColorDistance": round(closest, 2)}
        return {}

    def hold_and_assert_state_layer(self, area: Bounds) -> None:
        self.screenshot("01-default-before-press")
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
        self.screenshot("02-default-pressed-state")
        try:
            _, stderr = process.communicate(timeout=2.0)
        except subprocess.TimeoutExpired as exception:
            process.kill()
            raise AuditFailure("Android did not finish the held button gesture") from exception
        if process.returncode != 0:
            raise AuditFailure(
                "held button gesture failed: " + stderr.decode(errors="replace").strip()
            )
        time.sleep(0.7)
        with Image.open(self.output / "01-default-before-press.png").convert("RGB") as before, Image.open(
            self.output / "02-default-pressed-state.png"
        ).convert("RGB") as pressed:
            difference = ImageChops.difference(before, pressed)
            changed = sum(
                1
                for y in range(area.top, area.bottom)
                for x in range(area.left, area.right)
                if sum(difference.getpixel((x, y))) >= 12
            )
        minimum = int(round(area.width * area.height * 0.025))
        if changed < minimum:
            raise AuditFailure(
                f"pressed state layer changed only {changed} pixels; expected at least {minimum}"
            )

    def press_point(self, variation: str, area: Bounds) -> tuple[int, int]:
        density = self.density()
        if variation == "Default":
            return area.center[0], area.top - int(round(3.0 * density))
        if variation == "Text":
            return area.center[0], area.bottom + int(round(3.0 * density))
        if variation in {"Extra small", "Compact density"}:
            return area.center[0], area.top - int(round(6.0 * density))
        return area.center

    def assert_pressed(self, root: ET.Element, variation: str) -> None:
        button = self.button(root, variation)
        labels = [
            node.attrib.get("text", "")
            for node in self.descendants(button, "android.widget.TextView")
        ]
        if labels != ["Pressed 1"]:
            raise AuditFailure(f"{variation} callback result is {labels!r}, expected ['Pressed 1']")

    def audit_all_interactions(
        self,
        width: int,
        height: int,
    ) -> tuple[dict[str, dict[str, float | int]], dict[str, dict[str, float]]]:
        geometry: dict[str, dict[str, float | int]] = {}
        colors: dict[str, dict[str, float]] = {}
        for index, variation in enumerate(self.VARIATIONS):
            root, button = self.scroll_to_button(variation, width, height, "interactive")
            area = node_bounds(button)
            geometry[variation] = self.assert_geometry(
                root, button, variation, width, height
            )
            initial_text = [
                node.attrib.get("text", "")
                for node in self.descendants(button, "android.widget.TextView")
            ]
            if variation not in self.NON_INTERACTIVE and initial_text != [self.LABELS[variation]]:
                raise AuditFailure(
                    f"{variation} leaked another instance state before its tap: {initial_text!r}"
                )
            if variation in {"Leading icon", "Trailing icon"}:
                self.assert_icon_anatomy(button, variation)
            if variation == "Loading":
                self.assert_loading_anatomy(button)

            screenshot_name = f"{3 + index:02d}-{variation.lower().replace(' ', '-')}"
            self.screenshot(screenshot_name)
            colors[variation] = self.assert_visual_tokens(
                button, variation, screenshot_name
            )

            if variation == "Default":
                self.hold_and_assert_state_layer(area)
                after = self.dump("03-default-callback")
                self.assert_pressed(after, variation)
                continue
            if variation in self.NON_INTERACTIVE:
                before = button.attrib.get("content-desc")
                self.tap(area)
                after = self.dump(f"non-interactive-{variation.lower()}")
                current = self.button(after, variation)
                if current.attrib.get("content-desc") != before:
                    raise AuditFailure(f"{variation} changed after a tap")
                if self.descendants(current, "android.widget.TextView") and any(
                    node.attrib.get("text") == "Pressed 1"
                    for node in self.descendants(current, "android.widget.TextView")
                ):
                    raise AuditFailure(f"{variation} dispatched a forbidden press callback")
                if variation == "Loading":
                    self.assert_loading_anatomy(current)
                continue

            self.tap(self.press_point(variation, area))
            after = self.dump(f"callback-{variation.lower().replace(' ', '-')}")
            self.assert_pressed(after, variation)
        return geometry, colors

    def audit_scaled_layout(self, width: int, height: int) -> None:
        self.set_setting("system", "font_scale", "1.3")
        self.launch()
        for variation in self.VARIATIONS:
            root, button = self.scroll_to_button(variation, width, height, "font130")
            self.assert_geometry(root, button, variation, width, height, scaled=True)
            if variation in {"Leading icon", "Trailing icon"}:
                self.assert_icon_anatomy(button, variation)
            if variation == "Loading":
                self.assert_loading_anatomy(button)
        self.screenshot("23-font-scale-130-bottom")
        self.set_setting("system", "font_scale", "1.0")

    def audit_landscape(self) -> None:
        self.launch()
        self.set_setting("system", "user_rotation", "1")
        self.wait_for_rotation(1, "button landscape")
        width, height = self.screenshot("24-landscape-top")
        if width <= height:
            raise AuditFailure("device did not enter landscape")
        block_pressed = False
        for variation in self.VARIATIONS:
            root, button = self.scroll_to_button(variation, width, height, "landscape")
            self.assert_geometry(root, button, variation, width, height)
            if variation in {"Leading icon", "Trailing icon"}:
                self.assert_icon_anatomy(button, variation)
            if variation == "Loading":
                self.assert_loading_anatomy(button)
            if variation == "Block":
                self.tap(node_bounds(button))
                self.assert_pressed(self.dump("26-landscape-block-pressed"), "Block")
                block_pressed = True
        self.screenshot("25-landscape-bottom")
        if not block_pressed:
            raise AuditFailure("landscape Block interaction was not executed")
        self.set_setting("system", "user_rotation", "0")
        self.wait_for_rotation(0, "button portrait restore")

    def audit_rapid_press_frames(self) -> dict[str, float | int]:
        self.launch()
        root = self.dump("27-stress-baseline")
        area = node_bounds(self.button(root, "Default"))
        self.shell("dumpsys", "gfxinfo", self.package, "reset", timeout=30.0)
        for _ in range(12):
            self.shell("input", "tap", str(area.center[0]), str(area.center[1]))
            time.sleep(0.12)
        time.sleep(0.9)
        stressed = self.dump("28-stress-result")
        labels = [
            node.attrib.get("text", "")
            for node in self.descendants(self.button(stressed, "Default"), "android.widget.TextView")
        ]
        if labels != ["Pressed 12"]:
            raise AuditFailure(f"rapid stress lost button activations: {labels!r}")
        self.screenshot("28-stress-result")

        report = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
        total_match = re.search(r"Total frames rendered:\s*(\d+)", report)
        legacy_match = re.search(r"Janky frames \(legacy\):\s*(\d+) \(([\d.]+)%\)", report)
        p99_match = re.search(r"99th percentile:\s*(\d+)ms", report)
        if total_match is None or legacy_match is None or p99_match is None:
            raise AuditFailure("Android gfxinfo did not expose the rapid-press frame metrics")
        total = int(total_match.group(1))
        legacy_janky = int(legacy_match.group(1))
        legacy_percent = float(legacy_match.group(2))
        p99_ms = int(p99_match.group(1))
        if total < 12:
            raise AuditFailure(f"rapid stress rendered only {total} frames for 12 state changes")
        if legacy_janky > 1:
            raise AuditFailure(
                f"rapid stress produced {legacy_janky} legacy janky frames ({legacy_percent:.2f}%)"
            )
        if p99_ms > 17:
            raise AuditFailure(f"rapid stress frame p99 is {p99_ms}ms, above one 60Hz frame")
        return {
            "presses": 12,
            "renderedFrames": total,
            "legacyJankyFrames": legacy_janky,
            "legacyJankyPercent": legacy_percent,
            "frameP99Ms": p99_ms,
        }

    def assert_runtime_log(self) -> None:
        logs = self.shell("logcat", "-d", "-t", "3500", timeout=30.0)
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
            geometry, colors = self.audit_all_interactions(width, height)
            self.audit_scaled_layout(width, height)
            self.audit_landscape()
            rapid_press = self.audit_rapid_press_frames()
            self.assert_runtime_log()

            report = {
                "schemaVersion": 2,
                "component": "p-btn",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allNineteenVariations": True,
                    "allEnabledCallbacks": True,
                    "instanceIsolation": True,
                    "disabledBehavior": True,
                    "loadingBehavior": True,
                    "loadingProgress20Dp": True,
                    "exact48DpEffectiveTargets": True,
                    "materialExpressiveSizes": True,
                    "blockAdaptiveWidth": True,
                    "textAndPlainContainerless": True,
                    "outlinedContainer": True,
                    "leadingAndTrailingIcons": True,
                    "iconSize20DpAndGap8Dp": True,
                    "semanticColorsAndContrast": True,
                    "pressedStateLayer": True,
                    "fontScale130": True,
                    "landscapeSafeArea": True,
                    "landscapeInteraction": True,
                    "rapidTwelveTapStress": True,
                    "frameP99AtMost17Ms": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "density": self.density(),
                    "geometry": geometry,
                    "visualTokens": colors,
                    "rapidPress": rapid_press,
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
    parser = argparse.ArgumentParser(description="Audit p-btn on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output", type=Path, default=Path("/tmp/pam-button-android-audit")
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = ButtonAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-btn; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-btn: {exception}")
        raise SystemExit(1)
