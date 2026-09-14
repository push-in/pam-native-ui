#!/usr/bin/env python3
"""Scoped protected grid selection checks; not full approval."""
import argparse
import importlib.util
import json
from pathlib import Path
import signal
import sys

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--row-layout-only', action='store_true')
parser.add_argument('--font-scale', choices=['1.0', '2.0'], default='1.0')
args = parser.parse_args()
spec = importlib.util.spec_from_file_location('grid_base', Path(__file__).with_name('audit-autocomplete-android.py'))
base = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = base
spec.loader.exec_module(base)
audit = base.AutocompleteAudit(args.serial, 'dev.pam.mobileui.catalog',
    'dev.pam.nativeapp.PamActivity', args.output, 'p-data-grid', 'Data Grid')
report = {'fullApproval': False, 'checks': []}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))

def section(title):
    for attempt in range(24):
        root = audit.dump(title.replace(' ', '-')+str(attempt))
        heading = next((n for n in root.iter('node') if n.attrib.get('text') == title), None)
        if heading is not None:
            top = base.node_bounds(heading).bottom
            controls = [n for n in root.iter('node') if n.attrib.get('content-desc', '').startswith('Select ')
                and top < base.node_bounds(n).top < top+650]
            if len(controls) >= 4 and max(base.node_bounds(n).bottom for n in controls[:4]) < height*.94:
                return controls[:4]
        audit.assert_foreground('reveal grid section')
        audit.shell('input', 'swipe', str(int(width*.98)), str(int(height*.8)),
                    str(int(width*.98)), str(int(height*.55)), '250')
    raise AssertionError('Cannot reveal '+title)

def at_bounds(root, original):
    return next(n for n in root.iter('node') if n.attrib.get('bounds') == original.attrib.get('bounds')
        and n.attrib.get('content-desc') == original.attrib.get('content-desc'))

try:
    audit.prepare()
    audit.set_setting('system', 'font_scale', args.font_scale)
    report['fontScale'] = args.font_scale
    audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}', '-d', 'pam-showcase://audit/p-data-grid')
    apk = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
    report['apkSha256'] = audit.shell('sha256sum', apk).split()[0]
    width, height = audit.screenshot('start')
    if args.row_layout_only:
        for title in ['Loading', 'Empty', 'Comfortable rows']:
            found = False
            for attempt in range(24):
                root = audit.dump('layout-'+title.replace(' ', '-')+str(attempt))
                heading = next((n for n in root.iter('node') if n.attrib.get('text') == title), None)
                if heading is not None and height*.15 < base.node_bounds(heading).top < height*.5:
                    audit.screenshot('layout-'+title.replace(' ', '-'))
                    found = True
                    break
                audit.assert_foreground('layout section')
                audit.shell('input', 'swipe', str(int(width*.98)), str(int(height*.8)),
                            str(int(width*.98)), str(int(height*.6)), '250')
            assert found, 'Cannot reveal '+title
        viewport = next(n for n in root.iter('node')
            if n.attrib.get('class') == 'androidx.recyclerview.widget.RecyclerView'
            and any(child.attrib.get('text') == 'Aurora 1' for child in n.iter('node')))
        bounds = base.node_bounds(viewport)
        reached = False
        for attempt in range(4):
            audit.assert_foreground('scroll taller grid rows')
            audit.shell('input', 'swipe', str(bounds.center[0]), str(bounds.bottom-60),
                        str(bounds.center[0]), str(bounds.top+60), '350')
            root = audit.dump('tall-rows-scroll-'+str(attempt))
            if any(n.attrib.get('text') == 'Studio 6' and bounds.top < base.node_bounds(n).top
                and base.node_bounds(n).bottom < bounds.bottom for n in root.iter('node')):
                reached = True
                break
        assert reached, 'Last tall row is not reachable inside the grid viewport'
        audit.screenshot('tall-rows-final')
        report['checks'].append({'layoutSectionsCaptured': True, 'lastTallRowReachable': True, 'manualReviewRequired': True})
        sys.exit(0)
    controls = section('Protected selection')
    bulk, first, protected, third = controls
    assert protected.attrib.get('enabled') == 'false' and protected.attrib.get('checked') == 'true'
    audit.tap(base.node_bounds(protected))
    root = audit.dump('protected-after')
    assert at_bounds(root, protected).attrib.get('checked') == 'true'
    for expected in ['true', 'false']:
        audit.tap(base.node_bounds(bulk))
        root = audit.dump('bulk-'+expected)
        assert at_bounds(root, first).attrib.get('checked') == expected
        assert at_bounds(root, third).attrib.get('checked') == expected
        assert at_bounds(root, protected).attrib.get('checked') == 'true'
    audit.screenshot('protected-selection')
    report['checks'].append({'bulkSelectAndDeselect': True, 'protectedSelectionPreserved': True})
    for title in ['Read-only selection', 'Disabled selection']:
        controls = section(title)
        assert all(n.attrib.get('enabled') == 'false' for n in controls)
        audit.tap(base.node_bounds(controls[0]))
        root = audit.dump(title.replace(' ', '-')+'-after')
        assert all(at_bounds(root, n).attrib.get('checked') == n.attrib.get('checked') for n in controls)
        audit.screenshot(title.replace(' ', '-'))
        report['checks'].append({'section': title, 'selectionRejected': True})
finally:
    audit.restore()
    (args.output/'report.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report), flush=True)
