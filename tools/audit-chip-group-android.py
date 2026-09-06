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
SPEC = importlib.util.spec_from_file_location("pam_chip_group_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class ChipGroupAudit(AutocompleteAudit):
    DESCRIPTIONS = ("Design filter", "Android filter", "Documentation filter")

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-chip-group", "Chip Group")
        self.original_font_scale = ""

    def controls(self, root: ET.Element) -> list[ET.Element]:
        result = [
            node for node in self.nodes(root)
            if node.attrib.get("content-desc") in self.DESCRIPTIONS
        ]
        if len(result) != 12:
            raise AuditFailure(f"expected 12 chip-group controls, found {len(result)}")
        return result

    @staticmethod
    def selected(node: ET.Element) -> bool:
        return node.attrib.get("selected") == "true"

    def require_text(self, root: ET.Element, value: str) -> None:
        if not self.exact(root, value):
            raise AuditFailure(f"expected feedback {value!r}")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            controls = self.controls(root)
            density = self.density()
            expected = {1, 3, 4, 7, 10}
            if {i for i, item in enumerate(controls) if self.selected(item)} != expected:
                raise AuditFailure("initial single, multiple, vertical or disabled selection is wrong")
            for index, control in enumerate(controls):
                area = node_bounds(control)
                # Android rounds selected and outlined chip bounds one pixel
                # differently at 420 dpi (81/82 px for the same 32 dp token).
                if area.height / density < 30.5:
                    raise AuditFailure(f"chip {index} has collapsed geometry")
                if index >= 9 and control.attrib.get("enabled") != "false":
                    raise AuditFailure(f"disabled group chip {index} is enabled")
            if not (
                node_bounds(controls[6]).left == node_bounds(controls[7]).left
                == node_bounds(controls[8]).left
                and node_bounds(controls[6]).top < node_bounds(controls[7]).top
                < node_bounds(controls[8]).top
            ):
                raise AuditFailure("vertical direction is not a true aligned column")
            self.screenshot("00-baseline")

            self.tap(node_bounds(controls[0]))
            single = self.dump("01-single-design")
            self.require_text(single, "Selected design")
            current = self.controls(single)
            if not self.selected(current[0]) or self.selected(current[1]):
                raise AuditFailure("single selection did not move exclusively to Design")

            self.tap(node_bounds(current[5]))
            multiple = self.dump("02-multiple-docs")
            self.require_text(multiple, "3 filters selected")
            current = self.controls(multiple)
            if not all(self.selected(current[i]) for i in (3, 4, 5)):
                raise AuditFailure("multiple selection did not retain and add values")

            self.tap(node_bounds(current[8]))
            vertical = self.dump("03-vertical-docs")
            self.require_text(vertical, "Selected docs")
            current = self.controls(vertical)
            if not self.selected(current[8]) or self.selected(current[7]):
                raise AuditFailure("vertical group selection did not move to Documentation")

            self.tap(node_bounds(current[9]))
            disabled = self.dump("04-disabled-inert")
            if any(self.selected(self.controls(disabled)[i]) for i in (9, 11)):
                raise AuditFailure("disabled group changed after a real tap")
            self.screenshot("04-interacted")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("05-font-scale-130")
            if any(node_bounds(item).height <= 0 for item in self.controls(scaled)):
                raise AuditFailure("a chip collapsed at 130 percent font scale")
            self.screenshot("05-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.launch()
            stress = self.controls(self.dump("06-stress-before"))
            point = node_bounds(stress[5]).center
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for _ in range(20):
                self.shell("input", "tap", str(point[0]), str(point[1]))
                time.sleep(0.08)
            time.sleep(0.4)
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            match = re.search(r"99th percentile:\s*(\d+)ms", gfx)
            p99 = int(match.group(1)) if match else -1
            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or "ANR in dev.pam.mobileui.catalog" in logs:
                raise AuditFailure("runtime errors found in logcat")

            checks = {
                "singleSelection": True,
                "multipleSelection": True,
                "verticalIntrinsicGeometry": True,
                "selectedVisualState": True,
                "disabledInertia": True,
                "fontScale130": True,
                "twentyTapStress": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-chip-group",
                "device": self.serial,
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
    parser = argparse.ArgumentParser(description="Audit p-chip-group on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-chip-group-audit"))
    args = parser.parse_args()
    report = ChipGroupAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-chip-group; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
