#!/usr/bin/env python3
"""Check controlled navigation and disabled destinations; not full approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--font-scales', nargs='+', choices=['1.0', '2.0'], default=['1.0', '2.0'])
parser.add_argument('--components', nargs='+', choices=['p-navigation-bar', 'p-navigation-rail'],
                    default=['p-navigation-bar', 'p-navigation-rail'])
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('navigation_items_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-navigation-bar', 'Navigation Bar')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def candidates(root, label):
    return [n for n in root.iter('node') if n.attrib.get('content-desc') == label]

def locate(label, name):
    for attempt in range(18):
        root = audit.dump(f'{name}-{attempt}')
        nodes = [n for n in candidates(root, label)
                 if base.node_bounds(n).top > height*.12 and base.node_bounds(n).bottom < height*.94]
        if nodes:
            return root, nodes[0]
        audit.assert_foreground(name)
        audit.shell('input', 'swipe', str(int(width*.9)), str(int(height*.8)),
                    str(int(width*.9)), str(int(height*.45)), '250')
    raise AssertionError('Cannot locate destination '+label)

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    for scale in args.font_scales:
        audit.set_setting('system', 'font_scale', scale)
        for tag in args.components:
            name = tag+'-'+scale
            audit.shell('am', 'force-stop', audit.package)
            audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                        '-d', 'pam-showcase://audit/'+tag)
            time.sleep(.5)
            width, height = audit.screenshot(name+'-start')
            root, home = locate('Home', name+'-home')
            assert home.attrib.get('selected') == 'false', 'Initial destination unexpectedly selected'
            label = next((n for n in home.iter('node') if n.attrib.get('text') == 'Home'), home)
            audit.tap(base.node_bounds(label))
            root = audit.dump(name+'-selected')
            assert any(n.attrib.get('selected') == 'true' for n in candidates(root, 'Home')), 'Destination selection not retained'
            audit.screenshot(name+'-selected')
            root, blocked = locate('Unavailable', name+'-blocked')
            assert blocked.attrib.get('enabled') == 'false', 'Disabled destination enabled'
            audit.tap(base.node_bounds(blocked))
            root = audit.dump(name+'-blocked-after')
            assert all(n.attrib.get('selected') == 'false' for n in candidates(root, 'Unavailable')), 'Disabled destination selected'
            assert any(n.attrib.get('selected') == 'true' for n in candidates(root, 'Current')), 'Disabled tap changed controlled selection'
            audit.screenshot(name+'-blocked')
            report['checks'].append({'component': tag, 'fontScale': scale,
                'selectDestination': True, 'disabledDestinationRejected': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
