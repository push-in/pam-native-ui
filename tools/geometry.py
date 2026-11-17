#!/usr/bin/env python3
"""Pixel geometry checks on a PAM Native layout snapshot.

Android debug builds publish the committed layout on request
(`dev.pam.nativeapp.action.CAPTURE_LAYOUT`, pam-native 1.52+): every shown
view with its engine frame in dp, its on-screen pixels, the part actually
visible and, for text, baseline, lines and ellipsis. This module captures it
and asserts what a designer checks with a loupe:

- touch targets: every enabled pressable is at least 48dp on both axes;
- system bars: no text or pressable is visible under the status bar,
  navigation bar or a cutout;
- truncation: no ellipsized or clipped text (unless allowed);
- overlap: no two pressables, or two texts, of the same surface intersect;
- pixel snapping: siblings that share an edge in dp share it in pixels;
- spacing: gaps between siblings are whole, even dp values;
- baselines: same-size texts side by side in a row share a baseline;
- centering: a pressable's single child (icon) is centred to the pixel.

    python3 tools/geometry.py --serial emulator-5554 [--package ...] [--json out.json]
"""

from __future__ import annotations

import argparse
import json
import secrets
import subprocess
import sys
import time
from dataclasses import dataclass, field

MIN_TARGET_DP = 48.0
TOLERANCE_PX = 1  # integer rounding of one edge


@dataclass
class Node:
    raw: dict
    children: list["Node"] = field(default_factory=list)
    parent: "Node | None" = None

    def __getattr__(self, name: str):
        try:
            return self.raw[name]
        except KeyError as error:
            raise AttributeError(name) from error

    @property
    def rect(self) -> tuple[int, int, int, int]:
        return self.raw["x"], self.raw["y"], self.raw["x"] + self.raw["w"], self.raw["y"] + self.raw["h"]

    @property
    def shown(self) -> tuple[int, int, int, int] | None:
        visible = self.raw.get("visible") or []
        return tuple(visible) if len(visible) == 4 and visible[2] > visible[0] and visible[3] > visible[1] else None

    @property
    def name(self) -> str:
        for key in ("testId", "label", "text"):
            value = self.raw.get(key)
            if value:
                return f"{self.raw['kind'].lower()} '{str(value)[:28]}'"
        return f"{self.raw['kind'].lower()}#{self.raw['id']}"

    def owner(self) -> str:
        """The nearest labelled ancestor, to say where a finding is."""
        node: Node | None = self
        while node is not None:
            if node.raw.get("label") or node.raw.get("testId"):
                return node.name
            node = node.parent
        return self.name

    def root(self) -> "Node":
        node = self
        while node.parent is not None:
            node = node.parent
        return node

    def ancestors(self) -> set[int]:
        found, node = set(), self.parent
        while node is not None:
            found.add(node.raw["id"])
            node = node.parent
        return found


def capture(serial: str, package: str, attempts: int = 60) -> dict:
    request = secrets.token_hex(16)
    adb = ["adb", "-s", serial]
    subprocess.run(
        [*adb, "shell", "am", "broadcast", "-a", "dev.pam.nativeapp.action.CAPTURE_LAYOUT", "-p", package, "--es", "requestId", request],
        check=True, capture_output=True,
    )
    path = f"cache/pam-layout-{request}.json"
    for _ in range(attempts):
        result = subprocess.run([*adb, "exec-out", "run-as", package, "cat", path], capture_output=True)
        if result.returncode == 0 and result.stdout.startswith(b"{"):
            subprocess.run([*adb, "shell", "run-as", package, "rm", "-f", path], capture_output=True)
            return json.loads(result.stdout)
        time.sleep(0.05)
    raise RuntimeError("the app did not publish its layout (debug build of pam-native 1.52+ running?)")


def tree(snapshot: dict) -> list[Node]:
    nodes = {raw["id"]: Node(raw) for raw in snapshot["nodes"]}
    for node in nodes.values():
        parent = nodes.get(node.raw["parent"])
        if parent is not None:
            node.parent = parent
            parent.children.append(node)
    return list(nodes.values())


def intersect(a, b) -> int:
    width = min(a[2], b[2]) - max(a[0], b[0])
    height = min(a[3], b[3]) - max(a[1], b[1])
    return min(width, height) if width > 0 and height > 0 else 0


def to_pixels(snapshot: dict) -> dict:
    """iOS snapshots are in points: convert geometry to device pixels."""
    if snapshot.get("units") != "pt":
        return snapshot
    scale = snapshot["density"]
    converted = dict(snapshot)
    converted["screen"] = [round(v * scale) for v in snapshot["screen"]]
    converted["insets"] = [round(v * scale) for v in snapshot["insets"]]
    nodes = []
    for raw in snapshot["nodes"]:
        node = dict(raw)
        for key in ("x", "y", "w", "h", "baseline", "textHeight", "contentHeight", "textWidth", "contentWidth"):
            if key in node:
                node[key] = node[key] * scale
        for key in ("visible", "hit"):
            if node.get(key):
                node[key] = [v * scale for v in node[key]]
        node["subpixel"] = [k for k in ("x", "y", "w", "h") if abs(node[k] - round(node[k])) > 0.02]
        for key in ("x", "y", "w", "h"):
            node[key] = round(node[key])
        if node.get("visible"):
            node["visible"] = [round(v) for v in node["visible"]]
        nodes.append(node)
    converted["nodes"] = nodes
    return converted


def check(snapshot: dict, allow_truncation: tuple[str, ...] = ()) -> dict[str, list[str]]:
    ios = snapshot.get("units") == "pt"
    minimum = 44.0 if ios else MIN_TARGET_DP  # Apple HIG 44pt, Material 48dp
    snapshot = to_pixels(snapshot)
    density = snapshot["density"]
    width, height = snapshot["screen"]
    left, top, right, bottom = snapshot["insets"]
    nodes = tree(snapshot)
    findings: dict[str, list[str]] = {name: [] for name in (
        "touch targets", "system bars", "truncation", "overlap", "pixel snapping", "spacing", "baselines", "centering",
    )}
    pressables = [n for n in nodes if n.raw.get("clickable") and not n.raw.get("disabled") and n.shown and n.raw.get("alpha", 1) > 0.01]
    texts = [n for n in nodes if "text" in n.raw and n.raw["text"].strip() and n.shown and n.raw.get("alpha", 1) > 0.01]

    for node in pressables:
        # The hit rectangle the platform tests touches against (hitSlop and the
        # renderer's 48dp minimum expand small visuals), else the view itself.
        w, h = node.raw.get("hit") or (node.raw["w"], node.raw["h"])
        w, h = w / density, h / density
        if w < minimum - 0.5 or h < minimum - 0.5:
            findings["touch targets"].append(f"{node.owner()}: {w:.1f}x{h:.1f}dp")

    bands = [(0, 0, width, top), (0, height - bottom, width, height), (0, 0, left, height), (width - right, 0, width, height)]
    scrolling = {"SCROLL", "LIST", "VIRTUAL_LIST", "SECTION_LIST"}

    def in_scroller(node: Node) -> bool:
        if node.raw.get("scrolls"):
            return True
        parent = node.parent
        while parent is not None:
            if parent.raw["kind"] in scrolling:
                return True
            parent = parent.parent
        return False

    for node in pressables + texts:
        # Scrolling content passes under a translucent bar on its way and is
        # padded to rest above it; the suites scroll to each end to check that.
        if in_scroller(node):
            continue
        for band in bands:
            if band[2] > band[0] and band[3] > band[1] and intersect(node.shown, band) > TOLERANCE_PX:
                findings["system bars"].append(f"{node.owner()}: visible {node.shown} under the bar {band}")

    for node in texts:
        if any(token in node.owner() for token in allow_truncation):
            continue
        if node.raw.get("ellipsized"):
            findings["truncation"].append(f"{node.owner()}: '{node.raw['text'][:30]}' ellipsized")
        elif node.raw.get("textHeight", 0) > node.raw.get("contentHeight", 1 << 30) + TOLERANCE_PX:
            findings["truncation"].append(f"{node.owner()}: text {node.raw['textHeight']}px in {node.raw['contentHeight']}px")
        elif node.raw.get("textWidth", 0) > node.raw.get("contentWidth", 1 << 30) + TOLERANCE_PX:
            findings["truncation"].append(f"{node.owner()}: line {node.raw['textWidth']:.0f}px in {node.raw['contentWidth']}px")

    for group in (pressables, texts):
        for i, a in enumerate(group):
            for b in group[i + 1 :]:
                if a.root() is not b.root() or a.raw["id"] in b.ancestors() or b.raw["id"] in a.ancestors():
                    continue
                if intersect(a.shown, b.shown) > TOLERANCE_PX:
                    findings["overlap"].append(f"{a.owner()} / {b.owner()}")

    for parent in nodes:
        laid_out = [k for k in parent.children if "frame" in k.raw and k.raw.get("alpha", 1) > 0.01]
        kids = [k for k in laid_out if not k.raw.get("transformed") and not k.raw.get("virtual")]
        for i, a in enumerate(kids):
            fa = a.raw["frame"]
            for b in kids[i + 1 :]:
                fb = b.raw["frame"]
                for label, da, db, pa, pb in (
                    ("top", fa[1], fb[1], a.raw["y"], b.raw["y"]),
                    ("left", fa[0], fb[0], a.raw["x"], b.raw["x"]),
                    ("bottom", fa[1] + fa[3], fb[1] + fb[3], a.raw["y"] + a.raw["h"], b.raw["y"] + b.raw["h"]),
                    ("right", fa[0] + fa[2], fb[0] + fb[2], a.raw["x"] + a.raw["w"], b.raw["x"] + b.raw["w"]),
                ):
                    if abs(da - db) < 0.01 and pa != pb:
                        findings["pixel snapping"].append(f"{a.owner()} / {b.owner()}: {label} {da:.2f}dp -> {pa} vs {pb}px")
        # Spacing between consecutive siblings along the stacking axis; centred
        # or distributed content and absolute children are placed, not spaced.
        # Protocol codes: justify 1 start, 2 center, 3 end, 4+ distributed;
        # position 2 absolute.
        if parent.raw.get("justify") not in (None, 1, 3):
            continue
        stacked = [k for k in laid_out if k.raw.get("position") != 2]
        ordered = sorted(stacked, key=lambda k: (k.raw["frame"][1], k.raw["frame"][0]))
        for a, b in zip(ordered, ordered[1:]):
            fa, fb = a.raw["frame"], b.raw["frame"]
            vertical = fb[1] >= fa[1] + fa[3] - 0.01
            horizontal = fb[0] >= fa[0] + fa[2] - 0.01 and abs(fb[1] - fa[1]) < fa[3]
            gap = fb[1] - (fa[1] + fa[3]) if vertical else fb[0] - (fa[0] + fa[2]) if horizontal else None
            if gap is None or gap < 0.01:
                continue
            if abs(gap - round(gap)) > 0.01:
                findings["spacing"].append(f"{a.owner()} -> {b.owner()}: {gap:.2f}dp gap")
            elif round(gap) % 2:
                findings["spacing"].append(f"{a.owner()} -> {b.owner()}: odd {round(gap)}dp gap")

    for parent in nodes:
        row = [k for k in parent.children if "baseline" in k.raw and k in texts]
        for i, a in enumerate(row):
            for b in row[i + 1 :]:
                ax1, ay1, ax2, ay2 = a.rect
                bx1, by1, bx2, by2 = b.rect
                side_by_side = (ax2 <= bx1 or bx2 <= ax1) and min(ay2, by2) > max(ay1, by1)
                if side_by_side and abs(a.raw["textSize"] - b.raw["textSize"]) < 0.01 and abs(a.raw["baseline"] - b.raw["baseline"]) > TOLERANCE_PX:
                    findings["baselines"].append(f"{a.owner()} / {b.owner()}: {a.raw['baseline']} vs {b.raw['baseline']}px")

    def has_text(node: Node) -> bool:
        return "text" in node.raw or any(has_text(child) for child in node.children)

    for node in pressables:
        # An icon button: small, one child, no text anywhere, icon-sized.
        if len(node.children) != 1 or has_text(node) or max(node.raw["w"], node.raw["h"]) > 64 * density:
            continue
        child = node.children[0]
        if child.raw.get("virtual") or child.raw.get("transformed") or child.raw["w"] > 40 * density or child.raw["h"] > 40 * density:
            continue
        px1, py1, px2, py2 = node.rect
        cx1, cy1, cx2, cy2 = child.rect
        dx = (cx1 - px1) - (px2 - cx2)
        dy = (cy1 - py1) - (py2 - cy2)
        if abs(dx) > TOLERANCE_PX or abs(dy) > TOLERANCE_PX:
            findings["centering"].append(f"{node.owner()}: icon off by {dx / 2:+.1f},{dy / 2:+.1f}px")
    # On iOS a view off the device pixel grid draws soft edges and borders.
    for node in nodes:
        if node.raw.get("subpixel") and not node.raw.get("transformed") and node.shown:
            findings["pixel snapping"].append(f"{node.owner()}: {'/'.join(node.raw['subpixel'])} between device pixels")
    for key, values in findings.items():
        findings[key] = sorted(set(values))
    return findings


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--serial", default=None)
    parser.add_argument("--files", nargs="*", default=None, help="check saved snapshots (iOS UI driver layout steps)")
    parser.add_argument("--package", default="dev.pam.nativeui.showcase.debug")
    parser.add_argument("--json", default=None, help="also write the raw snapshot here")
    args = parser.parse_args()
    if args.files:
        failed = False
        for path in args.files:
            with open(path) as handle:
                findings = check(json.load(handle))
            print(f"== {path}")
            for name, values in findings.items():
                print(("PASS " if not values else "FAIL ") + name + "".join(f"\n    {value}" for value in values[:20]))
            failed = failed or any(findings.values())
        return 1 if failed else 0
    snapshot = capture(args.serial, args.package)
    if args.json:
        with open(args.json, "w") as handle:
            json.dump(snapshot, handle)
    findings = check(snapshot)
    for name, values in findings.items():
        print(("PASS " if not values else "FAIL ") + name + "".join(f"\n    {value}" for value in values[:20]))
    return 1 if any(findings.values()) else 0


if __name__ == "__main__":
    sys.exit(main())
