import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SPEC = importlib.util.spec_from_file_location("capture_device_evidence", ROOT / "capture-device-evidence.py")
assert SPEC is not None and SPEC.loader is not None
subject = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(subject)


class CaptureDeviceEvidenceTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        reference = json.loads(
            (ROOT / "android-sm-s918b-api36-2026-07-24.json").read_text(encoding="utf-8")
        )
        raw = {"device": "samsung Test", "android": 36, "build": "debug"}
        for source_name, target_name in subject.RAW_METRICS.items():
            raw[source_name] = reference["measurements"][target_name]
        raw.update({name: 0 for name in subject.COUNTERS})
        self.logcat = self.root / "logcat.txt"
        self.logcat.write_text(f"I/PamMobileUiBench: {json.dumps(raw)}\n", encoding="utf-8")
        self.junit = self.root / "junit"
        self.junit.mkdir()
        self.junit_xml = self.junit / "TEST-device.xml"
        self.junit_xml.write_text(
            '<testsuite tests="2"><testcase classname="dev.pam.mobileui.OtherTest" name="passes" />'
            '<testcase classname="dev.pam.mobileui.MobileUiHostPerformanceInstrumentedTest" '
            'name="uiThreadLifecycleAndSliderGestureStayInsideTheFrameBudget" /></testsuite>',
            encoding="utf-8",
        )

    def tearDown(self):
        self.temporary.cleanup()

    def test_produces_current_canonical_contract_from_raw_sources(self):
        document = subject.produce(self.logcat, self.junit, "a" * 40, "2026-08-20")
        self.assertEqual(21, len(document["measurements"]))
        self.assertEqual({"passed": 2, "failed": 0}, document["functionalTests"])
        self.assertEqual(2, document["measurementCoverageCode"])
        subject.contract.verify(document, "captured")

    def test_android_producer_and_capture_inventory_remain_locked(self):
        source = (ROOT.parent / "android/src/androidTest/kotlin/dev/pam/mobileui/MobileUiHostPerformanceInstrumentedTest.kt").read_text(encoding="utf-8")
        for name in subject.RAW_METRICS:
            self.assertIn(f'append("\\"{name}\\":', source)
        for name in subject.COUNTERS:
            self.assertIn(f'append("\\"{name}\\":', source)
        self.assertIn("JSONObject.quote", source)

    def test_rejects_duplicate_or_partial_log_payload(self):
        original = self.logcat.read_text(encoding="utf-8")
        self.logcat.write_text(original + original, encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "exactly one"):
            subject.parse_logcat(self.logcat)
        raw = json.loads(original[original.index("{"):])
        raw.pop("update")
        self.logcat.write_text(f"PamMobileUiBench: {json.dumps(raw)}\n", encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "missing or unknown"):
            subject.parse_logcat(self.logcat)

    def test_rejects_failed_missing_and_duplicate_junit_cases(self):
        contents = self.junit_xml.read_text(encoding="utf-8")
        self.junit_xml.write_text(contents.replace(" /></testsuite>", "><failure /></testcase></testsuite>"), encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "failed, errored, or skipped"):
            subject.parse_junit(self.junit)
        self.junit_xml.write_text('<testsuite><testcase classname="Other" name="passes" /></testsuite>', encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "performance benchmark exactly once"):
            subject.parse_junit(self.junit)
        case = '<testcase classname="Other" name="same" />'
        self.junit_xml.write_text(f"<testsuite>{case}{case}</testsuite>", encoding="utf-8")
        with self.assertRaisesRegex(ValueError, "duplicate test cases"):
            subject.parse_junit(self.junit)

    def test_atomic_writer_refuses_to_replace_evidence(self):
        document = subject.produce(self.logcat, self.junit, "a" * 40, "2026-08-20")
        output = self.root / "evidence.json"
        subject.write(output, document)
        self.assertEqual(document, json.loads(output.read_text(encoding="utf-8")))
        with self.assertRaisesRegex(ValueError, "new regular path"):
            subject.write(output, document)


if __name__ == "__main__":
    unittest.main()
