#!/usr/bin/env python3
"""Verify controlled Treeview collapse geometry and re-expansion on one device."""
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
spec = importlib.util.spec_from_file_location('tree_layout_base', Path(__file__).with_name('audit-autocomplete-android.py'))
if spec is None or spec.loader is None:
    raise RuntimeError('Missing shared Android audit helpers')
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-treeview', 'Treeview')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def named(root, label):
    return [node for node in root.iter('node') if node.get('content-desc') == label]

def trees(root):
    result = named(root, 'Treeview preview')
    assert len(result) >= 2, 'Two independent tree specimens are required'
    return result

def toggle(tree):
    folder = named(tree, 'Applications')[0]
    audit.tap(base.node_bounds(folder))
    time.sleep(.6)

try:
    audit.prepare()
    audit.launch()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    before = audit.dump('expanded-before')
    first, second = trees(before)[:2]
    original_height = base.node_bounds(first).height
    assert named(first, 'iOS'), 'Default folder must initially expose its children'
    sibling_selected = named(second, 'Android')[0].get('selected')
    audit.screenshot('expanded-before')
    toggle(first)
    collapsed = audit.dump('collapsed')
    first, second = trees(collapsed)[:2]
    assert not named(first, 'iOS'), 'Collapsed descendants remain exposed'
    collapsed_height = base.node_bounds(first).height
    assert original_height - collapsed_height >= 3 * 48 * audit.density(), 'Collapse retains descendant layout space'
    assert named(second, 'Android')[0].get('selected') == sibling_selected, 'Collapse changed the sibling tree'
    audit.screenshot('collapsed')
    toggle(first)
    expanded = audit.dump('expanded-again')
    first, second = trees(expanded)[:2]
    assert named(first, 'iOS'), 'Expanding does not restore descendants'
    restored_height = base.node_bounds(first).height
    assert abs(restored_height - original_height) <= 2, 'Expansion fails to restore the original extent'
    assert named(second, 'Android')[0].get('selected') == sibling_selected, 'Expansion changed the sibling tree'
    audit.screenshot('expanded-again')
    report['checks'].append({'result': 1, 'collapseReleasesSpace': True,
        'reexpandRestoresChildren': True, 'instanceIsolation': True,
        'expandedHeightPx': original_height, 'collapsedHeightPx': collapsed_height,
        'restoredHeightPx': restored_height})
finally:
    audit.restore()
    (args.output / 'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
