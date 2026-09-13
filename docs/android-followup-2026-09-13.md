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
