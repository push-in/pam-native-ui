#!/usr/bin/env python3
"""Scoped singleton/constant chart interactions, not full approval."""
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
spec = importlib.util.spec_from_file_location('chart_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-chart', 'Chart')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))
try:
    audit.prepare()
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}', '-d', 'pam-showcase://audit/p-chart')
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    width, height = audit.screenshot('start')
    for label, name in [('One observation with value 42', 'singleton'), ('Four observations, all with value 42', 'constant')]:
        chart = None
        for attempt in range(24):
            root = audit.dump(name+'-'+str(attempt))
            chart = next((n for n in root.iter('node') if n.attrib.get('content-desc') == label
                and height*.15 < base.node_bounds(n).top and base.node_bounds(n).bottom < height*.85), None)
            if chart is not None:
                break
            audit.assert_foreground('reveal chart')
            audit.shell('input', 'swipe', str(int(width*.98)), str(int(height*.8)), str(int(width*.98)), str(int(height*.55)), '250')
        assert chart is not None, 'Cannot reveal '+name
        audit.screenshot(name+'-before')
        audit.tap(base.node_bounds(chart))
        root = audit.dump(name+'-after')
        assert any(n.attrib.get('text', '').startswith('Point ') and '42' in n.attrib.get('text', '')
            and base.node_bounds(chart).bottom <= base.node_bounds(n).top < base.node_bounds(chart).bottom+180
            for n in root.iter('node')), 'Chart did not report selected value beneath the tapped instance'
        audit.screenshot(name+'-selected')
        report['checks'].append({'series': name, 'tapReportsValue': True, 'manualReviewRequired': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
