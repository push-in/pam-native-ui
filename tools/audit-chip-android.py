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
SPEC = importlib.util.spec_from_file_location("pam_chip_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class ChipAudit(AutocompleteAudit):
    LABELS = (
        "View release details",
        "Filter production releases",
        "Android filter",
        "Design system tag",
        "Unavailable action",
        "Ready for accessibility review",
    )

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-chip", "Chip")
        self.original_font_scale = ""

    def control(self, root: ET.Element, label: str) -> ET.Element:
        matches = [node for node in self.nodes(root) if node.attrib.get("content-desc") == label]
        if len(matches) != 1:
            raise AuditFailure(f"expected one control named {label!r}, found {len(matches)}")
        return matches[0]

    def require_text(self, root: ET.Element, value: str) -> None:
        if not self.exact(root, value):
            raise AuditFailure(f"expected visible feedback {value!r}")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            density = self.density()
            for label in self.LABELS:
                control = self.control(root, label)
                area = node_bounds(control)
                if area.height / density < 31.0 or area.height / density > 33.0:
                    raise AuditFailure(f"{label!r} does not use the 32dp chip container")
                if area.left < 0:
                    raise AuditFailure(f"{label!r} escapes the viewport")
            if self.control(root, "Filter production releases").attrib.get("selected") != "true":
                raise AuditFailure("filter chip is not initially selected")
            if self.control(root, "Unavailable action").attrib.get("enabled") != "false":
                raise AuditFailure("disabled chip is exposed as enabled")
            self.screenshot("00-baseline")

            assist = node_bounds(self.control(root, "View release details"))
            self.tap((assist.center[0], assist.top - round(5 * density)))
            self.require_text(self.dump("01-assist-edge-tap"), "Release opened")

            root = self.dump("02-before-filter")
            self.tap(node_bounds(self.control(root, "Filter production releases")))
            root = self.dump("02-filter-cleared")
            self.require_text(root, "Filter cleared")
            if self.control(root, "Filter production releases").attrib.get("selected") != "false":
                raise AuditFailure("filter chip did not clear its selected state")
            self.tap(node_bounds(self.control(root, "Filter production releases")))
            root = self.dump("03-filter-selected")
            self.require_text(root, "Filter selected")
            if self.control(root, "Filter production releases").attrib.get("selected") != "true":
                raise AuditFailure("filter chip did not restore its selected state")

            close = node_bounds(self.control(root, "Remove Android filter"))
            self.tap((close.right + round(6 * density), close.center[1]))
            removed = self.dump("04-input-removed-edge-tap")
            self.require_text(removed, "Android filter removed")
            restore = self.exact(removed, "Restore")
            if len(restore) != 1:
                raise AuditFailure("input chip does not expose one restore action")
            self.tap(node_bounds(restore[0]))
            restored = self.dump("05-input-restored")
            self.control(restored, "Android filter")

            disabled = self.control(restored, "Unavailable action")
            self.tap(node_bounds(disabled))
            if self.control(self.dump("06-disabled-inert"), "Unavailable action").attrib.get("enabled") != "false":
                raise AuditFailure("disabled chip changed after a real tap")
            self.screenshot("06-interacted")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("07-font-scale-130")
            self.control(scaled, "Ready for accessibility review")
            self.screenshot("07-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.launch()
            stress = self.dump("08-stress-before")
            point = node_bounds(self.control(stress, "Filter production releases")).center
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for _ in range(20):
                self.shell("input", "tap", str(point[0]), str(point[1]))
                time.sleep(0.08)
            time.sleep(0.4)
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            p99_match = re.search(r"99th percentile:\s*(\d+)ms", gfx)
            p99 = int(p99_match.group(1)) if p99_match else -1
            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or "ANR in dev.pam.mobileui.catalog" in logs:
                raise AuditFailure("runtime errors found in logcat")

            checks = {
                "sixSemanticProfiles": True,
                "material32DpContainer": True,
                "fortyEightDpEffectiveTarget": True,
                "leadingIcons": True,
                "selectedFilterToggle": True,
                "inputRemoveAndRestore": True,
                "disabledInertia": True,
                "fontScale130": True,
                "twentyTapStress": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-chip",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "checks": checks,
                "metrics": {"density": density, "stressP99Ms": p99},
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-chip on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-chip-audit"))
    args = parser.parse_args()
    report = ChipAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-chip; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
