#!/usr/bin/env python3
"""Verify enabled and disabled swipe actions using physical Android gestures."""
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
spec = importlib.util.spec_from_file_location('swipe_base', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-swipe-actions', 'Swipe Actions')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def locate(label):
    for attempt in range(16):
        audit.assert_foreground('locate '+label)
        root = audit.dump(f'locate-{label.replace(" ", "-")}-{attempt}')
        for node in root.iter('node'):
            bounds = module.node_bounds(node)
            if node.attrib.get('text') == label and bounds.top > height*.12 and bounds.bottom < height*.88:
                return root, bounds
        audit.shell('input', 'swipe', str(width//2), str(int(height*.75)), str(width//2), str(int(height*.5)), '300')
    raise AssertionError('Cannot reveal '+label)

def swipe(bounds):
    audit.assert_foreground('before horizontal swipe')
    audit.shell('input', 'swipe', str(int(width*.25)), str(bounds.center[1]),
                str(int(width*.75)), str(bounds.center[1]), '400')
    time.sleep(.6)

def feedback(root):
    return [n.attrib.get('text') for n in root.iter('node') if 'action completed' in n.attrib.get('text', '')]

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', 'pam-showcase://audit/p-swipe-actions')
    width, height = audit.screenshot('start')
    root, bounds = locate('Quarterly design review')
    swipe(bounds)
    root = audit.dump('enabled-result')
    assert 'Archive action completed' in feedback(root), 'Enabled swipe did not perform action'
    audit.screenshot('enabled-result')
    for label in ['Delete', 'Archive']:
        root, bounds = locate(label)
        audit.tap(bounds)
        root = audit.dump('button-'+label)
        assert label+' action completed' in feedback(root), 'Visible action button failed: '+label
    audit.screenshot('buttons-result')
    root, bounds = locate('Locked item')
    before = feedback(root)
    swipe(bounds)
    root = audit.dump('disabled-result')
    assert feedback(root) == before, 'Disabled swipe emitted an action'
    audit.screenshot('disabled-result')
    report['checks'].append({'result': 1, 'enabledAction': True, 'disabledRejected': True,
                             'visibleArchiveAndDelete': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
