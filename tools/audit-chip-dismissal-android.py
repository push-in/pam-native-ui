#!/usr/bin/env python3
"""Exercise enabled and disabled chip removal with actual Android taps."""
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
spec = importlib.util.spec_from_file_location('chip_dismissal_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-chip', 'Chip')
report = {'checks': [], 'fullApproval': False}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))


def named(root, label):
    return [n for n in root.iter('node') if n.get('content-desc') == label]


def tap(node):
    audit.assert_foreground('chip dismissal')
    area = base.node_bounds(node)
    audit.shell('input', 'tap', str(area.center[0]), str(area.center[1]))


try:
    audit.prepare()
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    audit.launch()
    width, height = audit.screenshot('initial')
    for attempt in range(5):
        root = audit.dump(f'locate-{attempt}')
        active = named(root, 'Remove Android filter')
        blocked = named(root, 'Remove unavailable filter')
        if active and blocked:
            break
        audit.shell('input', 'swipe', str(width // 2), str(height * 4 // 5),
                    str(width // 2), str(height // 2), '300')
    else:
        raise AssertionError('Both chip removal controls must be visible')
    assert active[0].get('enabled') == 'true'
    assert blocked[0].get('enabled') == 'false', 'Disabled chip exposes enabled removal'
    audit.screenshot('actions')
    tap(blocked[0])
    root = audit.dump('blocked')
    assert named(root, 'Remove unavailable filter'), 'Disabled chip was removed'
    assert named(root, 'Remove Android filter'), 'Disabled tap changed another chip'
    tap(named(root, 'Remove Android filter')[0])
    root = audit.dump('removed')
    assert not named(root, 'Remove Android filter'), 'Active chip did not dismiss'
    assert any(n.get('text') == 'Android filter removed' for n in root.iter('node')), 'Controlled removal feedback missing'
    assert named(root, 'Remove unavailable filter'), 'Active removal affected the disabled chip'
    audit.screenshot('removed')
    report['checks'].append({'result': 1, 'disabledRemovalRejected': True, 'activeRemovalConfirmed': True})
finally:
    audit.restore()
    (args.output / 'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
