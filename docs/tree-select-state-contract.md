# Tree Select state contract

Tree Select owns visual composition and selection policy in PAM Native UI;
it uses existing PAM Native Pressable, checked and expanded semantics.

- `readonly`, `readOnly` and `isReadOnly` prevent leaf selection changes, while
  group expansion remains available for browsing. Selected values stay visible.
- A disabled branch disables every descendant, including already expanded
  descendants. Item `isDisabled` is accepted when `disabled` is absent; explicit
  `disabled: false` takes precedence for that item, but cannot override a
  disabled ancestor.
- Read-only leaves retain their normal colors; disabled items use the existing
  disabled opacity token. Both omit selection handlers and disable leaf actions.

Regression cases in `tests/material-matrix.php` cover all three read-only aliases,
preserved checked state, actual group-collapse callbacks, and two item-disabled
aliases through three levels. The showcase includes Read-only tree and Disabled
branch examples. These PHP checks do not establish screen-reader approval.

## Samsung interaction evidence — 2026-09-14

`tools/audit-tree-locks-android.py` passed on the SM-G973F with APK
`817341f53fc2365a060fb060cd769809f01a39ffb9411df5d9cfc45f0392006d`.
Report: `/tmp/pam-ui-tree-locks-samsung-final-20260914/report.json`.

Actual taps verified that a selected read-only leaf stays selected, its group
collapses and expands, and an archived disabled descendant rejects selection.
The disabled group and descendant expose disabled native semantics. The final
capture was inspected for readable labels and indentation. Device settings were
restored by the audit's finally block. The first attempt stopped because its
viewport threshold skipped a visible group; it is not passing evidence.

Still pending: screen-reader use, iOS, larger text/landscape coverage, deep-tree
layout and performance. The current disclosure glyph and geometric selection
markers also require the family visual review; this is not final showcase media
or complete component approval.

## Vector indicator refinement

Disclosure now uses the existing 24-unit ChevronDown/ChevronRight vector icons
instead of font characters. Selected multiple-choice leaves use the same
16-unit CheckIcon as table selection; single-choice leaves retain the radio dot.
Indicators cannot shrink, use theme colors and remain decorative for accessibility
because the containing row already exposes checked/expanded semantics. Unselected
leaves do not construct a selection mark. This is UI composition only.

PHP regressions cover expanded/collapsed vector hosts, fixed dimensions and
selected/unselected multiple-choice marks. The material matrix and targeted
PHPStan level 9 pass. Candidate
`8ba90170d03b28ca91f9578d6681906040c4d4d9406b39368dd5546eaacc1c97` passes
`/tmp/pam-tree-vector-20260914/report.json` on emulator-5554/API 36: read-only
selection rejects taps, group collapse/expand preserves selection, and disabled
descendants reject selection. `tree-locks-verified.png` was inspected: chevrons,
checks, radio dots and row labels are aligned, with consistent indentation.
The disabled example is partly below that capture; the report/XML, not that
single screenshot, establishes its interaction check. Large text, deep trees,
landscape, screen-reader traversal and iOS remain open. No publication occurred.
