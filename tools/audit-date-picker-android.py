#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_date_picker_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class DatePickerAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-date-picker", "Date Picker")
        self.original_font_scale = ""

    def date_node(self, root, iso_date: str):
        matches = [
            node for node in self.nodes(root)
            if iso_date in node.attrib.get("content-desc", "")
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one calendar date {iso_date}, found {len(matches)}")
        return matches[0]

    def launch_scenario(self, scenario: str):
        self.launch(scenario)
        return self.dump(f"scenario-{scenario}")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            baseline = self.dump("00-baseline")
            calendars = [n for n in self.nodes(baseline) if n.attrib.get("class") == "android.widget.CalendarView"]
            if len(calendars) != 1:
                raise AuditFailure(f"expected one native calendar, found {len(calendars)}")
            area = node_bounds(calendars[0])
            if area.width / self.density() < 320 or area.height / self.density() < 320:
                raise AuditFailure(f"calendar viewport is below its mobile geometry contract: {area}")
            selected = self.date_node(baseline, "julho 15, 2026")
            if selected.attrib.get("selected") != "true":
                raise AuditFailure("initial calendar date is not selected")
            self.screenshot("00-baseline")

            day_16 = self.date_node(baseline, "julho 16, 2026")
            self.tap(node_bounds(day_16))
            time.sleep(0.6)
            changed = self.dump("01-selected")
            if self.date_node(changed, "julho 16, 2026").attrib.get("selected") != "true":
                raise AuditFailure("real date tap did not update controlled selection")
            self.screenshot("01-selected")

            next_month = [n for n in self.nodes(changed) if n.attrib.get("content-desc") == "Next month"]
            if len(next_month) != 1:
                raise AuditFailure("calendar next-month action is missing")
            self.tap(node_bounds(next_month[0]))
            time.sleep(0.5)
            august = self.dump("02-next-month")
            if not any("agosto" in n.attrib.get("content-desc", "") for n in self.nodes(august)):
                raise AuditFailure("next-month action did not navigate to August")
            self.screenshot("02-next-month")

            ranged = self.launch_scenario("range")
            for date in ("julho 12, 2026", "julho 18, 2026"):
                if self.date_node(ranged, date).attrib.get("selected") != "true":
                    raise AuditFailure(f"range endpoint {date} is not selected")
            self.screenshot("03-range")

            week = self.launch_scenario("week")
            week_calendar = [n for n in self.nodes(week) if n.attrib.get("class") == "android.widget.CalendarView"]
            week_numbers = [
                n for n in week_calendar[0].iter("node")
                if n.attrib.get("content-desc", "").startswith("Week ")
            ] if week_calendar else []
            if len(week_numbers) < 4:
                raise AuditFailure("week-number profile does not expose calendar week semantics")
            self.screenshot("04-week-numbers")

            bounded = self.launch_scenario("bounded")
            if self.date_node(bounded, "julho 9, 2026").attrib.get("enabled") != "false":
                raise AuditFailure("date before minimum remains enabled")
            if self.date_node(bounded, "julho 25, 2026").attrib.get("enabled") != "false":
                raise AuditFailure("date after maximum remains enabled")
            if self.date_node(bounded, "julho 10, 2026").attrib.get("enabled") != "true":
                raise AuditFailure("minimum boundary date is not enabled")
            self.screenshot("05-bounded")

            for scenario in ("readonly", "disabled"):
                inert = self.launch_scenario(scenario)
                before = self.date_node(inert, "julho 15, 2026")
                self.tap(node_bounds(self.date_node(inert, "julho 16, 2026")))
                time.sleep(0.4)
                after = self.dump(f"06-{scenario}-inert")
                if self.date_node(after, "julho 15, 2026").attrib.get("selected") != "true":
                    raise AuditFailure(f"{scenario} calendar changed after a date tap")
                if before.attrib.get("enabled") != "false" or before.attrib.get("clickable") != "false":
                    raise AuditFailure(f"{scenario} calendar date remains actionable")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("07-font-scale-130")
            self.date_node(scaled, "julho 15, 2026")
            if not self.exact(scaled, "Default"):
                raise AuditFailure("calendar disappeared at 130 percent font scale")
            self.screenshot("07-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or f"ANR in {self.package}" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "nativeCalendarGeometry": True,
                "controlledDateSelection": True,
                "monthNavigation": True,
                "rangeEndpoints": True,
                "weekNumbers": True,
                "boundedDates": True,
                "readOnlyInert": True,
                "disabledInert": True,
                "fontScale130": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-date-picker",
                "device": self.serial,
                "checks": checks,
                "metrics": {"density": self.density(), "calendarBounds": area.__dict__},
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-date-picker on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-date-picker-audit"))
    args = parser.parse_args()
    report = DatePickerAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-date-picker; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
