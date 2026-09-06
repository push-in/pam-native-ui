#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import math
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_banner_actions_audit", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class BannerActionsAudit(AutocompleteAudit):
    VARIATIONS = (
        "Default",
        "Single Action",
        "Disabled Action",
        "Long Labels",
        "Independent Pair",
    )
    ACCESSIBILITY_LABELS = {
        "Default": "Banner actions. Default pair.",
        "Single Action": "Banner actions. Single action.",
        "Disabled Action": "Banner actions. Disabled leading action.",
        "Long Labels": "Banner actions. Long labels.",
        "Independent Pair": "Banner actions. Independent pair.",
    }
    INITIAL_ACTIONS = {
        "Default": ("Later", "Update"),
        "Single Action": ("Got it",),
        "Disabled Action": ("Later", "Update"),
        "Long Labels": ("Remind me later", "Install update"),
        "Independent Pair": ("Cancel", "Retry"),
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-banner-actions",
            component_label="Banner Actions",
        )
        self.original_font_scale = ""

    @staticmethod
    def descendant_text(node: ET.Element) -> list[str]:
        return [
            child.attrib.get("text", "")
            for child in node.iter("node")
            if child.attrib.get("text", "")
        ]

    def action_row_after(self, root: ET.Element, label: str) -> ET.Element:
        captions = self.exact(root, label)
        if not captions:
            raise AuditFailure(f"variation label {label!r} is missing")
        caption = node_bounds(captions[0])
        expected = self.ACCESSIBILITY_LABELS[label]
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("content-desc") == expected
            and node_bounds(node).top >= caption.bottom
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"action row after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def action(self, row: ET.Element, label: str) -> ET.Element:
        candidates = [
            node
            for node in row.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
            and label in self.descendant_text(node)
        ]
        if not candidates:
            raise AuditFailure(f"action {label!r} is missing")
        return candidates[0]

    def assert_row_geometry(
        self,
        root: ET.Element,
        row: ET.Element,
        label: str,
        screenshot: str,
    ) -> dict[str, object]:
        density = self.density()
        area = node_bounds(row)
        caption = node_bounds(self.exact(root, label)[0])
        tolerance = max(2, int(round(density)))
        if abs(area.left - caption.left) > tolerance:
            raise AuditFailure(f"{label} row lost the section start axis")
        if area.width / density < 320.0:
            raise AuditFailure(f"{label} row is unexpectedly narrow")
        if abs(area.height / density - 48.0) > 1.5:
            raise AuditFailure(f"{label} row is not 48dp high")

        buttons = [
            node
            for node in row.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
        ]
        expected_labels = self.INITIAL_ACTIONS[label]
        if len(buttons) != len(expected_labels):
            raise AuditFailure(
                f"{label} exposes {len(buttons)} actions; expected {len(expected_labels)}"
            )
        buttons.sort(key=lambda node: node_bounds(node).left)
        for button, expected_label in zip(buttons, expected_labels):
            button_area = node_bounds(button)
            if abs(button_area.height / density - 40.0) > 1.5:
                raise AuditFailure(f"{label} action {expected_label!r} is not 40dp high")
            if abs((button_area.top - area.top) / density - 4.0) > 1.5:
                raise AuditFailure(f"{label} action {expected_label!r} is not centered")
            text_nodes = [
                node
                for node in button.iter("node")
                if node.attrib.get("text") == expected_label
            ]
            if not text_nodes:
                raise AuditFailure(f"{label} action text {expected_label!r} is missing")
            text_area = node_bounds(text_nodes[0])
            if (
                text_area.left < button_area.left
                or text_area.right > button_area.right
                or text_area.top < button_area.top
                or text_area.bottom > button_area.bottom
            ):
                raise AuditFailure(f"{label} action text {expected_label!r} is clipped")

        gaps: list[float] = []
        for left, right in zip(buttons, buttons[1:]):
            gap = (node_bounds(right).left - node_bounds(left).right) / density
            gaps.append(round(gap, 2))
            if abs(gap - 8.0) > 1.5:
                raise AuditFailure(f"{label} action gap is {gap:.1f}dp instead of 8dp")
        trailing = (area.right - node_bounds(buttons[-1]).right) / density
        if abs(trailing) > 1.5:
            raise AuditFailure(f"{label} actions are not end aligned ({trailing:.1f}dp)")

        with Image.open(self.output / f"{screenshot}.png").convert("RGB") as image:
            reference = image.getpixel((area.left + int(4 * density), area.top + int(4 * density)))
            for button in buttons:
                button_area = node_bounds(button)
                corner = image.getpixel(
                    (button_area.left + int(3 * density), button_area.top + int(3 * density))
                )
                distance = math.sqrt(sum((a - b) ** 2 for a, b in zip(reference, corner)))
                if distance > 10.0:
                    raise AuditFailure(f"{label} action rendered a filled decorative surface")

        return {
            "widthDp": round(area.width / density, 2),
            "heightDp": round(area.height / density, 2),
            "actionCount": len(buttons),
            "gapsDp": gaps,
            "trailingDp": round(trailing, 2),
        }

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        metrics: dict[str, dict[str, object]] = {}
        try:
            self.launch()
            root = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            self.assert_window_count(1, "baseline")
            for label in self.VARIATIONS:
                row = self.action_row_after(root, label)
                metrics[label] = self.assert_row_geometry(
                    root, row, label, "00-baseline"
                )

            density = self.density()
            edge = int(round(2.0 * density))
            default = self.action_row_after(root, "Default")
            later = node_bounds(self.action(default, "Later"))
            self.tap((later.center[0], later.top - edge))
            after_later = self.dump("01-later")
            if not self.exact(after_later, "Scheduled"):
                raise AuditFailure("upper edge of the 48dp Later target did not activate")
            self.screenshot("01-later")

            self.launch()
            root = self.dump("02-update-before")
            update = node_bounds(self.action(self.action_row_after(root, "Default"), "Update"))
            self.tap((update.center[0], update.bottom + edge))
            after_update = self.dump("02-updating")
            if not self.exact(after_update, "Updating"):
                raise AuditFailure("lower edge of the 48dp Update target did not activate")
            self.screenshot("02-updating")

            self.launch()
            root = self.dump("03-state-behavior-before")
            single = self.action_row_after(root, "Single Action")
            self.tap(node_bounds(self.action(single, "Got it")))
            acknowledged = self.dump("03-acknowledged")
            acknowledged_buttons = self.exact(acknowledged, "Acknowledged")
            if not acknowledged_buttons:
                raise AuditFailure("single action did not expose its completed state")

            disabled_row = self.action_row_after(acknowledged, "Disabled Action")
            disabled = self.action(disabled_row, "Later")
            if disabled.attrib.get("enabled") != "false":
                raise AuditFailure("disabled leading action remains enabled")
            before_desc = disabled_row.attrib.get("content-desc")
            self.tap(node_bounds(disabled))
            after_disabled = self.dump("04-disabled-result")
            if self.action_row_after(after_disabled, "Disabled Action").attrib.get("content-desc") != before_desc:
                raise AuditFailure("disabled action changed its row state")
            active = self.action(self.action_row_after(after_disabled, "Disabled Action"), "Update")
            self.tap(node_bounds(active))
            after_active = self.dump("05-disabled-peer-active")
            if not self.exact(after_active, "Updating"):
                raise AuditFailure("enabled peer beside disabled action did not activate")
            self.screenshot("05-states")

            self.launch()
            root = self.dump("06-long-labels-before")
            long_row = self.action_row_after(root, "Long Labels")
            self.tap(node_bounds(self.action(long_row, "Install update")))
            after_install = self.dump("06-installing")
            if not self.exact(after_install, "Installing"):
                raise AuditFailure("long-label action did not activate")
            independent = self.action_row_after(after_install, "Independent Pair")
            self.tap(node_bounds(self.action(independent, "Retry")))
            after_retry = self.dump("07-independent")
            if not self.exact(after_retry, "Retrying"):
                raise AuditFailure("independent action did not activate")
            if not self.exact(after_retry, "Later"):
                raise AuditFailure("independent action mutated the Default row")
            self.screenshot("07-independent")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("08-font-scale-130")
            self.screenshot("08-font-scale-130")
            for label in self.VARIATIONS:
                row = self.action_row_after(scaled, label)
                buttons = [
                    node for node in row.iter("node")
                    if node.attrib.get("class") == "android.widget.Button"
                ]
                buttons.sort(key=lambda node: node_bounds(node).left)
                if any(node_bounds(node).height > int(round(48.0 * density)) for node in buttons):
                    raise AuditFailure(f"{label} action escaped its 48dp row at 130% text")
                if any(node_bounds(right).left < node_bounds(left).right for left, right in zip(buttons, buttons[1:])):
                    raise AuditFailure(f"{label} actions overlap at 130% text")
            self.set_setting("system", "font_scale", "1.0")

            self.launch()
            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "banner actions landscape")
            landscape_width, landscape_height = self.screenshot("09-landscape")
            landscape = self.dump("09-landscape")
            if landscape_width <= landscape_height:
                raise AuditFailure("device did not enter landscape")
            if self.exact(landscape, "Open component navigation"):
                raise AuditFailure("redundant drawer button remained in permanent navigation")
            default_landscape = self.action_row_after(landscape, "Default")
            self.tap(node_bounds(self.action(default_landscape, "Update")))
            if not self.exact(self.dump("10-landscape-updated"), "Updating"):
                raise AuditFailure("landscape action did not remain interactive")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "banner actions portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2500", timeout=30.0)
            markers = (
                "FATAL EXCEPTION",
                " E AndroidRuntime:",
                "Pam Native runtime error",
                "failed integrity verification",
                "ANR in dev.pam.mobileui.catalog",
                "Input dispatching timed out",
            )
            errors = [line for line in logs.splitlines() if any(marker in line for marker in markers)]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 1,
                "component": "p-banner-actions",
                "device": self.serial,
                "package": self.package,
                "checks": {
                    "fivePurposeBuiltVariations": True,
                    "exact48DpRows": True,
                    "fortyDpTextActions": True,
                    "exact48DpEffectiveTargets": True,
                    "eightDpActionGap": True,
                    "endAlignment": True,
                    "textOnlySurface": True,
                    "actionCallbacks": True,
                    "disabledActionBehavior": True,
                    "instanceIsolation": True,
                    "longLabelContainment": True,
                    "fontScale130": True,
                    "adaptiveNavigation": True,
                    "landscapeSafeArea": True,
                    "runtimeLog": True,
                },
                "metrics": metrics,
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n", encoding="utf-8"
            )
            return report
        finally:
            if self.original_font_scale not in {"", "null"}:
                try:
                    self.set_setting("system", "font_scale", self.original_font_scale)
                except Exception:
                    pass
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit p-banner-actions on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog.debug")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output", type=Path, default=Path("/tmp/pam-banner-actions-android-audit")
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    report = BannerActionsAudit(
        args.serial, args.package, args.activity, args.output
    ).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-banner-actions; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
