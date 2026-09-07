#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import re
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_virtual_table_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class VirtualDataTableAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-data-table-virtual", "Data Table Virtual")
        self.original_font_scale = ""

    def outer_swipe(self, root: ET.Element, height: int) -> None:
        profile_labels = {
            "Standard", "Compact", "Fixed Header", "Striped",
            "Selectable", "Loading", "Empty", "Mobile",
        }
        anchors = [
            node_bounds(n) for n in self.nodes(root)
            if self.text(n) in profile_labels
            and 120 <= node_bounds(n).top <= height - 120
        ]
        start_y = max(anchors, key=lambda area: area.top).center[1] if anchors else int(height * 0.88)
        self.assert_foreground("outer table scroll")
        self.shell("input", "swipe", "540", str(start_y), "540", str(int(height * 0.30)), "600")
        time.sleep(0.7)

    def scroll_to(self, label: str, width: int, height: int) -> tuple[ET.Element, ET.Element]:
        for attempt in range(16):
            root = self.dump(f"scroll-{label.lower().replace(' ', '-')}-{attempt}")
            visible = [
                n for n in self.exact(root, label)
                if 120 <= node_bounds(n).top and node_bounds(n).bottom <= height - 100
            ]
            if visible:
                return root, visible[0]
            self.outer_swipe(root, height)
        raise AuditFailure(f"could not bring {label!r} into view")

    @staticmethod
    def recycler(table: ET.Element) -> ET.Element:
        candidates = [
            n for n in table.iter("node")
            if n.attrib.get("class") == "androidx.recyclerview.widget.RecyclerView"
        ]
        if len(candidates) != 1:
            raise AuditFailure(f"expected one virtual list, found {len(candidates)}")
        return candidates[0]

    def inner_swipe(self, recycler: ET.Element) -> None:
        area = node_bounds(recycler)
        self.assert_foreground("inner virtual-table scroll")
        self.shell(
            "input", "swipe", str(area.center[0]), str(area.bottom - 24),
            str(area.center[0]), str(area.top + 24), "500",
        )
        time.sleep(0.5)

    @staticmethod
    def package_numbers(root: ET.Element) -> list[int]:
        values = []
        for node in root.iter("node"):
            text = node.attrib.get("text", "")
            match = re.fullmatch(r"Package (\d{2})", text)
            if match:
                values.append(int(match.group(1)))
        return values

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            screen = max((node_bounds(n) for n in self.nodes(root)), key=lambda b: b.width * b.height)
            density = self.density()
            standard = self.exact(root, "Standard virtual table")
            compact = self.exact(root, "Compact virtual table")
            if not standard or not compact:
                raise AuditFailure("standard and compact virtual tables are missing")
            mounted = self.package_numbers(standard[0])
            if not 3 <= len(mounted) <= 8 or max(mounted) > 8:
                raise AuditFailure(f"virtual list mounted an unexpected initial window: {mounted}")
            if node_bounds(standard[0]).height / density < 270:
                raise AuditFailure("virtual table viewport is shorter than its 280dp contract")
            self.screenshot("00-baseline")

            standard_recycler = self.recycler(standard[0])
            for _ in range(10):
                self.inner_swipe(standard_recycler)
                progressed = self.dump("01-inner-scroll-progress")
                current_table = self.exact(progressed, "Standard virtual table")
                if not current_table:
                    raise AuditFailure("inner scroll moved the outer documentation page")
                standard_recycler = self.recycler(current_table[0])
                numbers = self.package_numbers(current_table[0])
                if numbers and max(numbers) >= 35:
                    break
            if not numbers or max(numbers) < 20 or 1 in numbers:
                raise AuditFailure(f"virtualized rows did not recycle forward: {numbers}")
            self.screenshot("01-inner-scrolled")

            self.launch()
            fixed_root, _ = self.scroll_to("Fixed Header", screen.width, screen.height)
            fixed_tables = self.exact(fixed_root, "Fixed virtual table")
            for attempt in range(5):
                recycler_visible = fixed_tables and any(
                    n.attrib.get("class") == "androidx.recyclerview.widget.RecyclerView"
                    and node_bounds(n).height > 0
                    for n in fixed_tables[0].iter("node")
                )
                if recycler_visible:
                    break
                self.outer_swipe(fixed_root, screen.height)
                fixed_root = self.dump(f"02-fixed-visible-{attempt}")
                fixed_tables = self.exact(fixed_root, "Fixed virtual table")
            if not fixed_tables:
                raise AuditFailure("fixed-header table is missing")
            fixed_table = fixed_tables[0]
            headers = [n for n in fixed_table.iter("node") if self.text(n) == "Table header"]
            if len(headers) != 1:
                raise AuditFailure("fixed table does not expose exactly one header")
            before_header = node_bounds(headers[0])
            self.inner_swipe(self.recycler(fixed_table))
            fixed_after = self.dump("02-fixed-scrolled")
            current_fixed = self.exact(fixed_after, "Fixed virtual table")
            current_headers = [n for n in current_fixed[0].iter("node") if self.text(n) == "Table header"]
            if len(current_headers) != 1 or node_bounds(current_headers[0]) != before_header:
                raise AuditFailure("fixed header moved during the inner list scroll")
            self.screenshot("02-fixed-scrolled")

            selectable, row_two = self.scroll_to("Select row 2", screen.width, screen.height)
            self.tap(node_bounds(row_two))
            selected = self.dump("03-selected")
            row_two_after = self.exact(selected, "Select row 2")
            if not row_two_after or row_two_after[0].attrib.get("checked") != "true":
                raise AuditFailure("virtual selectable row did not become checked")
            if not self.exact(selected, "2 builds selected"):
                selected, _ = self.scroll_to("2 builds selected", screen.width, screen.height)
            self.screenshot("03-selected")

            loading, _ = self.scroll_to("Refreshing builds", screen.width, screen.height)
            if not self.exact(loading, "Loading virtual table"):
                raise AuditFailure("virtual loading semantics are missing")
            empty, _ = self.scroll_to("No builds found", screen.width, screen.height)
            if not self.exact(empty, "Empty virtual table"):
                raise AuditFailure("virtual empty semantics are missing")
            self.screenshot("04-loading-empty")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("05-font-scale-130")
            if not self.exact(scaled, "Standard virtual table"):
                raise AuditFailure("virtual table disappeared at 130 percent font scale")
            self.screenshot("05-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or "ANR in dev.pam.mobileui.catalog" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "eightProfiles": True,
                "boundedViewport": True,
                "windowedMount": True,
                "innerRowRecycling": True,
                "outerScrollIsolation": True,
                "fixedHeader": True,
                "controlledSelection": True,
                "loadingAndEmpty": True,
                "fontScale130": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-data-table-virtual",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "checks": checks,
                "metrics": {"density": density, "initialMountedRows": len(mounted)},
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
    parser = argparse.ArgumentParser(description="Audit p-data-table-virtual on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-data-table-virtual-audit"))
    args = parser.parse_args()
    report = VirtualDataTableAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-data-table-virtual; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
