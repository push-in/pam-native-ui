#!/usr/bin/env python3
"""Fail a tagged UI release until every required Material iOS gate is verified."""

import json
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def check(manifest_path: Path) -> tuple[int, int]:
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    reference = manifest["reference"]
    modules = manifest["modules"]
    gates = manifest["gateDefinitions"]
    if reference["moduleCount"] != 92 or len(modules) != 92:
        raise ValueError("Material release inventory must contain 92 modules")
    if [module["type"] for module in modules] != list(range(1, 93)):
        raise ValueError("Material module IDs must be sequential from 1 to 92")
    ios_gates = [gate["id"] for gate in gates if gate["name"] == "ios"]
    if ios_gates != [4]:
        raise ValueError("The Material iOS gate must have ID 4")
    pending = 0
    for module in modules:
        verification = module["verification"]
        if len(verification) != len(gates) or any(value not in (1, 2, 3, 4) for value in verification):
            raise ValueError(f"Invalid verification statuses for {module['module']}")
        if verification[3] != 3:
            pending += 1
    return pending, len(modules)


def main() -> int:
    path = Path(sys.argv[1]) if len(sys.argv) == 2 else ROOT / "resources/material-parity.json"
    try:
        pending, total = check(path)
    except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError) as error:
        print(f"release-parity: invalid manifest: {error}", file=sys.stderr)
        return 1
    if pending:
        print(
            f"release-parity: iOS gate is not verified for {pending}/{total} Material modules.",
            file=sys.stderr,
        )
        return 1
    print(f"release-parity: iOS gate verified for {total}/{total} Material modules.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
