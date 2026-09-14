#!/usr/bin/env python3
"""Verify compound password actions without touching personal apps or settings permanently."""
import argparse
import importlib.util
import json
import signal
import sys
import time
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('password_base', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-password-field', 'Password Field')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def tap(node):
    audit.assert_foreground('password action')
    bounds = module.node_bounds(node)
    audit.shell('input', 'tap', str(bounds.center[0]), str(bounds.center[1]))
    time.sleep(.5)

def named(root, label):
    return [n for n in root.iter('node') if n.attrib.get('content-desc') == label]

def editable_input(root):
    inputs = [n for n in root.iter('node') if n.attrib.get('class') == 'android.widget.EditText']
    return min(inputs, key=lambda n: abs(module.node_bounds(n).center[1] - clear_bounds.center[1]))

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', 'pam-showcase://audit/p-password-field')
    width, height = audit.screenshot('start')
    for attempt in range(16):
        audit.assert_foreground('locating password fixtures')
        root = audit.dump(f'locate-{attempt}')
        clear = named(root, 'Clear Editable secret')
        protected = [n for n in root.iter('node') if n.attrib.get('text') == 'Protected secret']
        if clear and protected and module.node_bounds(clear[0]).top > height*.12 and module.node_bounds(protected[-1]).bottom < height*.88:
            break
        audit.shell('input', 'swipe', str(width//2), str(int(height*.75)),
                    str(width//2), str(int(height*.5)), '300')
    else:
        raise AssertionError('Cannot reveal password fixtures')
    assert not named(root, 'Clear Protected secret'), 'Read-only clear action exposed'
    audit.screenshot('combined-actions')
    clear_bounds = module.node_bounds(clear[0])
    reveals = named(root, 'Show password')
    reveal = min(reveals, key=lambda n: abs(module.node_bounds(n).center[1] - clear_bounds.center[1]))
    assert clear_bounds.right < module.node_bounds(reveal).left, 'Clear/reveal actions overlap'
    tap(reveal)
    root = audit.dump('revealed')
    editable = editable_input(root)
    assert editable.attrib.get('password') == 'false', 'Reveal did not unmask native input'
    audit.screenshot('revealed')
    tap(named(root, 'Clear Editable secret')[0])
    root = audit.dump('cleared')
    assert not named(root, 'Clear Editable secret'), 'Clear action did not update controlled value'
    editable = editable_input(root)
    assert editable.attrib.get('text', '') == '', 'Password value did not clear'
    assert not named(root, 'Clear Protected secret'), 'Read-only clear appeared after neighboring update'
    audit.screenshot('cleared')
    report['checks'].append({'result': 1, 'reveal': True, 'clear': True, 'readOnlyClearAbsent': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
