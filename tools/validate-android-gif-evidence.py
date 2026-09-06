#!/usr/bin/env python3
"""Validate every published Android interaction GIF and its provenance."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parent.parent
GIF_DIRECTORY = ROOT / "docs/assets/android/gifs/components"
AUDIT_DIRECTORY = ROOT / "docs/assets/android/audit"
EXPECTED_GIFS = 65


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def require(condition: bool, message: str, failures: list[str]) -> None:
    if not condition:
        failures.append(message)


def is_sha256(value: object) -> bool:
    return isinstance(value, str) and len(value) == 64 and all(
        character in "0123456789abcdef" for character in value
    )


def main() -> int:
    failures: list[str] = []
    gifs = sorted(GIF_DIRECTORY.glob("p-*.gif"))
    metadata_files = sorted(GIF_DIRECTORY.glob("p-*.json"))
    require(len(gifs) == EXPECTED_GIFS, f"expected {EXPECTED_GIFS} GIFs, found {len(gifs)}", failures)
    require(
        {path.stem for path in gifs} == {path.stem for path in metadata_files},
        "GIF and metadata component sets differ",
        failures,
    )

    for gif_path in gifs:
        component = gif_path.stem
        metadata_path = GIF_DIRECTORY / f"{component}.json"
        if not metadata_path.is_file():
            failures.append(f"{component}: missing metadata")
            continue
        try:
            metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, OSError) as error:
            failures.append(f"{component}: invalid metadata: {error}")
            continue

        audit = AUDIT_DIRECTORY / component
        report = audit / "report-candidate.json"
        baseline = audit / "baseline-candidate.png"
        interacted = audit / "interacted-candidate.png"
        require(metadata.get("schemaVersion") == 1, f"{component}: unsupported schema", failures)
        require(metadata.get("component") == component, f"{component}: component mismatch", failures)
        require(metadata.get("candidateOnly") is True, f"{component}: candidateOnly must be true", failures)
        require(
            metadata.get("derivedFromRealInteraction") is True,
            f"{component}: not marked as real interaction",
            failures,
        )
        require(metadata.get("gif") == gif_path.name, f"{component}: GIF filename mismatch", failures)
        require(metadata.get("gifSha256") == sha256(gif_path), f"{component}: GIF hash mismatch", failures)

        for source, key in (
            (report, "sourceReportSha256"),
            (baseline, "baselineSha256"),
            (interacted, "interactedSha256"),
        ):
            require(is_sha256(metadata.get(key)), f"{component}: invalid {key}", failures)
            if source.is_file():
                require(metadata.get(key) == sha256(source), f"{component}: {key} mismatch", failures)

        if report.is_file():
            report_data = json.loads(report.read_text(encoding="utf-8"))
            checks = report_data.get("checks", {})
            require(report_data.get("resultStatus") == 1, f"{component}: source report did not pass", failures)
            require(checks.get("twoConsecutivePasses") is True, f"{component}: two-pass check missing", failures)
            require(checks.get("realInteraction") is True, f"{component}: real-interaction check missing", failures)

        try:
            with Image.open(gif_path) as image:
                require(image.format == "GIF", f"{component}: invalid GIF format", failures)
                require(image.width == metadata.get("width"), f"{component}: width mismatch", failures)
                require(getattr(image, "n_frames", 1) >= 2, f"{component}: GIF is not animated", failures)
        except OSError as error:
            failures.append(f"{component}: unreadable GIF: {error}")

    if failures:
        print("Android GIF evidence failed:")
        for failure in failures:
            print(f"- {failure}")
        return 1
    print(f"Android GIF evidence complete: {len(gifs)}/{EXPECTED_GIFS} animations with verified provenance.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
