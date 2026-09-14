#!/usr/bin/env python3
"""Exercise tree read-only browsing and disabled descendants; scoped evidence only."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('tree_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-tree-select', 'Tree Select')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def labeled(root, label):
    return next((node for node in root.iter('node')
        if node.attrib.get('content-desc') == label), None)

try:
    audit.prepare()
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
        '-d', 'pam-showcase://audit/p-tree-select')
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    width, height = audit.screenshot('start')
    previous = None
    for attempt in range(16):
        root = audit.dump('reveal-'+str(attempt))
        group = labeled(root, 'Access policies')
        leaf = labeled(root, 'Edit documents')
        if group is not None and leaf is not None and base.node_bounds(group).top > height*.06 and base.node_bounds(leaf).bottom < height*.85:
            break
        signature = [(n.attrib.get('content-desc'), n.attrib.get('bounds')) for n in root.iter('node')]
        assert signature != previous, 'Tree samples not reachable; scroll stopped'
        previous = signature
        audit.assert_foreground('reveal tree')
        audit.shell('input', 'swipe', str(int(width*.98)), str(int(height*.8)),
            str(int(width*.98)), str(int(height*.55)), '250')
    else:
        raise AssertionError('Read-only tree not found')
    leaf = labeled(root, 'Read documents')
    assert leaf is not None and leaf.attrib.get('checked') == 'true'
    assert leaf.attrib.get('enabled') == 'false'
    audit.tap(base.node_bounds(leaf))
    root = audit.dump('readonly-tapped')
    assert labeled(root, 'Read documents').attrib.get('checked') == 'true'
    group = labeled(root, 'Access policies')
    audit.tap(base.node_bounds(group))
    root = audit.dump('collapsed')
    assert labeled(root, 'Read documents') is None, 'Read-only group did not collapse'
    audit.tap(base.node_bounds(labeled(root, 'Access policies')))
    root = audit.dump('expanded')
    assert labeled(root, 'Read documents').attrib.get('checked') == 'true'
    report['checks'].append('readOnlyRejectsSelectionAndAllowsCollapseExpand')
    archived = labeled(root, 'Archived document access')
    assert archived is not None, 'Disabled descendant not visible'
    assert archived.attrib.get('enabled') == 'false' and archived.attrib.get('checked') == 'true'
    audit.tap(base.node_bounds(archived))
    root = audit.dump('disabled-tapped')
    assert labeled(root, 'Archived document access').attrib.get('checked') == 'true'
    assert labeled(root, 'Archived policies').attrib.get('enabled') == 'false'
    report['checks'].append('disabledBranchRejectsDescendantSelection')
    audit.screenshot('tree-locks-verified')
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
