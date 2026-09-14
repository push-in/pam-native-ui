# Search family validation

## Command Palette integrated search contract

The integrated audit no longer accepts only opening/closing the palette. It now
focuses the unique search editor, checks focus survives keyboard appearance,
types Open, requires exactly the Open file result, selects it, checks dismissal
and verifies the selected value in the Quick actions trigger specifically.

`/tmp/pam-command-integrated-search-20260914.json` passes on emulator-5554/API 36
in 21.328 seconds using existing candidate
`ad9367fe9acecb49a648d9639eabaec35f6571795ee2701ee9e6f3958ed7b15a`.
The filtered screenshot was inspected: query and result are visible above the
keyboard, with the search icon/editor aligned. No new build was needed.
This closes the integrated-suite coverage gap recorded in the navigation/search
batch, not empty results, disabled commands, rotation mid-edit, performance,
TalkBack, iOS or full component approval. Historical open/close-only reports
remain scoped to their original assertions.

## Custom value collision: Android event path

`/tmp/pam-tag-collision-final-20260914.json` passes the Tag Input flow on the
Android emulator: search for `Android`, invoke `Use Android`, verify the protected
`Managed platform` option remains checked, dismiss and verify the trigger still
contains that label. The fixture deliberately uses different display/value text
to exercise the custom action rather than the disabled option's touch handler.

Two preceding attempts stopped in the harness before the custom press: duplicate
semantic Spinner nodes required selecting the clickable one, and Android removes
resource quoting from the action label. Their failing reports remain at
`/tmp/pam-tag-collision-20260914.json` and
`/tmp/pam-tag-collision-retry-20260914.json`. No product rebuild was needed for
these locator corrections. This verifies one protected custom scalar path,
not all custom entry, platforms or full component approval.

## Selection-lock Android batch

Candidate `576ec564bb1ebbb7381f3e269d3d6e08a7d025f5baa6197df1cc9750e83f6eac`
passed the Tag Input and Multi Select scenarios on emulator-5554, Android 16/API
36, in `/tmp/pam-selection-locks-20260914.json`. Each opens the field, verifies a
selected `isDisabled` option rejects a tap, adds a `disabled: "false"` option,
closes/reopens, removes it and confirms restoration of the original selection.
Durations: 23.857 and 23.472 seconds. One build took 10 seconds; automatic cleanup
removed 96.8 MiB of generated artifacts.

Added-state screenshots were inspected for both routes: selected chips remain
inside their fields and visible long-label examples wrap onto another row.
This does not validate custom-value collision entry, all five selector facades,
large text, dark theme, RTL, screen readers or iOS. Those scopes remain open;
the PHP custom-value guards must not be presented as device-tested.

## Custom-value protection

Tag Input and Combobox now reject scalar custom-value events matching disabled
options, using the same scalar identity comparison as selection. Tag Input array
replacement also rejects new disabled values while retaining already-selected
protected values. Valid custom values remain accepted. PHP regressions exercise
integer/string identities, valid additions and protected collection replacement.
Device reproduction/coverage for this event path is still pending.

## Shared option lock normalization

Select, Autocomplete, Combobox, Tag Input and Multi Select now normalize individual
option `disabled` values with the common boolean parser and accept `isDisabled`.
Explicit `disabled: false` wins over `isDisabled: true`; the string `"false"`
does not accidentally disable an option through PHP's boolean cast.
Fifteen PHP cases verify option enabled state and press-handler presence across
the five public facades. Matrix and targeted renderer PHPStan pass. Device
coverage for these option-lock cases remains pending; existing APK results do
not cover this later normalization change.

## Native editor contract follow-up

Search Bar forwards all eight native editor events, including end-editing,
selection, content-size and key-press events, preserving handler identity and
payload. `editable: false` also disables the clear action, matching the editor's
own lock. PHP regressions cover the four previously dropped events and this
additional lock. These follow-up contracts have not yet received device-event
coverage; the earlier clear-action APK predates these changes.

## Opt-in controlled clear action

Search Bar now accepts `clearable: true` and an optional localized `clearLabel`
(default `Clear search`). The trailing action reserves 48×48 layout space even
for an empty query, preventing editor width changes. Pressing it emits `onChange`
with an empty string; the consumer must update `modelValue`, as with text entry.
Without a change handler, with an empty query, or under any supported editing
lock, the action is disabled and has no press handler. Default anatomy is unchanged.

PHP regressions cover empty/nonempty queries, the five lock aliases, translated
label, stable width and the empty change payload. The render matrix passes.
The new Android scenario passed on emulator-5554 (Android 16/API 36), candidate
`f91f06bafeecf23fca02838419f64797aebb1094460b4ec3a6643a7368d50e98`.
Report: `/tmp/pam-search-clear-20260914.json` (17.831 seconds, one pass).
It clears the populated controlled instance, checks identical editor/action
bounds and a disabled empty action, then checks native text entry. The cleared
screenshot was inspected: the trailing icon is aligned and the hint is restored.
The build took 10 seconds and its cleanup removed 100.5 MiB of generated artifacts.

Keyboard submit forwarding is covered by PHP, not a new device IME assertion.
Locked-action behavior is covered by PHP; the new read-only/disabled showcase
examples still need device interaction coverage. Large text, RTL, dark theme,
iOS and full component approval remain open. No publication is implied.

PHP regression checks now include Command Palette in the shared selection-lock
matrix (readonly/readOnly/isReadOnly/disabled/isDisabled) and verify that Search
Bar preserves its query and accessible label with editing disabled for each alias.
The render matrix passes. No new native implementation is needed for these locks.

Samsung SM-G973F, APK
`817341f53fc2365a060fb060cd769809f01a39ffb9411df5d9cfc45f0392006d`:

- `/tmp/pam-ui-search-samsung-20260914/report.json`: long-query editing passes;
  the input remains within horizontal bounds and reflects typed XYZ.
- `/tmp/pam-ui-command-samsung-20260914/report.json`: FAILED, not approved.
  `tools/audit-command-search-android.py` reproduces opening the palette, focusing
  its search editor and typing Open. After the keyboard appears, the modal's
  content disappears from the visible/accessibility hierarchy. A second dump
  confirms persistence; keyboard-panel-missing.png visibly shows only scrim and
  keyboard, without the command sheet. Filtering and selection remain unverified.

Investigate shared modal viewport handling in PAM Native (`PamModalHost.kt` and
`PamRenderer.kt`) and UI modal composition before assigning the final fix owner.
Observed dialog content height changes from 1930px to 1177px when the IME opens.
Do not bypass this by suppressing the keyboard or replacing native search with
a screenshot/visual-only example. The existing passing PHP guards cannot detect
this runtime layout failure. No publication or complete component approval.

Follow-up experiment: limiting the UI host's keyboard translation to the actual
visible-frame overlap passed pure Kotlin tests but did not resolve the Samsung
failure. APK `d156f1bb86365559efd7059487b33e0e22b0bc97026eb264f90369423e9bf426`
and `/tmp/pam-ui-command-keyboard-fix-20260914/report.json` reproduce the same
missing children. The experimental source change and its isolated tests were
removed; do not treat this device APK as the current worktree or approved build.
The staging copy also contains that experiment until resynchronized. Next inspect
actual child bounds/visibility across modal resize and native selection filtering,
not just IME arithmetic. The root cause is still unproven.

## Native viewport correction

Geometry diagnostics subsequently showed a 2280px selection host inside a 1177px
dialog viewport, with content starting at y=1513 and keyboard inset zero. The
renderer was overwriting the full-window modal child's MATCH_PARENT sizing with
the activity engine frame. PAM Native now preserves window-owned dimensions for
those children; centered dialogs and native sheet presentations are excluded.
Temporary UI geometry logging was removed.

Samsung APK `e9583f894901d0432135d2141e4b81e143b5c77adeebf377de6df897f4248b06`:
`/tmp/pam-ui-command-native-viewport-20260914/report.json` PASSES keyboard focus,
filtering Open to Open file, selecting it, dismissal and updated trigger value.
The filtered capture was inspected: search and result remain above the keyboard.
This resolves the reproduced case; broader modal regressions, iOS and complete
component approval remain pending. No release was published.

## Landscape keyboard follow-up

The Samsung landscape check exposed IME fullscreen extraction covering the
results. The UI-owned selection search editor now requests the Search action
and disables fullscreen/extract UI, matching its inline-search purpose.
`tools/audit-command-search-android.py --landscape` verifies physical orientation
and the same focus/filter/select/dismiss flow, restoring settings afterward.

APK `6a0f3e5e252b14ac14f26259928343cbc95eb22323612266b50d51993c6fbd0d`:
`/tmp/pam-ui-command-landscape-fixed-20260914/report.json` passes on SM-G973F.
The earlier `/tmp/pam-ui-command-landscape-20260914` attempt is failing evidence,
not approval. This covers a launch in landscape, not rotation during editing.
