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

from PIL import Image


MODULE_PATH = Path(__file__).with_name("audit-autocomplete-android.py")
SPEC = importlib.util.spec_from_file_location("pam_carousel_shared", MODULE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError(f"cannot load shared Android audit helpers from {MODULE_PATH}")
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AuditFailure = MODULE.AuditFailure
AutocompleteAudit = MODULE.AutocompleteAudit
node_bounds = MODULE.node_bounds


class CarouselAudit(AutocompleteAudit):
    TITLES = ("Native by design", "Composable", "Accessible")

    def __init__(
        self,
        serial: str,
        package: str,
        activity: str,
        output: Path,
        component_tag: str = "p-carousel",
    ) -> None:
        self.is_item = component_tag == "p-carousel-item"
        super().__init__(
            serial,
            package,
            activity,
            output,
            component_tag=component_tag,
            component_label="Carousel Item" if self.is_item else "Carousel",
        )
        self.original_font_scale = ""

    def carousel(self, root: ET.Element) -> ET.Element:
        matches = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.TabWidget"
            and node.attrib.get("content-desc")
                == ("Carousel item preview" if self.is_item else "Carousel preview")
        ]
        if len(matches) != 1:
            raise AuditFailure(f"expected one visible carousel, found {len(matches)}")
        return matches[0]

    def selected_index(self, root: ET.Element) -> int:
        selected = []
        for index in range(1, 4):
            matches = [
                node for node in self.nodes(root)
                if node.attrib.get("content-desc") == f"Go to slide {index}"
                and node.attrib.get("selected") == "true"
            ]
            if matches:
                selected.append(index)
        if len(selected) != 1:
            raise AuditFailure(f"carousel exposes invalid selected delimiters: {selected}")
        return selected[0]

    def visible_title(self, root: ET.Element) -> str:
        visible = [title for title in self.TITLES if self.exact(root, title)]
        if len(visible) != 1:
            raise AuditFailure(f"expected one visible slide title, found {visible}")
        return visible[0]

    def swipe(self, root: ET.Element, direction: str) -> None:
        area = node_bounds(self.carousel(root))
        cx, cy = area.center
        dx = int(area.width * 0.32)
        dy = int(area.height * 0.32)
        points = {
            "left": (cx + dx, cy, cx - dx, cy),
            "right": (cx - dx, cy, cx + dx, cy),
            "up": (cx, cy + dy, cx, cy - dy),
        }[direction]
        self.shell("input", "swipe", *(str(value) for value in points), "320")
        time.sleep(0.8)

    def assert_rounded_clip(self, screenshot: Path, root: ET.Element) -> None:
        area = node_bounds(self.carousel(root))
        with Image.open(screenshot).convert("RGB") as image:
            corner = image.getpixel((area.left + 2, area.top + 2))
            interior = image.getpixel((area.left + 48, area.top + 48))
        if sum(abs(a - b) for a, b in zip(corner, interior)) < 80:
            raise AuditFailure("carousel children are not clipped to the rounded 24dp surface")

    def run(self) -> dict[str, object]:
        self.original_font_scale = self.setting("system", "font_scale")
        self.set_setting("system", "font_scale", "1.0")
        self.prepare()
        try:
            self.launch()
            root = self.dump("00-baseline")
            area = node_bounds(self.carousel(root))
            density = self.density()
            if abs(area.height / density - 240.0) > 1.5:
                raise AuditFailure(f"carousel height is {area.height / density:.1f}dp")
            if self.selected_index(root) != 1 or self.visible_title(root) != self.TITLES[0]:
                raise AuditFailure("carousel did not start on its first slide")
            self.screenshot("00-baseline")
            self.assert_rounded_clip(self.output / "00-baseline.png", root)

            self.swipe(root, "left")
            second = self.dump("01-slide-2")
            if self.selected_index(second) != 2 or self.visible_title(second) != self.TITLES[1]:
                raise AuditFailure("horizontal swipe did not synchronize slide 2 and its indicator")
            self.screenshot("01-slide-2")
            self.swipe(second, "left")
            third = self.dump("02-slide-3")
            if self.selected_index(third) != 3 or self.visible_title(third) != self.TITLES[2]:
                raise AuditFailure("second horizontal swipe did not select slide 3")
            self.swipe(third, "left")
            wrapped = self.dump("03-continuous-wrap")
            if self.selected_index(wrapped) != 1:
                raise AuditFailure("continuous carousel did not wrap to slide 1")

            delimiter = next(
                node for node in self.nodes(wrapped)
                if node.attrib.get("content-desc") == "Go to slide 3"
            )
            self.tap(node_bounds(delimiter))
            tapped = self.dump("04-delimiter-tap")
            if self.selected_index(tapped) != 3 or self.visible_title(tapped) != self.TITLES[2]:
                raise AuditFailure("delimiter tap did not synchronize content and selection")
            self.screenshot("04-delimiter-tap")

            if not self.is_item:
                self.launch("arrows")
                arrows = self.dump("05-arrows")
                next_arrow = next(
                    node for node in self.nodes(arrows)
                    if node.attrib.get("content-desc") == "Next slide"
                )
                self.tap(node_bounds(next_arrow))
                arrow_result = self.dump("05-arrow-result")
                if self.visible_title(arrow_result) != self.TITLES[1]:
                    raise AuditFailure("next arrow did not activate slide 2")
                self.screenshot("05-arrow-result")

                self.launch("vertical")
                vertical = self.dump("06-vertical")
                self.swipe(vertical, "up")
                if self.visible_title(self.dump("06-vertical-result")) != self.TITLES[1]:
                    raise AuditFailure("vertical carousel did not respond to an upward swipe")

                self.launch("bounded")
                bounded = self.dump("07-bounded")
                self.swipe(bounded, "right")
                if self.visible_title(self.dump("07-bounded-edge")) != self.TITLES[0]:
                    raise AuditFailure("bounded carousel escaped its leading edge")

                self.launch("reverse")
                reverse = self.dump("08-reverse")
                self.swipe(reverse, "right")
                if self.visible_title(self.dump("08-reverse-result")) != self.TITLES[1]:
                    raise AuditFailure("reverse carousel did not invert gesture direction")

                self.launch("without-delimiters")
                hidden = self.dump("09-without-delimiters")
                if any(
                    node.attrib.get("content-desc", "").startswith("Go to slide")
                    for node in self.nodes(hidden)
                ):
                    raise AuditFailure("hideDelimiters still exposes visible delimiters")

                self.launch("cycle")
                automatic_titles = [
                    self.visible_title(self.dump(f"10-cycle-{sample}"))
                    for sample in range(3)
                ]
                if len(set(automatic_titles)) < 2:
                    raise AuditFailure("automatic cycle did not advance")

            self.set_setting("system", "font_scale", "1.3")
            self.launch()
            scaled = self.dump("11-font-scale-130")
            scaled_area = node_bounds(self.carousel(scaled))
            for node in self.exact(scaled, self.TITLES[0]):
                title = node_bounds(node)
                if title.left < scaled_area.left or title.right > scaled_area.right:
                    raise AuditFailure("scaled carousel title escaped its surface")
            self.screenshot("11-font-scale-130")
            self.set_setting("system", "font_scale", "1.0")

            self.set_setting("system", "user_rotation", "1")
            self.wait_for_rotation(1, "Carousel landscape")
            self.launch()
            landscape = self.dump("12-landscape")
            self.swipe(landscape, "left")
            if self.visible_title(self.dump("12-landscape-result")) != self.TITLES[1]:
                raise AuditFailure("landscape carousel swipe failed")
            self.screenshot("12-landscape")
            self.set_setting("system", "user_rotation", "0")
            self.wait_for_rotation(0, "Carousel portrait restore")

            self.launch()
            stress = self.dump("13-stress-before")
            self.shell("dumpsys", "gfxinfo", self.package, "reset")
            for index in range(12):
                self.swipe(stress, "left" if index % 2 == 0 else "right")
            gfx = self.shell("dumpsys", "gfxinfo", self.package, timeout=30.0)
            p99 = int(re.search(r"99th percentile:\s*(\d+)ms", gfx).group(1))
            missed = int(re.search(r"Number Missed Vsync:\s*(\d+)", gfx).group(1))
            slow = int(re.search(r"Number Slow UI thread:\s*(\d+)", gfx).group(1))
            if p99 > 17 or missed > 0 or slow > 0:
                raise AuditFailure(f"carousel stress failed: p99={p99}, missed={missed}, slow={slow}")

            logs = self.shell("logcat", "-d", "-t", "2000", timeout=30.0)
            if any(marker in logs for marker in ("FATAL EXCEPTION", "ANR in dev.pam.mobileui.catalog")):
                raise AuditFailure("runtime errors found in logcat")

            report = {
                "schemaVersion": 2,
                "component": self.component_tag,
                "device": self.serial,
                "checks": {
                    "rounded24DpClip": True,
                    "horizontalSwipeAndIndicators": True,
                    "continuousWrap": True,
                    "delimiterTap": True,
                    **(
                        {"itemComposition": True}
                        if self.is_item
                        else {
                            "arrowControls": True,
                            "verticalGesture": True,
                            "boundedEdges": True,
                            "reverseDirection": True,
                            "hiddenDelimiters": True,
                            "automaticCycle": True,
                        }
                    ),
                    "fontScale130": True,
                    "adaptiveLandscape": True,
                    "twelveGestureStress": True,
                    "runtimeLog": True,
                },
                "metrics": {
                    "density": density,
                    "heightDp": round(area.height / density, 2),
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
    parser = argparse.ArgumentParser(description="Audit p-carousel on Android.")
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--component-tag",
        choices=("p-carousel", "p-carousel-item"),
        default="p-carousel",
    )
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-carousel-audit"))
    args = parser.parse_args()
    report = CarouselAudit(
        args.serial,
        args.package,
        args.activity,
        args.output,
        component_tag=args.component_tag,
    ).run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS {args.component_tag}; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
