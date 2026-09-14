# Feedback batch — 2026-09-14

## Explicit dismissal-action locks

Chips now use the shared vector CloseIcon instead of a font glyph. Chip and
Alert close children explicitly expose disabled semantics and omit their Press
callback when disabled/isDisabled, readonly/readOnly/isReadOnly or
loading/isLoading. Active close callbacks remain unchanged. Root disabling is
not used as a substitute for the child action's own state contract.

The field-action regression file covers active behavior and all seven lock
aliases on both components. The material matrix and targeted level-9 PHPStan
pass. The disabled chip showcase now includes a closable specimen with a real
callback, so an incorrectly enabled close action would visibly remove it.

One 11-second Android build produced APK
`3fb5c5ad3c1b44c9ca543c07dcd19d1b99dc1d2bd8640b31eae8d81b5262c984`.
`/tmp/pam-chip-dismissal-20260914/report.json` passed real taps on disabled and
active removal controls on emulator-5554. The disabled chip remained; the active
chip disappeared with controlled confirmation text. The actions screenshot was
inspected: vector close marks are aligned with labels and within the chip bounds.
This is scoped chip evidence; Alert device interaction, every alias on-device,
themes, dynamic text and UIKit remain outside this check. No media was published.

Ownership: changes in this batch belong to PAM Native UI. No native primitive or
CLI change was required. The UI/UX review focused on truthful state feedback,
semantic theme colors, existing typography and unframed page composition.

## Implemented

- Result State error, warning and empty defaults no longer inherit success copy.
- Empty results use a neutral icon surface, not a success color.
- ResultStatus exposes sequential integer codes; legacy names remain boundary
  aliases. Invalid status values fail explicitly. Application copy is preserved.
- Result State and Progress Button honor `isLoading`, with explicit `loading`
  taking precedence. This closes the early-composition alias gap.
- Showcase and public Result State documentation include warning/error/empty
  actions. Chart's PHP event adapter was inspected; its existing finite-value
  validation was not changed and this is not a new chart approval.

## Evidence

Material matrix passed: 114 components, 32,832 style cases and 456 renders.
Focused PHPStan level 9 passed for the renderer, ResultStatus, matrix and showcase.
The additional invalid-status regression cases also passed the PHP matrix.

One Android release-mode build took 11 seconds and was installed on API 36,
emulator-5554. Candidate SHA-256:
`393e1558d85b6b9f3a747b01a664249dabb5e81d057643f01908d487b708baff`.
Build cleanup removed 96.7 MiB of development artifacts.

`/tmp/pam-feedback-results-20260914/report.json` proves enabled action completion,
rejection of disabled/loading actions, and warning/error/empty actions updating
their own tapped instances. The warning, error and empty screenshots were
inspected: centered content, full text, distinct semantic icons, neutral empty
surface, and no decorative/nested cards. Neighboring sections are clipped by the
scroll viewport in raw captures; these are audit evidence, not publication media.

This is scoped verification, not full component approval. Device status defaults
are represented by customized showcase copy; uncustomized defaults are covered
by PHP tests. Dark mode, TalkBack, RTL and iOS are not established by this run.
No packages or documentation were published.

Progress Button completed Upload → 25% → 50% → 75% → Complete → Upload and
rejected taps on the indeterminate Preparing action at font scales 1.0 and 2.0.
Evidence: `/tmp/pam-feedback-progress-20260914/report.json`, same candidate SHA.
Settings were restored. These interactions do not establish frame-rate or
animation quality because the harness disables system animations.
