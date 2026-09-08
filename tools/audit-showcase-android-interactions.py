#!/usr/bin/env python3

from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from dataclasses import dataclass
from enum import IntEnum
from pathlib import Path
from typing import Iterable


class InteractionKind(IntEnum):
    STATIC = 1
    PRESS = 2
    TOGGLE = 3
    INPUT = 4
    ADJUST = 5
    OPEN_CLOSE = 6
    SELECT = 7
    SCROLL = 8
    GESTURE = 9


class ResultStatus(IntEnum):
    PASSED = 1
    FAILED = 2


STATIC = {
    "p-alert", "p-avatar", "p-badge", "p-banner", "p-card",
    "p-data-table", "p-divider", "p-empty-state", "p-icon", "p-img",
    "p-progress-circular", "p-progress-linear", "p-sheet",
    "p-skeleton-loader", "p-sparkline", "p-timeline",
    "p-expansion-panel-text",
    "p-stepper-header", "p-stepper-window", "p-stepper-window-item",
    "p-app-scaffold", "p-chart", "p-responsive-grid", "p-result-state",
}
PRESS = {
    "p-app-bar", "p-app-bar-nav-icon", "p-banner-actions", "p-btn",
    "p-btn-group", "p-card-actions", "p-chip", "p-fab", "p-icon-btn",
    "p-list-item", "p-snackbar", "p-stepper-actions",
    "p-stepper-vertical-actions", "p-toolbar",
    "p-timeline-item", "p-infinite-scroll",
    "p-progress-button",
}
TOGGLE = {
    "p-checkbox", "p-radio", "p-radio-group",
    "p-switch",
}
INPUT = {
    "p-color-input", "p-form", "p-number-input", "p-otp-input",
    "p-text-field", "p-textarea",
    "p-currency-field", "p-masked-field", "p-password-field", "p-search-bar",
}
ADJUST = {"p-range-slider", "p-rating", "p-slider"}
OPEN_CLOSE = {
    "p-autocomplete", "p-bottom-sheet", "p-combobox", "p-date-input",
    "p-dialog", "p-menu", "p-overlay", "p-select", "p-speed-dial",
    "p-time-picker", "p-tooltip",
    "p-command-palette", "p-date-range-picker", "p-file-input",
    "p-multi-select", "p-popover", "p-tag-input",
    "p-time-range-picker",
}
SELECT = {
    "p-btn-toggle", "p-calendar", "p-calendar-day", "p-carousel",
    "p-carousel-item", "p-chip-group", "p-date-picker",
    "p-expansion-panel", "p-expansion-panel-title",
    "p-expansion-panels", "p-slide-group", "p-slide-group-item",
    "p-stepper", "p-stepper-item", "p-stepper-vertical",
    "p-stepper-vertical-item",
    "p-tab", "p-tabs", "p-treeview", "p-treeview-item", "p-item", "p-item-group",
    "p-list",
    "p-bottom-app-bar", "p-data-grid", "p-filter-bar", "p-navigation-bar",
    "p-navigation-drawer", "p-navigation-rail", "p-pagination",
    "p-segmented-button", "p-tree-select",
}
SCROLL = {"p-data-table-virtual", "p-section-list", "p-virtual-list"}
GESTURE = {"p-pull-to-refresh", "p-reorderable-list", "p-swipe-actions"}

PARENT_ROUTE: dict[str, str] = {}

OPEN_LABELS = {
    "p-bottom-sheet": ("Open bottom sheet",),
    "p-dialog": ("Open dialog",),
    "p-menu": ("Open menu",),
    "p-overlay": ("Show overlay",),
    "p-speed-dial": ("+", "Create actions"),
    "p-tooltip": ("More information",),
}

PRESS_LABELS = {
    "p-snackbar": ("Undo",),
    "p-stepper-actions": ("Continue to next step",),
    "p-stepper-vertical-actions": ("Continue to next step",),
    "p-infinite-scroll": ("Load more items",),
}

ERROR_MARKERS = (
    "Pam Native runtime error",
    "failed integrity verification",
    "Unknown native icon",
)


@dataclass(frozen=True)
class Bounds:
    left: int
    top: int
    right: int
    bottom: int

    @property
    def width(self) -> int:
        return max(0, self.right - self.left)

    @property
    def height(self) -> int:
        return max(0, self.bottom - self.top)

    @property
    def center(self) -> tuple[int, int]:
        return ((self.left + self.right) // 2, (self.top + self.bottom) // 2)


@dataclass
class Hierarchy:
    root: ET.Element
    xml: str
    parents: dict[ET.Element, ET.Element]

    @classmethod
    def parse(cls, xml: str) -> "Hierarchy":
        root = ET.fromstring(xml)
        parents = {child: parent for parent in root.iter() for child in parent}
        return cls(root=root, xml=xml, parents=parents)

    def nodes(self) -> Iterable[ET.Element]:
        return self.root.iter("node")

    def all_text(self) -> str:
        return "\n".join(
            value
            for node in self.nodes()
            for value in (node.attrib.get("text", ""), node.attrib.get("content-desc", ""))
            if value
        )


class AuditFailure(RuntimeError):
    pass


class AndroidAudit:
    def __init__(
        self,
        serial: str,
        package: str,
        activity: str,
        settle_seconds: float,
        evidence_directory: Path,
    ) -> None:
        self.serial = serial
        self.package = package
        self.activity = activity
        self.settle_seconds = settle_seconds
        self.evidence_directory = evidence_directory
        self.evidence_directory.mkdir(parents=True, exist_ok=True)
        self.original_settings: dict[str, str] = {}
        self.task_locked = False

    def adb(
        self,
        *arguments: str,
        timeout: float = 20.0,
        binary: bool = False,
        check: bool = True,
    ) -> str | bytes:
        result = subprocess.run(
            ["adb", "-s", self.serial, *arguments],
            check=False,
            capture_output=True,
            timeout=timeout,
            text=not binary,
        )
        if check and result.returncode != 0:
            stderr = result.stderr.decode(errors="replace") if binary else result.stderr
            raise AuditFailure(
                f"adb {' '.join(arguments)} failed ({result.returncode}): {stderr.strip()}"
            )
        return result.stdout

    def shell(self, *arguments: str, timeout: float = 20.0) -> str:
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
        self.shell("input", "keyevent", "WAKEUP")
        self.shell("wm", "dismiss-keyguard")
        time.sleep(0.8)
        if self.device_locked():
            raise AuditFailure(
                "the physical Android device is locked; unlock it before the audit"
            )
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
            if self.shell("pm", "clear", self.package).strip() != "Success":
                raise AuditFailure(f"could not clear {self.package} before the audit")
            self.shell("logcat", "-c")
        except BaseException:
            self.restore()
            raise

    def restore(self) -> None:
        self.stop_task_lock()
        for key, value in self.original_settings.items():
            namespace, name = key.split(":", 1)
            if value in {"", "null"}:
                continue
            try:
                self.set_setting(namespace, name, value)
            except (AuditFailure, subprocess.SubprocessError):
                continue

    @staticmethod
    def foreground_package(report: str) -> str | None:
        match = re.search(
            r"(?:topResumedActivity|mResumedActivity|ResumedActivity|Resumed)"
            r"\s*[:=].*?\s([A-Za-z0-9_.]+)/(?:[A-Za-z0-9_.$]+)",
            report,
        )
        return match.group(1) if match else None

    def assert_foreground(self, context: str) -> None:
        report = self.shell("dumpsys", "activity", "activities", timeout=30.0)
        resumed = self.foreground_package(report)
        if resumed != self.package:
            raise AuditFailure(
                f"{context}: audit app lost foreground "
                f"(expected {self.package}, found {resumed or 'unknown'})"
            )

    def start_task_lock(self) -> None:
        report = self.shell("dumpsys", "activity", "activities", timeout=30.0)
        match = re.search(
            rf"mResumedActivity:.*?\s{re.escape(self.package)}/.*?\st(\d+)",
            report,
        ) or re.search(
            rf"ActivityRecord\{{[^\n]*?\s{re.escape(self.package)}/.*?\st(\d+)\}}",
            report,
        )
        if match is None:
            raise AuditFailure("could not resolve the PAM task before task pinning")
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

    def launch(self, route: str) -> None:
        self.stop_task_lock()
        report = self.adb(
            "shell", "am", "start", "-W", "-S",
            "-n", f"{self.package}/{self.activity}",
            "-d", f"pam-showcase://audit/{route}",
            timeout=30.0,
        )
        assert isinstance(report, str)
        if "Status: ok" not in report:
            raise AuditFailure(f"Android did not confirm the {route} deep link: {report.strip()}")
        time.sleep(self.settle_seconds)
        # Android may restore the native ScrollView offset after a force-stop.
        # Normalize the viewport so route identity and screenshots always begin
        # at the authoritative heading rather than a persisted lower variation.
        for _ in range(3):
            self.adb(
                "shell", "input", "swipe",
                "540", "520", "540", "2100", "160",
            )
        time.sleep(0.35)

    def dump(self, name: str) -> Hierarchy:
        self.assert_foreground(f"hierarchy {name}")
        remote = f"/sdcard/pam-interaction-{name}.xml"
        self.adb("shell", "rm", "-f", remote)
        self.adb("shell", "uiautomator", "dump", "--compressed", remote, timeout=25.0)
        xml = self.adb("exec-out", "cat", remote, timeout=10.0)
        assert isinstance(xml, str)
        path = self.evidence_directory / f"{name}.xml"
        path.write_text(xml, encoding="utf-8")
        return Hierarchy.parse(xml)

    def screenshot_hash(self, name: str | None = None) -> str:
        was_locked = self.task_locked
        if was_locked:
            self.stop_task_lock()
            time.sleep(0.35)
        try:
            self.assert_foreground(f"screenshot {name or 'probe'}")
            payload = self.adb("exec-out", "screencap", "-p", timeout=15.0, binary=True)
            assert isinstance(payload, bytes)
            if name is not None:
                (self.evidence_directory / f"{name}.png").write_bytes(payload)
            return hashlib.sha256(payload).hexdigest()
        finally:
            if was_locked:
                self.start_task_lock()
                time.sleep(0.25)

    def settled_screenshot_hash(self, name: str | None = None) -> str:
        """Capture evidence after Material state layers and PHP reconciliation settle."""
        time.sleep(max(0.45, self.settle_seconds))
        return self.screenshot_hash(name)

    def tap(self, bounds: Bounds) -> None:
        self.assert_foreground("tap")
        x, y = bounds.center
        self.adb("shell", "input", "tap", str(x), str(y))
        time.sleep(0.8)
        self.assert_foreground("tap result")

    def long_press(self, bounds: Bounds, duration: int = 700) -> None:
        self.assert_foreground("long press")
        x, y = bounds.center
        self.adb(
            "shell", "input", "swipe",
            str(x), str(y), str(x), str(y), str(duration),
        )
        time.sleep(0.8)
        self.assert_foreground("long press result")

    def touch(self, action: str, bounds: Bounds) -> None:
        self.assert_foreground(f"touch {action.lower()}")
        x, y = bounds.center
        self.adb("shell", "input", "motionevent", action, str(x), str(y))

    def swipe(self, start: tuple[int, int], end: tuple[int, int], duration: int = 700) -> None:
        self.assert_foreground("swipe")
        self.adb(
            "shell", "input", "swipe",
            str(start[0]), str(start[1]), str(end[0]), str(end[1]), str(duration),
        )
        time.sleep(1.0)
        self.assert_foreground("swipe result")

    def back(self) -> None:
        self.assert_foreground("system Back")
        self.adb("shell", "input", "keyevent", "BACK")
        time.sleep(0.8)
        self.assert_foreground("system Back result")


def bounds(node: ET.Element) -> Bounds:
    values = [int(value) for value in re.findall(r"\d+", node.attrib.get("bounds", ""))]
    if len(values) != 4:
        return Bounds(0, 0, 0, 0)
    return Bounds(*values)


def visible(node: ET.Element) -> bool:
    area = bounds(node)
    return area.width > 0 and area.height > 0 and area.bottom > 0 and area.top < 2400


def enabled(node: ET.Element) -> bool:
    return node.attrib.get("enabled") == "true" and visible(node)


def is_navigation(node: ET.Element) -> bool:
    return node.attrib.get("content-desc") == "Open component navigation"


def descendant_labels(node: ET.Element) -> set[str]:
    return {
        value
        for descendant in node.iter("node")
        for value in (
            descendant.attrib.get("text", ""),
            descendant.attrib.get("content-desc", ""),
        )
        if value
    }


def interactive_nodes(hierarchy: Hierarchy) -> list[ET.Element]:
    return [
        node
        for node in hierarchy.nodes()
        if enabled(node)
        and not is_navigation(node)
        and node.attrib.get("clickable") == "true"
        and node.attrib.get("class") != "android.widget.ScrollView"
    ]


def nearest_node(
    hierarchy: Hierarchy,
    original: ET.Element,
    predicate,
) -> ET.Element | None:
    original_bounds = bounds(original)
    ox, oy = original_bounds.center
    matches = [node for node in hierarchy.nodes() if enabled(node) and predicate(node)]
    if not matches:
        return None
    return min(
        matches,
        key=lambda node: abs(bounds(node).center[0] - ox) + abs(bounds(node).center[1] - oy),
    )


def assert_healthy(hierarchy: Hierarchy, expected_route: str, allow_overlay: bool = False) -> None:
    text = hierarchy.all_text()
    for marker in ERROR_MARKERS:
        if marker in text:
            raise AuditFailure(f"runtime error marker is visible: {marker}")
    if not allow_overlay and expected_route not in text:
        raise AuditFailure(f"route marker {expected_route!r} is not visible")


def component_title(tag: str) -> str:
    replacements = {
        "btn": "Button",
        "fab": "FAB",
        "img": "Image",
        "otp": "OTP",
        "ui": "UI",
    }
    return " ".join(
        replacements.get(part, part.capitalize())
        for part in tag.removeprefix("p-").split("-")
    )


def assert_route_top(hierarchy: Hierarchy, tag: str) -> None:
    title = component_title(tag)
    title_nodes = [
        node for node in hierarchy.nodes()
        if title in {node.attrib.get("text", ""), node.attrib.get("content-desc", "")}
        and bounds(node).top < 260
    ]
    variation_nodes = [
        node for node in hierarchy.nodes()
        if "Variations" in {node.attrib.get("text", ""), node.attrib.get("content-desc", "")}
        and bounds(node).top < 520
    ]
    if not title_nodes or not variation_nodes:
        visible = [
            value
            for node in hierarchy.nodes()
            if bounds(node).top < 520
            for value in (node.attrib.get("text", ""), node.attrib.get("content-desc", ""))
            if value
        ]
        raise AuditFailure(
            f"deep link did not render {title!r} at its authoritative route top; "
            f"visible labels were {visible[:12]!r}"
        )


def choose_open_node(tag: str, hierarchy: Hierarchy) -> ET.Element:
    labels = set(OPEN_LABELS.get(tag, ()))
    candidates = interactive_nodes(hierarchy)
    if labels:
        for candidate in candidates:
            if labels & descendant_labels(candidate):
                return candidate
            if candidate.attrib.get("content-desc") in labels:
                return candidate
    preferred_classes = {
        "android.widget.Spinner", "android.widget.EditText", "android.widget.Button"
    }
    for candidate in candidates:
        if candidate.attrib.get("class") in preferred_classes:
            return candidate
    # Some compound controls intentionally expose one accessible parent while
    # their visual trigger is focusable rather than separately clickable.
    fallback_labels = {
        "p-popover": "Show popover details",
    }
    fallback_label = fallback_labels.get(tag)
    if fallback_label is not None:
        for candidate in hierarchy.nodes():
            if (
                enabled(candidate)
                and candidate.attrib.get("content-desc") == fallback_label
            ):
                return candidate
    if not candidates:
        raise AuditFailure("no enabled overlay trigger is exposed")
    return candidates[0]


def choose_selection_node(hierarchy: Hierarchy) -> ET.Element:
    candidates = interactive_nodes(hierarchy)
    leaf_candidates = [
        candidate
        for candidate in candidates
        if candidate.attrib.get("selected") != "true"
        and candidate.attrib.get("class") not in {
            "android.widget.CalendarView", "android.widget.ScrollView"
        }
        and candidate.attrib.get("content-desc") != "Open component navigation"
        and not candidate.attrib.get("content-desc", "").startswith("Open Front navigation drawer")
        and not candidate.attrib.get("content-desc", "").startswith("Open Slide navigation drawer")
    ]
    # Selection routes share the screen with the catalog navigation button.
    # Prefer actual leaf controls so the audit cannot pass or fail by opening
    # the drawer instead of exercising the component under test.
    preferred_classes = {
        "android.widget.Button", "android.widget.RadioButton",
        "android.widget.CheckBox", "android.widget.Spinner",
    }
    for candidate in leaf_candidates:
        if (
            candidate.attrib.get("class") in preferred_classes
            and (
                candidate.attrib.get("content-desc")
                or descendant_labels(candidate)
            )
        ):
            return candidate
    for candidate in leaf_candidates:
        if descendant_labels(candidate):
            return candidate
    if leaf_candidates:
        return leaf_candidates[0]
    if candidates:
        return candidates[0]
    raise AuditFailure("no enabled selection target is exposed")


def interaction_kind(tag: str) -> InteractionKind:
    groups = {
        InteractionKind.STATIC: STATIC,
        InteractionKind.PRESS: PRESS,
        InteractionKind.TOGGLE: TOGGLE,
        InteractionKind.INPUT: INPUT,
        InteractionKind.ADJUST: ADJUST,
        InteractionKind.OPEN_CLOSE: OPEN_CLOSE,
        InteractionKind.SELECT: SELECT,
        InteractionKind.SCROLL: SCROLL,
        InteractionKind.GESTURE: GESTURE,
    }
    matches = [kind for kind, tags in groups.items() if tag in tags]
    if len(matches) != 1:
        raise AuditFailure(f"{tag} has {len(matches)} interaction classifications")
    return matches[0]


def exercise(
    audit: AndroidAudit,
    tag: str,
    route: str,
    kind: InteractionKind,
    before: Hierarchy,
    evidence_prefix: str | None = None,
) -> tuple[Hierarchy, bool]:
    evidence_name = evidence_prefix or tag
    before_hash = audit.screenshot_hash(f"{evidence_name}-before")
    changed = False

    if kind == InteractionKind.STATIC:
        return before, False

    if kind == InteractionKind.TOGGLE:
        candidates = [
            node for node in before.nodes()
            if enabled(node) and node.attrib.get("checkable") == "true"
        ]
        if not candidates:
            raise AuditFailure("no enabled checkable control is exposed")
        target = next(
            (candidate for candidate in candidates if candidate.attrib.get("checked") != "true"),
            candidates[0],
        )
        previous = target.attrib.get("checked")
        audit.tap(bounds(target))
        after = audit.dump(f"{evidence_name}-after")
        assert_healthy(after, route)
        current = nearest_node(
            after,
            target,
            lambda node: node.attrib.get("class") == target.attrib.get("class")
            and node.attrib.get("checkable") == "true",
        )
        if current is None or current.attrib.get("checked") == previous:
            raise AuditFailure("toggle did not publish a changed checked state")
        audit.settled_screenshot_hash(f"{evidence_name}-after")
        return after, True

    if kind == InteractionKind.INPUT:
        candidates = [
            node for node in before.nodes()
            if enabled(node)
            and node.attrib.get("class") == "android.widget.EditText"
            and node.attrib.get("focusable") == "true"
        ]
        if not candidates:
            raise AuditFailure("no enabled editable field is exposed")
        target = candidates[0]
        audit.tap(bounds(target))
        typed_value = {
            "p-color-input": "112233",
            "p-currency-field": "73125",
            "p-masked-field": "21912345678",
            "p-number-input": "731",
            "p-otp-input": "738204",
            "p-password-field": "PAM_AUDIT_2026",
        }.get(tag, "PAM_AUDIT")
        if tag in {
            "p-color-input", "p-currency-field", "p-masked-field",
            "p-number-input", "p-otp-input", "p-password-field",
        }:
            audit.adb("shell", "input", "keyevent", "KEYCODE_MOVE_END")
            for _ in range(16):
                audit.adb("shell", "input", "keyevent", "DEL")
        audit.adb("shell", "input", "text", typed_value)
        time.sleep(0.8)
        after = audit.dump(f"{evidence_name}-after")
        assert_healthy(after, route)
        expected_text = {
            "p-currency-field": "731,25",
            "p-masked-field": "(21) 91234-5678",
        }.get(tag, typed_value)
        retained = any(
            expected_text in node.attrib.get("text", "")
            for node in after.nodes()
            if node.attrib.get("class") == "android.widget.EditText"
        )
        if tag == "p-password-field":
            before_values = [
                node.attrib.get("text", "")
                for node in before.nodes()
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            after_values = [
                node.attrib.get("text", "")
                for node in after.nodes()
                if node.attrib.get("class") == "android.widget.EditText"
            ]
            retained = after_values != before_values
        if not retained:
            raise AuditFailure("typed text was not retained by the native input")
        audit.settled_screenshot_hash(f"{evidence_name}-after")
        audit.back()
        return after, True

    if kind == InteractionKind.GESTURE:
        previews = [
            node for node in before.nodes()
            if enabled(node)
            and node.attrib.get("content-desc")
            in {
                "Pull To Refresh preview",
                "Reorderable List preview",
                "Swipe Actions preview",
            }
        ]
        if not previews:
            raise AuditFailure("no enabled gesture surface is exposed")
        area = bounds(previews[0])
        if tag == "p-pull-to-refresh":
            audit.swipe(area.center, (area.center[0], area.bottom - 20), 800)
        elif tag == "p-reorderable-list":
            start = (area.left + 70, area.top + min(70, area.height // 4))
            end = (start[0], min(area.bottom - 40, start[1] + 300))
            audit.swipe(start, end, 1400)
        else:
            audit.swipe(
                (area.right - 80, area.center[1]),
                (area.left + 120, area.center[1]),
                550,
            )
        after = audit.dump(f"{evidence_name}-after")
        assert_healthy(after, route, allow_overlay=False)
        after_hash = audit.settled_screenshot_hash(f"{evidence_name}-after")
        if after.xml == before.xml and after_hash == before_hash:
            raise AuditFailure("gesture produced no hierarchy or visual change")
        return after, True

    if kind == InteractionKind.ADJUST:
        candidates = [
            node for node in before.nodes()
            if enabled(node) and node.attrib.get("class") == "android.widget.SeekBar"
        ]
        if not candidates:
            raise AuditFailure("no enabled adjustable control is exposed")
        area = bounds(candidates[0])
        y = (area.top + area.bottom) // 2
        audit.swipe(
            (area.left + area.width // 4, y),
            (area.left + (area.width * 3) // 4, y),
        )
        after = audit.dump(f"{evidence_name}-after")
        assert_healthy(after, route)
        changed = audit.settled_screenshot_hash(f"{evidence_name}-after") != before_hash
        if not changed:
            raise AuditFailure("adjust gesture produced no visible state change")
        return after, True

    if kind == InteractionKind.OPEN_CLOSE:
        target = choose_open_node(tag, before)
        if tag == "p-file-input":
            audit.stop_task_lock()
            x, y = bounds(target).center
            audit.adb("shell", "input", "tap", str(x), str(y))
            time.sleep(1.0)
            report = audit.shell("dumpsys", "activity", "activities", timeout=30.0)
            resumed = audit.foreground_package(report)
            if resumed != "com.google.android.documentsui":
                raise AuditFailure(
                    "file input did not launch Android's document picker "
                    f"(found {resumed or 'unknown'})"
                )
            picker = audit.adb("exec-out", "screencap", "-p", binary=True)
            assert isinstance(picker, bytes)
            (audit.evidence_directory / f"{evidence_name}-open.png").write_bytes(picker)
            audit.adb("shell", "input", "keyevent", "BACK")
            time.sleep(0.8)
            audit.assert_foreground("file picker cancellation")
            audit.start_task_lock()
            closed = audit.dump(f"{evidence_name}-closed")
            assert_healthy(closed, route)
            audit.screenshot_hash(f"{evidence_name}-closed")
            return closed, True
        if tag == "p-tooltip":
            audit.touch("DOWN", bounds(target))
            time.sleep(0.75)
            open_hash = audit.screenshot_hash(f"{evidence_name}-open")
            if open_hash == before_hash:
                audit.touch("UP", bounds(target))
                raise AuditFailure("tooltip did not appear while long press was held")
            audit.touch("UP", bounds(target))
            time.sleep(0.5)
            closed = audit.dump(f"{evidence_name}-closed")
            assert_healthy(closed, route)
            closed_hash = audit.screenshot_hash(f"{evidence_name}-closed")
            if closed_hash == open_hash:
                raise AuditFailure("tooltip remained visible after long press release")
            return closed, True
        audit.tap(bounds(target))
        # UiAutomator's dump waits for an idle window. Animated loading samples
        # behind a modal keep Android's accessibility window non-idle, so an
        # open overlay can legitimately produce no XML file. The screenshot is
        # the reliable open-state assertion; the hierarchy is asserted again
        # immediately after the overlay closes.
        open_hash = audit.screenshot_hash(f"{evidence_name}-open")
        changed = open_hash != before_hash
        if not changed:
            raise AuditFailure("overlay trigger produced no visual change")
        if tag in {"p-menu", "p-speed-dial"}:
            opened = audit.dump(f"{evidence_name}-open")
            expected_action_labels = (
                {"Edit profile", "Manage notifications", "Sign out"}
                if tag == "p-menu"
                else {"New message", "Upload file", "Create folder"}
            )
            actions = [
                node for node in interactive_nodes(opened)
                if expected_action_labels & descendant_labels(node)
            ]
            if not actions:
                raise AuditFailure(f"{tag} opened without exposing an enabled action")
            audit.tap(bounds(actions[0]))
            selected = audit.dump(f"{evidence_name}-selected")
            assert_healthy(selected, route)
            if tag == "p-menu" and not any(
                label in selected.all_text()
                for label in {"Profile selected", "Notifications selected", "Sign out selected"}
            ):
                raise AuditFailure("p-menu action closed the surface without publishing its result")
            if tag == "p-menu" and any(
                expected_action_labels & descendant_labels(node)
                for node in interactive_nodes(selected)
            ):
                raise AuditFailure("p-menu action remained visible after selection")
            if tag == "p-speed-dial" and "✓" not in selected.all_text():
                raise AuditFailure("p-speed-dial action closed without publishing completion")
            selected_hash = audit.settled_screenshot_hash(f"{evidence_name}-selected")
            if selected_hash == open_hash:
                raise AuditFailure(f"{tag} action produced no visible result")
            return selected, True
        if tag == "p-popover":
            # Dismiss through the component's scrim. Android Back can finish
            # the freshly deep-linked Activity and reveal another installed
            # build, which tests task history rather than popover behavior.
            audit.tap(Bounds(24, 260, 120, 356))
        else:
            audit.back()
        closed = audit.dump(f"{evidence_name}-closed")
        assert_healthy(closed, route)
        audit.screenshot_hash(f"{evidence_name}-closed")
        return closed, True

    if kind == InteractionKind.SCROLL:
        scrolls = [
            node for node in before.nodes()
            if enabled(node) and node.attrib.get("scrollable") == "true"
        ]
        if not scrolls:
            raise AuditFailure("no scrollable viewport is exposed")
        area = bounds(max(scrolls, key=lambda node: bounds(node).height))
        x = (area.left + area.right) // 2
        audit.swipe((x, area.bottom - 80), (x, area.top + 120), 800)
        after = audit.dump(f"{evidence_name}-after")
        # The route marker lives above the virtual table and is expected to
        # leave the viewport after a successful scroll. Runtime error markers
        # must still be absent, while viewport movement is asserted below.
        assert_healthy(after, route, allow_overlay=True)
        after_hash = audit.settled_screenshot_hash(f"{evidence_name}-after")
        if after.xml == before.xml and after_hash == before_hash:
            raise AuditFailure("scroll gesture produced no viewport change")
        return after, True

    if kind == InteractionKind.SELECT and tag == "p-data-grid":
        scrolls = [
            node for node in before.nodes()
            if enabled(node) and node.attrib.get("scrollable") == "true"
        ]
        if not scrolls:
            raise AuditFailure("data grid route exposes no scrollable catalog viewport")
        area = bounds(max(scrolls, key=lambda node: bounds(node).height))
        audit.swipe(
            (area.center[0], area.bottom - 100),
            (area.center[0], area.top + 220),
            900,
        )
        scrolled = audit.dump(f"{evidence_name}-selectable")
        tables = [
            node for node in scrolled.nodes()
            if enabled(node)
            and node.attrib.get("class") == "android.widget.TableLayout"
            and bounds(node).height > 100
        ]
        if not tables:
            raise AuditFailure("selectable data grid did not enter the viewport")
        table = max(tables, key=lambda node: bounds(node).top)
        table_bounds = bounds(table)
        audit.tap(Bounds(
            table_bounds.left + 24,
            table_bounds.top + min(180, table_bounds.height // 3),
            min(table_bounds.left + 136, table_bounds.right),
            table_bounds.top + min(280, table_bounds.height // 2),
        ))
        after = audit.dump(f"{evidence_name}-after")
        assert_healthy(after, route, allow_overlay=True)
        if "No rows selected" in after.all_text():
            raise AuditFailure("selectable data grid row did not publish selection")
        audit.settled_screenshot_hash(f"{evidence_name}-after")
        return after, True

    if kind == InteractionKind.SELECT:
        target = choose_selection_node(before)
    else:
        candidates = interactive_nodes(before)
        labels = set(PRESS_LABELS.get(tag, ()))
        target = next(
            (
                candidate for candidate in candidates
                if labels & descendant_labels(candidate)
                and not any(
                    descendant is not candidate
                    and descendant.attrib.get("clickable") == "true"
                    for descendant in candidate.iter("node")
                )
            ),
            candidates[0] if candidates else None,
        )
    if target is None:
        raise AuditFailure("no enabled press target is exposed")
    audit.tap(bounds(target))
    after = audit.dump(f"{evidence_name}-after")
    assert_healthy(after, route, allow_overlay=False)
    after_hash = audit.settled_screenshot_hash(f"{evidence_name}-after")
    changed = after.xml != before.xml or after_hash != before_hash
    if not changed:
        action = "selection" if kind == InteractionKind.SELECT else "press"
        raise AuditFailure(f"{action} produced no hierarchy or visual change")
    return after, changed


def catalog_tags(root: Path) -> list[str]:
    content = (root / "docs" / "catalog.md").read_text(encoding="utf-8")
    return sorted({match[1:] for match in re.findall(r"<p-[a-z0-9-]+", content)})


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Exercise every PAM Native UI showcase component on Android.",
    )
    parser.add_argument("--serial", default="", help="ADB serial; defaults to ANDROID_SERIAL")
    parser.add_argument("--package", default="dev.pam.mobileui.catalog")
    parser.add_argument("--activity", default="dev.pam.nativeapp.PamActivity")
    parser.add_argument("--settle-seconds", type=float, default=1.5)
    parser.add_argument("--output", type=Path, default=Path("/tmp/pam-interaction-audit.json"))
    parser.add_argument(
        "--evidence-directory",
        type=Path,
        default=Path("/tmp/pam-interaction-audit-evidence"),
    )
    parser.add_argument("--tags", nargs="*", default=[])
    parser.add_argument(
        "--repetitions",
        type=int,
        default=1,
        help="interaction passes; the separate physical gate requires two",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    serial = args.serial or __import__("os").environ.get("ANDROID_SERIAL", "")
    if not serial:
        raise AuditFailure("set --serial or ANDROID_SERIAL")
    root = Path(__file__).resolve().parents[1]
    all_tags = catalog_tags(root)
    classified = (
        STATIC | PRESS | TOGGLE | INPUT | ADJUST | OPEN_CLOSE
        | SELECT | SCROLL | GESTURE
    )
    if classified != set(all_tags):
        missing = sorted(set(all_tags) - classified)
        extra = sorted(classified - set(all_tags))
        raise AuditFailure(f"interaction inventory mismatch; missing={missing}, extra={extra}")
    tags = args.tags or all_tags
    unknown = sorted(set(tags) - set(all_tags))
    if unknown:
        raise AuditFailure(f"unknown tags requested: {unknown}")
    if args.repetitions < 1:
        raise AuditFailure("at least one interaction pass is required")

    audit = AndroidAudit(
        serial=serial,
        package=args.package,
        activity=args.activity,
        settle_seconds=args.settle_seconds,
        evidence_directory=args.evidence_directory,
    )
    results: list[dict[str, object]] = []
    failed_components: set[str] = set()
    failed_attempts = 0
    audit.prepare()
    try:
        package_report = audit.shell("pm", "path", args.package).strip()
        package_path = package_report.splitlines()[0].removeprefix("package:")
        build_hash = audit.shell("sha256sum", package_path).split()[0]
        device_info = {
            "serial": serial,
            "manufacturer": audit.shell("getprop", "ro.product.manufacturer").strip(),
            "model": audit.shell("getprop", "ro.product.model").strip(),
            "androidVersion": audit.shell("getprop", "ro.build.version.release").strip(),
            "api": int(audit.shell("getprop", "ro.build.version.sdk").strip()),
            "viewport": audit.shell("wm", "size").strip().replace("\n", "; "),
            "density": audit.shell("wm", "density").strip().replace("\n", "; "),
            "buildSha256": build_hash,
        }
        attempt_total = len(tags) * args.repetitions
        attempt_index = 0
        for repetition in range(1, args.repetitions + 1):
            for tag in tags:
                attempt_index += 1
                route = PARENT_ROUTE.get(tag, tag)
                kind = interaction_kind(tag)
                started = time.monotonic()
                evidence_prefix = f"pass-{repetition:02d}-{tag}"
                try:
                    audit.shell("logcat", "-c")
                    audit.launch(route)
                    before = audit.dump(f"{evidence_prefix}-before")
                    assert_route_top(before, tag)
                    assert_healthy(before, route)
                    _, changed = exercise(
                        audit,
                        tag,
                        route,
                        kind,
                        before,
                        evidence_prefix=evidence_prefix,
                    )
                    pid = audit.shell("pidof", args.package).strip()
                    logs = audit.shell(
                        "logcat", "--pid", pid, "-d", "-t", "1600", timeout=30.0,
                    ) if pid else ""
                    runtime_errors = [
                        line for line in logs.splitlines()
                        if any(marker in line for marker in (
                            "FATAL EXCEPTION", " E AndroidRuntime:",
                            "Pam Native runtime error", "failed integrity verification",
                            "Unknown native icon", "Input dispatching timed out",
                        ))
                        or "requestLayout() improperly called" in line
                    ]
                    if runtime_errors:
                        raise AuditFailure(
                            "runtime errors found in logcat: "
                            + " | ".join(runtime_errors[-8:])
                        )
                    result_status = ResultStatus.PASSED
                    detail = ""
                except Exception as exception:  # keep the full inventory running
                    failed_attempts += 1
                    failed_components.add(tag)
                    result_status = ResultStatus.FAILED
                    changed = False
                    detail = str(exception)
                elapsed_ms = int((time.monotonic() - started) * 1000)
                results.append({
                    "component": tag,
                    "route": route,
                    "repetition": repetition,
                    "interactionKind": int(kind),
                    "resultStatus": int(result_status),
                    "stateChanged": changed,
                    "elapsedMs": elapsed_ms,
                    "detail": detail,
                    "evidencePrefix": evidence_prefix,
                })
                symbol = "PASS" if result_status == ResultStatus.PASSED else "FAIL"
                print(
                    f"[{attempt_index:03d}/{attempt_total:03d}] {symbol} "
                    f"{tag} pass {repetition}/{args.repetitions} "
                    f"({kind.name.lower()}) {detail}",
                    flush=True,
                )
    finally:
        audit.restore()

    report = {
        "schemaVersion": 1,
        "device": device_info,
        "componentCount": len(tags),
        "repetitions": args.repetitions,
        "attemptCount": len(results),
        "passedCount": len(tags) - len(failed_components),
        "failedCount": len(failed_components),
        "failedAttemptCount": failed_attempts,
        "evidenceDirectory": str(args.evidence_directory),
        "results": results,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(
        f"Audited {len(tags)} components across {args.repetitions} passes: "
        f"{len(tags) - len(failed_components)} passed, "
        f"{len(failed_components)} failed. "
        f"Report: {args.output}",
        flush=True,
    )
    return 1 if failed_components else 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except AuditFailure as exception:
        print(f"audit error: {exception}", file=sys.stderr)
        raise SystemExit(2)
