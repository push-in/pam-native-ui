#!/usr/bin/env python3

from __future__ import annotations

import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path


TOOLS = Path(__file__).resolve().parent


def load_module(name: str, filename: str):
    spec = importlib.util.spec_from_file_location(name, TOOLS / filename)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"cannot load {filename}")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


AUTOCOMPLETE = load_module(
    "pam_autocomplete_device_lock_test",
    "audit-autocomplete-android.py",
)
SHOWCASE = load_module(
    "pam_showcase_device_lock_test",
    "audit-showcase-android-interactions.py",
)


class DeviceStateMixin:
    trust_report = ""
    policy_report = ""

    def shell(self, *arguments: str, timeout: float = 20.0) -> str:
        if arguments[:2] == ("dumpsys", "trust"):
            return self.trust_report
        if arguments[:3] == ("dumpsys", "window", "policy"):
            return self.policy_report
        raise AssertionError(f"unexpected shell command: {arguments!r}")


class FakeAutocompleteAudit(DeviceStateMixin, AUTOCOMPLETE.AutocompleteAudit):
    def __init__(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            super().__init__("phone", "package", "activity", Path(directory))


class FakeShowcaseAudit(DeviceStateMixin, SHOWCASE.AndroidAudit):
    def __init__(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            super().__init__("phone", "package", "activity", 0.0, Path(directory))


class AndroidAuditDeviceLockTest(unittest.TestCase):
    def audits(self):
        return (FakeAutocompleteAudit(), FakeShowcaseAudit())

    def test_samsung_trust_state_wins_over_contradictory_legacy_flag(self) -> None:
        for audit in self.audits():
            audit.trust_report = (
                'User "Owner" (id=0) (current): trusted=0, '
                'deviceLocked=0, strongAuthRequired=0x0\n'
            )
            audit.policy_report = (
                "KeyguardServiceDelegate\n"
                "  showing=false\n"
                "  showingAndNotOccluded=true\n"
            )
            self.assertFalse(audit.device_locked())

    def test_strong_auth_lock_is_detected(self) -> None:
        for audit in self.audits():
            audit.trust_report = (
                'User "Owner" (id=0) (current): trusted=0, '
                'deviceLocked=1, strongAuthRequired=0x4\n'
            )
            self.assertTrue(audit.device_locked())

    def test_keyguard_monitor_is_the_legacy_fallback(self) -> None:
        for audit in self.audits():
            audit.trust_report = "trust service unavailable\n"
            audit.policy_report = (
                "KeyguardStateMonitor\n"
                "  mIsShowing=false\n"
                "  showingAndNotOccluded=true\n"
            )
            self.assertFalse(audit.device_locked())

    def test_foreground_package_supports_android_16_top_resumed_format(self) -> None:
        report = (
            "  topResumedActivity=ActivityRecord{127628936 u0 "
            "dev.pam.mobileui.catalog.debug/dev.pam.nativeapp.PamActivity t143}\n"
        )
        for audit in self.audits():
            self.assertEqual(
                "dev.pam.mobileui.catalog.debug",
                audit.foreground_package(report),
            )

    def test_foreground_package_supports_legacy_resumed_format(self) -> None:
        report = (
            "mResumedActivity: ActivityRecord{944df07 u0 "
            "dev.pam.mobileui.catalog.debug/dev.pam.nativeapp.PamActivity t241}\n"
        )
        for audit in self.audits():
            self.assertEqual(
                "dev.pam.mobileui.catalog.debug",
                audit.foreground_package(report),
            )


if __name__ == "__main__":
    unittest.main()
