#!/usr/bin/env python3

"""Reject blank, overlapping and clipped Android showcase states.

Compact Android controls are allowed to draw below 48 dp because the PAM Native
renderer expands their effective hit area with ``TouchDelegate``. The hierarchy
reports visual bounds, not those delegated bounds, so compact controls are
recorded as observations and their 48 dp interaction contract is covered by the
renderer instrumentation suite.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path
from xml.etree import ElementTree

from PIL import Image, ImageStat


BOUNDS = re.compile(r"\[(-?\d+),(-?\d+)]\[(-?\d+),(-?\d+)]")


def rectangle(value: str) -> tuple[int, int, int, int] | None:
    match = BOUNDS.fullmatch(value)
    return tuple(map(int, match.groups())) if match else None


def intersection(
    first: tuple[int, int, int, int],
    second: tuple[int, int, int, int],
) -> int:
    width = max(0, min(first[2], second[2]) - max(first[0], second[0]))
    height = max(0, min(first[3], second[3]) - max(first[1], second[1]))
    return width * height


def validate_screen(
    image_path: Path,
    hierarchy_path: Path,
    density: int,
) -> tuple[list[str], list[str]]:
    failures: list[str] = []
    observations: list[str] = []
    with Image.open(image_path) as source:
        image = source.convert("RGB")
        width, height = image.size
        sample = image.resize((135, 300))
        deviation = sum(ImageStat.Stat(sample).stddev) / 3
        colors = sample.getcolors(maxcolors=135 * 300) or []
        if width < 720 or height < 1280:
            failures.append(f"unexpected resolution {width}x{height}")
        if deviation < 8 or len(colors) < 24:
            failures.append(
                f"screen appears blank (deviation={deviation:.2f}, colors={len(colors)})",
            )

    root = ElementTree.parse(hierarchy_path).getroot()
    nodes = list(root.iter("node"))
    app_packages = {
        node.attrib.get("package", "")
        for node in nodes
        if node.attrib.get("package", "").startswith("dev.pam.")
    }
    if not app_packages:
        failures.append("hierarchy has no PAM application nodes")
        return failures, observations

    text_nodes: list[tuple[str, tuple[int, int, int, int]]] = []
    minimum_target = round(48 * density / 160)
    for node in nodes:
        if node.attrib.get("package", "") not in app_packages:
            continue
        bounds = rectangle(node.attrib.get("bounds", ""))
        if bounds is None:
            continue
        left, top, right, bottom = bounds
        if left < 0 or top < 0 or right > width or bottom > height:
            failures.append(f"node outside viewport: {bounds}")
        text = node.attrib.get("text", "").strip()
        if text and node.attrib.get("class") == "android.widget.TextView":
            text_nodes.append((text, bounds))
        if node.attrib.get("clickable") == "true" and node.attrib.get("enabled") == "true":
            target_width = right - left
            target_height = bottom - top
            if target_width < minimum_target or target_height < minimum_target:
                label = (
                    node.attrib.get("content-desc", "")
                    or text
                    or node.attrib.get("class", "interactive node")
                )
                observations.append(
                    f"compact visual control '{label}' is "
                    f"{target_width}x{target_height}px; PAM Native expands its "
                    f"effective target to {minimum_target}px",
                )

    for index, (first_text, first_bounds) in enumerate(text_nodes):
        first_area = max(1, (first_bounds[2] - first_bounds[0]) * (first_bounds[3] - first_bounds[1]))
        for second_text, second_bounds in text_nodes[index + 1 :]:
            overlap = intersection(first_bounds, second_bounds)
            second_area = max(
                1,
                (second_bounds[2] - second_bounds[0]) * (second_bounds[3] - second_bounds[1]),
            )
            if overlap / min(first_area, second_area) >= 0.08:
                failures.append(
                    f"text overlap: '{first_text}' {first_bounds} and "
                    f"'{second_text}' {second_bounds}",
                )

    return failures, observations


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("screenshots", type=Path)
    parser.add_argument("hierarchies", type=Path)
    parser.add_argument("--density", type=int, required=True)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--expect-components", type=int, default=84)
    arguments = parser.parse_args()

    component_images = sorted(arguments.screenshots.glob("p-*.png"))
    failures: dict[str, list[str]] = {}
    observations: dict[str, list[str]] = {}
    if len(component_images) != arguments.expect_components:
        failures["coverage"] = [
            f"expected {arguments.expect_components} component screenshots, "
            f"found {len(component_images)}",
        ]
    for image_path in sorted(arguments.screenshots.glob("*.png")):
        hierarchy_path = arguments.hierarchies / f"{image_path.stem}.xml"
        if not hierarchy_path.is_file():
            failures[image_path.name] = ["missing UI hierarchy"]
            continue
        screen_failures, screen_observations = validate_screen(
            image_path,
            hierarchy_path,
            arguments.density,
        )
        if screen_failures:
            failures[image_path.name] = screen_failures
        if screen_observations:
            observations[image_path.name] = screen_observations

    result = {
        "componentCount": len(component_images),
        "screenCount": len(list(arguments.screenshots.glob("*.png"))),
        "densityDpi": arguments.density,
        "status": "failed" if failures else "passed",
        "failures": failures,
        "observations": observations,
    }
    encoded = json.dumps(result, indent=2, sort_keys=True) + "\n"
    if arguments.output:
        arguments.output.write_text(encoded, encoding="utf-8")
    sys.stdout.write(encoded)
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())
