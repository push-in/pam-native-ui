#!/usr/bin/env python3

from __future__ import annotations

import argparse
import importlib.util
import json
import sys
from pathlib import Path

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_empty_state_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class EmptyStateAudit(AutocompleteAudit):
    PROFILES = {
        "interactive": ("Default", "Nothing here yet", "Create item", 264.0),
        "search": ("No Results", "No matching results", "Clear filters", 264.0),
        "offline": ("Offline", "You are offline", "Try again", 264.0),
        "permission": ("Permission", "Access required", "Review access", 264.0),
        "compact": ("Compact", "Inbox clear", "Refresh", 200.0),
    }

    def __init__(self, serial: str, package: str, activity: str, output: Path) -> None:
        super().__init__(serial, package, activity, output, "p-empty-state", "Empty State")
        self.original_font_scale = ""

    def summary(self, root, title: str):
        matches = [
            node for node in self.nodes(root)
            if node.attrib.get("content-desc", "").startswith(title + ". ")
            and node_bounds(node).height > 0
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one semantic summary for {title!r}, found {len(matches)}")
        return matches[0]

    def action(self, root, label: str):
        matches = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Button"
            and node.attrib.get("content-desc") == label
            and node.attrib.get("clickable") == "true"
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one enabled action {label!r}, found {len(matches)}")
        return matches[0]

    def open_profile(self, scenario: str, evidence: str, validate_height: bool = True):
        label, title, action, expected_height = self.PROFILES[scenario]
        self.launch(scenario)
        root = self.dump(evidence)
        if not self.exact(root, label) or not self.exact(root, title):
            raise AuditFailure(f"{scenario} empty-state copy is missing")
        summary = self.summary(root, title)
        summary_area = node_bounds(summary)
        density = self.density()
        actual_height = summary_area.height / density
        valid_height = (
            200.0 <= actual_height < 250.0
            if scenario == "compact"
            else abs(actual_height - expected_height) <= 1.5
        )
        if validate_height and not valid_height:
            raise AuditFailure(
                f"{scenario} summary height is {actual_height:.1f}dp; "
                f"expected {'200-250dp' if scenario == 'compact' else f'{expected_height:.0f}dp'}"
            )
        if summary.attrib.get("clickable") == "true":
            raise AuditFailure(f"{scenario} summary incorrectly exposes a click action")
        button = self.action(root, action)
        button_area = node_bounds(button)
        if not 39.0 <= button_area.height / density <= 41.0:
            raise AuditFailure(f"{scenario} action is not a 40dp Material button")
        if button_area.left < summary_area.left or button_area.right > summary_area.right:
            raise AuditFailure(f"{scenario} action overflows its summary")
        self.screenshot(evidence)
        return root, summary_area, button_area

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            metrics: dict[str, object] = {"density": self.density(), "profiles": {}}
            density = self.density()
            default_summary = None
            for offset, scenario in enumerate(self.PROFILES):
                root, summary, button = self.open_profile(scenario, f"{offset:02d}-{scenario}")
                metrics["profiles"][scenario] = {
                    "summaryHeightDp": summary.height / density,
                    "buttonWidthDp": button.width / density,
                    "buttonHeightDp": button.height / density,
                }
                if scenario == "interactive":
                    default_summary = summary
                    # The visual container is 40dp, while the renderer adds an
                    # invisible 4dp hit expansion on each vertical edge. A tap
                    # 3dp below the visible button must still activate it.
                    self.tap((button.center[0], button.bottom + round(3 * density)))
                    activated = self.dump("01-activated")
                    if not self.exact(activated, "Ready to go"):
                        raise AuditFailure("expanded 48dp action target did not activate")
                    if not self.action(activated, "Do it again"):
                        raise AuditFailure("activated empty state lost its follow-up action")
                    self.screenshot("01-activated")

            if default_summary is None:
                raise AuditFailure("default summary geometry was not captured")
            with Image.open(self.output / "00-interactive.png").convert("RGB") as image:
                # The summary itself must remain transparent: left-side pixels
                # inside and immediately below its measured bounds must match
                # the page canvas at every Android density.
                sample_x = default_summary.left + 5
                inside = image.getpixel((sample_x, default_summary.top + 20))
                outside = image.getpixel((
                    sample_x,
                    min(image.height - 2, default_summary.bottom + 20),
                ))
            if max(abs(a - b) for a, b in zip(inside, outside, strict=True)) > 3:
                raise AuditFailure(f"empty state introduced a decorative outer card: {outside} -> {inside}")

            self.set_setting("system", "font_scale", "1.3")
            scaled, scaled_summary, scaled_button = self.open_profile(
                "interactive", "05-font-scale-130"
            )
            if scaled_button.bottom > scaled_summary.bottom:
                raise AuditFailure("130 percent font scale clipped the empty-state action")
            if not self.exact(scaled, "Create the first item to get started."):
                raise AuditFailure("supporting copy disappeared at 130 percent font scale")
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "empty state landscape")
            landscape, area, action = self.open_profile(
                "interactive", "06-landscape", validate_height=False
            )
            width, height = self.screenshot("06-landscape-verified")
            if area.right > width or area.bottom > height or action.bottom > height:
                raise AuditFailure("empty state clipped in landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "empty state portrait restore")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if "FATAL EXCEPTION" in logs or f"ANR in {self.package}" in logs:
                raise AuditFailure("runtime crash or ANR found in logcat")
            checks = {
                "fiveProfiles": True,
                "semanticSummary": True,
                "directCanvas": True,
                "materialGeometry": True,
                "expanded48DpTarget": True,
                "controlledActionState": True,
                "compactDensity": True,
                "fontScale130": True,
                "landscape": True,
                "runtimeLog": True,
            }
            report = {
                "schemaVersion": 2,
                "component": "p-empty-state",
                "device": self.serial,
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
    parser = argparse.ArgumentParser(description="Audit p-empty-state on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-empty-state-audit"))
    args = parser.parse_args()
    report = EmptyStateAudit(args.serial, args.package, args.activity, args.output).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-empty-state; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
