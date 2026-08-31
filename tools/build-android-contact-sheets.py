#!/usr/bin/env python3

"""Build review-friendly contact sheets from Android showcase screenshots."""

from __future__ import annotations

import argparse
import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("screenshots", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--per-sheet", type=int, default=15)
    parser.add_argument("--columns", type=int, default=5)
    arguments = parser.parse_args()

    images = sorted(
        path
        for path in arguments.screenshots.glob("*.png")
        if not path.name.startswith("contact-sheet-")
    )
    if not images:
        parser.error(f"no PNG screenshots found in {arguments.screenshots}")

    arguments.output.mkdir(parents=True, exist_ok=True)
    thumbnail_width = 216
    thumbnail_height = 480
    label_height = 32
    rows = math.ceil(arguments.per_sheet / arguments.columns)
    font = ImageFont.truetype(
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        14,
    )

    for sheet_index, offset in enumerate(
        range(0, len(images), arguments.per_sheet),
        start=1,
    ):
        sheet = Image.new(
            "RGB",
            (
                thumbnail_width * arguments.columns,
                (thumbnail_height + label_height) * rows,
            ),
            "white",
        )
        draw = ImageDraw.Draw(sheet)
        for local_index, image_path in enumerate(
            images[offset : offset + arguments.per_sheet],
        ):
            column = local_index % arguments.columns
            row = local_index // arguments.columns
            x = column * thumbnail_width
            y = row * (thumbnail_height + label_height)
            with Image.open(image_path) as source:
                thumbnail = source.convert("RGB")
                thumbnail.thumbnail((thumbnail_width, thumbnail_height))
                image_x = x + (thumbnail_width - thumbnail.width) // 2
                sheet.paste(thumbnail, (image_x, y))
            label = image_path.stem
            label_width = draw.textlength(label, font=font)
            draw.text(
                (x + max(4, (thumbnail_width - label_width) / 2), y + thumbnail_height + 7),
                label,
                fill="#0b172a",
                font=font,
            )
        sheet.save(arguments.output / f"contact-sheet-{sheet_index:02d}.jpg", quality=90)

    print(
        f"Built {math.ceil(len(images) / arguments.per_sheet)} contact sheets "
        f"from {len(images)} screenshots.",
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
