#!/usr/bin/env python3
"""Check search editing and picker cancellation without selecting personal files."""
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
parser.add_argument('--search-only', action='store_true', help='Do not repeat the already validated file-picker flow.')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('search_file_base', Path(__file__).with_name('audit-autocomplete-android.py'))
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
audit = module.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-search-bar', 'Search Bar')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def launch(tag):
    audit.shell('am', 'force-stop', audit.package)
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', f'pam-showcase://audit/{tag}')
    time.sleep(.5)

def tap(node):
    audit.assert_foreground('before action')
    bounds = module.node_bounds(node)
    audit.shell('input', 'tap', str(bounds.center[0]), str(bounds.center[1]))

try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    launch('p-search-bar')
    width, height = audit.screenshot('search-long')
    root = audit.dump('search-before')
    inputs = [n for n in root.iter('node') if n.attrib.get('class') == 'android.widget.EditText']
    target = next(n for n in inputs if n.attrib.get('text', '').startswith('Search invoices'))
    bounds = module.node_bounds(target)
    assert bounds.left > 0 and bounds.right < width and bounds.width > width*.5
    tap(target)
    audit.shell('input', 'text', 'XYZ')
    time.sleep(.7)
    root = audit.dump('search-edited')
    assert any('XYZ' in n.attrib.get('text', '') for n in root.iter('node')), 'Search editing did not update native value'
    audit.shell('input', 'keyevent', 'KEYCODE_BACK')
    report['checks'].append({'component': 'search', 'result': 1})
    if args.search_only:
        sys.exit(0)
    launch('p-file-input')
    root = audit.dump('file-before')
    audit.screenshot('file-long')
    triggers = [n for n in root.iter('node') if n.attrib.get('content-desc') == 'Annual report']
    assert triggers, 'Long filename trigger missing'
    tap(triggers[0])
    time.sleep(.8)
    # Do not capture picker contents: only check the resumed system activity.
    activities = audit.shell('dumpsys', 'activity', 'activities')
    resumed = '\n'.join(line for line in activities.splitlines() if 'mResumedActivity' in line)
    assert 'documentsui' in resumed.lower(), 'Expected Android document picker'
    audit.shell('input', 'keyevent', 'KEYCODE_BACK')
    time.sleep(.6)
    audit.assert_foreground('after picker cancellation')
    root = audit.dump('file-cancelled')
    assert any('annual-financial-report' in n.attrib.get('text', '') for n in root.iter('node')), 'Cancellation changed the file label'
    audit.screenshot('file-cancelled')
    report['checks'].append({'component': 'file-input', 'result': 1, 'pickerCancelled': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
