# Pagination boundary validation

The integrated Android audit now exercises the controlled one-visible-page
fixture through 1 → 2 → 3 → 2 → 1. At both endpoints it verifies the relevant
navigation action is disabled, taps it and verifies selection is unchanged.
It separately taps both readonly navigation controls and verifies page 2 remains
selected. Locators are scoped to each fixture's row to avoid confusing repeated
Next/Previous labels elsewhere in the showcase.

`/tmp/pam-pagination-boundaries-20260914.json` passes on emulator-5554/API 36 in
31.638 seconds, candidate
`8ba90170d03b28ca91f9578d6681906040c4d4d9406b39368dd5546eaacc1c97`.
No rebuild or UI code change was required. The final screenshot was inspected:
page labels and arrows are aligned, with clear selected and boundary states.
Before/step/readonly XML and the screenshot are retained in the report's evidence
directory. The test replaces generic visual-change-only approval for this route.

This covers the single-page window and readonly navigation, not large page
numbers, all numbered-page window sizes, keyboard/screen-reader use, large text,
RTL, dark themes or iOS. It is not full component approval or publication media.
