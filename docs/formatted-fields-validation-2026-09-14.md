# Mask and currency fields — Android editing evidence

No production formatting change was required for the exercised cases: formatting
already belongs to PAM Native, while the UI supplies the pattern, locale, decimal
precision and separately composed currency prefix.

On API 36 emulator, optimized APK
`ac94b16de8cdfa3ee998ce1c9250141256610cd9eb89a71bc78da553d41f5780`,
the audit replaced the existing text using native selection/keyboard input:

| Field | Entered digits | Displayed result |
| --- | --- | --- |
| Phone | 21912345678 | (21) 91234-5678 |
| CPF mask | 98765432100 | 987.654.321-00 |
| BRL | 123456 | 1.234,56 |
| USD | 123456 | 1,234.56 |
| Zero decimal places | 9876 | 9.876 |

Initial formatting, replacement typing and retained value after closing the
keyboard passed for all five fields. Inspected the final mask and currency
screenshots: visible labels/values/prefixes aligned, errors below fields.
CPF here is formatting only, not a valid-CPF/checksum validator.

Evidence: `/tmp/pam-ui-formatted-fields-20260914/report.json` and adjacent PNG/XML.
The initial report used the misleading key `retainedAfterBlur`; Android retained
input focus after hiding the IME. The script now calls this
`retainedAfterKeyboardDismissal`. This evidence does NOT prove blur handling,
cursor insertion in the middle, paste, blocked states, large fonts, all themes,
Samsung/iOS or complete component approval. No test was rerun just to rename it.

The existing APK was reused, no rebuild or dependency changes. Device settings
were restored in `finally`; no public documentation media was published.
