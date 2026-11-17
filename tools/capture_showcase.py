#!/usr/bin/env python3
"""Captures the showcase screens in light and dark on Android.

    python3 tools/capture_showcase.py --serial emulator-5554 --out docs/assets/showcase
"""

from __future__ import annotations

import argparse
import os
import sys
import time

sys.path.insert(0, os.path.dirname(__file__))
from e2e_android import Device  # noqa: E402


def capture(device: Device, out: str, mode: str) -> None:
    device.launch()
    device.tap(desc=mode.capitalize())
    time.sleep(1.2)
    device.screenshot(f"{out}/{mode}-catalog.png")
    device.tap(text="Forms")
    device.tap(desc="Create account")
    time.sleep(0.8)
    device.screenshot(f"{out}/{mode}-forms.png")
    device.back()
    device.tap(text="Controls")
    device.tap(desc="Choice")
    time.sleep(0.6)
    device.screenshot(f"{out}/{mode}-controls.png")
    device.tap(desc="Select, none selected")
    time.sleep(1.0)
    device.screenshot(f"{out}/{mode}-select.png")
    device.home()
    device.scroll_to(text="Overlays")
    device.tap(text="Overlays")
    device.tap(desc="Open filters")
    time.sleep(1.0)
    device.screenshot(f"{out}/{mode}-sheet.png")
    device.home()
    device.scroll_to(text="Feedback")
    device.tap(text="Feedback")
    time.sleep(0.8)
    device.screenshot(f"{out}/{mode}-feedback.png")
    device.home()
    device.scroll_to(text="Lists")
    device.tap(text="Lists")
    time.sleep(1.0)
    device.screenshot(f"{out}/{mode}-lists.png")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.nativeui.showcase.debug")
    parser.add_argument("--out", required=True)
    args = parser.parse_args()
    os.makedirs(args.out, exist_ok=True)
    device = Device(args.serial, args.package)
    for mode in ("light", "dark"):
        capture(device, args.out, mode)
    return 0


if __name__ == "__main__":
    sys.exit(main())
