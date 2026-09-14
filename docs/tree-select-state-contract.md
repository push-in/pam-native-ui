# Tree Select state contract

## Treeview disabled-branch parity

Controlled expansion follow-up: `p-treeview` now translates the native expanded
event into `onToggle(array $opened)`, preserving other open paths, removing
collapsed paths and avoiding duplicates. Raw Native subscribers still receive
the original payload; malformed maps do not change the opened set. The action
code is the existing shared integer 1, represented by FileTreeAction::Expanded.
The showcase now maintains both `modelValue` and `opened`, using the same state
flow as Tree Select instead of relying solely on transient host state. PHP
regressions cover expand/collapse, duplicate prevention and raw-handler retention.
Matrix and public-surface validation pass. A combined analysis initially exhausted
the 2 GiB limit; event translation was extracted from the main render method and
level-9 analysis then passed in separate renderer/enum and test/showcase groups
at the same limit, without suppressions or dependency changes.
An end-to-end showcase rerender check is still required for this later change.

Android showcase follow-up: `/tmp/pam-treeview-controlled-labels-20260914/report.json`
passed add/remove selection and collapse/reopen through actual taps on API 36.
Candidate SHA-256:
`0d582ad891c2c5c42984f1d13f3ea6aa1c32a11a2af0e85e0c7d129ef4ca81e3`.
The five saved XML states also show PHP selection summaries 2, 1, 2, 1, 2,
confirming consumer rerenders rather than only optimistic host highlights. Those
checks were subsequently made explicit in the reusable audit; no unchanged
interaction was rerun merely for the new assertions. The final screenshot was
viewed: labels and indentation remain readable and both selected rows visible.
Selection still relies on the themed row highlight in this older Treeview;
publication-quality visual refinement is not approved by this scoped result.

The first attempt `/tmp/pam-treeview-controlled-20260914/report.json` stopped
before tapping: generated items exposed internal paths as accessible labels.
The renderer now supplies the authored title explicitly, verified by PHP and
the device hierarchy. A 10-second corrective build followed the initial
20-second integrated build; each automatically cleaned 96.8 MiB. Renderer/test
and showcase PHPStan pass, as does Python script compilation. New UIKit
multiple-selection/controlled-expansion integration still needs remote coverage.

UIKit follow-up candidate `fca5827` implements folder expansion, leaf selection,
selected traits/colors, chevron state and root-owned Change/Native expansion
events. Touches are scoped to folder headers; accessibility activation uses the
same state transition. Disabled/hidden ancestry rejects activation. Explicitly
empty controlled expanded paths collapse folders; nested independent trees are
excluded from traversal. A new UIKit test covers activation, blocked ancestors,
selection deduplication and controlled collapse. Both iOS jobs passed in Verify
run `34835373543`; current job `103947877428` explicitly reports this new test
passed and ten UIKit tests with zero failures. This is not full iOS approval; real layout
reflow, nested-touch routing and screen-reader traversal remain to be checked.

Multiple selection follow-up: the Treeview renderer now emits the complete
controlled selection array when a native path is toggled, and transmits all
selected paths to the host rather than only the first one. Android and UIKit
hosts keep a set in multiple mode and preserve the original single-mode behavior.
PHP regressions cover add/remove payloads. Android's multiple-state test verifies
both visual selected flags, retention of the other item, removal and controlled
empty reset. Together with the disabled-ancestry regression, two Android tests
passed with zero failures/errors/skips in an eight-second filtered build/test.
Matrix and targeted PHPStan level 9 pass. The additional UIKit multiple-state
test is not in `fca5827` and still needs remote execution; the earlier ten-test
success must not be used as evidence for this follow-up. Showcase touch flows
and controlled expansion across rerenders remain separate acceptance cases.

The older `p-treeview` composition now propagates a disabled item/ancestor into
all generated folders and files, removes their selection callbacks and honors
the `isDisabled` item alias. An explicit enabled child cannot escape a disabled
ancestor. This matches the existing Tree Select disabled-branch policy.

The Android FileTree host also checks the root, target and intermediate host
ancestors before changing expansion/selection, including direct `performClick`
activation. The check traverses ancestors without allocating a collection.
This is UI-specific FileTree policy, not a new engine or CLI responsibility.

Validation: matrix and focused PHPStan level 9 pass. On emulator-5554/API 36,
`fileTreeRejectsActivationThroughDisabledItemsAndAncestors` passed: one test,
zero failures/errors/skips, 0.014s test body, 13s Gradle build/test. It rejects
root, branch and leaf disabled activation without emitting events, then confirms
folder and file changes after re-enabling. JUnit is under
`android/build/outputs/androidTest-results/connected/debug/`.
This host-level interaction test is not a screenshot/visual approval, a manual
screen-reader check, or UIKit behavior evidence. The showcase APK must synchronize
the changed Kotlin host before its next consolidated build.

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
