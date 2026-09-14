# Partial table selection

The select-all header now distinguishes no selection, partial selection and all
selectable rows selected. Partial selection uses the existing RemoveIcon vector
as a contrasting dash and `AccessibilityCheckedState::Mixed`; full selection
uses CheckIcon and Checked. Disabled rows are excluded from the selectable set.
The shared table cell composition owns this visual behavior, not PAM Native.

PHP regressions inspect all three header states and marker presence. The full
UI render matrix and targeted renderer PHPStan pass.

Android 16/API 36 emulator report `/tmp/pam-table-mixed-20260914.json` passed
select/deselect in 21.907 seconds, candidate
`0d9f02c3607226062a91de0f5382e7a938ab21437d8c7a4eb9520098b5e3dbeb`.
The checked/unchecked screenshots were inspected: selecting one row shows the
contrasting centered dash in the header; clearing that row restores an empty
header. This is not a device test of tapping the header to select all rows.
Screen-reader announcement, full header interaction and iOS remain pending.

## Bulk header follow-up: original failure

The new bulk-selection test fails in `/tmp/pam-table-bulk-20260914.json` and
`/tmp/pam-table-bulk-settled-20260914.json`. After select-all, the footer reports
six selected rows and a direct screenshot `/tmp/pam-table-bulk-observed.png`
visibly shows all six checked. However, UiAutomator exposes only rows 1–5: row 6
is absent even after two additional hierarchy observations without another tap.
This is not yet proven to be a transient snapshot issue. Keep the bulk case
failed and investigate native accessibility/virtualization visibility. Do not
weaken the six-row assertion or claim complete select-all validation. Clearing
through the header has not yet been reached by this test.

## Native correction and completed bulk interaction

The cause was PAM Native's whole-row visibility requirement in `PamRecyclerList`:
a partially clipped row lost all accessibility descendants. The runtime now
hides only fully offscreen holders and restores their original mode on detach.
See sibling `pam-native/docs/virtual-row-accessibility-2026-09-14.md`.

`/tmp/pam-grid-accessible-bulk-20260914.json` passes on Android 16/API 36,
candidate `222f2dfd46e0b618ae1e9053db741a2cd2e8bc8dfeee276fb3ab829c28e67aca`.
One 31.451-second interaction round verifies individual select/deselect and
header select-all/clear-all, including all six row checkbox states and the
controlled summary. No extra hierarchy retry was needed; the speculative retry
loop was removed. The all-selected screenshot was inspected: all six checkmarks,
the header marker and summary are visible and aligned.

Historical failures above are retained, not counted as passes. Full screen-reader
navigation, theme/large-text coverage and iOS are still separate open scopes.
