# Protected reorder positions

A disabled Reorderable List item now retains its position during both drag/drop
and button-based moves. A drop spanning a disabled row is rejected because the
splice would indirectly move that protected row. Moves within an unblocked
segment remain valid. This deliberately tightens the earlier drag behavior to
match the existing adjacent-button policy.

Item `isDisabled` is accepted when `disabled` is absent. An explicit `disabled`
value retains precedence. The composition and policy belong to PAM Native UI;
PAM Native's reusable drag/drop implementation is unchanged.

PHP regression cases exercise crossing the protected position in both directions,
both disabled aliases, blocked adjacent controls, and a valid within-segment drop.
The render matrix passes.

Samsung SM-G973F evidence, APK
`93b007191fac0baf8eb2e4ca67465b1237809addeccdbb3b285a7264bdfe94e6`:
`/tmp/pam-ui-reorder-protected-20260914/report.json` passes three actual long-press
drags: crossing the protected milestone in both directions leaves the order
unchanged, while moving Verification before Implementation in the free segment
updates the controlled order. Reproduce with `tools/audit-reorder-drag-android.py
--protected-only`. This is scoped behavioral evidence, not complete visual,
screen-reader, performance or iOS approval.

## Integrated suite now checks controlled order

The generic showcase audit previously accepted any hierarchy/pixel difference
after a swipe, which could pass without reordering. Its Reorderable List branch
now uses Android long-press drag-and-drop between named row controls and requires
the exact controlled order. It also checks move-down, move-up and rejection of
the disabled first-row move-up action. Ambiguous or missing named controls fail.

`/tmp/pam-reorder-integrated-contract-20260914.json` passes this stronger contract
on emulator-5554/API 36 with the already-installed candidate
`e73add1a5806362152ad065a31ce6077d7489f0e1f27ab5cc80feb1644046af3`.
No build or dependency change was needed. The evidence directory retains XML
after dragging, each button move and the rejected boundary action. This replaces
the weak generic success criterion; it does not retrospectively strengthen older
reports or rerun the separate protected-position scenario above.
