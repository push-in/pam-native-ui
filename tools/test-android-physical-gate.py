#!/usr/bin/env python3

from __future__ import annotations

import json
import os
import stat
import subprocess
import sys
import tempfile
import textwrap
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
GATE = ROOT / "tools/run-android-component-physical-gate.sh"
HARNESS = ROOT / "tools/audit-range-slider-android.py"


class AndroidPhysicalGateTest(unittest.TestCase):
    def executable(self, path: Path, source: str) -> None:
        path.write_text(
            f"#!{sys.executable}\n" + textwrap.dedent(source),
            encoding="utf-8",
        )
        path.chmod(path.stat().st_mode | stat.S_IXUSR)

    def fixture(self, directory: Path, *, emulator: bool = False) -> tuple[dict[str, str], Path]:
        fake_bin = directory / "bin"
        fake_bin.mkdir()
        apk = directory / "catalog-release.apk"
        apk.write_bytes(b"PAM physical gate fixture\n")

        self.executable(
            fake_bin / "adb",
            """
            import os
            import shutil
            import sys

            arguments = sys.argv[1:]
            if arguments[:1] == ["-s"]:
                arguments = arguments[2:]
            if arguments == ["get-state"]:
                print("device")
            elif arguments[:2] == ["shell", "getprop"]:
                values = {
                    "ro.kernel.qemu": os.environ.get("PAM_FAKE_QEMU", "0"),
                    "ro.product.manufacturer": "Google" if os.environ.get("PAM_FAKE_QEMU") == "1" else "Samsung",
                    "ro.product.model": "sdk_gphone64_x86_64" if os.environ.get("PAM_FAKE_QEMU") == "1" else "SM-G973F",
                    "ro.product.cpu.abi": "arm64-v8a",
                    "ro.build.version.release": "12",
                    "ro.build.version.sdk": "31",
                }
                print(values[arguments[2]])
            elif arguments[:2] == ["install", "-r"]:
                print("Success")
            elif arguments[:3] == ["shell", "pm", "path"]:
                print("package:/data/app/fake/base.apk")
            elif arguments[:1] == ["pull"]:
                shutil.copyfile(os.environ["PAM_FAKE_APK"], arguments[2])
            elif arguments[:3] == ["shell", "wm", "size"]:
                print("Physical size: 1080x2280")
            elif arguments[:3] == ["shell", "wm", "density"]:
                print("Physical density: 420")
            else:
                raise SystemExit(f"unexpected fake adb arguments: {arguments!r}")
            """,
        )
        self.executable(
            fake_bin / "aapt",
            """
            print("package: name='dev.pam.mobileui.catalog' versionCode='1' versionName='0.1.0'")
            print("native-code: 'arm64-v8a'")
            """,
        )
        self.executable(
            fake_bin / "python3",
            """
            import json
            import sys
            from pathlib import Path

            arguments = sys.argv[2:]
            serial = arguments[arguments.index("--serial") + 1]
            package = arguments[arguments.index("--package") + 1]
            output = Path(arguments[arguments.index("--output") + 1])
            output.mkdir(parents=True)
            report = {
                "schemaVersion": 2,
                "component": "p-range-slider",
                "device": serial,
                "package": package,
                "resultStatus": 1,
                "checks": {"fixturePassed": True},
                "evidence": [{"path": str(output)}],
            }
            (output / "report.json").write_text(json.dumps(report) + "\\n", encoding="utf-8")
            """,
        )
        environment = os.environ.copy()
        environment["PATH"] = f"{fake_bin}:{environment['PATH']}"
        environment["PAM_FAKE_APK"] = str(apk)
        environment["PAM_FAKE_QEMU"] = "1" if emulator else "0"
        return environment, apk

    def test_physical_device_produces_two_distinct_passes(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            directory = Path(temporary)
            environment, apk = self.fixture(directory)
            output = directory / "evidence"
            result = subprocess.run(
                [
                    "bash",
                    str(GATE),
                    "p-range-slider",
                    "physical-samsung",
                    str(apk),
                    "dev.pam.mobileui.catalog",
                    str(HARNESS),
                    str(output),
                ],
                cwd=ROOT,
                env=environment,
                check=False,
                capture_output=True,
                text=True,
            )
            self.assertEqual(0, result.returncode, result.stderr)
            gate = json.loads((output / "two-pass-gate.json").read_text(encoding="utf-8"))
            self.assertEqual(1, gate["resultStatus"])
            self.assertEqual("Samsung", gate["device"]["manufacturer"])
            self.assertEqual("SM-G973F", gate["device"]["model"])
            self.assertEqual("1080x2280@420dpi", gate["device"]["viewport"])
            self.assertNotEqual(
                gate["passes"][0]["rawReportSha256"],
                gate["passes"][1]["rawReportSha256"],
            )
            self.assertEqual(
                ["manualVisualReview", "realInteractionRecording"],
                gate["approvalPending"],
            )

    def test_emulator_identity_is_rejected_before_evidence_is_created(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            directory = Path(temporary)
            environment, apk = self.fixture(directory, emulator=True)
            output = directory / "evidence"
            result = subprocess.run(
                [
                    "bash",
                    str(GATE),
                    "p-range-slider",
                    "emulator-5554",
                    str(apk),
                    "dev.pam.mobileui.catalog",
                    str(HARNESS),
                    str(output),
                ],
                cwd=ROOT,
                env=environment,
                check=False,
                capture_output=True,
                text=True,
            )
            self.assertEqual(69, result.returncode)
            self.assertIn("refuses emulator identity", result.stderr)
            self.assertFalse(output.exists())


if __name__ == "__main__":
    unittest.main()
