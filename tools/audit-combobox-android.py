#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_autocomplete_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class ComboboxAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-combobox",
            component_label="Combobox",
        )

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            baseline = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            for label in (
                "Default", "Isolated instance", "Disabled", "Read Only", "No Data",
            ):
                if not self.exact(baseline, label):
                    raise AuditFailure(f"audit surface is missing {label!r}")
            self.assert_window_count(1, "baseline")

            default_trigger = self.trigger_after(baseline, "Default")
            target_dp = node_bounds(default_trigger).height / self.density()
            if target_dp < 48.0:
                raise AuditFailure(
                    f"default touch target is {target_dp:.1f}dp; Material minimum is 48dp"
                )

            opened = self.open_and_dump(default_trigger, "01-default-open")
            searched = self.search(opened, "Strategy")
            searched = self.dump("02-custom-value")
            actions = [
                node for node in self.nodes(searched)
                if self.text(node) == "Use Strategy"
                and node.attrib.get("clickable") == "true"
                and node_bounds(node).height > 0
            ]
            if len(actions) != 1:
                raise AuditFailure("custom value did not expose exactly one use action")
            input_area = node_bounds([
                node for node in self.nodes(searched)
                if node.attrib.get("class") == "android.widget.EditText"
            ][0])
            action_area = node_bounds(actions[0])
            density = self.density()
            if action_area.top < input_area.bottom + round(8.0 * density):
                raise AuditFailure("custom-value action overlaps the search field")
            if not (48.0 <= action_area.height / density <= 64.0):
                raise AuditFailure(
                    f"custom-value action is {action_area.height / density:.1f}dp high"
                )
            self.screenshot("02-custom-value")
            self.tap(node_bounds(actions[0]))
            self.assert_window_count(1, "custom value close")
            custom = self.dump("03-custom-selected")
            if "Strategy" not in self.displayed_value(custom, "Default"):
                raise AuditFailure("custom value Strategy was not retained")
            self.screenshot("03-custom-selected")

            opened = self.open_and_dump(
                self.trigger_after(custom, "Default"),
                "04-existing-option-open",
            )
            searched = self.search(opened, "eng")
            searched = self.dump("05-filtered-existing")
            options = [
                node for node in self.nodes(searched)
                if node.attrib.get("clickable") == "true"
                and node_bounds(node).height > 0
                and self.text(node) in {"Design", "Engineering", "Product", "Research"}
            ]
            if [self.text(node) for node in options] != ["Engineering"]:
                raise AuditFailure("filtering did not isolate Engineering")
            self.screenshot("05-filtered-existing")
            self.choose(searched, "Engineering")
            self.assert_window_count(1, "existing option close")
            selected = self.dump("05-existing-selected")
            if "Engineering" not in self.displayed_value(selected, "Default"):
                raise AuditFailure("existing option Engineering was not retained")
            self.screenshot("05-existing-selected")

            opened = self.open_and_dump(
                self.trigger_after(selected, "Default"),
                "06-backdrop-open",
            )
            search_area = node_bounds([
                node for node in self.nodes(opened)
                if node.attrib.get("class") == "android.widget.EditText"
            ][0])
            self.tap((width // 2, max(120, search_area.top - 120)))
            backdrop = self.dump("07-backdrop-closed")
            self.assert_window_count(1, "backdrop close")
            if "Engineering" not in self.displayed_value(backdrop, "Default"):
                raise AuditFailure("backdrop dismissal corrupted the selected value")

            edge_open = self.open_and_dump(
                self.trigger_after(backdrop, "Default"),
                "07b-last-option-edge-open",
            )
            research = [
                node for node in self.exact(edge_open, "Research")
                if node.attrib.get("clickable") == "true"
            ]
            if len(research) != 1:
                raise AuditFailure("last combobox option is not uniquely actionable")
            research_area = node_bounds(research[0])
            intended_edge_y = research_area.top + round(52.0 * density)
            physical_bottom = self.physical_content_bottom(width, height)
            intended_row_bottom = research_area.top + round(56.0 * density)
            if intended_edge_y >= physical_bottom:
                raise AuditFailure("last combobox option extends into system navigation")
            if physical_bottom - intended_row_bottom < round(20.0 * density):
                raise AuditFailure(
                    "last combobox option violates the Material bottom safe area"
                )
            self.tap((research_area.center[0], intended_edge_y))
            edge_selected = self.dump("07c-last-option-edge-selected")
            self.assert_window_count(1, "last-row lower-edge selection")
            if "Research" not in self.displayed_value(edge_selected, "Default"):
                raise AuditFailure("the lower edge of the final 56dp option is not interactive")
            self.screenshot("07c-last-option-edge-selected")

            isolated_trigger = self.trigger_after(edge_selected, "Isolated instance")
            isolated_open = self.open_and_dump(isolated_trigger, "08-isolated-open")
            self.choose(isolated_open, "Product")
            isolated = self.dump("09-isolated-selected")
            if "Research" not in self.displayed_value(isolated, "Default"):
                raise AuditFailure("second instance corrupted the first combobox")
            if "Product" not in self.displayed_value(isolated, "Isolated instance"):
                raise AuditFailure("second instance did not retain Product")
            self.screenshot("09-isolated-selected")

            for label in ("Disabled", "Read Only"):
                _, trigger = self.scroll_to_trigger(label, width, height)
                before = self.visible_app_windows()
                self.tap(node_bounds(trigger))
                if self.visible_app_windows() != before:
                    raise AuditFailure(f"{label} combobox opened a modal")
                if label == "Disabled" and trigger.attrib.get("enabled") == "true":
                    raise AuditFailure("disabled combobox is accessibility-enabled")
                if label == "Read Only" and trigger.attrib.get("clickable") == "true":
                    raise AuditFailure("read-only combobox exposes a click action")

            _, empty_trigger = self.scroll_to_trigger("No Data", width, height)
            empty = self.open_and_dump(empty_trigger, "10-no-data-open")
            messages = [
                node for node in self.exact(empty, "No options available")
                if node_bounds(node).width > 0
            ]
            inputs = [
                node for node in self.nodes(empty)
                if node.attrib.get("class") == "android.widget.EditText"
                and node_bounds(node).width > 0
            ]
            if len(messages) != 1:
                raise AuditFailure(
                    "empty combobox must expose exactly one no-data message; "
                    f"found {len(messages)}"
                )
            message_area = node_bounds(messages[0])
            search_area = node_bounds(inputs[0])
            if message_area.top < search_area.bottom + round(8.0 * density):
                raise AuditFailure("empty combobox message overlaps the search field")
            minimum_gutter = round(16.0 * density)
            if (
                message_area.left < minimum_gutter
                or width - message_area.right < minimum_gutter
            ):
                raise AuditFailure("empty combobox violates the 16dp sheet gutter")
            self.close_modal_with_back("no-data close")

            self.launch()
            root = self.dump("11-back-baseline")
            opened = self.open_and_dump(
                self.trigger_after(root, "Default"),
                "11-back-open",
            )
            self.search(opened, "pro")
            self.back()
            if self.ime_shown() or self.visible_app_windows() != 2:
                raise AuditFailure("first Back did not hide only the keyboard")
            self.back()
            self.assert_window_count(1, "second Back")

            root = self.dump("12-rotation-baseline")
            opened = self.open_and_dump(
                self.trigger_after(root, "Default"),
                "12-rotation-open",
            )
            rotation_query = self.search(opened, "pro")
            rotation_inputs = [
                node for node in self.nodes(rotation_query)
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            if len(rotation_inputs) != 1 or rotation_inputs[0].attrib.get("text") != "pro":
                raise AuditFailure("rotation precondition did not preserve the exact query 'pro'")
            self.back()
            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            landscape_root = self.dump("13-landscape")
            landscape_inputs = [
                node for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            if len(landscape_inputs) != 1 or landscape_inputs[0].attrib.get("text") != "pro":
                raise AuditFailure("landscape rotation changed or duplicated the search query")
            landscape = self.screenshot("13-landscape")
            self.assert_window_count(2, "landscape sheet")
            if landscape[0] <= landscape[1]:
                raise AuditFailure("device did not enter landscape")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            portrait = self.dump("14-portrait-restored")
            portrait_inputs = [
                node for node in self.nodes(portrait)
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            if len(portrait_inputs) != 1 or portrait_inputs[0].attrib.get("text") != "pro":
                raise AuditFailure("portrait restoration changed or duplicated the search query")
            portrait_size = self.screenshot("14-portrait-restored")
            self.assert_window_count(2, "portrait-restored sheet")
            if portrait_size[0] >= portrait_size[1]:
                raise AuditFailure("device did not return to portrait")
            visible_text = {self.text(node) for node in self.nodes(portrait)}
            if {"Overview", "Actions", "Forms", "Data", "Overlays"} <= visible_text:
                raise AuditFailure("responsive drawer remained open after rotation")
            self.back()

            logs = self.shell("logcat", "-d", "-t", "1200", timeout=30.0)
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
                "component": "p-combobox",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "result": "passed",
                "checks": {
                    "singleSheet": True,
                    "materialTouchTarget": True,
                    "customValue": True,
                    "filterAndSelection": True,
                    "backdropPreservesValue": True,
                    "lastRowLowerEdgeHitTarget": True,
                    "bottomSafeArea": True,
                    "instanceIsolation": True,
                    "disabledAndReadOnly": True,
                    "noDataUniqueAndSeparated": True,
                    "materialDragIndicator": True,
                    "keyboardBack": True,
                    "rotation": True,
                    "responsiveDrawer": True,
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
    parser = argparse.ArgumentParser(description="Audit p-combobox on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("/tmp/pam-combobox-android-audit"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = ComboboxAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-combobox; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-combobox: {exception}")
        raise SystemExit(1)
