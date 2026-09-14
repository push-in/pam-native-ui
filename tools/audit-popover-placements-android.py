#!/usr/bin/env python3
"""Exercise internal actions and reopening for the remaining Popover placements."""
import argparse
import importlib.util
import json
from pathlib import Path
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('popover_audit_base',
    Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-popover', 'Popover')
report = {'fullApproval': False, 'complete': False, 'checks': [], 'failures': []}


def labelled(root, label):
    return [node for node in root.iter('node') if node.get('content-desc') == label]


def assert_counter(root, count):
    texts = {node.get('text') for node in root.iter('node')}
    if 'Native overlay' not in texts or f'Acknowledged: {count}' not in texts:
        raise AssertionError(f'Open overlay must retain acknowledgement {count}')


def trigger(root, index):
    triggers = sorted(labelled(root, 'Show popover details'),
        key=lambda node: base.node_bounds(node).top)
    if len(triggers) != 4:
        raise AssertionError('Expected all four placement triggers')
    return triggers[index]


def assert_anchor_clear(root, anchor):
    parents = {child: parent for parent in root.iter() for child in parent}
    titles = [node for node in root.iter('node') if node.get('text') == 'Native overlay']
    if len(titles) != 1 or titles[0] not in parents:
        raise AssertionError('Expected one overlay surface')
    surface = base.node_bounds(parents[titles[0]])
    if (min(surface.right, anchor.right) > max(surface.left, anchor.left)
            and min(surface.bottom, anchor.bottom) > max(surface.top, anchor.top)):
        raise AssertionError('Overlay surface overlaps its trigger')


try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    report['fontScale'] = audit.setting('system', 'font_scale')
    for index, placement in enumerate(('Bottom', 'Top', 'Left', 'Right')):
        if index == 0:
            continue  # Already covered by the integrated component scenario.
        try:
            audit.launch()
            root = audit.dump(placement+'-before')
            anchor = base.node_bounds(trigger(root, index))
            audit.tap(anchor)
            root = audit.dump(placement+'-open')
            assert_counter(root, 0)
            assert_anchor_clear(root, anchor)
            for count in (1, 2):
                actions = labelled(root, 'Acknowledge popover details')
                if len(actions) != 1 or actions[0].get('enabled') != 'true':
                    raise AssertionError('Expected one enabled internal action')
                audit.tap(base.node_bounds(actions[0]))
                root = audit.dump(f'{placement}-action-{count}')
                assert_counter(root, count)
            audit.screenshot(placement+'-acknowledged')
            audit.shell('input', 'tap', '72', '308')
            root = audit.dump(placement+'-closed')
            if any(node.get('text') == 'Native overlay' for node in root.iter('node')):
                raise AssertionError('Outside tap did not dismiss overlay')
            audit.tap(base.node_bounds(trigger(root, index)))
            root = audit.dump(placement+'-reopened')
            assert_counter(root, 2)
            assert_anchor_clear(root, anchor)
            audit.screenshot(placement+'-reopened')
            audit.shell('input', 'tap', '72', '308')
            report['checks'].append(placement)
            print('PASS '+placement, flush=True)
        except Exception as error:
            report['failures'].append({'placement': placement, 'error': str(error)})
            print('FAIL '+placement+': '+str(error), flush=True)
    report['complete'] = True
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
if report['failures']:
    sys.exit(1)
