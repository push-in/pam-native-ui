#!/usr/bin/env python3
"""Validate compact/expanded drawer presentation on an emulator, not full approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import re
import signal
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--viewports', nargs='+', choices=['compact', 'expanded'], default=['compact', 'expanded'])
args = parser.parse_args()
if not args.serial.startswith('emulator-'):
    parser.error('Resolution-changing audit is restricted to an emulator')
spec = importlib.util.spec_from_file_location('adaptive_drawer_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-navigation-drawer', 'Navigation Drawer')
report = {'fullApproval': False, 'checks': []}
original_size = audit.shell('wm', 'size')
override = re.search(r'Override size: (\d+x\d+)', original_size)
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def find(root, label):
    return next((n for n in root.iter('node') if n.attrib.get('content-desc') == label
                 and n.attrib.get('class') == 'android.widget.Button'), None)

def reveal_control(name):
    for attempt in range(16):
        root = audit.dump(f'{name}-locate-{attempt}')
        node = find(root, 'Open Adaptive navigation drawer')
        if node:
            bounds = base.node_bounds(node)
            if bounds.top > height*.12 and bounds.bottom < height*.90:
                return root, node
        audit.assert_foreground(name)
        audit.shell('input', 'swipe', str(int(width*.96)), str(int(height*.78)),
                    str(int(width*.96)), str(int(height*.45)), '250')
    raise AssertionError('Adaptive control not reachable')

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    # The showcase's own permanent navigation reserves part of a wide window.
    # Leave enough space for the nested example itself to exceed 840 dp.
    for name, size, scale in [('compact', '1080x2400', '2.0'), ('expanded', '3400x2400', '1.0')]:
        if name not in args.viewports:
            continue
        audit.shell('wm', 'size', size)
        audit.set_setting('system', 'font_scale', scale)
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                    '-d', 'pam-showcase://audit/p-navigation-drawer')
        time.sleep(.5)
        width, height = audit.screenshot(name+'-start')
        root, control = reveal_control(name)
        if name == 'compact':
            assert find(root, 'Dashboard') is None, 'Compact drawer is permanently exposed'
            audit.screenshot(name+'-closed')
            audit.tap(base.node_bounds(control))
            root = audit.dump(name+'-opened')
        dashboard = find(root, 'Dashboard')
        assert dashboard is not None, name+' drawer destinations are missing'
        audit.screenshot(name+'-destinations')
        if name == 'expanded':
            assert base.node_bounds(dashboard).right <= base.node_bounds(control).left, 'Permanent drawer overlaps the content control'
        report['checks'].append({'viewport': name, 'size': size, 'fontScale': scale,
            'presentationVerified': True})
finally:
    audit.shell('wm', 'size', override.group(1) if override else 'reset')
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
