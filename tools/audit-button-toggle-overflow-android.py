#!/usr/bin/env python3
"""Focused overflow regression; does not approve the whole component."""

import argparse
import importlib.util
import json
import sys
import time
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--package', default='dev.pam.mobileui.catalog')
parser.add_argument('--activity', default='dev.pam.nativeapp.PamActivity')
args = parser.parse_args()
source = Path(__file__).with_name('audit-button-toggle-android.py')
spec = importlib.util.spec_from_file_location('overflow_toggle', source)
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)
out = args.output
audit = module.ButtonToggleAudit(args.serial, args.package, args.activity, out)
original = audit.shell('settings', 'get', 'system', 'font_scale').strip()
checks = []
try:
    audit.prepare()
    for scale in ('1.0', '2.0'):
        audit.shell('settings', 'put', 'system', 'font_scale', scale)
        time.sleep(2)
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}', '-d', 'pam-showcase://audit/p-btn-toggle')
        time.sleep(2)
        width, height = audit.screenshot(f'launch-{scale}')
        for variation, target in [('Five options', 'Fri'), ('Disabled group', 'Admin'), ('RTL', 'Day')]:
            root, group = audit.scroll_to_group(variation, width, height, f'font-{scale}')
            area = module.node_bounds(group)
            assert area.left > 0 and area.right < width, (variation, area)
            if variation == 'RTL':
                visual_order = [n.attrib.get('content-desc') for n in sorted(
                    audit.buttons(group), key=lambda n: module.node_bounds(n).left
                )]
                assert visual_order == [label for label in ('Month', 'Week', 'Day') if label in visual_order], visual_order
            if variation == 'Five options':
                for button in audit.buttons(group):
                    for label in button.iter('node'):
                        if label.attrib.get('text') in ('Mon', 'Tue', 'Wed', 'Thu', 'Fri'):
                            assert module.node_bounds(label).height <= 22 * float(scale) * audit.density(), (
                                'short label wraps', label.attrib
                            )
            audit.screenshot(f'{variation.replace(" ", "-")}-{scale}-before')
            found = None
            for attempt in range(5):
                candidates = [n for n in audit.buttons(group) if n.attrib.get('content-desc') == target]
                if candidates:
                    bounds = module.node_bounds(candidates[0])
                    labels = [n for n in candidates[0].iter('node') if n.attrib.get('text') == target]
                    label_clear = any(
                        module.node_bounds(n).left >= area.left + 4 * audit.density()
                        and module.node_bounds(n).right <= area.right - 4 * audit.density()
                        for n in labels
                    )
                    if bounds.width > 100 and bounds.right <= area.right and label_clear:
                        found = candidates[0]
                        break
                audit.shell('input', 'swipe', str(area.left + int(area.width*.75)), str((area.top + area.bottom)//2), str(area.left + int(area.width*.25)), str((area.top + area.bottom)//2), '450')
                time.sleep(.6)
                root = audit.dump(f'{variation.replace(" ", "-")}-{scale}-swipe-{attempt}')
                group = audit.group_after(root, variation)
            assert found is not None, f'{variation}: {target} unreachable'
            bounds = module.node_bounds(found)
            audit.shell('input', 'tap', str((bounds.left+bounds.right)//2), str((bounds.top+bounds.bottom)//2))
            time.sleep(.7)
            root = audit.dump(f'{variation.replace(" ", "-")}-{scale}-after')
            group = audit.group_after(root, variation)
            if variation != 'Disabled group':
                assert target in audit.selected_labels(group), audit.selected_labels(group)
            else:
                assert all(n.attrib.get('enabled') == 'false' for n in audit.buttons(group))
                assert target not in audit.selected_labels(group)
            audit.screenshot(f'{variation.replace(" ", "-")}-{scale}-after')
            checks.append({'variation': variation, 'fontScale': scale, 'target': target, 'status': 1})
            print(checks[-1], flush=True)
finally:
    audit.shell('settings', 'put', 'system', 'font_scale', original)
    audit.restore()
    (out/'report.json').write_text(json.dumps({'checks': checks, 'fullApproval': False}, indent=2))
