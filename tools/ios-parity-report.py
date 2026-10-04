#!/usr/bin/env python3
"""Validate and report the *known* UIKit evidence without changing parity status."""

import argparse
import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
TEST_PATTERN = re.compile(r"\bfunc\s+(test[A-Za-z0-9_]+)\s*\(")
TAG_PATTERN = re.compile(r"^\s*'(p-[a-z0-9-]+)'\s*=>", re.MULTILINE)


def build_report(root=ROOT):
    manifest = json.loads((root / "resources/material-parity.json").read_text())
    evidence = json.loads((root / "resources/ios-parity-evidence.json").read_text())
    test_files = sorted((root / "ios/Tests/PamMobileUiTests").glob("*Tests.swift"))
    if not test_files:
        raise ValueError("The UIKit XCTest suite has no test source files")
    test_source = "\n".join(path.read_text() for path in test_files)
    generated_map = (root / "src/Generated/MaterialComponentMap.php").read_text()

    modules = manifest["modules"]
    if len(modules) != 92 or [module["type"] for module in modules] != list(range(1, 93)):
        raise ValueError("Material module IDs must be sequential from 1 through 92")
    names = [module["module"] for module in modules]
    if len(set(names)) != len(names):
        raise ValueError("Material module names must be unique")
    tags = [tag for module in modules for tag in module["components"]]
    if len(tags) != 114 or len(set(tags)) != 114:
        raise ValueError("Material modules must contain 114 unique public tags")
    generated_tags = TAG_PATTERN.findall(generated_map.split("public const array MODULES")[0])
    if tags != generated_tags:
        raise ValueError("Material parity tags differ from the generated PHP public map")
    ios_gate = next(gate["id"] for gate in manifest["gateDefinitions"] if gate["name"] == "ios")
    if ios_gate != 4:
        raise ValueError("The iOS gate ID changed; review the report before using it")

    tests = TEST_PATTERN.findall(test_source)
    if len(tests) != len(set(tests)):
        raise ValueError("Duplicate UIKit XCTest function names")
    known_tests = set(tests)
    linked = {name: [] for name in names}
    for test_name, module_names in evidence["behaviorTests"].items():
        if test_name not in known_tests:
            raise ValueError(f"Linked UIKit test is absent: {test_name}")
        if not module_names or len(module_names) != len(set(module_names)):
            raise ValueError(f"Linked UIKit test has no modules or duplicates: {test_name}")
        for name in module_names:
            if name not in linked:
                raise ValueError(f"Unknown Material module for {test_name}: {name}")
            linked[name].append(test_name)

    rows = []
    for module in modules:
        verification = module["verification"]
        if len(verification) != len(manifest["gateDefinitions"]):
            raise ValueError(f"Invalid gate count for {module['module']}")
        rows.append({
            "id": module["type"],
            "module": module["module"],
            "tags": module["components"],
            "iosStatus": verification[ios_gate - 1],
            "linkedBehaviorTests": linked[module["module"]],
        })
    return {
        "scope": "Source links only; CI simulator success is required for passing tests. Visual, interaction, accessibility, themes and performance require separate evidence.",
        "modules": rows,
        "summary": {
            "modules": len(rows),
            "tags": len(tags),
            "linkedBehaviorTests": len(evidence["behaviorTests"]),
            "modulesWithLinkedBehaviorTests": sum(bool(row["linkedBehaviorTests"]) for row in rows),
            "modulesWithoutLinkedBehaviorTests": sum(not row["linkedBehaviorTests"] for row in rows),
            "iosImplemented": sum(row["iosStatus"] == 2 for row in rows),
            "iosVerified": sum(row["iosStatus"] == 3 for row in rows),
        },
    }


def markdown(report):
    summary = report["summary"]
    lines = [
        "# UIKit parity evidence inventory",
        "",
        report["scope"],
        "",
        f"Modules: {summary['modules']}; public tags: {summary['tags']}; "
        f"modules linked to targeted XCTest cases: {summary['modulesWithLinkedBehaviorTests']}; "
        f"iOS verified in manifest: {summary['iosVerified']}.",
        "",
        "| ID | Module | Public tags | iOS status | Linked UIKit tests |",
        "| ---: | --- | --- | ---: | --- |",
    ]
    for row in report["modules"]:
        tests = ", ".join(row["linkedBehaviorTests"]) or "—"
        lines.append(
            f"| {row['id']} | {row['module']} | {', '.join(row['tags'])} | "
            f"{row['iosStatus']} | {tests} |"
        )
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--format", choices=("json", "markdown"), default="markdown")
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()
    report = build_report()
    output = json.dumps(report, indent=2, ensure_ascii=False) + "\n" if args.format == "json" else markdown(report)
    if args.output:
        args.output.write_text(output)
    else:
        print(output, end="")


if __name__ == "__main__":
    main()
