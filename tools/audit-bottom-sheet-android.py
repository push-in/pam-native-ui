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
from pathlib import Path

from PIL import Image, ImageStat


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_bottom_sheet_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class BottomSheetAudit(AutocompleteAudit):
    PROFILES = {
        "Default": ("Open default sheet", "Share this workspace", 34.0),
        "Two detents": ("Open two-detent sheet", "Flexible workspace", 34.0),
        "Persistent": ("Open persistent sheet", "Review required", 42.0),
        "No scrim": ("Open sheet without scrim", "Non-blocking details", 34.0),
        "No drag indicator": (
            "Open sheet without indicator",
            "Fixed presentation",
            34.0,
        ),
        "Dynamic height": ("Open dynamic sheet", "Sized to its content", None),
        "Keyboard form": ("Open keyboard form sheet", "Rename workspace", 58.0),
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-bottom-sheet",
            component_label="Bottom Sheet",
        )
        self.original_font_scale = ""

    def trigger(self, root: ET.Element, text: str) -> ET.Element:
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == text
            and node.attrib.get("clickable") == "true"
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"bottom-sheet trigger {text!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_trigger(
        self,
        trigger_text: str,
        width: int,
        height: int,
    ) -> tuple[ET.Element, ET.Element]:
        for attempt in range(12):
            root = self.dump(f"scroll-{trigger_text.lower().replace(' ', '-')}-{attempt}")
            try:
                trigger = self.trigger(root, trigger_text)
            except AuditFailure:
                trigger = None
            if trigger is not None:
                area = node_bounds(trigger)
                if area.top >= 120 and area.bottom <= height - 120:
                    return root, trigger
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {trigger_text!r} into the safe viewport")

    @staticmethod
    def sheet_accessibility(title: str) -> str:
        return f"{title} bottom sheet"

    def sheet(self, root: ET.Element, title: str) -> ET.Element:
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("content-desc") == self.sheet_accessibility(title)
            and node_bounds(node).top > 0
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"visible sheet surface for {title!r} is missing")
        return max(candidates, key=lambda node: node_bounds(node).top)

    def action(self, root: ET.Element, accessibility_label: str) -> ET.Element:
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == accessibility_label
            and node.attrib.get("clickable") == "true"
            and node_bounds(node).height > 0
        ]
        if len(candidates) != 1:
            raise AuditFailure(
                f"expected one action {accessibility_label!r}, found {len(candidates)}"
            )
        return candidates[0]

    def open_profile(
        self,
        profile: str,
        width: int,
        height: int,
        evidence_name: str,
        validate_expected_height: bool = True,
    ) -> tuple[ET.Element, Bounds, Path]:
        trigger_text, title, expected_percent = self.PROFILES[profile]
        _, trigger = self.scroll_to_trigger(trigger_text, width, height)
        trigger_area = node_bounds(trigger)
        target_dp = trigger_area.height / self.density()
        if target_dp < 48.0:
            raise AuditFailure(
                f"{profile} trigger is {target_dp:.1f}dp high; Material minimum is 48dp"
            )
        self.screenshot(f"{evidence_name}-before")
        self.tap(trigger_area)
        self.assert_window_count(2, f"{profile} open")
        root = self.dump(evidence_name)
        self.screenshot(evidence_name)
        area = node_bounds(self.sheet(root, title))
        if area.left != 0 or area.right != width or area.bottom != height:
            raise AuditFailure(
                f"{profile} sheet escaped the viewport: {area} vs {width}x{height}"
            )
        actual_percent = area.height / height * 100.0
        if (
            expected_percent is not None
            and validate_expected_height
            and width <= height
            and abs(actual_percent - expected_percent) > 1.25
        ):
            raise AuditFailure(
                f"{profile} opened at {actual_percent:.1f}%; expected {expected_percent:.0f}%"
            )
        close = self.action(root, f"Close {title}")
        confirm_label = (
            "Save workspace name"
            if profile == "Keyboard form"
            else f"Confirm {title}"
        )
        confirm = self.action(root, confirm_label)
        density = self.density()
        for label, node in (("secondary", close), ("primary", confirm)):
            action_area = node_bounds(node)
            if action_area.height / density < 48.0:
                raise AuditFailure(
                    f"{profile} {label} action is only {action_area.height / density:.1f}dp high"
                )
            if action_area.left < area.left or action_area.right > area.right:
                raise AuditFailure(f"{profile} {label} action overflows the sheet")
        gap_dp = (node_bounds(confirm).left - node_bounds(close).right) / density
        if gap_dp < 8.0 - 1.25:
            raise AuditFailure(f"{profile} action gap is {gap_dp:.1f}dp; expected at least 8dp")
        if node_bounds(confirm).bottom > height - round(16.0 * density):
            raise AuditFailure(f"{profile} primary action violates the bottom safe area")
        if width > height:
            safe_right = self.physical_content_right(width, height)
            if node_bounds(confirm).right > safe_right - round(16.0 * density):
                raise AuditFailure(
                    f"{profile} primary action violates the landscape navigation inset"
                )
        return root, area, self.output / f"{evidence_name}.png"

    @staticmethod
    def median_patch(path: Path, x: int, y: int, radius: int = 5) -> tuple[int, int, int]:
        with Image.open(path).convert("RGB") as image:
            crop = image.crop(
                (
                    max(0, x - radius),
                    max(0, y - radius),
                    min(image.width, x + radius + 1),
                    min(image.height, y + radius + 1),
                )
            )
            stat = ImageStat.Stat(crop)
            return tuple(round(channel) for channel in stat.median[:3])

    @staticmethod
    def rgb_distance(first: tuple[int, int, int], second: tuple[int, int, int]) -> float:
        return math.sqrt(
            sum((left - right) ** 2 for left, right in zip(first, second, strict=True))
        )

    def assert_scrim(
        self,
        before: Path,
        opened: Path,
        width: int,
        sheet_top: int,
        enabled: bool,
    ) -> dict[str, list[int]]:
        x = width - round(32.0 * self.density())
        y = max(150, sheet_top - round(56.0 * self.density()))
        baseline = self.median_patch(before, x, y)
        actual = self.median_patch(opened, x, y)
        if enabled:
            expected = tuple(round(channel * 0.60) for channel in baseline)
            if self.rgb_distance(actual, expected) > 10.0:
                raise AuditFailure(
                    f"Material 40% scrim is {actual}; expected approximately {expected}"
                )
        elif self.rgb_distance(actual, baseline) > 5.0:
            raise AuditFailure(
                f"no-scrim profile changed background {baseline} to {actual}"
            )
        return {"before": list(baseline), "opened": list(actual)}

    def handle_dark_pixels(self, path: Path, area: Bounds) -> int:
        density = self.density()
        x_radius = round(24.0 * density)
        top = area.top + round(10.0 * density)
        bottom = area.top + round(18.0 * density)
        with Image.open(path).convert("RGB") as image:
            crop = image.crop(
                (image.width // 2 - x_radius, top, image.width // 2 + x_radius, bottom)
            )
            pixels = crop.get_flattened_data() if hasattr(crop, "get_flattened_data") else crop.getdata()
            return sum(1 for red, green, blue in pixels if max(red, green, blue) < 180)

    def ime_top(self, height: int) -> int:
        report = self.shell("dumpsys", "window", "windows", timeout=30.0)
        blocks = re.split(r"(?=\s*Window #\d+ )", report)
        for block in blocks:
            if "InputMethod" not in block or "isVisible=true" not in block:
                continue
            frame = re.search(
                r"mFrame=\[(-?\d+),(-?\d+)\]\[(-?\d+),(-?\d+)\]",
                block,
            )
            if frame is not None:
                return int(frame.group(2))
        return height

    def physical_content_right(self, width: int, height: int) -> int:
        report = self.shell("dumpsys", "window", "windows", timeout=30.0)
        blocks = re.split(r"(?=\s*Window #\d+ )", report)
        for block in blocks:
            if "NavigationBar0" not in block:
                continue
            frame = re.search(
                r"mFrame=\[(-?\d+),(-?\d+)\]\[(-?\d+),(-?\d+)\]",
                block,
            )
            if frame is None:
                continue
            left, top, right, bottom = map(int, frame.groups())
            if top <= 0 and bottom >= height and left > width // 2:
                return left
        return width

    def assert_present(self, title: str, name: str) -> tuple[ET.Element, Bounds]:
        root = self.dump(name)
        self.assert_window_count(2, name)
        return root, node_bounds(self.sheet(root, title))

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.prepare()
        metrics: dict[str, object] = {}
        try:
            self.set_setting("system", "font_scale", "1.0")
            self.launch()
            baseline = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_window_count(1, "baseline")
            if not self.exact(baseline, "Default"):
                raise AuditFailure("bottom-sheet route did not start at its authoritative top")

            default_root, default_area, default_path = self.open_profile(
                "Default", width, height, "01-default-open"
            )
            metrics["defaultHeightPercent"] = round(default_area.height / height * 100.0, 2)
            metrics["defaultScrim"] = self.assert_scrim(
                self.output / "01-default-open-before.png",
                default_path,
                width,
                default_area.top,
                enabled=True,
            )
            if self.handle_dark_pixels(default_path, default_area) < 20:
                raise AuditFailure("default drag indicator is not visually present")
            self.tap((width - 40, max(140, default_area.top - 100)))
            self.assert_window_count(1, "default backdrop dismissal")

            self.launch()
            _, default_area, _ = self.open_profile(
                "Default", width, height, "02-default-back-open"
            )
            self.back()
            self.assert_window_count(1, "default Back dismissal")

            self.launch()
            _, low_area, _ = self.open_profile(
                "Two detents", width, height, "03-two-detents-low"
            )
            self.shell(
                "input",
                "swipe",
                str(width // 2),
                str(low_area.top + round(14.0 * self.density())),
                str(width // 2),
                str(round(height * 0.34)),
                "700",
            )
            time.sleep(1.0)
            expanded_root, expanded_area = self.assert_present(
                "Flexible workspace", "04-two-detents-expanded"
            )
            self.screenshot("04-two-detents-expanded")
            expanded_percent = expanded_area.height / height * 100.0
            if abs(expanded_percent - 68.0) > 1.25:
                raise AuditFailure(
                    f"drag settled at {expanded_percent:.1f}%; expected 68% detent"
                )
            if not self.exact(expanded_root, "Expanded details"):
                raise AuditFailure("expanded detent remained visually empty")
            if not all(
                self.exact(expanded_root, label)
                for label in ("Native layout", "Responsive gestures", "Safe dismissal")
            ):
                raise AuditFailure("expanded detent lost its useful detail rows")
            metrics["expandedHeightPercent"] = round(expanded_percent, 2)
            self.back()
            self.assert_window_count(1, "two-detent close")

            self.launch()
            _, persistent_area, _ = self.open_profile(
                "Persistent", width, height, "05-persistent-open"
            )
            self.tap((width - 40, max(140, persistent_area.top - 100)))
            _, persistent_area = self.assert_present(
                "Review required", "05-persistent-after-backdrop"
            )
            self.back()
            _, persistent_area = self.assert_present(
                "Review required", "05-persistent-after-back"
            )
            self.shell(
                "input",
                "swipe",
                str(width // 2),
                str(persistent_area.top + round(14.0 * self.density())),
                str(width // 2),
                str(height - 80),
                "700",
            )
            time.sleep(0.9)
            persistent_root, _ = self.assert_present(
                "Review required", "05-persistent-after-pan"
            )
            self.screenshot("05-persistent-after-pan")
            self.tap(node_bounds(self.action(persistent_root, "Confirm Review required")))
            self.assert_window_count(1, "persistent explicit action")

            self.launch()
            _, no_scrim_area, no_scrim_path = self.open_profile(
                "No scrim", width, height, "06-no-scrim-open"
            )
            metrics["noScrim"] = self.assert_scrim(
                self.output / "06-no-scrim-open-before.png",
                no_scrim_path,
                width,
                no_scrim_area.top,
                enabled=False,
            )
            self.back()
            self.assert_window_count(1, "no-scrim close")

            self.launch()
            _, no_indicator_area, no_indicator_path = self.open_profile(
                "No drag indicator", width, height, "07-no-indicator-open"
            )
            dark_pixels = self.handle_dark_pixels(no_indicator_path, no_indicator_area)
            if dark_pixels > 8:
                raise AuditFailure(
                    f"no-indicator profile still paints {dark_pixels} dark handle pixels"
                )
            metrics["noIndicatorDarkPixels"] = dark_pixels
            self.back()
            self.assert_window_count(1, "no-indicator close")

            self.launch()
            _, dynamic_area, _ = self.open_profile(
                "Dynamic height", width, height, "08-dynamic-open"
            )
            dynamic_dp = dynamic_area.height / self.density()
            if not 220.0 <= dynamic_dp <= 420.0:
                raise AuditFailure(
                    f"dynamic sheet is {dynamic_dp:.1f}dp high; expected useful intrinsic height"
                )
            metrics["dynamicHeightDp"] = round(dynamic_dp, 2)
            self.back()
            self.assert_window_count(1, "dynamic close")

            self.launch()
            keyboard_root, keyboard_area, _ = self.open_profile(
                "Keyboard form", width, height, "09-keyboard-open"
            )
            inputs = [
                node
                for node in self.nodes(keyboard_root)
                if node.attrib.get("class") == "android.widget.EditText"
                and node.attrib.get("content-desc") == "Workspace name"
                and node_bounds(node).height > 0
            ]
            if len(inputs) != 1:
                raise AuditFailure(f"keyboard sheet exposes {len(inputs)} workspace inputs")
            self.tap(node_bounds(inputs[0]))
            self.shell("input", "text", "2")
            time.sleep(0.8)
            if not self.ime_shown():
                raise AuditFailure("keyboard sheet input did not open the IME")
            focused_root, focused_area = self.assert_present(
                "Rename workspace", "10-keyboard-focused"
            )
            self.screenshot("10-keyboard-focused")
            save = self.action(focused_root, "Save workspace name")
            ime_top = self.ime_top(height)
            if node_bounds(save).bottom > ime_top:
                raise AuditFailure("keyboard covered the sheet primary action")
            if node_bounds(inputs[0]).bottom > ime_top:
                raise AuditFailure("keyboard covered the focused workspace input")
            if focused_area.top < 0 or focused_area.bottom > height:
                raise AuditFailure("keyboard moved the sheet outside the viewport")
            metrics["keyboardImeTop"] = ime_top
            self.back()
            if self.ime_shown():
                raise AuditFailure("first Back did not hide the software keyboard")
            self.assert_present("Rename workspace", "11-keyboard-hidden")
            self.back()
            self.assert_window_count(1, "keyboard sheet second Back")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled_root, scaled_area, _ = self.open_profile(
                "Default",
                width,
                height,
                "12-font-scale-130",
                validate_expected_height=False,
            )
            scaled_percent = scaled_area.height / height * 100.0
            if not 34.0 < scaled_percent <= 90.0:
                raise AuditFailure(
                    "130% font scale did not adapt the undersized detent to visible content"
                )
            scaled_description = self.exact(
                scaled_root,
                "Choose how collaborators can access your latest mobile prototype.",
            )
            scaled_close = self.action(scaled_root, "Close Share this workspace")
            if not scaled_description:
                raise AuditFailure("130% font scale lost the sheet description")
            if node_bounds(scaled_description[0]).bottom >= node_bounds(scaled_close).top:
                raise AuditFailure("130% font scale overlaps description and actions")
            if node_bounds(scaled_close).bottom > scaled_area.bottom:
                raise AuditFailure("130% font scale clips the sheet actions")
            metrics["fontScale130HeightPercent"] = round(scaled_percent, 2)
            self.back()
            self.assert_window_count(1, "scaled sheet close")
            self.set_setting("system", "font_scale", self.original_font_scale or "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "bottom-sheet landscape")
            self.launch()
            landscape_width, landscape_height = self.screenshot("13-landscape-baseline")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            _, landscape_area, _ = self.open_profile(
                "Default",
                landscape_width,
                landscape_height,
                "14-landscape-open",
            )
            landscape_percent = landscape_area.height / landscape_height * 100.0
            if not 34.0 < landscape_percent <= 90.0:
                raise AuditFailure(
                    "landscape sheet did not adapt its undersized detent to visible content"
                )
            metrics["landscapeAdaptiveHeightPercent"] = round(landscape_percent, 2)
            self.back()
            self.assert_window_count(1, "landscape sheet close")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "bottom-sheet portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2500", timeout=30.0)
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
            errors = [
                line for line in logs.splitlines() if any(marker in line for marker in markers)
            ]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 1,
                "component": "p-bottom-sheet",
                "device": self.serial,
                "package": self.package,
                "result": "passed",
                "checks": {
                    "allSevenProfiles": True,
                    "exact48DpTargets": True,
                    "materialEightDpActionGap": True,
                    "defaultBackdropDismissal": True,
                    "defaultBackDismissal": True,
                    "materialFortyPercentScrim": True,
                    "twoDetentRealDrag": True,
                    "expandedStateUsefulContent": True,
                    "persistentBackdropBlocked": True,
                    "persistentBackBlocked": True,
                    "persistentPanBlocked": True,
                    "persistentExplicitAction": True,
                    "noScrim": True,
                    "noDragIndicator": True,
                    "dynamicIntrinsicHeight": True,
                    "keyboardInput": True,
                    "keyboardAvoidance": True,
                    "keyboardFirstBack": True,
                    "fontScale130": True,
                    "landscapeAdaptiveDetent": True,
                    "safeArea": True,
                    "runtimeLog": True,
                },
                "metrics": metrics,
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
    parser = argparse.ArgumentParser(description="Audit p-bottom-sheet on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("/tmp/pam-bottom-sheet-android-audit"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = BottomSheetAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-bottom-sheet; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-bottom-sheet: {exception}")
        raise SystemExit(1)
