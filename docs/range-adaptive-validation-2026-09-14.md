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

## Readonly and empty endpoint follow-up

Date/time range compositions now suppress Change callbacks for all three readonly
aliases (`readonly`, `readOnly`, `isReadOnly`), retaining readable values rather
than styling them as disabled. An empty time endpoint displays `Select time` in
the muted foreground token. The showcase includes readonly examples for both
ranges and an open time range, directly on the canvas without decorative cards.

Regression checks cover readonly/disabled callback suppression, distinct endpoint
labels, empty-time hint, and existing reversed-range normalization. The material
matrix and focused PHPStan level 9 passed. These are composition responsibilities
in PAM Native UI; no native workaround or PAM CLI change was added.

The updated arm64 build installed on Samsung, but the device required user
authentication before the audit could begin. The authorized API 36 emulator
fallback ran the same source as an x86_64 build:
`1b704c65ca83d341af9548811df4d766d2a789f1f3ad216e24983c4606c4ddb4`.

`/tmp/pam-ranges-final-emulator-20260914.json` records 3/3 scoped flows passing:

- Both readonly endpoints reject taps without opening a dialog or changing text.
- Enabled date/time fields open; cancelling preserves the visible field content.
- File Input opens the document activity resolved by Android, not a hardcoded
  vendor package; cancelling preserves the triggering field's content.

The three interaction flows took 44.5 seconds total. Raw before screenshots in
`/tmp/pam-ranges-final-emulator-20260914` were inspected: visible labels/values
align, the empty time hint is visible, and file names/actions stay within fields.
The final disabled time specimen was below the captured viewport and is not
visually approved by that screenshot. Animations were disabled by the harness;
this provides no frame-rate evidence. Device settings were restored by the audit.

Earlier Samsung failures were harness/infrastructure issues: readonly is enabled
but not clickable (correctly distinct from disabled), and a UI hierarchy dump
exited 137. They are not evidence of component defects. The harness now checks
clickability plus actual taps. Samsung File Input open/cancel passed previously,
but this does not substitute for current-candidate physical-device validation.

Still pending: changed endpoint confirmation/persistence on device, real file
selection, constraint combinations, all themes, RTL, accessibility and iOS.
No full component approval or publication is claimed. Build cleanup removed
88.1 MiB (Samsung) and 96.8 MiB (emulator) of regenerable artifacts; 22 GiB remained
available after the builds.

## Confirmed endpoint interactions (same emulator candidate)

`tools/audit-interval-confirmation-android.py` now exercises actual native picker
choices, confirms with OK, verifies both endpoint values in the first interactive
range (not text anywhere on the screen), reopens and cancels, then verifies retained
values again. No new build was needed; the APK hash is the same as above.

- Date: choosing September 15 clears the earlier September 14 endpoint; choosing
  September 20 as the end produces `2026-09-15` to `2026-09-20`.
- Time: choosing 10 AM retains the 18:00 end; choosing 7 PM as the end produces
  `10:00` to `19:00`.

Both date checks passed in `/tmp/pam-interval-confirmation-20260914/report.json`.
That run then stopped on a harness selector: clock numbers are accessibility
descriptions, not text nodes. After correcting that selector, `--time-only`
repeated just the unfinished time flows; both passed in
`/tmp/pam-time-confirmation-20260914/report.json`. Final confirmed screenshots
were viewed for both ranges: values remained aligned and inside their fields.

This closes basic changed-value confirmation and in-route retention for the API
36 candidate. It does not prove persistence across process death, all constraints,
minute editing, locale differences, iOS, or current Samsung behavior. File
selection beyond cancellation remains pending. These scoped reports are not full
component approval or published media.
