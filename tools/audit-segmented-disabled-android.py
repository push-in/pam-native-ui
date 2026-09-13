#!/usr/bin/env python3
"""Verify individual disabled items in the generated segmented control."""

import argparse
import hashlib
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
source = Path(__file__).with_name('audit-autocomplete-android.py')
spec = importlib.util.spec_from_file_location('segmented_audit_base', source)
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-segmented-button', 'Segmented Button')
apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
report = {
    'serial': args.serial,
    'apkSha256': audit.shell('sha256sum', apk).split()[0],
    'scriptSha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
    'startedAtUnix': time.time(),
    'checks': [],
    'fullApproval': False,
}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))


def controls(root):
    return {node.attrib.get('content-desc'): node for node in root.iter('node')
            if node.attrib.get('class') == 'android.widget.ToggleButton'
            and node.attrib.get('content-desc') in ('View', 'Edit', 'Share')}


def assert_selection(items, expected):
    assert set(items) == {'View', 'Edit', 'Share'}, items.keys()
    for label, node in items.items():
        assert node.attrib.get('enabled') == ('false' if label == 'Edit' else 'true'), node.attrib
        assert node.attrib.get('checked') == ('true' if label == expected else 'false'), node.attrib
        assert node.attrib.get('selected') == node.attrib.get('checked'), node.attrib


try:
    audit.prepare()
    for scale in ('1.0', '2.0'):
        audit.shell('settings', 'put', 'system', 'font_scale', scale)
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                    '-d', 'pam-showcase://audit/p-segmented-button')
        time.sleep(1)
        width, height = audit.screenshot(f'launch-{scale}')
        for attempt in range(16):
            root = audit.dump(f'locate-{scale}-{attempt}')
            items = controls(root)
            if len(items) == 3 and all(
                module.node_bounds(node).top > height * .1
                and module.node_bounds(node).bottom < height * .9 for node in items.values()
            ):
                break
            audit.assert_foreground('locating disabled segmented item')
            audit.shell('input', 'swipe', str(width//2), str(int(height*.75)),
                        str(width//2), str(int(height*.5)), '350')
        else:
            raise AssertionError('Disabled-item fixture could not be fully revealed')
        assert_selection(items, 'View')
        audit.screenshot(f'before-{scale}')
        for label, expected in (('Edit', 'View'), ('Share', 'Share')):
            audit.assert_foreground(f'before tapping {label}')
            bounds = module.node_bounds(items[label])
            audit.shell('input', 'tap', str(bounds.center[0]), str(bounds.center[1]))
            time.sleep(.5)
            items = controls(audit.dump(f'after-{label}-{scale}'))
            assert_selection(items, expected)
            audit.screenshot(f'after-{label}-{scale}')
        report['checks'].append({'fontScale': scale, 'status': 1,
                                 'disabledRejected': True, 'enabledNeighborSelected': True})
        print(report['checks'][-1], flush=True)
        (args.output/'report.json').write_text(json.dumps(report, indent=2))
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
