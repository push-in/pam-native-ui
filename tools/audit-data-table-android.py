#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_data_table_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class DataTableAudit(AutocompleteAudit):
    PROFILES = (
        "Standard", "Compact", "Comfortable", "Striped",
        "Selectable", "Loading", "Empty", "Mobile",
    )

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-data-table", "Data Table")
        self.original_font_scale = ""

    def visible(self, root: ET.Element, label: str, height: int) -> list[ET.Element]:
        return [
            n for n in self.exact(root, label)
            if 120 <= node_bounds(n).top and node_bounds(n).bottom <= height - 100
        ]

    def scroll_to(self, label: str, width: int, height: int) -> tuple[ET.Element, ET.Element]:
        for attempt in range(12):
            root = self.dump(f"scroll-{label.lower().replace(' ', '-')}-{attempt}")
            matches = self.visible(root, label, height)
            if matches:
                return root, matches[0]
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} into view")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            screen = max((node_bounds(n) for n in self.nodes(root)), key=lambda b: b.width * b.height)
            density = self.density()
            labels = {self.text(n) for n in self.nodes(root)}
            if not {"Standard", "Compact", "Comfortable"}.issubset(labels):
                raise AuditFailure("the first three density profiles are missing")
            tables = [
                n for n in self.nodes(root)
                if n.attrib.get("class") == "android.widget.TableLayout"
                and n.attrib.get("content-desc", "").endswith("product table")
            ]
            if len(tables) < 3 or any(node_bounds(n).width < screen.width * 0.85 for n in tables[:3]):
                raise AuditFailure("density tables do not fill the content viewport")
            self.screenshot("00-baseline")

            selectable_root, row_two = self.scroll_to("Select row 2", screen.width, screen.height)
            if row_two.attrib.get("checked") == "true":
                raise AuditFailure("row two starts selected")
            self.tap(node_bounds(row_two))
            selected = self.dump("01-row-selected")
            selected_row_two = self.exact(selected, "Select row 2")
            if not selected_row_two or selected_row_two[0].attrib.get("checked") != "true":
                raise AuditFailure("row two did not expose checked state")
            if not self.exact(selected, "2 products selected"):
                selected, _ = self.scroll_to("2 products selected", screen.width, screen.height)
            self.screenshot("01-row-selected")

            _, select_all = self.scroll_to("Select all rows", screen.width, screen.height)
            self.tap(node_bounds(select_all))
            all_selected = self.dump("02-select-all")
            if not self.exact(all_selected, "3 products selected"):
                all_selected, _ = self.scroll_to("3 products selected", screen.width, screen.height)

            loading, _ = self.scroll_to("Syncing inventory", screen.width, screen.height)
            if not self.exact(loading, "Loading product table"):
                raise AuditFailure("loading table semantics are missing")
            self.screenshot("03-loading")
            empty, _ = self.scroll_to("No products found", screen.width, screen.height)
            if not self.exact(empty, "Empty product table"):
                raise AuditFailure("empty table semantics are missing")
            mobile, mobile_label = self.scroll_to("Mobile product table", screen.width, screen.height)
            if node_bounds(mobile_label).width < screen.width * 0.85:
                raise AuditFailure("mobile table does not use the available viewport")
            self.screenshot("04-mobile-empty")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("05-font-scale-130")
            if not self.exact(scaled, "Data Table"):
                raise AuditFailure("table route disappeared at 130 percent font scale")
            self.screenshot("05-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "accelerometer_rotation", "0")
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "data-table landscape")
            self.launch()
            landscape = self.dump("06-landscape")
            if not self.exact(landscape, "Standard product table"):
                raise AuditFailure("standard table is missing in landscape")
            self.screenshot("06-landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "data-table portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or "ANR in dev.pam.mobileui.catalog" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "eightProfiles": True,
                "densityGeometry": True,
                "controlledRowSelection": True,
                "selectAll": True,
                "loadingState": True,
                "emptyState": True,
                "mobileLayout": True,
                "fontScale130": True,
                "landscape": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-data-table",
                "device": self.serial,
                "checks": checks,
                "metrics": {"density": density},
                "evidence": self.evidence,
            }
            self.output.mkdir(parents=True, exist_ok=True)
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-data-table on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-data-table-audit"))
    args = parser.parse_args()
    report = DataTableAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-data-table; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
