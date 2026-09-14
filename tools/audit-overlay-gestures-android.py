#!/usr/bin/env python3
"""Check exclusive tap/long-press opening and overlay release behavior."""
import argparse
import importlib.util
import json
from pathlib import Path
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('overlay_base',
    Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
report = {'fullApproval': False, 'checks': [], 'failures': []}
args.output.mkdir(parents=True, exist_ok=True)


def visible(root, text):
    return any(node.get('text') == text for node in root.iter('node'))


for tag, title, trigger_text, content in (
    ('p-menu', 'Menu', 'Open menu', 'Edit profile'),
    ('p-tooltip', 'Tooltip', 'More information', 'Rendered by a native anchored overlay.'),
):
    audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
        'dev.pam.nativeapp.PamActivity', args.output / tag, tag, title)
    held = None
    try:
        audit.prepare()
        audit.launch('gestures')
        apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
        report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
        root = audit.dump('initial')
        triggers = sorted((base.node_bounds(n) for n in root.iter('node')
            if n.get('text') == trigger_text), key=lambda b: b.top)
        if len(triggers) != 2:
            raise AssertionError('Expected exactly two gesture specimens')
        audit.tap(triggers[0])
        if not visible(audit.dump('tap-open'), content):
            raise AssertionError('Tap-only overlay did not open')
        audit.shell('input', 'tap', '72', '308')
        if visible(audit.dump('tap-closed'), content):
            raise AssertionError('Tap-only overlay did not dismiss outside')
        audit.tap(triggers[1])
        if visible(audit.dump('long-press-short-tap'), content):
            raise AssertionError('Long-press-only overlay incorrectly opened on a short tap')
        bounds = triggers[1]
        held = (str((bounds.left + bounds.right) // 2), str((bounds.top + bounds.bottom) // 2))
        audit.shell('input', 'motionevent', 'DOWN', *held)
        time.sleep(0.8)
        if not visible(audit.dump('long-press-held'), content):
            raise AssertionError('Long press did not expose actual overlay content')
        audit.screenshot('long-press-held')
        audit.shell('input', 'motionevent', 'UP', *held)
        held = None
        if visible(audit.dump('long-press-released'), content) != (tag == 'p-menu'):
            raise AssertionError('Release must retain Menu but dismiss Tooltip')
        if tag == 'p-menu':
            root = audit.dump('menu-action')
            action = next(n for n in root.iter('node') if n.get('text') == content)
            audit.tap(base.node_bounds(action))
            root = audit.dump('menu-selected')
            if not visible(root, 'Profile selected') or visible(root, 'Manage notifications'):
                raise AssertionError('Long-press Menu selection must update feedback and dismiss')
        report['checks'].append(tag)
        print('PASS ' + tag, flush=True)
    except Exception as error:
        report['failures'].append({'component': tag, 'error': str(error)})
        print('FAIL ' + tag + ': ' + str(error), flush=True)
    finally:
        if held is not None:
            audit.shell('input', 'motionevent', 'UP', *held)
        audit.restore()
report['complete'] = True
(args.output / 'report.json').write_text(json.dumps(report, indent=2))
if report['failures']:
    sys.exit(1)
