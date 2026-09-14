# Anchored overlays — scoped Samsung batch

## Anchor collision correction

PAM Native's new Android plugin API selects an origin using clamped anchor
intersection and viewport overflow. The UI host supplies requested/opposite and
perpendicular alternatives, respecting shouldFlip, explicit overlap and Center.
This keeps reusable geometry in Native and visual/candidate policy in UI.

Final APK `e171ef62098dea8641d8309685f3391ea1681f2ef0accfb20f045fa6372928ff`
passes Top, Left and Right in
`/tmp/pam-popover-collision-final-20260914/report.json`: internal actions,
dismiss/reopen retention and newly asserted zero intersection between the
overlay surface and its own trigger. Viewed Left/Right reopened captures confirm
vertical fallback with a visible gap instead of covering the trigger edge.
Native's four targeted JVM regressions pass. Final Android build took 17 seconds
and cleaned 96.8 MiB of old generated artifacts.

The first collision report (`/tmp/pam-popover-collision-20260914/report.json`,
APK `5005a12b324aa18631fc63bb7d4ddabea1666b36aafe14fc1781fd7254d69a31`)
retains lateral failures. Staging contained the new Native API but the old UI
Kotlin host. The UI host was copied and matching source/staging SHA-256 verified
before the final build. This was not evidence against the new host algorithm.

The observed lateral collision is resolved in these Android examples. Full
Menu/Tooltip shared-host regression, arrows, explicit overlap/flip-disabled
device cases, large fonts, keyboard, RTL, motion and iOS remain open. This is not
release approval; UI publication depends on the new Native plugin API release.

## Interactive Popover follow-up

Remaining-placement run: `/tmp/pam-popover-placements-20260914/report.json`
passes Top, Left and Right on the same APK below, without rebuilding or rerunning
Bottom. Each scenario invokes the internal action twice, dismisses outside,
reopens and verifies that acknowledgement count 2 is retained. The dedicated
runner collects failures across the batch instead of stopping on the first one.
Python syntax and diff checks pass.

Top/Left reopened and Right acknowledged screenshots were viewed. The content
fits inside the viewport with readable action/feedback and internal padding.
Left resolves to the right side in this narrow layout; both lateral examples
partially overlap the trigger's trailing edge. This run proves interaction and
retention for the requested configurations, not exact placement geometry or a
collision-free positioning policy. Lateral placement/anchor overlap remains a
separate visual issue to assess; do not publish these as final polished media.

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
