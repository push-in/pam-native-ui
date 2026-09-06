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


MODULE_PATH = Path(__file__).with_name("audit-slider-android.py")
SPEC = importlib.util.spec_from_file_location("pam_range_slider_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared slider audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
SliderAudit = MODULE.SliderAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class ResultStatus(IntEnum):
    PASSED = 1
    FAILED = 2


class RangeSliderAudit(SliderAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output)
        self.component_tag = "p-range-slider"
        self.component_label = "Range Slider"

    def prepare(self) -> None:
        super().prepare()
        try:
            self.original_settings["system:font_scale"] = self.setting(
                "system", "font_scale",
            )
        except BaseException:
            self.restore()
            raise

    @staticmethod
    def thumb_centers(path: Path, area: Bounds) -> list[float]:
        with Image.open(path).convert("RGB") as image:
            counts: list[tuple[int, int]] = []
            for x in range(max(0, area.left), min(image.width, area.right)):
                count = 0
                for y in range(max(0, area.top), min(image.height, area.bottom)):
                    red, green, blue = image.getpixel((x, y))
                    if green >= red + 18 and green >= blue + 10 and green >= 75:
                        count += 1
                counts.append((x, count))
        peak = max((count for _, count in counts), default=0)
        if peak < 18:
            raise AuditFailure(f"range handles were not visually detectable (peak={peak})")
        columns = [x for x, count in counts if count >= peak * 0.72]
        groups: list[list[int]] = []
        for x in columns:
            if not groups or x > groups[-1][-1] + 1:
                groups.append([x])
            else:
                groups[-1].append(x)
        centers = [(group[0] + group[-1]) / 2.0 for group in groups if len(group) >= 3]
        if not centers:
            raise AuditFailure("range handle geometry was not detectable")
        return centers

    @staticmethod
    def ratios(centers: list[float], start: float, end: float) -> list[float]:
        return sorted((center - start) / max(1.0, end - start) for center in centers)

    @staticmethod
    def track_center_y(path: Path, area: Bounds) -> int:
        with Image.open(path).convert("RGB") as image:
            rows: list[tuple[int, int]] = []
            inactive_rows: list[tuple[int, int]] = []
            for y in range(max(0, area.top), min(image.height, area.bottom)):
                count = 0
                inactive_count = 0
                for x in range(max(0, area.left), min(image.width, area.right)):
                    red, green, blue = image.getpixel((x, y))
                    if green >= red + 18 and green >= blue + 10 and green >= 75:
                        count += 1
                    # Persistent value labels can contain more accent pixels
                    # than a zero-length active range. The inactive rail is
                    # still the longest muted shape in the control, so use it
                    # to locate the real touch axis whenever it is visible.
                    if (
                        170 <= red <= 245
                        and 170 <= green <= 245
                        and 170 <= blue <= 245
                        and max(red, green, blue) - min(red, green, blue) <= 18
                    ):
                        inactive_count += 1
                rows.append((y, count))
                inactive_rows.append((y, inactive_count))
        inactive_peak = max((count for _, count in inactive_rows), default=0)
        if inactive_peak >= max(18, round(area.width * 0.35)):
            candidates = [
                y for y, count in inactive_rows
                if count >= inactive_peak * 0.96
            ]
            return round((candidates[0] + candidates[-1]) / 2.0)
        peak = max((count for _, count in rows), default=0)
        if peak < 18:
            raise AuditFailure(f"range track was not visually detectable (peak={peak})")
        candidates = [y for y, count in rows if count >= peak * 0.96]
        return round((candidates[0] + candidates[-1]) / 2.0)

    @staticmethod
    def vertical_thumb_centers(path: Path, area: Bounds) -> list[float]:
        with Image.open(path).convert("RGB") as image:
            counts: list[tuple[int, int]] = []
            for y in range(max(0, area.top), min(image.height, area.bottom)):
                count = 0
                for x in range(max(0, area.left), min(image.width, area.right)):
                    red, green, blue = image.getpixel((x, y))
                    if green >= red + 18 and green >= blue + 10 and green >= 75:
                        count += 1
                counts.append((y, count))
        peak = max((count for _, count in counts), default=0)
        if peak < 36:
            raise AuditFailure(f"vertical range handles were not detectable (peak={peak})")
        rows = [y for y, count in counts if count >= peak * 0.78]
        groups: list[list[int]] = []
        for y in rows:
            if not groups or y > groups[-1][-1] + 1:
                groups.append([y])
            else:
                groups[-1].append(y)
        centers = [(group[0] + group[-1]) / 2.0 for group in groups if len(group) >= 3]
        if not centers:
            raise AuditFailure("vertical range handle geometry was not detectable")
        return centers

    @staticmethod
    def vertical_ratios(centers: list[float], area: Bounds) -> list[float]:
        return sorted(
            (area.bottom - center) / max(1.0, area.height)
            for center in centers
        )

    @staticmethod
    def green_pixels(
        path: Path,
        area: Bounds,
        *,
        top: int | None = None,
        bottom: int | None = None,
        left: int | None = None,
        right: int | None = None,
    ) -> int:
        with Image.open(path).convert("RGB") as image:
            count = 0
            for y in range(
                max(0, area.top if top is None else top),
                min(image.height, area.bottom if bottom is None else bottom),
            ):
                for x in range(
                    max(0, area.left if left is None else left),
                    min(image.width, area.right if right is None else right),
                ):
                    red, green, blue = image.getpixel((x, y))
                    if green >= red + 18 and green >= blue + 10 and green >= 75:
                        count += 1
            return count

    @staticmethod
    def dark_pixels(
        path: Path,
        area: Bounds,
        *,
        top: int,
        bottom: int,
    ) -> int:
        with Image.open(path).convert("RGB") as image:
            count = 0
            for y in range(max(0, top), min(image.height, bottom)):
                for x in range(max(0, area.left), min(image.width, area.right)):
                    red, green, blue = image.getpixel((x, y))
                    if red < 150 and green < 150 and blue < 150:
                        count += 1
            return count

    @staticmethod
    def assert_ratios(label: str, actual: list[float], expected: list[float], tolerance: float = 0.045) -> None:
        if len(actual) != len(expected):
            raise AuditFailure(f"{label} exposes {len(actual)} handle(s), expected {len(expected)}")
        for index, (value, target) in enumerate(zip(actual, expected, strict=True)):
            if abs(value - target) > tolerance:
                raise AuditFailure(
                    f"{label} handle {index + 1} is at {value:.3f}; expected {target:.3f}"
                )

    def screenshot_centers(self, name: str, nodes: dict[str, object]) -> dict[str, list[float]]:
        self.screenshot(name)
        path = self.output / f"{name}.png"
        return {
            label: self.thumb_centers(path, node_bounds(node))
            for label, node in nodes.items()
        }

    def run_advanced(
        self,
        width: int,
        height: int,
        start: float,
        end: float,
        density: float,
    ) -> dict[str, bool]:
        self.launch("advanced")
        _, read_only = self.scroll_to_range("Read Only", width, height)
        read_only_area = node_bounds(read_only)
        read_only_before_path = self.output / "10-read-only-baseline.png"
        self.screenshot("10-read-only-baseline")
        read_only_before = self.thumb_centers(read_only_before_path, read_only_area)
        read_only_y = self.track_center_y(read_only_before_path, read_only_area)
        self.shell(
            "input", "swipe", str(int(read_only_before[0])), str(read_only_y),
            str(int(start + (end - start) * 0.45)), str(read_only_y), "650",
        )
        time.sleep(0.8)
        read_only_after_path = self.output / "11-read-only-after-drag.png"
        self.screenshot("11-read-only-after-drag")
        read_only_after = self.thumb_centers(read_only_after_path, read_only_area)
        if any(
            abs(before - after) > 5.0
            for before, after in zip(read_only_before, read_only_after, strict=True)
        ):
            raise AuditFailure("read-only range reacted to a real drag")

        self.launch("advanced")
        _, transient = self.scroll_to_range("Transient labels", width, height)
        transient_area = node_bounds(transient)
        transient_baseline_path = self.output / "12-transient-labels-rest.png"
        self.screenshot("12-transient-labels-rest")
        transient_centers = self.thumb_centers(
            transient_baseline_path,
            transient_area,
        )
        transient_y = self.track_center_y(transient_baseline_path, transient_area)
        label_band_bottom = transient_area.top + round(32.0 * density)
        rest_green = self.green_pixels(
            transient_baseline_path,
            transient_area,
            bottom=label_band_bottom,
        )
        self.shell(
            "input", "motionevent", "DOWN",
            str(int(transient_centers[0])), str(transient_y),
        )
        self.shell(
            "input", "motionevent", "MOVE",
            str(int(start + (end - start) * 0.35)), str(transient_y),
        )
        time.sleep(0.25)
        transient_active_path = self.output / "13-transient-labels-active.png"
        self.screenshot("13-transient-labels-active")
        active_green = self.green_pixels(
            transient_active_path,
            transient_area,
            bottom=label_band_bottom,
        )
        self.shell(
            "input", "motionevent", "UP",
            str(int(start + (end - start) * 0.35)), str(transient_y),
        )
        time.sleep(0.5)
        transient_restored_path = self.output / "14-transient-labels-restored.png"
        self.screenshot("14-transient-labels-restored")
        restored_green = self.green_pixels(
            transient_restored_path,
            transient_area,
            bottom=label_band_bottom,
        )
        if active_green < rest_green + round(200.0 * density):
            raise AuditFailure("transient value labels did not appear during a real touch")
        if restored_green > rest_green + round(60.0 * density):
            raise AuditFailure("transient value labels remained visible after touch release")

        self.launch("advanced")
        _, tick_labels = self.scroll_to_range("Tick labels", width, height)
        tick_labels_area = node_bounds(tick_labels)
        if tick_labels_area.height / density < 72.0:
            raise AuditFailure("tick labels do not reserve their 72dp lane")
        tick_labels_path = self.output / "15-tick-labels.png"
        self.screenshot("15-tick-labels")
        label_pixels = self.dark_pixels(
            tick_labels_path,
            tick_labels_area,
            top=tick_labels_area.top + round(50.0 * density),
            bottom=tick_labels_area.bottom,
        )
        if label_pixels < round(24.0 * density):
            raise AuditFailure("tick-label text was not visibly rendered below the track")

        self.launch("advanced")
        _, custom_bounds = self.scroll_to_range("Custom bounds", width, height)
        custom_bounds_area = node_bounds(custom_bounds)
        custom_bounds_path = self.output / "16-custom-bounds.png"
        self.screenshot("16-custom-bounds")
        self.assert_ratios(
            "Custom bounds",
            self.ratios(
                self.thumb_centers(custom_bounds_path, custom_bounds_area),
                start,
                end,
            ),
            [0.20, 0.90],
        )

        self.launch("advanced")
        _, vertical = self.scroll_to_range(
            "Vertical",
            width,
            height,
            minimum_width_dp=48.0,
            minimum_height_dp=300.0,
        )
        vertical_area = node_bounds(vertical)
        if (
            vertical_area.width / density < 48.0 - 1.5
            or vertical_area.height / density < 300.0 - 1.5
        ):
            raise AuditFailure("vertical range does not expose its 48x300dp geometry")
        vertical_path = self.output / "17-vertical-baseline.png"
        self.screenshot("17-vertical-baseline")
        vertical_before = self.vertical_thumb_centers(vertical_path, vertical_area)
        self.assert_ratios(
            "Vertical",
            self.vertical_ratios(vertical_before, vertical_area),
            [0.20, 0.80],
        )
        lower_y = max(vertical_before)
        requested_lower_y = vertical_area.bottom - vertical_area.height * 0.40
        self.shell(
            "input", "swipe", str(vertical_area.center[0]), str(int(lower_y)),
            str(vertical_area.center[0]), str(int(requested_lower_y)), "650",
        )
        time.sleep(0.8)
        vertical_after_root = self.dump("18-vertical-dragged")
        vertical_after = self.slider_after(vertical_after_root, "Vertical")
        vertical_after_area = node_bounds(vertical_after)
        vertical_after_path = self.output / "18-vertical-dragged.png"
        self.screenshot("18-vertical-dragged")
        self.assert_ratios(
            "Vertical drag",
            self.vertical_ratios(
                self.vertical_thumb_centers(vertical_after_path, vertical_after_area),
                vertical_after_area,
            ),
            [0.40, 0.80],
        )

        self.launch("advanced")
        _, vertical_labelled = self.scroll_to_range(
            "Vertical labelled",
            width,
            height,
            minimum_width_dp=112.0,
            minimum_height_dp=300.0,
        )
        vertical_labelled_area = node_bounds(vertical_labelled)
        if vertical_labelled_area.width / density < 112.0:
            raise AuditFailure("vertical labels do not reserve their 112dp lane")
        vertical_labelled_path = self.output / "19-vertical-labelled.png"
        self.screenshot("19-vertical-labelled")
        vertical_label_green = self.green_pixels(
            vertical_labelled_path,
            vertical_labelled_area,
            left=vertical_labelled_area.left + round(48.0 * density),
        )
        if vertical_label_green < round(80.0 * density):
            raise AuditFailure("vertical value labels were clipped or missing")

        self.launch("advanced")
        _, descending = self.scroll_to_range("Descending input", width, height)
        descending_area = node_bounds(descending)
        descending_path = self.output / "20-descending-normalized.png"
        self.screenshot("20-descending-normalized")
        self.assert_ratios(
            "Descending input",
            self.ratios(
                self.thumb_centers(descending_path, descending_area),
                start,
                end,
            ),
            [0.20, 0.80],
        )

        self.launch("advanced")
        _, coincident = self.scroll_to_range("Coincident values", width, height)
        coincident_area = node_bounds(coincident)
        coincident_path = self.output / "21-coincident-baseline.png"
        self.screenshot("21-coincident-baseline")
        coincident_centers = self.thumb_centers(coincident_path, coincident_area)
        self.assert_ratios(
            "Coincident values",
            self.ratios(coincident_centers, start, end),
            [0.50],
        )
        coincident_y = self.track_center_y(coincident_path, coincident_area)
        self.shell(
            "input", "swipe", str(int(coincident_centers[0])), str(coincident_y),
            str(int(start + (end - start) * 0.40)), str(coincident_y), "650",
        )
        time.sleep(0.6)
        coincident_root = self.dump("22-coincident-lower-dragged")
        coincident_after = self.slider_after(coincident_root, "Coincident values")
        coincident_after_path = self.output / "22-coincident-lower-dragged.png"
        self.screenshot("22-coincident-lower-dragged")
        self.assert_ratios(
            "Coincident lower drag",
            self.ratios(
                self.thumb_centers(coincident_after_path, node_bounds(coincident_after)),
                start,
                end,
            ),
            [0.40, 0.50],
        )

        return {
            "readOnly": True,
            "transientThumbLabels": True,
            "tickLabels": True,
            "customBounds": True,
            "vertical": True,
            "verticalRealDrag": True,
            "verticalThumbLabels": True,
            "descendingInputNormalization": True,
            "coincidentEndpoints": True,
        }

    def run_scaled_text(self) -> bool:
        original_scale = self.original_settings["system:font_scale"]
        try:
            self.set_setting("system", "font_scale", "1.30")
            self.launch()
            portrait_root = self.dump("23-font-scale-130-portrait")
            portrait_width, _ = self.screenshot("23-font-scale-130-portrait")
            heading = min(
                self.exact(portrait_root, self.component_label),
                key=lambda node: node_bounds(node).top,
            )
            tag = min(
                self.exact(portrait_root, self.component_tag),
                key=lambda node: node_bounds(node).top,
            )
            variations = min(
                self.exact(portrait_root, "Variations"),
                key=lambda node: node_bounds(node).top,
            )
            default_label = min(
                self.exact(portrait_root, "Default"),
                key=lambda node: node_bounds(node).top,
            )
            default_slider = self.slider_after(portrait_root, "Default")
            ordered = [heading, tag, variations, default_label, default_slider]
            for before, after in zip(ordered, ordered[1:]):
                if node_bounds(before).bottom > node_bounds(after).top:
                    raise AuditFailure(
                        "130% system text scale overlaps the Range Slider hierarchy"
                    )
            if any(node_bounds(node).right > portrait_width for node in ordered):
                raise AuditFailure("130% system text scale overflows the portrait viewport")
            if len(self.thumb_centers(
                self.output / "23-font-scale-130-portrait.png",
                node_bounds(default_slider),
            )) != 2:
                raise AuditFailure("130% system text scale hides a portrait range handle")

            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            landscape_width, landscape_height = self.screenshot(
                "24-font-scale-130-landscape",
            )
            if landscape_width <= landscape_height:
                raise AuditFailure("130% text-scale check did not enter landscape")
            landscape_root = self.dump("24-font-scale-130-landscape")
            landscape_path = self.output / "24-font-scale-130-landscape.png"
            landscape_sliders = [
                node for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.SeekBar"
            ]
            if len(landscape_sliders) < 2:
                raise AuditFailure("130% text-scale landscape lost range sliders")
            for index, slider in enumerate(landscape_sliders[:2], start=1):
                area = node_bounds(slider)
                if area.right > landscape_width:
                    raise AuditFailure(
                        f"130% text-scale landscape slider {index} overflows"
                    )
                if len(self.thumb_centers(landscape_path, area)) != 2:
                    raise AuditFailure(
                        f"130% text-scale landscape slider {index} clips a handle"
                    )
            return True
        finally:
            self.set_setting("system", "user_rotation", "0")
            self.set_setting("system", "font_scale", original_scale)
            time.sleep(0.8)

    def scroll_to_range(
        self,
        label: str,
        width: int,
        height: int,
        *,
        minimum_width_dp: float = 0.0,
        minimum_height_dp: float = 48.0,
    ) -> tuple[object, object]:
        content_bottom = self.physical_content_bottom(width, height)
        density = self.density()
        minimum_target = round(48.0 * density)
        # Layout coordinates are integer pixels; at 420 dpi a canonical
        # 48x300dp control can round one edge down. Use the same 1.5dp geometry
        # tolerance as the component assertions instead of requiring ceil-like
        # pixel dimensions here.
        geometry_tolerance = round(1.5 * density)
        required_width = max(0, round(minimum_width_dp * density) - geometry_tolerance)
        required_height = max(0, round(minimum_height_dp * density) - geometry_tolerance)
        minimum_bottom_gap = round(16.0 * density)
        for attempt in range(10):
            slug = label.lower().replace(" ", "-")
            root = self.dump(f"scroll-{slug}-{attempt}")
            if self.exact(root, label):
                try:
                    slider = self.slider_after(root, label)
                except AuditFailure:
                    slider = None
                if slider is not None:
                    area = node_bounds(slider)
                    restored_bottom = area.top + minimum_target
                    if (
                        minimum_height_dp <= 48.0
                        and 0 < area.height < minimum_target
                        and area.top >= 120
                        and restored_bottom <= content_bottom - minimum_bottom_gap
                    ):
                        area = Bounds(area.left, area.top, area.right, restored_bottom)
                        slider.attrib["bounds"] = (
                            f"[{area.left},{area.top}][{area.right},{area.bottom}]"
                        )
                    if (
                        area.width >= required_width
                        and area.height >= required_height
                        and area.top >= 120
                        and area.bottom <= content_bottom - 32
                    ):
                        return root, slider
            # Use the page gutter so the gesture scrolls the route instead of
            # changing a visible range handle under the finger.
            x = max(1, width - round(8.0 * density))
            self.shell(
                "input", "swipe", str(x), str(int(height * 0.72)),
                str(x), str(int(height * 0.28)), "650",
            )
            time.sleep(0.8)
            self.assert_foreground("range slider audit scroll result")
        raise AuditFailure(f"could not bring {label!r} fully into the safe viewport")

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            labels = (
                "Default", "Isolated instance", "Disabled", "Full range",
                "Step 10", "Reversed",
            )
            nodes = {label: self.slider_after(root, label) for label in labels}
            self.assert_window_count(1, "baseline")
            if self.ime_shown():
                raise AuditFailure("range slider unexpectedly opened the keyboard")

            density = self.density()
            for label, node in nodes.items():
                area = node_bounds(node)
                if area.height / density < 48.0:
                    raise AuditFailure(f"{label} target is below the 48dp minimum")
                if node.attrib.get("focusable") != "true":
                    raise AuditFailure(f"{label} is not accessibility-focusable")
                expected_enabled = label != "Disabled"
                if (node.attrib.get("enabled") == "true") != expected_enabled:
                    raise AuditFailure(f"{label} exposes the wrong enabled state")

            baseline_path = self.output / "00-baseline.png"
            centers = {
                label: self.thumb_centers(baseline_path, node_bounds(node))
                for label, node in nodes.items()
            }
            full = centers["Full range"]
            if len(full) != 2:
                raise AuditFailure("full range does not expose both handles")
            start, end = full
            if end - start < width * 0.72:
                raise AuditFailure("range track wastes horizontal space or is clipped")
            self.assert_ratios("Default", self.ratios(centers["Default"], start, end), [0.24, 0.76])
            self.assert_ratios("Isolated", self.ratios(centers["Isolated instance"], start, end), [0.15, 0.45])
            self.assert_ratios("Step 10", self.ratios(centers["Step 10"], start, end), [0.20, 0.80])
            self.assert_ratios("Reversed", self.ratios(centers["Reversed"], start, end), [0.60, 0.90])

            default_area = node_bounds(nodes["Default"])
            lower, upper = centers["Default"]
            requested_lower = start + (end - start) * 0.36
            self.shell(
                "input", "swipe", str(int(lower)), str(default_area.center[1]),
                str(int(requested_lower)), str(default_area.center[1]), "650",
            )
            time.sleep(0.8)
            lower_root = self.dump("01-lower-dragged")
            lower_nodes = {label: self.slider_after(lower_root, label) for label in labels}
            lower_centers = self.screenshot_centers("01-lower-dragged", lower_nodes)
            self.assert_ratios(
                "Lower drag", self.ratios(lower_centers["Default"], start, end), [0.36, 0.76],
            )
            if any(
                abs(a - b) > 5.0
                for a, b in zip(
                    lower_centers["Isolated instance"],
                    centers["Isolated instance"],
                    strict=True,
                )
            ):
                raise AuditFailure("dragging one range corrupted another instance")

            current_lower, current_upper = lower_centers["Default"]
            requested_upper = start + (end - start) * 0.66
            self.shell(
                "input", "swipe", str(int(current_upper)), str(default_area.center[1]),
                str(int(requested_upper)), str(default_area.center[1]), "650",
            )
            time.sleep(0.8)
            upper_path = self.output / "02-upper-dragged.png"
            upper_root = self.dump("02-upper-dragged")
            upper_nodes = {label: self.slider_after(upper_root, label) for label in labels}
            self.screenshot("02-upper-dragged")
            upper_centers = self.thumb_centers(
                upper_path,
                node_bounds(upper_nodes["Default"]),
            )
            self.assert_ratios("Upper drag", self.ratios(upper_centers, start, end), [0.36, 0.66])

            disabled_area = node_bounds(upper_nodes["Disabled"])
            disabled_before = self.thumb_centers(upper_path, disabled_area)
            self.shell(
                "input", "swipe", str(int(disabled_before[0])),
                str(disabled_area.center[1]), str(int(start + (end - start) * 0.55)),
                str(disabled_area.center[1]), "650",
            )
            time.sleep(0.8)
            disabled_path = self.output / "03-disabled-after-drag.png"
            self.screenshot("03-disabled-after-drag")
            disabled_after = self.thumb_centers(disabled_path, disabled_area)
            if any(abs(a - b) > 5.0 for a, b in zip(disabled_before, disabled_after, strict=True)):
                raise AuditFailure("disabled range reacted to a real drag")

            self.launch()
            _, step_node = self.scroll_to_range("Step 10", width, height)
            step_area = node_bounds(step_node)
            if step_area.height / density < 80.0:
                raise AuditFailure(
                    "Step 10 does not reserve the 80dp persistent-label lane"
                )
            step_baseline_path = self.output / "04-step-baseline.png"
            self.screenshot("04-step-baseline")
            step_before = self.thumb_centers(step_baseline_path, step_area)
            step_touch_y = self.track_center_y(step_baseline_path, step_area)
            self.assert_ratios(
                "Step 10",
                self.ratios(step_before, start, end),
                [0.20, 0.80],
            )
            step_lower = step_before[0]
            self.shell(
                "input", "swipe", str(int(step_lower)), str(step_touch_y),
                str(int(start + (end - start) * 0.36)), str(step_touch_y), "650",
            )
            time.sleep(0.8)
            step_root = self.dump("05-step-snapped")
            step_node_after = self.slider_after(step_root, "Step 10")
            step_path = self.output / "05-step-snapped.png"
            self.screenshot("05-step-snapped")
            self.assert_ratios(
                "Step drag",
                self.ratios(
                    self.thumb_centers(step_path, node_bounds(step_node_after)),
                    start,
                    end,
                ),
                [0.40, 0.80],
            )

            self.launch()
            _, reversed_node = self.scroll_to_range("Reversed", width, height)
            reversed_area = node_bounds(reversed_node)
            reversed_baseline_path = self.output / "06-reversed-baseline.png"
            self.screenshot("06-reversed-baseline")
            reversed_before = self.thumb_centers(reversed_baseline_path, reversed_area)
            reversed_touch_y = self.track_center_y(
                reversed_baseline_path,
                reversed_area,
            )
            self.assert_ratios(
                "Reversed",
                self.ratios(reversed_before, start, end),
                [0.60, 0.90],
            )
            self.shell(
                "input", "swipe", str(int(reversed_before[0])),
                str(reversed_touch_y),
                str(int(start + (end - start) * 0.48)),
                str(reversed_touch_y), "650",
            )
            time.sleep(0.8)
            reversed_root = self.dump("07-reversed-dragged")
            reversed_node_after = self.slider_after(reversed_root, "Reversed")
            reversed_path = self.output / "07-reversed-dragged.png"
            self.screenshot("07-reversed-dragged")
            self.assert_ratios(
                "Reversed drag",
                self.ratios(
                    self.thumb_centers(reversed_path, node_bounds(reversed_node_after)),
                    start,
                    end,
                ),
                [0.48, 0.90],
            )

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            landscape_width, landscape_height = self.screenshot("08-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            landscape_root = self.dump("08-landscape")
            visible_text = {self.text(node) for node in self.nodes(landscape_root)}
            if not {"Overview", "Actions", "Forms", "Data", "Overlays"} <= visible_text:
                raise AuditFailure("adaptive permanent drawer is missing in landscape")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained in expanded navigation")
            landscape_sliders = [
                node for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.SeekBar"
            ]
            if not landscape_sliders or any(node_bounds(node).right > landscape_width for node in landscape_sliders):
                raise AuditFailure("range slider disappeared or overflowed in landscape")
            landscape_path = self.output / "08-landscape.png"
            for index, slider in enumerate(landscape_sliders[:2], start=1):
                area = node_bounds(slider)
                visible_handles = self.thumb_centers(landscape_path, area)
                if len(visible_handles) != 2:
                    raise AuditFailure(
                        f"landscape range slider {index} exposes "
                        f"{len(visible_handles)} visible handle(s); expected 2"
                    )
                edge_clearance = max(2.0 * density, 4.0)
                if (
                    visible_handles[0] - area.left < edge_clearance
                    or area.right - visible_handles[-1] < edge_clearance
                ):
                    raise AuditFailure(
                        f"landscape range slider {index} clips a handle at the viewport edge"
                    )
            safe_right = self.physical_content_right(
                landscape_width,
                landscape_height,
            )
            if safe_right < landscape_width:
                with Image.open(self.output / "08-landscape.png").convert("RGB") as image:
                    sample_x = min(landscape_width - 1, safe_right + 4)
                    for slider in landscape_sliders:
                        area = node_bounds(slider)
                        center_y = max(0, min(landscape_height - 1, area.center[1]))
                        reference_y = max(0, area.top - 12)
                        sample = image.getpixel((sample_x, center_y))
                        reference = image.getpixel((sample_x, reference_y))
                        if max(
                            abs(a - b)
                            for a, b in zip(sample, reference, strict=True)
                        ) > 10:
                            raise AuditFailure(
                                "range slider paints inside the landscape navigation-bar inset"
                            )
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            portrait_root = self.dump("09-portrait-restored")
            self.screenshot("09-portrait-restored")
            if not self.exact(portrait_root, "Open component navigation"):
                raise AuditFailure("portrait menu did not return after rotation")
            portrait_text = {self.text(node) for node in self.nodes(portrait_root)}
            if {"Overview", "Actions", "Forms", "Data", "Overlays"} <= portrait_text:
                raise AuditFailure("permanent drawer did not collapse in compact portrait")

            advanced_checks = self.run_advanced(
                width,
                height,
                start,
                end,
                density,
            )
            scaled_text = self.run_scaled_text()

            logs = self.shell("logcat", "-d", "-t", "1200", timeout=30.0)
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "failed integrity verification", "Unknown native icon",
                "requestLayout() improperly called",
            )
            errors = [line for line in logs.splitlines() if any(marker in line for marker in markers)]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-range-slider",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "materialTouchTarget": True,
                    "dualHandleGeometry": True,
                    "lowerAndUpperDrag": True,
                    "instanceIsolation": True,
                    "disabled": True,
                    "stepSnapping": True,
                    "persistentTicks": True,
                    "persistentThumbLabels": True,
                    "reversed": True,
                    "reversedRealDrag": True,
                    "bottomSafeArea": True,
                    "rotation": True,
                    "landscapeSafeArea": True,
                    "adaptiveNavigation": True,
                    "runtimeLog": True,
                    "dynamicTextScale": scaled_text,
                    **advanced_checks,
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n", encoding="utf-8",
            )
            return report
        finally:
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit p-range-slider on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog.debug")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-range-slider-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = RangeSliderAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-range-slider; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-range-slider: {exception}")
        raise SystemExit(1)
