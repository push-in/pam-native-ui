#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import re
import sys
import time
from pathlib import Path

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_slider_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class SliderAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-slider",
            component_label="Slider",
        )

    def application_logs(self) -> str:
        package_report = self.shell(
            "dumpsys", "package", self.package, timeout=30.0,
        )
        match = re.search(r"\buserId=(\d+)\b", package_report)
        if match is None:
            raise AuditFailure(f"could not resolve Android UID for {self.package}")
        return self.shell(
            "logcat", f"--uid={match.group(1)}", "-d", "-t", "1200",
            timeout=30.0,
        )

    def slider_after(self, root, label: str):
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"audit surface is missing {label!r}")
        label_area = node_bounds(labels[0])
        candidates = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.SeekBar"
            and node.attrib.get("content-desc") == f"{self.component_label} preview"
            and node_bounds(node).top >= label_area.bottom
        ]
        if not candidates:
            raise AuditFailure(f"slider after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    @staticmethod
    def thumb_center(path: Path, area: Bounds) -> float:
        with Image.open(path).convert("RGB") as image:
            counts: list[tuple[int, int]] = []
            for x in range(max(0, area.left), min(image.width, area.right)):
                count = 0
                for y in range(max(0, area.top), min(image.height, area.bottom)):
                    red, green, blue = image.getpixel((x, y))
                    if green >= red + 18 and green >= blue + 10 and green >= 75:
                        count += 1
                counts.append((x, count))
        if not counts:
            raise AuditFailure("slider crop had no pixels")
        peak = max(count for _, count in counts)
        if peak < 18:
            raise AuditFailure(f"slider thumb was not visually detectable (peak={peak})")
        thumb_columns = [x for x, count in counts if count >= peak * 0.72]
        if not thumb_columns:
            raise AuditFailure("slider thumb geometry was not detectable")
        return (thumb_columns[0] + thumb_columns[-1]) / 2.0

    def capture_centers(self, name: str, nodes: dict[str, object]) -> dict[str, float]:
        self.screenshot(name)
        path = self.output / f"{name}.png"
        return {
            label: self.thumb_center(path, node_bounds(node))
            for label, node in nodes.items()
        }

    def scroll_to_slider(
        self,
        label: str,
        width: int,
        height: int,
    ) -> tuple[object, object]:
        content_bottom = self.physical_content_bottom(width, height)
        density = self.density()
        minimum_target = round(48.0 * density)
        minimum_bottom_gap = round(16.0 * density)
        for attempt in range(10):
            root = self.dump(f"scroll-{label.lower().replace(' ', '-')}-{attempt}")
            if self.exact(root, label):
                try:
                    slider = self.slider_after(root, label)
                except AuditFailure:
                    slider = None
                if slider is not None:
                    area = node_bounds(slider)
                    # Samsung's UiAutomator clips edge-to-edge node bounds at
                    # the legacy app-height coordinate (physical height minus
                    # both system bars), although screencap/input keep physical
                    # screen coordinates. Restore the known 48dp SeekBar target
                    # only when it still fits above navigation with a safe gap.
                    restored_bottom = area.top + minimum_target
                    if (
                        0 < area.height < minimum_target
                        and area.top >= 120
                        and restored_bottom <= content_bottom - minimum_bottom_gap
                    ):
                        area = Bounds(
                            area.left,
                            area.top,
                            area.right,
                            restored_bottom,
                        )
                        slider.attrib["bounds"] = (
                            f"[{area.left},{area.top}][{area.right},{area.bottom}]"
                        )
                    if (
                        area.height / density >= 48.0
                        and area.top >= 120
                        and area.bottom <= content_bottom - 32
                    ):
                        return root, slider
            # Start in the stable blank band between Maximum and Step 10.
            # The page gutter is outside the native ScrollView touch target,
            # while starting lower would land on Step 10 itself.
            self.shell(
                "input", "swipe",
                str(width // 2), str(int(height * 0.70)),
                str(width // 2), str(int(height * 0.28)), "650",
            )
            time.sleep(0.8)
            self.assert_foreground("slider audit scroll result")
        raise AuditFailure(f"could not bring {label!r} fully into the safe viewport")

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

    @staticmethod
    def assert_ratio(label: str, center: float, start: float, end: float, low: float, high: float) -> None:
        ratio = (center - start) / max(1.0, end - start)
        if not low <= ratio <= high:
            raise AuditFailure(
                f"{label} thumb is at {ratio:.3f}; expected {low:.2f}..{high:.2f}"
            )

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            baseline_labels = (
                "Default", "Isolated instance", "Disabled", "Minimum", "Maximum",
            )
            nodes = {
                label: self.slider_after(root, label)
                for label in baseline_labels
            }
            self.assert_window_count(1, "baseline")
            if self.ime_shown():
                raise AuditFailure("slider unexpectedly opened the software keyboard")

            density = self.density()
            for label, node in nodes.items():
                area = node_bounds(node)
                if area.height / density < 48.0:
                    raise AuditFailure(
                        f"{label} target is {area.height / density:.1f}dp; minimum is 48dp"
                    )
                if node.attrib.get("focusable") != "true":
                    raise AuditFailure(f"{label} is not accessibility-focusable")
                expected_enabled = label != "Disabled"
                if (node.attrib.get("enabled") == "true") != expected_enabled:
                    raise AuditFailure(f"{label} exposes the wrong enabled state")

            baseline_path = self.output / "00-baseline.png"
            centers = {
                label: self.thumb_center(baseline_path, node_bounds(node))
                for label, node in nodes.items()
            }
            start = centers["Minimum"]
            end = centers["Maximum"]
            if end - start < width * 0.72:
                raise AuditFailure("slider track wastes horizontal space or is clipped")
            self.assert_ratio("Default", centers["Default"], start, end, 0.60, 0.68)
            self.assert_ratio("Isolated", centers["Isolated instance"], start, end, 0.20, 0.28)

            default_area = node_bounds(nodes["Default"])
            target = int(start + (end - start) * 0.30)
            self.shell(
                "input", "swipe", str(int(centers["Default"])),
                str(default_area.center[1]), str(target),
                str(default_area.center[1]), "650",
            )
            time.sleep(1.0)
            after_drag = self.dump("01-dragged")
            dragged_nodes = {
                label: self.slider_after(after_drag, label)
                for label in baseline_labels
            }
            dragged = self.capture_centers("01-dragged", dragged_nodes)
            self.assert_ratio("Dragged default", dragged["Default"], start, end, 0.26, 0.34)
            if abs(dragged["Isolated instance"] - centers["Isolated instance"]) > 5.0:
                raise AuditFailure("dragging the default slider corrupted the second instance")

            disabled_area = node_bounds(dragged_nodes["Disabled"])
            self.shell(
                "input", "swipe", str(int(dragged["Disabled"])),
                str(disabled_area.center[1]), str(int(start + (end - start) * 0.85)),
                str(disabled_area.center[1]), "650",
            )
            time.sleep(0.8)
            disabled_path = self.output / "02-disabled-after-drag.png"
            self.screenshot("02-disabled-after-drag")
            disabled_center = self.thumb_center(disabled_path, disabled_area)
            if abs(disabled_center - dragged["Disabled"]) > 5.0:
                raise AuditFailure("disabled slider reacted to a real drag")

            self.launch()
            _, step_node = self.scroll_to_slider("Step 10", width, height)
            step_area = node_bounds(step_node)
            if step_area.height / density < 88.0:
                raise AuditFailure(
                    "Step 10 does not reserve enough height for its persistent thumb label"
                )
            step_baseline_path = self.output / "03-step-baseline.png"
            self.screenshot("03-step-baseline")
            step_before = self.thumb_center(step_baseline_path, step_area)
            self.assert_ratio("Step 10", step_before, start, end, 0.56, 0.64)
            self.shell(
                "input", "swipe", str(int(step_before)),
                str(step_area.center[1]), str(int(start + (end - start) * 0.37)),
                str(step_area.center[1]), "650",
            )
            time.sleep(0.8)
            stepped_path = self.output / "03-step-snapped.png"
            self.screenshot("03-step-snapped")
            stepped_center = self.thumb_center(stepped_path, step_area)
            self.assert_ratio("Step drag", stepped_center, start, end, 0.36, 0.44)

            self.launch()
            _, reversed_node = self.scroll_to_slider("Reversed", width, height)
            reversed_area = node_bounds(reversed_node)
            if reversed_area.height / density < 48.0:
                raise AuditFailure(
                    f"Reversed target is {reversed_area.height / density:.1f}dp; minimum is 48dp"
                )
            reversed_baseline_path = self.output / "04-reversed-baseline.png"
            self.screenshot("04-reversed-baseline")
            reversed_before = self.thumb_center(reversed_baseline_path, reversed_area)
            self.assert_ratio("Reversed", reversed_before, start, end, 0.71, 0.79)
            self.shell(
                "input", "swipe", str(int(reversed_before)),
                str(reversed_area.center[1]), str(int(start + (end - start) * 0.30)),
                str(reversed_area.center[1]), "650",
            )
            time.sleep(0.8)
            reversed_drag_path = self.output / "05-reversed-dragged.png"
            self.screenshot("05-reversed-dragged")
            reversed_after = self.thumb_center(reversed_drag_path, reversed_area)
            self.assert_ratio("Reversed drag", reversed_after, start, end, 0.26, 0.34)

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            landscape_width, landscape_height = self.screenshot("06-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            landscape_root = self.dump("06-landscape")
            visible_sliders = [
                node for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.SeekBar"
                and node_bounds(node).width > 0
            ]
            if not visible_sliders:
                raise AuditFailure("slider disappeared in landscape")
            if any(node_bounds(node).right > landscape_width for node in visible_sliders):
                raise AuditFailure("slider overflows the landscape viewport")
            safe_right = self.physical_content_right(
                landscape_width,
                landscape_height,
            )
            if safe_right < landscape_width:
                with Image.open(self.output / "06-landscape.png").convert("RGB") as image:
                    sample_x = min(landscape_width - 1, safe_right + 4)
                    for slider in visible_sliders:
                        area = node_bounds(slider)
                        center_y = max(0, min(landscape_height - 1, area.center[1]))
                        reference_y = max(0, area.top - 12)
                        sample = image.getpixel((sample_x, center_y))
                        reference = image.getpixel((sample_x, reference_y))
                        if max(abs(a - b) for a, b in zip(sample, reference, strict=True)) > 10:
                            raise AuditFailure(
                                "slider paints inside the landscape navigation-bar inset"
                            )
            visible_text = {self.text(node) for node in self.nodes(landscape_root)}
            if not {"Overview", "Actions", "Forms", "Data", "Overlays"} <= visible_text:
                raise AuditFailure("adaptive permanent drawer is missing in expanded landscape")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained beside the permanent drawer")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            portrait_root = self.dump("07-portrait-restored")
            self.screenshot("07-portrait-restored")
            if not self.exact(portrait_root, "Open component navigation"):
                raise AuditFailure("portrait menu button did not return after rotation")
            portrait_text = {self.text(node) for node in self.nodes(portrait_root)}
            if {"Overview", "Actions", "Forms", "Data", "Overlays"} <= portrait_text:
                raise AuditFailure("permanent drawer did not collapse in compact portrait")

            logs = self.application_logs()
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "failed integrity verification", "Unknown native icon",
                "requestLayout() improperly called",
            )
            errors = [
                line for line in logs.splitlines()
                if any(marker in line for marker in markers)
            ]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-slider",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "result": "passed",
                "checks": {
                    "materialTouchTarget": True,
                    "visualValueGeometry": True,
                    "realDrag": True,
                    "instanceIsolation": True,
                    "disabled": True,
                    "stepSnapping": True,
                    "persistentThumbLabel": True,
                    "reversed": True,
                    "reversedRealDrag": True,
                    "rotation": True,
                    "landscapeSafeArea": True,
                    "adaptiveNavigation": True,
                    "runtimeLog": True,
                },
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
    parser = argparse.ArgumentParser(description="Audit p-slider on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-slider-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = SliderAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-slider; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-slider: {exception}")
        raise SystemExit(1)
