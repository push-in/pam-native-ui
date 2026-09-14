#!/usr/bin/env python3
"""Exercise native mask/currency editing through UI compositions, not full approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('formatted_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-masked-field', 'Masked Field')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def field(root, label):
    candidates = [n for n in root.iter('node') if n.attrib.get('class') == 'android.widget.EditText'
                  and n.attrib.get('content-desc') == label]
    assert len(candidates) == 1, f'Expected one input labelled {label}, found {len(candidates)}'
    return candidates[0]

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    for tag, cases in [
        ('p-masked-field', [('Mobile phone', '(11) 98765-4321', '21912345678', '(21) 91234-5678'),
                            ('CPF', '123.456.789-01', '98765432100', '987.654.321-00')]),
        ('p-currency-field', [('Investment', '1.284,50', '123456', '1.234,56'),
                              ('Revenue', '9,480.25', '123456', '1,234.56'),
                              ('Budget', '12.000', '9876', '9.876')]),
    ]:
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                    '-d', 'pam-showcase://audit/'+tag)
        time.sleep(.5)
        root = audit.dump(tag+'-baseline')
        audit.screenshot(tag+'-baseline')
        for label, initial, typed, expected in cases:
            name = label.lower().replace(' ', '-')
            node = field(root, label)
            assert node.attrib.get('text') == initial, (label, node.attrib.get('text'), initial)
            audit.tap(base.node_bounds(node))
            audit.shell('input', 'keycombination', '113', '29')
            audit.shell('input', 'keyevent', '67')
            audit.shell('input', 'text', typed)
            root = audit.dump(name+'-edited')
            assert field(root, label).attrib.get('text') == expected, (label, field(root, label).attrib.get('text'), expected)
            if audit.ime_shown():
                audit.back()
            root = audit.dump(name+'-keyboard-dismissed')
            assert field(root, label).attrib.get('text') == expected, label+' lost value after keyboard dismissal'
            audit.screenshot(name+'-result')
            report['checks'].append({'field': label, 'initialFormat': True,
                'editedFormat': True, 'retainedAfterKeyboardDismissal': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
