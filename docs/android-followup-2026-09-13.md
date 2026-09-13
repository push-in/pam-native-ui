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

Tag Input's passing report/captures are preserved in the ignored local archive
`docs/assets/android/audit/2026-09-13/tag-interaction-fixed.tar.gz`, verified by
`gzip -t`, SHA-256
`13c4c0da12c4a1882fdfd77536cc60f302010875ed7b6072ad66000c4e23f54f`.

Native CI run `34774898818` for `d6a6a87` completed all six jobs successfully.
The newest UI commits had no automatically triggered run. Manual UI run
`34775988382` failed during checkout because the supplied abbreviated Native
SHA was interpreted as a branch/tag. It did not establish code failures.
Replacement run `34776129611` uses full Native SHA
`d6a6a8772e315551d0e4b3d3bca37d9c9ccb8ff5` against UI `04c3201` and is running.
The corrected 114-route startup audit on the tag-fixed build is also running;
its intended report is `/tmp/pam-ui-startup-114-tag-fixed-20260913.json`.

That startup run subsequently completed successfully: **114/114 cold launches**,
p50 329 ms, p95 386 ms, p99 410 ms, maximum 411 ms, and no matched runtime log
errors. Device: API 36 x86_64 emulator. Installed APK SHA-256:
`11ab30e2cf1525f0426850a8af629637dad4b43da453d0cb6da015b337306919`.
Report SHA-256:
`62d6210986d5a77956dbd2ab0b72b9cead34e8c41873c2c7438d5d08a4b9c96e`.
A local copy is retained at
`docs/assets/android/audit/2026-09-13/startup-114-tag-fixed.json`.
Only Command Palette required intentional overlay dismissal. These numbers
measure Activity cold-launch time, one sample per route; they do not certify
frame smoothness, interaction latency, physical-device performance or all
component variants. No release/public documentation gate is waived.

## Multi Select retained-state follow-up

The dedicated Multi Select audit completed all six checks on the installed
Android emulator build: add to selection, remove after reopening, filtered
selection, selecting two values from empty, three-field isolation and keeping
the sheet open during multiple selection. Report/captures:
`/tmp/pam-ui-multi-select-complete-20260913`. `09-two-categories.png` was
inspected and shows Engineering/Product, Ana/Bruno and Mobile/Web retained in
their separate fields. This is not complete all-variant or physical approval.

UI CI run `34776129611` passed both UIKit jobs but failed to compile the new
Android instrumented test: its collected event type was incorrectly Int,
although NativeViewEmitter supplies NativeViewEventKind. Commit `62e47a6`
corrects the test type. Replacement run `34776338798` is in progress; the
earlier compile failure did not execute the Android behavioral assertions.

## File Input document selection follow-up

Using two 43-byte text fixtures created in the emulator's Downloads directory,
manual selection returned the correct single filename and then `2 files
selected` in the separate multiple-file field. Inspected capture:
`/tmp/pam-file-multiple-selected-20260913.png`; XML for single, multiple and
cancellation is retained under `/tmp/pam-file-*-20260913.xml`.

Cancelling a subsequent multiple selection incorrectly cleared the displayed
value. Native Files intentionally reports cancellation as null/empty list;
the showcase was assigning that cancellation result to the selected state.
Its callback now ignores null/empty cancellation. Syntax and diff checks pass;
device confirmation is pending. The two fixtures remain temporarily for that
retest: `/sdcard/Download/pam-ui-file-audit-a-20260913.txt` and the matching
`pam-ui-file-audit-b-20260913.txt`. No personal documents were selected.

The showcase fix built and installed successfully. Both cancellation paths
were verified: two selected files remain after reopening/cancelling the
multiple picker, and the selected filename remains after cancelling the
single picker. XML/captures are `/tmp/pam-file-cancel-fixed-20260913.*` and
`/tmp/pam-file-single-cancel-fixed-20260913.*`; the latter capture was inspected.
The two exact Downloads fixtures were removed afterward; their local `/tmp`
source copies remain reproducible. Build cleanup removed 904.9 MiB.

UI CI run `34776338798` completed successfully for `62e47a6`: all nine checks
passed, including Android API 26/36 instrumentation and UIKit current/minimum.
The later showcase-only file cancellation fix has local syntax, build and
emulator interaction evidence; do not attribute it to that earlier CI run.

### Range Slider audit integrity follow-up

The baseline Reversed example was partly clipped by the bottom viewport.
The audit now scrolls the page gutter before measuring the actual Android
accessibility bounds. Removed the helper that fabricated a taller bounds
rectangle for clipped controls; target-size assertions must use real geometry.

The subsequent run reached Tick labels and failed its pixel assertion, despite
all five numbers being visibly rendered in the inspected screenshot
`/tmp/pam-ui-range-scrolled-20260913/15-tick-labels.png`.
Its 50dp scan offset began below the glyphs: the original band contained zero
dark pixels; the corrected 36dp text band contained 1,397 on the same capture.
The new band remains below the track/thumb and within the measured control.
No component rendering was changed to accommodate this test correction.

Syntax and diff checks pass. A full rerun is in progress at
`/tmp/pam-ui-range-label-band-20260913`; until its final report exists, this is
not complete Range Slider approval or evidence of animation smoothness.

That run stopped at Custom bounds: the detector counted disconnected green
value bubbles (and their glyph cut-outs) as extra handles. On its saved capture,
the old detector returned three centers `[271.5, 298.0, 942.5]`; counting only
green segments connected to the track yields `[274.5, 942.0]`, matching the
two displayed handles and expected range. The same detector passes all six
saved baseline variants. Four regression tests cover labels, plain handles,
a missing handle and no handles; the combined evidence-test suite passes
20 tests and now includes these checks in CI.

The full rerun `/tmp/pam-ui-range-track-connected-20260913` terminated with
`130% text-scale landscape lost range sliders`. It progressed through the
advanced range cases but produced no final passing report. This failure is
still unresolved; do not count this component as approved.

Separately, inspection of `00-baseline.png` shows asymmetric outer clearance
in Full range. The right handle extends past the accessibility host's right
edge while the left has more clearance. Investigate authored padding versus
Android host child placement before applying a UI-only workaround. No runtime
fix for this issue has been made in this follow-up yet.

The inspected 130% landscape capture actually shows Default fully rendered;
Isolated instance is below the viewport. The audit now scrolls to each named
control and checks its handles separately, retaining the initial viewport
capture. Seven local range/startup unit tests pass after this change.

The padding investigation identified an Android renderer issue in PAM Native:
engine child frames already include authored padding, while custom FrameLayout
hosts apply native padding again to the renderer's child margins. The pending
native patch subtracts the custom host's native padding from engine-derived
margins, in ordinary and virtualized-cell placement. Other container types and
safe-area adjustments are unchanged. Native `:app:testDebugUnitTest --tests
dev.pam.nativeapp.render.PamCustomViewPaddingTest --offline --no-daemon` passed
with Kotlin compilation (26s). A release showcase build is now running for
visual confirmation; no publication or final range approval yet.

Added a native instrumentation regression exercising actual Android
FrameLayout measurement/layout: a 300x80px host with 21px horizontal and
13px vertical padding must retain symmetric child margins without removing
the host padding. `:app:compileDebugAndroidTestKotlin --offline --no-daemon`
passes (16s); this is compilation, not an instrumented execution result.
The range audit now additionally requires symmetric outer clearance in its
Full range example (2dp rounding tolerance), preventing the observed defect
from passing on handle count and drag behavior alone.

The release showcase build completed and installed on emulator-5554 in 3m44s;
automatic cleanup removed 904.9 MiB. The new full range interaction audit is
running at `/tmp/pam-ui-range-native-padding-20260913` against that installed
native padding correction. Final runtime/visual confirmation remains pending.

Inspected that run's `00-baseline.png`: Full range now has symmetric endpoint
clearance, unlike the prior capture. The new symmetry assertion passed and
execution advanced to real lower/upper-handle gestures. This confirms the
specific padding defect visually, not the full component or all custom hosts.

Native correction committed/pushed as `f83eaa3d8df5c95b6c1b0ffc6475a895691792cb`
to draft PR 134. The complete local Android unit-test task passed in 9s.
Native CI run `34778057612` is in progress for that exact SHA; it is not yet
a passing instrumented/platform gate. No version was released.

The native unit XML reports total 139 tests, zero failures/errors/skips.
Prior failed range runs were preserved locally in
`docs/assets/android/audit/2026-09-13/range-audit-diagnostics.tar.gz`
(SHA-256 `83bbdc2a3f2d760539dd4762d25680e973926cf1f38ba43562f9bb5177ffceff`;
gzip integrity passed). These are diagnostic captures, not approved docs media.

The native-padding range run completed its gesture/advanced/130% text checks,
then stopped in the log collector: Android 36 exposes `appId`, not the old
`dumpsys package` `userId` field. The shared slider collector now resolves the
exact package UID using `pm list packages --user current -U`; tests reject a
debug-package prefix match. All six geometry/log-collector unit tests pass.
Reran only the failed log check immediately, without another app session:
21 UID-scoped lines, zero matches for the audit's runtime-error markers.
There is no fabricated full-run report: the original process exited at the
collector and this supplemental check is recorded separately. Physical,
smoothness and broad custom-host regression gates remain pending.

Started Autocomplete regression against the same installed native correction
at `/tmp/pam-ui-autocomplete-native-padding-20260913` to check another affected
host family before considering publication.

Autocomplete completed successfully after the native padding fix: all 14
checks pass in `/tmp/pam-ui-autocomplete-native-padding-20260913/report.json`.
Inspected its empty-state sheet capture: search field, message and panel
margins remain aligned. UI CI run `34778225705` targets UI `0dd36df` with native
`f83eaa3`; native CI `34778057612` has passed build/unit, Rust/PHP/protocol and
Swift/UIKit jobs, while API 26/36 instrumented execution is still running.

Added a focused range frame diagnostic (`tools/audit-range-slider-frames-android.py`)
using 12 real ADB drags, animations enabled, final geometry verification and
UID-independent gfxinfo for the exact package. It explicitly does not grant
approval or claim physical-device smoothness. First measurement is running
at `/tmp/pam-ui-range-frames-native-padding-20260913`.

That sample completed: 404 frames, p95/p99 16ms, zero legacy janky frames,
but **54 current-definition janky frames (13.37%)**. Raw gfxinfo additionally
reports 325 high-input-latency frames, zero slow-UI-thread frames and 54 slow
draw-command/deadline-missed frames. The 12 drags preserved final geometry.
These mixed results do not establish impeccable smoothness; rendering and
input latency need further diagnosis/physical comparison. The diagnostic now
also exposes those counters directly in future reports instead of hiding
them behind the percentiles. System animation settings were restored.

### Slider drag hot-path investigation

Found redundant UI-plugin work: each changed touch MOVE called
`applyRangeVisualState`, laying out/scaling/translating invisible authored
children even though the visible slider canvas reads current values directly.
The pending UI Android patch leaves canvas invalidation and coalesced change
events intact, and synchronizes hidden anatomy at UP/CANCEL instead.
An instrumentation regression checks no hidden-fill transform during MOVE
and correct synchronization at both termination paths. This is a UI-plugin
optimization, separate from the already committed native layout correction.

Local `compileDebugAndroidTestKotlin testDebugUnitTest --offline --no-daemon`
passed in 25s. Instrumentation is compiled, not yet executed for this patch.
Gradle reported a cleanup permission warning for an old 96KiB `.gradle/8.13`
cache after successful completion; no permissions were broadened or user files
deleted. A release build is running to compare the same drag diagnostic before
and after. No performance improvement is claimed until measured.

The frame collector now has three focused regression tests: current jank is
kept distinct from legacy jank, missing latency counters fail rather than
defaulting to zero, and insufficient frame samples fail. The combined
geometry/collector suite passes nine tests. These tests were added to CI;
the in-flight earlier CI run does not include this local diagnostic change.

UI CI `34778225705` completed successfully for `0dd36df` with native `f83eaa3`:
all nine jobs passed. This covers the padding correction, not the later
uncommitted drag-hot-path optimization. Its release build has now installed
successfully (3m46s; cleanup 904.5 MiB), and the comparison sample is running at
`/tmp/pam-ui-range-frames-hotpath-20260913`. SurfaceFlinger identifies NVIDIA
RTX 4070 through the Android Emulator OpenGL ES translator; this is hardware
accelerated emulation, not evidence that the emulator caused the measured jank.

Comparison completed: 399 frames, 55 current janky/deadline-missed frames,
zero legacy jank, p95/p99 16ms, 308 high-input-latency frames, zero slow UI
thread frames and 54 slow draw-command frames. Geometry remains correct.
Against baseline (54/404 janky), this does **not** demonstrate smoother
rendering. Removing hidden-child work did not resolve the observed deadline
misses; investigate frame stages before claiming or publishing a performance
fix. The optimization remains uncommitted pending further validation.

### Frame-stage evidence and native gesture execution

The collector now retains `gfxinfo ... framestats` and names timestamp
intervals literally; it does not call the swap-to-GPU-completion interval pure
GPU execution time. Android documents framestats as recent-frame diagnostic
data: https://developer.android.com/tools/dumpsys#graphics . Eleven local
geometry/collector tests pass, including flagged and missing timestamp cases.

Stage sample `/tmp/pam-ui-range-frame-stages-20260913/report.json`: 400 frames,
55 current janky frames, one legacy janky frame, p95 16ms/p99 17ms. In its
120 eligible recent frames, p95 input-start-to-draw-start was 1.386ms,
draw-commands-to-swap 1.542ms, and swap-to-GPU-completion 15.373ms. Host-side
test APK assembly overlapped the beginning of this diagnostic; do not treat
it as a controlled A/B performance comparison or proof of a GPU defect.

Built the instrumentation APK and executed the exact
`sliderAndProgressMoveAuthoredAnatomyWithoutPhpFrames` test on emulator-5554:
`OK (1 test)`, including MOVE, UP and CANCEL assertions added for the pending
optimization. This upgrades that regression from compiled-only to executed;
it does not close the remaining smoothness gate.

Three additional targeted instrumented tests passed on the same emulator:
range endpoint/payload behavior, adaptive stale-track bounds and the
100-tick allocation regression (`OK (3 tests)`). The hidden-anatomy hot-path
change is retained as tested redundant-work removal, not as a measured jank
fix. Release/physical smoothness remains unapproved.

Executed the complete `MobileUiHostInstrumentedTest` class on emulator-5554
against the latest UI optimization: `OK (66 tests)`, 6.194s. This covers the
host's existing behavioral/layout/accessibility regressions, not all 114
showcase compositions or all visual variants. UI commit `05dfed8` was pushed;
CI run `34778970174` is running against native `f83eaa3`.

Started the exact 114-route cold-start regression on the installed release
at `/tmp/pam-ui-startup-114-padding-hotpath-20260913.json`; no final result yet.

Native CI `34778057612` is now fully green for `f83eaa3`: six jobs including
API 26/36 instrumentation and cross-platform accessibility evidence passed.

A subsequent native code review found the virtualized-cell placement path
did not set the physical frame gravity already used by ordinary placement.
Added that gravity so an RTL FrameLayout does not resolve engine coordinates
through START again. A narrow-cell LTR/RTL instrumentation regression was
added; pixel-snapping/padding unit tests and instrumentation compilation pass
(26s). Native debug/test APK assembly is running for local execution. This
new patch is not covered by the earlier green CI and is not yet in showcase.

Native debug and test APK assembly completed successfully in 12s. Execution
of the new RTL test is waiting for the active startup audit to release the
emulator; do not overlap foreground test apps with startup measurements.
The public `/pam-docs/mobile-ui/overview/` URL returned HTTP 200 in a read-only
check (this does not approve the pending documentation media).

The startup auditor now emits flushed per-route progress on stderr after each
route/heading assertion. The three catalog-discovery unit tests pass; the
already running process was not restarted to adopt this diagnostic change.

The 114-route startup run passed: p50 330ms, p95 422ms, p99 479ms, max 500ms,
zero matched runtime errors. Report SHA-256:
`a085c8fa071aaf15ec47b7c7ed606113c524374cd2542d2a91766c83f71b73d3`;
installed APK SHA-256:
`f96f127975f6a281cc5a7a7ad92ce438be48b1a6fe4b46d8312e9ac0c0edf1c3`.
This is emulator startup evidence for native f83eaa3 plus UI drag optimization;
it predates the new virtual-cell RTL patch and does not approve every variant.

After startup completed, installed the native debug/test APKs and ran both
padding and physical-cell LTR/RTL instrumentation checks: `OK (2 tests)`.
The first runner command used the unsuffixed test package and could not start;
`pm list instrumentation` identified `dev.pam.nativeapp.debug.test`, which was
used for the successful execution. No test was silently skipped.

### Selector empty-state hierarchy

MultiSelect regression after native padding passed all six interaction checks
at `/tmp/pam-ui-multi-select-padding-regression-20260913/report.json`. Inspection
of `09-two-categories.png` exposed the Error field repeating Categories as
both persistent label and empty placeholder. Following the UI/UX skill's
label/hint hierarchy guidance, material selectors now use action hints by
default (Select an option / Search options / Select or add / Add tags /
Select options), preserving the label and explicit custom placeholders.
The UI renderer also determines hint color from selection state rather than
string equality, so a selected value matching placeholder text remains a
normal foreground value.

Added regressions for all five selector families, explicit hint overrides and
selected-value/placeholder collisions. PHP material matrix passes (32,832
style cases, 456 renders, 114 components), syntax and diff checks pass. These
are pending UI-only appearance changes; native APIs were not changed for them.

PHPStan level 9 also completed with no errors for the selector hint changes.
A combined release build is running with the selector rendering fix and the
latest native virtual-cell RTL correction. It has not yet supplied new visual
evidence; the previous MultiSelect capture is explicitly the before image.

The combined release build completed in 3m42s and installed successfully;
cleanup removed 904.9 MiB. Started the post-hint MultiSelect interaction and
capture run at `/tmp/pam-ui-multi-select-action-hints-20260913`. It uses native
3f6e17d and the pending UI selector hint changes; results are not yet final.

Post-hint MultiSelect completed: all six interaction checks passed. Inspected
`09-two-categories.png`: the Error control now shows Categories once as its
label and Select options in muted text as its empty hint; selected chips in
the other fields remain aligned. This is the after image for the duplicate
label defect.

Also added explanatory error messages to the showcase's masked-phone,
currency and multi-category examples using existing `errorMessage` props.
The PHP matrix verifies these three families render exactly one error message
with the semantic destructive color; matrix and syntax pass. These new
showcase messages are not in the just-inspected APK and await next-build
visual confirmation. No new core/native API was introduced for the copy.

### Error-helper confirmation and retained selection

Built UI `8d908f8` with Native `3f6e17d` and installed on the API 36 emulator
successfully in 3m46s. Automatic cleanup removed 904.9 MiB. The shared Gradle
cache already occupies 11 GiB, so this build retained isolated cache cleanup;
no global cache configuration was changed or user cache deleted.

Inspected all three error-helper screenshots at normal font scale and 200%:
masked phone, currency and multiple categories retain their complete helper
text below the outline. The phone helper wraps onto two lines at 200%; the
currency and category helpers remain visible without clipping. These are
scrolled field-region checks, not approval of the entire large-text screen,
all themes, or every component variant. Original font scale 1.0 was restored.

Evidence directories:

- `/tmp/pam-ui-error-messages-20260913` (hierarchies, captures, presence report).
- `/tmp/pam-ui-error-messages-font200-20260913` (hierarchies and captures).
- `/tmp/pam-ui-multi-select-error-helper-20260913` (six interaction checks
  passed: add/remove, filtering, empty selection, instance isolation and
  keeping the multiple-selection sheet open).

The presence report deliberately retains `visualApprovalPending: true`;
manual inspection above is a separate, scoped observation, not an automated
whole-component approval. Samsung remains locked (`deviceLocked=1`).

Visual review also caught an inconsistent demo: the phone Error variation
inherited a complete number while asking the user to complete it. Its source
now explicitly starts with incomplete digits `11987`. Syntax and PHPStan
level 9 pass. This last demo-data adjustment is **not yet installed** and is
not represented in the screenshots above; include it in the next batch build.

UI CI run `34780218220` pins UI `8d908f8` to Native
`3f6e17d38f4b3552c7f9161cace8883dd0d000da`. It and Native run `34779362085`
were still in progress at this observation; neither is a completed release gate.
The 11 local range geometry/frame-parser regression tests also pass, without
changing the previously documented unresolved drag-performance result.

### Showcase hierarchy and button interaction batch

Native CI `34779362085` completed all six jobs successfully. UI CI
`34780218220` completed all nine jobs successfully for UI `8d908f8` pinned to
Native `3f6e17d`; this does not cover subsequent showcase caption edits.

Current button inspection confirmed Text and Plain have no filled containers
and Text, Plain and Outlined activate correctly. Before evidence:
`/tmp/pam-ui-button-current-20260913`. The inspection exposed a showcase-wide
hierarchy problem: rotating filled variation captions looked like additional
buttons. Replaced those decorative pills with muted 12/18 semibold captions,
without fixed height or background. This follows the UI/UX skill's hierarchy
and dynamic-type guidance and changes showcase composition only. Updated the
visual-quality contract accordingly; component theme colors remain intact.

PHP syntax, PHPStan level 9 and diff checks passed. Combined build installed
successfully in 3m44s, including the incomplete phone Error example; automatic
cleanup removed 904.9 MiB. Button batch evidence:
`/tmp/pam-ui-button-quiet-captions-20260913/report.json`.

All 19 button specimens passed the scoped interaction batch: every enabled
example activated its callback; Disabled and Loading remained disabled and
did not produce the pressed label. Extra small, Small and Compact density
retained their text-node bounds inside their controls at 200% font scale.
Inspected baseline, loading and large-font captures. Captions no longer compete
with controls, but **small buttons remain visually tight vertically at 200%**:
contained text is not enough to approve their typography/padding. Revisit the
public button's intrinsic height/padding before approving dynamic type.
Font scale was restored to 1.0. This batch did not measure motion, verify
expanded hit areas, test every theme, or replace the Samsung release gate.

The older full button auditor still contains pre-Spectrum color expectations
(for example primary RGB 22/101/52 instead of the current 7/122/80). It was
not run wholesale or silently treated as current: this batch reused its
route/interaction helpers with explicit scoped checks, not its obsolete
visual-token assertions. Those expectations need updating from the actual
theme contract before that full auditor can serve as a release gate again.

Disk hygiene: removed only `/home/davidbalbino/.gradle/caches/9.3.1/transforms`
after verifying it was a regular user-owned directory and no Gradle 9.3.1
process was running. Its 6.9 GiB of derived transforms are regenerable;
downloaded modules, sources and evidence were preserved. Global Gradle caches
fell to 3.7 GiB, under the documented 8 GiB limit; disk free space was about
30 GiB during the build. The first force-delete command was rejected without
execution; a non-force recursive removal of the same exact cache succeeded.

### Public text-button intrinsic padding

Fixed the public UI style resolver, not the showcase: text-bearing buttons now
use the size token as `minHeight`, omit fixed `height`, and reserve vertical
padding (4 dp for extra-small/compact, 8 dp otherwise). Icon-only controls
retain fixed geometry and zero text padding. This also applies to grouped
text buttons and extended FABs using the same resolver; no new Native API or
core PAM change was needed. Group/toggle disabled-state contracts now assert
minimum height and absence of a fixed height while preserving hit-slop checks.
Added six button-variant intrinsic-height checks and an icon-only control
counterexample. Extended FAB tests retain its 56 dp minimum.

Material matrix, theme runtime, recipe matrix, PHPStan level 9 and diff checks
passed. Initial matrix runs correctly failed obsolete fixed-height assertions
for toggle segments and extended FAB; these were updated to the explicit new
minimum-height contract, not removed. Release build installed in 3m38s and
cleaned 904.9 MiB of intermediates.

Measured and tapped Default, Outlined, Text, Extra small, Small and Compact
density on API 36 at font scales 1.0 and 2.0: all 12 checks passed. Report and
captures: `/tmp/pam-ui-button-intrinsic-padding-20260913/report.json`.

| Controls | Height at 100% | Height at 200% | Top/bottom text clearance at 200% |
| --- | --- | --- | --- |
| Default, Outlined, Text, Small | 40 dp | 56 dp | 8 / 8 dp |
| Extra small, Compact density | 32 dp | 48 dp | 4.19 / 3.81 dp (pixel rounding) |

Inspected default, extra-small and compact 200% screenshots: text no longer
crowds the surface edge. Font scale was restored to 1.0. This closes the
specific tight vertical padding defect for the six measured button examples;
it does not approve all grouped controls, FAB variations, themes, landscape,
Samsung, iOS rendering or continuous animation. Those shared consumers still
need device regression coverage before release.

### Group regression exposed native row measurement

On UI `539789e` / Native `3f6e17d`, Standard, Connected, Compact density and
Long labels groups completed every selection step at font scale 1.0. The
200% run stopped on Standard's first **outside-edge** tap: the helper taps
3 dp above Day, and Week stayed selected. This does not establish that center
taps fail; it leaves expanded-target behavior outside a grown parent pending.
Evidence: `/tmp/pam-ui-group-intrinsic-20260913` (partial run, no final report).
The emulator font scale was restored to 1.0 by cleanup.

Inspection of `standard-font2.0.png` also found clipped second words in Full
width: “7 days” and “30 days” displayed only the numbers. Native intrinsic row
measurement was using the entire row width for each child, before final flex
allocation narrowed those children. A candidate fix now shares main-axis
allocation between measurement and final layout, including growth/shrink and
constraints. This belongs to PAM Native's layout engine, not a UI height hack.

New Rust regression `auto_row_measures_text_height_at_allocated_flex_width`
models three equally allocated text-bearing pressables at 200% with shrinkable
text. With allocated-width measurement disabled, the row incorrectly remains
56 dp and the test fails; restoring it passes the two-line 96 dp requirement.
The initial fixture incorrectly used Button/Value instead of Pressable/Text;
only the corrected fixture plus the explicit disabled-fix check count as
regression evidence. All 75 engine tests and all-target Clippy with warnings
denied pass after restoring the fix.

The native patch is **not installed on Android yet**. Actual group labels,
runtime performance of the shared layout change, outside-edge taps, FAB
interaction, iOS and Samsung validation remain pending. UI CI `34781564249`
targets UI `539789e` with the older Native `3f6e17d`, not this new engine patch.

### Native row fix on-device and shared-consumer follow-up

Installed Native `634ef37` with the existing UI in 4m02s. Inspected
`/tmp/pam-ui-group-native-only-20260913/full-width-font200.png`: both “7 days”
and “30 days” now display completely on two lines. The native correction alone
fixes that reproduced clipping defect. It also exposes differing heights for
single-line and multiline siblings (Today 56 dp, the other two 96 dp).

Separately, public button Text children now default to `flexShrink: 1` while
preserving an explicit application override (including zero). Matrix tests
cover both behaviors. This makes label width adaptation explicit; it is not
claimed as necessary to recover the specific words already fixed above.
A second build with this label policy installed in 3m43s. Both builds cleaned
904.9 MiB each after completion.

The group helper now exercises outside-edge expansion only when the actual
button is shorter than 48 dp. A grown control is already a complete minimum
target; its ancestor delegates only received events, not arbitrary points
outside that ancestor. See Android's [TouchDelegate contract](https://developer.android.com/reference/android/view/TouchDelegate).
Normal 40/32 dp examples still execute the original outside-edge taps, so this
change does not skip their expanded-target checks.

Group results are `/tmp/pam-ui-group-native-width-20260913/report.json`:
Standard, Connected, Full width, Compact density and Long labels complete
selection steps in normal font and the expanded-font round. **Exception:**
the first Standard capture in the expanded round was taken before Android
applied the configuration; its recorded 40 dp heights and screenshot prove
it was still normal font. Do not count that entry as 200% evidence.
Repeated only Standard after a fresh launch and confirmed all three actual
heights were 56 dp before tapping. Initial Week and final Month selection
passed; inspected the after capture. Corrected evidence:
`/tmp/pam-ui-group-standard-font200-confirmed-20260913/report.json`.

Extended FAB activated at both 100% and 200%, changed its accessible label to
“Create project completed”, displayed Created and a check icon, and retained
its 56 dp height with readable, aligned content at 200%. Inspected the latter
capture. Evidence: `/tmp/pam-ui-fab-native-flex-20260913/report.json`.
These are interaction/layout observations, not animation/performance approval.

To address the mixed-height row, UI now sets cross-axis stretch for full-width
PBtnGroup/PBtnToggle while keeping regular groups centered. Six style checks
cover default, block and fullWidth for both families; material matrix and
PHPStan level 9 pass. **This final stretch change is not installed yet:** stage
the updated MaterialStyleResolver in the next build and verify equal heights,
wrapping and group/toggle interactions. Current screenshots intentionally show
the before state. Font scale was restored to 1.0; disk has about 31 GiB free.

Native CI `34782038599` targets `634ef37` and was still running Android API 36
at the last check. UI run `34781564249` finished successfully on the older native
snapshot; it does not validate this combined native/UI revision. No release
or documentation gallery publication was performed.

### Equal-height groups installed; full-catalog startup regression

Installed UI `5824402` / Native `634ef37`, including the final group stretch
policy, in 3m44s; automatic cleanup removed 904.9 MiB. Eight focused checks
passed: Full width and Long labels in both PBtnGroup and PBtnToggle at 100%
and 200%, with equal sibling heights and successful selection sequences.
Heights were 48 dp at normal scale and 96 dp for the tested multiline groups
at 200%. Toggle checks also require unchanged segment bounds after selection.
Evidence: `/tmp/pam-ui-equal-group-heights-20260913/report.json`.
The toggle interaction helper now applies the same measured sub-48 dp
outside-edge probe rule as the group helper; Python compilation passes.

Inspected the group Full width and toggle Long labels 200% captures: the
target specimens are aligned and their labels complete. **Other specimens in
the same toggle capture are not approved:** Five options breaks Mon/Tue/etc.
letter by letter, and Disabled group extends beyond the right content edge.
Their responsive width/overflow behavior is the next concrete defect; passing
the eight scoped checks does not approve those neighboring examples.

After restoring and confirming font scale 1.0, the complete 114-route release
cold-start audit passed with no matched runtime errors: p50 329ms, p95 392ms,
max 450ms. This exercises route startup, not every interaction or visual state.
Report: `/tmp/pam-ui-startup-114-flex-width-20260913.json`, also preserved at
`docs/assets/android/audit/2026-09-13/startup-114-flex-width.json` (ignored).
Report SHA-256:
`2b558d1be68955b6873ca1e53f23176a30f28e7f2628a7725a9e2f48ee7d2190`.
APK SHA-256:
`7b9e737b8c7761dcd955c147515b9e5cf98107e411b847f0685a0b6ebe2a33fc`.

Native CI `34782038599` completed all six jobs successfully. UI CI
`34782732115` completed all nine jobs successfully for UI `5824402` pinned to
Native `634ef37`. These CI results and startup timings do not approve Samsung,
all iOS visuals, continuous gesture performance, or the known width defects.

Preserved the focused group/FAB captures, corrected Standard large-font
evidence, native-only before-stretch capture and startup report in local
`docs/assets/android/audit/2026-09-13/button-native-flex-followup.tar.gz`.
Gzip integrity passes; SHA-256:
`127f55dcf5762b7167f28ca33a80fe8ed5052ef201662c6d54f406f9522ffae2`.
This ignored diagnostic archive is not published gallery media.

### Horizontal overflow prerequisite — local Native implementation

Investigating the Five options letter wrapping and Disabled group overflow
confirmed that Native already supplies horizontal Scroll, but its unspecified
height falls back to the generic 240 dp list height. The local engine change
measures horizontal Scroll's auto height from its children at the natural
scrollable width (with fillViewport respected), including vertical padding.
Explicit height continues to take precedence; vertical Scroll is unchanged.

The regression checks a 400 dp content row in a 300 dp viewport: 56 dp content
plus 16 dp padding produces a 72 dp scroller and positions the following
sibling at 72 dp. An explicit 100 dp height still positions it at 100 dp.
All 76 engine unit tests and clippy with warnings denied pass locally.
This is an unpublished Native prerequisite, not an installed Android build
or a UI visual approval. Group integration, narrow-width and enlarged-font
interaction checks remain pending; the two reported visual defects remain open.

### Scrollable groups — integration and first device check, not approved

PBtnGroup/PBtnToggle now compose Native Scroll with an inner Row. Public
semantics, viewport sizing, padding and margins remain on the root; row gap,
direction and alignment (including explicit Style overrides) stay on content.
The PHP matrix preserves existing selection/mandatory/multiple/disabled/RTL
checks through that new hierarchy and adds custom-inset/non-duplication checks.
Material matrix, theme runtime, recipe matrix and PHPStan level 9 pass.

First release build completed in 4m59s, installed on emulator-5554 and cleaned
904.9 MiB. Samsung was authoritatively locked. This APK contains the initial
horizontal-auto-height engine change, NOT the later natural-text/grow-width
corrections described below. Diagnostic captures are at
`/tmp/pam-ui-group-overflow-20260913`.

The first diagnostic swipe started too near the system edge, navigated away
from the app and failed the foreground guard. After moving the gesture into
the center of the control, four scoped checks passed: Five options/Fri selected
and Disabled group/Admin remained disabled, each at font scales 1.0 and 2.0.
Font scale restored and confirmed 1.0. These checks prove reachability and those
specific state transitions, not text quality. Inspected captures still show
Mon/Wed breaking into fragments, including at normal scale. Visual gate fails.

Two additional local Native corrections address the cause: text leaves on
the horizontal scrolling axis are measured without a viewport-width cap;
zero-basis grow items reserve enough natural content width for the largest
weighted share instead of dividing a sum that can undersize the widest label.
Regression checks include a long unwrapped label and two equal-grow children
with natural widths 120/180, which require a 360-wide scroll row and 180 each.
All 77 engine tests and clippy with warnings denied pass. The second release
build is running; its device evidence must not be confused with the first APK.
Source SHA-256 for this second build:

- Renderer: `0da258117a42d08f35913d333a2d82a93628f7953aaddcdeac42be2eb8458654`
- Engine layout: `3559f3c1935ac1f025d2d52e4ea85c98f7a3b606e7c74bb95d4e39ca248bddea`

No release/public gallery update, Samsung approval or complete group approval
is claimed. Remaining checks include readable full labels, scroll affordance,
RTL overflow, all variants and user styles on the corrected binary.

### Corrected binary — readable short labels and scoped overflow interactions

The second build completed successfully in 3m56s, installed on emulator-5554
and cleaned 904.9 MiB. Its source hashes match the preceding entry. Installed
APK SHA-256: `f19daa87871db9c90f4d81e1b1925cce00c9bb08ac5711e8a949a44783747020`.

Inspected Five options at normal and 200% scale: visible Mon/Tue/Wed/Thu/Fri
labels no longer break into fragments. Horizontal swiping reaches Fri and a
real tap selects it. The initial corrected diagnostic still accepted a partly
visible Admin, so it is not proof of a complete label. Strengthened the check
to require the target text to have at least 4 dp clearance inside the viewport
before tapping. That corrected run passed all four cases (Five options/Fri,
Disabled group/Admin, each at 100% and 200%). Inspected its 200% Admin capture:
the complete label is visible after swiping and the disabled control remains
inactive. Font scale restored and confirmed 1.0.

Final scoped report:
`/tmp/pam-ui-group-overflow-complete-labels-20260913/report.json`;
SHA-256 `fe833dce9644b982694e0d4dd71b75bf78acf2265b213e93fd6d380783f4d3be`.
The reusable runner is `tools/audit-button-toggle-overflow-android.py`
(`--serial`, `--output` required); Python compilation and CLI help pass.
It checks visible short-label height, viewport gutters, reaching/selecting Fri,
and reaching Admin without enabling it. It does not validate all variants,
all labels, performance, screen readers, or the entire component.

Native Android's inner HorizontalScrollView is exposed as scrollable when it
overflows; the outer semantic group being non-scrollable was not evidence of
a lost scroll action. This is hierarchy evidence, not a TalkBack approval.
Visual release remains pending: overflow needs a clearer persistent affordance,
and RTL/all-variant checks are not complete. Existing neighboring labels outside
the horizontal viewport are intentionally clipped until scrolled, not proof of
letter wrapping. No public gallery or package release was updated.

The three diagnostic batches (including rejected/intermediate captures) are
preserved in ignored `docs/assets/android/audit/2026-09-13/group-overflow-followup.tar.gz`.
Gzip integrity passes; SHA-256:
`9a4b8f7c06d070bbc8c0de7d3f47335e9f15d8ab0ced9adf4ca3d580e81bbc30`.

### Overflow affordance candidate and Native CI

The local UI candidate enables Native Scroll's persistent scrollbar and 12 dp
fading edges for both button-group families. Material matrix and PHPStan level
9 pass; the group contract asserts the two native properties. Renderer SHA-256:
`e89592275ad876ced8a92e82c899ea57558f1d0b160cd061b548e9134553ab1c`.
Its Android release build is in progress; appearance of the indicator is not
yet approved. The overflow runner now also checks RTL visual order and tapping
Day, at both font scales. Python compilation passes; those new device checks
have not yet run on the candidate.

Native layout changes are committed/pushed as
`ebd00a32e67f6b31272a2af8efc920910f2d2b93` on the existing draft PR 134.
CI run `34784866537` is confirmed in progress for that exact SHA. This is a
review-branch push, not a merge or package release. Local Native validation
remains 77 passing engine tests and clippy with warnings denied.

### Affordance/RTL device batch and paired review branches

UI candidate `ca87d63cf58503949f30186b278bb462b078de34` is pushed to draft PR
46. CI `34784974116` is running with Native explicitly pinned to
`ebd00a32e67f6b31272a2af8efc920910f2d2b93`; no merge or version release occurred.
The local build completed in 4m43s, installed successfully and cleaned
904.9 MiB. Installed APK SHA-256:
`3b4586e63a830fe719ecb581c799dee47706d80d8604f1cbedef0d76a2b60684`.

`tools/audit-button-toggle-overflow-android.py` completed six checks on that
APK: Five options/Fri, Disabled group/Admin, and RTL/Day at 100% and 200%.
The RTL check verifies physical left-to-right ordering as Month/Week/Day among
visible segments, then reaches and selects Day. Inspected the RTL 200% result
and Five options normal-scale capture. Short labels remain unwrapped; target
labels are visible before taps. Font scale restored and confirmed 1.0.
Report: `/tmp/pam-ui-overflow-affordance-rtl-20260913/report.json`;
SHA-256 `b850b1b5b4068ceb692cc29c2d5bcb3de70c46f474f364baf36dc7887dec71c3`.

Fading edges are visibly active. A clearly distinguishable persistent scrollbar
was NOT established by visual inspection; enabling its property is insufficient
evidence. Overflow discoverability therefore remains an open visual gate.
This six-case batch also does not approve every group variant or the PBtnGroup
family, iOS visuals, TalkBack, physical Samsung, or gesture performance.
Native CI has completed Swift/UIKit and Rust/PHP successfully; Android jobs
are still pending at this checkpoint.

### Isolated native indicator regression and track separation candidate

Added a local Native instrumentation regression,
`PamScrollContainerInstrumentedTest#persistentHorizontalIndicatorDrawsBeforeFirstGesture`.
It lays out blank overflowing horizontal content in a 300×80 viewport and
requires non-background indicator pixels in the bottom strip before any gesture.
The debug/test APK build reused cached dependencies offline (16s); installation
and the focused test on emulator-5554 succeeded: `OK (1 test)`. This proves the
basic native indicator draws, not that its contrast/placement in UI is adequate.
No production Native change was needed for this diagnostic.

The working UI candidate reserves a 4 dp bottom strip on the inner content row,
with a 52 dp minimum row extent to retain the prior 48 dp content area. This
tests the hypothesis that a scrollbar drawn over a segmented outline is hard
to distinguish. Public viewport padding/margins are unchanged. Material matrix
and PHPStan level 9 pass. Renderer SHA-256:
`d9bbe54f4df4a306f5e0d7e899e61dcd99786955c9e54b6b6975aba33f6800f1`.
Its release build is in progress; visual improvement is not yet proven.

UI CI `34784974116` completed all nine jobs successfully for `ca87d63`, pinned
to Native `ebd00a3`. It does NOT cover the new 4 dp working-tree change. Native
CI still has API 26/36 instrumentation running. Global Gradle cache remains
3.7 GiB; local Native app build is 151 MiB. No package was released.

### Track candidate: partial device evidence and interruption recovery

The 4 dp track candidate built in 5m52s, installed and cleaned 904.3 MiB.
The device runner now checks clearance below all visible buttons. Five scoped
cases completed (three normal-scale cases, Five options and Disabled group at
200%) before the process terminated with signal 143. The last RTL 200% case
and final report did NOT complete. Captures are in
`/tmp/pam-ui-scrollbar-track-20260913`; they are partial diagnostics, not a
passing batch. Font scale was found at 2.0 and explicitly restored to 1.0.
The interrupted run had no durable settings snapshot, so restoration of its
other pre-run settings cannot be proven retrospectively.

Inspected Five options after selection at normal scale: labels and clearance
are retained, but a clearly distinguishable indicator is still not established.
The isolated native draw test does not explain this composition-level result.
Next diagnosis must use the complete renderer rather than another padding guess.

Hardened the overflow runner with incremental reports and a SIGTERM handler
that executes cleanup. The shared Android audit helper now captures font scale
and persists all seven original settings before changing them. Python
compilation passes. A deliberate 8-second SIGTERM test exited with expected
timeout code 124; direct ADB reads then matched all seven saved values, and
the partial report was written with zero completed checks and fullApproval=false.
Evidence: `/tmp/pam-ui-overflow-signal-recovery-20260913`.

Also documented bounded shared Gradle cache reuse in `docs/build-hygiene.md`:
the existing CLI override can avoid repopulating isolated caches while mandatory
application-artifact cleanup and the 8 GB shared-cache limit remain in force.
This was verified in CLI code, not yet benchmarked on a showcase release build.

### Renderer window regression: redraw fixed, initial showcase indicator still open

A complete-renderer fixture compared indicator-on/off pixels in the reserved
strip. Software Canvas drawing passed, while actual UiAutomation window
captures failed. A separate body-color assertion proved the fixture was really
present in the captured window. Explicit invalidation after changing native
indicator visibility/persistence made that same window comparison pass.
Combined Scroll/renderer instrumentation completed `OK (11 tests)` in 4.813s.
The reusable Native fix and regressions are pushed on draft PR 134 as
`aa9a9df4b71e143f4079c42a3a88611ff1a3b8cc`; CI `34786118795` is running.
Previous Native `ebd00a3` CI `34784866537` completed all six jobs successfully.

The showcase release build with the bounded existing shared Gradle cache
completed in 1m06s (27 cached tasks), versus 5m52s for the preceding isolated
cache build. This is an observed pair, not a controlled universal speedup.
The Gradle file-watcher warning did not stop the build. Installation succeeded,
96.9 MiB of project artifacts were cleaned, shared cache remained 3.7 GiB and
31 GiB remained free.

All six scoped overflow/RTL cases passed on this build, including track
clearance; font scale restored to 1.0. Report:
`/tmp/pam-ui-indicator-redraw-20260913/report.json` (SHA-256
`b850b1b5b4068ceb692cc29c2d5bcb3de70c46f474f364baf36dc7887dec71c3`).
Report content is deterministic and shares a hash with an earlier six-case
report; do not treat that hash as an APK or media identity.
Installed APK SHA-256:
`c88bb451c7b274b46b5223e5edb7df3205113da4cd24c483b7a5f1226ac12295`.

Initial showcase indicator visibility is STILL unproven/failed: in the inspected
normal-scale Five options capture, group bounds were [42,1753,1038,1890], button
bottom 1879. All 9,560 sampled interior footer pixels were canvas RGB(247,249,255).
The native toggled-visibility fix therefore does not yet resolve this initial
showcase state. Next regression must capture the initial window before software
draw/toggling; no additional padding experiment or blanket approval is justified.

### Initial-window and nested-scroll isolation

The renderer regression now captures the initial window before any software
Canvas draw or indicator toggle and compares the reserved strip with a later
indicator-disabled capture. It passed on API 36 (1 test, 1.170s). Adding the
showcase's fill-viewport, nested-scroll and 12dp fading-edge properties also
passed (1.169s). The retained fixture now additionally nests the horizontal
scroll in a vertical Scroll/Column, positioned at x=16dp/y=480dp; the same
initial-window assertion passed (1.183s). These are native regression results,
not evidence that the showcase indicator is fixed.

Rendering the staged Button Toggle route through its real PHP lifecycle
confirmed all 14 group Scroll elements carry horizontal=true,
showsIndicator=true, persistentScrollbar=true, fillViewport=true,
nestedScrollEnabled=true and fadingEdgeLength=12. The staged and source
PamScrollContainer.kt hashes also match:
`b8a264ab5bfb4de57ffda904faa22f949c12a7538f447d54a6d561bbe7807d3c`.
Missing PHP properties and an outdated staged Kotlin source are therefore not
supported explanations. The initial showcase visibility issue remains open;
no additional production change or publication was made during this isolation.

### Runtime indicator diagnostics: contrast is not covered by pixel-difference tests

A temporary local Kotlin diagnostic measured the real overflowing group:
viewport/active Scroll=996x137px, content=1188x137px, max horizontal offset=192px,
indicator enabled=true, fading=false, persistent=true. The framework horizontal
thumb is a GradientDrawable with alpha=255, color `#84FFFFFF`, bounds
`Rect(0,126 - 835,137)`, scrollbar size=11px and zero bottom padding.
The same white semitransparent thumb was measured in the passing native fixture.
Thus a nonzero on/off pixel difference is NOT a sufficient visual gate: it can
pass for a low-contrast white indicator on a near-white canvas. This identifies
a contrast defect, but does not by itself explain the previously measured
completely unchanged footer pixels; do not claim the showcase issue resolved.

The regression additionally passed after rendering the group initially below
the viewport, capturing that window, then revealing it by vertical scrolling
without a horizontal gesture (1 test, 1.216s). Properties are applied in numeric
protocol order. Native indicator color/style is currently not exposed in the
PHP Scroll API; the next implementation should provide a reusable Native
capability and a theme-aware UI choice, with actual contrast verification.

Temporary runtime logging was removed from Native source after diagnostics.
Diagnostic showcase build times were 27s, 23s and 21s with the bounded shared
cache; each automatically cleaned 96.6 MiB of project build artifacts. Captures
in `/tmp/pam-ui-scroll-runtime-20260913` are diagnostic, not gallery assets.

### Persistent indicator fixed: delayed regression and real showcase contrast

The missing indicator was not just a color issue. Waiting 1.8 seconds before
revealing the native fixture reproduced the disappearance: the previously
passing renderer test failed after 2.596s. A fade callback scheduled before
persistence was configured still ran later. Native now configures a replacement
viewport before attaching it, replacing the viewport when persistence changes
to discard the old callback while retaining content, focus and offsets. The
same delayed test passed (2.929s); the final combined Scroll/renderer suite
passed `OK (12 tests)` in 7.253s. Initial renderer construction takes the final
orientation/persistence/appearance so it does not rebuild the viewport for each
initial property. Temporary logs were removed.

New reusable API: `Scroll::indicatorStyle(ScrollIndicatorStyle::Auto|Dark|Light)`
(wire values 1/2/3, property 465). Android uses themed system scrollbar drawables
without non-SDK access; UIKit maps the enum to native default/black/white styles.
Native templates accept `scrollIndicatorStyle="dark"`. UI grouped buttons choose
the style from background luminance and accept an explicit enum/integer override.
Native PHP SDK tests (including template mapping), 12 Rust protocol tests,
protocol parity and Android unit tests passed. UI PHPStan level 9 passed; the
114-component PHP matrix passed (32,832 style cases / 456 render cases), with
additional light/dark and appearance-override assertions.

The scoped real showcase audit now requires >=3:1 contrast in the reserved strip
for the overflowing Five options fixture. All six interaction cases passed at
font scales 1.0/2.0; contrasting strip pixel counts were 7,419 / 5,547. The normal
scale screenshot was visually inspected and the persistent track is visible.
Font scale restored to 1.0. Evidence: `/tmp/pam-ui-persistent-contrast-20260913`;
report SHA-256 `e9c72eacf5f0ed5e5a2ba459c467774aa2ca1d03ff848a16c0f9abe2802ca29b`;
installed APK SHA-256
`67cb95c44162c34023951d21467a6039f1a54fdf03cb8c1cd4b3fdfaa1962f9f`.
The final showcase build completed in 54s and cleaned 96.7 MiB automatically.

Verification limits: device tests here are emulator API 36, not Samsung or API
26. UIKit implementation and its new enum tests still require macOS CI. These
six cases do not approve every grouped-button variation or all 114 components.
Running full level-9 analysis specifically on Native TemplateRenderer exposed
89 diagnostics. A clean HEAD export and the changed file produce exactly the
same 89 identifier/message pairs; no suppressions were added. This is existing
Native static-analysis debt, not a clean full-SDK PHPStan result. Release remains
gated; no package, production docs or public gallery has been published here.

The test bootstrap now prepends its selected SDK autoloader: previously Composer
could silently win over explicit PAM_NATIVE_ROOT, testing an older SDK instead.

### Follow-up: Android tooling constructor and toggle semantics

Native CI `34788279996` passed Rust/PHP/protocol and Swift/UIKit contracts, but
failed Android lint: the constructor with optional configuration did not expose
a Java `(Context)` overload (`ViewConstructor`). Added `@JvmOverloads`, without
lint suppression; local `:app:lintDebug` passed in 38s. Fix pushed as Native
`c223515feef0603efbc4b0e74b1013473ff0ffcb`; fresh CI is required for this head.

Inspection of the previous showcase XML also found a separate UI defect:
the selected Board toggle reported selected=true but checked=false. Parent
selection propagation now sets both selected and checked for PBtnGroup and
PBtnToggle children. PHP assertions cover checked/unchecked accessibility
states, and the real interaction audit now requires checked == selected.
The PHP matrix and UI level-9 analysis passed for this change. Device validation
is pending: the active 114-route startup audit intentionally continues on the
unchanged prior APK, and cannot be used as evidence for this uninstalled change.

The prior-APK startup sweep completed: 114/114 cold launches at the authoritative
route top, p50=342ms, p95=419ms, max=556ms, no matched runtime errors. Report:
`/tmp/pam-ui-startup-114-persistent-20260913.json`, SHA-256
`fc9e637cc625a4c47ceb15df88e63dd9fbeb6599d04c131d024b2c6f38b2d9ea`.
Android lint ran concurrently during part of the sweep; these are gate timings,
not a controlled performance comparison. UI CI `34788293413` completed all nine
jobs successfully for UI 5e9d408 with Native 7470fbc. Native constructor-fix CI
`34788559359` is running for c223515. Neither result validates the uncommitted
checked-state adjustment in an installed APK.

The subsequent release-mode build (24s, 96.7 MiB cleaned) installed that
checked-state adjustment with Native c223515 on emulator API 36. All six
focused interaction cases passed at font scales 1.0/2.0, including the new
checked/selected agreement assertion. Evidence:
`/tmp/pam-ui-toggle-checked-20260913/report.json`. Inspection of its normal-font
launch capture also confirms that the selected Board check glyph is visible;
this does not establish a root cause for its absence in an older capture.
The expanded audit now covers all 14 fixture variations and records APK/script
hashes in each report, including failed/partial reports. That expanded batch
is pending; the six-case result is not whole-component approval.

Native CI 34788559359 subsequently passed Rust/PHP/protocol, Swift/UIKit and
Android build/unit/lint, but both API 26 and API 36 instrumentation jobs failed
the initial-window assertion in
`rendererKeepsPersistentHorizontalIndicatorVisibleBelowContent`. The later
software-draw and toggled-window assertions passed. Thus the earlier local
12-test success is not sufficient to approve persistence across environments.
Cross-platform accessibility evidence was skipped downstream. Investigation
must remain in Native; no weakened assertion or release is justified.

Expanded local batch completed: 28/28 cases (14 variations at font scales
1.0 and 2.0), including real target taps, checked/selected agreement, disabled
target rejection with unchanged selection, RTL ordering, reachable labels and
reserved scrollbar space. Five-options track contrast passed at both scales.
Normal launch and enlarged Full width captures were visually inspected; the
selected check glyphs and full target labels are visible after horizontal
scrolling. Font scale restored to 1.0. This does not cover every state transition
(for example second-tap deselection) or constitute full component approval.
Report `/tmp/pam-ui-toggle-14-20260913/report.json`, SHA-256
`504a9d50811063b7b593a84813b7447e7e1f30f6f166b1cb6698c9e4018490c1`;
APK SHA-256 `f1a5f0adbbc6f760b3bd6bd5d0203d296847a93d97131d9812871e2c023898c0`.

### CI-only indicator failure investigation

The installed local Native debug APK and instrumentation APK matched their
on-disk SHA-256 hashes (`cebde91b...` / `776f2149...`). The renderer class passed
35 tests in 16.366s, and the complete installed suite passed 81 tests in 27.382s.
These APKs predate the constructor-overload annotation: comparison with the
downloaded c223515 CI artifact confirmed the main four-argument constructor
bytecode is identical, but only CI had the generated overloads. Therefore these
81 tests must not be described as an exact c223515 artifact validation.

Rebuilt current Native plus failure-capture diagnostics in 3s, installed both
debug APKs, and the indicator test passed in 2.922s. No runtime fix or assertion
relaxation was made. Native c2548cc preserves initial/shown/hidden PNG frames on
assertion failure and pulls them before the CI emulator exits, retaining the
original test exit status. This follows the Kotlin cleanup and CI artifact
guidance; a fresh CI run must provide the missing environment-specific evidence.
The API 26/36 failures remain unresolved and release remains gated.

### Selection transition follow-up

Six targeted cases passed on the same showcase APK f1a5f0ad...: Single choice,
Single optional and Multiple choice at font scales 1.0/2.0, with two actual
taps per case. Assertions verify exact visible selection sets after both taps:
mandatory Month remains selected, optional Compact clears, and multiple Drive
toggles off without clearing Walk/Ride. Checked and selected remain consistent.
The normal-font cleared optional state was visually inspected. Font scale was
restored to 1.0. Report `/tmp/pam-ui-toggle-transitions-20260913/report.json`,
SHA-256 `c7b2b35559f08c81cdb92aef6320ee6f6470f36369efb5de1481ca17c29dc8d8`.

The audit now accepts repeatable `--variation` filters to avoid rerunning
unaffected fixtures. After the device batch, argument validation was tightened
with the same fixture table and requested scope added to future reports.
An invalid label exits 2 before any ADB call; this was checked without a device.
These runner-only follow-ups do not alter the tested selection implementation.
Native diagnostic CI 34789501902 and UI CI 34789550326 remain in progress;
neither is a release approval.

### Samsung return and artifact collection correction

Samsung SM-G973F was connected and unlocked. Built/installed the current arm64
release-mode showcase: Rust 9.69s, Gradle 4s, cold start 1267ms, automatic cleanup
88.1 MiB. Staged and source UI renderer hashes matched. APK SHA-256:
`7340ab84b247189c9f5b80665cdc171210b48a1d5479c1f6af24c8cff8d328f9`.
Five normal-font cases passed: mandatory/optional/multiple two-tap transitions,
Five options (7419 track pixels at >=3:1 contrast), and Disabled item. During RTL
the foreground became Samsung's launcher, so the audit exited 1 and restored
the original font scale 1.1. Logcat showed a HOME launch; the precise cause was
not established. RTL and all enlarged-font physical cases remain unapproved.
Report `/tmp/pam-ui-samsung-toggle-transitions-20260913/report.json`, SHA-256
`3487e25931de61dfa4c142d410d6900f7e334e9efbc4db16799fcd282cd54f5d`.

Native CI 34789501902 failed the initial-indicator assertion on both Android
versions. The shell trap could not find the app-owned evidence directory after
Gradle finished, so no PNGs were uploaded. Native a904d22 replaces that trap with
Gradle's `additionalTestOutputDir` collection (see the
[Android test runner implementation](https://android.googlesource.com/platform/tools/base/+/95fd82a56d2c27676620cd25ae6fec291c666977/android-test/android-test-junit-engine/src/main/java/com/android/tools/androidtest/testengine/AndroidDeviceDescriptor.kt)).
The fixture saves all three already-captured frames on success and failure,
without redrawing or relaxing assertions. A scoped local connected Gradle run
passed in 9s and exported initial/shown/hidden PNGs to
`android/app/build/outputs/connected_android_test_additional_output/`.
Initial PNG was inspected and its dark indicator is visible. This verifies the
collection mechanism locally, not the unresolved CI renderer behavior.

### Samsung interrupted cases resumed without rerunning passed cases

The device was unlocked at its launcher. RTL alone passed at 1.0/2.0 on the same
APK 7340ab84...; the enlarged after-capture was visually inspected and Day is
fully readable/selected with the expected physical RTL ordering. Report:
`/tmp/pam-ui-samsung-toggle-rtl-20260913/report.json`, SHA-256
`65a304cfccd120b4675fef9043437ce73d1ff05dc2a20a0fc8253f16b2abf5af`.

Added repeatable validated `--font-scale` selection to avoid repeating the five
passed normal-font cases. The five pending 2.0 cases then passed on Samsung:
mandatory, multiple, optional two-tap transitions, Five options overflow/contrast
(5547 contrasting pixels), and disabled-item rejection. Report:
`/tmp/pam-ui-samsung-toggle-large-20260913/report.json`, SHA-256
`56093a47b76e964092eea36aa543ec8636043e4fd100f6f8b65581ac1a660d54`.
Original font scale 1.1 was restored. This completes these six
selected variations at both scales across the three reports, not all 14
variations or the entire component. Invalid scale 0 was rejected with exit 2
before device access. Native CI 34790093998 remains pending Android instrumentation.

Inspection also identified a weakness in the Native fixture's capture check:
resolveHostBackground inherits the gray descendant color, so checking that same
gray at one point cannot independently prove the child has rendered. Preserve
the current CI evidence before changing the fixture; distinguish initial-frame
synchronization from actual persistent-scrollbar failure.

### First CI window evidence obtained

API 26 of Native run 34790093998 failed and exported all three PNGs via Gradle's
additional-test-output collection. Downloaded to
`/tmp/pam-indicator-frames-ci26.mc4g0b`. Visual inspection confirms no indicator
in initial.png and a dark indicator after toggling visibility in shown.png.
The shared gray canvas still prevents visual confirmation of child readiness.
Native 69ee5cc gives the fixture an explicit white Screen background and asserts
white just outside the gray child before checking that child's pixel. The
scoped connected Android test passed locally in 9s, without changing runtime
code or weakening the indicator assertions. This commit is being submitted to
CI to distinguish an initial-frame problem from an indicator-only problem.

### Remaining toggle variations and separate segmented-item fix

Samsung completed the remaining eight PBtnToggle variations at font scales
1.0/2.0: Full width, Leading icons, Compact density, Two options, Disabled group,
Long labels, Tile and Success color. All 16 scoped cases passed on APK 7340ab84...
and original font 1.1 was restored. Report:
`/tmp/pam-ui-samsung-toggle-remaining-20260913/report.json`, SHA-256
`42b44470c18fcfc89c4d84acb4dbcace121ce8da2c40fc4d7fcc4f80aa7f712e`.
Combined with previous physical reports this covers the 14 fixture variations
at both scales. It does not establish every interaction, dark-mode, performance
or accessibility-service requirement, nor fix the separate CI indicator failure.

Code review found a separate PSegmentedButton defect: generated items ignored
their individual disabled flag. Added regression assertions for item/group
precedence, Enabled, opacity and absence of Press handlers; the test failed
before the UI fix and the 114-component PHP matrix passed after it. Generated
controls now honor that flag without changing Native code. The showcase has a
Disabled item fixture and preserves fixture-provided items. This change is not
in the tested Samsung APK and still needs device validation. Initial PHPStan
flagged passing an untyped item map to flag(); the fix extracts only the disabled
property into a string-keyed map, without suppressions or widened types.
