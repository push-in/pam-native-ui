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
SPEC = importlib.util.spec_from_file_location("pam_number_input_audit", MODULE_PATH)
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


class NumberInputAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-number-input",
            component_label="Number Input",
        )

    def launch(self, scenario: str = "interactive") -> None:
        for package in ("dev.pam.scanner.test", "br.com.linkinpay.app.debug"):
            self.adb("shell", "am", "force-stop", package, check=False)
        super().launch(scenario)

    def pin_task_immediately_after_launch(self) -> bool:
        # A separately running development harness on the physical Samsung
        # can explicitly relaunch itself even after force-stop. Pin before the
        # first settle/dump so keyboard/touch assertions cannot be redirected.
        return True

    def screenshot(self, name: str) -> tuple[int, int]:
        # Screencap works while the task is pinned. Keeping the lock avoids the
        # short unpinned window in the shared helper where another dev harness
        # can steal the foreground between a keyboard edit and its evidence.
        if name == "00-baseline" and self.task_locked:
            # Lock-task mode intentionally hides Android system bars. Capture
            # the baseline in a tightly bounded unpinned window so its status
            # bar contrast remains a real assertion, then pin immediately.
            self.stop_task_lock()
            for package in ("dev.pam.scanner.test", "br.com.linkinpay.app.debug"):
                self.adb("shell", "am", "force-stop", package, check=False)
            time.sleep(0.15)
            try:
                return super().screenshot(name)
            finally:
                self.start_task_lock()
        self.assert_foreground(f"screenshot {name}")
        path = self.output / f"{name}.png"
        payload = self.adb("exec-out", "screencap", "-p", binary=True)
        assert isinstance(payload, bytes)
        path.write_bytes(payload)
        with Image.open(path) as image:
            size = image.size
        self.evidence.append({"name": name, "path": str(path), "size": list(size)})
        return size

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
            raise AuditFailure(f"number input after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def actions_for_input(self, root, field):
        area = node_bounds(field)
        actions = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") in {"Decrease value", "Increase value"}
            and node_bounds(node).bottom > area.top
            and node_bounds(node).top < area.bottom
        ]
        return sorted(actions, key=lambda node: (node_bounds(node).left, node_bounds(node).top))

    def action(self, root, field, description: str):
        matches = [
            node for node in self.actions_for_input(root, field)
            if node.attrib.get("content-desc") == description
        ]
        if len(matches) != 1:
            raise AuditFailure(
                f"{description!r} has {len(matches)} matching controls for the number input"
            )
        return matches[0]

    def scroll_to_input(self, label: str, width: int, height: int):
        slug = label.lower().replace(" ", "-")
        expected_height = round(
            (96 if label == "Stacked controls" else 48) * self.density()
        ) - 1
        for attempt in range(18):
            root = self.dump(f"scroll-{slug}-{attempt}")
            try:
                field = self.input_after(root, label)
            except AuditFailure:
                self.swipe_up(width, height)
                continue
            area = node_bounds(field)
            # The app content ends 63 px above the physical display edge on
            # this density because of the gesture navigation bar. A field
            # whose bottom sits on that content boundary is fully visible and
            # must not be rejected as if it were covered.
            if (
                area.top >= 130
                and area.bottom <= height - 60
                and area.height >= expected_height
            ):
                return root, field
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} number input into the viewport")

    def replace_text(self, value: str) -> None:
        # Samsung's numeric IME ignores Ctrl+A. Delete from the active cursor
        # so the audit proves replacement on real manufacturer keyboards
        # instead of accidentally appending to the existing value.
        self.shell("input", "keyevent", "KEYCODE_MOVE_END")
        # Four isolated deletes cover every fixture value without triggering
        # Samsung/Google keyboard shortcuts caused by a batched key sequence.
        for _ in range(4):
            self.shell("input", "keyevent", "KEYCODE_DEL")
        self.shell("input", "text", value)
        time.sleep(1.0)

    def assert_blocked_state(self, label: str, width: int, height: int) -> None:
        self.launch()
        root, field = self.scroll_to_input(label, width, height)
        before = field.attrib.get("text", "")
        actions = self.actions_for_input(root, field)
        if len(actions) != 2 or any(a.attrib.get("enabled") == "true" for a in actions):
            raise AuditFailure(f"{label} number input left a step control enabled")
        self.tap(node_bounds(field))
        self.expect_ime(False, label)
        self.shell("input", "text", "19")
        for action in actions:
            self.tap(node_bounds(action))
        after_root = self.dump(f"state-{label.lower().replace(' ', '-')}")
        after = self.input_after(after_root, label)
        if after.attrib.get("text", "") != before:
            raise AuditFailure(f"{label} number input mutated from blocked interactions")
        if label == "Disabled" and after.attrib.get("enabled") == "true":
            raise AuditFailure("disabled number input remains accessibility-enabled")
        if label == "Read Only" and after.attrib.get("enabled") != "true":
            raise AuditFailure("read-only number input is incorrectly disabled")
        self.screenshot(f"state-{label.lower().replace(' ', '-')}")

    def tap_and_expect_value(
        self,
        root,
        field,
        action_label: str,
        variation: str,
        expected: str,
        dump_name: str,
    ):
        self.tap(node_bounds(self.action(root, field, action_label)))
        after = self.dump(dump_name)
        updated = self.input_after(after, variation)
        actual = updated.attrib.get("text", "")
        if actual != expected:
            raise AuditFailure(
                f"{action_label} on {variation} produced {actual!r}, expected {expected!r}"
            )
        return after, updated

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
            if default.attrib.get("text") != "8" or isolated.attrib.get("text") != "3":
                raise AuditFailure("default or isolated numeric value did not render")
            default_actions = self.actions_for_input(root, default)
            if len(default_actions) != 2:
                raise AuditFailure("default number input does not expose two controls")
            minimum_touch = round(48 * self.density()) - 1
            if any(
                node_bounds(action).width < minimum_touch
                or node_bounds(action).height < minimum_touch
                for action in default_actions
            ):
                raise AuditFailure("number step targets are smaller than 48dp")

            after_decrease, updated_default = self.tap_and_expect_value(
                root,
                default,
                "Decrease value",
                "Default",
                "7",
                "01-decreased",
            )
            if self.input_after(after_decrease, "Isolated instance").attrib.get("text") != "3":
                raise AuditFailure("stepping the default number corrupted its sibling")
            self.screenshot("01-decreased")
            after_increase, updated_default = self.tap_and_expect_value(
                after_decrease,
                updated_default,
                "Increase value",
                "Default",
                "8",
                "02-increased",
            )
            self.screenshot("02-increased")

            self.tap(node_bounds(updated_default))
            self.expect_ime(True, "direct numeric input")
            self.replace_text("13")
            typed_root = self.dump("03-typed-keyboard")
            self.screenshot("03-typed-keyboard")
            if self.input_after(typed_root, "Default").attrib.get("text") != "13":
                raise AuditFailure("direct numeric keyboard input was not retained")
            self.back()
            self.expect_ime(False, "numeric Back")
            persisted = self.dump("04-keyboard-closed")
            if self.input_after(persisted, "Default").attrib.get("text") != "13":
                raise AuditFailure("numeric value was lost when the keyboard closed")

            self.assert_blocked_state("Disabled", width, height)
            self.assert_blocked_state("Read Only", width, height)

            self.launch()
            error_root, error_input = self.scroll_to_input("Error", width, height)
            if not self.exact(error_root, "Enter a valid quantity"):
                raise AuditFailure("error number input does not expose its validation message")
            if len(self.actions_for_input(error_root, error_input)) != 2:
                raise AuditFailure("error state lost number controls")
            self.screenshot("05-error")

            self.launch()
            minimum_root, minimum_input = self.scroll_to_input("Minimum", width, height)
            minimum_decrease = self.action(minimum_root, minimum_input, "Decrease value")
            if minimum_decrease.attrib.get("enabled") == "true":
                raise AuditFailure("minimum decrement control remains enabled")
            minimum_root, minimum_input = self.tap_and_expect_value(
                minimum_root,
                minimum_input,
                "Increase value",
                "Minimum",
                "1",
                "06-minimum-increased",
            )
            self.screenshot("06-minimum-increased")

            self.launch()
            maximum_root, maximum_input = self.scroll_to_input("Maximum", width, height)
            maximum_increase = self.action(maximum_root, maximum_input, "Increase value")
            if maximum_increase.attrib.get("enabled") == "true":
                raise AuditFailure("maximum increment control remains enabled")
            maximum_root, maximum_input = self.tap_and_expect_value(
                maximum_root,
                maximum_input,
                "Decrease value",
                "Maximum",
                "19",
                "07-maximum-decreased",
            )
            self.screenshot("07-maximum-decreased")

            self.launch()
            decimal_root, decimal_input = self.scroll_to_input("Decimal step", width, height)
            if decimal_input.attrib.get("text") != "4.50":
                raise AuditFailure("precision=2 did not render 4.50")
            decimal_root, decimal_input = self.tap_and_expect_value(
                decimal_root,
                decimal_input,
                "Increase value",
                "Decimal step",
                "4.75",
                "08-decimal-step",
            )
            self.screenshot("08-decimal-step")

            self.launch()
            inset_root, inset_input = self.scroll_to_input("Inset controls", width, height)
            inset_actions = self.actions_for_input(inset_root, inset_input)
            if len(inset_actions) != 2:
                raise AuditFailure("inset variant lost its controls")
            self.screenshot("09-inset")

            self.launch()
            split_root, split_input = self.scroll_to_input("Split controls", width, height)
            split_actions = self.actions_for_input(split_root, split_input)
            if len(split_actions) != 2:
                raise AuditFailure("split variant lost its controls")
            if node_bounds(split_actions[0]).right != node_bounds(split_input).left:
                raise AuditFailure("split decrement control is not flush with the input")
            if node_bounds(split_input).right != node_bounds(split_actions[1]).left:
                raise AuditFailure("split increment control is not flush with the input")
            self.screenshot("10-split")

            self.launch()
            stacked_root, stacked_input = self.scroll_to_input("Stacked controls", width, height)
            stacked_actions = self.actions_for_input(stacked_root, stacked_input)
            if len(stacked_actions) != 2:
                raise AuditFailure("stacked variant lost its controls")
            stacked_areas = sorted((node_bounds(action) for action in stacked_actions), key=lambda a: a.top)
            if (
                stacked_areas[0].left != stacked_areas[1].left
                or stacked_areas[0].bottom != stacked_areas[1].top
                or stacked_areas[0].height < minimum_touch
                or stacked_areas[1].height < minimum_touch
            ):
                raise AuditFailure("stacked controls are not two aligned 48dp targets")
            self.screenshot("11-stacked")

            self.launch()
            reverse_root, reverse_input = self.scroll_to_input("Reverse controls", width, height)
            reverse_area = node_bounds(reverse_input)
            reverse_decrease = node_bounds(self.action(reverse_root, reverse_input, "Decrease value"))
            reverse_increase = node_bounds(self.action(reverse_root, reverse_input, "Increase value"))
            if reverse_increase.left >= reverse_area.left or reverse_decrease.left <= reverse_area.right:
                raise AuditFailure("reverse variant did not swap the control order")
            self.screenshot("12-reverse")

            self.launch()
            hidden_root, hidden_input = self.scroll_to_input("Hidden controls", width, height)
            if self.actions_for_input(hidden_root, hidden_input):
                raise AuditFailure("hidden control variant still exposes step buttons")
            if node_bounds(hidden_input).width < int(width * 0.75):
                raise AuditFailure("hidden control variant did not give the editor full width")
            self.tap(node_bounds(hidden_input))
            self.expect_ime(True, "hidden-control numeric input")
            self.replace_text("11")
            hidden_typed = self.dump("13-hidden-typed")
            self.screenshot("13-hidden-typed")
            if self.input_after(hidden_typed, "Hidden controls").attrib.get("text") != "11":
                raise AuditFailure("hidden-control editor did not accept real numeric input")

            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.8)
            landscape_width, landscape_height = self.screenshot("14-landscape-keyboard")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            self.expect_ime(True, "focused number input after rotation")
            landscape_root = self.dump("14-landscape-keyboard")
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
                raise AuditFailure("focused number input disappeared or overflowed after rotation")
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
                raise AuditFailure("focused number input is clipped by its landscape viewport")
            if self.exact(landscape_root, "Open component navigation"):
                raise AuditFailure("redundant menu button remained with expanded navigation")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            self.back()

            logs = self.shell("logcat", "-d", "-t", "2200", timeout=30.0)
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
                "component": "p-number-input",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "48dpControls": True,
                    "incrementAndDecrement": True,
                    "realNumericKeyboardInput": True,
                    "instanceIsolation": True,
                    "systemBackPersistence": True,
                    "disabledAndReadOnly": True,
                    "errorMessage": True,
                    "minimumAndMaximum": True,
                    "decimalStepAndPrecision": True,
                    "insetSplitStackedReverseHidden": True,
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
    parser = argparse.ArgumentParser(description="Audit p-number-input on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-number-input-android-audit"))
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = NumberInputAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-number-input; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-number-input: {exception}")
        raise SystemExit(1)
