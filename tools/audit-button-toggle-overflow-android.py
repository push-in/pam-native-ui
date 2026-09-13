#!/usr/bin/env python3
"""Grouped-button overflow and selection regressions, not full component approval."""

import argparse
import hashlib
import importlib.util
import json
import signal
import sys
import time
from pathlib import Path

VARIATIONS = (
    ('Single choice', 'Month'), ('Multiple choice', 'Drive'),
    ('Full width', 'Calendar'), ('Single optional', 'Compact'),
    ('Leading icons', 'Saved'), ('Compact density', 'Right'),
    ('Two options', 'Yearly'), ('Five options', 'Fri'),
    ('Disabled item', 'Edit'), ('Disabled group', 'Admin'),
    ('Long labels', 'Assigned to me'), ('Tile', 'Three'),
    ('RTL', 'Day'), ('Success color', 'Review'),
)

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--serial', required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--package', default='dev.pam.mobileui.catalog')
parser.add_argument('--activity', default='dev.pam.nativeapp.PamActivity')
parser.add_argument('--variation', action='append', choices=[label for label, _ in VARIATIONS],
                    help='Run only this fixture label; repeat to select several.')
parser.add_argument('--font-scale', action='append', choices=('1.0', '2.0'),
                    help='Run only this font scale; defaults to both supported scales.')
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
apk_path = audit.shell('pm', 'path', audit.package).strip().splitlines()[0].removeprefix('package:')
provenance = {
    'serial': args.serial,
    'startedAtUnix': time.time(),
    'scriptSha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
    'apkSha256': audit.shell('sha256sum', apk_path).split()[0],
    'requestedVariations': args.variation or [label for label, _ in VARIATIONS],
    'requestedFontScales': args.font_scale or ['1.0', '2.0'],
}
signal.signal(signal.SIGTERM, lambda signum, frame: sys.exit(128 + signum))
try:
    audit.prepare()
    for scale in dict.fromkeys(args.font_scale or ('1.0', '2.0')):
        audit.shell('settings', 'put', 'system', 'font_scale', scale)
        time.sleep(2)
        audit.shell('am', 'force-stop', audit.package)
        audit.shell('am', 'start', '-W', '-n', f'{audit.package}/{audit.activity}', '-d', 'pam-showcase://audit/p-btn-toggle')
        time.sleep(2)
        width, height = audit.screenshot(f'launch-{scale}')
        for variation, target in VARIATIONS:
            if args.variation and variation not in args.variation:
                continue
            root, group = audit.scroll_to_group(variation, width, height, f'font-{scale}')
            area = module.node_bounds(group)
            assert area.left > 0 and area.right < width, (variation, area)
            visible_buttons = audit.buttons(group)
            assert visible_buttons, f'{variation}: no visible buttons'
            footer = area.bottom - max(module.node_bounds(n).bottom for n in visible_buttons)
            assert footer >= 3 * audit.density(), (variation, 'scrollbar overlaps button outline', footer)
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
            before_name = f'{variation.replace(" ", "-")}-{scale}-before'
            audit.screenshot(before_name)
            indicator_pixels = None
            if variation == 'Five options':
                # This fixture overflows at both tested scales. Sample only the
                # reserved strip, excluding button borders/text, and compare
                # against the surrounding canvas rather than a fixed theme RGB.
                strip_top = max(module.node_bounds(n).bottom for n in visible_buttons) + 1
                with module.Image.open(out / f'{before_name}.png').convert('RGB') as capture:
                    canvas = capture.getpixel((area.left - 5, strip_top))
                    indicator_pixels = sum(
                        audit.contrast_ratio(capture.getpixel((x, y)), canvas) >= 3.0
                        for y in range(strip_top, area.bottom - 1)
                        for x in range(area.left + 10, area.right - 10)
                    )
                assert indicator_pixels > area.width, (
                    variation, scale, 'persistent indicator lacks 3:1 contrast', indicator_pixels
                )
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
            before_selected = audit.selected_labels(group)
            audit.shell('input', 'tap', str((bounds.left+bounds.right)//2), str((bounds.top+bounds.bottom)//2))
            time.sleep(.7)
            root = audit.dump(f'{variation.replace(" ", "-")}-{scale}-after')
            group = audit.group_after(root, variation)
            for button in audit.buttons(group):
                assert button.attrib.get('checked') == button.attrib.get('selected'), (
                    variation, 'checked and selected states disagree', button.attrib
                )
            if variation not in ('Disabled item', 'Disabled group'):
                assert target in audit.selected_labels(group), audit.selected_labels(group)
            else:
                assert found.attrib.get('enabled') == 'false'
                assert audit.selected_labels(group) == before_selected
                if variation == 'Disabled group':
                    assert all(n.attrib.get('enabled') == 'false' for n in audit.buttons(group))
                assert target not in audit.selected_labels(group)
            audit.screenshot(f'{variation.replace(" ", "-")}-{scale}-after')
            second_tap = None
            if variation in ('Single choice', 'Single optional', 'Multiple choice'):
                selected = audit.selected_labels(group)
                expected = before_selected | {target} if variation == 'Multiple choice' else {target}
                assert selected == expected, (variation, 'first tap selection', selected, expected)
                current = next(n for n in audit.buttons(group) if n.attrib.get('content-desc') == target)
                current_bounds = module.node_bounds(current)
                audit.shell('input', 'tap', str((current_bounds.left + current_bounds.right)//2),
                            str((current_bounds.top + current_bounds.bottom)//2))
                time.sleep(.7)
                root = audit.dump(f'{variation.replace(" ", "-")}-{scale}-second-tap')
                group = audit.group_after(root, variation)
                expected = ({target} if variation == 'Single choice' else
                            before_selected if variation == 'Multiple choice' else set())
                assert audit.selected_labels(group) == expected, (
                    variation, 'second tap selection', audit.selected_labels(group), expected
                )
                for button in audit.buttons(group):
                    assert button.attrib.get('checked') == button.attrib.get('selected'), button.attrib
                second_tap = sorted(expected)
                audit.screenshot(f'{variation.replace(" ", "-")}-{scale}-second-tap')
            checks.append({'variation': variation, 'fontScale': scale, 'target': target, 'status': 1,
                           'secondTapSelectedLabels': second_tap,
                           'indicatorPixelsAtLeast3To1': indicator_pixels})
            (out/'report.json').write_text(json.dumps({**provenance, 'checks': checks, 'fullApproval': False}, indent=2))
            print(checks[-1], flush=True)
finally:
    audit.shell('settings', 'put', 'system', 'font_scale', original)
    audit.restore()
    (out/'report.json').write_text(json.dumps({**provenance, 'checks': checks, 'fullApproval': False}, indent=2))
