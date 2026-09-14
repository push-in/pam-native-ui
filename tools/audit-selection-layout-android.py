#!/usr/bin/env python3
"""Check long selection labels and readonly aliases; scoped, not full approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', required=True, type=Path)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('selection_layout_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-tag-input', 'Tag Input')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def locate(label, name):
    for attempt in range(16):
        audit.assert_foreground(name)
        root = audit.dump(f'{name}-{attempt}')
        for node in root.iter('node'):
            bounds = base.node_bounds(node)
            if (node.attrib.get('content-desc') == label
                    and node.attrib.get('class') == 'android.widget.Spinner'
                    and bounds.top > height * .12 and bounds.bottom < height * .94):
                return node
        audit.shell('input', 'swipe', str(width//2), str(int(height*.76)),
                    str(width//2), str(int(height*.48)), '250')
    raise AssertionError('Cannot reveal field: '+label)

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    for scale in ['1.0', '2.0']:
        audit.set_setting('system', 'font_scale', scale)
        for tag, label, protected in [
            ('p-tag-input', 'Specialties', 'Protected tags'),
            ('p-multi-select', 'Departments', 'Protected teams'),
        ]:
            name = tag+'-'+scale
            audit.shell('am', 'force-stop', audit.package)
            audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                        '-d', 'pam-showcase://audit/'+tag)
            time.sleep(.5)
            width, height = audit.screenshot(name+'-start')
            field = locate(label, name+'-long')
            outer = base.node_bounds(field)
            for node in field.iter('node'):
                bounds = base.node_bounds(node)
                if node.attrib.get('text'):
                    assert bounds.left >= outer.left and bounds.right <= outer.right, name+' text escapes field'
            audit.screenshot(name+'-long')
            field = locate(protected, name+'-readonly')
            assert field.attrib.get('enabled') == 'false', name+' readonly trigger enabled'
            audit.tap(base.node_bounds(field))
            audit.assert_window_count(1, name+' readonly tap')
            audit.screenshot(name+'-readonly')
            report['checks'].append({'component': tag, 'fontScale': scale,
                'textWithinField': True, 'readonlyTapRejected': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
