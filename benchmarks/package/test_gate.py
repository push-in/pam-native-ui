from __future__ import annotations

import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent
GATE = ROOT / "gate.py"


class PackageBudgetTests(unittest.TestCase):
    def run_gate(self, *arguments: str) -> subprocess.CompletedProcess[str]:
        return subprocess.run(
            [sys.executable, str(GATE), *arguments],
            text=True,
            capture_output=True,
            check=False,
        )

    def budgets(self, root: Path, maximum: int) -> Path:
        path = root / "budgets.json"
        path.write_text(
            json.dumps(
                {
                    "schemaVersion": 1,
                    "budgets": [
                        {"artifactCode": code, "maximumBytes": maximum}
                        for code in range(1, 4)
                    ],
                }
            ),
            encoding="utf-8",
        )
        return path

    def test_emits_and_reverifies_bounded_integer_evidence(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            budgets = self.budgets(root, 16)
            artifact = root / "package.zip"
            artifact.write_bytes(b"package")
            report = root / "report.json"
            produced = self.run_gate(
                "--budgets", str(budgets),
                "--artifact", f"1={artifact}",
                "--output", str(report),
            )
            verified = self.run_gate(
                "--budgets", str(budgets),
                "--artifact", f"1={artifact}",
                "--verify-report", str(report),
            )
        self.assertEqual(0, produced.returncode, produced.stderr)
        self.assertEqual(0, verified.returncode, verified.stderr)
        document = json.loads(produced.stdout)
        self.assertEqual(1, document["resultCode"])
        self.assertEqual(1, document["artifacts"][0]["artifactCode"])

    def test_reports_an_exceeded_budget(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            budgets = self.budgets(root, 4)
            artifact = root / "package.aar"
            artifact.write_bytes(b"too large")
            result = self.run_gate(
                "--budgets", str(budgets), "--artifact", f"2={artifact}"
            )
        self.assertEqual(1, result.returncode)
        self.assertEqual(2, json.loads(result.stdout)["resultCode"])

    def test_rejects_duplicates_symlinks_and_tampering(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            budgets = self.budgets(root, 32)
            artifact = root / "package"
            artifact.write_bytes(b"package")
            linked = root / "linked"
            linked.symlink_to(artifact)
            duplicate = self.run_gate(
                "--budgets", str(budgets),
                "--artifact", f"3={artifact}",
                "--artifact", f"3={artifact}",
            )
            symlink = self.run_gate(
                "--budgets", str(budgets), "--artifact", f"3={linked}"
            )
            report = root / "report.json"
            created = self.run_gate(
                "--budgets", str(budgets),
                "--artifact", f"3={artifact}",
                "--output", str(report),
            )
            artifact.write_bytes(b"tampered")
            tampered = self.run_gate(
                "--budgets", str(budgets),
                "--artifact", f"3={artifact}",
                "--verify-report", str(report),
            )
        self.assertNotEqual(0, duplicate.returncode)
        self.assertIn("unique codes", duplicate.stderr)
        self.assertNotEqual(0, symlink.returncode)
        self.assertIn("regular file", symlink.stderr)
        self.assertEqual(0, created.returncode, created.stderr)
        self.assertNotEqual(0, tampered.returncode)
        self.assertIn("stale", tampered.stderr)

    def test_contract_defines_all_sequential_codes(self):
        budgets = json.loads((ROOT / "budgets.json").read_text(encoding="utf-8"))
        self.assertEqual([1, 2, 3], [item["artifactCode"] for item in budgets["budgets"]])
        schema = json.loads((ROOT / "package-budget.schema.json").read_text(encoding="utf-8"))
        code = schema["properties"]["artifacts"]["items"]["properties"]["artifactCode"]
        self.assertEqual((1, 3), (code["minimum"], code["maximum"]))

    def test_release_gates_every_artifact_before_publication(self):
        workflow = (ROOT.parents[1] / ".github/workflows/release.yml").read_text()
        for code in range(1, 4):
            self.assertIn(f'--artifact "{code}=', workflow)
        publish = workflow.split("  publish:\n", 1)[1]
        self.assertLess(
            publish.index("Verify downloaded release artifacts"),
            publish.index("softprops/action-gh-release@v3"),
        )


if __name__ == "__main__":
    unittest.main()
