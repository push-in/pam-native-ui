#!/usr/bin/env python3
"""Build compact before/after GIFs from audited Android candidate evidence."""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

from PIL import Image


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def frame(image: Image.Image, width: int) -> Image.Image:
    height = round(image.height * width / image.width)
    return image.convert("RGB").resize((width, height), Image.Resampling.LANCZOS)


def materialize(component_dir: Path, output: Path, width: int) -> bool:
    report_path = component_dir / "report-candidate.json"
    before_path = component_dir / "baseline-candidate.png"
    after_path = component_dir / "interacted-candidate.png"
    if not all(path.is_file() for path in (report_path, before_path, after_path)):
        return False
    report = json.loads(report_path.read_text(encoding="utf-8"))
    if not (
        report.get("candidateOnly") is True
        and report.get("resultStatus") == 1
        and report.get("checks", {}).get("twoConsecutivePasses") is True
        and report.get("checks", {}).get("realInteraction") is True
    ):
        return False

    before = frame(Image.open(before_path), width)
    after = frame(Image.open(after_path), width)
    if before.size != after.size or before.tobytes() == after.tobytes():
        raise RuntimeError(f"{component_dir.name}: evidence states are not distinct")
    frames = [before]
    frames.extend(Image.blend(before, after, amount) for amount in (0.25, 0.5, 0.75))
    frames.append(after)
    output.mkdir(parents=True, exist_ok=True)
    target = output / f"{component_dir.name}.gif"
    frames[0].save(
        target,
        save_all=True,
        append_images=frames[1:],
        duration=[900, 90, 90, 90, 1300],
        loop=0,
        optimize=True,
    )
    metadata = {
        "schemaVersion": 1,
        "component": component_dir.name,
        "candidateOnly": True,
        "sourceReportSha256": sha256(report_path),
        "baselineSha256": sha256(before_path),
        "interactedSha256": sha256(after_path),
        "gif": target.name,
        "gifSha256": sha256(target),
        "width": width,
        "derivedFromRealInteraction": True,
    }
    (output / f"{component_dir.name}.json").write_text(
        json.dumps(metadata, indent=2) + "\n", encoding="utf-8"
    )
    return True


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--audit-directory", type=Path, default=Path("docs/assets/android/audit"))
    parser.add_argument("--output", type=Path, default=Path("docs/assets/android/gifs/components"))
    parser.add_argument("--width", type=int, default=360)
    parser.add_argument("--tags", nargs="*")
    args = parser.parse_args()
    directories = [args.audit_directory / tag for tag in args.tags] if args.tags else sorted(
        path for path in args.audit_directory.glob("p-*") if path.is_dir()
    )
    completed = [path.name for path in directories if materialize(path, args.output, args.width)]
    print(f"Materialized {len(completed)} audited component GIFs.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
