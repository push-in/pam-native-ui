#!/usr/bin/env python3
"""Exercise long drawer scrolling and disabled destinations; not full approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', required=True, type=Path)
parser.add_argument('--font-scales', nargs='+', choices=['1.0', '2.0'], default=['1.0', '2.0'])
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('drawer_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-navigation-drawer', 'Navigation Drawer')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def open_long_drawer(name):
    for attempt in range(16):
        root = audit.dump(f'{name}-locate-{attempt}')
        captions = [n for n in root.iter('node') if n.attrib.get('text') == 'Scrollable destinations']
        if captions:
            top = base.node_bounds(captions[0]).bottom
            buttons = [n for n in root.iter('node') if n.attrib.get('content-desc') == 'Open Front navigation drawer'
                       and base.node_bounds(n).top > top and base.node_bounds(n).bottom < height*.94]
            if buttons:
                audit.tap(base.node_bounds(buttons[0]))
                return audit.dump(name+'-opened')
        audit.assert_foreground(name)
        audit.shell('input', 'swipe', str(int(width*.94)), str(int(height*.8)),
                    str(int(width*.94)), str(int(height*.45)), '250')
    raise AssertionError('Long drawer control not reachable')

def destination(root, label):
    return next((n for n in root.iter('node') if n.attrib.get('content-desc') == label
                 and n.attrib.get('class') == 'android.widget.Button'), None)

def reveal(root, label, name):
    for attempt in range(18):
        node = destination(root, label)
        scrolls = [n for n in root.iter('node') if n.attrib.get('scrollable') == 'true'
                   and any('destination' in c.attrib.get('content-desc', '')
                           or c.attrib.get('text') == 'Customer experience workspace'
                           for c in n.iter('node'))]
        assert scrolls, 'Drawer has no scrollable destination viewport'
        viewport = base.node_bounds(min(scrolls, key=lambda n: base.node_bounds(n).width))
        if node:
            bounds = base.node_bounds(node)
            if bounds.top >= viewport.top and bounds.bottom <= viewport.bottom:
                return node
        audit.assert_foreground(name)
        audit.shell('input', 'swipe', str(viewport.center[0]), str(viewport.bottom-24),
                    str(viewport.center[0]), str(viewport.top+24), '250')
        root = audit.dump(f'{name}-scroll-{attempt}')
    raise AssertionError('Cannot reach '+label)

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    for scale in args.font_scales:
        audit.set_setting('system', 'font_scale', scale)
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                    '-d', 'pam-showcase://audit/p-navigation-drawer')
        time.sleep(.5)
        width, height = audit.screenshot(scale+'-start')
        root = open_long_drawer(scale)
        blocked = reveal(root, 'Unavailable destination', scale+'-disabled')
        assert blocked.attrib.get('enabled') == 'false', 'Disabled destination is enabled'
        audit.tap(base.node_bounds(blocked))
        root = audit.dump(scale+'-disabled-tapped')
        assert destination(root, 'Unavailable destination') is not None, 'Disabled tap dismissed drawer'
        audit.screenshot(scale+'-disabled')
        last = reveal(root, 'Workspace destination 16', scale+'-last')
        audit.screenshot(scale+'-last')
        audit.tap(base.node_bounds(last))
        root = audit.dump(scale+'-selected')
        assert destination(root, 'Workspace destination 16') is None, 'Selecting destination did not close drawer'
        report['checks'].append({'fontScale': scale, 'disabledTapRejected': True,
            'lastDestinationReachable': True, 'selectionClosesDrawer': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
