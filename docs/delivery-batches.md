# Delivery batches

Work on shared causes across component families before building the showcase.
Keep native capabilities in PAM Native, CLI capabilities in PAM, and visual
composition in PAM Native UI. Do not add decorative preview cards.

For each batch:

1. Inspect the current diff and existing evidence; do not repeat completed work.
2. Implement related fixes and regression cases together.
3. Run the render matrix and static analysis once against the batch.
4. Build one Android candidate and exercise the changed interactions and layouts.
5. Retest failed or subsequently changed scopes, not unrelated successful cases.
6. Record the candidate identity, actual coverage, remaining gaps and deliverable.

Screenshots alone are not interaction approval. Matrix coverage is not device
coverage. A local commit is not a published release. Release gates remain required.
Avoid repeated CI polling and retain only the build artifacts needed for the
current candidate and evidence; never delete unrelated user files or devices.

## Throughput checkpoints

- Before starting, name the component family, shared defects, acceptance cases
  and concrete deliverable. Do not expand the batch with optional polish midway.
- Reuse existing passing evidence when its implementation and candidate scope
  have not changed. Do not rerun a scenario merely to produce another screenshot.
- At 30 minutes without a completed batch, report the actual blocker and choose
  a smaller deliverable or a different approach. This is a reassessment checkpoint,
  not a promise that every defect can be resolved within 30 minutes.
- Separate release-blocking defects from optional enhancements. Record unrelated
  discoveries for the next batch instead of restarting the current component.
- Report delivered changes, checks actually performed and remaining gaps. Do not
  count scripts, screenshots or individual assertions as completed components.
- End each batch with a reviewable diff and evidence summary. Do not repeatedly
  rebuild for documentation-only changes or already validated unchanged code.

## Current release checkpoint — 2026-09-14

`python3 tools/validate-android-component-approvals.py --require-complete` fails
with 5/114 formal approvals. Recent scoped Samsung checks must not be counted as
additional full approvals or as proof that historical approvals cover a new APK.

An earlier completed behavioral batch covers protected reorder positions, both swipe
directions with visible button alternatives and disabled rejection, and two
controlled refresh cycles. Reports and exact APK identities are in
`reorder-protected-positions.md`, `swipe-actions-validation-2026-09-14.md` and
`pull-to-refresh-demo.md`. Broader visual, accessibility, performance and iOS
requirements remain open. No publication is authorized by these partial results.

## Latest scoped deliveries and process blockers

Do not restart these scopes merely because their evidence is not a full approval:

| Delivery | Code identity | Evidence and remaining scope |
| --- | --- | --- |
| Responsive row-grid integration | UI `b2bd825`, Native `6a54f53` | `collections-batch-2026-09-14.md`; engine/PHP coverage and emulator 2×2 visual check. Resize, RTL, large text and iOS remain open. |
| Result defaults and loading aliases | UI `9edd6aa` | `feedback-batch-2026-09-14.md`; result actions and Progress Button cycle at font scales 1×/2×. Wider a11y/theme/platform checks remain open. |

The approval validator currently hard-codes `twoConsecutivePhysicalPasses`,
requires exactly two distinct physical reports, and rejects emulator identities.
This differs from the user's newer request for one consolidated test round and
authorized emulator fallback. Clarification has been requested before changing
the approval policy. **No gate has been relaxed and no approval was added.**
Even with a changed pass-count/device policy, missing scenario, visual,
accessibility or runtime evidence must still fail approval.

The release check was executed after `9edd6aa` and stopped with:
`Material release gate ios is not verified for 92 modules.` This is the first
reported failure, not an exhaustive list of remaining release failures. The
current Linux/Android evidence cannot establish iOS completion. Do not repeatedly
run this unchanged gate or describe an Android-only batch as ready to publish.

Next execution should address uncovered acceptance scopes or shared defects,
not regenerate already-passing screenshots. Keep historical physical approvals
separate from validation of the current candidate APK.

Native SDK checkpoint: the accumulated TemplateRenderer validation batches now
pass the targeted PHPStan level-9 check with zero file errors, plus the SDK suite
and UI matrix. See `pam-native/docs/template-renderer-static-closure-2026-09-14.md`
in the sibling repository. This closes that particular code gate, not the whole
native SDK audit. Before the next Android build, explicitly sync the current PHP
TemplateRenderer into staging; the installed feedback candidate predates these
native PHP changes. Do not repeat the resolved static-diagnostic investigation.

## Integrated Android candidate — 2026-09-14

The staging PHP SDK was synchronized with Native `2654c90` and a single release
build installed on `emulator-5554` (Android 16/API 36). Candidate SHA-256:
`298ca172c5b4d59f1da0be470c6f5eeaedaa71208295d5dc7155f19b741a0a0e`.
UI implementation: `9edd6aa`; documentation HEAD before this batch: `cfc57ec`.

- Chart point selection and Search Bar text entry passed in the integrated run:
  `/tmp/pam-sdk-integrated-20260914.json`.
- Data Grid initially failed because the harness inspected another instance's
  `No rows selected` footer. The corrected locator scrolls the page gutter,
  identifies the Selectable table and its own footer, and verifies both checkbox
  state and summary when selecting **and deselecting** row 1.
- Only Data Grid was repeated: `/tmp/pam-sdk-data-grid-20260914.json`, one pass,
  zero failures, 21.558 seconds. The initial failure remains in its original report.
- Existing screenshots were inspected for all three cases. The selected grid
  checkbox appears as a solid green square without a contrasting check mark;
  selection works, but this remains a visual issue to investigate in the native
  checkbox rendering. Search Bar evidence covers entry with the keyboard open,
  not clear, submit, long-query handling or every variation. Chart evidence does
  not establish all point/gesture behavior.

These are scoped interaction results, **not full component approvals** or
publication-quality media. Animations were disabled; no motion-performance,
iOS, RTL or comprehensive accessibility claim is made. Temporary evidence paths
are local diagnostic artifacts, not durable online documentation assets.

### Selection mark follow-up: visual failure remains

The text check was replaced with the existing native vector icon, explicitly
16×16 with PrimaryForeground. The UI matrix and targeted renderer PHPStan passed.
Staging initially still contained the old renderer; it was explicitly synchronized
before rebuilding the tested candidate. Future builds must compare staged UI PHP
sources too, not just Native SDK sources.

Candidate `8e4eb4012a99b969c899d13b37222abcabff02233dcf7962700a4b9ab80e0df6`
passed select/deselect in `/tmp/pam-grid-vector-20260914.json`, but inspection of
`pass-01-p-data-grid-checked-true.png` **still shows no contrasting check**.
Therefore replacing the glyph alone does not resolve the defect. Keep the visual
case open and inspect native child layout/painting of the selection cell next;
do not rebuild or repeat the same unchanged interaction expecting a different
visual result. No component approval or release is supported by this run.

### Native insertion correction: check now visible

The cause was deferred native view creation inside virtual rows: adding a child
does not change the row ID/extent, so RecyclerView's content comparison skips
rebinding. PAM Native now collects created node IDs during the mutation batch,
resolves their unique virtual cell roots after frames arrive, and materializes
missing descendants in already-mounted affected cells. Offscreen rows remain lazy.

An 18-second Android build succeeded. The focused select/deselect run passed in
`/tmp/pam-grid-insertion-20260914.json`; inspection of its
`pass-01-p-data-grid-checked-true.png` confirms the white vector check is now
visible and centered. This closes the observed check-visibility failure on the
tested emulator, not the whole Data Grid audit.

Native commit `8338bec` adds the implementation and the instrumented regression
`richVirtualCellMountsInsertedChildrenWithoutChangingRowExtent`. Its Android 16
JUnit report records one test, zero failures/errors/skips, exercising two
insert/remove cycles while retaining the holder and rejecting duplicate children.
The test body took 0.368 seconds; the filtered build/test command took 17 seconds.
See the sibling Native document `docs/virtual-cell-insertion-2026-09-14.md`.
Broader virtualization/platform coverage remains open. Do not restart this fixed
case unless subsequent changes invalidate its evidence.
