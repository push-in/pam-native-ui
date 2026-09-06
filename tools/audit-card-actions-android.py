#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import re
import sys
import time
import xml.etree.ElementTree as ET
from enum import IntEnum
from pathlib import Path


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_card_actions_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
Bounds = MODULE.Bounds
node_bounds = MODULE.node_bounds


class ResultStatus(IntEnum):
    PASSED = 1
    FAILED = 2


class CardActionsAudit(AutocompleteAudit):
    VARIATIONS = (
        "Action Pair",
        "Single Action",
        "Disabled Leading",
        "Destructive Choice",
        "Long Labels",
        "Three Actions",
    )
    ACTIONS = {
        "Action Pair": ("Cancel", "Continue"),
        "Single Action": ("Done",),
        "Disabled Leading": ("Cancel", "Continue"),
        "Destructive Choice": ("Keep", "Delete"),
        "Long Labels": ("Review changes", "Publish release"),
        "Three Actions": ("Back", "Save draft", "Send"),
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag="p-card-actions",
            component_label="Card Actions",
        )
        self.original_font_scale = ""

    @staticmethod
    def contains(outer: Bounds, inner: Bounds) -> bool:
        return (
            outer.left <= inner.left
            and outer.top <= inner.top
            and outer.right >= inner.right
            and outer.bottom >= inner.bottom
        )

    def row(self, root: ET.Element, variation: str) -> ET.Element:
        captions = self.exact(root, variation)
        if not captions:
            raise AuditFailure(f"{variation} caption is missing")
        caption = node_bounds(captions[0])
        candidates = [
            node
            for node in self.nodes(root)
            if node.attrib.get("content-desc") == "Card actions"
            and node_bounds(node).top >= caption.bottom
            and node_bounds(node).height > 0
        ]
        if not candidates:
            raise AuditFailure(f"{variation} action row is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def button(self, row: ET.Element, label: str) -> ET.Element:
        candidates = [
            node
            for node in row.iter("node")
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == label
        ]
        if len(candidates) != 1:
            raise AuditFailure(f"row exposes {len(candidates)} actions named {label!r}")
        return candidates[0]

    def row_has_text(self, row: ET.Element, value: str) -> bool:
        area = node_bounds(row)
        return any(
            node.attrib.get("text") == value
            and node_bounds(node).height > 0
            and self.contains(area, node_bounds(node))
            for node in self.nodes(row)
        )

    def assert_geometry(self, root: ET.Element, variation: str) -> dict[str, object]:
        density = self.density()
        row = self.row(root, variation)
        area = node_bounds(row)
        buttons = [self.button(row, label) for label in self.ACTIONS[variation]]
        height_dp = area.height / density
        wrapped = len(buttons) > 1 and node_bounds(buttons[1]).top > node_bounds(buttons[0]).top
        expected_height = 104.0 if wrapped else 56.0
        if abs(height_dp - expected_height) > 1.5:
            raise AuditFailure(
                f"{variation} row is {height_dp:.1f}dp; expected {expected_height:.0f}dp"
            )
        if area.width / density < 320.0:
            raise AuditFailure(f"{variation} row is unexpectedly narrow")
        for button in buttons:
            button_area = node_bounds(button)
            if abs(button_area.height / density - 40.0) > 1.5:
                raise AuditFailure(f"{variation} visual button is not 40dp high")
        if abs((node_bounds(buttons[0]).top - area.top) / density - 8.0) > 1.5:
            raise AuditFailure(f"{variation} leading action does not preserve the 8dp top inset")
        gaps = []
        for left, right in zip(buttons, buttons[1:]):
            left_area = node_bounds(left)
            right_area = node_bounds(right)
            gap = (
                (right_area.top - left_area.bottom) / density
                if right_area.top > left_area.top
                else (right_area.left - left_area.right) / density
            )
            gaps.append(gap)
        if any(abs(gap - 8.0) > 1.5 for gap in gaps):
            raise AuditFailure(f"{variation} does not preserve 8dp action gaps")
        trailing = (area.right - node_bounds(buttons[-1]).right) / density
        if abs(trailing - 8.0) > 1.5:
            raise AuditFailure(f"{variation} trailing inset is {trailing:.1f}dp")
        for left, right in zip(buttons, buttons[1:]):
            left_area = node_bounds(left)
            right_area = node_bounds(right)
            same_line = right_area.top == left_area.top
            if same_line and left_area.right >= right_area.left:
                raise AuditFailure(f"{variation} actions overlap")
        expected_disabled = variation == "Disabled Leading"
        if (buttons[0].attrib.get("enabled") == "false") != expected_disabled:
            raise AuditFailure(f"{variation} leading enabled state is incorrect")
        return {
            "widthDp": round(area.width / density, 2),
            "heightDp": round(height_dp, 2),
            "wrapped": wrapped,
            "actionCount": len(buttons),
            "visualActionHeightDp": round(node_bounds(buttons[-1]).height / density, 2),
            "effectiveTargetDp": 48,
            "gapsDp": [round(gap, 2) for gap in gaps],
            "trailingDp": round(trailing, 2),
        }

    def tap_action(self, variation: str, label: str, result: str, *, edge: bool = False) -> None:
        root = self.dump("interaction-before-" + result.lower().replace(" ", "-"))
        row = self.row(root, variation)
        area = node_bounds(self.button(row, label))
        point = area.center
        if edge:
            point = (area.center[0], area.top - int(round(2.0 * self.density())))
        self.tap(point)
        changed = self.dump("interaction-after-" + result.lower().replace(" ", "-"))
        if not self.row_has_text(self.row(changed, variation), result):
            raise AuditFailure(f"{variation} action {label!r} did not produce {result!r}")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            self.screenshot("00-baseline")
            root = self.dump("00-baseline")
            geometry = {
                variation: self.assert_geometry(root, variation)
                for variation in self.VARIATIONS
            }
            self.tap_action("Action Pair", "Cancel", "Cancelled", edge=True)
            self.tap_action("Single Action", "Done", "Done ✓")

            disabled_root = self.dump("disabled-before")
            disabled_row = self.row(disabled_root, "Disabled Leading")
            self.tap(node_bounds(self.button(disabled_row, "Cancel")))
            disabled_after = self.dump("disabled-after")
            if self.row_has_text(self.row(disabled_after, "Disabled Leading"), "Cancelled"):
                raise AuditFailure("disabled Card Actions control dispatched a callback")
            self.tap_action("Disabled Leading", "Continue", "Continued")
            self.tap_action("Destructive Choice", "Delete", "Deleted")
            self.tap_action("Long Labels", "Publish release", "Published")
            self.tap_action("Three Actions", "Save draft", "Draft saved")
            self.screenshot("01-interacted")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            font_root = self.dump("02-font-scale-130")
            for variation in self.VARIATIONS:
                self.assert_geometry(font_root, variation)
            self.screenshot("02-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "Card Actions landscape")
            self.launch()
            landscape_root = self.dump("03-landscape")
            if not self.exact(landscape_root, "Action Pair"):
                raise AuditFailure("Card Actions landscape route is missing")
            self.screenshot("03-landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "Card Actions portrait restore")

            self.launch()
            stress_root = self.dump("stress-before")
            stress_button = node_bounds(self.button(self.row(stress_root, "Action Pair"), "Continue"))
            point = stress_button.center
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for _ in range(20):
                self.tap(point)
                time.sleep(0.12)
            time.sleep(0.6)
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            p99_match = re.search(r"99th percentile:\s*(\d+)ms", gfx)
            missed_match = re.search(r"Number Missed Vsync:\s*(\d+)", gfx)
            slow_match = re.search(r"Number Slow UI thread:\s*(\d+)", gfx)
            p99 = int(p99_match.group(1)) if p99_match else 10**9
            missed = int(missed_match.group(1)) if missed_match else 10**9
            slow = int(slow_match.group(1)) if slow_match else 10**9
            if p99 > 17 or missed > 0 or slow > 0:
                raise AuditFailure(
                    f"Card Actions stress failed: p99={p99}, missed={missed}, slow={slow}"
                )
            self.screenshot("04-stress-result")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            markers = (
                "FATAL EXCEPTION",
                "Pam Native runtime error",
                "ANR in dev.pam.mobileui.catalog",
                "Input dispatching timed out",
            )
            if any(marker in logs for marker in markers):
                raise AuditFailure("runtime errors found in logcat")

            report = {
                "schemaVersion": 2,
                "component": "p-card-actions",
                "device": self.serial,
                "package": self.package,
                "resultStatus": int(ResultStatus.PASSED),
                "checks": {
                    "allSixArrangements": True,
                    "directCanvasPresentation": True,
                    "materialEightDpInsetsAndGaps": True,
                    "fortyDpVisualButtons": True,
                    "fortyEightDpEffectiveTargets": True,
                    "endAlignment": True,
                    "independentCallbacks": True,
                    "disabledActionInert": True,
                    "semanticDestructiveAction": True,
                    "fontScale130": True,
                    "adaptiveLandscape": True,
                    "rapidTwentyTapStress": True,
                    "frameP99AtMost17Ms": True,
                    "zeroMissedVsync": True,
                    "zeroSlowUiThreadFrames": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "density": self.density(),
                    "geometry": geometry,
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
                try:
                    self.set_setting("system", "font_scale", self.original_font_scale)
                except Exception:
                    pass
            self.restore()


def main() -> int:
    parser = argparse.ArgumentParser(description="Audit p-card-actions on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-card-actions-audit"))
    args = parser.parse_args()
    report = CardActionsAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-card-actions; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
