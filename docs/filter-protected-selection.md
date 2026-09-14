# Filter Bar: protected selections

The group clear action preserves selected values belonging to disabled items.
It removes editable selections and emits the retained values through `onChange`.
When only protected selections remain, there is no clear action. This matches
the fact that the disabled item cannot be deselected individually.

PHP regressions cover mixed editable/protected selection and protected-only
selection, alongside the existing item/group lock tests. The render matrix
passes, and targeted renderer PHPStan reports no errors.

Android 16/API 36 emulator verification passed in
`/tmp/pam-filter-protected-20260914.json` (14.040 seconds), APK SHA-256
`e00d6531527f46796575f40da8abab35049e1ce15a89b81bfb538449440ee9e5`.
The scenario clears Optional scope, retains checked Required scope and rejects
a tap on the disabled Required scope. The resulting screenshot was inspected:
labels fit, the protected group no longer shows Clear, and adjacent examples
remain laid out without nested decorative cards.

This is scoped emulator coverage, not complete Filter Bar approval. Disabled
selection is indicated subtly by weight; broader visual-state clarity, themes,
large text, screen-reader behavior and iOS remain unverified. The build took
10 seconds and automatic cleanup removed 96.8 MiB of generated artifacts.
