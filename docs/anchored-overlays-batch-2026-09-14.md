# Anchored overlays — scoped Samsung batch

## Interactive Popover follow-up

The showcase now includes an existing text-variant UI button and a controlled
acknowledgement counter inside each Popover, not just static copy. The integrated
audit opens the overlay, invokes the action, requires the counter to change from
0 to 1 while the overlay remains open, then dismisses outside and checks that
overlay content is no longer accessible.

`/tmp/pam-popover-action-20260914.json` passes on emulator-5554/API 36, font 1.0,
APK `333d2a830c7e53d502fd810c5d961c7b8a2ec8a3c5ea2cbe1abdff5c0f5f932c`.
The acknowledged screenshot was inspected: supporting text, text button and
counter fit inside the surface with bottom padding. This verifies one anchored
placement and internal action, not every placement, keyboard focus, reopening,
large fonts, TalkBack, motion or iOS. No native workaround or primitive was added.
The matrix, targeted showcase PHPStan and Python syntax checks pass. One build
took 10 seconds and cleaned 96.8 MiB of previous development artifacts.

## Historical Samsung sizing batch

APK: `ca9d10ec355cf24b51811232880cab0d0596e81ab7e837af21c76ec7579d2015`.
Report: `/tmp/pam-overlays-batch-20260914.json`.
Captures/XML: `/tmp/pam-overlays-batch-20260914`.

One interaction pass each on SM-G973F, Android 12/API 31, font scale 1.1:

- Menu: opened, selected an enabled action, displayed selection feedback and
  removed menu actions from the hierarchy.
- Popover: opened with Native overlay content; outside dismissal removed that
  content from accessibility. The audit now explicitly asserts both states.
- Speed Dial: opened, selected an enabled action and displayed completion feedback.

All three scoped cases passed. This does not approve all variants, motion,
accessibility, large text, RTL, landscape or iOS. Animations were disabled during
the deterministic run; it is not a performance measurement.

Visual review found a remaining Popover defect: supporting text ends too close
to the bottom curved edge at font scale 1.1. Investigate content sizing/padding
before considering this component visually accepted. The menu and Speed Dial
captures show readable actions; they are not evidence for their other variants.

## Padding correction

The anchored host measured content using bare AT_MOST specs, ignoring the
renderer-authored layout dimensions. FrameLayout consequently shrank to its
children's extents and lost trailing/bottom padding already encoded by the
renderer. Measurement now respects those dimensions, capped to the visible
viewport, while retaining normal wrap/match-parent measurement semantics.

Regression `anchoredContentPreservesRendererDimensionsIncludingTrailingPadding`
passed on Android API 36. Samsung APK
`df3bb8d4f8435eadbdafeb4a4c831cc91d6f6c85dfb8adbbbb717d27f49c5e32`
passed the three interaction cases again; report and screenshots are at
`/tmp/pam-overlays-sizing-20260914.json` and
`/tmp/pam-overlays-sizing-20260914`. Visual inspection of the Popover capture
confirmed restored bottom/right breathing room at font scale 1.1. The fix is in
PAM Native UI's existing anchored-overlay host; no new PAM Native primitive or
CLI behavior was needed. The broader coverage limitations above still apply.

Tooltip on the same APK also passed long-press display and release dismissal;
`/tmp/pam-tooltip-sizing-20260914.json` records the scoped run. The open capture
was inspected and its text fits inside the tooltip. This checks the shared
measurement change, not all tooltip placements or accessibility behavior.

Batch checks: PAM Native UI `testDebugUnitTest`, PAM Native
`:app:testDebugUnitTest`, and PHP `tests/material-matrix.php` all passed. The
matrix reports 114 components, 32,832 style cases and 456 render cases; these
counts are automated coverage, not full device approvals.
