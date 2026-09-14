#!/usr/bin/env python3
"""Scoped progress-button interaction checks, not full component approval."""
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
spec = importlib.util.spec_from_file_location('progress_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-progress-button', 'Progress Button')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def locate(label, name):
    for attempt in range(12):
        audit.assert_foreground(name)
        root = audit.dump(f'{name}-{attempt}')
        for node in root.iter('node'):
            bounds = base.node_bounds(node)
            if node.attrib.get('class') == 'android.widget.Button' and any(
                child.attrib.get('text') == label for child in node.iter('node')
            ) and bounds.top > height*.12 and bounds.bottom < height*.94:
                return node
        audit.shell('input', 'swipe', str(int(width*.9)), str(int(height*.78)),
                    str(int(width*.9)), str(int(height*.48)), '250')
    raise AssertionError('Cannot locate '+label)

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    for scale in ['1.0', '2.0']:
        audit.set_setting('system', 'font_scale', scale)
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                    '-d', 'pam-showcase://audit/p-progress-button')
        time.sleep(.5)
        width, height = audit.screenshot('progress-'+scale+'-start')
        for before, after in [('Upload', 'Uploading 25%'), ('Uploading 25%', 'Uploading 50%'),
                              ('Uploading 50%', 'Uploading 75%'), ('Uploading 75%', 'Complete'),
                              ('Complete', 'Upload')]:
            node = locate(before, 'progress-'+scale+'-'+before.replace(' ', '-'))
            assert node.attrib.get('enabled') == 'true'
            audit.tap(base.node_bounds(node))
            root = audit.dump('progress-'+scale+'-after-'+after.replace(' ', '-'))
            assert any(n.attrib.get('text') == after for n in root.iter('node')), 'Progress did not advance to '+after
        audit.screenshot('progress-'+scale+'-reset')
        node = locate('Preparing', 'progress-'+scale+'-loading')
        assert node.attrib.get('enabled') == 'false', 'Indeterminate action remains enabled'
        audit.tap(base.node_bounds(node))
        root = audit.dump('progress-'+scale+'-loading-after')
        assert any(n.attrib.get('class') == 'android.widget.Button' and n.attrib.get('enabled') == 'false'
            and any(child.attrib.get('text') == 'Preparing' for child in n.iter('node')) for n in root.iter('node'))
        audit.screenshot('progress-'+scale+'-loading')
        report['checks'].append({'fontScale': scale, 'advanceCompleteReset': True, 'indeterminateDisabled': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
