import json
import re
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import reproducibility as subject


class ReproducibilityTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.primary = self.root / "primary.zip"
        self.rebuild = self.root / "rebuild.zip"
        self.primary.write_bytes(b"identical package")
        self.rebuild.write_bytes(b"identical package")

    def tearDown(self):
        self.temporary.cleanup()

    def test_produces_and_reverifies_exact_artifact(self):
        code = subject.ArtifactCode.IOS_SOURCE_ARCHIVE
        report = subject.produce([(code, self.primary, self.rebuild)])
        output = self.root / "report.json"
        subject.write(output, report)
        subject.verify([(code, self.primary)], output)
        self.assertEqual(subject.ResultCode.PASSED, report["resultCode"])

    def test_mismatch_fails_and_cannot_be_trusted(self):
        self.rebuild.write_bytes(b"different package")
        report = subject.produce([
            (subject.ArtifactCode.ANDROID_AAR, self.primary, self.rebuild)
        ])
        self.assertEqual(subject.ResultCode.MISMATCHED, report["resultCode"])
        with self.assertRaisesRegex(ValueError, "passed integer 1"):
            subject.validate(report)

    def test_rejects_duplicate_codes_tampering_and_wrong_set(self):
        code = subject.ArtifactCode.PHP_SOURCE_ARCHIVE
        item = (code, self.primary, self.rebuild)
        with self.assertRaisesRegex(ValueError, "unique codes"):
            subject.produce([item, item])
        output = self.root / "report.json"
        subject.write(output, subject.produce([item]))
        self.primary.write_bytes(b"tampered")
        with self.assertRaisesRegex(ValueError, "does not match"):
            subject.verify([(code, self.primary)], output)
        with self.assertRaisesRegex(ValueError, "supplied artifact set"):
            subject.verify([(subject.ArtifactCode.IOS_SOURCE_ARCHIVE, self.rebuild)], output)

    def test_rejects_symlinks_unknown_fields_and_string_codes(self):
        linked = self.root / "linked.zip"
        linked.symlink_to(self.primary)
        with self.assertRaisesRegex(ValueError, "regular file"):
            subject.digest(linked, "linked")
        report = subject.produce([
            (subject.ArtifactCode.IOS_SOURCE_ARCHIVE, self.primary, self.rebuild)
        ])
        report["unexpected"] = True
        with self.assertRaisesRegex(ValueError, "root shape"):
            subject.validate(report)
        report.pop("unexpected")
        report["artifacts"][0]["artifactCode"] = "1"
        with self.assertRaisesRegex(ValueError, "codes must be integers"):
            subject.validate(report)

    def test_schema_uses_sequential_integer_codes(self):
        schema = json.loads(Path(__file__).with_name("reproducibility.schema.json").read_text())
        properties = schema["properties"]["artifacts"]["items"]["properties"]
        self.assertEqual((1, 3), (properties["artifactCode"]["minimum"], properties["artifactCode"]["maximum"]))
        self.assertEqual([1, 2], properties["resultCode"]["enum"])

    def test_release_attests_and_reverifies_every_package(self):
        workflow = (Path(__file__).resolve().parents[2] / ".github/workflows/release.yml").read_text()
        for code in range(1, 4):
            self.assertIn(f'--pair "{code}=', workflow)
            self.assertIn(f'--artifact "{code}=', workflow)
        self.assertEqual(2, len(re.findall(r'--output "dist/[^\"]+\.reproducibility\.json"', workflow)))
        publish = workflow.split("  publish:\n", 1)[1]
        self.assertLess(publish.index("Verify downloaded release artifacts"), publish.index("softprops/action-gh-release@v3"))
        self.assertEqual(2, publish.count("--verify-report"))


if __name__ == "__main__":
    unittest.main()
