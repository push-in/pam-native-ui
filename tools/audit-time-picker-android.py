#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_time_picker_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class TimePickerAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-time-picker", "Time Picker")
        self.original_font_scale = ""

    def picker(self, root):
        matches = [n for n in self.nodes(root) if n.attrib.get("class") == "android.widget.TimePicker"]
        if len(matches) != 1:
            raise AuditFailure(f"expected one native time picker, found {len(matches)}")
        return matches[0]

    def open_dialog(self, root):
        self.tap(node_bounds(self.picker(root)))
        time.sleep(0.6)
        opened = self.dump("dialog-open")
        if not self.exact(opened, "CANCEL") or not self.exact(opened, "OK"):
            raise AuditFailure("time field did not open Android's native dialog")
        return opened

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            baseline = self.dump("00-baseline")
            area = node_bounds(self.picker(baseline))
            if area.height / self.density() < 52:
                raise AuditFailure("time field fell below its 52dp visual-height contract")
            if not self.exact(baseline, "14:35"):
                raise AuditFailure("initial controlled time is missing")
            self.screenshot("00-baseline")

            dialog = self.open_dialog(baseline)
            hour_three = [n for n in self.nodes(dialog) if n.attrib.get("content-desc") == "3"]
            if len(hour_three) != 1:
                raise AuditFailure("native hour dial does not expose hour 3")
            self.tap(node_bounds(hour_three[0]))
            time.sleep(0.25)
            minutes = self.dump("01-minute-dial")
            minute_45 = [n for n in self.nodes(minutes) if n.attrib.get("content-desc") == "45"]
            if len(minute_45) != 1:
                raise AuditFailure("native minute dial does not expose minute 45")
            self.tap(node_bounds(minute_45[0]))
            pm = self.exact(self.dump("01-minute-selected"), "PM")
            if len(pm) != 1:
                raise AuditFailure("12-hour dialog does not expose PM")
            self.tap(node_bounds(pm[0]))
            ok = self.exact(self.dump("01-pm-selected"), "OK")
            self.tap(node_bounds(ok[0]))
            time.sleep(0.6)
            changed = self.dump("02-time-changed")
            if not self.exact(changed, "15:45"):
                raise AuditFailure("confirmed native time did not update controlled state")
            self.screenshot("02-time-changed")

            self.launch("24-hour")
            twenty_four = self.open_dialog(self.dump("03-24-hour-field"))
            texts = {self.text(n) for n in self.nodes(twenty_four)}
            descriptions = {n.attrib.get("content-desc", "") for n in self.nodes(twenty_four)}
            if {"AM", "PM"} & texts:
                raise AuditFailure("24-hour profile still exposes AM/PM")
            if not set(map(str, range(13, 24))).issubset(descriptions):
                raise AuditFailure("24-hour dialog does not expose hours 13 through 23")
            self.screenshot("03-24-hour-dialog")
            self.back()

            self.launch("ampm")
            ampm = self.open_dialog(self.dump("04-ampm-field"))
            if not {"AM", "PM"}.issubset({self.text(n) for n in self.nodes(ampm)}):
                raise AuditFailure("AM/PM profile is missing its period controls")
            self.back()

            for scenario in ("readonly", "disabled"):
                self.launch(scenario)
                inert = self.dump(f"05-{scenario}-before")
                field = self.picker(inert)
                self.tap(node_bounds(field))
                time.sleep(0.35)
                after = self.dump(f"05-{scenario}-after")
                if self.exact(after, "CANCEL"):
                    raise AuditFailure(f"{scenario} time field opened a dialog")
                expected = ("false", "true") if scenario == "readonly" else ("false", "false")
                actual = (field.attrib.get("clickable"), field.attrib.get("enabled"))
                if actual != expected:
                    raise AuditFailure(f"{scenario} semantics are {actual}, expected {expected}")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("06-font-scale-130")
            if not self.exact(scaled, "14:35") or node_bounds(self.picker(scaled)).height <= 0:
                raise AuditFailure("time field disappeared at 130 percent font scale")
            self.screenshot("06-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or f"ANR in {self.package}" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "nativeFieldGeometry": True,
                "nativeDialog": True,
                "hourAndMinuteSelection": True,
                "controlledTimeChange": True,
                "twentyFourHourMode": True,
                "amPmMode": True,
                "readOnlyInert": True,
                "disabledInert": True,
                "fontScale130": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-time-picker",
                "device": self.serial,
                "checks": checks,
                "metrics": {"density": self.density(), "fieldHeightDp": area.height / self.density()},
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-time-picker on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-time-picker-audit"))
    args = parser.parse_args()
    report = TimePickerAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-time-picker; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
