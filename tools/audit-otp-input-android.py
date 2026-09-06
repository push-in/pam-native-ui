#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

from PIL import Image, ImageChops


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_otp_input_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class OtpInputAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-otp-input",
            component_label="OTP Input",
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
        path = self.output / f"{screenshot_name}.png"
        with Image.open(path).convert("RGB") as image:
            crop = image.crop((0, 0, image.width, min(120, image.height)))
            pixels = (
                crop.get_flattened_data()
                if hasattr(crop, "get_flattened_data")
                else crop.getdata()
            )
            dark_pixels = sum(
                1 for red, green, blue in pixels if max(red, green, blue) < 100
            )
        if dark_pixels < 500:
            raise AuditFailure(
                "light status bar has no readable dark system icons "
                f"({dark_pixels} contrast pixels)"
            )

    def changed_surface_ratio(self, reference_name: str, screenshot_name: str) -> float:
        baseline_path = self.output / f"{reference_name}.png"
        current_path = self.output / f"{screenshot_name}.png"
        with (
            Image.open(baseline_path).convert("RGB") as baseline,
            Image.open(current_path).convert("RGB") as current,
        ):
            if baseline.size != current.size:
                raise AuditFailure("keyboard visual probe changed the display dimensions")
            difference = ImageChops.difference(baseline, current)
            pixels = (
                difference.get_flattened_data()
                if hasattr(difference, "get_flattened_data")
                else difference.getdata()
            )
            changed = sum(1 for pixel in pixels if max(pixel) > 20)
            return changed / (baseline.width * baseline.height)

    def require_visible_software_keyboard(self) -> None:
        self.screenshot("01-keyboard-probe")
        ratio = self.changed_surface_ratio("00-baseline", "01-keyboard-probe")
        if ratio < 0.045:
            # Gboard can expose only its hardware-keyboard toolbar while the
            # InputMethod window reports visible. Alt+K is its documented
            # emulator shortcut for the actual on-screen keyboard.
            self.shell("input", "keycombination", "57", "39", timeout=30.0)
            time.sleep(1.0)
            self.screenshot("01-keyboard-expanded")
            ratio = self.changed_surface_ratio("00-baseline", "01-keyboard-expanded")
        if ratio < 0.045:
            raise AuditFailure(
                "Android reported a visible IME but no full software keyboard "
                f"surface was rendered (changed-area ratio {ratio:.3f})"
            )

    def require_visible_landscape_keyboard(self) -> tuple[int, int]:
        self.screenshot("12-landscape-keyboard-probe")
        ratio = self.changed_surface_ratio(
            "12-landscape-baseline",
            "12-landscape-keyboard-probe",
        )
        if ratio < 0.05:
            self.shell("input", "keycombination", "57", "39", timeout=30.0)
            time.sleep(1.0)
        width, height = self.screenshot("12-landscape-keyboard")
        ratio = self.changed_surface_ratio(
            "12-landscape-baseline",
            "12-landscape-keyboard",
        )
        if ratio < 0.05:
            raise AuditFailure(
                "OTP rotation retained only the hardware-keyboard toolbar, not "
                f"the full software keyboard (changed-area ratio {ratio:.3f})"
            )
        return width, height

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
            raise AuditFailure(f"OTP editor after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_input(self, label: str, width: int, height: int):
        slug = label.lower().replace(" ", "-")
        for attempt in range(22):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                field = self.input_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(field)
            if area.top >= 130 and area.bottom <= height - 60:
                return root, field
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} OTP input into the viewport")

    def clear_text_with_backspace(self) -> None:
        # Android's injected Ctrl+A is not honored by every numeric IME (the
        # Samsung keyboard is one example). Backspace exercises the same real
        # InputConnection path as a user and remains deterministic everywhere.
        for _ in range(12):
            self.shell("input", "keyevent", "DEL")
            time.sleep(0.12)
        time.sleep(0.5)

    def replace_text(self, value: str) -> None:
        self.clear_text_with_backspace()
        self.shell("input", "text", value)
        time.sleep(1.0)

    def assert_blocked_state(self, label: str, width: int, height: int) -> None:
        self.launch()
        root, field = self.scroll_to_input(label, width, height)
        before = field.attrib.get("text", "")
        self.tap(node_bounds(field))
        self.expect_ime(False, label)
        self.shell("input", "text", "999999")
        time.sleep(0.5)
        after_root = self.dump(f"state-{label.lower().replace(' ', '-')}")
        after = self.input_after(after_root, label)
        if after.attrib.get("text", "") != before:
            raise AuditFailure(f"{label} OTP input mutated from a blocked interaction")
        if label == "Disabled" and after.attrib.get("enabled") == "true":
            raise AuditFailure("disabled OTP input remains accessibility-enabled")
        if label == "Read Only" and after.attrib.get("enabled") != "true":
            raise AuditFailure("read-only OTP input is incorrectly disabled")
        self.screenshot(f"state-{label.lower().replace(' ', '-')}")

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_light_status_bar_contrast("00-baseline")
            self.assert_window_count(1, "baseline")
            expected_labels = (
                "Default", "Isolated instance", "Disabled", "Read Only",
                "Empty", "Four digits", "Masked", "Divider", "Merged",
                "Error", "Loading",
            )
            for label in expected_labels:
                if not self.exact(root, label):
                    # Lower scenarios may be outside the accessibility viewport,
                    # but they still need to become reachable by native scrolling.
                    # Labels are ordered exactly as rendered, so continue from
                    # the current native scroll offset instead of cold-starting
                    # the same route once per off-screen variation.
                    root, _ = self.scroll_to_input(label, width, height)

            self.launch()
            root = self.dump("00-baseline-reset")
            default = self.input_after(root, "Default")
            isolated = self.input_after(root, "Isolated instance")
            density = self.density()
            minimum_touch = round(48 * density) - 1
            default_area = node_bounds(default)
            if (
                default_area.height < minimum_touch
                or default_area.width < round(280 * density)
            ):
                raise AuditFailure("OTP native editor does not expose a complete 48dp target")
            if default.attrib.get("content-desc") != "OTP Input":
                raise AuditFailure("OTP editor lost its accessible label")
            if default.attrib.get("text") != "482915" or isolated.attrib.get("text") != "123456":
                raise AuditFailure("default or isolated OTP value did not render accessibly")
            raw_digit_nodes = [
                node for node in self.nodes(root)
                if node.attrib.get("class") == "android.widget.TextView"
                and node.attrib.get("text") in set("0123456789")
                and node_bounds(node).top >= node_bounds(self.exact(root, "Default")[0]).bottom
            ]
            if raw_digit_nodes:
                raise AuditFailure("visual OTP slots leaked as duplicate accessibility nodes")

            self.tap(default_area)
            self.expect_ime(True, "default OTP")
            self.require_visible_software_keyboard()
            self.clear_text_with_backspace()
            cleared_root = self.dump("01-cleared-with-backspace")
            self.screenshot("01-cleared-with-backspace")
            if self.input_after(cleared_root, "Default").attrib.get("text") != "":
                raise AuditFailure("repeated real Backspace did not clear the OTP")
            self.shell("input", "text", "738204")
            time.sleep(1.0)
            typed_root = self.dump("01-typed-keyboard")
            self.screenshot("01-typed-keyboard")
            updated = self.input_after(typed_root, "Default")
            if updated.attrib.get("text") != "738204":
                raise AuditFailure("real numeric keyboard input was not retained")
            if self.input_after(typed_root, "Isolated instance").attrib.get("text") != "123456":
                raise AuditFailure("typing into the default OTP corrupted its sibling")

            self.shell("input", "keyevent", "DEL")
            time.sleep(0.8)
            deleted_root = self.dump("02-backspace")
            self.screenshot("02-backspace")
            if self.input_after(deleted_root, "Default").attrib.get("text") != "73820":
                raise AuditFailure("OTP Backspace did not delete exactly one character")
            self.shell("input", "text", "499")
            time.sleep(0.8)
            capped_root = self.dump("03-max-length")
            self.screenshot("03-max-length")
            if self.input_after(capped_root, "Default").attrib.get("text") != "738204":
                raise AuditFailure("OTP maxLength accepted characters past six slots")
            self.back()
            self.expect_ime(False, "OTP system Back")
            closed_root = self.dump("04-keyboard-closed")
            if self.input_after(closed_root, "Default").attrib.get("text") != "738204":
                raise AuditFailure("OTP value was lost when the keyboard closed")

            self.assert_blocked_state("Disabled", width, height)
            self.assert_blocked_state("Read Only", width, height)

            self.launch()
            empty_root, empty_input = self.scroll_to_input("Empty", width, height)
            self.tap(node_bounds(empty_input))
            self.expect_ime(True, "empty OTP")
            self.replace_text("246810")
            filled_empty = self.dump("05-empty-filled")
            self.screenshot("05-empty-filled")
            if self.input_after(filled_empty, "Empty").attrib.get("text") != "246810":
                raise AuditFailure("empty OTP did not accept a pasted six-digit code")
            self.back()

            self.launch()
            four_root, four_input = self.scroll_to_input("Four digits", width, height)
            self.tap(node_bounds(four_input))
            self.expect_ime(True, "four-digit OTP")
            self.replace_text("13579")
            four_typed = self.dump("06-four-digit-limit")
            self.screenshot("06-four-digit-limit")
            if self.input_after(four_typed, "Four digits").attrib.get("text") != "1357":
                raise AuditFailure("four-digit OTP ignored its configured length")
            self.back()

            self.launch()
            masked_root, masked_input = self.scroll_to_input("Masked", width, height)
            if masked_input.attrib.get("password") != "true":
                raise AuditFailure("masked OTP is not exposed as a secure native editor")
            if "482915" in masked_input.attrib.get("text", ""):
                raise AuditFailure("masked OTP leaked its raw value through accessibility")
            self.tap(node_bounds(masked_input))
            self.expect_ime(True, "masked OTP")
            self.replace_text("112233")
            masked_typed = self.dump("07-masked-typed")
            self.screenshot("07-masked-typed")
            masked_after = self.input_after(masked_typed, "Masked")
            if masked_after.attrib.get("password") != "true":
                raise AuditFailure("masked OTP lost its secure state while editing")
            if "112233" in masked_after.attrib.get("text", ""):
                raise AuditFailure("masked OTP exposed the newly typed secret")
            self.back()

            self.launch()
            divider_root, divider_input = self.scroll_to_input("Divider", width, height)
            divider_area = node_bounds(divider_input)
            if divider_area.width < round(340 * density) or divider_area.right > width:
                raise AuditFailure("divider OTP is clipped or failed to reserve its wider layout")
            self.screenshot("08-divider")

            self.launch()
            merged_root, merged_input = self.scroll_to_input("Merged", width, height)
            if node_bounds(merged_input).width < round(280 * density):
                raise AuditFailure("merged OTP collapsed its native target")
            self.screenshot("09-merged")

            self.launch()
            error_root, error_input = self.scroll_to_input("Error", width, height)
            if not self.exact(error_root, "Enter the complete verification code"):
                raise AuditFailure("OTP error state does not expose its validation message")
            self.screenshot("10-error")

            self.launch()
            loading_root, loading_input = self.scroll_to_input("Loading", width, height)
            if loading_input.attrib.get("enabled") != "true":
                raise AuditFailure("loading OTP unexpectedly disabled its native editor")
            self.screenshot("11-loading")

            # Establish the exact landscape layout without an IME before
            # proving a focused rotation with the real keyboard surface.
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "OTP landscape transition")
            baseline_width, baseline_height = self.screenshot("12-landscape-baseline")
            if baseline_width <= baseline_height:
                raise AuditFailure("device did not enter landscape for the baseline")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "OTP portrait focus setup")
            _, loading_input = self.scroll_to_input("Loading", width, height)
            self.tap(node_bounds(loading_input))
            self.expect_ime(True, "loading OTP before rotation")
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "focused OTP landscape transition")
            landscape_width, landscape_height = self.require_visible_landscape_keyboard()
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            self.expect_ime(True, "focused OTP after rotation")
            landscape_root = self.dump("12-landscape-keyboard")
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
                or focused_area.height < minimum_touch
            ):
                raise AuditFailure("focused OTP disappeared or overflowed after rotation")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained with expanded navigation")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "OTP portrait restoration")
            self.back()

            logs = self.shell("logcat", "-d", "-t", "2400", timeout=30.0)
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "failed integrity verification", "Unknown native icon",
                "ANR in dev.pam.mobileui.catalog.debug", "Input dispatching timed out",
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
                "schemaVersion": 1,
                "component": "p-otp-input",
                "device": self.serial,
                "package": self.package,
                "result": "passed",
                "checks": {
                    "accessible48dpNativeEditor": True,
                    "singleAccessibilityNodePerInstance": True,
                    "realNumericKeyboardInput": True,
                    "backspaceAndPaste": True,
                    "maxLength": True,
                    "instanceIsolation": True,
                    "systemBackPersistence": True,
                    "disabledAndReadOnly": True,
                    "fourDigitLength": True,
                    "maskedSecret": True,
                    "dividerAndMerged": True,
                    "errorMessage": True,
                    "loadingIndicator": True,
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
    parser = argparse.ArgumentParser(description="Audit p-otp-input on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog.debug")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-otp-input-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = OtpInputAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-otp-input; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-otp-input: {exception}")
        raise SystemExit(1)
