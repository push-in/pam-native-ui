#!/usr/bin/env python3
"""Exercise tree item blocking, multiple selection and collapse on Android."""
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
spec = importlib.util.spec_from_file_location('tree_base', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-tree-select', 'Tree Select')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def named(root, label):
    return [n for n in root.iter('node') if n.attrib.get('content-desc') == label]

def tap(root, label):
    audit.assert_foreground(label)
    bounds = module.node_bounds(named(root, label)[0])
    audit.shell('input', 'tap', str(bounds.center[0]), str(bounds.center[1]))
    time.sleep(.4)

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', 'pam-showcase://audit/p-tree-select')
    width, height = audit.screenshot('start')
    for attempt in range(20):
        audit.assert_foreground('locating tree group')
        root = audit.dump(f'locate-{attempt}')
        group, last = named(root, 'Project access'), named(root, 'Review changes')
        if group and last and module.node_bounds(group[0]).top > height*.12 and module.node_bounds(last[0]).bottom < height*.88:
            break
        audit.shell('input', 'swipe', str(width//2), str(int(height*.75)), str(width//2), str(int(height*.5)), '300')
    else:
        raise AssertionError('Tree fixture not found')
    assert named(root, 'Manage billing')[0].attrib.get('enabled') == 'false'
    audit.screenshot('before')
    tap(root, 'Manage billing')
    root = audit.dump('disabled-rejected')
    assert named(root, 'Manage billing')[0].attrib.get('checked') == 'false'
    tap(root, 'Review changes')
    root = audit.dump('selected')
    for label in ('Read project documents', 'Review changes'):
        assert named(root, label)[0].attrib.get('checked') == 'true', label
    audit.screenshot('selected')
    tap(root, 'Project access')
    root = audit.dump('collapsed')
    assert not named(root, 'Review changes'), 'Collapsed children remain exposed'
    tap(root, 'Project access')
    root = audit.dump('expanded')
    assert named(root, 'Review changes')[0].attrib.get('checked') == 'true', 'Expansion lost selection'
    audit.screenshot('expanded')
    report['checks'].append({'result': 1, 'disabledRejected': True, 'multipleSelection': True, 'collapsePreservesSelection': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
