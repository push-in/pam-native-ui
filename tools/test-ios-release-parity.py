import importlib.util
import json
import re
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "check_ios_release_parity", ROOT / "tools/check-ios-release-parity.py"
)
GATE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GATE)


class IosReleaseParityTests(unittest.TestCase):
    def setUp(self):
        self.manifest = json.loads((ROOT / "resources/material-parity.json").read_text())

    def check_copy(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "material-parity.json"
            path.write_text(json.dumps(self.manifest))
            return GATE.check(path)

    def run_copy(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "material-parity.json"
            path.write_text(json.dumps(self.manifest))
            return subprocess.run(
                [sys.executable, str(ROOT / "tools/check-ios-release-parity.py"), str(path)],
                capture_output=True,
                text=True,
                check=False,
            )

    def test_92_implemented_modules_block_release(self):
        for module in self.manifest["modules"]:
            module["verification"][3] = 2
        self.assertEqual(self.check_copy(), (92, 92))
        result = self.run_copy()
        self.assertEqual(result.returncode, 1)
        self.assertIn("92/92 Material modules", result.stderr)

    def test_all_verified_modules_can_pass(self):
        for module in self.manifest["modules"]:
            module["verification"][3] = 3
        self.assertEqual(self.check_copy(), (0, 92))
        self.assertEqual(self.run_copy().returncode, 0)

    def test_not_applicable_ios_status_cannot_pass(self):
        for module in self.manifest["modules"]:
            module["verification"][3] = 3
        self.manifest["modules"][0]["verification"][3] = 4
        self.assertEqual(self.check_copy(), (1, 92))

    def test_release_workflow_has_no_ios_ci_override(self):
        workflow = (ROOT / ".github/workflows/release.yml").read_text()
        self.assertNotIn("PAM_CI_VERIFIED_GATES", workflow)
        self.assertIn("python3 tools/check-ios-release-parity.py", workflow)
        for job_name in ("ecosystem-compatibility", "android-minimum", "ios-minimum"):
            section = re.search(
                rf"(?ms)^  {job_name}:\n(.*?)(?=^  [a-z-]+:\n|\Z)", workflow
            )
            self.assertIsNotNone(section, job_name)
            self.assertIn("    needs: release-parity", section.group(1))
        self.assertRegex(workflow, r"(?ms)^  package:\n.*?^      - ecosystem-compatibility$")
        self.assertRegex(workflow, r"(?ms)^  publish:\n.*?^      - package$")


if __name__ == "__main__":
    unittest.main()
