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
SPEC = importlib.util.spec_from_file_location("pam_textarea_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class ResultStatus(IntEnum):
    PASSED = 1
    FAILED = 2


class TextareaAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-textarea",
            component_label="Textarea",
        )

    def expect_ime(self, expected: bool, context: str) -> None:
        deadline = time.monotonic() + (5.0 if expected else 2.0)
        while time.monotonic() < deadline:
            shown = self.ime_shown()
            if shown == expected:
                if expected:
                    return
                time.sleep(0.5)
                if not self.ime_shown():
                    return
            time.sleep(0.15)
        state = "open" if expected else "closed"
        raise AuditFailure(f"{context} did not keep the software keyboard {state}")

    def assert_light_status_bar_contrast(self, screenshot_name: str) -> None:
        dark_pixels = 0
        for attempt in range(6):
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
            if dark_pixels >= 500:
                return
            if attempt < 5:
                # On a cold emulator boot the Activity can be resumed a moment
                # before SystemUI attaches the status-bar surface. Keep the
                # contrast threshold strict, but wait for that surface instead
                # of treating an undrawn frame as a styling failure.
                time.sleep(0.6)
                self.screenshot(screenshot_name)
        raise AuditFailure(
            "light status bar has no readable dark system icons "
            f"({dark_pixels} contrast pixels)"
        )

    def input_after(self, root, label: str):
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"variation label {label!r} is missing")
        label_area = node_bounds(labels[0])
        candidates = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.EditText"
            and node_bounds(node).top >= label_area.bottom
        ]
        if not candidates:
            raise AuditFailure(f"textarea after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_input(self, label: str, width: int, height: int):
        slug = label.lower().replace(" ", "-")
        for attempt in range(14):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                field = self.input_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(field)
            if area.top >= 130 and area.bottom <= height - 100:
                return root, field
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} textarea into the viewport")

    def replace_text(self, value: str) -> None:
        self.shell("input", "keycombination", "113", "29")
        self.shell("input", "text", value)
        time.sleep(0.6)

    def append_line(self, value: str) -> None:
        self.shell("input", "keyevent", "ENTER")
        self.expect_ime(True, "multiline Enter")
        self.shell("input", "text", value)
        time.sleep(0.6)

    def assert_focus_indicator_geometry(self, root, screenshot_name: str) -> None:
        field = self.input_after(root, "Default")
        field_area = node_bounds(field)
        helpers = [
            node for node in self.exact(root, "Up to 280 characters")
            if node_bounds(node).top >= field_area.bottom
        ]
        if not helpers:
            raise AuditFailure("default textarea helper is missing below the editor")
        helper_area = node_bounds(min(helpers, key=lambda node: node_bounds(node).top))
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            left = max(0, field_area.left)
            right = min(image.width, field_area.right)
            required = max(1, int((right - left) * 0.7))

            def green_line_strength(top: int, bottom: int) -> tuple[int, int]:
                strongest = 0
                solid_rows = 0
                for y in range(max(0, top), min(image.height, bottom)):
                    count = sum(
                        1 for x in range(left, right)
                        if (
                            (pixel := image.getpixel((x, y)))[1] >= 80
                            and pixel[1] >= pixel[0] + 30
                            and pixel[1] >= pixel[2] + 20
                        )
                    )
                    strongest = max(strongest, count)
                    if count >= required:
                        solid_rows += 1
                return strongest, solid_rows

            on_surface, surface_rows = green_line_strength(
                field_area.bottom,
                helper_area.top,
            )
            below_helper, _ = green_line_strength(
                helper_area.top,
                helper_area.bottom + 5,
            )
        if on_surface < required:
            raise AuditFailure(
                "focused indicator did not replace the textarea-surface underline"
            )
        minimum_rows = max(2, int(self.density() * 1.5))
        if surface_rows < minimum_rows:
            raise AuditFailure(
                "focused indicator is thinner than the Material 2dp state line: "
                f"{surface_rows} solid pixels"
            )
        if below_helper >= required:
            raise AuditFailure(
                "focused indicator was drawn below the helper instead of on the textarea"
            )

    def assert_affix_first_line_alignment(
        self,
        field,
        prefix,
        suffix,
        screenshot_name: str,
    ) -> None:
        field_area = node_bounds(field)
        prefix_area = node_bounds(prefix)
        suffix_area = node_bounds(suffix)
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            def visible_glyph_top(left: int, top: int, right: int, bottom: int) -> int:
                for y in range(max(0, top), min(image.height, bottom)):
                    dark_pixels = sum(
                        1 for x in range(max(0, left), min(image.width, right))
                        if max(image.getpixel((x, y))) < 120
                    )
                    if dark_pixels >= 2:
                        return y
                raise AuditFailure("could not resolve visible textarea affix glyphs")

            prefix_top = visible_glyph_top(
                prefix_area.left,
                prefix_area.top,
                prefix_area.right,
                prefix_area.bottom,
            )
            value_top = visible_glyph_top(
                prefix_area.right,
                field_area.top,
                suffix_area.left,
                min(field_area.bottom, field_area.top + int(self.density() * 48)),
            )
            suffix_top = visible_glyph_top(
                suffix_area.left,
                suffix_area.top,
                suffix_area.right,
                suffix_area.bottom,
            )
        tolerance = max(4, int(self.density() * 3.0))
        if (
            abs(prefix_top - value_top) > tolerance
            or abs(suffix_top - value_top) > tolerance
        ):
            raise AuditFailure(
                "textarea affixes are not aligned to the first editable line: "
                f"prefix={prefix_top}px, value={value_top}px, suffix={suffix_top}px"
            )

    def assert_unchanged_after_tap(self, label: str, width: int, height: int) -> None:
        self.launch()
        root, field = self.scroll_to_input(label, width, height)
        before = field.attrib.get("text", "")
        self.tap(node_bounds(field))
        self.expect_ime(False, label)
        tapped_root = self.dump(f"state-{label.lower().replace(' ', '-')}-tapped")
        tapped_field = self.input_after(tapped_root, label)
        focused_inputs = [
            node for node in self.nodes(tapped_root)
            if node.attrib.get("class") == "android.widget.EditText"
            and node.attrib.get("focused") == "true"
        ]
        if label == "Disabled" and tapped_field.attrib.get("focused") == "true":
            raise AuditFailure("disabled textarea accepted input focus")
        if label == "Read Only" and focused_inputs:
            if focused_inputs != [tapped_field]:
                raise AuditFailure("read-only tap focused a different textarea")
            self.shell("input", "text", "SHOULD_NOT_APPEAR")
            time.sleep(0.5)
        after_root = self.dump(f"state-{label.lower().replace(' ', '-')}-verified")
        after_field = self.input_after(after_root, label)
        if after_field.attrib.get("text", "") != before:
            raise AuditFailure(f"{label} textarea mutated from an input command")
        if label == "Disabled" and after_field.attrib.get("enabled") == "true":
            raise AuditFailure("disabled textarea is accessibility-enabled")
        if label == "Read Only" and after_field.attrib.get("enabled") != "true":
            raise AuditFailure("read-only textarea is incorrectly disabled")
        self.screenshot(f"state-{label.lower().replace(' ', '-')}")

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_light_status_bar_contrast("00-baseline")
            self.assert_window_count(1, "baseline")
            required = (
                "Default", "Isolated instance", "Disabled", "Read Only",
                "Error", "Clearable", "Prefix and suffix", "Counter",
                "Auto grow", "Four rows", "Fixed rows",
            )
            for label in required:
                if not self.exact(root, label):
                    # Later variations can live below the visible viewport, so
                    # their presence is asserted when scrolled into view.
                    if label in required[:4]:
                        raise AuditFailure(f"audit surface is missing {label!r}")

            default = self.input_after(root, "Default")
            isolated = self.input_after(root, "Isolated instance")
            if default.attrib.get("text") != "Built for Android and iOS.":
                raise AuditFailure("default textarea value did not render")
            if isolated.attrib.get("text") != "Independent value":
                raise AuditFailure("isolated textarea value did not render")
            if default.attrib.get("hint") != "Write a message":
                raise AuditFailure("textarea placeholder is not exposed as the native hint")
            default_area = node_bounds(default)
            if default_area.height < 160 or default_area.width < int(width * 0.75):
                raise AuditFailure("default textarea is smaller than its mobile control surface")

            internal_labels = [
                node for node in self.exact(root, "Textarea")
                if node_bounds(node).top >= node_bounds(self.exact(root, "Default")[0]).bottom
                and node_bounds(node).bottom <= default_area.top
            ]
            if not internal_labels:
                raise AuditFailure("floating label for the default textarea is missing")
            self.tap(node_bounds(internal_labels[0]))
            self.expect_ime(True, "tapping the textarea label")
            self.replace_text("First")
            self.append_line("Second")
            self.append_line("Third")
            edited_root = self.dump("01-multiline-keyboard")
            self.screenshot("01-multiline-keyboard")
            edited = self.input_after(edited_root, "Default").attrib.get("text", "")
            if edited != "First\nSecond\nThird":
                raise AuditFailure(f"Enter did not create native multiline text: {edited!r}")
            self.assert_focus_indicator_geometry(edited_root, "01-multiline-keyboard")
            if self.input_after(edited_root, "Isolated instance").attrib.get("text") != "Independent value":
                raise AuditFailure("editing the textarea corrupted its sibling instance")
            self.back()
            self.expect_ime(False, "system Back")
            persisted_root = self.dump("02-keyboard-closed")
            self.screenshot("02-keyboard-closed")
            if self.input_after(persisted_root, "Default").attrib.get("text") != edited:
                raise AuditFailure("multiline value was lost when the keyboard closed")

            self.assert_unchanged_after_tap("Disabled", width, height)
            self.assert_unchanged_after_tap("Read Only", width, height)

            self.launch()
            error_root, error_input = self.scroll_to_input("Error", width, height)
            if not self.exact(error_root, "This value needs attention"):
                raise AuditFailure("error textarea does not expose its validation message")
            if node_bounds(error_input).height < 160:
                raise AuditFailure("error state collapsed the textarea height")
            self.screenshot("03-error")

            self.launch()
            clear_root, _ = self.scroll_to_input("Clearable", width, height)
            clear_actions = self.exact(clear_root, "Clear Textarea")
            if len(clear_actions) != 1:
                raise AuditFailure("clearable textarea does not expose one clear action")
            self.tap(node_bounds(clear_actions[0]))
            cleared_root = self.dump("04-cleared")
            self.screenshot("04-cleared")
            cleared = self.input_after(cleared_root, "Clearable")
            if (
                cleared.attrib.get("text", "") not in {"", "Write a message"}
                or cleared.attrib.get("hint") != "Write a message"
                or self.exact(cleared_root, "Clear Textarea")
            ):
                raise AuditFailure("clear action did not restore the empty textarea state")

            self.launch()
            affix_root, affix_input = self.scroll_to_input("Prefix and suffix", width, height)
            prefixes = self.exact(affix_root, "@")
            suffixes = self.exact(affix_root, "note")
            if len(prefixes) != 1 or len(suffixes) != 1:
                raise AuditFailure("textarea prefix or suffix is missing")
            affix_area = node_bounds(affix_input)
            if (
                node_bounds(prefixes[0]).left < affix_area.left - 1
                or node_bounds(suffixes[0]).right > affix_area.right + 1
            ):
                raise AuditFailure("textarea affix escaped the native input bounds")
            self.screenshot("05-affixes")
            self.assert_affix_first_line_alignment(
                affix_input,
                prefixes[0],
                suffixes[0],
                "05-affixes",
            )

            self.launch()
            counter_root, counter_input = self.scroll_to_input("Counter", width, height)
            if not self.exact(counter_root, "10/20"):
                raise AuditFailure("textarea counter did not count the initial newline")
            self.tap(node_bounds(counter_input))
            self.expect_ime(True, "counter textarea")
            self.replace_text("1234567890")
            self.append_line("abcdefghijklmnop")
            counter_after = self.dump("06-counter-max")
            self.screenshot("06-counter-max")
            limited = self.input_after(counter_after, "Counter").attrib.get("text", "")
            if limited != "1234567890\nabcdefghi":
                raise AuditFailure(f"textarea maxLength did not include newline: {limited!r}")
            if not self.exact(counter_after, "20/20"):
                raise AuditFailure("textarea counter did not update to 20/20")
            self.back()
            self.expect_ime(False, "counter Back")

            self.launch()
            _, grow_input = self.scroll_to_input("Auto grow", width, height)
            initial_grow_height = node_bounds(grow_input).height
            self.tap(node_bounds(grow_input))
            self.expect_ime(True, "auto-grow textarea")
            self.replace_text("One")
            for value in ("Two", "Three", "Four", "Five"):
                self.append_line(value)
            grown_root = self.dump("07-auto-grow")
            self.screenshot("07-auto-grow")
            grown = self.input_after(grown_root, "Auto grow")
            grown_text = grown.attrib.get("text", "")
            if grown_text != "One\nTwo\nThree\nFour\nFive":
                raise AuditFailure(f"auto-grow textarea lost multiline content: {grown_text!r}")
            if node_bounds(grown).height < initial_grow_height + 60:
                raise AuditFailure(
                    "autoGrow did not increase the native textarea for five lines "
                    f"({initial_grow_height}px -> {node_bounds(grown).height}px)"
                )
            self.back()
            self.expect_ime(False, "auto-grow Back")

            self.launch()
            _, four_rows = self.scroll_to_input("Four rows", width, height)
            if node_bounds(four_rows).height < default_area.height + 35:
                raise AuditFailure("rows=4 did not create a taller textarea")
            self.screenshot("08-four-rows")

            self.launch()
            _, fixed_input = self.scroll_to_input("Fixed rows", width, height)
            fixed_height = node_bounds(fixed_input).height
            self.tap(node_bounds(fixed_input))
            self.expect_ime(True, "fixed textarea")
            self.replace_text("One")
            for value in ("Two", "Three", "Four", "Five", "Six"):
                self.append_line(value)
            fixed_root = self.dump("09-fixed-scroll")
            self.screenshot("09-fixed-scroll")
            fixed_after = self.input_after(fixed_root, "Fixed rows")
            if abs(node_bounds(fixed_after).height - fixed_height) > 3:
                raise AuditFailure("fixed-row textarea resized while entering overflow content")
            if fixed_after.attrib.get("text") != "One\nTwo\nThree\nFour\nFive\nSix":
                raise AuditFailure("fixed-row textarea lost scrollable overflow content")

            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.8)
            landscape_width, landscape_height = self.screenshot("10-landscape-keyboard")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            self.expect_ime(True, "focused textarea after rotation")
            landscape_root = self.dump("10-landscape-keyboard")
            focused = [
                node for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.EditText"
                and node.attrib.get("focused") == "true"
            ]
            focused_area = node_bounds(focused[0]) if len(focused) == 1 else None
            if (
                focused_area is None
                or focused_area.left < 0
                or focused_area.right > landscape_width
                or focused_area.top < 0
                or focused_area.bottom > landscape_height
                or focused_area.height < 96
            ):
                raise AuditFailure("focused textarea disappeared or overflowed after rotation")
            containing_scrolls = [
                node_bounds(node) for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.ScrollView"
                and node_bounds(node).left <= focused_area.left
                and node_bounds(node).right >= focused_area.right
            ]
            if not any(
                area.top <= focused_area.top and area.bottom >= focused_area.bottom
                for area in containing_scrolls
            ):
                raise AuditFailure("focused textarea is clipped by its landscape scroll viewport")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained with expanded navigation")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            self.back()

            logs = self.shell("logcat", "-d", "-t", "1800", timeout=30.0)
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "failed integrity verification", "Unknown native icon",
                "ANR in dev.pam.mobileui.catalog.debug",
                "Input dispatching timed out",
            )
            errors = [
                line for line in logs.splitlines()
                if any(marker in line for marker in markers)
                or (
                    "requestLayout() improperly called" in line
                    and ("dev.pam." in line or "Pam" in line)
                )
            ]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 2,
                "component": "p-textarea",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "labelTouchFocus": True,
                    "nativeMultilineAndEnter": True,
                    "focusIndicatorGeometry": True,
                    "instanceIsolation": True,
                    "systemBackPersistence": True,
                    "disabledAndReadOnly": True,
                    "errorMessage": True,
                    "clearable": True,
                    "prefixAndSuffix": True,
                    "affixFirstLineAlignment": True,
                    "counterAndMaxLengthWithNewline": True,
                    "autoGrow": True,
                    "rows": True,
                    "fixedOverflow": True,
                    "rotationWithIme": True,
                    "adaptiveNavigation": True,
                    "statusBarContrast": True,
                    "runtimeLog": True,
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
    parser = argparse.ArgumentParser(description="Audit p-textarea on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog.debug")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-textarea-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = TextareaAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-textarea; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-textarea: {exception}")
        raise SystemExit(1)
