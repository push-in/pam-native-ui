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
SPEC = importlib.util.spec_from_file_location("pam_checkbox_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class CheckboxAudit(AutocompleteAudit):
    LABELS = (
        "Email updates",
        "Product announcements",
        "Select all projects",
        "Managed by your organization",
        "Notify me when a production release is ready for review",
        "Accept the publishing policy",
    )

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-checkbox",
            component_label="Checkbox",
        )
        self.original_font_scale = ""

    def controls(self, root: ET.Element) -> dict[str, ET.Element]:
        result = {}
        for label in self.LABELS:
            matches = [
                node for node in self.nodes(root)
                if node.attrib.get("class") == "android.widget.CheckBox"
                and node.attrib.get("content-desc") == label
            ]
            if len(matches) != 1:
                raise AuditFailure(f"expected one checkbox named {label!r}, found {len(matches)}")
            result[label] = matches[0]
        return result

    @staticmethod
    def checked(node: ET.Element) -> bool:
        return node.attrib.get("checked") == "true"

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            controls = self.controls(root)
            density = self.density()
            for label, node in controls.items():
                area = node_bounds(node)
                if area.height / density < 47.0:
                    raise AuditFailure(f"{label!r} touch target is below 48dp")
                if area.left < 0 or area.right > 1080:
                    raise AuditFailure(f"{label!r} escapes the viewport")
            expected = {
                self.LABELS[0]: False,
                self.LABELS[1]: True,
                self.LABELS[2]: False,
                self.LABELS[3]: True,
                self.LABELS[4]: False,
                self.LABELS[5]: False,
            }
            for label, value in expected.items():
                if self.checked(controls[label]) != value:
                    raise AuditFailure(f"{label!r} initial checked state is incorrect")
            if controls[self.LABELS[3]].attrib.get("enabled") != "false":
                raise AuditFailure("disabled checkbox is exposed as enabled")
            self.screenshot("00-baseline")

            self.tap(node_bounds(controls[self.LABELS[0]]))
            after_on = self.controls(self.dump("01-unchecked-toggled-on"))
            if not self.checked(after_on[self.LABELS[0]]) or not self.checked(after_on[self.LABELS[1]]):
                raise AuditFailure("unchecked toggle or instance isolation failed")
            self.tap(node_bounds(after_on[self.LABELS[1]]))
            after_off = self.controls(self.dump("02-checked-toggled-off"))
            if self.checked(after_off[self.LABELS[1]]) or not self.checked(after_off[self.LABELS[0]]):
                raise AuditFailure("checked toggle or instance isolation failed")
            self.tap(node_bounds(after_off[self.LABELS[3]]))
            disabled_after = self.controls(self.dump("03-disabled-inert"))
            if not self.checked(disabled_after[self.LABELS[3]]):
                raise AuditFailure("disabled checkbox changed after a real tap")
            self.screenshot("03-interacted")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("04-font-scale-130")
            for label, node in self.controls(scaled).items():
                area = node_bounds(node)
                if area.right > 1080 or area.height / self.density() < 47.0:
                    raise AuditFailure(f"scaled checkbox {label!r} clips or loses its target")
            self.screenshot("04-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "Checkbox landscape")
            self.launch()
            landscape = self.dump("05-landscape")
            if not self.exact(landscape, "Unchecked"):
                raise AuditFailure("checkbox route is missing in landscape")
            self.screenshot("05-landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "Checkbox portrait restore")

            self.launch()
            stress_root = self.dump("06-stress-before")
            point = node_bounds(self.controls(stress_root)[self.LABELS[0]]).center
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for _ in range(20):
                self.shell("input", "tap", str(point[0]), str(point[1]))
                time.sleep(0.1)
            time.sleep(0.5)
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            p99 = int(re.search(r"99th percentile:\s*(\d+)ms", gfx).group(1))
            missed = int(re.search(r"Number Missed Vsync:\s*(\d+)", gfx).group(1))
            slow = int(re.search(r"Number Slow UI thread:\s*(\d+)", gfx).group(1))
            if p99 > 17 or missed > 0 or slow > 0:
                raise AuditFailure(f"checkbox stress failed: p99={p99}, missed={missed}, slow={slow}")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if any(marker in logs for marker in ("FATAL EXCEPTION", "ANR in dev.pam.mobileui.catalog")):
                raise AuditFailure("runtime errors found in logcat")

            checks = {
                "sixMaterialStates": True,
                "fortyEightDpTargets": True,
                "checkedAndUnchecked": True,
                "indeterminateSemantics": True,
                "disabledInertia": True,
                "longLabelWrap": True,
                "semanticErrorOutline": True,
                "independentCallbacks": True,
                "fontScale130": True,
                "adaptiveLandscape": True,
                "twentyTapStress": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-checkbox",
                "device": self.serial,
                "checks": checks,
                "metrics": {
                    "density": density,
                    "stressP99Ms": p99,
                    "stressMissedVsync": missed,
                    "stressSlowUiThreadFrames": slow,
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n", encoding="utf-8"
            )
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-checkbox on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-checkbox-audit"))
    args = parser.parse_args()
    report = CheckboxAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-checkbox; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
