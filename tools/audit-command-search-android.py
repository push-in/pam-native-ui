#!/usr/bin/env python3
"""Scoped command filtering and selection, without executing external commands."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--landscape', action='store_true')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('command_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-command-palette', 'Command Palette')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))
try:
    audit.prepare()
    if args.landscape:
        audit.set_setting('system', 'user_rotation', '1')
    report['landscapeRequested'] = args.landscape
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
        '-d', 'pam-showcase://audit/p-command-palette')
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    root = audit.dump('opened')
    if args.landscape:
        width, height = audit.screenshot('landscape-opened')
        assert width > height, 'Device did not rotate to landscape'
    editor = next(n for n in root.iter('node') if n.attrib.get('class') == 'android.widget.EditText')
    audit.tap(base.node_bounds(editor))
    focused = audit.dump('keyboard-open-before-typing')
    audit.screenshot('keyboard-open-before-typing')
    assert any(n.attrib.get('class') == 'android.widget.EditText' for n in focused.iter('node')), 'Command editor disappeared on keyboard focus before filtering'
    audit.shell('input', 'text', 'Open')
    root = audit.dump('filtered')
    options = [n for n in root.iter('node') if n.attrib.get('class') == 'android.widget.CheckedTextView']
    assert [n.attrib.get('content-desc') for n in options] == ['Open file'], 'Commands did not filter'
    audit.screenshot('filtered')
    audit.tap(base.node_bounds(options[0]))
    root = audit.dump('selected')
    assert not any(n.attrib.get('class') == 'android.widget.CheckedTextView' for n in root.iter('node')), 'Command surface did not dismiss'
    assert any('Open file' in n.attrib.get('text', '') or 'Open file' in n.attrib.get('content-desc', '') for n in root.iter('node')), 'Selected command not reflected in trigger'
    audit.screenshot('selected')
    report['checks'].append('filterAndSelectUpdatesTriggerAndDismisses')
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
