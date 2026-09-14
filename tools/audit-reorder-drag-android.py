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
    report['checks'].append({'result': 1, 'realDragUpdatesOrder': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
