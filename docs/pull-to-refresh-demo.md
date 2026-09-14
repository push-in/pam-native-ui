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
