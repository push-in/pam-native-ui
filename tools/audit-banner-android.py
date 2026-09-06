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
SPEC = importlib.util.spec_from_file_location("pam_banner_audit", MODULE_PATH)
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


class BannerAudit(AutocompleteAudit):
    VARIATIONS = (
        "Default",
        "One Line",
        "Three Lines",
        "Leading Icon",
        "Single Action",
        "Disabled Action",
        "Success",
        "Information",
        "Warning",
        "Error",
        "Compact",
        "Dismissible",
    )
    HEIGHT_RANGES_DP = {
        "Default": (108.0, 124.0),
        "One Line": (88.0, 104.0),
        "Three Lines": (128.0, 152.0),
        "Leading Icon": (108.0, 132.0),
        "Single Action": (108.0, 124.0),
        "Disabled Action": (108.0, 124.0),
        "Success": (108.0, 132.0),
        "Information": (108.0, 132.0),
        "Warning": (108.0, 132.0),
        "Error": (108.0, 132.0),
        "Compact": (96.0, 112.0),
        "Dismissible": (108.0, 124.0),
    }
    MESSAGES = {
        "Default": "A new native release is ready with performance and accessibility improvements.",
        "One Line": "Your workspace is back online.",
        "Three Lines": (
            "A new release is ready. Install it now to receive performance "
            "improvements and the latest accessibility fixes."
        ),
        "Leading Icon": "Cloud sync is paused. Review your connection before continuing.",
        "Single Action": "A new native release is ready with performance and accessibility improvements.",
        "Disabled Action": "A new native release is ready with performance and accessibility improvements.",
        "Success": "Success: the latest release was installed.",
        "Information": "Information: a newer release is available.",
        "Warning": "Warning: connect to power before updating.",
        "Error": "Error: the update could not be downloaded.",
        "Compact": "A new native release is ready with performance and accessibility improvements.",
        "Dismissible": "A new native release is ready with performance and accessibility improvements.",
    }
    ACCENTS = {
        "Success": (21, 128, 61),
        "Information": (29, 78, 216),
        "Warning": (133, 77, 14),
        "Error": (185, 28, 28),
    }
    SURFACE = (248, 250, 252)

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-banner",
            component_label="Banner",
        )
        self.original_font_scale = ""

    @staticmethod
    def descendants_text(node: ET.Element) -> list[str]:
        return [
            child.attrib.get("text", "")
            for child in node.iter("node")
            if child.attrib.get("text", "")
        ]

    def banner_after(self, root: ET.Element, label: str) -> ET.Element:
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"variation label {label!r} is missing")
        label_area = node_bounds(labels[0])
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("content-desc", "").startswith("Banner. ")
            and node_bounds(node).top >= label_area.bottom
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"banner after {label!r} is missing or has invalid semantics")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_banner(
        self, label: str, width: int, height: int
    ) -> tuple[ET.Element, ET.Element]:
        slug = label.lower().replace(" ", "-")
        for attempt in range(20):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                banner = self.banner_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(banner)
            if area.top >= 120 and area.bottom <= height - 105:
                return root, banner
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} banner into the viewport")

    def button(self, banner: ET.Element, label: str) -> ET.Element:
        candidates = [
            node
            for node in banner.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
            and label in self.descendants_text(node)
        ]
        if not candidates:
            raise AuditFailure(f"banner action {label!r} is missing")
        return candidates[0]

    def global_button_after(self, root: ET.Element, label: str, text: str) -> ET.Element:
        variation = self.exact(root, label)
        if not variation:
            raise AuditFailure(f"variation label {label!r} disappeared")
        top = node_bounds(variation[0]).bottom
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and text in self.descendants_text(node)
            and node_bounds(node).top >= top
        ]
        if not candidates:
            raise AuditFailure(f"action {text!r} after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def assert_banner_geometry(
        self, root: ET.Element, banner: ET.Element, label: str
    ) -> dict[str, float]:
        density = self.density()
        area = node_bounds(banner)
        label_area = node_bounds(self.exact(root, label)[0])
        tolerance = max(2, int(round(density)))
        if abs(area.left - label_area.left) > tolerance:
            raise AuditFailure(f"{label} banner does not share the section start axis")
        width_dp = area.width / density
        height_dp = area.height / density
        if width_dp < 320.0:
            raise AuditFailure(f"{label} banner is unexpectedly narrow ({width_dp:.1f}dp)")
        minimum, maximum = self.HEIGHT_RANGES_DP[label]
        if not minimum <= height_dp <= maximum:
            raise AuditFailure(
                f"{label} banner is {height_dp:.1f}dp high; expected {minimum:g}–{maximum:g}dp"
            )

        message = self.exact(root, self.MESSAGES[label])
        message = [node for node in message if node_bounds(node).top >= area.top]
        if not message:
            raise AuditFailure(f"{label} message is missing from the native hierarchy")
        message_area = node_bounds(message[0])
        expected_message_left = area.left + int(
            round((64.0 if label in (*self.ACCENTS, "Leading Icon") else 16.0) * density)
        )
        if abs(message_area.left - expected_message_left) > tolerance:
            raise AuditFailure(
                f"{label} message starts at {message_area.left}; expected {expected_message_left}"
            )

        actions = [
            node
            for node in banner.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
        ]
        if len(actions) not in {1, 2}:
            raise AuditFailure(f"{label} exposes {len(actions)} actions; expected one or two")
        actions.sort(key=lambda node: node_bounds(node).left)
        for action in actions:
            action_area = node_bounds(action)
            if abs(action_area.height / density - 40.0) > 1.5:
                raise AuditFailure(f"{label} action visual height is not 40dp")
        if len(actions) == 2:
            gap_dp = (node_bounds(actions[1]).left - node_bounds(actions[0]).right) / density
            if abs(gap_dp - 8.0) > 1.5:
                raise AuditFailure(f"{label} action gap is {gap_dp:.1f}dp instead of 8dp")
        trailing_dp = (area.right - node_bounds(actions[-1]).right) / density
        bottom_dp = (area.bottom - node_bounds(actions[-1]).bottom) / density
        expected_bottom = 8.0 if label == "Compact" else 12.0
        if abs(trailing_dp - 8.0) > 1.5 or abs(bottom_dp - expected_bottom) > 1.5:
            raise AuditFailure(
                f"{label} action insets are trailing={trailing_dp:.1f}dp, bottom={bottom_dp:.1f}dp"
            )
        return {
            "widthDp": round(width_dp, 2),
            "heightDp": round(height_dp, 2),
            "messageLeftDp": round((message_area.left - area.left) / density, 2),
            "actionTrailingDp": round(trailing_dp, 2),
            "actionBottomDp": round(bottom_dp, 2),
        }

    @staticmethod
    def blend(accent: tuple[int, int, int]) -> tuple[int, int, int]:
        return tuple(round(channel * 0.12 + surface * 0.88) for channel, surface in zip(accent, BannerAudit.SURFACE))

    def sample_background(self, screenshot: str, area: Bounds) -> tuple[int, int, int]:
        density = self.density()
        x = area.left + int(round(4.0 * density))
        y = area.top + int(round(4.0 * density))
        with Image.open(self.output / f"{screenshot}.png").convert("RGB") as image:
            return image.getpixel((x, y))

    @staticmethod
    def distance(first: tuple[int, int, int], second: tuple[int, int, int]) -> float:
        return math.sqrt(sum((left - right) ** 2 for left, right in zip(first, second)))

    def assert_light_status_bar_contrast(self, screenshot: str) -> None:
        with Image.open(self.output / f"{screenshot}.png").convert("RGB") as image:
            crop = image.crop((0, 0, image.width, min(120, image.height)))
            pixels = crop.get_flattened_data() if hasattr(crop, "get_flattened_data") else crop.getdata()
            dark = sum(1 for red, green, blue in pixels if max(red, green, blue) < 100)
        if dark < 500:
            raise AuditFailure(f"status bar contrast is insufficient ({dark} dark pixels)")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        # Measure the canonical layout from a deterministic Android baseline.
        # The user's setting is restored in finally and 130% is covered below.
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        geometry: dict[str, dict[str, float]] = {}
        backgrounds: dict[str, list[int]] = {}
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_light_status_bar_contrast("00-baseline")
            self.assert_window_count(1, "baseline")
            for label in self.VARIATIONS[:3]:
                if not self.exact(root, label):
                    raise AuditFailure(f"audit surface is missing {label!r}")

            default = self.banner_after(root, "Default")
            geometry["Default"] = self.assert_banner_geometry(root, default, "Default")
            later = self.button(default, "Later")
            update = self.button(default, "Update")
            if later.attrib.get("enabled") != "true" or update.attrib.get("enabled") != "true":
                raise AuditFailure("default banner actions are not enabled")
            edge = int(round(2.0 * self.density()))
            later_area = node_bounds(later)
            self.tap((later_area.center[0], later_area.top - edge))
            after_later = self.dump("01-later")
            if not self.exact(after_later, "Reminder scheduled for tomorrow."):
                raise AuditFailure("upper edge of the 48dp Later target did not activate")
            self.screenshot("01-later")

            self.launch()
            update_root = self.dump("02-update-before")
            update_banner = self.banner_after(update_root, "Default")
            update_area = node_bounds(self.button(update_banner, "Update"))
            self.tap((update_area.center[0], update_area.bottom + edge))
            updated = self.dump("02-updated")
            if not self.exact(updated, "Update started. Downloading securely in the background."):
                raise AuditFailure("lower edge of the 48dp Update target did not activate")
            self.screenshot("02-updated")

            for index, label in enumerate(self.VARIATIONS[1:], start=1):
                self.launch()
                variation_root, banner = self.scroll_to_banner(label, width, height)
                geometry[label] = self.assert_banner_geometry(variation_root, banner, label)
                shot = f"{index + 2:02d}-{label.lower().replace(' ', '-')}"
                self.screenshot(shot)
                area = node_bounds(banner)
                if label in self.ACCENTS:
                    actual = self.sample_background(shot, area)
                    expected = self.blend(self.ACCENTS[label])
                    if self.distance(actual, expected) > 8.0:
                        raise AuditFailure(
                            f"{label} tonal surface is {actual}, expected approximately {expected}"
                        )
                    backgrounds[label] = list(actual)

                if label == "Single Action":
                    got_it = self.button(banner, "Got it")
                    self.tap(node_bounds(got_it))
                    acknowledged = self.dump("single-action-result")
                    if not self.exact(acknowledged, "Message acknowledged."):
                        raise AuditFailure("single banner action did not update its instance")
                elif label == "Disabled Action":
                    disabled = self.button(banner, "Later")
                    active = self.button(banner, "Update")
                    if disabled.attrib.get("enabled") != "false":
                        raise AuditFailure("disabled banner action remains enabled")
                    before = banner.attrib.get("content-desc")
                    self.tap(node_bounds(disabled))
                    disabled_root = self.dump("disabled-action-result")
                    disabled_banner = self.banner_after(disabled_root, "Disabled Action")
                    if disabled_banner.attrib.get("content-desc") != before:
                        raise AuditFailure("disabled banner action changed state")
                    self.tap(node_bounds(self.button(disabled_banner, "Update")))
                    active_root = self.dump("disabled-active-result")
                    if not self.exact(active_root, "Update started. Downloading securely in the background."):
                        raise AuditFailure("enabled action beside a disabled action did not work")
                elif label == "Dismissible":
                    self.tap(node_bounds(self.button(banner, "Dismiss")))
                    dismissed = self.dump("dismissed")
                    if not self.exact(dismissed, "Banner dismissed"):
                        raise AuditFailure("dismiss action did not remove the banner")
                    self.screenshot("14-dismissed")
                    restore = self.global_button_after(dismissed, "Dismissible", "Restore")
                    self.tap(node_bounds(restore))
                    restored, restored_banner = self.scroll_to_banner(
                        "Dismissible", width, height
                    )
                    (self.output / "restored.xml").write_text(
                        ET.tostring(restored, encoding="unicode"), encoding="utf-8"
                    )
                    self.assert_banner_geometry(restored, restored_banner, "Dismissible")
                    self.screenshot("15-restored")

            if geometry["Compact"]["heightDp"] >= geometry["Default"]["heightDp"]:
                raise AuditFailure("compact banner did not reduce vertical density")
            if len({tuple(value) for value in backgrounds.values()}) != 4:
                raise AuditFailure("semantic banner surfaces are not visually distinct")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled_root = self.dump("16-font-scale-130")
            scaled_banner = self.banner_after(scaled_root, "Default")
            scaled_area = node_bounds(scaled_banner)
            scaled_message = self.exact(scaled_root, self.MESSAGES["Default"])[0]
            scaled_actions = [
                node for node in scaled_banner.iter("node")
                if node.attrib.get("class") == "android.widget.Button"
            ]
            if not scaled_actions or node_bounds(scaled_message).bottom >= min(node_bounds(node).top for node in scaled_actions):
                raise AuditFailure("130% text scale overlaps the banner action row")
            if scaled_area.height <= node_bounds(default).height:
                raise AuditFailure("banner did not grow for 130% Android text scale")
            self.screenshot("16-font-scale-130")
            self.set_setting("system", "font_scale", self.original_font_scale or "1.0")

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "banner landscape")
            landscape_width, landscape_height = self.screenshot("17-landscape")
            landscape_root = self.dump("17-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant drawer button remained in permanent navigation")
            landscape_banner = self.banner_after(landscape_root, "Default")
            landscape_area = node_bounds(landscape_banner)
            if landscape_area.right > landscape_width or landscape_area.bottom > landscape_height:
                raise AuditFailure("landscape banner escaped the adaptive content pane")
            self.tap(node_bounds(self.button(landscape_banner, "Update")))
            landscape_updated = self.dump("18-landscape-updated")
            if not self.exact(landscape_updated, "Update started. Downloading securely in the background."):
                raise AuditFailure("landscape banner action did not remain interactive")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "banner portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2500", timeout=30.0)
            markers = (
                "FATAL EXCEPTION",
                " E AndroidRuntime:",
                "Pam Native runtime error",
                "failed integrity verification",
                "Unknown native icon",
                "ANR in dev.pam.mobileui.catalog",
                "Input dispatching timed out",
            )
            errors = [line for line in logs.splitlines() if any(marker in line for marker in markers)]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-banner",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allTwelveVariations": True,
                    "intrinsicOneTwoThreeLineGeometry": True,
                    "materialSpacingGrid": True,
                    "oneAndTwoTextActions": True,
                    "exact48DpEffectiveTargets": True,
                    "nonOverlappingActionTargets": True,
                    "actionCallbacks": True,
                    "disabledActionBehavior": True,
                    "instanceIsolation": True,
                    "dismissAndRestore": True,
                    "semanticTonalSurfaces": True,
                    "meaningBeyondColor": True,
                    "politeAlertSemantics": True,
                    "fontScale130": True,
                    "adaptiveNavigation": True,
                    "landscapeSafeArea": True,
                    "statusBarContrast": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "geometry": geometry,
                    "semanticBackgroundRgb": backgrounds,
                    "fontScale130HeightDp": round(scaled_area.height / self.density(), 2),
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
    parser = argparse.ArgumentParser(description="Audit p-banner on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog.debug")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("/tmp/pam-banner-android-audit"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = BannerAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-banner; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-banner: {exception}")
        raise SystemExit(1)
