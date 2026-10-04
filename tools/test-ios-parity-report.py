import importlib.util
import json
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("ios_parity_report", ROOT / "tools/ios-parity-report.py")
REPORT = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(REPORT)


class IosParityReportTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.root = Path(self.directory.name)
        for relative in (
            "resources/material-parity.json",
            "resources/ios-parity-evidence.json",
            "ios/Tests/PamMobileUiTests/PamMobileUiTests.swift",
            "src/Generated/MaterialComponentMap.php",
        ):
            target = self.root / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes((ROOT / relative).read_bytes())

    def tearDown(self):
        self.directory.cleanup()

    def test_reports_all_modules_without_promoting_the_ios_gate(self):
        report = REPORT.build_report(self.root)
        self.assertEqual(report["summary"]["modules"], 92)
        self.assertEqual(report["summary"]["tags"], 114)
        self.assertEqual(report["summary"]["iosImplemented"], 92)
        self.assertEqual(report["summary"]["iosVerified"], 0)
        self.assertGreater(report["summary"]["modulesWithLinkedBehaviorTests"], 0)
        self.assertLess(report["summary"]["modulesWithLinkedBehaviorTests"], 92)

    def test_stale_test_links_fail(self):
        path = self.root / "resources/ios-parity-evidence.json"
        evidence = json.loads(path.read_text())
        evidence["behaviorTests"]["testRemovedUIKitBehavior"] = ["calendar"]
        path.write_text(json.dumps(evidence))
        with self.assertRaisesRegex(ValueError, "Linked UIKit test is absent"):
            REPORT.build_report(self.root)

    def test_public_tag_drift_fails(self):
        path = self.root / "resources/material-parity.json"
        manifest = json.loads(path.read_text())
        manifest["modules"][0]["components"][0] = "p-renamed-app-bar"
        path.write_text(json.dumps(manifest))
        with self.assertRaisesRegex(ValueError, "differ from the generated PHP public map"):
            REPORT.build_report(self.root)


if __name__ == "__main__":
    unittest.main()
