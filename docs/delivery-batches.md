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

Latest completed behavioral batch covers protected reorder positions, both swipe
directions with visible button alternatives and disabled rejection, and two
controlled refresh cycles. Reports and exact APK identities are in
`reorder-protected-positions.md`, `swipe-actions-validation-2026-09-14.md` and
`pull-to-refresh-demo.md`. Broader visual, accessibility, performance and iOS
requirements remain open. No publication is authorized by these partial results.
