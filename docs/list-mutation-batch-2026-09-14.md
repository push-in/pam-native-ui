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
An existing showcase caption still suggests swiping on blocked specimens; keep
that copy correction pending rather than treating the preview as final media.

The first reorder audit stopped before interaction because its diagnostic name
contained spaces; the script now uses hyphenated names. No component failure was
inferred. The corrected rerun passed in
`/tmp/pam-list-reorder-locks-route-20260914/report.json` on the same APK:
long-press drags and both possible move buttons preserved the exact two-item
order for readonly and loading specimens. The readonly retained-order capture
was inspected. No second build was required.

No full component approval, iOS verification of this change or publication is
claimed. Device reports remain diagnostic evidence, not public showcase media.
