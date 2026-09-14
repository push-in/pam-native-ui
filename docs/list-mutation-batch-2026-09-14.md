# List mutation guards

Reorderable List and Swipe Actions previously guarded `disabled` but still
attached mutating handlers when `readonly` or `loading` was set. Both now use
the shared UI mutation guard, including `isDisabled`, `readOnly`, `isReadOnly`
and `isLoading`. Explicit primary-property false values retain alias precedence.

- Reorderable List disables dragging, drop acceptance and move buttons while
  blocked. Readonly content retains normal text emphasis; mutation controls do
  not remain actionable. Existing protected-row/boundary policy is unchanged.
- Swipe Actions disables gesture recognition, native translation, completion
  callbacks and both visible action buttons while blocked.
- Showcase examples expose readonly and loading states for both components.

These are UI interaction policies using existing native primitives; PAM and
PAM Native need no implementation change for this batch. The UI/UX skill's
readonly distinction and equivalent gesture/button behavior guided the fix.

## Checks

The material matrix passed (114 components, 32,832 style cases, 456 renders),
including the new isolated `tests/material-list-mutation.php`: seven aliases,
true/false values, both components, handler presence and native enable flags.
Focused PHPStan level 9 passed for renderer, regression file and showcase route.
This repository uses its existing PHP assertion runner, not PHPUnit coverage;
no coverage percentage is claimed.

One optimized emulator build took 10 seconds and cleaned 96.8 MiB of regenerable
artifacts. Staged renderer, route and UI Kotlin hashes were checked before build.
APK SHA-256: `c42f096ee2e1751c50eb832105bd704d416e61964c9be843559b1cdc8d56a476`.

Swipe Actions passed on API 36 emulator-5554 in
`/tmp/pam-list-swipe-locks-20260914/report.json`: active swipe and both buttons,
then actual swipe/taps against disabled, readonly and loading specimens. The
readonly capture was inspected: content is readable and action buttons muted.
That APK's showcase caption still suggests swiping on blocked specimens. The
subsequent source correction supplies state-specific disabled, readonly and
synchronizing instructions; the enabled hint mentions both swipe and buttons.
Eleven isolated PHP regression cases cover aliases and explicit-false precedence.
This caption-only change is not in the above APK; capture wrapping and final
media remain pending for the next integrated showcase build.

The first reorder audit stopped before interaction because its diagnostic name
contained spaces; the script now uses hyphenated names. No component failure was
inferred. The corrected rerun passed in
`/tmp/pam-list-reorder-locks-route-20260914/report.json` on the same APK:
long-press drags and both possible move buttons preserved the exact two-item
order for readonly and loading specimens. The readonly retained-order capture
was inspected. No second build was required.

No full component approval, iOS verification of this change or publication is
claimed. Device reports remain diagnostic evidence, not public showcase media.

## Vector controls and state instructions follow-up

Reorderable List now uses the existing GripVertical, ArrowUp and ArrowDown vector
icons instead of a font-dependent grip and visible English Up/Down text. The
32 dp grip lane, 48 dp action hit areas, semantic colors and accessible action
labels are retained. Icons use the existing decorative accessibility treatment.
This follows the UI/UX consistency guidance without introducing native code.

The material matrix and focused level-9 PHPStan passed. One 10-second optimized
build includes these icons and the state-specific Swipe Actions instructions;
cleanup removed 96.8 MiB. APK:
`0b19ba4315aa19c2a41299a1938ef0b56f33c27a5ce8f5e975b899f667adee55`.
`/tmp/pam-reorder-vectors-20260914/report.json` passed real drag reordering,
move-down/move-up button callbacks and first-item boundary rejection. The
buttons-result capture was inspected: grips, labels and arrows align, including
muted boundary controls. Full variant/theme/a11y approval is still separate.

On that same APK, `/tmp/pam-swipe-state-copy-20260914/report.json` also passed
enabled swipe/buttons and rejection of disabled/readonly/loading actions. The
synchronizing capture was inspected for the new state-specific instructions.
This supersedes the earlier caption-only source checkpoint for this Android
candidate; it is not published media or full device/platform approval.
