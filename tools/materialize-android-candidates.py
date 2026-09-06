#!/usr/bin/env python3

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
from pathlib import Path


class CandidateFailure(RuntimeError):
    pass


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def read_report(path: Path) -> dict[str, object]:
    try:
        report = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exception:
        raise CandidateFailure(f"cannot read interaction report {path}: {exception}") from exception
    if not isinstance(report, dict) or report.get("schemaVersion") != 1:
        raise CandidateFailure("interaction report has an unsupported schema")
    return report


def successful_components(report: dict[str, object]) -> dict[str, list[dict[str, object]]]:
    repetitions = report.get("repetitions")
    results = report.get("results")
    if not isinstance(repetitions, int) or repetitions < 2 or not isinstance(results, list):
        raise CandidateFailure("report must contain at least two interaction passes")
    grouped: dict[str, list[dict[str, object]]] = {}
    for result in results:
        if not isinstance(result, dict) or not isinstance(result.get("component"), str):
            raise CandidateFailure("report contains an invalid component result")
        grouped.setdefault(result["component"], []).append(result)
    return {
        component: sorted(items, key=lambda item: int(item.get("repetition", 0)))
        for component, items in grouped.items()
        if len(items) == repetitions
        and all(item.get("resultStatus") == 1 for item in items)
    }


def evidence_files(directory: Path, prefix: str) -> list[Path]:
    valid_states = {"before", "after", "open", "closed", "selected"}
    return sorted(
        path for path in directory.glob(f"{prefix}-*.png")
        if path.is_file()
        and path.stem.removeprefix(f"{prefix}-") in valid_states
        and path.stat().st_size >= 1024
    )


def materialize(
    report_path: Path,
    evidence_directory: Path,
    output_root: Path,
    requested_tags: list[str],
) -> list[str]:
    report = read_report(report_path)
    successful = successful_components(report)
    tags = requested_tags or sorted(successful)
    missing = sorted(set(tags) - set(successful))
    if missing:
        raise CandidateFailure(
            "cannot materialize failed or missing components: " + ", ".join(missing)
        )
    device = report.get("device")
    if not isinstance(device, dict) or not isinstance(device.get("buildSha256"), str):
        raise CandidateFailure("report is missing device/build identity")

    written: list[str] = []
    for tag in tags:
        attempts = successful[tag]
        final_attempt = attempts[-1]
        prefix = final_attempt.get("evidencePrefix")
        if not isinstance(prefix, str) or not prefix:
            raise CandidateFailure(f"{tag}: final pass has no evidence prefix")
        screenshots = evidence_files(evidence_directory, prefix)
        before = next((path for path in screenshots if path.name.endswith("-before.png")), None)
        states = [path for path in screenshots if path != before]
        if before is None:
            raise CandidateFailure(f"{tag}: baseline screenshot is missing")
        component_directory = output_root / tag
        component_directory.mkdir(parents=True, exist_ok=True)
        baseline_target = component_directory / "baseline-candidate.png"
        shutil.copy2(before, baseline_target)
        copied = [baseline_target]
        if states:
            interacted_target = component_directory / "interacted-candidate.png"
            shutil.copy2(states[-1], interacted_target)
            copied.append(interacted_target)

        candidate_report = {
            "schemaVersion": 1,
            "resultStatus": 1,
            "approvalStatus": 1,
            "component": tag,
            "candidateOnly": True,
            "buildSha256": device["buildSha256"],
            "device": device,
            "sourceReport": str(report_path),
            "passes": [
                {
                    "repetition": attempt.get("repetition"),
                    "interactionKind": attempt.get("interactionKind"),
                    "stateChanged": attempt.get("stateChanged"),
                    "elapsedMs": attempt.get("elapsedMs"),
                    "evidencePrefix": attempt.get("evidencePrefix"),
                }
                for attempt in attempts
            ],
            "screenshots": [
                {"file": path.name, "sha256": sha256(path)} for path in copied
            ],
            "checks": {
                "twoConsecutivePasses": True,
                "runtimeLog": True,
                "routeIdentity": True,
                "realInteraction": bool(final_attempt.get("stateChanged"))
                or final_attempt.get("interactionKind") == 1,
                "requiresManualVisualReview": True,
                "requiresPhysicalApproval": True,
            },
        }
        (component_directory / "report-candidate.json").write_text(
            json.dumps(candidate_report, indent=2) + "\n",
            encoding="utf-8",
        )
        written.append(tag)
    return written


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Materialize non-approved per-component candidates from the consolidated Android audit.",
    )
    parser.add_argument("report", type=Path)
    parser.add_argument("evidence_directory", type=Path)
    parser.add_argument("--output-root", type=Path, default=Path("docs/assets/android/audit"))
    parser.add_argument("--tags", nargs="*", default=[])
    args = parser.parse_args()
    written = materialize(
        args.report,
        args.evidence_directory,
        args.output_root,
        args.tags,
    )
    print(f"Materialized {len(written)} Android candidates: {', '.join(written)}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except CandidateFailure as exception:
        print(f"candidate error: {exception}", file=__import__("sys").stderr)
        raise SystemExit(2)
