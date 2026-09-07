#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_date_input_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class DateInputAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-date-input", "Date Input")
        self.original_font_scale = ""

    def dialog_open(self, root) -> bool:
        texts = {self.text(node).upper() for node in self.nodes(root)}
        return "CANCEL" in texts and "OK" in texts

    def tap_labelled_picker(self, root, label: str) -> None:
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"missing date-input profile {label!r}")
        label_area = node_bounds(labels[0])
        pickers = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.DatePicker"
            and node_bounds(node).top >= label_area.bottom
        ]
        if not pickers:
            raise AuditFailure(f"missing native picker after {label!r}")
        self.tap(node_bounds(min(pickers, key=lambda node: node_bounds(node).top)))
        time.sleep(0.7)

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            baseline = self.dump("00-baseline")
            required = {"Default", "Isolated instance", "Disabled", "Read Only", "Empty", "Error"}
            present = {label for label in required if self.exact(baseline, label)}
            if present != required:
                raise AuditFailure(f"missing profiles: {sorted(required - present)}")
            pickers = [
                node for node in self.nodes(baseline)
                if node.attrib.get("class") == "android.widget.DatePicker"
            ]
            if len(pickers) != 6:
                raise AuditFailure(f"expected six native date inputs, found {len(pickers)}")
            expected_labels = {
                "Delivery date", "Review date", "Archived date",
                "Created date", "Optional date", "Invalid date",
            }
            actual_labels = {
                node.attrib.get("content-desc", "") for node in pickers
            }
            if actual_labels != expected_labels:
                raise AuditFailure(
                    f"date inputs need unique semantic names: {sorted(actual_labels)}"
                )
            heights = [node_bounds(node).height / self.density() for node in pickers]
            if min(heights) < 52:
                raise AuditFailure(f"date input fell below its 52dp field contract: {heights}")
            self.screenshot("00-baseline")

            self.tap(node_bounds(pickers[0]))
            time.sleep(0.8)
            dialog = self.dump("01-dialog")
            if not self.dialog_open(dialog):
                raise AuditFailure("default date input did not open the native date dialog")
            day = self.exact(dialog, "16")
            if not day:
                raise AuditFailure("native date dialog did not expose day 16")
            self.tap(node_bounds(day[0]))
            time.sleep(0.2)
            ok = self.exact(self.dump("01-day-selected"), "OK")
            if not ok:
                raise AuditFailure("native date dialog lost its confirmation action")
            self.tap(node_bounds(ok[0]))
            time.sleep(0.8)
            changed = self.dump("02-date-changed")
            if not self.exact(changed, "16/07/2026"):
                raise AuditFailure("confirmed native date was not committed to controlled state")
            if not self.exact(changed, "01/09/2026"):
                raise AuditFailure("changing one instance mutated the isolated date input")
            self.screenshot("02-date-changed")

            self.tap_labelled_picker(changed, "Read Only")
            read_only = self.dump("03-read-only-inert")
            if self.dialog_open(read_only):
                raise AuditFailure("read-only date input opened the native dialog")

            self.tap_labelled_picker(read_only, "Disabled")
            disabled = self.dump("04-disabled-inert")
            if self.dialog_open(disabled):
                raise AuditFailure("disabled date input opened the native dialog")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("05-font-scale-130")
            if not self.exact(scaled, "Default") or not self.exact(scaled, "15/07/2026"):
                raise AuditFailure("date input disappeared or clipped at 130 percent font scale")
            self.screenshot("05-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or f"ANR in {self.package}" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "sixProfiles": True,
                "nativeFieldGeometry": True,
                "uniqueSemanticNames": True,
                "nativeDialog": True,
                "controlledDateChange": True,
                "isolatedState": True,
                "readOnlyInert": True,
                "disabledInert": True,
                "fontScale130": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-date-input",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "checks": checks,
                "metrics": {"density": self.density(), "fieldHeightsDp": heights},
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-date-input on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-date-input-audit"))
    args = parser.parse_args()
    report = DateInputAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-date-input; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
