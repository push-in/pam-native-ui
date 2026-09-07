#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from enum import IntEnum
from pathlib import Path

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_app_bar_audit", MODULE_PATH)
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


class AppBarAudit(AutocompleteAudit):
    VARIATIONS = (
        "Default",
        "Primary",
        "Prominent",
        "Compact",
        "Flat",
        "Elevation 0",
        "Elevation 1",
        "Elevation 2",
        "Elevation 3",
        "Elevation 4",
        "Elevation 5",
    )

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-app-bar",
            component_label="App Bar",
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
            dark_pixels = sum(
                1 for red, green, blue in pixels
                if max(red, green, blue) < 100
            )
        if dark_pixels < 500:
            raise AuditFailure(
                "light status bar has no readable dark system icons "
                f"({dark_pixels} contrast pixels)"
            )

    def bar_after(self, root, label: str):
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"variation label {label!r} is missing")
        label_area = node_bounds(labels[0])
        candidates = [
            node for node in self.nodes(root)
            if node.attrib.get("content-desc") == "App Bar preview"
            and node_bounds(node).top >= label_area.bottom
        ]
        if not candidates:
            raise AuditFailure(f"app bar after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_bar(self, label: str, width: int, height: int):
        slug = label.lower().replace(" ", "-")
        for attempt in range(18):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                bar = self.bar_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(bar)
            if area.top >= 130 and area.bottom <= height - 110:
                return root, bar
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} app bar into the viewport")

    def descendants_with_text(self, root, value: str):
        return [node for node in root.iter("node") if self.text(node) == value]

    def assert_bar_anatomy(
        self,
        bar,
        expected_height_dp: float,
        label: str,
        expected_title: str = "PAM Workspace",
    ) -> tuple[object, object, object]:
        density = self.density()
        area = node_bounds(bar)
        expected_height = int(round(expected_height_dp * density))
        if abs(area.height - expected_height) > max(2, int(density)):
            raise AuditFailure(
                f"{label} app bar height is {area.height}px; "
                f"expected {expected_height_dp:g}dp ({expected_height}px)"
            )
        navigation = self.descendants_with_text(bar, "Open navigation")
        titles = self.descendants_with_text(bar, expected_title)
        options = self.descendants_with_text(bar, "More options")
        if len(navigation) != 1 or len(titles) != 1 or len(options) != 1:
            raise AuditFailure(
                f"{label} app bar does not expose one navigation action, title and option action"
            )
        nav, title, action = navigation[0], titles[0], options[0]
        nav_area = node_bounds(nav)
        title_area = node_bounds(title)
        action_area = node_bounds(action)
        for name, node, child_area in (
            ("navigation", nav, nav_area),
            ("title", title, title_area),
            ("options", action, action_area),
        ):
            if (
                child_area.left < area.left
                or child_area.right > area.right
                or child_area.top < area.top
                or child_area.bottom > area.bottom
            ):
                raise AuditFailure(f"{label} {name} escaped the app-bar surface")
            if name != "title" and node.attrib.get("clickable") != "true":
                raise AuditFailure(f"{label} {name} action is not clickable")
        minimum_visual = int(round(40 * density))
        if min(nav_area.width, nav_area.height) < minimum_visual:
            raise AuditFailure(f"{label} navigation action is smaller than 40dp visually")
        if min(action_area.width, action_area.height) < minimum_visual:
            raise AuditFailure(f"{label} option action is smaller than 40dp visually")
        edge_inset = int(round(4 * density))
        inset_tolerance = max(3, int(round(2 * density)))
        if abs(nav_area.left - area.left - edge_inset) > inset_tolerance:
            raise AuditFailure(f"{label} navigation action is not aligned to the 4dp edge inset")
        if abs(area.right - action_area.right - edge_inset) > inset_tolerance:
            raise AuditFailure(f"{label} option action is not aligned to the 4dp edge inset")
        if expected_height_dp == 128:
            action_row_bottom = area.top + int(round(64 * density))
            if (
                nav_area.bottom > action_row_bottom
                or action_area.bottom > action_row_bottom
                or title_area.top < action_row_bottom
                or title_area.bottom > area.bottom - int(round(12 * density))
            ):
                raise AuditFailure("prominent app bar zones overlap or lost the bottom title anchor")
        else:
            bar_center = (area.top + area.bottom) / 2
            title_center = (title_area.top + title_area.bottom) / 2
            if abs(bar_center - title_center) > max(3, density * 2):
                raise AuditFailure(f"{label} title is not vertically centered")
        return nav, title, action

    @staticmethod
    def relative_luminance(color: tuple[int, int, int]) -> float:
        def channel(value: int) -> float:
            normalized = value / 255.0
            return normalized / 12.92 if normalized <= 0.04045 else (
                (normalized + 0.055) / 1.055
            ) ** 2.4

        red, green, blue = (channel(value) for value in color)
        return 0.2126 * red + 0.7152 * green + 0.0722 * blue

    def assert_primary_contrast(self, bar, title, screenshot_name: str) -> None:
        area = node_bounds(bar)
        title_area = node_bounds(title)
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            background = image.getpixel((area.left + 8, area.bottom - 8))
            background_luminance = self.relative_luminance(background)
            contrast_pixels = 0
            strongest = 1.0
            for y in range(title_area.top, title_area.bottom):
                for x in range(title_area.left, title_area.right):
                    foreground_luminance = self.relative_luminance(image.getpixel((x, y)))
                    ratio = (
                        max(background_luminance, foreground_luminance) + 0.05
                    ) / (
                        min(background_luminance, foreground_luminance) + 0.05
                    )
                    strongest = max(strongest, ratio)
                    if ratio >= 4.5:
                        contrast_pixels += 1
        if contrast_pixels < 120 or strongest < 4.5:
            raise AuditFailure(
                "primary app-bar title does not retain WCAG 4.5:1 contrast "
                f"({contrast_pixels} pixels, strongest {strongest:.2f}:1)"
            )

    def shadow_band_mean(
        self,
        bar,
        screenshot_name: str,
        start: int,
        end: int,
    ) -> float:
        area = node_bounds(bar)
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            values = [
                (red + green + blue) / 3.0
                for y in range(area.bottom + start, min(image.height, area.bottom + end))
                for x in range(area.left + 20, area.right - 20)
                for red, green, blue in [image.getpixel((x, y))]
            ]
        if not values:
            raise AuditFailure(f"{screenshot_name} has no pixels below the app-bar surface")
        return sum(values) / len(values)

    def assert_elevation_depth(self, elevation_zero, elevation_five) -> dict[str, float]:
        zero_near = self.shadow_band_mean(elevation_zero, "07-elevation-0", 1, 8)
        five_near = self.shadow_band_mean(elevation_five, "08-elevation-5", 1, 8)
        five_far = self.shadow_band_mean(elevation_five, "08-elevation-5", 16, 24)
        if zero_near < 245.0:
            raise AuditFailure(
                f"elevation 0 paints an unexpected shadow ({zero_near:.2f} luminance)"
            )
        if five_near > zero_near - 20.0:
            raise AuditFailure(
                "elevation 5 does not paint materially deeper pixels than elevation 0 "
                f"({five_near:.2f} versus {zero_near:.2f})"
            )
        if five_far < five_near + 4.0:
            raise AuditFailure(
                "elevation 5 shadow does not soften away from the surface "
                f"({five_near:.2f} near, {five_far:.2f} far)"
            )
        return {
            "elevation0NearLuminance": round(zero_near, 2),
            "elevation5NearLuminance": round(five_near, 2),
            "elevation5FarLuminance": round(five_far, 2),
        }

    def assert_expanded_option_target(self, root, action) -> None:
        density = self.density()
        area = node_bounds(action)
        # The Material icon-button container is 40dp, while PAM Native expands
        # its actual hit rectangle to 48dp. Tap 3dp outside the visible node to
        # prove the native TouchDelegate is active instead of trusting styles.
        x = area.left - max(2, int(round(3 * density)))
        self.tap((x, (area.top + area.bottom) // 2))
        changed = self.dump("01-options-expanded-target")
        if not self.exact(changed, "Options requested"):
            raise AuditFailure("the 48dp expanded option target did not activate")
        if self.exact(changed, "Navigation requested"):
            raise AuditFailure("expanded option target triggered the adjacent navigation action")
        self.screenshot("01-options-expanded-target")

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_light_status_bar_contrast("00-baseline")
            self.assert_window_count(1, "baseline")
            default = self.bar_after(root, "Default")
            navigation, _, action = self.assert_bar_anatomy(default, 64, "Default")

            self.assert_expanded_option_target(root, action)
            changed = self.dump("options-state")
            current_navigation = self.descendants_with_text(
                self.bar_after(changed, "Default"),
                "Open navigation",
            )[0]
            self.tap(node_bounds(current_navigation))
            navigation_changed = self.dump("02-navigation-requested")
            if not self.exact(navigation_changed, "Navigation requested"):
                raise AuditFailure("navigation action did not update its app-bar title")
            self.screenshot("02-navigation-requested")

            variation_heights = {
                "Default": 64.0,
                "Primary": 64.0,
                "Prominent": 128.0,
                "Compact": 48.0,
                "Flat": 64.0,
                "Elevation 0": 64.0,
                "Elevation 1": 64.0,
                "Elevation 2": 64.0,
                "Elevation 3": 64.0,
                "Elevation 4": 64.0,
                "Elevation 5": 64.0,
            }
            elevation_zero = None
            elevation_metrics = None
            for label in self.VARIATIONS[1:]:
                self.launch()
                variation_root, bar = self.scroll_to_bar(label, width, height)
                _, title, _ = self.assert_bar_anatomy(
                    bar,
                    variation_heights[label],
                    label,
                )
                if label == "Primary":
                    self.screenshot("03-primary")
                    self.assert_primary_contrast(bar, title, "03-primary")
                elif label == "Prominent":
                    self.screenshot("04-prominent")
                elif label == "Compact":
                    self.screenshot("05-compact")
                elif label == "Flat":
                    self.screenshot("06-flat")
                elif label == "Elevation 0":
                    self.screenshot("07-elevation-0")
                    elevation_zero = bar
                elif label == "Elevation 5":
                    self.screenshot("08-elevation-5")
                    if elevation_zero is None:
                        raise AuditFailure("elevation 0 evidence was not captured")
                    elevation_metrics = self.assert_elevation_depth(elevation_zero, bar)
                if label != "Default" and self.descendants_with_text(
                    bar,
                    "Navigation requested",
                ):
                    raise AuditFailure(f"Default interaction leaked into {label}")

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "app-bar landscape")
            landscape_width, landscape_height = self.screenshot("09-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            landscape_root = self.dump("09-landscape")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained with expanded navigation")
            landscape_bar = self.bar_after(landscape_root, "Default")
            # The navigation interaction above intentionally updates this
            # controlled instance and the state survives a configuration
            # change. Landscape must preserve that title, not reset it.
            self.assert_bar_anatomy(
                landscape_bar,
                64,
                "Landscape Default",
                expected_title="Navigation requested",
            )
            landscape_area = node_bounds(landscape_bar)
            if (
                landscape_area.left < 0
                or landscape_area.right > landscape_width
                or landscape_area.top < 0
                or landscape_area.bottom > landscape_height
            ):
                raise AuditFailure("landscape app bar escaped the adaptive content pane")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "app-bar portrait restore")

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
            errors = [line for line in logs.splitlines() if any(
                marker in line for marker in markers
            )]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-app-bar",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "defaultAnatomy": True,
                    "navigationAction": True,
                    "optionAction": True,
                    "expanded48DpOptionTarget": True,
                    "instanceIsolation": True,
                    "primaryContrast": True,
                    "prominentGeometry": True,
                    "compactGeometry": True,
                    "flat": True,
                    "elevation0Through5": True,
                    "elevationShadowDepth": True,
                    "adaptiveNavigation": True,
                    "landscapeSafeArea": True,
                    "statusBarContrast": True,
                    "runtimeLog": True,
                },
                "metrics": elevation_metrics,
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n",
                encoding="utf-8",
            )
            return report
        finally:
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit p-app-bar on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-app-bar-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = AppBarAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-app-bar; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-app-bar: {exception}")
        raise SystemExit(1)
