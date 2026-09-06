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
SPEC = importlib.util.spec_from_file_location("pam_card_audit_shared", MODULE_PATH)
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


class CardAudit(AutocompleteAudit):
    VARIATIONS = (
        "Elevated",
        "Filled",
        "Outlined",
        "Interactive",
        "Horizontal",
        "Loading",
        "Disabled",
        "Tile",
    )
    TITLES = {
        "Elevated": "Native experience",
        "Filled": "Quiet workspace",
        "Outlined": "Release checklist",
        "Interactive": "Native experience",
        "Horizontal": "Native everywhere",
        "Loading": "Syncing changes",
        "Disabled": "Archived workspace",
        "Tile": "Edge-to-edge surface",
    }
    DESCRIPTIONS = {
        "Interactive": "Open native experience card",
        "Disabled": "Archived workspace card",
    }
    EXPECTED_SURFACES = {
        "Elevated": (245, 248, 246),
        "Filled": (223, 229, 224),
        "Outlined": (248, 250, 252),
    }
    MIN_HEIGHTS_DP = {
        "Elevated": 160.0,
        "Filled": 104.0,
        "Outlined": 124.0,
        "Interactive": 160.0,
        "Horizontal": 188.0,
        "Loading": 108.0,
        "Disabled": 160.0,
        "Tile": 104.0,
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-card",
            component_label="Card",
        )
        self.original_font_scale = ""

    @staticmethod
    def contains(outer: Bounds, inner: Bounds) -> bool:
        return (
            outer.left <= inner.left
            and outer.top <= inner.top
            and outer.right >= inner.right
            and outer.bottom >= inner.bottom
        )

    @staticmethod
    def distance(left: tuple[int, int, int], right: tuple[int, int, int]) -> float:
        return math.sqrt(sum((a - b) ** 2 for a, b in zip(left, right)))

    def card(self, root: ET.Element, variation: str) -> ET.Element:
        title_nodes = self.exact(root, self.TITLES[variation])
        if not title_nodes:
            raise AuditFailure(f"{variation} title is missing")
        description = self.DESCRIPTIONS.get(variation, "Card preview")
        candidates: list[ET.Element] = []
        for title in title_nodes:
            title_area = node_bounds(title)
            for node in self.nodes(root):
                area = node_bounds(node)
                if (
                    node.attrib.get("content-desc") == description
                    and area.width > 0
                    and area.height > 0
                    and self.contains(area, title_area)
                ):
                    candidates.append(node)
        unique = {node.attrib.get("bounds", ""): node for node in candidates}
        if len(unique) != 1:
            raise AuditFailure(f"{variation} exposes {len(unique)} matching card surfaces")
        return next(iter(unique.values()))

    def caption(self, root: ET.Element, variation: str, card: ET.Element) -> ET.Element:
        card_area = node_bounds(card)
        candidates = [
            node
            for node in self.exact(root, variation)
            if node_bounds(node).bottom <= card_area.top and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"{variation} caption is missing")
        return max(candidates, key=lambda node: node_bounds(node).bottom)

    def scroll_to(
        self,
        variation: str,
        width: int,
        height: int,
        prefix: str,
    ) -> tuple[ET.Element, ET.Element]:
        # The catalog edge is the Android navigation inset (24dp on the audit
        # profiles), not an arbitrary 110px margin. This remains density-aware
        # in portrait and landscape while keeping the card out of system UI.
        safe_bottom = height - int(round(24.0 * self.density()))
        for attempt in range(24):
            root = self.dump(f"{prefix}-{variation.lower()}-{attempt:02d}")
            try:
                card = self.card(root, variation)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(card)
            if area.top >= 145 and area.bottom <= safe_bottom:
                if (
                    area.height / self.density()
                    < self.MIN_HEIGHTS_DP[variation] - 2.0
                ):
                    self.swipe_up(width, height)
                    continue
                if variation in {"Elevated", "Interactive", "Disabled"}:
                    actions = [
                        node for node in self.nodes(root)
                        if node.attrib.get("content-desc")
                        in {"Show card details", "Continue from card"}
                        and self.contains(area, node_bounds(node))
                    ]
                    if len(actions) != 2 or any(
                        node_bounds(action).height / self.density() < 39.0
                        for action in actions
                    ):
                        self.swipe_up(width, height)
                        continue
                return root, card
            if area.bottom > safe_bottom:
                self.swipe_up(width, height)
            else:
                self.shell(
                    "input", "swipe", str(width // 2), str(int(height * 0.32)),
                    str(width // 2), str(int(height * 0.72)), "500",
                )
                time.sleep(0.6)
        raise AuditFailure(f"could not bring {variation} card fully into the viewport")

    def action(self, root: ET.Element, card: ET.Element, description: str) -> ET.Element:
        area = node_bounds(card)
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == description
            and self.contains(area, node_bounds(node))
            and node_bounds(node).height > 0
        ]
        if len(candidates) != 1:
            raise AuditFailure(f"card exposes {len(candidates)} actions named {description!r}")
        return candidates[0]

    def card_has_text(self, root: ET.Element, card: ET.Element, text: str) -> bool:
        area = node_bounds(card)
        return any(
            self.text(node) == text
            and node_bounds(node).height > 0
            and self.contains(area, node_bounds(node))
            for node in self.nodes(root)
        )

    def assert_geometry(
        self,
        root: ET.Element,
        card: ET.Element,
        variation: str,
        width: int,
        height: int,
    ) -> dict[str, float]:
        density = self.density()
        tolerance = max(2, int(round(1.5 * density)))
        area = node_bounds(card)
        caption_area = node_bounds(self.caption(root, variation, card))
        if abs(area.left - caption_area.left) > tolerance or abs(area.right - caption_area.right) > tolerance:
            raise AuditFailure(f"{variation} does not align with the adaptive content pane")
        gap = (area.top - caption_area.bottom) / density
        if abs(gap - 8.0) > 1.75:
            raise AuditFailure(f"{variation} caption gap is {gap:.1f}dp instead of 8dp")
        if (
            area.width / density < 280.0
            or area.height / density < self.MIN_HEIGHTS_DP[variation] - 2.0
        ):
            raise AuditFailure(f"{variation} card is unexpectedly undersized")
        if area.left < 0 or area.right > width or area.top < 0 or area.bottom > height:
            raise AuditFailure(f"{variation} escaped the physical viewport")

        expected_enabled = variation not in {"Loading", "Disabled"}
        if (card.attrib.get("enabled") == "true") != expected_enabled:
            raise AuditFailure(f"{variation} accessibility enabled state is incorrect")
        if variation == "Interactive":
            if card.attrib.get("class") != "android.widget.Button" or card.attrib.get("clickable") != "true":
                raise AuditFailure("interactive card does not expose one native button surface")
        elif variation == "Disabled" and card.attrib.get("clickable") != "true":
            raise AuditFailure("disabled interactive card lost its control semantics")

        action_height = 0.0
        if variation in {"Elevated", "Interactive", "Disabled"}:
            details = self.action(root, card, "Show card details")
            proceed = self.action(root, card, "Continue from card")
            for action in (details, proceed):
                action_area = node_bounds(action)
                if action_area.height / density < 39.0:
                    raise AuditFailure(f"{variation} action container is below the Material 40dp height")
                if (action.attrib.get("enabled") == "true") != expected_enabled:
                    raise AuditFailure(f"{variation} action enabled state is incorrect")
            if node_bounds(details).right >= node_bounds(proceed).left:
                raise AuditFailure(f"{variation} actions overlap")
            action_height = node_bounds(details).height / density
        else:
            for description in ("Show card details", "Continue from card"):
                if any(
                    node.attrib.get("content-desc") == description
                    and self.contains(area, node_bounds(node))
                    for node in self.nodes(root)
                ):
                    raise AuditFailure(f"{variation} repeats actions without a semantic need")

        for node in card.iter("node"):
            if node.attrib.get("class") != "android.widget.TextView":
                continue
            text_area = node_bounds(node)
            if text_area.width > 0 and text_area.height > 0 and not self.contains(area, text_area):
                raise AuditFailure(f"{variation} contains clipped text")

        return {
            "widthDp": round(area.width / density, 2),
            "heightDp": round(area.height / density, 2),
            "captionGapDp": round(gap, 2),
            "actionHeightDp": round(action_height, 2),
        }

    def assert_surface_tokens(
        self,
        root: ET.Element,
        card: ET.Element,
        variation: str,
        screenshot_name: str,
    ) -> dict[str, float]:
        area = node_bounds(card)
        density = self.density()
        expected = self.EXPECTED_SURFACES[variation]
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            sample = image.getpixel(
                (area.right - int(round(18 * density)), area.top + int(round(18 * density)))
            )
            difference = self.distance(sample, expected)
            if difference > 8.0:
                raise AuditFailure(
                    f"{variation} surface color {sample} misses token {expected} by {difference:.1f}"
                )
            if variation == "Outlined":
                border_pixels = [
                    image.getpixel((area.left, y))
                    for y in range(area.top + int(16 * density), area.bottom - int(16 * density))
                ]
                outline = (194, 202, 195)
                if min((self.distance(pixel, outline) for pixel in border_pixels), default=999.0) > 12.0:
                    raise AuditFailure("outlined card has no 1dp outline-variant border")
            background = (248, 250, 252)
            shadow_rows = min(image.height - area.bottom, int(round(4 * density)))
            shadow_pixels = sum(
                1
                for y in range(area.bottom, area.bottom + shadow_rows)
                for x in range(area.left, area.right)
                if self.distance(image.getpixel((x, y)), background) > 3.0
            )
            row_area = max(1, area.width * shadow_rows)
            shadow_ratio = shadow_pixels / row_area
            if variation == "Elevated" and shadow_ratio < 0.50:
                raise AuditFailure("elevated card has no measurable native shadow")
            if variation != "Elevated" and shadow_ratio > 0.05:
                raise AuditFailure(f"{variation} card renders an unexpected shadow")
        return {
            "surfaceColorDistance": round(difference, 2),
            "shadowPixelRatio": round(shadow_ratio, 4),
        }

    def assert_corner_shape(
        self,
        card: ET.Element,
        variation: str,
        screenshot_name: str,
    ) -> int:
        area = node_bounds(card)
        density = self.density()
        edge = max(8, int(round(5 * density)))
        outline = (194, 202, 195)
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            matches = sum(
                1
                for y in range(area.top, min(area.bottom, area.top + edge))
                for x in range(area.left, min(area.right, area.left + edge))
                if self.distance(image.getpixel((x, y)), outline) <= 12.0
            )
        if variation == "Outlined" and matches > edge * 2:
            raise AuditFailure("outlined card lost its rounded 12dp corner")
        if variation == "Tile" and matches < edge * 2:
            raise AuditFailure("tile card did not render a square outlined corner")
        return matches

    def assert_horizontal(
        self,
        root: ET.Element,
        card: ET.Element,
        screenshot_name: str,
    ) -> dict[str, float]:
        area = node_bounds(card)
        density = self.density()
        pam = self.exact(root, "PAM")
        title = self.exact(root, "Native everywhere")
        if len(pam) != 1 or len(title) != 1:
            raise AuditFailure("horizontal card media or title is missing")
        primary = (22, 101, 52)
        sample_y = area.top + int(round(16 * density))
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            primary_x = [
                x
                for x in range(area.left, area.right)
                if self.distance(image.getpixel((x, sample_y)), primary) <= 8.0
            ]
            if not primary_x:
                raise AuditFailure("horizontal card media region is missing")
            media_right = max(primary_x) + 1
            media_width = (media_right - area.left) / density
            lower_pixel = image.getpixel(
                (area.left + int(round(16 * density)), area.bottom - 2)
            )
        if abs(media_width - 112.0) > 1.5:
            raise AuditFailure(f"horizontal media is {media_width:.1f}dp instead of 112dp")
        if node_bounds(title[0]).left <= media_right:
            raise AuditFailure("horizontal card text overlaps its media region")
        if self.distance(lower_pixel, primary) > 12.0:
            raise AuditFailure("horizontal media does not fill the card height")
        return {"mediaWidthDp": round(media_width, 2)}

    def assert_loading(
        self,
        card: ET.Element,
        screenshot_name: str,
    ) -> dict[str, float]:
        area = node_bounds(card)
        density = self.density()
        filled = (223, 229, 224)
        with Image.open(self.output / f"{screenshot_name}.png").convert("RGB") as image:
            center_x = area.center[0]
            changed_rows = []
            for y in range(area.top, min(area.bottom, area.top + int(round(8 * density)))):
                if self.distance(image.getpixel((center_x, y)), filled) > 20.0:
                    changed_rows.append(y)
                elif changed_rows:
                    break
            track_height = len(changed_rows) / density
            sample_y = area.top + max(1, int(round(2 * density)))
            inset = int(round(12 * density))
            row = [
                image.getpixel((x, sample_y))
                for x in range(area.left + inset, area.right - inset)
            ]
            contrasting = sum(self.distance(pixel, filled) > 20.0 for pixel in row)
        if not 3.0 <= track_height <= 5.0:
            raise AuditFailure(f"loading track is {track_height:.1f}dp instead of 4dp")
        if not row or contrasting / len(row) < 0.90:
            raise AuditFailure("loading progress track is not continuous across the card top")
        return {"trackHeightDp": round(track_height, 2)}

    def assert_interaction(self, width: int, height: int) -> None:
        self.launch()
        root, card = self.scroll_to("Interactive", width, height, "interactive-card")
        area = node_bounds(card)
        self.tap((area.right - int(20 * self.density()), area.top + int(56 * self.density())))
        after = self.dump("interaction-card-result")
        if not self.card_has_text(after, card, "Card activated"):
            raise AuditFailure("pressing the card body did not execute its callback")
        self.tap((area.right - int(20 * self.density()), area.top + int(56 * self.density())))
        reset = self.dump("interaction-card-reset")
        if not self.card_has_text(reset, card, self.TITLES["Interactive"]):
            raise AuditFailure("pressing the active card did not restore its initial state")

        for action_name, result in (
            ("Show card details", "Details selected"),
            ("Continue from card", "Continue selected"),
        ):
            self.launch()
            current, current_card = self.scroll_to("Interactive", width, height, "interaction-action")
            self.tap(node_bounds(self.action(current, current_card, action_name)))
            changed = self.dump("interaction-" + result.lower().replace(" ", "-"))
            if (
                not self.card_has_text(changed, current_card, result)
                or self.card_has_text(changed, current_card, "Card activated")
            ):
                raise AuditFailure(f"{action_name} did not remain isolated from the parent card")
            current_area = node_bounds(current_card)
            self.tap((
                current_area.right - int(20 * self.density()),
                current_area.top + int(56 * self.density()),
            ))
            reset = self.dump("interaction-action-reset-" + action_name.lower().replace(" ", "-"))
            if not self.card_has_text(reset, current_card, self.TITLES["Interactive"]):
                raise AuditFailure(f"{action_name} result could not be reset from the card body")

        self.launch()
        disabled_root, disabled = self.scroll_to("Disabled", width, height, "disabled")
        disabled_title = self.TITLES["Disabled"]
        self.tap(node_bounds(disabled))
        self.tap(node_bounds(self.action(disabled_root, disabled, "Show card details")))
        inert = self.dump("disabled-after-taps")
        if not self.card_has_text(inert, disabled, disabled_title):
            raise AuditFailure("disabled card or action dispatched a callback")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            width, height = self.screenshot("00-baseline")
            geometry: dict[str, dict[str, float]] = {}
            visual: dict[str, dict[str, float]] = {}
            for index, variation in enumerate(self.VARIATIONS):
                root, card = self.scroll_to(variation, width, height, "geometry")
                geometry[variation] = self.assert_geometry(root, card, variation, width, height)
                screenshot = f"{index + 1:02d}-{variation.lower()}"
                self.screenshot(screenshot)
                if variation in self.EXPECTED_SURFACES:
                    visual[variation] = self.assert_surface_tokens(root, card, variation, screenshot)
                if variation in {"Outlined", "Tile"}:
                    visual.setdefault(variation, {})["cornerOutlinePixels"] = self.assert_corner_shape(
                        card, variation, screenshot
                    )
                if variation == "Horizontal":
                    geometry[variation].update(self.assert_horizontal(root, card, screenshot))
                if variation == "Loading":
                    geometry[variation].update(self.assert_loading(card, screenshot))

            self.assert_interaction(width, height)

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            font_root, font_card = self.scroll_to("Horizontal", width, height, "font130")
            self.assert_geometry(font_root, font_card, "Horizontal", width, height)
            self.screenshot("09-font-scale-130")
            self.assert_horizontal(font_root, font_card, "09-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "card landscape")
            landscape_width, landscape_height = self.screenshot("10-landscape")
            landscape_root, landscape_card = self.scroll_to(
                "Horizontal", landscape_width, landscape_height, "landscape"
            )
            self.assert_geometry(
                landscape_root, landscape_card, "Horizontal", landscape_width, landscape_height
            )
            self.screenshot("10-landscape-horizontal")
            self.assert_horizontal(landscape_root, landscape_card, "10-landscape-horizontal")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "card portrait restore")

            self.launch()
            stress_root, stress_card = self.scroll_to("Interactive", width, height, "stress")
            stress_area = node_bounds(stress_card)
            point = (
                stress_area.right - int(20 * self.density()),
                stress_area.top + int(56 * self.density()),
            )
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for _ in range(20):
                self.tap(point)
                time.sleep(0.14)
            time.sleep(0.8)
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            stress_result = self.dump("11-stress-result")
            self.screenshot("11-stress-result")
            if not self.card_has_text(
                stress_result, stress_card, self.TITLES["Interactive"]
            ):
                raise AuditFailure("twenty card presses did not return to the initial state")
            frame_match = re.search(r"Total frames rendered:\s*(\d+)", gfx)
            jank_match = re.search(r"Janky frames:\s*(\d+)", gfx)
            p99_match = re.search(r"99th percentile:\s*(\d+)ms", gfx)
            missed_match = re.search(r"Number Missed Vsync:\s*(\d+)", gfx)
            slow_match = re.search(r"Number Slow UI thread:\s*(\d+)", gfx)
            frames = int(frame_match.group(1)) if frame_match else 0
            janky = int(jank_match.group(1)) if jank_match else 0
            p99 = int(p99_match.group(1)) if p99_match else 10**9
            missed = int(missed_match.group(1)) if missed_match else 10**9
            slow = int(slow_match.group(1)) if slow_match else 10**9
            if frames <= 0 or p99 > 17 or missed > 0 or slow > 0:
                raise AuditFailure(
                    f"card stress failed: frames={frames}, p99={p99}, missed={missed}, slow={slow}"
                )

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "ANR in dev.pam.mobileui.catalog", "Input dispatching timed out",
            )
            errors = [line for line in logs.splitlines() if any(marker in line for marker in markers)]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-card",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allEightVariations": True,
                    "materialElevatedFilledOutlined": True,
                    "surfaceTokenPixels": True,
                    "outlinedOneDpBorder": True,
                    "cardBodyCallback": True,
                    "independentActionCallbacks": True,
                    "disabledCardAndActions": True,
                    "loadingTrackFourDp": True,
                    "loadingHasNoRedundantActions": True,
                    "horizontalMedia112Dp": True,
                    "horizontalTextNoOverlap": True,
                    "materialActionGeometry": True,
                    "tileGeometry": True,
                    "fontScale130": True,
                    "adaptiveLandscape": True,
                    "rapidTwentyTapStress": True,
                    "frameP99AtMost17Ms": True,
                    "zeroMissedVsync": True,
                    "zeroSlowUiThreadFrames": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "density": self.density(),
                    "geometry": geometry,
                    "visual": visual,
                    "stressFrames": frames,
                    "stressJankyFrames": janky,
                    "stressP99Ms": p99,
                    "stressMissedVsync": missed,
                    "stressSlowUiThreadFrames": slow,
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
    parser = argparse.ArgumentParser(description="Audit p-card on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-card-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = CardAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-card; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-card: {exception}")
        raise SystemExit(1)
