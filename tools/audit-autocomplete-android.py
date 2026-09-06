#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
import subprocess
import time
import xml.etree.ElementTree as ET
from dataclasses import dataclass
from pathlib import Path

from PIL import Image


class AuditFailure(RuntimeError):
    pass


@dataclass(frozen=True)
class Bounds:
    left: int
    top: int
    right: int
    bottom: int

    @property
    def width(self) -> int:
        return self.right - self.left

    @property
    def height(self) -> int:
        return self.bottom - self.top

    @property
    def center(self) -> tuple[int, int]:
        return ((self.left + self.right) // 2, (self.top + self.bottom) // 2)


def node_bounds(node: ET.Element) -> Bounds:
    values = [int(value) for value in re.findall(r"\d+", node.attrib.get("bounds", ""))]
    if len(values) != 4:
        return Bounds(0, 0, 0, 0)
    return Bounds(*values)


class AutocompleteAudit:
    def __init__(
        self,
        serial: str,
        package: str,
        activity: str,
        output: Path,
        component_tag: str = "p-autocomplete",
        component_label: str = "Autocomplete",
    ) -> None:
        self.serial = serial
        self.package = package
        self.activity = activity
        self.output = output
        self.component_tag = component_tag
        self.component_label = component_label
        self.output.mkdir(parents=True, exist_ok=True)
        self.evidence: list[dict[str, object]] = []
        self.original_settings: dict[str, str] = {}
        self.launch_count = 0
        self.task_locked = False

    def adb(
        self,
        *arguments: str,
        timeout: float = 25.0,
        binary: bool = False,
        check: bool = True,
    ) -> str | bytes:
        result = subprocess.run(
            ["adb", "-s", self.serial, *arguments],
            capture_output=True,
            check=False,
            timeout=timeout,
            text=not binary,
        )
        if check and result.returncode != 0:
            stderr = result.stderr.decode(errors="replace") if binary else result.stderr
            raise AuditFailure(
                f"adb {' '.join(arguments)} failed ({result.returncode}): {stderr.strip()}"
            )
        return result.stdout

    def shell(self, *arguments: str, timeout: float = 25.0) -> str:
        result = self.adb("shell", *arguments, timeout=timeout)
        assert isinstance(result, str)
        return result

    def setting(self, namespace: str, name: str) -> str:
        return self.shell("settings", "get", namespace, name).strip()

    def set_setting(self, namespace: str, name: str, value: str) -> None:
        self.shell("settings", "put", namespace, name, value)

    def device_locked(self) -> bool:
        trust = self.shell("dumpsys", "trust", timeout=30.0)
        current = re.search(
            r"\(current\):[^\n]*?\bdeviceLocked=(\d+)",
            trust,
        )
        if current is not None:
            return current.group(1) != "0"
        policy = self.shell("dumpsys", "window", "policy", timeout=30.0)
        showing = re.search(r"KeyguardStateMonitor[\s\S]*?mIsShowing=(true|false)", policy)
        if showing is not None:
            return showing.group(1) == "true"
        delegate = re.search(r"KeyguardServiceDelegate[\s\S]*?\n\s+showing=(true|false)", policy)
        return delegate is None or delegate.group(1) == "true"

    def prepare(self) -> None:
        self.adb("get-state")
        self.shell("pm", "path", self.package)
        try:
            for namespace, name in (
                ("global", "window_animation_scale"),
                ("global", "transition_animation_scale"),
                ("global", "animator_duration_scale"),
                ("system", "accelerometer_rotation"),
                ("system", "user_rotation"),
                ("secure", "show_ime_with_hard_keyboard"),
            ):
                self.original_settings[f"{namespace}:{name}"] = self.setting(namespace, name)
            for name in (
                "window_animation_scale",
                "transition_animation_scale",
                "animator_duration_scale",
            ):
                self.set_setting("global", name, "0")
            self.set_setting("system", "accelerometer_rotation", "0")
            self.set_setting("system", "user_rotation", "0")
            self.set_setting("secure", "show_ime_with_hard_keyboard", "1")
            self.shell("input", "keyevent", "WAKEUP")
            self.shell("wm", "dismiss-keyguard")
            time.sleep(0.8)
            if self.device_locked():
                raise AuditFailure(
                    "the physical Android device is locked; unlock it before the audit"
                )
            cleared = self.shell("pm", "clear", self.package).strip()
            if cleared != "Success":
                raise AuditFailure(
                    f"could not clear {self.package} before the cold-start audit: {cleared!r}"
                )
            self.shell("logcat", "-c")
        except BaseException:
            self.restore()
            raise

    def restore(self) -> None:
        self.stop_task_lock()
        for key, value in self.original_settings.items():
            namespace, name = key.split(":", 1)
            if value not in {"", "null"}:
                try:
                    self.set_setting(namespace, name, value)
                except (AuditFailure, subprocess.SubprocessError):
                    # Cleanup must never hide the interaction failure that is
                    # already propagating (for example when an emulator exits
                    # mid-run). Every in-scope ADB assertion before this point
                    # remains strict; restoration is best-effort only.
                    continue

    def launch(self, scenario: str = "interactive") -> None:
        self.stop_task_lock()
        uri = f"pam-showcase://audit/{self.component_tag}"
        if scenario != "interactive":
            uri += f"?scenario={scenario}"
        launch_report = self.shell(
            # prepare() clears the package before the first launch. Reuse the
            # process for later deep links instead of force-stopping it for
            # every assertion: repeated -S cycles can exhaust the Android 16
            # emulator's graphics buffers and create a false render-thread ANR.
            "am", "start", "-W",
            "-n", f"{self.package}/{self.activity}",
            "-d", uri,
            timeout=35.0,
        )
        if "Status: ok" not in launch_report:
            # Android 16 can report a launch wait timeout even after the
            # requested Activity has resumed. Accept that transport-level
            # result only when the authoritative foreground check succeeds;
            # the hierarchy precondition below must still prove the exact
            # deep-linked route and its top position.
            if "Status: timeout" not in launch_report:
                raise AuditFailure(
                    f"Android did not confirm the deep-link launch for {uri}: "
                    f"{launch_report.strip()}"
                )
            self.assert_foreground(f"timed-out deep-link launch for {uri}")
        time.sleep(1.5)
        self.launch_count += 1
        visible: list[str] = []
        for restore_attempt in range(4):
            root = self.dump(
                f"route-precondition-{self.launch_count:02d}-{restore_attempt + 1}"
            )
            route_headings = [
                node for node in self.exact(root, self.component_label)
                if node_bounds(node).top < 220
            ]
            variation_headings = [
                node for node in self.exact(root, "Variations")
                if node_bounds(node).top < 460
            ]
            if route_headings and variation_headings:
                return
            visible = [
                self.text(node) for node in self.nodes(root)
                if self.text(node) and node_bounds(node).top < 460
            ]
            if restore_attempt < 3:
                screen = max(
                    (node_bounds(node) for node in self.nodes(root)),
                    key=lambda area: area.width * area.height,
                )
                self.swipe_down(screen.width, screen.height)
        raise AuditFailure(
            f"deep link {uri} did not render the requested component route at "
            f"its authoritative top position; top-of-screen labels were {visible[:12]!r}"
        )

    def start_task_lock(self) -> None:
        report = self.shell("dumpsys", "activity", "activities", timeout=30.0)
        match = re.search(
            rf"mResumedActivity:.*?\s{re.escape(self.package)}/.*?\st(\d+)",
            report,
        )
        if match is None:
            match = re.search(
                rf"ActivityRecord\{{[^\n]*?\s{re.escape(self.package)}/.*?\st(\d+)\}}",
                report,
            )
        if match is None:
            raise AuditFailure("could not resolve the PAM task before Android task pinning")
        self.shell("am", "task", "lock", match.group(1))
        self.task_locked = True
        state = self.shell("dumpsys", "activity", "activities", timeout=30.0)
        if not re.search(r"mLockTaskModeState=(?:PINNED|LOCKED)", state):
            raise AuditFailure("Android did not pin the PAM audit task")
        self.assert_foreground("task pinning")

    def stop_task_lock(self) -> None:
        if not self.task_locked:
            return
        self.adb("shell", "am", "task", "lock", "stop", check=False)
        self.task_locked = False

    @staticmethod
    def foreground_package(report: str) -> str | None:
        match = re.search(
            r"(?:topResumedActivity|mResumedActivity|ResumedActivity|Resumed)"
            r"\s*[:=].*?\s([A-Za-z0-9_.]+)/(?:[A-Za-z0-9_.$]+)",
            report,
        )
        return match.group(1) if match else None

    def assert_foreground(self, context: str) -> None:
        resumed = None
        for attempt in range(3):
            report = self.shell("dumpsys", "activity", "activities", timeout=30.0)
            resumed = self.foreground_package(report)
            if resumed == self.package:
                return
            if resumed is not None:
                break
            time.sleep(0.2 * (attempt + 1))
        raise AuditFailure(
            f"{context}: audit app lost the foreground "
            f"(expected {self.package}, found {resumed or 'unknown'})"
        )

    def dump(self, name: str) -> ET.Element:
        self.assert_foreground(f"hierarchy {name}")
        remote = f"/sdcard/pam-autocomplete-{name}.xml"
        local = self.output / f"{name}.xml"
        last_problem = "UiAutomator did not return an XML hierarchy"
        for attempt in range(3):
            self.shell("rm", "-f", remote)
            try:
                self.shell("uiautomator", "dump", "--compressed", remote, timeout=30.0)
            except (subprocess.TimeoutExpired, AuditFailure) as exception:
                self.adb("shell", "pkill", "-f", "uiautomator", check=False)
                last_problem = f"UiAutomator command failed: {exception}"
            else:
                payload = self.adb(
                    "exec-out", "cat", remote,
                    timeout=30.0,
                    check=False,
                )
                assert isinstance(payload, str)
                if payload.lstrip().startswith("<?xml") and "<hierarchy" in payload:
                    try:
                        root = ET.fromstring(payload)
                    except ET.ParseError as exception:
                        last_problem = f"invalid UiAutomator XML: {exception}"
                    else:
                        local.write_text(payload, encoding="utf-8")
                        return root
                else:
                    last_problem = payload.strip() or "UiAutomator dump file was absent"
            self.adb("shell", "pkill", "-f", "uiautomator", check=False)
            if attempt < 2:
                time.sleep(1.0)
        raise AuditFailure(
            f"could not collect a valid hierarchy for {name!r} after three attempts: "
            f"{last_problem}"
        )

    def screenshot(self, name: str) -> tuple[int, int]:
        was_locked = self.task_locked
        if was_locked:
            self.stop_task_lock()
            time.sleep(0.35)
        try:
            self.assert_foreground(f"screenshot {name}")
            path = self.output / f"{name}.png"
            payload = self.adb("exec-out", "screencap", "-p", binary=True)
            assert isinstance(payload, bytes)
            path.write_bytes(payload)
            with Image.open(path) as image:
                size = image.size
            self.evidence.append({"name": name, "path": str(path), "size": list(size)})
            return size
        finally:
            if was_locked:
                self.start_task_lock()
                time.sleep(0.25)

    def tap(self, area: Bounds | tuple[int, int]) -> None:
        self.assert_foreground("tap precondition")
        x, y = area.center if isinstance(area, Bounds) else area
        self.shell("input", "tap", str(x), str(y))
        time.sleep(0.7)
        self.assert_foreground("tap result")

    def back(self) -> None:
        self.assert_foreground("system Back precondition")
        self.shell("input", "keyevent", "BACK", timeout=60.0)
        time.sleep(0.7)
        self.assert_foreground("system Back result")

    def wait_for_rotation(self, expected: int, context: str) -> None:
        deadline = time.monotonic() + 10.0
        actual = -1
        while time.monotonic() < deadline:
            report = self.shell("dumpsys", "display", timeout=20.0)
            match = re.search(
                r"mViewports=\[DisplayViewport\{[^\n]*orientation=(\d+)",
                report,
            )
            actual = int(match.group(1)) if match else -1
            if actual == expected:
                time.sleep(0.5)
                return
            time.sleep(0.2)
        raise AuditFailure(
            f"{context}: expected Android surface rotation {expected}, found {actual}"
        )

    def close_modal_with_back(self, context: str) -> None:
        self.back()
        if self.visible_app_windows() == 2:
            self.back()
        self.assert_window_count(1, context)

    def swipe_up(self, width: int, height: int) -> None:
        self.assert_foreground("upward swipe precondition")
        self.shell(
            "input", "swipe",
            str(width // 2), str(int(height * 0.82)),
            str(width // 2), str(int(height * 0.34)), "650",
        )
        time.sleep(0.8)
        self.assert_foreground("upward swipe result")

    def swipe_down(self, width: int, height: int) -> None:
        self.assert_foreground("downward swipe precondition")
        self.shell(
            "input", "swipe",
            str(width // 2), str(int(height * 0.28)),
            str(width // 2), str(int(height * 0.86)), "650",
        )
        time.sleep(0.8)
        self.assert_foreground("downward swipe result")

    def visible_app_windows(self) -> int:
        report = self.shell("dumpsys", "window", "windows", timeout=30.0)
        blocks = re.split(r"(?=\s*Window #\d+ )", report)
        return sum(
            1 for block in blocks
            if f"package={self.package}" in block and "isVisible=true" in block
        )

    def ime_shown(self) -> bool:
        report = self.shell("dumpsys", "window", "windows", timeout=30.0)
        blocks = re.split(r"(?=\s*Window #\d+ )", report)
        return any(
            "InputMethod" in block and "isVisible=true" in block
            for block in blocks
        )

    def density(self) -> float:
        report = self.shell("wm", "density")
        matches = re.findall(r"(?:Override|Physical) density: (\d+)", report)
        if not matches:
            raise AuditFailure(f"cannot parse display density: {report!r}")
        return int(matches[-1]) / 160.0

    def physical_content_bottom(self, width: int, height: int) -> int:
        report = self.shell("dumpsys", "window", "windows", timeout=30.0)
        blocks = re.split(r"(?=\s*Window #\d+ )", report)
        for block in blocks:
            if "NavigationBar0" not in block:
                continue
            frame = re.search(
                r"mFrame=\[(-?\d+),(-?\d+)\]\[(-?\d+),(-?\d+)\]",
                block,
            )
            if frame is None:
                continue
            left, top, right, bottom = map(int, frame.groups())
            if left <= 0 and right >= width and top > height // 2:
                return top
        return height

    @staticmethod
    def nodes(root: ET.Element) -> list[ET.Element]:
        return list(root.iter("node"))

    @staticmethod
    def text(node: ET.Element) -> str:
        return node.attrib.get("text", "") or node.attrib.get("content-desc", "")

    def exact(self, root: ET.Element, value: str) -> list[ET.Element]:
        return [node for node in self.nodes(root) if self.text(node) == value]

    def trigger_after(self, root: ET.Element, label: str) -> ET.Element:
        labels = self.exact(root, label)
        if not labels:
            raise AuditFailure(f"variation label {label!r} is missing")
        label_area = node_bounds(labels[0])
        candidates = [
            node for node in self.nodes(root)
            if node.attrib.get("class") == "android.widget.Spinner"
            and node.attrib.get("content-desc") == self.component_label
            and node_bounds(node).top >= label_area.bottom
        ]
        if not candidates:
            raise AuditFailure(f"autocomplete trigger after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def field_after(self, root: ET.Element, label: str) -> ET.Element:
        label_node = self.exact(root, label)[0]
        label_area = node_bounds(label_node)
        candidates = [
            node for node in self.nodes(root)
            if node.attrib.get("content-desc") == f"{self.component_label} preview"
            and node_bounds(node).top >= label_area.bottom
        ]
        if not candidates:
            raise AuditFailure(f"autocomplete field after {label!r} is missing")
        return min(candidates, key=lambda node: node_bounds(node).top)

    def scroll_to_trigger(
        self,
        label: str,
        width: int,
        height: int,
    ) -> tuple[ET.Element, ET.Element]:
        for attempt in range(8):
            root = self.dump(f"scroll-{label.lower().replace(' ', '-')}-{attempt}")
            trigger = self.trigger_after(root, label)
            area = node_bounds(trigger)
            if area.top >= 120 and area.bottom <= height - 100:
                return root, trigger
            self.swipe_up(width, height)
        raise AuditFailure(f"could not bring {label!r} into the viewport")

    def displayed_value(self, root: ET.Element, label: str) -> str:
        trigger = self.trigger_after(root, label)
        values = [
            node.attrib.get("text", "")
            for node in trigger.iter("node")
            if node.attrib.get("text", "")
        ]
        return ", ".join(values)

    def assert_window_count(self, expected: int, context: str) -> None:
        actual = -1
        deadline = time.monotonic() + 4.0
        while time.monotonic() < deadline:
            actual = self.visible_app_windows()
            if actual == expected:
                return
            time.sleep(0.15)
        raise AuditFailure(
            f"{context}: expected {expected} stable app window(s), found {actual}"
        )

    def open_and_dump(self, trigger: ET.Element, name: str) -> ET.Element:
        self.tap(node_bounds(trigger))
        self.assert_window_count(2, f"{name} open")
        # The native search field receives focus on presentation. Moving focus
        # once prevents its blinking cursor from keeping UiAutomator's idle
        # detector busy; the interaction test explicitly focuses it again.
        self.shell("input", "keyevent", "TAB")
        time.sleep(0.2)
        opened = self.dump(name)
        inputs = [
            node for node in self.nodes(opened)
            if node.attrib.get("class") == "android.widget.EditText"
            and node_bounds(node).width > 0
        ]
        if len(inputs) != 1:
            raise AuditFailure(
                f"{name}: expected one searchable sheet/input, found {len(inputs)}"
            )
        stale_query = inputs[0].attrib.get("text", "")
        # Samsung/UIAutomator exposes an unfocused EditText hint through the
        # text attribute. Treat only content different from the declared hint
        # as an actual persisted query.
        input_hint = inputs[0].attrib.get("content-desc", "")
        if stale_query and stale_query != input_hint:
            raise AuditFailure(
                f"{name}: reopened sheet leaked stale query {stale_query!r}"
            )
        self.screenshot(name)
        return opened

    def choose(self, opened: ET.Element, label: str) -> None:
        matches = [node for node in self.exact(opened, label) if node_bounds(node).height > 0]
        if not matches:
            raise AuditFailure(f"sheet option {label!r} is missing")
        self.tap(node_bounds(matches[-1]))

    def search(self, opened: ET.Element, query: str) -> ET.Element:
        inputs = [
            node for node in self.nodes(opened)
            if node.attrib.get("class") == "android.widget.EditText"
        ]
        if len(inputs) != 1:
            raise AuditFailure(f"expected one search input, found {len(inputs)}")
        self.tap(node_bounds(inputs[0]))
        self.shell("input", "text", query)
        time.sleep(0.8)
        if not self.ime_shown():
            raise AuditFailure("search input did not show the software keyboard")
        self.shell("input", "keyevent", "TAB")
        time.sleep(0.2)
        return self.dump(f"search-{query}")

    def run(self) -> dict[str, object]:
        self.prepare()
        try:
            self.launch()
            baseline = self.dump("00-baseline")
            width, height = self.screenshot("00-baseline")
            density = self.density()
            required = [
                "Default", "Isolated instance", "Disabled",
                "Read Only", "No Data", "Multiple",
            ]
            for label in required:
                if not self.exact(baseline, label):
                    raise AuditFailure(f"audit surface is missing {label!r}")
            self.assert_window_count(1, "baseline")

            default_trigger = self.trigger_after(baseline, "Default")
            trigger_dp = node_bounds(default_trigger).height / density
            if trigger_dp < 48.0:
                raise AuditFailure(
                    f"default touch target is {trigger_dp:.1f}dp; Material minimum is 48dp"
                )

            opened = self.open_and_dump(default_trigger, "01-default-open")
            searched = self.search(opened, "eng")
            searched = self.dump("02-filtered-engineering")
            filtered_options = [
                node for node in self.nodes(searched)
                if node.attrib.get("clickable") == "true"
                and node_bounds(node).height > 0
                and self.text(node) in {"Design", "Engineering", "Product", "Research"}
            ]
            if [self.text(node) for node in filtered_options] != ["Engineering"]:
                raise AuditFailure("filtering 'eng' did not isolate Engineering")
            self.screenshot("02-filtered-engineering")
            self.choose(searched, "Engineering")
            selected = self.dump("03-selected-engineering")
            self.assert_window_count(1, "single selection close")
            if "Engineering" not in self.displayed_value(selected, "Default"):
                raise AuditFailure("selected Engineering was not retained")
            self.screenshot("03-selected-engineering")

            opened = self.open_and_dump(
                self.trigger_after(selected, "Default"),
                "04-reopen-for-backdrop",
            )
            search_area = node_bounds([
                node for node in self.nodes(opened)
                if node.attrib.get("class") == "android.widget.EditText"
            ][0])
            self.tap((width // 2, max(120, search_area.top - 120)))
            backdrop_closed = self.dump("05-backdrop-closed")
            self.assert_window_count(1, "backdrop close")
            if "Engineering" not in self.displayed_value(backdrop_closed, "Default"):
                raise AuditFailure("backdrop dismissal corrupted the selected value")

            edge_open = self.open_and_dump(
                self.trigger_after(backdrop_closed, "Default"),
                "05b-last-option-edge-open",
            )
            research = [
                node for node in self.exact(edge_open, "Research")
                if node.attrib.get("clickable") == "true"
            ]
            if len(research) != 1:
                raise AuditFailure("last autocomplete option is not uniquely actionable")
            research_area = node_bounds(research[0])
            intended_edge_y = research_area.top + round(52.0 * density)
            physical_bottom = self.physical_content_bottom(width, height)
            intended_row_bottom = research_area.top + round(56.0 * density)
            if intended_edge_y >= physical_bottom:
                raise AuditFailure("last autocomplete option extends into system navigation")
            if physical_bottom - intended_row_bottom < round(20.0 * density):
                raise AuditFailure(
                    "last autocomplete option violates the Material bottom safe area"
                )
            self.tap((research_area.center[0], intended_edge_y))
            edge_selected = self.dump("05c-last-option-edge-selected")
            self.assert_window_count(1, "last-row lower-edge selection")
            if "Research" not in self.displayed_value(edge_selected, "Default"):
                raise AuditFailure("the lower edge of the final 56dp option is not interactive")
            self.screenshot("05c-last-option-edge-selected")

            isolated_trigger = self.trigger_after(edge_selected, "Isolated instance")
            opened = self.open_and_dump(isolated_trigger, "06-isolated-open")
            self.choose(opened, "Product")
            isolated = self.dump("07-isolated-selected")
            if "Research" not in self.displayed_value(isolated, "Default"):
                raise AuditFailure("second instance corrupted the first autocomplete")
            if "Product" not in self.displayed_value(isolated, "Isolated instance"):
                raise AuditFailure("second instance did not retain Product")
            self.screenshot("07-isolated-selected")

            for label in ("Disabled", "Read Only"):
                root, trigger = self.scroll_to_trigger(label, width, height)
                before_windows = self.visible_app_windows()
                self.tap(node_bounds(trigger))
                after_windows = self.visible_app_windows()
                if after_windows != before_windows:
                    raise AuditFailure(f"{label} autocomplete opened a modal")
                if label == "Disabled" and trigger.attrib.get("enabled") == "true":
                    raise AuditFailure("disabled autocomplete is accessibility-enabled")
                if label == "Read Only" and trigger.attrib.get("clickable") == "true":
                    raise AuditFailure("read-only autocomplete exposes a click action")

            _, no_data_trigger = self.scroll_to_trigger("No Data", width, height)
            no_data_open = self.open_and_dump(no_data_trigger, "08-no-data-open")
            no_data_inputs = [
                node for node in self.nodes(no_data_open)
                if node.attrib.get("class") == "android.widget.EditText"
                and node_bounds(node).width > 0
            ]
            no_data_messages = [
                node for node in self.exact(no_data_open, "No options available")
                if node_bounds(node).width > 0
            ]
            if len(no_data_messages) != 1:
                raise AuditFailure(
                    "empty autocomplete must expose exactly one no-data message; "
                    f"found {len(no_data_messages)}"
                )
            search_area = node_bounds(no_data_inputs[0])
            message_area = node_bounds(no_data_messages[0])
            minimum_gap = round(8.0 * density)
            minimum_gutter = round(16.0 * density)
            if message_area.top < search_area.bottom + minimum_gap:
                raise AuditFailure(
                    "empty autocomplete overlaps its search field: "
                    f"search={search_area}, message={message_area}"
                )
            if (
                search_area.left < minimum_gutter
                or width - search_area.right < minimum_gutter
                or message_area.left < minimum_gutter
                or width - message_area.right < minimum_gutter
            ):
                raise AuditFailure(
                    "empty autocomplete violates the 16dp horizontal sheet gutter"
                )
            if not (47.0 <= search_area.height / density <= 57.0):
                raise AuditFailure(
                    f"empty autocomplete search is {search_area.height / density:.1f}dp high"
                )
            reported_message_dp = message_area.height / density
            if not (48.0 <= reported_message_dp <= 64.0):
                accessibility_root = node_bounds(next(no_data_open.iter("node")))
                intended_bottom = message_area.top + round(56.0 * density)
                if (
                    message_area.bottom != accessibility_root.bottom
                    or intended_bottom > self.physical_content_bottom(width, height)
                ):
                    raise AuditFailure(
                        "empty autocomplete message does not own a complete 56dp "
                        f"physical row: reported={reported_message_dp:.1f}dp, "
                        f"message={message_area}, root={accessibility_root}"
                    )
            self.close_modal_with_back("no-data close")

            root, multiple_trigger = self.scroll_to_trigger("Multiple", width, height)
            if "Design" not in self.displayed_value(root, "Multiple") or "Product" not in self.displayed_value(root, "Multiple"):
                raise AuditFailure("multiple autocomplete does not expose both initial values")
            multiple_open = self.open_and_dump(multiple_trigger, "09-multiple-open")
            self.choose(multiple_open, "Engineering")
            time.sleep(0.8)
            if self.visible_app_windows() == 2:
                self.close_modal_with_back("multiple close")
            multiple_selected = self.dump("10-multiple-selected")
            multiple_value = self.displayed_value(multiple_selected, "Multiple")
            for value in ("Design", "Product", "Engineering"):
                if value not in multiple_value:
                    raise AuditFailure(f"multiple autocomplete lost {value!r}")
            self.screenshot("10-multiple-selected")

            self.launch()
            root = self.dump("11-back-baseline")
            opened = self.open_and_dump(
                self.trigger_after(root, "Default"),
                "11-back-open",
            )
            self.search(opened, "pro")
            self.back()
            if self.ime_shown() or self.visible_app_windows() != 2:
                raise AuditFailure("first Back did not hide only the keyboard")
            self.back()
            self.assert_window_count(1, "second Back")

            root = self.dump("12-rotation-baseline")
            opened = self.open_and_dump(
                self.trigger_after(root, "Default"),
                "12-rotation-open",
            )
            rotation_query = self.search(opened, "pro")
            rotation_inputs = [
                node for node in self.nodes(rotation_query)
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            if len(rotation_inputs) != 1 or rotation_inputs[0].attrib.get("text") != "pro":
                raise AuditFailure("rotation precondition did not preserve the exact query 'pro'")
            self.back()
            self.set_setting("system", "user_rotation", "1")
            time.sleep(1.5)
            landscape_root = self.dump("13-landscape")
            landscape_inputs = [
                node for node in self.nodes(landscape_root)
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            if len(landscape_inputs) != 1 or landscape_inputs[0].attrib.get("text") != "pro":
                raise AuditFailure("landscape rotation changed or duplicated the search query")
            landscape = self.screenshot("13-landscape")
            self.assert_window_count(2, "landscape sheet")
            if landscape[0] <= landscape[1]:
                raise AuditFailure("device did not enter landscape")
            self.set_setting("system", "user_rotation", "0")
            time.sleep(1.5)
            portrait = self.dump("14-portrait-restored")
            portrait_inputs = [
                node for node in self.nodes(portrait)
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            if len(portrait_inputs) != 1 or portrait_inputs[0].attrib.get("text") != "pro":
                raise AuditFailure("portrait restoration changed or duplicated the search query")
            portrait_size = self.screenshot("14-portrait-restored")
            self.assert_window_count(2, "portrait-restored sheet")
            if portrait_size[0] >= portrait_size[1]:
                raise AuditFailure("device did not return to portrait")
            visible_text = {self.text(node) for node in self.nodes(portrait)}
            if {"Overview", "Actions", "Forms", "Data", "Overlays"} <= visible_text:
                raise AuditFailure("responsive drawer remained open after returning to portrait")
            self.back()

            logs = self.shell("logcat", "-d", "-t", "1200", timeout=30.0)
            markers = (
                "FATAL EXCEPTION", " E AndroidRuntime:", "Pam Native runtime error",
                "failed integrity verification", "Unknown native icon",
                "requestLayout() improperly called",
            )
            errors = [
                line for line in logs.splitlines()
                if any(marker in line for marker in markers)
            ]
            if errors:
                raise AuditFailure("runtime errors found in logcat: " + " | ".join(errors[-8:]))

            report = {
                "schemaVersion": 1,
                "component": self.component_tag,
                "device": self.serial,
                "package": self.package,
                "result": "passed",
                "checks": {
                    "singleSheet": True,
                    "materialTouchTarget": True,
                    "filterAndSelection": True,
                    "backdropPreservesValue": True,
                    "lastRowLowerEdgeHitTarget": True,
                    "bottomSafeArea": True,
                    "instanceIsolation": True,
                    "disabledAndReadOnly": True,
                    "noDataUniqueAndSeparated": True,
                    "multipleSelection": True,
                    "keyboardBack": True,
                    "rotation": True,
                    "responsiveDrawer": True,
                    "runtimeLog": True,
                },
                "evidence": self.evidence,
            }
            (self.output / "report.json").write_text(
                json.dumps(report, indent=2) + "\n",
                encoding="utf-8",
            )
            return report
        finally:
            self.restore()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Run the complete Android interaction audit for p-autocomplete.",
    )
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.mobileui.catalog.debug")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("/tmp/pam-autocomplete-android-audit"),
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    audit = AutocompleteAudit(args.serial, args.package, args.activity, args.output)
    report = audit.run()
    print(json.dumps(report["checks"], indent=2))
    print(f"PASS p-autocomplete; evidence: {args.output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"FAIL p-autocomplete: {exception}")
        raise SystemExit(1)
