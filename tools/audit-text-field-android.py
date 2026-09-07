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
SPEC = importlib.util.spec_from_file_location("pam_text_field_audit", MODULE_PATH)
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


class TextFieldAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-text-field",
            component_label="Text Field",
        )

    def expect_ime(self, expected: bool, context: str) -> None:
        if not expected:
            # Absence must be stable: a programmatic focus request can schedule
            # showSoftInput after the tap command itself has returned.
            time.sleep(1.2)
            if self.ime_shown():
                raise AuditFailure(f"{context} opened the software keyboard")
            return
        deadline = time.monotonic() + 5.0
        while time.monotonic() < deadline:
            if self.ime_shown():
                return
            time.sleep(0.15)
        raise AuditFailure(f"{context} did not open the software keyboard")

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
            raise AuditFailure(f"input after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_input(self, label: str, width: int, height: int):
        slug = label.lower().replace(" ", "-")
        for attempt in range(9):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                field = self.input_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(field)
            # Keep the editor above the 100px gesture/navigation exclusion.
            # At the natural end of this ScrollView the final field bottoms at
            # y=2274 on a 2400px device, which is fully visible and tappable;
            # the former 180px margin incorrectly declared that valid terminal
            # position unreachable.
            if area.top >= 130 and area.bottom <= height - 100:
                return root, field
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} input into the viewport")

    def append_system_text(
        self,
        label: str,
        before: str,
        suffix: str,
    ):
        expected = before + suffix
        for attempt in range(2):
            # Android 16 may expose the IME inset before Gboard has completed
            # switching from its handwriting panel to the served editor. A
            # command dropped during that system transition leaves the value
            # completely unchanged; retry only that no-op case. Partial or
            # malformed input is always a hard failure.
            self.shell("input", "text", suffix)
            time.sleep(0.8)
            root = self.dump(f"system-text-attempt-{attempt + 1}")
            actual = self.input_after(root, label).attrib.get("text", "")
            if actual == expected:
                return root
            if actual != before:
                raise AuditFailure(
                    "real keyboard input produced a partial or malformed value: "
                    f"{actual!r}"
                )
            if attempt == 0:
                time.sleep(0.8)
        raise AuditFailure(f"real keyboard input was not retained: {before!r}")

    def assert_focus_indicator_geometry(self, root, screenshot_name: str) -> None:
        field = self.input_after(root, "Default")
        field_area = node_bounds(field)
        helpers = [
            node for node in self.exact(root, "Rendered by the platform")
            if node_bounds(node).top >= field_area.bottom
        ]
        if not helpers:
            raise AuditFailure("default helper text is missing below the editor")
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
                "focused indicator did not replace the field-surface underline"
            )
        minimum_rows = max(2, int(self.density() * 1.5))
        if surface_rows < minimum_rows:
            raise AuditFailure(
                "focused indicator is thinner than the Material 2dp state line: "
                f"{surface_rows} solid pixels"
            )
        if below_helper >= required:
            raise AuditFailure(
                "focused indicator was drawn below the helper instead of on the field"
            )

    def assert_unchanged_after_tap(self, label: str, width: int, height: int) -> None:
        # Start each non-editable state from a fresh Activity. An editable
        # field keeps Android's served-input connection after Back hides the
        # IME, so reusing that Activity could send the proof keystrokes to a
        # sibling and produce a false result for Disabled or Read Only.
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
            raise AuditFailure("disabled text field accepted input focus")
        if label == "Read Only" and focused_inputs:
            if focused_inputs != [tapped_field]:
                raise AuditFailure("read-only tap focused a different text field")
            # A focusable read-only editor may allow selection/copy, but must
            # reject actual key input and must never summon the soft keyboard.
            self.shell("input", "text", "SHOULD_NOT_APPEAR")
            time.sleep(0.5)
        after_root = self.dump(f"state-{label.lower().replace(' ', '-')}-verified")
        after = self.input_after(after_root, label).attrib.get("text", "")
        if after != before:
            raise AuditFailure(f"{label} input mutated from an input command")
        after_field = self.input_after(after_root, label)
        if label == "Disabled" and after_field.attrib.get("enabled") == "true":
            raise AuditFailure("disabled text field is accessibility-enabled")
        if label == "Read Only" and after_field.attrib.get("enabled") != "true":
            raise AuditFailure("read-only text field is incorrectly disabled")
        self.screenshot(f"state-{label.lower().replace(' ', '-')}")

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_light_status_bar_contrast("00-baseline")
            self.assert_window_count(1, "baseline")
            for label in ("Default", "Isolated instance", "Disabled", "Read Only"):
                if not self.exact(root, label):
                    raise AuditFailure(f"audit surface is missing {label!r}")

            default = self.input_after(root, "Default")
            isolated = self.input_after(root, "Isolated instance")
            if default.attrib.get("text") != "Native field":
                raise AuditFailure("default value did not render")
            if isolated.attrib.get("text") != "Independent value":
                raise AuditFailure("isolated value did not render")
            # UiAutomator on Android 12/API 31 does not serialize `hint` for
            # a non-empty EditText. Newer releases do, so assert it whenever
            # the platform exposes the attribute and prove the legacy path
            # after clearing the field below.
            if "hint" in default.attrib and default.attrib.get("hint") != "Type here":
                raise AuditFailure("placeholder is not exposed as the native hint")

            # The label area is part of the Material text-field touch surface,
            # so tapping it must focus the native editor, not only tapping the
            # glyph-sized EditText accessibility bounds.
            internal_labels = [
                node for node in self.exact(root, "Text Field")
                if node_bounds(node).top >= node_bounds(self.exact(root, "Default")[0]).bottom
                and node_bounds(node).bottom <= node_bounds(default).top
            ]
            if not internal_labels:
                raise AuditFailure("floating label for the default field is missing")
            self.tap(node_bounds(internal_labels[0]))
            self.expect_ime(True, "tapping the field label")
            self.append_system_text("Default", "Native field", "_Edited")
            edited_root = self.dump("01-edited-keyboard")
            self.screenshot("01-edited-keyboard")
            edited = self.input_after(edited_root, "Default").attrib.get("text", "")
            if edited != "Native field_Edited":
                raise AuditFailure(f"real keyboard input was not retained: {edited!r}")
            self.assert_focus_indicator_geometry(edited_root, "01-edited-keyboard")
            if self.input_after(edited_root, "Isolated instance").attrib.get("text") != "Independent value":
                raise AuditFailure("editing the default field corrupted the second instance")
            self.back()
            self.expect_ime(False, "system Back")
            persisted_root = self.dump("02-keyboard-closed")
            self.screenshot("02-keyboard-closed")
            if self.input_after(persisted_root, "Default").attrib.get("text") != edited:
                raise AuditFailure("text value was lost when the keyboard closed")

            self.assert_unchanged_after_tap("Disabled", width, height)
            self.assert_unchanged_after_tap("Read Only", width, height)

            error_root, _ = self.scroll_to_input("Error", width, height)
            if not self.exact(error_root, "This value needs attention"):
                raise AuditFailure("error field does not expose its validation message")
            self.screenshot("03-error")

            clear_root, clear_input = self.scroll_to_input("Clearable", width, height)
            # The public renderer intentionally derives a contextual label
            # from the visible field label ("Clear Text Field"). The Android
            # host's generic "Clear input" is only a fallback when callers do
            # not provide an accessible label.
            clear_actions = self.exact(clear_root, "Clear Text Field")
            if len(clear_actions) != 1:
                raise AuditFailure("clearable field does not expose one clear action")
            self.tap(node_bounds(clear_actions[0]))
            cleared_root = self.dump("04-cleared")
            self.screenshot("04-cleared")
            cleared = self.input_after(cleared_root, "Clearable")
            # UiAutomator mirrors an empty editor's hint into `text` on API 36.
            # The disappearing clear action plus the native hint distinguish
            # the genuinely empty state from a literal placeholder value.
            cleared_text = cleared.attrib.get("text", "")
            exposed_hint = cleared.attrib.get("hint")
            placeholder_visible = (
                exposed_hint == "Type here"
                if exposed_hint is not None
                else cleared_text == "Type here"
            )
            if (
                cleared_text not in {"", "Type here"}
                or not placeholder_visible
                or self.exact(cleared_root, "Clear Text Field")
            ):
                raise AuditFailure("clear action did not restore the empty placeholder state")

            affix_root, affix_input = self.scroll_to_input("Prefix and suffix", width, height)
            if affix_input.attrib.get("text") != "120":
                raise AuditFailure("affixed input lost its model value")
            prefixes = self.exact(affix_root, "$")
            suffixes = self.exact(affix_root, "USD")
            if len(prefixes) != 1 or len(suffixes) != 1:
                raise AuditFailure("prefix or suffix is missing")
            field_area = node_bounds(affix_input)
            if node_bounds(prefixes[0]).left < field_area.left - 1:
                raise AuditFailure("prefix escaped the input bounds")
            if node_bounds(suffixes[0]).right > field_area.right + 1:
                raise AuditFailure("suffix escaped the input bounds")
            self.screenshot("05-affixes")

            counter_root, counter_input = self.scroll_to_input("Counter", width, height)
            if not self.exact(counter_root, "6/12"):
                raise AuditFailure("counter did not expose the initial 6/12 value")
            self.tap(node_bounds(counter_input))
            self.expect_ime(True, "counter input")
            self.shell("input", "keycombination", "113", "29")
            self.shell("input", "text", "123456789012345")
            time.sleep(1.0)
            counter_after = self.dump("06-counter-max")
            self.screenshot("06-counter-max")
            limited = self.input_after(counter_after, "Counter").attrib.get("text", "")
            if len(limited) != 12:
                raise AuditFailure(
                    "maxLength did not clamp the real keyboard input to 12 "
                    f"characters: {limited!r}"
                )
            if not self.exact(counter_after, "12/12"):
                raise AuditFailure("counter did not update to 12/12")

            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            landscape_width, landscape_height = self.screenshot("07-landscape-keyboard")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            self.expect_ime(True, "focused field after rotation")
            landscape_root = self.dump("07-landscape-keyboard")
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
                or focused_area.height < 48
            ):
                raise AuditFailure("focused input disappeared or overflowed after rotation")
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
                raise AuditFailure("focused input is clipped by its landscape scroll viewport")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained with expanded navigation")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            self.back()

            logs = self.shell("logcat", "-d", "-t", "1500", timeout=30.0)
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "failed integrity verification", "Unknown native icon",
                f"ANR in {self.package}",
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
                "component": "p-text-field",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "labelTouchFocus": True,
                    "realKeyboardInput": True,
                    "focusIndicatorGeometry": True,
                    "instanceIsolation": True,
                    "systemBackPersistence": True,
                    "disabledAndReadOnly": True,
                    "errorMessage": True,
                    "clearable": True,
                    "prefixAndSuffix": True,
                    "counterAndMaxLength": True,
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
    parser = argparse.ArgumentParser(description="Audit p-text-field on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-text-field-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = TextFieldAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-text-field; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-text-field: {exception}")
        raise SystemExit(1)
