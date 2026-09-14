# Swipe Actions — scoped Samsung validation

APK `93b007191fac0baf8eb2e4ca67465b1237809addeccdbb3b285a7264bdfe94e6`,
Samsung SM-G973F:
`/tmp/pam-ui-swipe-samsung-20260914/report.json` passes the existing
`tools/audit-swipe-actions-android.py` flow.

Actual interactions covered:

- Left-to-right swipe invokes Archive and updates visible feedback.
- Visible Delete and Archive buttons invoke their corresponding actions.
- Swiping the disabled item leaves action feedback unchanged.

The disabled-result capture was inspected: labels and fallback buttons are
readable, and disabled controls have reduced emphasis. No component source
change or new build was necessary for this check. No real message or file is
deleted: these are controlled showcase callbacks.

Follow-up on the same APK:
`/tmp/pam-ui-swipe-reverse-samsung-20260914/report.json` passes a right-to-left
Delete swipe and verifies that a ten-physical-pixel drag leaves feedback unchanged.
Use `--reverse-only` to reproduce these checks without repeating the button flow.
This small-motion check is not a full gesture-cancellation lifecycle test.

Not covered: RTL semantics, partial-drag cancellation,
velocity thresholds, TalkBack, performance/frame timing, large-font/landscape
layout or iOS. Screenshots do not prove smooth animation. This does not grant
full component approval or authorize release/publication of the library.
