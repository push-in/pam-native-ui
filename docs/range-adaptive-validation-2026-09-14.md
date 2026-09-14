# Adaptive interval fields — scoped Android evidence

Both interval compositions now use a 56 dp minimum rather than fixed height,
with 12 dp vertical padding. Disabled date fields use the same semantic surface,
border and foreground treatment as disabled time fields. These are UI composition
changes; no PAM core or native primitive changes were necessary.

Validation:

- Material matrix: 114 components, 32,832 style cases, 456 render cases passed.
- Configured PHPStan level 9 passed.
- Optimized Android build installed on API 36 emulator, APK SHA-256
  `2cb94f5b318b70ad331885b49dd2f72a4e1ae6ea7c36f71e34cddef30fc515aa`.
- `audit-result-range-android.py`: result enabled/disabled/loading actions passed;
  both interval dialogs opened and returned after cancellation; both opened at
  font scale 2.0. Original device settings were restored in `finally`.
- Inspected large-font screenshots: visible date/time values remained aligned
  and unclipped. This does not approve off-screen specimens, all themes,
  changed-value persistence, TalkBack, iOS or physical Samsung coverage.

Local diagnostic evidence: `/tmp/pam-ui-range-adaptive-20260914/report.json`,
`p-date-range-picker-large-font.png`, `p-time-range-picker-large-font.png`.
These are temporary diagnostic captures, not published documentation media.
The build command cleaned 88.1 MiB of regenerable development artifacts.
