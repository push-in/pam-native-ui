#!/usr/bin/env python3
"""Exercise retained tag additions, removals, custom values and isolation."""
from __future__ import annotations

import argparse
import importlib.util
import json
from pathlib import Path
import sys

SPEC = importlib.util.spec_from_file_location(
    "pam_tag_shared_audit", Path(__file__).with_name("audit-autocomplete-android.py")
)
assert SPEC is not None and SPEC.loader is not None
SHARED = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = SHARED
SPEC.loader.exec_module(SHARED)


class TagInputAudit(SHARED.AutocompleteAudit):
    def trigger(self, root, label):
        matches = [node for node in self.nodes(root)
                   if node.attrib.get("class") == "android.widget.Spinner"
                   and node.attrib.get("content-desc") == label
                   and node.attrib.get("clickable") == "true"]
        if len(matches) != 1:
            raise SHARED.AuditFailure(f"expected one actionable {label} field")
        return matches[0]

    def assert_tags(self, root, label, expected):
        actual = [node.attrib["text"] for node in self.trigger(root, label).iter("node")
                  if node.attrib.get("text")]
        if actual and actual[0] == label:
            actual.pop(0)  # Persistent field label is not a selected tag.
        if set(actual) != set(expected) or len(actual) != len(expected):
            raise SHARED.AuditFailure(f"{label}: expected tags {expected}, found {actual}")

    def close_sheet(self, name):
        if self.ime_shown():
            self.back()
        self.back()
        self.assert_window_count(1, name)
        root = self.dump(name)
        self.screenshot(name)
        return root

    def run(self):
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            self.assert_tags(root, "Skills", ["PHP", "Kotlin"])
            opened = self.open_and_dump(self.trigger(root, "Skills"), "01-skills-open")
            self.choose(opened, "Swift")
            self.assert_window_count(2, "multiple selection remains open")
            root = self.close_sheet("02-swift-added")
            self.assert_tags(root, "Skills", ["PHP", "Kotlin", "Swift"])

            opened = self.open_and_dump(self.trigger(root, "Skills"), "03-remove-open")
            self.choose(opened, "PHP")
            root = self.close_sheet("04-php-removed")
            self.assert_tags(root, "Skills", ["Kotlin", "Swift"])

            opened = self.open_and_dump(self.trigger(root, "Technologies"), "05-custom-open")
            searched = self.search(opened, "Rust")
            actions = [node for node in self.exact(searched, "Use Rust")
                       if node.attrib.get("clickable") == "true"]
            if len(actions) != 1:
                raise SHARED.AuditFailure("custom Rust action is not unique")
            self.tap(SHARED.node_bounds(actions[0]))
            self.assert_window_count(2, "custom multiple selection remains open")
            root = self.close_sheet("06-custom-retained")
            self.assert_tags(root, "Technologies", ["Android", "Rust"])
            self.assert_tags(root, "Skills", ["Kotlin", "Swift"])

            opened = self.open_and_dump(self.trigger(root, "Technologies"), "07-custom-reopened")
            self.choose(opened, "Rust")
            root = self.close_sheet("08-custom-removed")
            self.assert_tags(root, "Technologies", ["Android"])
            self.assert_tags(root, "Skills", ["Kotlin", "Swift"])

            report = {
                "schemaVersion": 2, "component": "p-tag-input", "device": self.serial,
                "package": self.package, "resultStatus": 1,
                "checks": {"addExisting": True, "removeExisting": True,
                           "retainCustom": True, "removeCustomAfterReopen": True,
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
        report = TagInputAudit(args.serial, args.package, args.activity, args.output,
                               component_tag="p-tag-input", component_label="Tag Input").run()
        print(json.dumps(report["checks"], indent=2))
    except SHARED.AuditFailure as error:
        print(f"FAIL p-tag-input: {error}")
        raise SystemExit(1)
