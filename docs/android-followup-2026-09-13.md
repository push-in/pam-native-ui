# Android follow-up — 2026-09-13

## Build and scope

- UI source: `55c6fcb`; Native source: `a737904` (1.0.27).
- Optimized x86_64 build installed on API 36 emulator, 1080×2400, density 420.
- Installed APK SHA-256: `d19050ad5334e85c6a8b6d4251e7d0271da4adeae4488d8f44eb6fed5be1f4a2`.
- This is one-pass showcase coverage, not the two-pass physical-device gate,
  a performance benchmark, or approval of every component variant.

## Completed checks

Currency Field, Masked Field and Text Field passed actual text-entry checks.
The rounded focus outline was also inspected in the captured Currency Field
and Text Field screens.

The remaining candidate-component round completed with no failed checks:

| Verification | Components |
| --- | --- |
| Open and close | Date Range Picker, Time Range Picker, Popover |
| Selection | Segmented Button, Filter Bar, Data Grid, Tree Select, Chart |
| Scroll | Virtual List, Section List |
| Gesture | Reorderable List, Swipe Actions, Pull to Refresh |
| Press | Progress Button |
| Static composition | Responsive Grid, Result State |

The Reorderable List after-state was visually inspected: updated item order,
aligned handles and labels, and no decorative card nesting.

Local raw reports and captures are `/tmp/pam-ui-fields-fixed-20260913.json`
and `/tmp/pam-ui-remaining-16-20260913.json`, with matching directories without
the `.json` suffix. These temporary paths are not published documentation assets.

A local diagnostic archive preserves both reports, all captures and the exact
application lockfile at
`docs/assets/android/audit/2026-09-13/emulator-followup.tar.gz` (ignored by Git).
Archive SHA-256:
`ba6174a08b501440b1911ad825bdf0b1db0f1a06a541d683b9e149e217a54c02`.
This is a local reproducibility artifact, not a publicly accessible download.

## Not approved yet

- Currency prefix alignment across font scales and platforms. Its current
  absolute positioning is not proof of a shared text baseline. Native's
  `baseline_from_top` estimates font metrics; investigate native measurement
  and reusable field layout before adding another fixed visual offset.
- The emulator keyboard displayed its floating toolbar/onboarding over some
  field captures. Those captures are diagnostic only, not gallery material.
- Samsung validation of the latest field changes, complete variant coverage,
  large text, landscape, accessibility and measured performance remain pending.
- These results do not replace existing release gates or approve all 114
  components as a finished premium library.

## Subsequent in-flow affix correction

Text Field, Masked Field and Currency Field now compose single-line adornments
and the editor in a shared, stretched native row. Prefix/suffix widths are
measured; the editor receives remaining space, and clear-action space stays
reserved separately. Multiline and specialized controls are unchanged.
This uses existing Native primitives; no new native protocol property was
needed for this UI composition change.

Validation after this correction:

- Material matrix, including long prefix/suffix structural regressions: passed.
- PHPStan level 9, theme runtime and recipe matrix: passed.
- Optimized Android build and installation: passed.
- Currency Field actual input: `73125` retained and formatted as `731,25`.
- Currency Field screenshot inspected at font scales 1.0 and 1.3: affix/value
  alignment improved, with no overlap in the displayed BRL, USD, integer and
  error examples. The original emulator font scale was restored to 1.0.

Raw evidence is in `/tmp/pam-ui-flow-affixes-20260913.json` and the matching
directory. The first `font-scale-130.png` captured the launcher and is not
validation evidence; `currency-font-scale-130.png` is the inspected app screen.
Larger scales, long affixes on-device, landscape and iOS/Samsung remain open.

## Font-scale 2.0 follow-up

The first 200% capture reproduced clipped editor values: field surfaces had a
fixed height despite scaled labels and text. Text Field, Password Field, Masked
Field, Currency Field, Color Input and Date Input now keep the density-defined
minimum height without imposing a fixed surface height.

The material matrix (including all six height contracts), PHPStan level 9,
theme runtime and recipe matrix passed. An optimized Android build installed
successfully. In `currency-intrinsic-font-200.png`, the displayed BRL, USD and
integer fields grow and retain their full values inside the outline. The error
field is below the initial viewport and is not approved by that capture.
Font scale was restored to 1.0 afterward.

The showcase heading still displays only “Currency” instead of “Currency Field”
at 200%. Its wrapping/measurement and the fixed-height tag caption require a
separate correction. This screenshot does not approve the whole screen or all
six field families at 200%.

## Native measurement integration check

The build combining Native `ef981e6` and UI `0299e83` installed successfully.
`native-width-final-font-200.png` confirms the route tag caption expands and
the visible currency values remain inside their surfaces. It does **not**
confirm the title fix: only “Currency” is visible. The UI hierarchy still
contains the complete `Currency Field` text at bounds `[84,246][880,404]`.
The title therefore still receives a single-line-height frame at this scale.
Further text measurement investigation is required; do not approve the heading
based on the passing parent-row regression alone. Font scale was restored.

## Confirmed title wrapping correction

Native `451e296` additionally makes line breaking use the same platform width
correction as intrinsic text width. A regression at the observed 303-point
width failed before this change (one line despite a wider measured title) and
passed afterward. All 73 engine tests and Clippy with warnings denied passed.

The optimized Android build with this revision installed successfully.
`native-wrap-consistent-font-200.png` was inspected: **Currency Field** is now
fully visible on two lines, the route caption grows, and the visible monetary
values remain inside their fields. This resolves the specific title clipping
reproduced above on the API 36 emulator. It does not establish all-component,
all-scale or physical Samsung approval. Font scale was restored to 1.0.

## Integrated field interaction follow-up

The Native `451e296` build passed the six-family showcase round: text, mask,
currency, password and color entry, plus opening/closing the date input.
Report: `/tmp/pam-ui-integrated-fields-20260913.json`.

Password coverage was then strengthened beyond masked-character changes. The
audit now taps **Show password**, verifies the exact typed value and exposed
password semantics, taps **Hide password**, and verifies secure semantics are
restored. This flow passed on the emulator; its revealed/hidden XML snapshots
and report are under `/tmp/pam-ui-password-toggle-20260913` and the matching
`.json` file. The six device-lock audit unit tests also passed.

## Detailed autocomplete audit

`/tmp/pam-ui-autocomplete-integrated-20260913/report.json` completed with all
14 checks passing: one sheet, touch targets, filtering/selection, backdrop
preservation, last-option edge hit area, bottom safe area, instance isolation,
disabled/read-only, empty result separation, multiple selection, keyboard Back,
rotation, responsive drawer and runtime log.

Visual inspection of `06-isolated-open.png` nevertheless found the field label
touching its top outline. A subsequent UI change gives selection fields top
and bottom padding plus intrinsic height, retaining the existing minimum
height. Its matrix passes; its Android build/visual validation is pending.
The completed autocomplete report predates that spacing change.

The spacing build subsequently installed successfully. The inspected
`/tmp/pam-ui-selector-padding-20260913/pass-01-p-autocomplete-before.png` shows
the label inset from the outline. Matrix, theme, recipe and PHPStan checks pass.
The five-family round logged successful open/close checks for Autocomplete,
Combobox, Select and Tag Input, then exited with signal-derived code 143 before
writing its final report. Do not treat that run as a complete report.
Multi Select was resumed separately and passed; its complete report is
`/tmp/pam-ui-multi-select-padding-20260913.json`. Full detailed selection and
large-text coverage after the spacing change still remain pending.

### Post-padding autocomplete regression

The installed spacing build completed the detailed autocomplete audit again:
all 14 checks passed, with a complete report at
`/tmp/pam-ui-autocomplete-post-padding-20260913/report.json`.
The `06-isolated-open.png` capture was visually inspected: field labels are
inset, selection remains independent between instances, and the open sheet's
search field and options have visible horizontal gutters. This replaces the
pending detailed autocomplete check above, not the remaining large-text,
other-component, physical-device or performance gates. The audit disables
system animations and therefore does not establish animation smoothness.

### Selection fields at 200% system text

Manual inspection at font scale 2.0 confirmed the visible Autocomplete, Select,
Combobox and Multi Select labels and values remain inside their outlines.
Autocomplete was opened and its last option, Research, tapped; the resulting
hierarchy contains Research in the field. Captures and XML are in the same
post-padding evidence directory (`scaled-200*`, `multi-select-200.png`,
`p-select-200.png`, `p-combobox-200.png`, `p-tag-input-200.png`).

**New failure:** Tag Input's selected PHP chip renders only PH at this scale.
Do not approve its large-text variant. The chip uses intrinsic text sizing,
not a fixed width. Native fallback glyph measurement and chip child sizing
need investigation; no root cause or fix is yet verified. System font scale
was restored to 1.0 after the checks.

The emulator's `/system/fonts/Roboto-Regular.ttf` was measured with the existing
Rust TTF parser (no dependency installation). At weight 600 and 24sp, PHP
requires 47.789063 dp; the Native fallback estimated only 46.8032 dp. Native
commit `d6a6a87` replaces the coarse uppercase advance class with differentiated
glyph estimates while retaining actual metrics for packaged fonts. Its
regression covers PHP at 100%, 130% and 200%; all 74 engine tests and Clippy
passed. An optimized Android build is running for visual confirmation; this
is not yet proof that the observed chip clipping has been resolved on screen.

The `d6a6a87` build subsequently completed and installed. Visual inspection of
`tag-uppercase-fixed-200.png` confirms PHP is fully visible at 200%, alongside
Kotlin and Android. The matching XML is retained in the evidence directory.
This resolves the reproduced PHP clipping case on the API 36 emulator, not
all strings, font families, devices or Tag Input interaction gates. Font scale
was restored to 1.0; automatic build cleanup removed 904.9 MiB.

## Integrated Select and Combobox follow-up

On the installed Native `d6a6a87` build, Combobox completed all 15 detailed
checks and Select all 14. Reports are respectively under
`/tmp/pam-ui-combobox-native-fixed-20260913` and
`/tmp/pam-ui-select-native-fixed-20260913`. Combobox covers creation of Strategy,
replacement with an existing option and instance isolation. Select verifies
that neither search nor the software keyboard appears. Both cover disabled
and read-only behavior, Back, rotation and runtime logs. These are emulator
functional reports, not physical or performance approval.

Combobox's `02-custom-value.png` was inspected: the search and custom action
are separated. The floating IME toolbar makes it diagnostic-only, unsuitable
for public showcase documentation.

The release startup audit was still discovering only 84 screenshot-backed
routes. It now uses the catalog, checking its count against parity metadata,
so all 114 current routes participate even without screenshots. Two unit
tests pass, including rejection of incomplete discovery before ADB mutations.
The full 114-route startup benchmark has not yet been run on this revision.

Startup follow-up: the first 114-route attempt stopped at Command Palette's
route-heading assertion. Its inspected screenshot showed the correct page
behind the intentionally open command sheet. Dismissing the sheet exposed
`p-command-palette` at y=265 and Variations at y=409, both within the existing
limits. The harness now explicitly dismisses the two initially open showcase
overlays (Command Palette and Navigation Drawer) after measuring cold-start
time; route/top checks remain unchanged, and the dismissals are reported.
A new full run is in progress; no startup performance approval is claimed.

Catalog discovery now also compares exact tags against the parity registry,
not just the count. Its three tests are included in CI; the combined 16-test
audit-tool unit suite passed locally. Native CI for `7137867` completed all six
jobs successfully; the subsequent uppercase fix `d6a6a87` has been pushed for
its own checks. UI changes through `64fb0be` were pushed to draft PR 46.

Recent selector evidence is also preserved locally outside `/tmp` in the
ignored archive `docs/assets/android/audit/2026-09-13/selector-native-followup.tar.gz`
(6.3 MiB). It contains the three complete Autocomplete, Combobox and Select
reports, captures and hierarchies, including the before/after 200% tag case.
Archive integrity was checked with `gzip -t`; SHA-256:
`92cd2319b21a394f5c5716e42a97256e50ebbbda00da5578d1c3deb5919c4089`.
This is diagnostic evidence, not a public documentation asset or physical
device approval.

The next startup run terminated at Navigation Drawer after the harness sent
Back and left the app. Isolated cold launch confirmed its route caption and
Variations already appear at y=265/409 without dismissal. The drawer exception
was incorrect and has been removed; only Command Palette is dismissed.
No complete 114-route performance report has been produced yet.

## Tag Input custom-value interaction regression

A dedicated Tag Input audit was added to exercise add/remove of existing
options, custom creation, reopening/removing the custom value and isolation.
After correcting its parsing of the persistent field label, it reproduced
an inconsistent close: existing-option selection stays open, but custom Rust
creation closes the multiple-selection sheet. Evidence is under
`/tmp/pam-ui-tag-interactions-complete-20260913`; this is a failed partial run,
not a passing component report.

The UI modal identity was hashing the expanded option list, including custom
controlled values. It now hashes configured options before expansion. The
matrix regression asserts the same modal marker before/after Rust is added;
all 32,832 style and 456 render cases pass. Device confirmation of this change
and completion of the dedicated interaction audit remain pending.

The stable-identity build installed but the interaction audit still failed at
custom creation (`/tmp/pam-ui-tag-stable-modal-20260913`). The identity change
alone did not resolve dismissal. Android's custom-value action also emitted
the native dismissal event unconditionally, unlike existing-option actions.
The UI Android plugin now respects `closeOnSelect` before emitting that event;
the instrumented regression expects only Change when it is false. This
Kotlin change still needs compilation, instrumentation and device confirmation.

The Android build with `079941c` subsequently installed and the dedicated tag
audit passed all six checks. Complete report:
`/tmp/pam-ui-tag-dismiss-policy-20260913/report.json`. The inspected
`06-custom-retained.png` shows Kotlin/Swift in Skills and Android/Rust in
Technologies, with clean label/chip spacing. Existing-option additions and
custom creation both keep the sheet open; reopening and removing Rust passes.
This is an emulator functional result, not Samsung or all-variant approval.

UIKit had the same unconditional custom dismissal and ignored closeOnSelect
for existing sheet items. Commit `04c3201` unifies both paths under the same
property policy and adds a UIKit precedence test. It was pushed to draft PR
46; compilation/tests remain pending on the iOS CI runner. Android build
cleanup removed 904.9 MiB.
