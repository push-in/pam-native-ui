#!/usr/bin/env python3

from __future__ import annotations

import copy
import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path


TOOLS = Path(__file__).resolve().parent
ROOT = TOOLS.parent
SPEC = importlib.util.spec_from_file_location(
    "pam_android_component_approval_validator",
    TOOLS / "validate-android-component-approvals.py",
)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("cannot load Android component approval validator")
VALIDATOR = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = VALIDATOR
SPEC.loader.exec_module(VALIDATOR)


class AndroidComponentApprovalValidatorTest(unittest.TestCase):
    def test_current_approved_evidence_is_complete(self) -> None:
        self.assertEqual(
            5,
            VALIDATOR.validate(ROOT, ROOT / "docs/android-component-audit.json"),
        )

    def test_emulator_identity_cannot_be_approved(self) -> None:
        evidence = {
            "device": "Google sdk_gphone64_x86_64",
            "androidVersion": 16,
            "androidApi": 36,
            "viewport": "1080x2340@440dpi",
        }
        report = {
            "device": {
                "manufacturer": "Google",
                "model": "sdk_gphone64_x86_64",
                "androidVersion": 16,
                "api": 36,
                "viewport": "1080x2340@440dpi",
            }
        }
        with self.assertRaisesRegex(VALIDATOR.ApprovalFailure, "emulator evidence"):
            VALIDATOR.validate_device("p-test", evidence, report)

    def test_duplicate_pass_reports_cannot_be_approved(self) -> None:
        digest = "a" * 64
        evidence = {
            "passes": [
                {"index": 1, "rawReportSha256": digest},
                {"index": 2, "rawReportSha256": digest},
            ]
        }
        report = {
            "passes": [
                {
                    "index": 1,
                    "resultStatus": int(VALIDATOR.ResultStatus.PASSED),
                    "rawReportSha256": digest,
                    "geometryFailureCount": 0,
                },
                {
                    "index": 2,
                    "resultStatus": int(VALIDATOR.ResultStatus.PASSED),
                    "rawReportSha256": digest,
                    "geometryFailureCount": 0,
                },
            ]
        }
        with self.assertRaisesRegex(VALIDATOR.ApprovalFailure, "same report"):
            VALIDATOR.validate_passes("p-test", evidence, report)

    def test_failed_integer_status_cannot_be_approved(self) -> None:
        manifest = VALIDATOR.read_json(ROOT / "docs/android-component-audit.json")
        evidence = copy.deepcopy(manifest["componentEvidence"]["p-slider"])
        report_path = ROOT / evidence["report"]
        report = VALIDATOR.read_json(report_path)
        report["passes"][0]["resultStatus"] = int(VALIDATOR.ResultStatus.FAILED)
        with self.assertRaisesRegex(VALIDATOR.ApprovalFailure, "not explicitly passed"):
            VALIDATOR.validate_passes("p-slider", evidence, report)

    def test_repository_path_cannot_escape_root(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with self.assertRaisesRegex(VALIDATOR.ApprovalFailure, "escapes"):
                VALIDATOR.repository_file(root, "../evidence.json", "evidence")


if __name__ == "__main__":
    unittest.main()
