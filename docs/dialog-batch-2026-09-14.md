# Dialog batch — Samsung, 2026-09-14

This is partial evidence, not component or release approval.

Reused the installed showcase without rebuilding. Executed
`tools/audit-dialog-android.py` on SM-G973F; artifacts are in
`/tmp/pam-dialog-batch-20260914`.

Passed before the first failure: default Keep/Discard actions, Back dismissal,
outside dismissal, persistent Back/outside rejection and explicit dismissal,
and no-scrim backdrop comparison. The default screenshot was visually inspected:
title, supporting text and actions fit inside the surface without overlap.

Fullscreen failed: the screenshot visibly contains Keep draft, but the captured
accessibility hierarchy omits that action entirely while exposing Discard draft.
Do not replace this semantic assertion with a coordinate-only tap. Investigate
native accessibility visibility/bounds and reproduce before assigning the cause.
The captured dialog bounds also exclude system bars; the existing full-screen
viewport assertion has not yet run and needs comparison with the intended native
window contract, not an automatic relaxation.

Compact/large widths, enlarged text and landscape were not reached.

Fixed the audit's font-scale snapshot ordering: prepare now records the original
scale before the test sets 1.0. After the failed run, ADB confirmed restoration
to 1.1. Successful reports now explicitly declare `fullApproval: false`.

Next: isolate the fullscreen accessibility failure and finish only the remaining
profiles; do not rerun the already passing profiles on unchanged code by default.

## Fullscreen root cause and targeted retest

Android's accessibility dumper confirmed a zero-height button, not just a missing
label. A subsequent opening omitted Discard instead of Keep. PAM Native mixed a
flattened Row's axis with its materialized Column ancestor's measured viewport;
the viewport-height reduction was incorrectly deducted from button heights.
The renderer now uses the materialized host's axis for those measured dimensions.

`flattenedRowButtonsKeepHeightWhenColumnViewportShrinks` passed on API 36.
Installed Samsung APK SHA-256:
`ca9d10ec355cf24b51811232880cab0d0596e81ab7e837af21c76ec7579d2015`.
Targeted Samsung retest exposed both actions with nonzero bounds at font scales
1.0 and 1.1, clicked Keep at 1.0 and Discard at 1.1, and verified actual dismissal
after each. Evidence: `/tmp/pam-dialog-fullscreen-fixed-20260914`.
This resolves the reproduced collapsed-button case, not the remaining full-screen
system-bar contract, larger font/landscape profiles or overall release gates.

## Remaining profiles on the corrected APK

`/tmp/pam-dialog-remaining-20260914` records passes for compact (320dp), large
(wider than compact, inside viewport) and default at 130% font scale. Both action
bounds were nonzero and inside the surface; Discard was tapped and dismissal
verified in each profile. The 130% screenshot was visually inspected: title,
supporting text and both actions remain readable without overlap.

Landscape was **not exercised**: the surface remained at rotation 0 after the
request for rotation 1, and the precondition failed. Settings were restored by
the audit's finally block. This is neither a landscape pass nor evidence of a
dialog landscape layout defect. Recheck orientation prerequisites separately.
