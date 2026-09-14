#!/usr/bin/env python3
"""Check composed validation copy and actual editing on the same Android screen."""
import argparse
import importlib.util
import json
import signal
import sys
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('validation_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-text-field', 'Text Field')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))


def field(root, label):
    matches = [n for n in root.iter('node')
               if n.get('class') == 'android.widget.EditText' and n.get('content-desc') == label]
    if len(matches) != 1:
        raise AssertionError(f'Expected one native editor named {label}, got {len(matches)}')
    return matches[0]


def copy_checks(root):
    texts = [n.get('text', '') for n in root.iter('node')]
    assert 'Account *' in texts, 'Required alias has no visible indicator'
    assert 'Optional *' not in texts, 'Explicit required=false was ignored'
    assert 'Check your account name' in texts, 'Invalid alias lost helper text'
    assert any('Use at least six characters' in text and 'Include a number' in text for text in texts), 'Error array is not displayed'


try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}',
                '-d', 'pam-showcase://audit/p-text-field?scenario=validation')
    root = audit.dump('initial')
    copy_checks(root)
    audit.screenshot('initial')
    account = field(root, 'Account')
    area = base.node_bounds(account)
    audit.assert_foreground('editing invalid account field')
    audit.shell('input', 'tap', str(area.center[0]), str(area.center[1]))
    audit.shell('input', 'keyevent', 'KEYCODE_MOVE_END')
    audit.shell('input', 'text', '42')
    root = audit.dump('edited')
    assert field(root, 'Account').get('text') == 'Ada42', 'Invalid state blocked or lost native editing'
    audit.screenshot('edited')
    audit.shell('input', 'keyevent', 'KEYCODE_BACK')
    root = audit.dump('retained')
    copy_checks(root)
    assert field(root, 'Account').get('text') == 'Ada42', 'Editing was lost after keyboard dismissal'
    audit.screenshot('retained')
    report['checks'].append({'result': 1, 'requiredAlias': True, 'errorArray': True,
                             'editableInvalidField': True, 'retainedAfterKeyboardDismissal': True})
finally:
    audit.restore()
    (args.output / 'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
