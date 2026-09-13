#!/usr/bin/env python3
"""Verify retained multiple selections and isolation across three fields."""
from __future__ import annotations

import argparse
import importlib.util
import json
from pathlib import Path
import sys

SPEC = importlib.util.spec_from_file_location(
    "pam_multiple_selection_audit", Path(__file__).with_name("audit-tag-input-android.py")
)
assert SPEC is not None and SPEC.loader is not None
SHARED = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = SHARED
SPEC.loader.exec_module(SHARED)


class MultiSelectAudit(SHARED.TagInputAudit):
    def trigger(self, root, label):
        if label != "Categories":
            return super().trigger(root, label)
        # Empty and Error deliberately share the same field label. Scope
        # this flow to the first field after the Empty variation caption.
        caption = self.exact(root, "Empty")[0]
        minimum_top = SHARED.SHARED.node_bounds(caption).bottom
        candidates = [node for node in self.nodes(root)
                      if node.attrib.get("class") == "android.widget.Spinner"
                      and node.attrib.get("content-desc") == label
                      and node.attrib.get("clickable") == "true"
                      and SHARED.SHARED.node_bounds(node).top >= minimum_top]
        if not candidates:
            raise SHARED.SHARED.AuditFailure("Empty Categories field is missing")
        return min(candidates, key=lambda node: SHARED.SHARED.node_bounds(node).top)

    def run(self):
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            self.assert_tags(root, "Teams", ["Design", "Product"])
            opened = self.open_and_dump(self.trigger(root, "Teams"), "01-teams-open")
            self.choose(opened, "Engineering")
            self.assert_window_count(2, "adding preserves open sheet")
            root = self.close_sheet("02-team-added")
            self.assert_tags(root, "Teams", ["Design", "Engineering", "Product"])

            opened = self.open_and_dump(self.trigger(root, "Teams"), "03-teams-reopened")
            self.choose(opened, "Design")
            root = self.close_sheet("04-team-removed")
            self.assert_tags(root, "Teams", ["Engineering", "Product"])

            opened = self.open_and_dump(self.trigger(root, "People"), "05-people-open")
            searched = self.search(opened, "Bru")
            self.choose(searched, "Bruno")
            root = self.close_sheet("06-filtered-person-added")
            self.assert_tags(root, "People", ["Ana", "Bruno"])
            self.assert_tags(root, "Teams", ["Engineering", "Product"])

            opened = self.open_and_dump(self.trigger(root, "Categories"), "07-empty-open")
            self.choose(opened, "Mobile")
            self.assert_window_count(2, "first empty-field selection")
            opened = self.dump("08-mobile-selected")
            self.choose(opened, "Web")
            root = self.close_sheet("09-two-categories")
            self.assert_tags(root, "Categories", ["Mobile", "Web"])
            self.assert_tags(root, "People", ["Ana", "Bruno"])
            self.assert_tags(root, "Teams", ["Engineering", "Product"])

            report = {
                "schemaVersion": 2, "component": "p-multi-select", "device": self.serial,
                "package": self.package, "resultStatus": 1,
                "checks": {"addToSelection": True, "removeAfterReopen": True,
                           "filteredSelection": True, "selectFromEmpty": True,
                           "instanceIsolation": True, "multipleKeepsSheetOpen": True},
                "fontScale": self.setting("system", "font_scale"),
                "systemAnimationsEnabled": False, "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            self.restore()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    try:
        report = MultiSelectAudit(args.serial, args.package, args.activity, args.output,
                                  component_tag="p-multi-select", component_label="Multi Select").run()
        print(json.dumps(report["checks"], indent=2))
    except SHARED.SHARED.AuditFailure as error:
        print(f"FAIL p-multi-select: {error}")
        raise SystemExit(1)
