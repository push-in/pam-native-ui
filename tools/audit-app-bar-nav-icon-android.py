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
SPEC = importlib.util.spec_from_file_location("pam_app_bar_nav_icon_audit", MODULE_PATH)
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


class AppBarNavIconAudit(AutocompleteAudit):
    VARIATIONS = (
        "Default",
        "Back",
        "Close",
        "Extra Small",
        "Small",
        "Large",
        "Extra Large",
        "Disabled",
        "Primary",
        "Secondary",
        "Custom Color",
    )
    ACCESSIBILITY_LABELS = {
        "Default": "Open navigation",
        "Back": "Navigate back",
        "Close": "Close current screen",
        "Extra Small": "Open navigation",
        "Small": "Open navigation",
        "Large": "Open navigation",
        "Extra Large": "Open navigation",
        "Disabled": "Open navigation",
        "Primary": "Open navigation",
        "Secondary": "Open navigation",
        "Custom Color": "Open navigation",
    }
    GLYPH_SIZE_DP = {
        "Default": 24.0,
        "Back": 24.0,
        "Close": 24.0,
        "Extra Small": 18.0,
        "Small": 20.0,
        "Large": 28.0,
        "Extra Large": 32.0,
        "Disabled": 24.0,
        "Primary": 24.0,
        "Secondary": 24.0,
        "Custom Color": 24.0,
    }
    GLYPH_COLORS = {
        "Default": (15, 23, 42),
        "Primary": (22, 101, 52),
        "Secondary": (51, 65, 85),
        "Custom Color": (154, 52, 18),
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-app-bar-nav-icon",
            component_label="App Bar Nav Icon",
        )

    def assert_light_status_bar_contrast(self, screenshot_name: str) -> None:
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            crop = image.crop((0, 0, image.width, min(120, image.height)))
            pixels = (
                crop.get_flattened_data()
                if hasattr(crop, "get_flattened_data")
                else crop.getdata()
            )
            dark_pixels = sum(1 for red, green, blue in pixels if max(red, green, blue) < 100)
        if dark_pixels < 500:
            raise AuditFailure(
                "light status bar has no readable dark system icons "
                f"({dark_pixels} contrast pixels)"
            )

    def dump(self, name: str) -> ET.Element:
        # This audit measures the decorative vector host inside the accessible
        # button. `--compressed` intentionally removes that no-hide-descendants
        # child, so retain the complete visual hierarchy without changing the
        # production accessibility tree.
        self.assert_foreground(f"hierarchy {name}")
        remote = f"/sdcard/pam-app-bar-nav-icon-{name}.xml"
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
                time.sleep(1.0)
        raise AuditFailure(
            f"could not collect a valid hierarchy for {name!r} after three attempts: "
            f"{last_problem}"
        )

    def button_after(self, root, label: str, description: str | None = None):
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"variation label {label!r} is missing")
        label_area = node_bounds(labels[0])
        expected_description = description or self.ACCESSIBILITY_LABELS[label]
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == expected_description
            and node_bounds(node).top >= label_area.bottom
        ]
        if not candidates:
            raise AuditFailure(
                f"navigation icon after {label!r} with description "
                f"{expected_description!r} is missing"
            )
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_button(self, label: str, width: int, height: int):
        slug = label.lower().replace(" ", "-")
        for attempt in range(18):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                button = self.button_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(button)
            if area.top >= 130 and area.bottom <= height - 110:
                return root, button
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} navigation icon into the viewport")

    @staticmethod
    def glyph(button):
        candidates = [
            node
            for node in button.iter("node")
            if node is not button
            and node.attrib.get("class") == "android.view.View"
            and node_bounds(node).width > 0
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure("navigation action does not expose its native vector glyph")
        return min(candidates, key=lambda node: node_bounds(node).width * node_bounds(node).height)

    def assert_geometry(self, button, label: str) -> tuple[Bounds, Bounds]:
        density = self.density()
        area = node_bounds(button)
        glyph_area = node_bounds(self.glyph(button))
        target = int(round(48.0 * density))
        tolerance = max(2, int(round(density)))
        if abs(area.width - target) > tolerance or abs(area.height - target) > tolerance:
            raise AuditFailure(
                f"{label} target is {area.width}x{area.height}px; "
                f"expected 48x48dp ({target}px)"
            )
        expected_glyph = int(round(self.GLYPH_SIZE_DP[label] * density))
        if (
            abs(glyph_area.width - expected_glyph) > tolerance
            or abs(glyph_area.height - expected_glyph) > tolerance
        ):
            raise AuditFailure(
                f"{label} glyph is {glyph_area.width}x{glyph_area.height}px; "
                f"expected {self.GLYPH_SIZE_DP[label]:g}dp ({expected_glyph}px)"
            )
        if (
            abs(area.center[0] - glyph_area.center[0]) > tolerance
            or abs(area.center[1] - glyph_area.center[1]) > tolerance
        ):
            raise AuditFailure(f"{label} glyph is not centered in its 48dp target")
        expected_enabled = label != "Disabled"
        if (button.attrib.get("enabled") == "true") != expected_enabled:
            raise AuditFailure(f"{label} accessibility enabled state is incorrect")
        if expected_enabled:
            if button.attrib.get("clickable") != "true":
                raise AuditFailure(f"{label} does not expose a click action")
            if button.attrib.get("focusable") != "true":
                raise AuditFailure(f"{label} is not accessibility-focusable")
        elif button.attrib.get("focusable") == "true":
            raise AuditFailure("disabled navigation icon remains accessibility-focusable")
        return area, glyph_area

    def assert_glyph_color(
        self,
        label: str,
        glyph_area: Bounds,
        screenshot_name: str,
    ) -> float:
        expected = self.GLYPH_COLORS[label]
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            distances = [
                math.sqrt(sum((actual - target) ** 2 for actual, target in zip(pixel, expected)))
                for y in range(glyph_area.top, glyph_area.bottom)
                for x in range(glyph_area.left, glyph_area.right)
                for pixel in [image.getpixel((x, y))]
            ]
        closest = min(distances, default=999.0)
        if closest > 12.0:
            raise AuditFailure(
                f"{label} glyph did not render its requested color; "
                f"closest pixel distance is {closest:.1f}"
            )
        return round(closest, 2)

    def hold_and_assert_ripple(self, area: Bounds) -> None:
        self.screenshot("01-before-press")
        process = subprocess.Popen(
            [
                "adb",
                "-s",
                self.serial,
                "shell",
                "input",
                "swipe",
                str(area.center[0]),
                str(area.center[1]),
                str(area.center[0]),
                str(area.center[1]),
                "900",
            ],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
        )
        time.sleep(0.22)
        self.screenshot("02-pressed-ripple")
        try:
            _, stderr = process.communicate(timeout=2.0)
        except subprocess.TimeoutExpired as exception:
            process.kill()
            raise AuditFailure("Android did not finish the held ripple gesture") from exception
        if process.returncode != 0:
            raise AuditFailure(
                "held ripple gesture failed: " + stderr.decode(errors="replace").strip()
            )
        time.sleep(0.7)
        density = self.density()
        before_path = self.output / "01-before-press.png"
        pressed_path = self.output / "02-pressed-ripple.png"
        with Image.open(before_path).convert("RGB") as before, Image.open(
            pressed_path
        ).convert("RGB") as pressed:
            difference = ImageChops.difference(before, pressed)
            changed = 0
            inner = 12.0 * density
            outer = 21.0 * density
            for y in range(area.top, area.bottom):
                for x in range(area.left, area.right):
                    radius = math.hypot(x - area.center[0], y - area.center[1])
                    if inner <= radius <= outer and sum(difference.getpixel((x, y))) >= 18:
                        changed += 1
        minimum_pixels = int(round(80 * density * density))
        if changed < minimum_pixels:
            raise AuditFailure(
                "press did not expose the circular Material state layer "
                f"({changed} changed annulus pixels, expected at least {minimum_pixels})"
            )

    def leading_system_gesture_inset(self) -> int:
        report = self.shell("dumpsys", "window", "displays", timeout=30.0)
        match = re.search(
            r"type=systemGestures frame=\[0,0\]\[(\d+),(\d+)\]"
            r"[^\n]*sideHint=LEFT",
            report,
        )
        return int(match.group(1)) if match else 0

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_light_status_bar_contrast("00-baseline")
            self.assert_window_count(1, "baseline")
            for label in self.VARIATIONS:
                if not self.exact(root, label) and label in self.VARIATIONS[:4]:
                    raise AuditFailure(f"audit surface is missing {label!r}")

            default = self.button_after(root, "Default")
            default_area, default_glyph = self.assert_geometry(default, "Default")
            default_left = default_area.left
            color_metrics: dict[str, float] = {}
            color_metrics["Default"] = self.assert_glyph_color(
                "Default", default_glyph, "00-baseline"
            )

            self.hold_and_assert_ripple(default_area)
            pressed = self.dump("03-press-result")
            if not self.exact(pressed, "Close navigation"):
                raise AuditFailure("default navigation callback did not update its state")
            if not self.exact(pressed, "Navigate back"):
                raise AuditFailure("default interaction leaked into the Back instance")

            current_default = self.exact(pressed, "Close navigation")[0]
            current_area = node_bounds(current_default)
            edge_inset = max(2, int(round(2.0 * self.density())))
            self.tap((current_area.right - edge_inset, current_area.center[1]))
            reopened = self.dump("04-right-edge")
            default_open = self.button_after(reopened, "Default")
            if default_open.attrib.get("content-desc") != "Open navigation":
                raise AuditFailure("right edge of the 48dp target did not activate")
            reopened_area = node_bounds(default_open)
            system_gesture_inset = self.leading_system_gesture_inset()
            gesture_clearance = max(
                edge_inset,
                int(round(4.0 * self.density())),
            )
            leading_test_x = max(
                reopened_area.left + edge_inset,
                system_gesture_inset + gesture_clearance,
            )
            if leading_test_x >= reopened_area.right - edge_inset:
                raise AuditFailure(
                    "the Android leading system gesture consumes the entire navigation target"
                )
            self.tap((leading_test_x, reopened_area.center[1]))
            left_edge = self.dump("05-left-edge")
            if not self.exact(left_edge, "Close navigation"):
                raise AuditFailure(
                    "leading usable edge of the 48dp target did not activate "
                    f"(x={leading_test_x}, system inset={system_gesture_inset}px)"
                )
            self.screenshot("05-left-edge")

            glyph_sizes: dict[str, float] = {}
            for index, label in enumerate(self.VARIATIONS[1:], start=1):
                self.launch()
                variation_root, button = self.scroll_to_button(label, width, height)
                area, glyph_area = self.assert_geometry(button, label)
                glyph_sizes[label] = round(glyph_area.width / self.density(), 2)
                label_area = node_bounds(self.exact(variation_root, label)[0])
                if abs(area.left - label_area.left) > max(2, int(round(self.density()))):
                    raise AuditFailure(f"{label} target does not share the variation start axis")
                if abs(area.left - default_left) > max(2, int(round(self.density()))):
                    raise AuditFailure(f"{label} target shifts horizontally when its glyph changes")
                screenshot_name = f"{6 + index:02d}-{label.lower().replace(' ', '-')}"
                self.screenshot(screenshot_name)
                if label in self.GLYPH_COLORS:
                    color_metrics[label] = self.assert_glyph_color(
                        label, glyph_area, screenshot_name
                    )
                if label == "Disabled":
                    before = button.attrib.get("content-desc")
                    self.tap(area)
                    disabled_after = self.dump("13-disabled-after-tap")
                    disabled = self.button_after(disabled_after, "Disabled")
                    if disabled.attrib.get("content-desc") != before:
                        raise AuditFailure("disabled navigation icon reacted to a tap")

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "navigation icon landscape")
            landscape_width, landscape_height = self.screenshot("18-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            landscape_root = self.dump("18-landscape")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant drawer button remained in permanent navigation")
            # The leading-edge interaction above leaves this controlled sample
            # open. A configuration change must preserve that state.
            landscape_default = self.button_after(
                landscape_root,
                "Default",
                description="Close navigation",
            )
            landscape_area, _ = self.assert_geometry(landscape_default, "Default")
            if (
                landscape_area.left < 0
                or landscape_area.right > landscape_width
                or landscape_area.top < 0
                or landscape_area.bottom > landscape_height
            ):
                raise AuditFailure("landscape navigation icon escaped the adaptive content pane")
            self.tap(landscape_area)
            landscape_pressed = self.dump("19-landscape-pressed")
            if not self.exact(landscape_pressed, "Open navigation"):
                raise AuditFailure("landscape navigation action did not remain interactive")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "navigation icon portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            markers = (
                "FATAL EXCEPTION",
                " E AndroidRuntime:",
                "Pam Native runtime error",
                "failed integrity verification",
                "Unknown native icon",
                "ANR in dev.pam.mobileui.catalog",
                "Input dispatching timed out",
            )
            errors = [
                line
                for line in logs.splitlines()
                if any(marker in line for marker in markers)
            ]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-app-bar-nav-icon",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allElevenVariations": True,
                    "exact48DpTarget": True,
                    "centeredGlyphs": True,
                    "glyphSizes18Through32Dp": True,
                    "menuBackAndCloseIcons": True,
                    "customAccessibilityLabels": True,
                    "pressCallback": True,
                    "leadingAndTrailingUsableTargetEdges": True,
                    "circularPressedStateLayer": True,
                    "disabledBehavior": True,
                    "instanceIsolation": True,
                    "semanticAndCustomColors": True,
                    "adaptiveNavigation": True,
                    "landscapeSafeArea": True,
                    "statusBarContrast": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "targetDp": 48.0,
                    "leadingSystemGestureInsetPx": system_gesture_inset,
                    "leadingSystemGestureInsetDp": round(
                        system_gesture_inset / self.density(), 2
                    ),
                    "glyphSizesDp": glyph_sizes,
                    "colorPixelDistance": color_metrics,
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
    parser = argparse.ArgumentParser(description="Audit p-app-bar-nav-icon on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("/tmp/pam-app-bar-nav-icon-android-audit"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = AppBarNavIconAudit(
        args.serial, args.package, args.activity, args.output
    ).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-app-bar-nav-icon; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-app-bar-nav-icon: {exception}")
        raise SystemExit(1)
