#!/usr/bin/env python3
"""Exercise two controlled refresh cycles in the showcase."""
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
spec = importlib.util.spec_from_file_location('refresh_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-pull-to-refresh', 'Pull to Refresh')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def first(root, attribute, value):
    return next(n for n in root.iter('node') if n.attrib.get(attribute) == value)

try:
    audit.prepare()
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
        '-d', 'pam-showcase://audit/p-pull-to-refresh')
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    root = audit.dump('ready')
    for cycle in range(2):
        today = base.node_bounds(first(root, 'text', 'Today'))
        audit.assert_foreground('pull refresh')
        audit.shell('input', 'swipe', str(today.center[0]), str(today.center[1]),
            str(today.center[0]), str(today.center[1]+300), '600')
        root = audit.dump('refreshing-'+str(cycle))
        complete = first(root, 'content-desc', 'Complete refresh')
        assert complete.attrib.get('enabled') == 'true', 'Pull did not start the controlled refresh'
        audit.screenshot('refreshing-'+str(cycle))
        audit.tap(base.node_bounds(complete))
        root = audit.dump('completed-'+str(cycle))
        complete = first(root, 'content-desc', 'Complete refresh')
        assert complete.attrib.get('enabled') == 'false', 'Completion did not reset refresh'
        first(root, 'text', 'Ready to refresh. Pull down to start another cycle.')
        report['checks'].append({'cycle': cycle+1, 'pullStartsAndCompletionResets': True})
    audit.screenshot('ready-again')
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
