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
