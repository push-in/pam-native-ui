#!/usr/bin/env python3
"""iOS end-to-end suite: the Android suite's scenarios as a PamNativeUITests script.

Prints the JSON step list for the PAM Native iOS UI driver (pam-native
`PamNativeUITests`, docs/ios-ui-driver.md). `expectText` steps read the PHP
state JSON (testID "php-state") back from the screen, so a step passes only
when the event reached PHP and PHP re-rendered.

    python3 tools/e2e_ios.py [--suite all] > e2e-ios.json
    cd examples/showcase/.pam-native/ios/App
    env TEST_RUNNER_PAM_UI_SCRIPT="$PWD/e2e-ios.json" TEST_RUNNER_PAM_UI_SHOT_DIR="$PWD/shots" \\
        xcodebuild test -project PamNativeApp.xcodeproj -scheme PamNativeUITests \\
        -destination "platform=iOS Simulator,id=<UDID>" -parallel-testing-enabled NO

Suites: controls, forms, events, overlays, lists, feedback, theme.
With --layout every screenshot state also writes its layout snapshot; check
them with `python3 tools/geometry.py --files shots/*.json`.
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import sys

CATALOG = "Every PAM Native UI component, on device."
SCREENS = {"Forms": "Create account", "Lists": "People"}


def state(*pairs: str, timeout: float = 6) -> dict:
    return {"expectText": "php-state", "contains": list(pairs), "timeout": timeout}


def home() -> list[dict]:
    return [{"tapIfExists": "Back", "timeout": 1}, {"tapIfExists": "Back", "timeout": 1}, {"waitFor": CATALOG, "timeout": 20}]


def open_screen(title: str, screen: str | None = None) -> list[dict]:
    return [*home(), {"tap": title}, {"waitFor": screen or SCREENS.get(title, title)}]


def controls() -> list[dict]:
    today = dt.date.today()
    day = today.strftime("%A, %B ") + str(today.day) + today.strftime(", %Y")
    return [
        *open_screen("Controls"),
        {"tap": "Text field"}, {"type": "hello pam\n"}, state('"text":"hello pam"'),
        {"tap": "Textarea"}, {"type": "multi line"}, state('"notes":"multi line"'),
        {"tapXY": [0.5, 0.12]},
        {"tap": "Date, not set"}, {"tap": day}, state(f'"date":"{today.isoformat()}"'),
        {"tap": "Choice"},
        {"tap": "Checkbox"}, state('"checked":true'),
        {"tap": "Switch"}, state('"toggled":false'),
        {"tap": "Free"}, state('"plan":"free"'),
        {"tap": "Select, none selected"}, {"waitFor": "Brazil"}, {"shot": "select-sheet"},
        {"tap": "Brazil"}, state('"country":"br"'),
        {"tap": "Multi-select, none selected"}, {"tap": "PHP"}, {"tap": "Rust"}, {"tap": "Done"},
        state('"tags":["php","rust"]'),
        {"tap": "Filter chip"}, state('"chip":false'),
        {"swipe": "down"},
        {"tap": "Range"},
        {"tap": "Increase"}, state('"count":3'),
        {"tap": "Month"}, state('"period":"month"'),
        {"shot": "controls"},
    ]


def forms() -> list[dict]:
    return [
        *open_screen("Forms"),
        {"tap": "Create account"},
        {"waitFor": "Fix the highlighted fields and try again."},
        {"expect": "Enter your email."},
        {"shot": "forms-errors"},
        {"tap": "Name, required"}, {"type": "Ada Lovelace"},
        {"tap": "Email, required"}, {"type": "ada@pam.dev"},
        {"tap": "Password, required"}, {"type": "analytical\n"},
        {"tap": "Role, required, none selected"}, {"tap": "Developer"},
        {"tap": "I accept the terms"},
        {"tap": "Create account"},
        {"swipe": "down"},
        {"waitFor": "Account created for ada@pam.dev", "timeout": 8},
        {"shot": "forms-done"},
    ]


def events() -> list[dict]:
    last = lambda event: state(f'"{event}"]')  # the newest entry closes the events list
    return [
        *open_screen("Events"),
        {"shot": "events"},
        {"tap": "Press me"}, last("btn.press"),
        {"tap": "Disabled"}, last("btn.press"),
        {"tap": "Loading button"}, last("btn.press"),
        {"tap": "Add item"}, last("iconbtn.press"),
        {"tap": "Assist"}, last("chip.press"),
        {"tap": "Filter"}, last("chip.change:on"), state('"chip":true'),
        {"tap": "Pressable card"}, last("card.press"),
        {"tap": "Open item"}, last("item.press"),
        {"tap": "Disabled item"}, last("item.press"),
        {"tap": "Dismiss"}, last("alert.close"), state('"alert":false'), {"expectNot": "Closable alert"},
        {"tap": "Second"}, last("tabs.change:b"), state('"tab":"b"'),
        {"tap": "Right"}, last("segmented.change:y"), state('"segment":"y"'),
        {"tap": "Increase"}, last("stepper.change:2"),
        {"tap": "Increase"}, {"tap": "Increase"}, state('"count":3'),
        {"tap": "Events field"}, last("field.focus"),
        {"type": "pam\n"}, state('"field.submit:pam"', '"name":"pam"'),
        {"tap": "Pressable card"}, state('"field.blur"'),
        {"tap": "Reset events"}, state('"events":[]', '"alert":true'),
    ]


def overlays() -> list[dict]:
    last = lambda event: state(f'"{event}"]')
    sheet = [{"tap": "Open filters"}, {"waitFor": "Apply"}]
    return [
        *open_screen("Overlays"),
        *sheet, {"expect": "Sort by"}, {"probe": "Apply", "name": "sheet-apply-frame"}, {"shot": "sheet"},
        {"tap": "Most popular"}, {"tap": "Unread only"}, {"tap": "Apply"}, {"expectNot": "Apply"},
        state('"sort":"popular"', '"unread":true', '"sheet.apply:popular:unread"'),
        {"waitFor": "Changes saved", "timeout": 4}, {"shot": "snackbar"},
        {"tap": "Undo"}, last("snackbar.action"), {"expectNot": "Changes saved"},
        *sheet, {"tapXY": [0.5, 0.12]}, {"expectNot": "Apply"}, last("sheet.close"), state('"sheet":false'),
        *sheet, {"drag": [0.5, 0.55, 0.5, 0.98], "velocity": 1500}, {"expectNot": "Apply"}, last("sheet.close"),
        {"tap": "Delete project"}, {"waitFor": "Delete project?"}, {"shot": "dialog"},
        {"tap": "Delete"}, {"expectNot": "Delete project?"}, last("dialog.confirm"),
        {"tap": "Delete project"}, {"tap": "Cancel"}, last("dialog.cancel"), state('"dialog":false'),
        {"tap": "Show snackbar"}, {"waitFor": "Changes saved"}, {"expectNot": "Changes saved", "timeout": 8},
        state('"snackbar":false'),
    ]


def lists() -> list[dict]:
    return [
        *open_screen("Lists"),
        {"expect": "500 people"}, {"shot": "lists"},
        {"fling": "up"}, {"fling": "up"}, {"fling": "up"}, {"wait": 1}, {"shot": "lists-scrolled"},
        {"tap": "Search people"}, {"type": "zzzz\n"}, {"waitFor": "Nobody found"}, {"shot": "lists-empty"},
    ]


def feedback() -> list[dict]:
    return [
        *open_screen("Feedback"), {"shot": "feedback"},
        {"tap": "Dismiss"}, {"expectNot": "Heads up"},
        {"tap": "Advance"}, {"expect": "Uploading"},
    ]


def theme() -> list[dict]:
    steps = [*home(), {"tap": "Dark"}, {"wait": 1}, {"shot": "catalog-dark"}]
    for title in ("Forms", "Controls", "Events", "Feedback", "Overlays"):
        steps += [*open_screen(title), {"shot": f"{title.lower()}-dark"}]
    steps += [{"tap": "Open filters"}, {"waitFor": "Apply"}, {"shot": "sheet-dark"}, {"tapXY": [0.5, 0.12]}, {"expectNot": "Apply"}]
    steps += [*home(), {"tap": "Light"}, {"wait": 1}, {"shot": "catalog-light"}, {"tap": "System"}]
    return steps


SUITES = {"controls": controls, "forms": forms, "events": events, "overlays": overlays, "lists": lists, "feedback": feedback, "theme": theme}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--suite", default="all")
    parser.add_argument("--layout", action="store_true", help="add a layout snapshot before every screenshot")
    args = parser.parse_args()
    names = list(SUITES) if args.suite == "all" else args.suite.split(",")
    steps: list[dict] = [{"launch": True}, {"wait": 4}]
    for name in names:
        steps += SUITES[name]()
    if args.layout:
        # Every screenshot state also gets its layout snapshot (tools/geometry.py).
        steps = [x for step in steps for x in (([{"layout": step["shot"]}] if "shot" in step else []) + [step])]
    json.dump(steps, sys.stdout, indent=1)
    print()
    return 0


if __name__ == "__main__":
    sys.exit(main())
