#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

from PIL import Image

MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_divider_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class DividerAudit(AutocompleteAudit):
    PROFILES = ("Default", "Inset", "Primary", "Thick", "Secondary", "Vertical")

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-divider", "Divider")
        self.original_font_scale = ""

    def dividers(self, root):
        return [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.FrameLayout"
            and node.attrib.get("content-desc") == "Divider preview"
        ]

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            missing = [label for label in self.PROFILES if not self.exact(root, label)]
            if missing:
                raise AuditFailure(f"divider profiles are missing: {missing}")
            dividers = self.dividers(root)
            if len(dividers) != 6:
                raise AuditFailure(f"expected six semantic dividers, found {len(dividers)}")
            areas = [node_bounds(node) for node in dividers]
            density = self.density()
            widths = [area.width / density for area in areas]
            heights = [area.height / density for area in areas]
            if not 0.8 <= heights[0] <= 1.5:
                raise AuditFailure(f"default divider is not 1dp: {heights[0]:.2f}dp")
            if abs((areas[1].left - areas[0].left) / density - 72) > 1.5:
                raise AuditFailure("inset divider does not begin at the 72dp Material inset")
            if not 3.5 <= heights[3] <= 4.5:
                raise AuditFailure(f"thick divider is not 4dp: {heights[3]:.2f}dp")
            if not 0.8 <= widths[5] <= 1.5 or not 47 <= heights[5] <= 49:
                raise AuditFailure(f"vertical divider geometry is invalid: {widths[5]:.2f}x{heights[5]:.2f}dp")
            if any(node.attrib.get("clickable") == "true" for node in dividers):
                raise AuditFailure("non-interactive divider exposes a click action")
            self.screenshot("00-baseline")

            with Image.open(self.output / "00-baseline.png").convert("RGB") as image:
                colors = [image.getpixel(area.center) for area in areas]
            if colors[0] == colors[2] or colors[2] == colors[4]:
                raise AuditFailure(f"semantic divider colors are not visually distinct: {colors}")
            if colors[0] != colors[1] or colors[0] != colors[3] or colors[0] != colors[5]:
                raise AuditFailure(f"neutral divider colors drift across orientations: {colors}")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("01-font-scale-130")
            if not all(self.exact(scaled, label) for label in self.PROFILES[:4]):
                raise AuditFailure("divider profiles disappeared at 130 percent font scale")
            scaled_dividers = self.dividers(scaled)
            if len(scaled_dividers) < 4 or node_bounds(scaled_dividers[0]).width <= 0:
                raise AuditFailure("divider geometry collapsed at 130 percent font scale")
            self.screenshot("01-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "divider landscape")
            self.launch()
            landscape = self.dump("02-landscape")
            if not self.exact(landscape, "Default") or len(self.dividers(landscape)) < 2:
                raise AuditFailure("divider route did not adapt to landscape")
            self.screenshot("02-landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "divider portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or f"ANR in {self.package}" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "sixProfiles": True,
                "oneDpDefault": True,
                "seventyTwoDpInset": True,
                "fourDpThick": True,
                "verticalGeometry": True,
                "semanticColors": True,
                "nonInteractiveSemantics": True,
                "fontScale130": True,
                "landscape": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-divider",
                "device": self.serial,
                "checks": checks,
                "metrics": {
                    "density": density,
                    "widthsDp": widths,
                    "heightsDp": heights,
                    "colorsRgb": colors,
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-divider on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-divider-audit"))
    args = parser.parse_args()
    report = DividerAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-divider; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
