#!/usr/bin/env python3
"""Android end-to-end check: every PAM Native UI input round-trips to PHP.

Drives the showcase app (examples/showcase) on a device or emulator with adb
and UI Automator, then reads the PHP state back from the screen: the Controls
screen prints its component state as JSON in an element labelled
"php-state", re-rendered by PHP after every change.

    python3 tools/e2e_android.py --serial emulator-5554 \
        --package dev.pam.nativeui.showcase.debug [--shots docs/assets/showcase]

Exit status 0 means every input reached PHP and came back on screen.
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET


class Device:
    def __init__(self, serial: str, package: str) -> None:
        self.serial = serial
        self.package = package

    def adb(self, *args: str, capture: bool = True) -> str:
        result = subprocess.run(
            ["adb", "-s", self.serial, *args],
            check=True,
            capture_output=capture,
            text=True,
        )
        return result.stdout if capture else ""

    def launch(self) -> None:
        self.adb("shell", "am", "force-stop", self.package)
        self.adb("shell", "monkey", "-p", self.package, "-c", "android.intent.category.LAUNCHER", "1")
        time.sleep(3)
        # The app restores its last route; go back to the catalog.
        self.home()

    def nodes(self) -> list[dict[str, str]]:
        for _ in range(5):
            raw = self.adb("exec-out", "uiautomator", "dump", "/dev/tty")
            start = raw.find("<?xml")
            end = raw.rfind("</hierarchy>")
            if start >= 0 and end > start:
                root = ET.fromstring(raw[start : end + len("</hierarchy>")])
                return [dict(node.attrib) for node in root.iter("node")]
            time.sleep(0.3)
        raise RuntimeError("uiautomator dump failed")

    def find(self, text: str | None = None, desc: str | None = None, prefix: bool = False) -> dict[str, str] | None:
        for node in self.nodes():
            value = node.get("text", "") if text is not None else node.get("content-desc", "")
            wanted = text if text is not None else desc
            if (value.startswith(wanted) if prefix else value == wanted) and node.get("bounds"):
                return node
        return None

    def wait_for(self, text: str | None = None, desc: str | None = None, timeout: float = 8, prefix: bool = False) -> dict[str, str]:
        deadline = time.time() + timeout
        while time.time() < deadline:
            node = self.find(text=text, desc=desc, prefix=prefix)
            if node is not None:
                return node
            time.sleep(0.25)
        raise AssertionError(f"not on screen: text={text!r} desc={desc!r}")

    @staticmethod
    def center(node: dict[str, str]) -> tuple[int, int]:
        x1, y1, x2, y2 = map(int, re.findall(r"\d+", node["bounds"]))
        return (x1 + x2) // 2, (y1 + y2) // 2

    def tap(self, text: str | None = None, desc: str | None = None, prefix: bool = False) -> None:
        node = self.wait_for(text=text, desc=desc, prefix=prefix)
        x, y = self.center(node)
        self.adb("shell", "input", "tap", str(x), str(y))
        time.sleep(0.6)

    def type(self, value: str) -> None:
        self.adb("shell", "input", "text", value.replace(" ", "%s"))
        time.sleep(0.8)

    def home(self) -> None:
        for _ in range(5):
            if self.find(text="Every PAM Native UI component, on device.") is not None:
                return
            self.back()
        self.wait_for(text="Every PAM Native UI component, on device.", timeout=30)

    def back(self) -> None:
        self.adb("shell", "input", "keyevent", "4")
        time.sleep(0.8)

    def scroll_to(self, text: str | None = None, desc: str | None = None, prefix: bool = False, attempts: int = 6) -> None:
        for _ in range(attempts):
            if self.find(text=text, desc=desc, prefix=prefix) is not None:
                return
            self.adb("shell", "input", "swipe", "540", "1700", "540", "900", "300")
            time.sleep(0.6)
        raise AssertionError(f"could not scroll to text={text!r} desc={desc!r}")

    def state(self) -> dict:
        self.scroll_to(desc="php-state")
        node = self.wait_for(desc="php-state")
        return json.loads(node["text"])

    def screenshot(self, path: str) -> None:
        with open(path, "wb") as handle:
            subprocess.run(["adb", "-s", self.serial, "exec-out", "screencap", "-p"], check=True, stdout=handle)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.nativeui.showcase.debug")
    parser.add_argument("--shots", default=None, help="directory for screenshots")
    args = parser.parse_args()
    device = Device(args.serial, args.package)
    results: list[tuple[str, bool, str]] = []

    def check(name: str, condition: bool, detail: str = "") -> None:
        results.append((name, condition, detail))
        print(("PASS " if condition else "FAIL ") + name + (f" ({detail})" if detail else ""), flush=True)

    device.launch()
    device.tap(text="Controls")
    device.wait_for(text="Controls")
    top = lambda: device.adb("shell", "input", "swipe", "540", "700", "540", "1900", "200")

    # Text tab: text field, textarea, date.
    device.tap(desc="Text field")
    device.type("hello pam")
    device.back()
    check("text field", device.state()["text"] == "hello pam")
    top()
    device.tap(desc="Textarea")
    device.type("multi line")
    device.back()
    check("textarea", device.state()["notes"] == "multi line")
    top()
    device.tap(desc="Date, not set")
    today = dt.date.today()
    device.tap(desc=today.strftime("%A, %B ") + str(today.day) + today.strftime(", %Y"))
    check("date input", device.state()["date"] == today.isoformat(), device.state()["date"] or "")

    # Choice tab.
    top()
    device.tap(desc="Choice")
    device.tap(desc="Checkbox")
    check("checkbox", device.state()["checked"] is True)
    top()
    device.tap(desc="Switch")
    check("switch", device.state()["toggled"] is False)
    top()
    device.tap(desc="Free")
    check("radio group", device.state()["plan"] == "free")
    top()
    device.tap(desc="Select, none selected")
    device.tap(desc="Brazil")
    check("select", device.state()["country"] == "br")
    top()
    device.scroll_to(desc="Multi-select, none selected")
    device.tap(desc="Multi-select, none selected")
    device.tap(desc="PHP")
    device.tap(desc="Rust")
    device.tap(desc="Done")
    check("multi-select", device.state()["tags"] == ["php", "rust"], json.dumps(device.state()["tags"]))
    top()
    device.scroll_to(desc="Filter chip")
    device.tap(desc="Filter chip")
    check("chip", device.state()["chip"] is False)

    # Range tab.
    top()
    device.tap(desc="Range")
    slider = device.wait_for(desc="Slider")
    x1, y1, x2, y2 = map(int, re.findall(r"\d+", slider["bounds"]))
    y = (y1 + y2) // 2
    device.adb("shell", "input", "swipe", str(x1 + (x2 - x1) * 2 // 5), str(y), str(x1 + (x2 - x1) * 9 // 10), str(y), "500")
    time.sleep(0.8)
    volume = device.state()["volume"]
    check("slider", volume > 40, str(volume))
    top()
    device.tap(desc="Increase")
    check("stepper", device.state()["count"] == 3)
    top()
    device.tap(desc="Month")
    check("segmented", device.state()["period"] == "month")
    if args.shots:
        top()
        device.screenshot(f"{args.shots}/controls.png")

    # Forms: validation errors, then a successful submit.
    device.back()
    device.tap(text="Forms")
    device.tap(desc="Create account", prefix=False)
    device.wait_for(text="Fix the highlighted fields and try again.")
    check("form errors after submit", device.find(text="Enter your email.") is not None)
    if args.shots:
        device.screenshot(f"{args.shots}/forms-errors.png")
    device.tap(desc="Name, required")
    device.type("Ada Lovelace")
    device.tap(desc="Email, required")
    device.type("ada@pam.dev")
    device.tap(desc="Password, required")
    device.type("analytical")
    device.back()
    device.tap(desc="Role, required, none selected")
    device.tap(desc="Developer")
    device.scroll_to(desc="I accept the terms")
    device.tap(desc="I accept the terms")
    device.tap(desc="Create account")
    top()
    device.wait_for(text="Account created for ada@pam.dev", timeout=6)
    check("form submit", True)

    failed = [name for name, ok, _ in results if not ok]
    print(json.dumps({"passed": len(results) - len(failed), "failed": failed}), flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
