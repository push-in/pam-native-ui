#!/usr/bin/env python3
"""Scoped physical interactions for pagination and individually disabled filters."""
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
spec = importlib.util.spec_from_file_location('batch_audit', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-pagination', 'Pagination')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def launch(tag):
    audit.shell('am', 'force-stop', audit.package)
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', f'pam-showcase://audit/{tag}')
    time.sleep(.7)

def find(label, name, enabled=None):
    for attempt in range(18):
        audit.assert_foreground(name)
        root = audit.dump(f'{name}-{attempt}')
        for node in root.iter('node'):
            if node.attrib.get('content-desc') != label:
                continue
            if enabled is not None and node.attrib.get('enabled') != str(enabled).lower():
                continue
            bounds = module.node_bounds(node)
            if bounds.top > height * .12 and bounds.bottom < height * .88:
                return node
        audit.shell('input', 'swipe', str(width//2), str(int(height*.75)),
                    str(width//2), str(int(height*.5)), '300')
    raise AssertionError(f'Cannot reveal {label}')

def tap(node):
    audit.assert_foreground('before tap')
    bounds = module.node_bounds(node)
    audit.shell('input', 'tap', str(bounds.center[0]), str(bounds.center[1]))
    time.sleep(.4)

def state(label, field, expected, name):
    root = audit.dump(name)
    nodes = [node for node in root.iter('node') if node.attrib.get('content-desc') == label]
    assert nodes and all(node.attrib.get(field) == expected for node in nodes), (label, field, [n.attrib for n in nodes])

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    launch('p-pagination')
    width, height = audit.screenshot('pagination-start')
    page = find('Page 2 of 5', 'find-page', True)
    tap(page)
    root = audit.dump('page-selected')
    selected = [n for n in root.iter('node') if n.attrib.get('content-desc') == 'Page 2 of 5'
                and n.attrib.get('selected') == 'true']
    assert selected, 'Page tap did not select page 2'
    audit.screenshot('pagination-selected')
    disabled = find('Page 1 of 3', 'find-disabled-page', False)
    tap(disabled)
    state('Page 2 of 3', 'selected', 'true', 'disabled-page-retained')
    audit.screenshot('pagination-disabled')
    report['checks'].append({'component': 'pagination', 'result': 1})
    launch('p-filter-bar')
    find('Unavailable', 'find-disabled-filter', False)
    audit.screenshot('filters-before')
    tap(find('Unavailable', 'disabled-filter', False))
    state('Unavailable', 'checked', 'false', 'filter-rejected')
    tap(find('Featured', 'enabled-filter', True))
    state('Featured', 'checked', 'true', 'filter-added')
    state('Available', 'checked', 'true', 'filter-retained')
    audit.screenshot('filters-selected')
    root = audit.dump('find-clear')
    clears = [n for n in root.iter('node') if n.attrib.get('content-desc') == 'Clear filters']
    assert clears, 'Clear filters action missing'
    tap(clears[-1])
    state('Featured', 'checked', 'false', 'filter-cleared')
    state('Available', 'checked', 'false', 'initial-filter-cleared')
    audit.screenshot('filters-cleared')
    report['checks'].append({'component': 'filter-bar', 'result': 1})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
