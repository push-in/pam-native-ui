#!/usr/bin/env python3
"""Scoped result actions and native interval dialog checks; not full approval."""
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
parser.add_argument('--results-only', action='store_true')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('interval_base', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-result-state', 'Result State')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def launch(tag):
    audit.shell('am', 'force-stop', audit.package)
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', 'pam-showcase://audit/'+tag)
    time.sleep(.5)

def locate(label, name):
    for attempt in range(14):
        audit.assert_foreground(name)
        root = audit.dump(f'{name}-{attempt}')
        for node in root.iter('node'):
            bounds = module.node_bounds(node)
            if node.attrib.get('content-desc') == label and bounds.top > height*.12 and bounds.bottom < height*.88:
                return node
        audit.shell('input', 'swipe', str(width//2), str(int(height*.75)),
                    str(width//2), str(int(height*.5)), '300')
    raise AssertionError('Cannot reveal '+label)

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    launch('p-result-state')
    width, height = audit.screenshot('result-start')
    audit.tap(module.node_bounds(locate('View report', 'enabled-result')))
    root = audit.dump('result-completed')
    assert any(n.attrib.get('text') == 'Primary action completed' for n in root.iter('node'))
    for label, name in [('Continue', 'disabled-result'), ('Preparing your report', 'loading-result')]:
        node = locate(label, name)
        assert node.attrib.get('enabled') == 'false', name+' is enabled'
        audit.tap(module.node_bounds(node))
        root = audit.dump(name+'-after')
        assert any(n.attrib.get('content-desc') == label for n in root.iter('node')), name+' changed action'
        if name == 'loading-result':
            # PAM's indicator is a custom native host, not necessarily an
            # android.widget.ProgressBar in the accessibility hierarchy.
            # Its visual presence requires inspection of the saved capture.
            assert any(n.attrib.get('text') == 'Preparing report' for n in root.iter('node')), 'Loading result lacks its progress message'
        audit.screenshot(name)
    report['checks'].append({'component': 'result-state', 'enabledAction': True, 'disabledAndLoadingRejected': True})
    for label, title, name in [('Review details', 'Review required', 'warning'),
                               ('Try again', 'Something went wrong', 'error'),
                               ('Add item', 'Nothing here yet', 'empty')]:
        node = locate(label, 'result-'+name)
        root = audit.dump('result-'+name+'-copy')
        assert any(n.attrib.get('text') == title for n in root.iter('node')), name+' title missing'
        audit.screenshot('result-'+name)
        audit.tap(module.node_bounds(node))
        root = audit.dump('result-'+name+'-action')
        assert any(n.attrib.get('content-desc') == 'Report opened'
                   and abs(module.node_bounds(n).top-module.node_bounds(node).top) < 8
                   for n in root.iter('node')), name+' action did not update the tapped instance'
        report['checks'].append({'component': 'result-state', 'scenario': name,
                                'titleVisible': True, 'actionUpdatesInstance': True})
    range_tags = [] if args.results_only else ['p-date-range-picker', 'p-time-range-picker']
    for tag in range_tags:
        launch(tag)
        node = locate('From', tag+'-from')
        before = node.attrib.get('content-desc')
        audit.tap(module.node_bounds(node))
        root = audit.dump(tag+'-dialog')
        assert any(n.attrib.get('resource-id') == 'android:id/button1' for n in root.iter('node')), tag+' did not open native dialog'
        audit.screenshot(tag+'-dialog')
        audit.back()
        root = audit.dump(tag+'-cancelled')
        assert any(n.attrib.get('content-desc') == before for n in root.iter('node')), tag+' did not return to field'
        report['checks'].append({'component': tag, 'opensNativeDialog': True, 'returnsAfterCancel': True})
    if range_tags:
        audit.set_setting('system', 'font_scale', '2.0')
    for tag in range_tags:
        launch(tag)
        node = locate('From', tag+'-large-font')
        audit.screenshot(tag+'-large-font')
        audit.tap(module.node_bounds(node))
        root = audit.dump(tag+'-large-font-dialog')
        assert any(n.attrib.get('resource-id') == 'android:id/button1' for n in root.iter('node')), tag+' did not open with large font'
        audit.back()
        report['checks'].append({'component': tag, 'fontScale': 2.0, 'opensNativeDialog': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
