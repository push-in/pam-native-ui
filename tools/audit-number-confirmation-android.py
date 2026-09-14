#!/usr/bin/env python3
"""Focused typed-bound confirmation regression; not full component approval."""
import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--value', default='731')
parser.add_argument('--expected', default='20')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('number_confirmation_base', Path(__file__).with_name('audit-autocomplete-android.py'))
if spec is None or spec.loader is None:
    raise RuntimeError('Missing shared audit helpers')
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-number-input', 'Number Input')
report = {'fullApproval': False, 'typedValue': args.value, 'expectedValue': args.expected, 'checks': []}

def fields(root):
    return [node for node in root.iter('node') if node.get('class') == 'android.widget.EditText']

try:
    audit.prepare()
    audit.launch('interactive')
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    initial = fields(audit.dump('before'))
    assert len(initial) >= 2, 'Two independent numeric editors required'
    sibling_value = initial[1].get('text')
    audit.tap(base.node_bounds(initial[0]))
    audit.shell('input', 'keyevent', 'KEYCODE_MOVE_END')
    audit.shell('input', 'keyevent', '--longpress', 'KEYCODE_DEL')
    audit.shell('input', 'text', args.value)
    time.sleep(.5)
    editing = fields(audit.dump('editing'))
    report['editingValue'] = editing[0].get('text')
    assert report['editingValue'] == args.value, 'Native editor rejected or altered the requested typed value'
    audit.tap(base.node_bounds(editing[1]))
    time.sleep(.5)
    confirmed = fields(audit.dump('confirmed'))
    audit.screenshot('confirmed')
    report['confirmedValue'] = confirmed[0].get('text')
    report['siblingValue'] = confirmed[1].get('text')
    assert confirmed[0].get('focused') != 'true', 'Editor did not lose focus'
    assert confirmed[0].get('text') == args.expected, 'Typed value did not normalize on blur'
    assert confirmed[1].get('text') == sibling_value, 'Confirmation changed sibling value'
    report['checks'].append({'result': 1, 'typedValueOnBlur': True, 'instanceIsolation': True})
finally:
    audit.restore()
    (args.output / 'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
