#!/usr/bin/env python3
"""Confirm both interval endpoints and reopen them; scoped API 36 evidence."""
import argparse
import importlib.util
import json
import signal
import sys
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--time-only', action='store_true', help='Repeat only time interactions after date confirmation has passed.')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('interval_confirmation_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output)
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def field(root, label):
    matches = [n for n in root.iter('node') if n.get('content-desc') == label
        and n.get('clickable') == 'true' and n.get('enabled') == 'true'
        and base.node_bounds(n).height > 0]
    if not matches:
        raise AssertionError('No interactive endpoint: ' + label)
    return min(matches, key=lambda n: base.node_bounds(n).top)

def text(node):
    return [n.get('text') for n in node.iter('node') if n.get('text')]

def unique(root, key, value):
    matches = [n for n in root.iter('node') if n.get(key) == value
        and n.get('enabled') == 'true' and base.node_bounds(n).height > 0]
    if len(matches) != 1:
        raise AssertionError(f'Expected one {key}={value}, found {len(matches)}')
    return matches[0]

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    for tag, title, edits in [
        ('p-date-range-picker', 'Date Range Picker', [
            ('From', '15', ['2026-09-15', 'Select date']),
            ('To', '20', ['2026-09-15', '2026-09-20'])]),
        ('p-time-range-picker', 'Time Range Picker', [
            ('From', '10', ['10:00', '18:00']),
            ('To', '7', ['10:00', '19:00'])]),
    ]:
        if args.time_only and tag == 'p-date-range-picker':
            continue
        audit.component_tag, audit.component_label = tag, title
        audit.launch()
        current = audit.dump(tag + '-initial')
        for label, choice, expected in edits:
            name = tag + '-' + label.lower()
            audit.tap(base.node_bounds(field(current, label)))
            opened = audit.dump(name + '-dialog')
            choice_attribute = 'content-desc' if tag == 'p-time-range-picker' else 'text'
            audit.tap(base.node_bounds(unique(opened, choice_attribute, choice)))
            chosen = audit.dump(name + '-chosen')
            audit.tap(base.node_bounds(unique(chosen, 'resource-id', 'android:id/button1')))
            current = audit.dump(name + '-confirmed')
            actual = [text(field(current, endpoint)) for endpoint in ('From', 'To')]
            if actual != [[value] for value in expected]:
                raise AssertionError(f'{name}: expected {expected}, got {actual}')
            audit.screenshot(name + '-confirmed')
            audit.tap(base.node_bounds(field(current, label)))
            reopened = audit.dump(name + '-reopened')
            unique(reopened, 'resource-id', 'android:id/button1')
            audit.back()
            current = audit.dump(name + '-retained')
            if [text(field(current, endpoint)) for endpoint in ('From', 'To')] != actual:
                raise AssertionError(name + ': reopening/cancelling lost the confirmed interval')
            report['checks'].append({'component': tag, 'endpoint': label,
                'values': expected, 'confirmedAndRetained': True})
except Exception as error:
    report['error'] = str(error)
    raise
finally:
    audit.restore()
    (args.output / 'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
