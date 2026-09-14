# Search family validation

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
