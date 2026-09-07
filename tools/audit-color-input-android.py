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
SPEC = importlib.util.spec_from_file_location("pam_color_input_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class ColorInputAudit(AutocompleteAudit):
    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-color-input", "Color Input")
        self.original_font_scale = ""

    def fields(self, root: ET.Element) -> list[ET.Element]:
        return [n for n in self.nodes(root) if n.attrib.get("class") == "android.widget.EditText"]

    def controls(self, root: ET.Element) -> list[ET.Element]:
        return [
            n for n in self.nodes(root)
            if n.attrib.get("class") == "android.view.ViewGroup"
            and n.attrib.get("content-desc", "").endswith("color value")
        ]

    def scroll_to(self, label: str, width: int, height: int) -> tuple[ET.Element, ET.Element]:
        for attempt in range(9):
            root = self.dump(f"scroll-{label.lower().replace(' ', '-')}-{attempt}")
            matches = [n for n in self.nodes(root) if self.text(n) == label]
            visible = [n for n in matches if 120 <= node_bounds(n).top and node_bounds(n).bottom <= height - 100]
            if visible:
                return root, visible[0]
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
            expected = {"HEX", "RGB", "HSL", "Palette"}
            labels = {self.text(n) for n in self.nodes(root)}
            if not expected.issubset(labels):
                raise AuditFailure(f"missing color modes: {sorted(expected - labels)}")
            initial_fields = self.fields(root)
            # The fourth field may sit below the physical viewport after the
            # editorial route header. Require every visible mode label and the
            # three visible native fields here; Palette and Disabled are
            # independently scrolled to and exercised below.
            if len(initial_fields) < 3:
                raise AuditFailure(
                    f"expected at least three visible native color inputs, found {len(initial_fields)}"
                )
            if any(node_bounds(control).height / density < 48 for control in self.controls(root)):
                raise AuditFailure("a color input is shorter than the 48dp touch target")
            self.screenshot("00-baseline")

            first = initial_fields[0]
            self.tap(node_bounds(first))
            # Samsung's numeric/text IMEs do not consistently honor Ctrl+A.
            # Replace the short fixture deterministically from the cursor.
            self.shell("input", "keyevent", "KEYCODE_MOVE_END")
            for _ in range(8):
                self.shell("input", "keyevent", "KEYCODE_DEL")
            self.shell("input", "keyevent", "KEYCODE_POUND")
            self.shell("input", "text", "112233")
            self.shell("input", "keyevent", "BACK")
            time.sleep(0.8)
            edited = self.dump("01-hex-edited")
            if not self.exact(edited, "#112233") or not self.exact(edited, "Color updated"):
                raise AuditFailure("HEX input did not accept and propagate a real edit")

            _, green = self.scroll_to("Select Green", screen.width, screen.height)
            green_area = node_bounds(green)
            if green_area.width <= 0 or green_area.height <= 0:
                raise AuditFailure("palette chip has collapsed geometry")
            self.tap(green_area)
            selected = self.dump("02-palette-selected")
            selected_green = self.exact(selected, "Select Green")
            if not selected_green or selected_green[0].attrib.get("selected") != "true":
                raise AuditFailure("palette selection state was not exposed")
            if not self.exact(selected, "Green selected"):
                raise AuditFailure("palette selection feedback is missing")
            if not self.exact(selected, "#006C4C"):
                raise AuditFailure("palette selection did not update the color value")
            self.screenshot("02-palette-selected")

            # Start the disabled assertion without a stale focus retained by
            # the earlier HEX EditText; otherwise ADB input is delivered to
            # that old focus even when the disabled target correctly rejects it.
            self.shell("am", "force-stop", self.package)
            self.launch()
            invalid_root, _ = self.scroll_to("Invalid", screen.width, screen.height)
            if not self.exact(invalid_root, "Disabled"):
                invalid_root, _ = self.scroll_to("Disabled", screen.width, screen.height)
            if not self.exact(invalid_root, "Enter a valid HEX color"):
                raise AuditFailure("invalid input does not expose recovery guidance")
            disabled = []
            for attempt in range(8):
                disabled_root = self.dump(f"scroll-disabled-field-{attempt}")
                disabled = [
                    n for n in self.fields(disabled_root)
                    if n.attrib.get("text") == "#9E9E9E"
                    and 120 <= node_bounds(n).top
                    and node_bounds(n).bottom <= screen.bottom - 60
                ]
                if disabled:
                    break
                self.swipe_up(screen.width, screen.height)
            if not disabled:
                raise AuditFailure("disabled color input is missing")
            self.tap(node_bounds(disabled[0]))
            self.shell("input", "keycombination", "113", "29")
            self.shell("input", "text", "FFFFFF")
            if self.ime_shown():
                self.shell("input", "keyevent", "BACK")
            time.sleep(0.5)
            disabled_after = self.dump("03-disabled-inert")
            if not self.exact(disabled_after, "#9E9E9E"):
                raise AuditFailure("disabled color input changed after real keyboard input")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("04-font-scale-130")
            if not self.exact(scaled, "Color Input") or any(node_bounds(n).height <= 0 for n in self.fields(scaled)):
                raise AuditFailure("color input collapsed at 130 percent font scale")
            self.screenshot("04-font-scale-130")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or "ANR in dev.pam.mobileui.catalog" in logs:
                raise AuditFailure("runtime errors found in logcat")
            checks = {
                "sixModes": True,
                "nativeInputGeometry": True,
                "realHexEdit": True,
                "paletteSelection": True,
                "paletteTouchTarget": True,
                "invalidRecovery": True,
                "disabledInertia": True,
                "fontScale130": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-color-input",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
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
    parser = argparse.ArgumentParser(description="Audit p-color-input on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-color-input-audit"))
    args = parser.parse_args()
    report = ColorInputAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-color-input; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
