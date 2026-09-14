# Filter Bar: protected selections

## Explicit selection marks

Selected filters now include a 16dp native vector check with an 8dp label gap,
including selected disabled items. Unselected filters do not render the check.
The decorative icon is accessibility-hidden; the pressable retains its complete
label and checked/selected semantics. This replaces reliance on color/weight alone
without changing selection protection or clear behavior.

Candidate `44c4a614eae3b85f9a9295520fc650bbca086b6fab2a3574f8983648a4581ac7`
passed `/tmp/pam-filter-mark-20260914.json` on emulator-5554/API 36 at font 1.0:
clear removes Optional scope, retains Required scope, and touching the disabled
Required scope does not deselect it. The after screenshot was viewed: the retained
check is visible beside Required scope, Optional scope has no check, and visible
readonly/multiple examples retain aligned checks and labels. This is scoped
interaction/visual evidence, not complete component approval. Large text, themes,
screen-reader operation, motion and iOS remain unverified for this change.

The renderer PHPStan and material matrix pass, including new mark-presence and
unselected-mark-absence regressions. One Android build took 10 seconds and cleaned
96.8 MiB of generated artifacts. No publication was performed.

## Earlier protection implementation

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
