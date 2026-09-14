# Consolidated form review

## Shared field action visuals

The shared clear action now renders the existing native CloseIcon rather than a
font multiplication glyph. Password visibility uses EyeIcon/EyeOffIcon, with
`showLabel` and `hideLabel` for localized accessible names. Decorative icons are
hidden from accessibility traversal; the parent retains its accessible action.
Existing slot geometry, callbacks and readonly/disabled policy are unchanged.
This is entirely UI composition over existing capabilities, with no new native
implementation, CLI change or dependency.

PHP regressions cover vector clearing in Text Field, Textarea, Password Field,
Masked Field and Currency Field, and both localized password visibility states.
The 114-component render/style matrix and targeted level-9 PHPStan pass.

One 10-second Android build produced candidate SHA-256
`bbd39bf7d6ebfe666111148cf39a3746a8f429372ee981c62bace40d5814803f`.
`/tmp/pam-field-vector-actions-20260914/report.json` records a passed password
reveal/clear flow, readonly-clear exclusion, distinct field names and aligned,
non-overlapping actions on emulator-5554. The combined-actions screenshot was
inspected: eye, eye-off and close vectors are visible, centered and separated
alongside the loading indicator. The build automatically cleaned 96.8 MiB.

Device coverage here is the password composite, not a new full approval of all
five fields, all themes, iOS, large text or animation performance. Existing
unchanged formatting and retention tests were not repeated. Images remain local
diagnostic evidence, not published media.

## Date range event bounds

Copy customization follow-up: Date Range and Time Range now honor shared
`placeholder` and endpoint-specific `fromPlaceholder`/`toPlaceholder` instead
of forcing English empty text. Existing endpoint labels remain distinct in
accessibility properties. Four PHP cases cover both components and precedence;
the full render/style matrix and targeted level-9 analysis pass. Authoring docs
show a controlled Portuguese example. This later copy change is not part of the
bounded-date APK below; long translated text/layout still needs device coverage.

The controlled Date Range Picker previously forwarded minimum/maximum dates to
the native calendar but accepted out-of-bounds Change events at its PHP boundary.
The new regression failed before implementation for both endpoints. The renderer
now rejects those events using `minDate`/`maxDate` and
`minimumDate`/`maximumDate`, with inclusive bounds. Calendar-invalid bounds are
ignored, matching the native host's parsed-bound behavior. Existing invalid-date,
disabled-date and ordered-interval guards remain in place.

The matrix passes rejection on both endpoints and acceptance at each exact bound,
preserving the other controlled endpoint. This is UI composition policy over the
existing native picker, not a new PAM Native primitive or CLI feature. It is
code-level regression evidence; no new device interaction or visual approval is
claimed by those PHP checks alone.

Android follow-up: `--date-bounds` in the interval-confirmation audit passed on
emulator-5554/API 36, candidate SHA-256
`1751f2c8915dd37a14babb9877f59f1afe72e42d4cd8f163055572fc8a5518d9`.
The native dialog exposes September 4 and 21 as disabled for a September 5–20
window. Both exact boundaries were selected and confirmed, then reopened and
cancelled without losing the controlled interval. Final capture was inspected:
labels and values align, remain within the two fields and have clear separation.
Report: `/tmp/pam-date-bounds-20260914/report.json`. No new time-picker run was
needed: its implementation and existing confirmation evidence were unchanged.
The build synchronized UI renderer/showcase sources and confirmed the copied
Kotlin host hash; Rust compilation took 10.11s and Gradle 10s. Installation passed
and automatic cleanup removed 96.8 MiB. Showcase PHPStan and script compilation
also pass. Dark mode, large text, alternate locales and iOS bounds remain outside
this scoped check; these captures are diagnostic, not publication-ready media.

## Current consolidated candidate

The integrated run `/tmp/pam-form-controls-batch-20260914.json` completed
7/7 scoped scenarios with no failures on emulator-5554 (Android 16/API 36,
font scale 1.0), without rebuilding or changing dependencies. APK SHA-256:
`ad9367fe9acecb49a648d9639eabaec35f6571795ee2701ee9e6f3958ed7b15a`.

- Password Field: reveal/hide and appended input retain the cursor/value.
- Masked Field and Currency Field: formatted typing retains expected values.
- Tag Input and Multi Select: protected selections, add/reopen/remove retention.
- Date Range Picker and Time Range Picker: readonly protection and open/cancel
  retention; this run does not prove confirmation of new range values.

Password, masked, currency, added-tag and added-selection screenshots were
visually inspected. The two range screenshots are not counted as visually
reviewed in this record. Evidence is local under
`/tmp/pam-form-controls-batch-20260914`; temporary artifacts are not published
documentation assets. Animations were disabled, so this run proves neither
animation smoothness nor performance. It is not seven full component approvals.

Delivery cadence: batch related implementation changes, build once when code
changes require it, run the integrated scenarios, and rerun only failures or
checks affected by subsequent shared changes. Do not repeat passing scenarios
on an unchanged candidate merely to produce another progress update.

## Earlier candidates

Initial Samsung batch `/tmp/pam-fields-consolidated-20260914.json` exercised Text
Field, Textarea, Password Field, Masked Field, Currency Field, Tag Input and Multi
Select on the auto-fit candidate. The first five retained input; password reveal/
hide retained the value, phone masking produced `(21) 91234-5678`, and currency
produced `731,25`. Raw before/after images were viewed, including keyboard-open
states. Field labels and editors remained aligned in those captured states.

This initial batch was not full approval: tags and multiple selection only opened/
closed. Its password assertion also missed cursor position: the viewed image showed
the cursor at the beginning after a visibility change. PAM Native now preserves
the selection during input configuration; UI did not acquire native compensation.

The reusable audit was strengthened:

- Editable value checks target the focused editor instead of accepting matching
  text from any field.
- Password visibility is toggled three times, typing after reveal and hide without
  moving the cursor. The final visible value must contain both appended characters.
- Tags and Multi Select add an option, close/reopen, remove it and assert that the
  original field selection is restored. Opening a changed screenshot is insufficient.
- Clearing formatted fields batches the same 17 key events into one ADB invocation.

The first strengthened selection attempt failed in the harness: it used the clicked
wrapper's identity rather than the accessible Spinner's stable field label. XML
showed the correct Skills/Teams field still present. This selector was corrected;
the failure is retained in `/tmp/pam-fields-retention-20260914.json`, not classified
as a UI defect. Currency passed that attempt with batched key events.

Final password candidate SHA:
`13acbe124a45d398ac68cb03417d9c7684164790ceaaa73d428394b1dc031830`.
`/tmp/pam-fields-fixed-20260914.json` contains the passed password cursor flow;
its viewed final screenshot shows `PAM_AUDIT_2026XX` with the cursor at the end.
That same report retains selection-harness failures: the uncompressed hierarchy
contains a noninteractive Spinner wrapper and an interactive Spinner with the
same label/bounds. Selection lookup now requires the enabled clickable Spinner,
as the existing dedicated selection audits do. This was not a second UI failure.

`/tmp/pam-fields-selection-final-20260914.json` passed both strengthened selection
flows on the same final APK: Swift was added/removed from Skills, Engineering
from Teams, with the original selections restored after reopening. Each flow took
about 28 seconds. Added-state screenshots were viewed: chips remain inside the
field, with aligned labels and preserved spacing. These are scoped functional and
visual checks, not full approval of either component.

This batch does not establish custom tag creation, search, all variants, larger
fonts, readonly/disabled interactions, RTL, IME composition, iOS or performance
approval. Existing dedicated scripts cover additional flows but their historical
results are not promoted to current-candidate proof. No publication was performed.
