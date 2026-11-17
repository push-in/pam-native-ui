#!/usr/bin/env python3
"""Golden screenshot comparison for the PAM Native UI showcase.

A golden is a PNG per component state, theme and platform
(tests/golden/<platform>/<name>.png). `compare()` masks what legitimately
changes between runs (the status bar clock and icons, the navigation or
home-indicator band, and any rectangle the caller passes, such as a running
spinner) and then compares every remaining pixel.

Tolerance (documented, deliberately tiny): a pixel differs when any channel
differs by more than CHANNEL_TOLERANCE (anti-aliasing of glyph and curve
edges differs between GPU drivers and software renderers by a few levels);
a screenshot matches when at most MAX_DIFF_RATIO of the compared pixels
differ and no differing region is larger than MAX_BLOB_PX on a side, so a
moved border or a 1px shift of a whole component still fails.

    python3 tools/golden.py compare actual.png golden.png [--diff out.png]
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

from PIL import Image, ImageChops

CHANNEL_TOLERANCE = 24
MAX_DIFF_RATIO = 0.0015
MAX_BLOB_PX = 6

Rect = tuple[int, int, int, int]


def mask(image: Image.Image, rects: list[Rect]) -> Image.Image:
    masked = image.convert("RGB").copy()
    for x1, y1, x2, y2 in rects:
        if x2 > x1 and y2 > y1:
            masked.paste((255, 0, 255), (max(0, x1), max(0, y1), min(masked.width, x2), min(masked.height, y2)))
    return masked


def compare(actual: Path, golden: Path, rects: list[Rect], diff: Path | None = None) -> tuple[bool, str]:
    a = Image.open(actual).convert("RGB")
    g = Image.open(golden).convert("RGB")
    if a.size != g.size:
        return False, f"size {a.size} vs golden {g.size}"
    a, g = mask(a, rects), mask(g, rects)
    # The golden carries the regions masked when it was recorded (magenta):
    # an animation there moves between runs, so it is masked in both.
    magenta = Image.new("RGB", g.size, (255, 0, 255))
    diff_to_magenta = ImageChops.difference(g, magenta).convert("L").point(lambda v: 255 if v == 0 else 0)
    a.paste(magenta, mask=diff_to_magenta)
    delta = ImageChops.difference(a, g).convert("RGB")
    channels = delta.split()
    over = [band.point(lambda value: 255 if value > CHANNEL_TOLERANCE else 0) for band in channels]
    differing = ImageChops.lighter(ImageChops.lighter(over[0], over[1]), over[2])
    count = sum(1 for value in differing.getdata() if value)
    ratio = count / (a.width * a.height)
    blob = largest_blob(differing) if count else 0
    ok = ratio <= MAX_DIFF_RATIO and blob <= MAX_BLOB_PX
    if not ok and diff is not None:
        overlay = Image.blend(a, Image.new("RGB", a.size, (255, 255, 255)), 0.6)
        overlay.paste((255, 0, 0), mask=differing)
        overlay.save(diff)
    return ok, f"{count} px differ ({ratio:.4%}), largest region {blob}px"


def animated_regions(first: Path, second: Path, pad: int = 8) -> list[Rect]:
    """Rectangles that changed between two captures taken moments apart
    (spinners, shimmer, indeterminate progress, a blinking caret): masked,
    because they are animations, not layout."""
    a = Image.open(first).convert("L")
    b = Image.open(second).convert("L")
    if a.size != b.size:
        return []
    changed = ImageChops.difference(a, b).point(lambda value: 255 if value > 8 else 0)
    regions: list[Rect] = []
    step = 24
    for y in range(0, changed.height, step):
        for x in range(0, changed.width, step):
            box = changed.crop((x, y, x + step, y + step))
            if box.getbbox():
                regions.append((x - pad, y - pad, x + step + pad, y + step + pad))
    return regions


def largest_blob(differing: Image.Image) -> int:
    """Longest side of the largest 8-connected differing region (downsampled by 2)."""
    small = differing.resize((max(1, differing.width // 2), max(1, differing.height // 2)), Image.Resampling.BOX)
    width, height = small.size
    pixels = small.load()
    seen = bytearray(width * height)
    best = 0
    for y in range(height):
        for x in range(width):
            if not pixels[x, y] or seen[y * width + x]:
                continue
            stack = [(x, y)]
            seen[y * width + x] = 1
            x1 = x2 = x
            y1 = y2 = y
            while stack:
                cx, cy = stack.pop()
                x1, x2, y1, y2 = min(x1, cx), max(x2, cx), min(y1, cy), max(y2, cy)
                for nx in (cx - 1, cx, cx + 1):
                    for ny in (cy - 1, cy, cy + 1):
                        if 0 <= nx < width and 0 <= ny < height and pixels[nx, ny] and not seen[ny * width + nx]:
                            seen[ny * width + nx] = 1
                            stack.append((nx, ny))
            best = max(best, (max(x2 - x1, y2 - y1) + 1) * 2)
    return best


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("command", choices=["compare"])
    parser.add_argument("actual", type=Path)
    parser.add_argument("golden", type=Path)
    parser.add_argument("--diff", type=Path, default=None)
    args = parser.parse_args()
    ok, detail = compare(args.actual, args.golden, [], args.diff)
    print(("MATCH " if ok else "DIFF ") + detail)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
