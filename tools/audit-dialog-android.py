#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

from PIL import Image, ImageStat


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_dialog_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class DialogAudit(AutocompleteAudit):
    PROFILES = {
        "default": ("Default", "Open dialog"),
        "persistent": ("Persistent", "Open persistent dialog"),
        "no-scrim": ("No Scrim", "Open dialog without scrim"),
        "fullscreen": ("Fullscreen", "Open fullscreen dialog"),
        "compact": ("Width Small", "Open compact dialog"),
        "large": ("Width Large", "Open large dialog"),
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-dialog", "Dialog")
        self.original_font_scale = ""

    def button_with_text(self, root: ET.Element, text: str) -> ET.Element:
        matches = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("clickable") == "true"
            and any(desc.attrib.get("text") == text for desc in node.iter())
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one clickable button containing {text!r}, found {len(matches)}")
        return matches[0]

    def dialog(self, root: ET.Element) -> ET.Element:
        matches = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.app.Dialog"
            and node.attrib.get("content-desc") == "Dialog example"
            and node_bounds(node).height > 0
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one native dialog surface, found {len(matches)}")
        return matches[0]

    def action(self, root: ET.Element, label: str) -> ET.Element:
        matches = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == label
            and node.attrib.get("clickable") == "true"
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one dialog action {label!r}, found {len(matches)}")
        return matches[0]

    def open_profile(self, scenario: str, evidence: str) -> tuple[ET.Element, Bounds, Path]:
        label, trigger_text = self.PROFILES[scenario]
        self.launch(scenario)
        closed = self.dump(f"{evidence}-closed")
        if not self.exact(closed, label):
            raise AuditFailure(f"dialog scenario {scenario!r} did not expose label {label!r}")
        trigger = self.button_with_text(closed, trigger_text)
        density = self.density()
        trigger_area = node_bounds(trigger)
        if trigger_area.height / density < 40.0:
            raise AuditFailure(f"{scenario} trigger collapsed below its 40dp visual container")
        before = self.output / f"{evidence}-before.png"
        self.screenshot(f"{evidence}-before")
        self.tap(trigger_area)
        self.assert_window_count(2, f"{scenario} open")
        opened = self.dump(evidence)
        image = self.output / f"{evidence}.png"
        self.screenshot(evidence)
        area = node_bounds(self.dialog(opened))
        keep = node_bounds(self.action(opened, "Keep draft"))
        discard = node_bounds(self.action(opened, "Discard draft"))
        if not self.exact(opened, "Discard draft?") or not self.exact(
            opened, "Unsaved changes will be removed from this device."
        ):
            raise AuditFailure(f"{scenario} dialog lost title or supporting text")
        if keep.left < area.left or discard.right > area.right or keep.right > discard.left:
            raise AuditFailure(f"{scenario} actions overflow or overlap: {keep}, {discard}")
        if (discard.left - keep.right) / density < 7.0:
            raise AuditFailure(f"{scenario} action spacing is below the 8dp Material grid")
        return opened, area, before

    @staticmethod
    def median(path: Path, x: int, y: int, radius: int = 5) -> tuple[int, int, int]:
        with Image.open(path).convert("RGB") as image:
            crop = image.crop((x - radius, y - radius, x + radius + 1, y + radius + 1))
            return tuple(round(value) for value in ImageStat.Stat(crop).median[:3])

    def assert_closed(self, context: str) -> None:
        self.assert_window_count(1, context)
        root = self.dump(context.replace(" ", "-"))
        if self.exact(root, "Discard draft?"):
            raise AuditFailure(f"{context}: dialog content remained visible")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        metrics: dict[str, object] = {"density": self.density(), "profiles": {}}
        try:
            self.launch()
            width, height = self.screenshot("device-precondition")
            density = self.density()

            default, default_area, default_before = self.open_profile("default", "00-default")
            metrics["profiles"]["default"] = list(default_area.__dict__.values())
            if default_area.left / density < 8 or (width - default_area.right) / density < 8:
                raise AuditFailure("default dialog violates the minimum horizontal viewport margin")
            self.tap(node_bounds(self.action(default, "Keep draft")))
            self.assert_closed("keep action close")

            default, _, _ = self.open_profile("default", "01-default-discard")
            self.tap(node_bounds(self.action(default, "Discard draft")))
            self.assert_closed("discard action close")

            self.open_profile("default", "02-default-back")
            self.back()
            self.assert_closed("default Back close")

            _, outside_area, _ = self.open_profile("default", "03-default-outside")
            self.tap((max(4, outside_area.left // 2), outside_area.top))
            self.assert_closed("default outside close")

            persistent, persistent_area, _ = self.open_profile("persistent", "04-persistent")
            self.back()
            self.assert_window_count(2, "persistent Back")
            persistent = self.dump("04-persistent-after-back")
            self.tap((max(4, persistent_area.left // 2), persistent_area.top))
            self.assert_window_count(2, "persistent outside tap")
            persistent = self.dump("04-persistent-after-outside")
            self.tap(node_bounds(self.action(persistent, "Discard draft")))
            self.assert_closed("persistent explicit action close")

            _, no_scrim_area, no_scrim_before = self.open_profile("no-scrim", "05-no-scrim")
            sample_x, sample_y = width // 2, max(160, no_scrim_area.top - round(40 * density))
            no_scrim_color = self.median(self.output / "05-no-scrim.png", sample_x, sample_y)
            no_scrim_base = self.median(no_scrim_before, sample_x, sample_y)
            if max(abs(a - b) for a, b in zip(no_scrim_color, no_scrim_base, strict=True)) > 4:
                raise AuditFailure(f"scrim=false still altered the backdrop: {no_scrim_base} -> {no_scrim_color}")
            self.back()

            _, fullscreen_area, _ = self.open_profile("fullscreen", "06-fullscreen")
            if fullscreen_area != Bounds(0, 0, width, height):
                raise AuditFailure(f"fullscreen dialog is not viewport-sized: {fullscreen_area}")
            full_root = self.dump("06-fullscreen-safe-area")
            title_area = node_bounds(self.exact(full_root, "Discard draft?")[0])
            if title_area.top / density < 44.0:
                raise AuditFailure(f"fullscreen title invades the status-bar safe area: {title_area.top / density:.1f}dp")
            self.back()

            _, compact_area, _ = self.open_profile("compact", "07-compact")
            compact_dp = compact_area.width / density
            if not 318 <= compact_dp <= 322:
                raise AuditFailure(f"compact dialog width is {compact_dp:.1f}dp, expected 320dp")
            self.back()

            _, large_area, _ = self.open_profile("large", "08-large")
            if large_area.width <= compact_area.width:
                raise AuditFailure("large dialog did not grow beyond the compact width")
            if large_area.left < 0 or large_area.right > width:
                raise AuditFailure("large dialog escaped the viewport")
            self.back()

            self.set_setting("system", "font_scale", "1.3")
            scaled, scaled_area, _ = self.open_profile("default", "09-font-scale-130")
            if node_bounds(self.action(scaled, "Discard draft")).bottom > scaled_area.bottom:
                raise AuditFailure("dialog actions clipped at 130 percent font scale")
            self.back()
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "dialog landscape")
            landscape, landscape_area, _ = self.open_profile("default", "10-landscape")
            landscape_size = self.screenshot("10-landscape-verified")
            if landscape_area.right > landscape_size[0] or landscape_area.bottom > landscape_size[1]:
                raise AuditFailure("landscape dialog escaped the viewport")
            if node_bounds(self.action(landscape, "Discard draft")).bottom > landscape_area.bottom:
                raise AuditFailure("landscape dialog clipped its primary action")
            self.back()
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "dialog portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2500", timeout=30.0)
            if "FATAL EXCEPTION" in logs or f"ANR in {self.package}" in logs:
                raise AuditFailure("runtime crash or ANR found in logcat")
            metrics["profiles"].update({
                "persistent": list(persistent_area.__dict__.values()),
                "noScrim": list(no_scrim_area.__dict__.values()),
                "fullscreen": list(fullscreen_area.__dict__.values()),
                "compact": list(compact_area.__dict__.values()),
                "large": list(large_area.__dict__.values()),
            })
            checks = {
                "sixProfiles": True,
                "nativeDialogWindow": True,
                "materialGeometry": True,
                "explicitActions": True,
                "backDismissal": True,
                "outsideDismissal": True,
                "persistentInertBackdrop": True,
                "noScrim": True,
                "fullscreenSafeArea": True,
                "adaptiveWidths": True,
                "fontScale130": True,
                "landscape": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-dialog",
                "device": self.serial,
                "package": self.package,
                "resultStatus": 1,
                "checks": checks,
                "metrics": metrics,
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(json.dumps(report, indent=2) + "\n")
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                self.set_setting("system", "font_scale", self.original_font_scale)
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-dialog on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-dialog-audit"))
    args = parser.parse_args()
    report = DialogAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-dialog; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
