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
parser.add_argument('--reverse-only', action='store_true')
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
    if args.reverse_only:
        before = feedback(root)
        # The public action threshold is 48dp. Ten physical pixels is below it
        # on every supported Android density and must not complete an action.
        audit.assert_foreground('before short drag')
        audit.shell('input', 'swipe', str(width//2), str(bounds.center[1]),
            str(width//2-10), str(bounds.center[1]), '400')
        root = audit.dump('short-drag')
        assert feedback(root) == before, 'Short drag completed an action'
        audit.assert_foreground('before reverse swipe')
        audit.shell('input', 'swipe', str(int(width*.75)), str(bounds.center[1]),
            str(int(width*.25)), str(bounds.center[1]), '400')
        root = audit.dump('reverse-result')
        assert 'Delete action completed' in feedback(root), 'Reverse swipe did not perform Delete'
        audit.screenshot('reverse-result')
        report['checks'].append({'reverseDelete': True, 'shortDragRejected': True})
        sys.exit(0)
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
    for label in ['Locked item', 'Read only item', 'Synchronizing item']:
        root, bounds = locate(label)
        before = feedback(root)
        swipe(bounds)
        root = audit.dump(label.replace(' ', '-')+'-result')
        assert feedback(root) == before, label+' emitted an action'
        # Locate the specimen by its content, not another row's duplicate button.
        parent = {child: node for node in root.iter('node') for child in node}
        specimen = next(n for n in root.iter('node') if n.attrib.get('text') == label)
        while specimen in parent and not any(n.attrib.get('content-desc') == 'Archive' for n in specimen.iter('node')):
            specimen = parent[specimen]
        controls = [n for n in specimen.iter('node') if n.attrib.get('content-desc') in ['Archive', 'Delete']]
        assert len(controls) == 2, 'Missing protected action buttons for '+label
        for control in controls:
            assert control.attrib.get('enabled') == 'false', label+' exposes enabled action'
            audit.tap(module.node_bounds(control))
        assert feedback(audit.dump(label.replace(' ', '-')+'-buttons')) == before, label+' button emitted an action'
        audit.screenshot(label.replace(' ', '-')+'-result')
    report['checks'].append({'result': 1, 'enabledAction': True, 'disabledRejected': True,
                             'visibleArchiveAndDelete': True, 'readonlyAndLoadingRejected': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
