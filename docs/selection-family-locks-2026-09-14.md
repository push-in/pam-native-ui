# Selection family locks

Pagination, Segmented Button and Filter Bar now explicitly suppress generated
Press handlers and disable their child controls for readonly/readOnly/isReadOnly.
Current selection and normal selected colors are preserved. Readonly Filter Bar
does not expose Clear. Segmented Button and Filter Bar item definitions also
honor isDisabled, with explicit disabled=false taking precedence.

Regression cases in tests/material-matrix.php cover all three aliases across
the three components, selection retention, absence of Clear, and per-item alias
precedence. Matrix and focused PHPStan level 9 passed. These are code-level
assertions, not device approval.
No new native primitive or CLI capability is needed for this composition fix.

Pending: showcase readonly examples and actual blocked-action checks. The new
candidate includes these PHP changes, but readonly device checks remain open.

Update: those examples and scoped checks are now implemented in
`tools/audit-selection-readonly-android.py`. On Samsung APK
`5043ba37ac0f6f06d97748a212e7be89c8974d8a0b90ba918ed5d60b9a855d75`,
all three cases passed: 3 pagination taps, 3 segment taps and 2 filter taps,
checking disabled semantics and unchanged selected/checked state after every
tap. Readonly filters expose exactly two controls, without Clear. Evidence:
`/tmp/pam-selection-readonly-20260914/report.json`. Segmented and filter captures
were visually inspected for legibility/alignment. This does not establish full
component approval or cover every alias on-device (aliases are covered in matrix).

Separate defect found during review: pagination with totalVisible=1 renders
only its selected page and no navigation action. Do not consider that variant
usable until page navigation remains reachable for this configuration.

## Compact pagination navigation correction

One/two numbered-page windows now include Previous/Next controls when the total
page count exceeds the window. The requested numbered-page count is unchanged.
Boundary controls are disabled; readonly/disabled prevent navigation callbacks.
Matrix regressions cover windows 1/2, first/middle/last pages, readonly and actual
callback values. Matrix and focused PHPStan level 9 passed.

Samsung interaction sequence 1 → 2 → 3 → 2 passed, including first/last disabled
controls, with actual taps. Evidence: `/tmp/pam-pagination-compact-final-20260914`.
APK SHA-256: `85a6a0826ee209bf43ec643efcfc2a79f686006a32c0bf9a7f6e9e3e484c582a`.
The last-page capture was visually inspected: arrows, selected page and numbered
rows fit without overlap. The initial audit attempt failed before interaction
because its capture filename contained spaces; no rebuild was needed for rerun.
This is not full pagination approval: RTL, larger fonts, landscape and iOS remain.
