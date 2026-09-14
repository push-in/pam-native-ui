#!/usr/bin/env python3
"""Audit cold startup and initial scroll position for every Android component route."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import statistics
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path


class AuditFailure(RuntimeError):
    pass


def run(adb: list[str], *arguments: str, timeout: float = 35.0) -> str:
    result = subprocess.run(
        [*adb, *arguments],
        capture_output=True,
        text=True,
        timeout=timeout,
        check=False,
    )
    if result.returncode != 0:
        raise AuditFailure(
            f"adb {' '.join(arguments)} failed ({result.returncode}): {result.stderr.strip()}"
        )
    return result.stdout.replace("\r", "")


def bounds_top(value: str) -> int:
    match = re.fullmatch(r"\[(\d+),(\d+)\]\[(\d+),(\d+)\]", value)
    if match is None:
        return 1_000_000
    return int(match.group(2))


def percentile(values: list[int], fraction: float) -> int:
    ordered = sorted(values)
    return ordered[min(len(ordered) - 1, round((len(ordered) - 1) * fraction))]


def catalog_tags(root: Path) -> list[str]:
    catalog = (root / "docs/catalog.md").read_text(encoding="utf-8")
    tags = sorted({match[1:] for match in re.findall(r"<p-[a-z0-9-]+", catalog)})
    parity = json.loads((root / "resources/material-parity.json").read_text(encoding="utf-8"))
    expected = parity["reference"]["componentCount"]
    if not tags or len(tags) != expected:
        raise AuditFailure(f"expected {expected} catalog routes, found {len(tags)}")
    registered = {tag for module in parity["modules"] for tag in module["components"]}
    if set(tags) != registered:
        raise AuditFailure(
            f"catalog differs from registry: missing={sorted(registered - set(tags))}, "
            f"unknown={sorted(set(tags) - registered)}"
        )
    return tags


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--tags", nargs="+", help="Measure only these registered routes; reports remain explicitly partial")
    parser.add_argument("--settle-seconds", type=float, default=0.8)
    parser.add_argument("--p95-budget-ms", type=int, default=1_500)
    parser.add_argument("--max-budget-ms", type=int, default=2_500)
    args = parser.parse_args()

    root = Path(__file__).resolve().parent.parent
    registered_tags = catalog_tags(root)
    tags = list(dict.fromkeys(args.tags)) if args.tags else registered_tags
    unknown = set(tags) - set(registered_tags)
    if unknown:
        raise AuditFailure(f"Unknown component routes: {sorted(unknown)}")

    adb = ["adb", "-s", args.serial]
    run(adb, "get-state")
    package_path = run(adb, "shell", "pm", "path", args.package).strip().removeprefix("package:")
    if not package_path:
        raise AuditFailure(f"{args.package} is not installed on {args.serial}")
    build_hash = run(adb, "shell", "sha256sum", package_path).split()[0]
    run(adb, "shell", "logcat", "-c")

    measurements: dict[str, int] = {}
    dismissed_initial_overlays: list[str] = []
    for index, tag in enumerate(tags, start=1):
        launch = run(
            adb,
            "shell",
            "am",
            "start",
            "-W",
            "-S",
            "-n",
            f"{args.package}/{args.activity}",
            "-d",
            f"pam-showcase://audit/{tag}",
        )
        if "Status: ok" not in launch or "LaunchState: COLD" not in launch:
            raise AuditFailure(f"{tag} was not confirmed as a cold launch: {launch.strip()}")
        match = re.search(r"^TotalTime: (\d+)$", launch, re.MULTILINE)
        if match is None:
            raise AuditFailure(f"{tag} launch did not report TotalTime")
        measurements[tag] = int(match.group(1))
        time.sleep(args.settle_seconds)

        # This showcase example intentionally starts with a modal. UiAutomator
        # exposes only the active modal, not the underlying route heading.
        # Dismiss only this confirmed initial overlay after measuring startup;
        # keep the same strict route/top assertions for the underlying page.
        if tag == "p-command-palette":
            run(adb, "shell", "input", "keyevent", "BACK")
            time.sleep(args.settle_seconds)
            dismissed_initial_overlays.append(tag)

        remote = f"/sdcard/pam-startup-{index:02d}.xml"
        route_at_top = False
        variations_visible = False
        for attempt in range(3):
            run(adb, "shell", "uiautomator", "dump", "--compressed", remote)
            hierarchy = run(adb, "exec-out", "cat", remote)
            tree = ET.fromstring(hierarchy)
            route_nodes = [node for node in tree.iter() if node.attrib.get("text") == tag]
            variation_nodes = [
                node for node in tree.iter() if node.attrib.get("text") == "Variations"
            ]
            route_at_top = bool(route_nodes) and min(
                bounds_top(node.attrib.get("bounds", "")) for node in route_nodes
            ) < 320
            variations_visible = bool(variation_nodes) and min(
                bounds_top(node.attrib.get("bounds", "")) for node in variation_nodes
            ) < 520
            if route_at_top and variations_visible:
                break
            if attempt < 2:
                time.sleep(0.5)
        run(adb, "shell", "rm", "-f", remote)
        if not route_at_top:
            raise AuditFailure(f"{tag} cold-launched outside the authoritative route top")
        if not variations_visible:
            raise AuditFailure(f"{tag} cold launch hid the Variations heading")
        print(
            f"[{index:03d}/{len(tags):03d}] {tag}: cold launch {measurements[tag]}ms; route verified",
            file=sys.stderr,
            flush=True,
        )

    durations = list(measurements.values())
    p95 = percentile(durations, 0.95)
    maximum = max(durations)
    logs = run(adb, "shell", "logcat", "-d", "-t", "12000", timeout=45.0)
    runtime_errors = [
        line
        for line in logs.splitlines()
        if any(
            marker in line
            for marker in (
                "FATAL EXCEPTION",
                "ANR in dev.pam.mobileui.catalog",
                "Pam Native runtime error",
                "Input dispatching timed out",
            )
        )
    ]
    passed = p95 <= args.p95_budget_ms and maximum <= args.max_budget_ms and not runtime_errors
    report = {
        "schemaVersion": 1,
        "resultCode": 1 if passed else 2,
        "platformCode": 1,
        "buildTypeCode": 2,
        "candidateOnly": True,
        "fullCatalog": set(tags) == set(registered_tags),
        "registeredRouteCount": len(registered_tags),
        "samplesPerRoute": 1,
        "capturedAt": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "package": args.package,
        "buildSha256": build_hash,
        "device": {
            "serial": args.serial,
            "model": run(adb, "shell", "getprop", "ro.product.model").strip(),
            "api": int(run(adb, "shell", "getprop", "ro.build.version.sdk").strip()),
        },
        "budgets": {"p95Ms": args.p95_budget_ms, "maxMs": args.max_budget_ms},
        "summary": {
            "routeCount": len(tags),
            "p50Ms": round(statistics.median(durations)),
            "p95Ms": p95,
            "p99Ms": percentile(durations, 0.99),
            "maxMs": maximum,
            "runtimeErrorCount": len(runtime_errors),
        },
        "coldStartMs": measurements,
        "dismissedInitialOverlays": dismissed_initial_overlays,
        "runtimeErrors": runtime_errors,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    encoded = json.dumps(report, indent=2, sort_keys=True) + "\n"
    args.output.write_text(encoded, encoding="utf-8")
    digest = hashlib.sha256(encoded.encode()).hexdigest()
    print(
        f"{'PASS' if passed else 'FAIL'} {len(tags)} release cold launches; "
        f"p50={report['summary']['p50Ms']}ms p95={p95}ms max={maximum}ms sha256={digest}"
    )
    return 0 if passed else 1


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (AuditFailure, ET.ParseError, subprocess.TimeoutExpired) as error:
        print(f"FAIL: {error}")
        raise SystemExit(1)
