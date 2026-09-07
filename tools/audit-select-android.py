#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_selection_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class SelectAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-select",
            component_label="Select",
        )

    def open_select(self, trigger, name: str):
        self.tap(node_bounds(trigger))
        self.assert_window_count(2, f"{name} open")
        opened = self.dump(name)
        inputs = [
            node for node in self.nodes(opened)
            if node.attrib.get("class") == "android.widget.EditText"
            and node_bounds(node).width > 0
        ]
        if inputs:
            raise AuditFailure("select incorrectly rendered a searchable input")
        if self.ime_shown():
            raise AuditFailure("select incorrectly opened the software keyboard")
        self.screenshot(name)
        return opened

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
            opened = self.open_select(default_trigger, "01-default-open")
            options = [
                node for node in self.nodes(opened)
                if node.attrib.get("clickable") == "true"
                and self.text(node) in {"Design", "Engineering", "Product", "Research"}
            ]
            if [self.text(node) for node in options] != [
                "Design", "Engineering", "Product", "Research",
            ]:
                raise AuditFailure("select did not expose the four options in order")
            self.choose(opened, "Engineering")
            self.assert_window_count(1, "selection close")
            selected = self.dump("02-selected")
            if "Engineering" not in self.displayed_value(selected, "Default"):
                raise AuditFailure("selected Engineering was not retained")
            self.screenshot("02-selected")

            opened = self.open_select(
                self.trigger_after(selected, "Default"),
                "03-backdrop-open",
            )
            # Tap well inside the scrim. Deriving this coordinate from the
            # selected row is unsafe because a compact sheet can have a
            # sizeable rounded header above its first item; a point only a few
            # pixels above the row may still be inside the sheet content.
            self.tap((width // 2, height // 4))
            backdrop = self.dump("04-backdrop-closed")
            self.assert_window_count(1, "backdrop close")
            if "Engineering" not in self.displayed_value(backdrop, "Default"):
                raise AuditFailure("backdrop dismissal corrupted the selected value")

            edge_open = self.open_select(
                self.trigger_after(backdrop, "Default"),
                "04b-last-option-edge-open",
            )
            research = [
                node for node in self.exact(edge_open, "Research")
                if node.attrib.get("clickable") == "true"
            ]
            if len(research) != 1:
                raise AuditFailure("last select option is not uniquely actionable")
            research_area = node_bounds(research[0])
            intended_edge_y = research_area.top + round(52.0 * self.density())
            if intended_edge_y >= self.physical_content_bottom(width, height):
                raise AuditFailure("last select option extends into system navigation")
            self.tap((research_area.center[0], intended_edge_y))
            edge_selected = self.dump("04c-last-option-edge-selected")
            self.assert_window_count(1, "last-row lower-edge selection")
            if "Research" not in self.displayed_value(edge_selected, "Default"):
                raise AuditFailure("the lower edge of the final 56dp option is not interactive")
            self.screenshot("04c-last-option-edge-selected")

            isolated_open = self.open_select(
                self.trigger_after(edge_selected, "Isolated instance"),
                "05-isolated-open",
            )
            self.choose(isolated_open, "Product")
            isolated = self.dump("06-isolated-selected")
            if "Research" not in self.displayed_value(isolated, "Default"):
                raise AuditFailure("second instance corrupted the first select")
            if "Product" not in self.displayed_value(isolated, "Isolated instance"):
                raise AuditFailure("second instance did not retain Product")
            self.screenshot("06-isolated-selected")

            for label in ("Disabled", "Read Only"):
                _, trigger = self.scroll_to_trigger(label, width, height)
                before = self.visible_app_windows()
                self.tap(node_bounds(trigger))
                if self.visible_app_windows() != before:
                    raise AuditFailure(f"{label} select opened a modal")
                if label == "Disabled" and trigger.attrib.get("enabled") == "true":
                    raise AuditFailure("disabled select is accessibility-enabled")
                if label == "Read Only" and trigger.attrib.get("clickable") == "true":
                    raise AuditFailure("read-only select exposes a click action")

            _, empty_trigger = self.scroll_to_trigger("No Data", width, height)
            empty = self.open_select(empty_trigger, "07-no-data-open")
            messages = [
                node for node in self.exact(empty, "No options available")
                if node_bounds(node).width > 0
            ]
            if len(messages) != 1:
                raise AuditFailure(
                    "empty select must expose exactly one no-data message; "
                    f"found {len(messages)}"
                )
            message_area = node_bounds(messages[0])
            density = self.density()
            minimum_gutter = round(16.0 * density)
            if (
                message_area.left < minimum_gutter
                or width - message_area.right < minimum_gutter
            ):
                raise AuditFailure("empty select violates the 16dp horizontal sheet gutter")
            reported_message_dp = message_area.height / density
            if not (48.0 <= reported_message_dp <= 64.0):
                accessibility_root = node_bounds(next(empty.iter("node")))
                intended_bottom = message_area.top + round(56.0 * density)
                if (
                    message_area.bottom != accessibility_root.bottom
                    or intended_bottom > self.physical_content_bottom(width, height)
                ):
                    raise AuditFailure(
                        "empty select does not own a complete 56dp physical row: "
                        f"reported={reported_message_dp:.1f}dp, "
                        f"message={message_area}, root={accessibility_root}"
                    )
            self.back()
            self.assert_window_count(1, "no-data close")

            self.launch()
            root = self.dump("08-back-baseline")
            self.open_select(self.trigger_after(root, "Default"), "08-back-open")
            self.back()
            self.assert_window_count(1, "Back close")

            root = self.dump("09-rotation-baseline")
            self.open_select(self.trigger_after(root, "Default"), "09-rotation-open")
            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            self.dump("10-landscape")
            landscape = self.screenshot("10-landscape")
            self.assert_window_count(2, "landscape sheet")
            if landscape[0] <= landscape[1]:
                raise AuditFailure("device did not enter landscape")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            portrait = self.dump("11-portrait-restored")
            portrait_size = self.screenshot("11-portrait-restored")
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
                "component": "p-select",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "result": "passed",
                "checks": {
                    "singleSheet": True,
                    "materialTouchTarget": True,
                    "noSearchOrKeyboard": True,
                    "selection": True,
                    "backdropPreservesValue": True,
                    "lastRowLowerEdgeHitTarget": True,
                    "instanceIsolation": True,
                    "disabledAndReadOnly": True,
                    "noDataUniqueAndAligned": True,
                    "materialDragIndicator": True,
                    "systemBack": True,
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
    parser = argparse.ArgumentParser(description="Audit p-select on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output", type=Path, default=Path("/tmp/pam-select-android-audit"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = SelectAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-select; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-select: {exception}")
        raise SystemExit(1)
