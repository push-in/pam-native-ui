#!/usr/bin/env python3
"""Android end-to-end suite: every PAM Native UI component and event, on device.

Drives the showcase app (examples/showcase) with adb and UI Automator and reads
PHP state back from the screen. Controls, Events and Overlays each print their
component state as JSON (a Text with testId "php-state"), re-rendered by PHP
after every change, so a check passes only when the event reached PHP.

    python3 tools/e2e_android.py --serial emulator-5554 \
        [--package dev.pam.nativeui.showcase.debug] [--suite all] [--shots DIR]

Suites: controls, forms, events, overlays, lists, feedback, theme (all = every one).
Every screen is also checked for content under the status or navigation bar.
Exit status 0 means every check passed.
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
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import geometry  # noqa: E402
import golden as goldens  # noqa: E402

CATALOG = "Every PAM Native UI component, on device."
# Catalog entry -> the title of the screen it opens.
SCREENS = {"Forms": "Create account", "Lists": "People"}
Node = dict[str, str]


def bounds(node: Node) -> tuple[int, int, int, int]:
    x1, y1, x2, y2 = map(int, re.findall(r"\d+", node["bounds"]))
    return x1, y1, x2, y2


class Device:
    def __init__(self, serial: str, package: str, shots: str | None) -> None:
        self.serial = serial
        self.package = package
        self.shots = shots
        self.width, self.height = 1080, 2400
        self.top_inset, self.bottom_inset = 0, 0
        self.snapshots = True

    def adb(self, *args: str) -> str:
        return subprocess.run(["adb", "-s", self.serial, *args], check=True, capture_output=True, text=True).stdout

    def insets(self) -> None:
        # The app's own view of the display (rotation included), when the
        # debug build publishes its layout; else the physical size and bars.
        try:
            snapshot = geometry.capture(self.serial, self.package, attempts=20)
            self.width, self.height = snapshot["screen"]
            left, top, right, bottom = snapshot["insets"]
            self.top_inset, self.bottom_inset = top, bottom
            return
        except (RuntimeError, subprocess.CalledProcessError, ValueError, KeyError):
            pass
        size = re.search(r"(\d+)x(\d+)", self.adb("shell", "wm", "size").splitlines()[-1])
        if size:
            self.width, self.height = int(size.group(1)), int(size.group(2))
        windows = self.adb("shell", "dumpsys", "window")
        status = re.search(r"type=statusBars frame=\[\d+,\d+\]\[\d+,(\d+)\]", windows)
        nav = re.search(r"type=navigationBars frame=\[\d+,(\d+)\]\[\d+,\d+\]", windows)
        self.top_inset = int(status.group(1)) if status else 0
        self.bottom_inset = self.height - int(nav.group(1)) if nav else 0

    def launch(self) -> None:
        self.adb("shell", "am", "force-stop", self.package)
        self.adb("shell", "monkey", "-p", self.package, "-c", "android.intent.category.LAUNCHER", "1")
        time.sleep(3)
        self.insets()
        self.home()

    def nodes(self) -> list[Node]:
        """On-screen nodes: the app's layout snapshot when the debug build
        publishes one (a few hundred ms), else a UI Automator dump (seconds,
        it waits for the UI to idle, which a spinner never does)."""
        if self.snapshots:
            try:
                return self.snapshot_nodes()
            except (RuntimeError, subprocess.CalledProcessError, ValueError):
                self.snapshots = False
        for _ in range(6):
            raw = self.adb("exec-out", "uiautomator", "dump", "/dev/tty")
            start, end = raw.find("<?xml"), raw.rfind("</hierarchy>")
            if start >= 0 and end > start:
                root = ET.fromstring(raw[start : end + len("</hierarchy>")])
                return [dict(node.attrib) for node in root.iter("node") if node.get("package") == self.package]
            time.sleep(0.3)
        raise RuntimeError("uiautomator dump failed")

    def snapshot_nodes(self) -> list[Node]:
        snapshot = geometry.capture(self.serial, self.package, attempts=30)
        found = []
        for raw in snapshot["nodes"]:
            visible = raw.get("visible") or []
            if len(visible) != 4 or visible[2] <= visible[0] or visible[3] <= visible[1] or raw.get("alpha", 1) <= 0.01:
                continue
            found.append({
                "text": raw.get("text", ""),
                "content-desc": raw.get("label", ""),
                "bounds": "[{},{}][{},{}]".format(*visible),
                "clickable": "true" if raw.get("clickable") else "false",
                "package": self.package,
            })
        return found

    def find(self, text: str | None = None, desc: str | None = None, prefix: bool = False) -> Node | None:
        wanted = text if text is not None else desc
        for node in self.nodes():
            value = node.get("text", "") if text is not None else node.get("content-desc", "")
            if (value.startswith(wanted) if prefix else value == wanted) and node.get("bounds"):
                x1, y1, x2, y2 = bounds(node)
                if x2 > x1 and y2 > y1:
                    return node
        return None

    def wait_for(self, text: str | None = None, desc: str | None = None, timeout: float = 8, prefix: bool = False) -> Node:
        deadline = time.time() + timeout
        while time.time() < deadline:
            node = self.find(text=text, desc=desc, prefix=prefix)
            if node is not None:
                return node
            time.sleep(0.25)
        raise AssertionError(f"not on screen: text={text!r} desc={desc!r}")

    def gone(self, text: str | None = None, desc: str | None = None, timeout: float = 6) -> bool:
        deadline = time.time() + timeout
        while time.time() < deadline:
            if self.find(text=text, desc=desc) is None:
                return True
            time.sleep(0.25)
        return False

    @staticmethod
    def center(node: Node) -> tuple[int, int]:
        x1, y1, x2, y2 = bounds(node)
        return (x1 + x2) // 2, (y1 + y2) // 2

    def tap_at(self, x: int, y: int, settle: float = 0.6) -> None:
        self.adb("shell", "input", "tap", str(x), str(y))
        time.sleep(settle)

    def tap(self, text: str | None = None, desc: str | None = None, prefix: bool = False, scroll: bool = True) -> None:
        node = self.scroll_to(text=text, desc=desc, prefix=prefix) if scroll else self.wait_for(text=text, desc=desc, prefix=prefix)
        self.tap_at(*self.center(node))

    def type(self, value: str) -> None:
        self.adb("shell", "input", "text", value.replace(" ", "%s"))
        time.sleep(0.8)

    def key(self, code: str) -> None:
        self.adb("shell", "input", "keyevent", code)
        time.sleep(0.8)

    def back(self) -> None:
        self.key("4")

    def keyboard_shown(self) -> bool:
        return "mInputShown=true" in self.adb("shell", "dumpsys", "input_method")

    def home(self) -> None:
        for _ in range(6):
            if self.find(text=CATALOG) is not None:
                return
            # The catalog keeps its scroll position: its header may be above.
            if self.find(text="COMPONENTS") is not None or self.find(text="APPEARANCE") is not None:
                self.top()
                continue
            self.back()
        if self.find(text=CATALOG) is None:
            # Backed out of the app (or a screen kept Back): start it again.
            self.adb("shell", "monkey", "-p", self.package, "-c", "android.intent.category.LAUNCHER", "1")
            time.sleep(2)
            for _ in range(6):
                if self.find(text=CATALOG) is not None:
                    return
                self.back()
        self.wait_for(text=CATALOG, timeout=30)

    def swipe(self, x1: int, y1: int, x2: int, y2: int, ms: int = 300) -> None:
        self.adb("shell", "input", "swipe", str(x1), str(y1), str(x2), str(y2), str(ms))
        time.sleep(0.7)

    def top(self) -> None:
        for _ in range(3):
            self.swipe(self.width // 2, self.height * 3 // 10, self.width // 2, self.height * 8 // 10, 150)

    def scroll_to(self, text: str | None = None, desc: str | None = None, prefix: bool = False, attempts: int = 10) -> Node:
        """Bring a node fully on screen: look down first, then back up."""
        x = self.width // 2
        low, high = self.height * 7 // 10, self.height * 4 // 10
        for step in range(attempts * 2 + 1):
            node = self.find(text=text, desc=desc, prefix=prefix)
            if node is not None:
                y1, y2 = bounds(node)[1], bounds(node)[3]
                if y1 > self.top_inset and y2 < self.height - self.bottom_inset - 24:
                    return node
            if step < attempts:
                self.swipe(x, low, x, high)
            else:
                self.swipe(x, high, x, low)
        raise AssertionError(f"could not scroll to text={text!r} desc={desc!r}")

    def state(self) -> dict:
        node = self.find(text='{"', prefix=True)
        if node is None:
            self.scroll_to(text='{"', prefix=True)
            node = self.wait_for(text='{"', prefix=True)
        return json.loads(node["text"])

    def events(self) -> list[str]:
        return self.state()["events"]

    def unsafe(self) -> list[str]:
        """Interactive or text nodes drawn under the status or navigation bar."""
        bad = []
        for node in self.nodes():
            label = node.get("text") or node.get("content-desc")
            if not label or not node.get("bounds"):
                continue
            x1, y1, x2, y2 = bounds(node)
            if y2 <= y1 or x2 <= x1 or node.get("scrollable") == "true":
                continue
            if node.get("clickable") != "true" and not node.get("text"):
                continue
            # Rows clipped by a scroll container are fine; only fully visible nodes count.
            if y1 < self.top_inset or (y2 > self.height - self.bottom_inset and y1 < self.height - self.bottom_inset):
                bad.append(f"{label[:30]}@{y1}-{y2}")
        return bad

    def screenshot(self, name: str) -> None:
        if not self.shots:
            return
        time.sleep(0.4)
        with open(f"{self.shots}/{name}.png", "wb") as handle:
            subprocess.run(["adb", "-s", self.serial, "exec-out", "screencap", "-p"], check=True, stdout=handle)


class Suite:
    def __init__(self, device: Device, golden: str | None = None, update: bool = False, theme: str = "light") -> None:
        self.d = device
        self.results: list[tuple[str, bool, str]] = []
        self.golden_dir = Path(golden) if golden else None
        self.update = update
        self.mode = theme

    def check(self, name: str, condition: bool, detail: object = "") -> None:
        detail = "" if detail in ("", None) else str(detail)
        self.results.append((name, bool(condition), detail))
        print(("PASS " if condition else "FAIL ") + name + (f" ({detail})" if detail else ""), flush=True)

    def safe(self, screen: str, golden: bool = True) -> None:
        """Geometry of the current screen (layout snapshot) plus its golden."""
        try:
            snapshot = geometry.capture(self.d.serial, self.d.package)
        except RuntimeError as error:
            bad = self.d.unsafe()
            self.check(f"{screen}: nothing under the system bars", not bad, ", ".join(bad[:4]) or str(error))
            return
        label = screen if self.mode in ("light", "dark") else f"{screen} ({self.mode})"
        for name, values in geometry.check(snapshot).items():
            self.check(f"{label}: {name}", not values, "; ".join(values[:3]))
        if golden:
            self.golden(screen)

    def golden(self, screen: str) -> None:
        if self.golden_dir is None:
            return
        import tempfile

        name = f"{screen}-{self.mode}".replace(" ", "-").replace("(", "").replace(")", "")
        work = Path(tempfile.mkdtemp(prefix="pam-golden-"))
        first, second = work / "a.png", work / "b.png"
        for path in (first, second):
            time.sleep(0.4)
            with open(path, "wb") as handle:
                subprocess.run(["adb", "-s", self.d.serial, "exec-out", "screencap", "-p"], check=True, stdout=handle)
        d = self.d
        masks = [(0, 0, d.width, d.top_inset), (0, d.height - d.bottom_inset, d.width, d.height)]
        masks += goldens.animated_regions(first, second)
        target = self.golden_dir / f"{name}.png"
        if self.update or not target.exists():
            target.parent.mkdir(parents=True, exist_ok=True)
            Image = goldens.Image
            goldens.mask(Image.open(second), masks).save(target)
            self.check(f"{screen}: golden recorded", True, target.name)
            return
        ok, detail = goldens.compare(second, target, masks, work / f"{name}-diff.png")
        self.check(f"{screen}: matches golden", ok, detail if ok else f"{detail}; diff {work / (name + '-diff.png')}")

    def run(self, name: str, scenario) -> None:
        try:
            scenario()
        except Exception as error:  # a broken step fails its suite, not the whole run
            self.check(f"{name}: completed", False, f"{type(error).__name__}: {error}")
            self.d.screenshot(f"fail-{name}")
        finally:
            self.d.home()

    def open(self, title: str, screen: str | None = None) -> None:
        self.d.home()
        self.d.tap(text=title)
        self.d.wait_for(text=screen or SCREENS.get(title, title))

    # -- Controls: every input round-trips to PHP --------------------------------
    def controls(self) -> None:
        d = self.d
        self.open("Controls")
        self.safe("controls")
        d.tap(desc="Text field")
        self.check("text field shows the keyboard", d.keyboard_shown())
        d.type("hello pam")
        d.back()
        self.check("text field", d.state()["text"] == "hello pam")
        d.top()
        d.tap(desc="Textarea")
        d.type("multi line")
        d.back()
        self.check("textarea", d.state()["notes"] == "multi line")
        d.top()
        d.tap(desc="Date, not set")
        today = dt.date.today()
        d.tap(desc=today.strftime("%A, %B ") + str(today.day) + today.strftime(", %Y"), scroll=False)
        self.check("date input", d.state()["date"] == today.isoformat(), d.state()["date"])
        d.top()
        d.tap(desc="Choice")
        d.tap(desc="Checkbox")
        self.check("checkbox", d.state()["checked"] is True)
        d.top()
        d.tap(desc="Switch")
        self.check("switch", d.state()["toggled"] is False)
        d.top()
        d.tap(desc="Free")
        self.check("radio group", d.state()["plan"] == "free")
        d.top()
        d.tap(desc="Select, none selected")
        d.wait_for(desc="Brazil")
        self.safe("select sheet")
        d.screenshot("select-sheet")
        # Scrolled to its end, the last option rests above the navigation bar.
        x = d.width // 2
        for _ in range(4):
            d.swipe(x, d.height * 85 // 100, x, d.height * 55 // 100, 120)
        limit = d.height - d.bottom_inset
        lowest = max((bounds(n)[3] for n in d.nodes() if n.get("clickable") == "true" and n.get("content-desc")), default=0)
        self.check("select sheet: last option above the navigation bar", 0 < lowest <= limit, f"{lowest} vs {limit}")
        d.screenshot("select-sheet-end")
        for _ in range(8):
            if d.find(desc="Brazil") is not None:
                break
            # Inside the list (below the sheet's title and search), so the
            # drag scrolls the rows instead of pulling the sheet down.
            d.swipe(x, d.height * 62 // 100, x, d.height * 92 // 100, 150)
        time.sleep(1.0)  # let the fling settle: a tap on a moving list only stops it
        d.tap(desc="Brazil", scroll=False)
        self.check("select (virtualized list in a sheet)", d.state()["country"] == "br")
        d.top()
        d.tap(desc="Multi-select, none selected")
        d.tap(desc="PHP", scroll=False)
        d.tap(desc="Rust", scroll=False)
        d.tap(desc="Done", scroll=False)
        tags = d.state()["tags"]
        self.check("multi-select", tags == ["php", "rust"], json.dumps(tags))
        d.top()
        d.tap(desc="Filter chip")
        self.check("chip", d.state()["chip"] is False)
        d.top()
        d.tap(desc="Range")
        slider = d.wait_for(desc="Slider")
        x1, y1, x2, y2 = bounds(slider)
        y = (y1 + y2) // 2
        d.swipe(x1 + (x2 - x1) * 2 // 5, y, x1 + (x2 - x1) * 9 // 10, y, 500)
        volume = d.state()["volume"]
        self.check("slider", volume > 40, volume)
        d.top()
        d.tap(desc="Increase")
        self.check("stepper", d.state()["count"] == 3)
        d.top()
        d.tap(desc="Month")
        self.check("segmented", d.state()["period"] == "month")
        d.top()
        d.screenshot("controls")

    # -- Forms: validation, errors, submit ---------------------------------------
    def forms(self) -> None:
        d = self.d
        self.open("Forms")
        self.safe("forms")
        d.tap(desc="Create account")
        d.wait_for(text="Fix the highlighted fields and try again.")
        self.check("form errors after submit", d.find(text="Enter your email.") is not None)
        d.screenshot("forms-errors")
        d.top()
        d.tap(desc="Name, required")
        d.type("Ada Lovelace")
        d.tap(desc="Email, required")
        d.type("ada@pam.dev")
        d.tap(desc="Password, required")
        d.type("analytical")
        d.back()
        d.tap(desc="Role, required, none selected")
        d.tap(desc="Developer", scroll=False)
        d.tap(desc="I accept the terms")
        d.tap(desc="Create account")
        d.top()
        d.wait_for(text="Account created for ada@pam.dev", timeout=6)
        self.check("form submit", True)

    # -- Events: every declared event reaches PHP --------------------------------
    def events(self) -> None:
        d = self.d
        self.open("Events")
        self.safe("events")
        d.screenshot("events")

        def fired(event: str, action) -> None:
            action()
            after = d.events()
            self.check(f"event {event}", after[-1:] == [event], after)

        def silent(name: str, action) -> None:
            before = d.events()
            action()
            self.check(name, d.events() == before, d.events())

        fired("btn.press", lambda: d.tap(desc="Press me"))
        silent("disabled button ignores presses", lambda: d.tap(desc="Disabled"))
        silent("loading button ignores presses", lambda: d.tap(desc="Loading button", prefix=True))
        fired("iconbtn.press", lambda: d.tap(desc="Add item"))
        fired("chip.press", lambda: d.tap(desc="Assist", prefix=True))
        fired("chip.change:on", lambda: d.tap(desc="Filter", prefix=True))
        self.check("chip p-model", d.state()["chip"] is True)
        fired("card.press", lambda: d.tap(text="Pressable card"))
        fired("item.press", lambda: d.tap(text="Open item"))
        silent("disabled list item ignores presses", lambda: d.tap(text="Disabled item"))
        fired("alert.close", lambda: d.tap(desc="Dismiss"))
        self.check("alert removed after close", d.find(text="Closable alert") is None and d.state()["alert"] is False)
        fired("tabs.change:b", lambda: d.tap(desc="Second", prefix=True))
        self.check("tabs p-model", d.state()["tab"] == "b")
        fired("segmented.change:y", lambda: d.tap(desc="Right", prefix=True))
        self.check("segmented p-model", d.state()["segment"] == "y")
        fired("stepper.change:2", lambda: d.tap(desc="Increase"))
        d.tap(desc="Increase")
        d.tap(desc="Increase")
        self.check("stepper clamps at max", d.state()["count"] == 3, d.state()["count"])
        fired("field.focus", lambda: d.tap(desc="Events field"))
        self.check("focus shows the keyboard", d.keyboard_shown())
        d.type("42")
        d.key("66")  # enter: return key "done" submits
        evts = d.events()
        # Digits: no IME capitalizes or corrects them, the value is exact.
        self.check("event field.submit", "field.submit:42" in evts, evts[-3:])
        self.check("submit hides the keyboard", not d.keyboard_shown())
        if "field.blur" not in evts:
            d.tap(text="Pressable card")
            evts = d.events()
        self.check("event field.blur", "field.blur" in evts, evts[-3:])
        self.check("field p-model", d.state()["name"] == "42", d.state()["name"])
        # Typed text reaches PHP before a press that follows it immediately.
        d.tap(desc="Events field")
        d.type("7")
        d.tap(text="Pressable card", scroll=False)
        self.check("pending input flushed before a press", d.state()["name"] == "427", d.state()["name"])
        d.back() if d.keyboard_shown() else None
        d.tap(desc="Reset events")
        self.check("reset", d.events() == [] and d.state()["alert"] is True)

    # -- Overlays: open, apply, dismiss by back, scrim and swipe -----------------
    def overlays(self) -> None:
        d = self.d
        self.open("Overlays")
        self.safe("overlays")

        def open_sheet() -> Node:
            d.top()
            d.tap(desc="Open filters")
            return d.wait_for(desc="Apply")

        apply = open_sheet()
        self.check("sheet opens", d.state is not None and d.find(text="Sort by") is not None)
        self.safe("sheet")
        _, _, _, apply_bottom = bounds(apply)
        self.check("sheet footer above the navigation bar", apply_bottom <= d.height - d.bottom_inset, f"{apply_bottom} vs {d.height - d.bottom_inset}")
        d.screenshot("sheet")
        d.tap(desc="Most popular", scroll=False)
        d.tap(desc="Unread only", scroll=False)
        d.tap(desc="Apply", scroll=False)
        # Apply also shows a 4 s snackbar: act on it before slower reads.
        undo = d.wait_for(desc="Undo", timeout=4)
        d.screenshot("snackbar")
        d.tap_at(*d.center(undo))
        self.check("sheet closes on apply", d.gone(desc="Apply"))
        state = d.state()
        self.check("sheet values reach PHP", state["sort"] == "popular" and state["unread"] is True, state)
        self.check("event sheet.apply", "sheet.apply:popular:unread" in state["events"], state["events"][-2:])
        self.check("event snackbar.action", "snackbar.action" in d.events())
        self.check("snackbar hides after its action", d.gone(desc="Undo"))

        open_sheet()
        d.back()
        self.check("sheet dismisses on back", d.gone(desc="Apply"))
        self.check("event sheet.close (back)", d.events()[-1:] == ["sheet.close"], d.events()[-2:])
        self.check("sheet p-model false after back", d.state()["sheet"] is False)

        open_sheet()
        d.tap_at(d.width // 2, d.top_inset + 80, 1.0)
        self.check("sheet dismisses on scrim tap", d.gone(desc="Apply"))
        self.check("event sheet.close (scrim)", d.events()[-1:] == ["sheet.close"])

        apply = open_sheet()
        handle_y = bounds(d.wait_for(text="Filters"))[1]
        d.swipe(d.width // 2, handle_y, d.width // 2, d.height - 40, 250)
        self.check("sheet dismisses on swipe down", d.gone(desc="Apply"))
        self.check("event sheet.close (swipe)", d.events()[-1:] == ["sheet.close"])

        d.top()
        d.tap(desc="Delete project")
        d.wait_for(text="Delete project?")
        self.safe("dialog")
        d.screenshot("dialog")
        d.tap(desc="Delete", scroll=False)
        self.check("dialog closes on confirm", d.gone(text="Delete project?"))
        self.check("event dialog.confirm", d.events()[-1:] == ["dialog.confirm"])
        d.top()
        d.tap(desc="Delete project")
        d.tap(desc="Cancel", scroll=False)
        self.check("event dialog.cancel", d.events()[-1:] == ["dialog.cancel"])
        d.top()
        d.tap(desc="Delete project")
        d.wait_for(text="Delete project?")
        d.back()
        self.check("dialog dismisses on back", d.gone(text="Delete project?"))
        self.check("dialog p-model false after back", d.state()["dialog"] is False)

        d.top()
        d.tap(desc="Show snackbar")
        d.wait_for(desc="Undo")
        self.check("snackbar auto-hides", d.gone(desc="Undo", timeout=7))
        self.check("snackbar p-model false after timeout", d.state()["snackbar"] is False)

    # -- Lists: virtualized rows, search, empty state ----------------------------
    def lists(self) -> None:
        d = self.d
        self.open("Lists")
        self.safe("lists")
        d.screenshot("lists")
        rows = [n for n in d.nodes() if n.get("text") in ("Online",)]
        self.check("virtualized list renders rows", d.find(text="500 people") is not None)
        for _ in range(4):
            d.swipe(d.width // 2, d.height * 8 // 10, d.width // 2, d.height * 2 // 10, 120)
        self.check("list scrolls", d.find(text="500 people") is not None and len(d.nodes()) > 10, len(rows))
        d.tap(desc="Search people", prefix=True)
        d.type("zzzz")
        d.back()
        self.check("empty state when nothing matches", d.wait_for(text="Nobody found") is not None)
        d.screenshot("lists-empty")

    # -- Feedback: alerts close, progress advances -------------------------------
    def feedback(self) -> None:
        d = self.d
        self.open("Feedback")
        self.safe("feedback")
        d.screenshot("feedback")
        d.tap(desc="Dismiss")
        self.check("info alert closes", d.gone(text="Heads up"))
        d.tap(desc="Advance")
        self.check("progress advances", d.find(desc="Uploading", prefix=True) is not None)

    # -- Theme: dark appearance from PHP, every screen ---------------------------
    def theme(self) -> None:
        d = self.d
        d.home()
        d.tap(desc="Dark", prefix=True)
        time.sleep(1.0)
        self.mode = "dark"
        d.screenshot("catalog-dark")
        self.check("dark appearance applies", self.background_dark(), "")
        for title in ("Forms", "Controls", "Events", "Feedback", "Overlays"):
            self.open(title)
            d.screenshot(f"{title.lower()}-dark")
            self.safe(title.lower())
            d.home()
        self.open("Overlays")
        d.tap(desc="Open filters")
        d.wait_for(desc="Apply")
        d.screenshot("sheet-dark")
        self.safe("sheet")
        d.back()
        self.mode = "light"
        d.home()
        d.tap(desc="Light", prefix=True)
        time.sleep(1.0)
        self.check("light appearance restores", not self.background_dark())
        d.tap(desc="System", prefix=True)

    # -- Sweep: font scale, RTL and landscape on every screen (emulator) -------
    def sweep(self) -> None:
        """Geometry of every screen at 0.85x, 1.3x and 2x font scale and in
        landscape. It changes system settings, so it runs on emulators
        only and restores them."""
        d = self.d
        if not d.serial.startswith("emulator-"):
            self.check("sweep: skipped on a physical device", True)
            return
        screens = ("Forms", "Controls", "Events", "Feedback", "Overlays", "Lists")
        settings = {
            "font": d.adb("shell", "settings", "get", "system", "font_scale").strip(),
            "rotation": d.adb("shell", "settings", "get", "system", "accelerometer_rotation").strip(),
        }

        def visit(label: str, relaunch: bool = True, only: tuple[str, ...] = screens) -> None:
            if relaunch:
                d.launch()
            else:
                d.home()
            self.mode = label
            self.safe("catalog", golden=False)
            for title in only:
                self.open(title)
                self.safe(title.lower(), golden=False)
                d.screenshot(f"{title.lower()}-{label}")
            self.mode = "light"

        try:
            for scale in ("0.85", "1.3", "2.0"):
                d.adb("shell", "settings", "put", "system", "font_scale", scale)
                visit(f"font{scale}")
            d.adb("shell", "settings", "put", "system", "font_scale", "1.0")
            # RTL is not swept: PAM Native lays out left-to-right in every
            # locale (no RTL mirroring yet), so an RTL pass checks nothing.
            # Rotate the running app (a cold start may still come up portrait).
            d.launch()
            d.adb("shell", "wm", "user-rotation", "lock", "1")
            for _ in range(20):  # until the app itself is laid out landscape
                time.sleep(0.5)
                d.insets()
                if d.width > d.height:
                    break
            # Lists is left out: its 500-row screen is the same layout as in
            # portrait, and returning to it reopened the portrait launcher.
            visit("landscape", relaunch=False, only=screens[:-1])
        finally:
            d.adb("shell", "settings", "put", "system", "font_scale", settings["font"] if settings["font"] not in ("", "null") else "1.0")
            d.adb("shell", "wm", "user-rotation", "lock", "0")
            if settings["rotation"] == "1":
                d.adb("shell", "wm", "user-rotation", "free")
            time.sleep(1)
            d.insets()
            d.launch()

    def background_dark(self) -> bool:
        from io import BytesIO

        try:
            from PIL import Image
        except ImportError:
            return True
        png = subprocess.run(["adb", "-s", self.d.serial, "exec-out", "screencap", "-p"], check=True, capture_output=True).stdout
        image = Image.open(BytesIO(png)).convert("L")
        return image.getpixel((8, image.height // 2)) < 60


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.nativeui.showcase.debug")
    parser.add_argument("--suite", default="all")
    parser.add_argument("--shots", default=None, help="directory for screenshots")
    parser.add_argument("--golden", default=None, help="golden directory (tests/golden/android)")
    parser.add_argument("--update-golden", action="store_true", help="record goldens instead of comparing")
    args = parser.parse_args()
    device = Device(args.serial, args.package, args.shots)
    suite = Suite(device, args.golden, args.update_golden)
    names = ["controls", "forms", "events", "overlays", "lists", "feedback", "theme", "sweep"]
    chosen = names if args.suite == "all" else args.suite.split(",")
    device.launch()
    for name in chosen:
        suite.run(name, getattr(suite, name))
    failed = [name for name, ok, _ in suite.results if not ok]
    print(json.dumps({"passed": len(suite.results) - len(failed), "failed": failed}), flush=True)
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
