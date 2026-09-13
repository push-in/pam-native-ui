# Android follow-up — 2026-09-13

## Build and scope

- UI source: `55c6fcb`; Native source: `a737904` (1.0.27).
- Optimized x86_64 build installed on API 36 emulator, 1080×2400, density 420.
- Installed APK SHA-256: `d19050ad5334e85c6a8b6d4251e7d0271da4adeae4488d8f44eb6fed5be1f4a2`.
- This is one-pass showcase coverage, not the two-pass physical-device gate,
  a performance benchmark, or approval of every component variant.

## Completed checks

Currency Field, Masked Field and Text Field passed actual text-entry checks.
The rounded focus outline was also inspected in the captured Currency Field
and Text Field screens.

The remaining candidate-component round completed with no failed checks:

| Verification | Components |
| --- | --- |
| Open and close | Date Range Picker, Time Range Picker, Popover |
| Selection | Segmented Button, Filter Bar, Data Grid, Tree Select, Chart |
| Scroll | Virtual List, Section List |
| Gesture | Reorderable List, Swipe Actions, Pull to Refresh |
| Press | Progress Button |
| Static composition | Responsive Grid, Result State |

The Reorderable List after-state was visually inspected: updated item order,
aligned handles and labels, and no decorative card nesting.

Local raw reports and captures are `/tmp/pam-ui-fields-fixed-20260913.json`
and `/tmp/pam-ui-remaining-16-20260913.json`, with matching directories without
the `.json` suffix. These temporary paths are not published documentation assets.

A local diagnostic archive preserves both reports, all captures and the exact
application lockfile at
`docs/assets/android/audit/2026-09-13/emulator-followup.tar.gz` (ignored by Git).
Archive SHA-256:
`ba6174a08b501440b1911ad825bdf0b1db0f1a06a541d683b9e149e217a54c02`.
This is a local reproducibility artifact, not a publicly accessible download.

## Not approved yet

- Currency prefix alignment across font scales and platforms. Its current
  absolute positioning is not proof of a shared text baseline. Native's
  `baseline_from_top` estimates font metrics; investigate native measurement
  and reusable field layout before adding another fixed visual offset.
- The emulator keyboard displayed its floating toolbar/onboarding over some
  field captures. Those captures are diagnostic only, not gallery material.
- Samsung validation of the latest field changes, complete variant coverage,
  large text, landscape, accessibility and measured performance remain pending.
- These results do not replace existing release gates or approve all 114
  components as a finished premium library.
