#!/usr/bin/env python3
"""Zero-errors gate for a running debug app: logcat and hot reload.

    python3 tools/zero_errors.py logcat --serial S [--package P] [--since-clear]
    python3 tools/zero_errors.py hot-reload --serial S --project DIR --files a.pam b.pam

`logcat` fails on any error or warning the app's process logged (PHP notices,
warnings and deprecations surface there as PamNative lines too), ignoring a
short, documented list of platform noise that no app code can cause.
`hot-reload` edits several files at once under `pam dev` (a trailing newline,
reverted afterwards), waits for the reload and then runs the logcat gate: a
multi-file reload must not log a single error.
"""

from __future__ import annotations

import argparse
import re
import subprocess
import sys
import time
from pathlib import Path

# Lines from the platform, emulator images or the IME that the app cannot
# influence. Everything else at W/E/F from the app process is a finding.
PLATFORM_NOISE = [
    r"OpenGLRenderer: (Unable to match the desired swap behavior|Failed to choose config)",
    r"EGL_emulation",
    r"HWUI: Failed to initialize",
    r"ziparchive: Unable to open",
    r"nativeloader",
    r"libc\s*: Access denied finding property",
    r"VRI\[.*\]: (Cancelling event|Dropping event)",
    r"InputEventReceiver: Attempted to finish an input event",
    r"ProfileInstaller",
    r"System\.err: .*FileNotFoundException: /proc/",
    r"AndroidRuntime: .*Shutting down VM",  # follows a crash, which itself is reported
    r"ImeTracker",
    r"InsetsController",
    r"WindowOnBackDispatcher",
    r"CompatChangeReporter",
    r"Choreographer: Skipped \d+ frames",  # measured separately (gfxinfo), not an error
    r"ashmem",
    r"TextInputManager|InputMethodManager",
    r"BLASTBufferQueue",
    r"FrameEvents: updateAcquireFence",
    r"userfaultfd: MOVE ioctl",  # ART on emulator kernels
    r"Unexpected CPU variant|Known variants:",  # ART on x86_64 emulators
    r"InteractionJankMonitor: Initializing without READ_DEVICE_CONFIG",
    r"HWUI\s*: (Unknown dataspace|Failed to initialize 101010-2|Failed to choose config)",
    r"FrameTracker: Missed SF frame",  # system jank tracker; jank is measured with gfxinfo
    r"RemoteInputConnectionImpl: requestCursorUpdates on inactive InputConnection",  # IME after the field blurs
]


def adb(serial: str, *args: str) -> str:
    return subprocess.run(["adb", "-s", serial, *args], check=True, capture_output=True, text=True).stdout


def app_lines(serial: str, package: str) -> list[str]:
    pid = adb(serial, "shell", "pidof", package).strip().split()
    if not pid:
        raise RuntimeError(f"{package} is not running")
    out = adb(serial, "logcat", "-d", "-v", "brief", f"--pid={pid[0]}", "*:W")
    noise = re.compile("|".join(PLATFORM_NOISE))
    lines = []
    for line in out.splitlines():
        if not re.match(r"^[WEF]/", line):
            continue
        # "W/Tag( 1234): message" -> "W/Tag: message" before matching noise.
        plain = re.sub(r"^([WEF])/\s*(.*?)\s*\(\s*\d+\):", r"\1/\2:", line)
        if not noise.search(plain):
            lines.append(line)
    return lines


def gate(serial: str, package: str) -> int:
    lines = app_lines(serial, package)
    for line in lines[:60]:
        print("FAIL " + line)
    print(f"{'PASS' if not lines else 'FAIL'} zero errors: {len(lines)} warning/error lines from {package}")
    return 1 if lines else 0


def hot_reload(serial: str, package: str, project: Path, files: list[str], log: Path | None) -> int:
    adb(serial, "logcat", "-c")
    edited = [project / name for name in files]
    originals = {path: path.read_bytes() for path in edited}
    marker = log.read_text().count("reload") if log and log.exists() else 0
    try:
        for path in edited:
            path.write_bytes(originals[path] + b"\n")
        deadline = time.time() + 30
        while log and time.time() < deadline and log.read_text().count("reload") <= marker:
            time.sleep(0.3)
        time.sleep(3)
    finally:
        for path, content in originals.items():
            path.write_bytes(content)
    time.sleep(3)
    print(f"edited {len(edited)} files at once and reverted them")
    return gate(serial, package)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("command", choices=["logcat", "hot-reload"])
    parser.add_argument("--serial", required=True)
    parser.add_argument("--package", default="dev.pam.nativeui.showcase.debug")
    parser.add_argument("--project", type=Path, default=None)
    parser.add_argument("--files", nargs="*", default=[])
    parser.add_argument("--dev-log", type=Path, default=None, help="pam dev output, to wait for the reload")
    args = parser.parse_args()
    if args.command == "logcat":
        return gate(args.serial, args.package)
    if args.project is None or len(args.files) < 2:
        parser.error("hot-reload needs --project and at least two --files")
    return hot_reload(args.serial, args.package, args.project, args.files, args.dev_log)


if __name__ == "__main__":
    sys.exit(main())
