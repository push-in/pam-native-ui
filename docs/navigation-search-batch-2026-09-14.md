# Navigation and search integrated checkpoint

## Follow-up implementation status

### Intrinsic-width navigation candidate

Current APK `4fa5bebb06e0325d1f7232abc18718db44d4e67b44c15afb087113db0f537d33`
uses native intrinsic destination widths (minimum 64dp), distributes remaining
space with flexGrow, and wraps whole destinations. Removing forced zero width
and the inner 100% width lets native text measurement participate in line
allocation. No font reduction, label abbreviation or new native control is used.

The prior scale-derived minimum-width attempt is retained in
`/tmp/pam-navigation-adaptive-20260914/report.json` with candidate
`2cb7eb0ca4c1f6c38f2f1c43b3973b5d7b46af9067c61ff777a35113d442a910`.
Its interactions passed but its viewed layout failed; that implementation was
replaced rather than approved. The final policy no longer reads window font scale
from PHP to guess label widths.

The current material matrix and targeted PHPStan for both renderer and style
resolver pass. `/tmp/pam-navigation-intrinsic-20260914/report.json` passes
selection and disabled rejection at font scales 1.0 and 2.0. The final build took
10 seconds and the two scenarios about 30 seconds. The extra build followed the
failed visual candidate; unchanged unrelated components were not retested.

Viewed `p-navigation-bar-2.0-selected.png` confirms Activity fits fully in the
four-destination example. Viewed `five-destinations-visible-2.0.png` confirms all
five labels are visible, with Account centered in a second row, inside the surface.
The capture also shows tonal and disabled examples without the earlier word split.
The manual capture restored font scale to 1.0. This closes the observed label
truncation/wrapping defect for these examples, not arbitrary labels, all device
widths, RTL, TalkBack, motion, Samsung or iOS approval. The added second-row
destination was visually checked, not separately selected in this run.

### Navigation label wrapping candidate — still visually incomplete

The UI composition now allows multiline destination labels and top-aligns Bar
content so additional label lines do not shift icons vertically. It reuses Native
Text/layout capabilities and does not disable font scaling. Regression assertions
cover multiline labels, top alignment, full accessible names and existing events.
The material matrix and targeted renderer PHPStan pass.

One Android build completed in 10 seconds and cleaned 96.8 MiB of previous
development artifacts. Candidate SHA-256:
`81694b4b7f9e5e805cd8e4edd43934e5a2c3159f2b32a553809bfb0b49d58b48`.
`/tmp/pam-navigation-wrap-20260914/report.json` passes Bar selection and disabled
rejection at 1.0 and 2.0 (about 30 seconds total scenario time). Rail was not rerun.

Visual inspection of the 2.0 selected screenshot rejects this as a complete
solution: Activity wraps as Activit / y in the four-destination example. The
five-destination labels fall below that screenshot's viewport. Removing ellipsis
alone does not deliver a polished large-font navigation layout. Adaptive
destination distribution remains necessary; this candidate is not approved for
publication. Do not describe the passing interaction report as visual acceptance.

The checkpoint below is historical, not the current outstanding-work list.
Subsequent implementation closed two of its concrete gaps:

- App Scaffold now composes PAM Native's KeyboardAvoidingView when keyboardAware
  is enabled, and its showcase contains a real editable field. See
  `app-scaffold-keyboard.md` for the current candidate and keyboard evidence.
- The integrated Command Palette audit now types a search query, selects the
  filtered command, and verifies dismissal and controlled selection. See
  `search-family-validation.md` for the exact scope and candidate.

Neither follow-up establishes full platform, accessibility or performance
approval. Do not restart the resolved implementation work from the historical
gap list below.

## Historical eight-route checkpoint

### Current navigation interaction follow-up

`/tmp/pam-navigation-batch-20260914/report.json` completes four scenarios:
Navigation Bar and Navigation Rail, each at font scales 1.0 and 2.0. All four
verify controlled destination selection and disabled-destination rejection on
emulator-5554, using unchanged candidate
`ad9367fe9acecb49a648d9639eabaec35f6571795ee2701ee9e6f3958ed7b15a`.
Scenario execution totaled about 63 seconds. System font scale was restored to
1.0 and read back after the run. No rebuild or dependency change was needed.

The dedicated runner now collects scenario failures and continues the batch,
reports completion separately from approval, and exits nonzero if any scenario
fails. Successful scenarios print progress immediately. Its syntax and diff
checks pass; this device run exercises the success path, not injected failures.

The selected-state screenshots at 2.0 were inspected. Compact Rail's icons and
indicator remain aligned in the visible example. Navigation Bar's five-destination
example truncates several labels heavily; the four-destination example also
ellipsizes Activity. These screenshots are not approved publication media.
The UI composition explicitly sets non-rail labels to one line in
`materialNavigationChildren`; adaptive label presentation remains an implementation
task. Do not reduce system font scaling to conceal this limitation. Expanded Rail
is below the selected screenshot viewport and is not visually approved by it.

These interaction results do not establish TalkBack operation, full visual,
motion-performance, RTL, iOS or release approval.

`/tmp/pam-navigation-search-batch-20260914.json` completes eight scoped scenarios
on emulator-5554/API 36, without rebuilding candidate
`8ba90170d03b28ca91f9578d6681906040c4d4d9406b39368dd5546eaacc1c97`.
All eight pass their current assertions; this is not eight component approvals.

| Component | Actual scope in this run |
| --- | --- |
| App Scaffold | Route health and static capture only |
| Navigation Bar / Rail | Generic destination press and resulting visual/hierarchy change |
| Navigation Drawer / Bottom App Bar | Generic enabled target press and resulting change |
| Search Bar | Controlled clear, stable bounds, native text entry |
| Command Palette | Open/close, not keyboard filtering or command execution |
| Tree Select | Generic selection change, not the full tree contract |

Navigation Bar, Rail, Drawer, Bottom App Bar and Scaffold captures were inspected.
Bar/Rail indicators and labels are aligned in the captured standard-size examples.
Rail's expanded example continues below the viewport. Drawer capture is closed:
it cannot prove open-drawer layout or selected destination retention.

Scaffold still displayed decorative rounded preview cards. Its showcase source
now uses the page Background token and removes the border/radius. This source
change follows the no-decorative-card rule and postdates the APK above; device
verification must occur with the next changed-showcase candidate, not by relabeling
this batch as proof. Matrix coverage remains code-only.

Remaining concrete gaps for the next implementation batch:

- Scaffold's Keyboard aware variation contains no editor; it cannot demonstrate
  IME avoidance. Add a real input/interaction demonstration before calling it tested.
- The partial Keyboard aware capture shows a clipped LIVE badge. Determine whether
  this is viewport clipping or incorrect child geometry; do not approve that media.
- Command filtering/selection has dedicated historical portrait/landscape evidence,
  but the integrated branch still only opens/closes. Preserve this distinction.
- Navigation destination identity, disabled rejection, reopening and selected-state
  retention need stronger integrated assertions; pixel changes alone are insufficient.
- Full layouts, large text, TalkBack, motion/performance and iOS remain unapproved.

No release gates were changed and no publication occurred.
