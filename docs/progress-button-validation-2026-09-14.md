# Progress Button: scoped validation

Candidate APK SHA-256:
`0dd3f8f75073ed4482d917e44a2373cf7f16294237ad5284c3f1eb088d9dccb1`.

UI-only corrections: queued zero is not busy; finite percentages are clamped to
0–100 and exposed through the existing native accessibility range properties;
the determinate track uses the resolved button foreground, preserving variant
colors instead of assuming a white foreground. No new native primitive required.

The render matrix passes, including boundary percentages and track geometry/color
for Text, Outlined, Tonal and Elevated. PHPStan level 9 passes for the configured
UI/showcase/test scope. These are not visual or device approvals.

Android API 36 emulator, fonts 1.0 and 2.0: taps advance the queued sample through
25, 50, 75, 100 and reset; the indeterminate sample remains disabled after a tap.
Normal and enlarged reset captures were inspected: button text remains readable
and determinate tracks are visible. The enlarged page scrolls to remaining content.

Local evidence: `/tmp/pam-ui-progress-button-labels-20260914/report.json` and PNG/XML
siblings. Script: `tools/audit-progress-button-android.py`. The first attempt at
`/tmp/pam-ui-progress-button-20260914` failed because the showcase supplies a generic
accessible label rather than the displayed button text; the harness was corrected
to locate the native button by its descendant text, without rebuilding the APK.

Remaining: replace the generic showcase accessible name, display and inspect the
light variants on device, Samsung/iOS coverage, assistive-technology announcements,
motion/performance measurements and release-grade media. This is not full approval
and does not update the formal component approval inventory.
