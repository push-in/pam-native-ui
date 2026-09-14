# Controlled refresh demonstration

The showcase previously set `refreshing` to true without an end transition.
It now provides an explicit Complete refresh action, enabled while refreshing,
which sets the controlled value back to false. Pulling again starts another cycle.
The initially refreshing variation can also be completed.

This is deliberately a controlled state demonstration, not an asynchronous
network request. No blocking sleep or invented timer API is used. In an app,
complete the refreshing state when the actual request finishes, including errors.

The change belongs only to the UI showcase. The native RefreshControl API remains
unchanged. PHP render checks pass.

Samsung SM-G973F, APK
`0e1c3355ee49cd9db267394da14171f55c6fab7eee7083f9923c3c61fa98459b`:
`/tmp/pam-ui-refresh-cycle-20260914/report.json` passes two real pull gestures,
each followed by tapping Complete refresh and observing the ready state and
disabled completion button. Reproduce with `tools/audit-refresh-cycle-android.py`.
This verifies the controlled demo cycle, not network requests, refresh-error
handling, performance or full cross-platform component approval.

Visual follow-up: the decorative rounded background around Today was removed.
The title and description now align with the page gutter, with 16dp vertical
padding and the same refresh gesture area. This follows the showcase rule against
decorative preview cards. The earlier APK screenshots predate this visual edit;
the render matrix passes.

Updated Samsung evidence: APK
`09d27d213b6367f0eee65cddf0cde3f9b0f1a171449186ba655673f5092ebf6b`,
`/tmp/pam-ui-refresh-canvas-20260914/report.json` passes both refresh cycles.
The ready-again capture was inspected: title and description share the page's
left gutter and the decorative surface is absent. The tall gesture area remains
intentional; this is not a full visual approval across device sizes.
