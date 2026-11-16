#!/usr/bin/env python3
"""Create stable SHA-256 evidence for exported XCUITest attachments."""

from __future__ import annotations

import argparse
import hashlib
from pathlib import Path


def main(root: Path) -> None:
    root.mkdir(parents=True, exist_ok=True)
    manifest = root / "SHA256SUMS"
    entries = []
    for path in sorted(root.rglob("*")):
        if path.is_file() and path != manifest:
            digest = hashlib.sha256(path.read_bytes()).hexdigest()
            entries.append(f"{digest}  {path.relative_to(root).as_posix()}")
    manifest.write_text("\n".join(entries) + ("\n" if entries else ""))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("root", type=Path)
    main(parser.parse_args().root)
