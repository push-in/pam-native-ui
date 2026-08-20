import copy
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SPEC = importlib.util.spec_from_file_location("device_evidence", ROOT / "verify-device-evidence.py")
assert SPEC is not None and SPEC.loader is not None
subject = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(subject)


class DeviceEvidenceTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.reference = json.loads(
            (ROOT / "android-sm-s918b-api36-2026-07-24.json").read_text(encoding="utf-8")
        )

    def test_checked_in_reports_satisfy_executable_contract(self):
        reports = sorted(ROOT.glob("android-*.json"))
        self.assertEqual(2, len(reports))
        for report in reports:
            subject.verify(subject.read_report(report), report.name)

    def test_rejects_unknown_fields_missing_measurements_and_string_codes(self):
        for mutate, message in [
            (lambda value: value.update({"unexpected": True}), "missing or unknown fields"),
            (lambda value: value["measurements"].pop("hostUpdate"), "measurement inventory"),
            (lambda value: value.update({"resultCode": "passed"}), "did not pass"),
        ]:
            document = copy.deepcopy(self.reference)
            mutate(document)
            with self.assertRaisesRegex(ValueError, message):
                subject.verify(document, "fixture")

    def test_rejects_failed_tests_budget_regression_and_non_monotonic_quantiles(self):
        document = copy.deepcopy(self.reference)
        document["functionalResultCode"] = 2
        with self.assertRaisesRegex(ValueError, "did not pass"):
            subject.verify(document, "fixture")
        document = copy.deepcopy(self.reference)
        document["measurements"]["hostUpdate"]["p99Us"] = 4_000
        document["measurements"]["hostUpdate"]["maxUs"] = 4_000
        with self.assertRaisesRegex(ValueError, "p99 exceeds"):
            subject.verify(document, "fixture")
        document = copy.deepcopy(self.reference)
        document["measurements"]["sliderMove"]["p95Us"] = 1_000
        with self.assertRaisesRegex(ValueError, "not monotonic"):
            subject.verify(document, "fixture")

    def test_rejects_impossible_date_and_boolean_integer(self):
        document = copy.deepcopy(self.reference)
        document["capturedDate"] = "2026-99-99"
        with self.assertRaisesRegex(ValueError, "invalid capture date"):
            subject.verify(document, "fixture")
        document = copy.deepcopy(self.reference)
        document["apiLevel"] = True
        with self.assertRaisesRegex(ValueError, "expected integer"):
            subject.verify(document, "fixture")

    def test_rejects_coverage_mismatch_and_duplicate_identity(self):
        document = copy.deepcopy(self.reference)
        document["measurementCoverageCode"] = 1
        with self.assertRaisesRegex(ValueError, "quantiles do not match"):
            subject.verify(document, "fixture")
        with self.assertRaisesRegex(ValueError, "duplicate device/API/date"):
            subject.verify_reports([
                ("first", self.reference),
                ("duplicate", copy.deepcopy(self.reference)),
            ])

    def test_accepts_current_inventory_but_rejects_partial_historical_extension(self):
        document = copy.deepcopy(self.reference)
        for name in subject.HISTORICAL_MEASUREMENTS:
            document["measurements"].pop(name)
        subject.verify(document, "current")
        document["measurements"]["imageNavigation"] = {"p50Us": 1, "p95Us": 1, "p99Us": 1, "maxUs": 1}
        with self.assertRaisesRegex(ValueError, "measurement inventory"):
            subject.verify(document, "partial")

    def test_reader_rejects_symlink_and_oversized_report(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            report = root / "report.json"
            report.write_text(json.dumps(self.reference), encoding="utf-8")
            linked = root / "linked.json"
            linked.symlink_to(report)
            with self.assertRaisesRegex(ValueError, "symlink"):
                subject.read_report(linked)
            oversized = root / "oversized.json"
            oversized.write_bytes(b"x" * (subject.MAX_REPORT_BYTES + 1))
            with self.assertRaisesRegex(ValueError, "1 byte to 1 MiB"):
                subject.read_report(oversized)


if __name__ == "__main__":
    unittest.main()
