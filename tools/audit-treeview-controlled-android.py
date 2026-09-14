#!/usr/bin/env python3
"""Exercise controlled Treeview selection and expansion through real Android taps."""
import argparse
import importlib.util
import json
import signal
import sys
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('treeview_audit_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-treeview', 'Treeview')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))


def nodes(root, label):
    return [node for node in root.iter('node')
            if node.get('content-desc') == label and node.get('clickable') == 'true'
            and base.node_bounds(node).height > 0]


def node(root, label):
    matches = nodes(root, label)
    if len(matches) != 1:
        raise AssertionError(f'Expected one interactive {label}, found {len(matches)}')
    return matches[0]


def selected(root, label, expected):
    actual = node(root, label).get('selected') == 'true'
    if actual != expected:
        raise AssertionError(f'{label}: selected={actual}, expected {expected}')


def feedback(root, count):
    if not any(item.get('text') == f'{count} item(s) selected' for item in root.iter('node')):
        raise AssertionError('Controlled PHP selection summary was not updated')


def tap(root, label, name):
    target = node(root, label)
    area = base.node_bounds(target)
    # Folder accessibility bounds include descendants; tap its header, not its center.
    audit.shell('input', 'tap', str((area.left + area.right) // 2), str(area.top + min(24, area.height // 2)))
    result = audit.dump(name)
    audit.screenshot(name)
    return result


try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.launch('controlled')
    current = audit.dump('initial')
    selected(current, 'Alpha', True)
    selected(current, 'Beta', False)
    current = tap(current, 'Beta', 'added-beta')
    selected(current, 'Alpha', True)
    selected(current, 'Beta', True)
    feedback(current, 2)
    report['checks'].append('add-preserves-existing-selection')
    current = tap(current, 'Alpha', 'removed-alpha')
    selected(current, 'Alpha', False)
    selected(current, 'Beta', True)
    feedback(current, 1)
    report['checks'].append('remove-preserves-other-selection')
    current = tap(current, 'Workspace', 'collapsed')
    if nodes(current, 'Alpha') or nodes(current, 'Beta'):
        raise AssertionError('Collapsed children remain interactive after controlled rerender')
    feedback(current, 2)
    current = tap(current, 'Workspace', 'expanded')
    selected(current, 'Alpha', False)
    selected(current, 'Beta', True)
    feedback(current, 1)
    current = tap(current, 'Shared files', 'retained-after-selection')
    selected(current, 'Beta', True)
    selected(current, 'Shared files', True)
    feedback(current, 2)
    report['checks'].append('collapse-expand-retains-selection-and-survives-rerender')
except Exception as error:
    report['error'] = str(error)
    raise
finally:
    audit.restore()
    (args.output / 'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
