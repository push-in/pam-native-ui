# Drawer interaction and visual findings

UI composition now respects global/item disabled aliases, suppresses disabled
destination callbacks, provides vertical padding, pairs selected icon colors
with their surface, constrains label/header width, and uses PAM Native Scroll
inside SafeAreaView for long menus. No new native primitive was needed.

Matrix and configured PHPStan level 9 passed. API 36 emulator optimized APK:
`edb050b1e98fd9542a6a0e7c94f5846d69c6e6b828b856a9a37576ff88029609`.

- `/tmp/pam-ui-drawer-scroll-20260914/report.json`: font 1.0 disabled tap rejected,
  destination 16 reached by scrolling, selection closed drawer. The run then
  failed its 2.0 viewport lookup because only the header was initially visible.
- `/tmp/pam-ui-drawer-scroll-large-20260914/report.json`: repeated only font 2.0
  after allowing viewport lookup by header as well as destination text. All
  three interaction checks passed on the same APK.

Inspected both last-destination screenshots and the 2.0 disabled screenshot.
Long menu labels wrap and the last destination is usable. **Not visually
approved:** the separate permanent showcase preview hardcodes a 216 dp sidebar,
which splits “Dashboard” mid-word at 2.0. Its cramped side-by-side canvas also
needs an adaptive demonstration design. Do not publish these diagnostic prints
as polished documentation media. Selected-value persistence after reopening,
screen reader operation, other presentations/themes and Samsung/iOS remain
outside this audit. Settings are restored in `finally`; fullApproval is false.

## Follow-up: adaptive showcase

The cramped permanent specimen is now `Adaptive permanent`: overlay below a
native 840 dp component-width breakpoint, permanent above it. Removed its 216 dp
sidebar override; previews have 360 dp height and padded controls. This keeps
system font scaling rather than shrinking typography. Production drawer types
remain available; the showcase demonstrates the adaptive permanent mode.

APK `8528edb3f6f56f4a2be271a4f2f2bd39b92c6607edceb145e039514370d31b8c`:

- `/tmp/pam-ui-adaptive-drawer-20260914/report.json`: compact 1080×2400 at font
  2.0 passed closed-overlay/open-destinations checks. Inspected capture shows
  Dashboard intact. The subsequent 2560×2400 test did not activate permanent:
  showcase navigation reserved enough width to keep the example below 840 dp.
- `/tmp/pam-ui-adaptive-drawer-expanded-20260914/report.json`: expanded
  3400×2400 at font 1.0 passed. Inspected permanent menu beside content, without
  overlap. This reran only the expanded case on the same APK.
- Emulator resolution restored to physical 1080×2400 and font scale to 1.0.

This resolves the observed mid-word break in the compact permanent specimen by
using the appropriate presentation. It is not complete drawer approval: live
resize persistence, expanded large-font behavior, all themes, gestures and
physical/iOS coverage remain separate requirements.
