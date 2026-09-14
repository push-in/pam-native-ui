#!/usr/bin/env python3
"""Verify a real Android long-press drag updates the controlled showcase order."""
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
parser.add_argument('--protected-only', action='store_true')
parser.add_argument('--mutation-locks-only', action='store_true')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('reorder_base', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-reorderable-list', 'Reorderable List')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))
try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', 'pam-showcase://audit/p-reorderable-list')
    root = audit.dump('before')
    audit.screenshot('before')
    if args.mutation_locks_only:
        width, height = audit.screenshot('mutation-start')
        for first_label, last_label in [('Approved order', 'Retained order'), ('Synchronizing order', 'Pending order')]:
            slug = first_label.replace(' ', '-')
            expected = 'Order: '+first_label+' · '+last_label
            for attempt in range(16):
                root = audit.dump('mutation-reveal-'+slug+'-'+str(attempt))
                nodes = {n.attrib.get('content-desc'): n for n in root.iter('node')}
                first, last = nodes.get('Reorder '+first_label), nodes.get('Reorder '+last_label)
                if first is not None and last is not None and module.node_bounds(first).top > height*.08 and module.node_bounds(last).bottom < height*.85:
                    break
                audit.assert_foreground('reveal mutation-locked list')
                audit.shell('input', 'swipe', str(int(width*.98)), str(int(height*.8)), str(int(width*.98)), str(int(height*.6)), '250')
            else:
                raise AssertionError('Mutation-locked list not visible: '+first_label)
            source, target = module.node_bounds(first), module.node_bounds(last)
            audit.assert_foreground('mutation-locked drag')
            audit.shell('input', 'touchscreen', 'draganddrop', str(source.center[0]), str(source.center[1]), str(target.center[0]), str(target.center[1]), '1000')
            root = audit.dump(slug+'-drag')
            assert any(n.attrib.get('text') == expected for n in root.iter('node')), 'Locked drag changed order'
            for label in ['Move '+first_label+' down', 'Move '+last_label+' up']:
                control = next(n for n in root.iter('node') if n.attrib.get('content-desc') == label)
                assert control.attrib.get('enabled') == 'false', 'Locked move control is enabled'
                audit.tap(module.node_bounds(control))
            root = audit.dump(slug+'-buttons')
            assert any(n.attrib.get('text') == expected for n in root.iter('node')), 'Locked button changed order'
            audit.screenshot(slug+'-retained')
        report['checks'].append({'readonlyAndLoadingDragRejected': True, 'readonlyAndLoadingButtonsRejected': True})
        sys.exit(0)
    if args.protected_only:
        width, height = audit.screenshot('protected-start')
        for attempt in range(16):
            root = audit.dump('protected-reveal-'+str(attempt))
            nodes = {n.attrib.get('content-desc'): n for n in root.iter('node')}
            first = nodes.get('Reorder Planning')
            last = nodes.get('Reorder Verification')
            if first is not None and last is not None and module.node_bounds(first).top > height*.06 and module.node_bounds(last).bottom < height*.9:
                break
            audit.assert_foreground('reveal protected list')
            audit.shell('input', 'swipe', str(int(width*.98)), str(int(height*.8)), str(int(width*.98)), str(int(height*.6)), '250')
        else:
            raise AssertionError('Protected list not visible')
        original = 'Order: Planning · Approved milestone · Implementation · Verification'
        for source_label, target_label, expected in [
            ('Verification', 'Planning', original),
            ('Planning', 'Verification', original),
            ('Verification', 'Implementation', 'Order: Planning · Approved milestone · Verification · Implementation'),
        ]:
            nodes = {n.attrib.get('content-desc'): n for n in root.iter('node')}
            source = module.node_bounds(nodes['Reorder '+source_label])
            target = module.node_bounds(nodes['Reorder '+target_label])
            audit.assert_foreground('protected drag')
            audit.shell('input', 'touchscreen', 'draganddrop', str(source.center[0]), str(source.center[1]), str(target.center[0]), str(target.center[1]), '1000')
            root = audit.dump('drag-'+source_label+'-to-'+target_label)
            assert any(n.attrib.get('text') == expected for n in root.iter('node')), 'Incorrect protected order'
        audit.screenshot('protected-result')
        report['checks'].append({'crossingRejectedBothDirections': True, 'freeSegmentReorders': True})
        sys.exit(0)
    nodes = {}
    for node in root.iter('node'):
        nodes.setdefault(node.attrib.get('content-desc'), node)
    source = module.node_bounds(nodes['Reorder Build'])
    target = module.node_bounds(nodes['Reorder Research'])
    audit.assert_foreground('before real drag')
    audit.shell('input', 'touchscreen', 'draganddrop', str(source.center[0]), str(source.center[1]),
                str(target.center[0]), str(target.center[1]), '1000')
    time.sleep(.7)
    audit.assert_foreground('after real drag')
    root = audit.dump('after')
    audit.screenshot('after')
    expected = 'Order: Build · Research · Prototype · Ship'
    assert any(n.attrib.get('text') == expected for n in root.iter('node')), 'Real drag did not update controlled order'
    for label, expected in [
        ('Move Build down', 'Order: Research · Build · Prototype · Ship'),
        ('Move Build up', 'Order: Build · Research · Prototype · Ship'),
    ]:
        control = next(n for n in root.iter('node') if n.attrib.get('content-desc') == label)
        audit.tap(module.node_bounds(control))
        root = audit.dump(label.replace(' ', '-'))
        assert any(n.attrib.get('text') == expected for n in root.iter('node')), 'Move button failed: '+label
    first_up = next(n for n in root.iter('node') if n.attrib.get('content-desc') == 'Move Build up')
    assert first_up.attrib.get('enabled') == 'false', 'First item must not move up'
    audit.tap(module.node_bounds(first_up))
    root = audit.dump('boundary-result')
    assert any(n.attrib.get('text') == expected for n in root.iter('node')), 'Boundary button changed order'
    audit.screenshot('buttons-result')
    report['checks'].append({'result': 1, 'realDragUpdatesOrder': True,
                             'moveUpAndDown': True, 'boundaryRejected': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
