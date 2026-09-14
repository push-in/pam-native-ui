# Delivery batches

Work on shared causes across component families before building the showcase.
Keep native capabilities in PAM Native, CLI capabilities in PAM, and visual
composition in PAM Native UI. Do not add decorative preview cards.

For each batch:

1. Inspect the current diff and existing evidence; do not repeat completed work.
2. Implement related fixes and regression cases together.
3. Run the render matrix and static analysis once against the batch.
   Before building a staged showcase, compare changed source files with their
   staged copies, including UI Kotlin under vendor/pushinbr/pam-native-ui/android,
   not just PHP. Native template synchronization does not refresh a copied UI
   dependency. A successful APK build alone does not prove it contains current
   worktree source; synchronize mismatches and verify hashes before the build.
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

Native 1.0.28 preparation: candidate `e963ef5dd2cdbca9ea232a030dfc2e2a1a911776`
aligns Rust workspace/lock and PHP SDK versions without creating a tag. UI
Composer and plugin minimum now require 1.0.28; showcase path aliases and lock
were updated after a successful targeted Composer dry-run, with `--no-install`
and `--no-scripts`. Composer schema validation passes but strict mode reports
the intentional exact showcase pins as warnings. Verify and Release workflow
Native pins/aliases now target that candidate. YAML parsing and the UI matrix
pass. These edits prepare compatibility; they do not claim the unpublished
candidate is available on Packagist or that its remote CI has passed.

UI candidate Verify run `34831423557`:
https://github.com/push-in/pam-native-ui/actions/runs/34831423557
was confirmed queued for `f35389a1ae47f65f1a7740c27278ff3aed4826bb`, on new audit
branch `audit/ui-candidate-20260914`, with explicit input
`native_ref=8e8c3524f63fae19c87630547d67942e43019d7e`.
The minimum-Native matrix incompatibility described below remains expected;
current-Native jobs are intended to identify additional Android/UIKit failures.
The run completed with failure: current Android, both iOS jobs and Android
API 26/36 behavior passed; minimum Android failed for the missing overlay API.
PHP jobs reported static-analysis errors in material-matrix.php. Added runtime
guards around reflected specimen arrays and moved callback-result comparisons
into a strict assertion helper so callback writes do not create false dead-code
inference. Removed two redundant nullsafe calls exposed after that correction.
Targeted PHPStan on material-matrix.php and documented-composition.php now
passes without suppressions; the matrix and executable docs example also pass.
This run does not relax the minimum contract or authorize release. Inspect this
run instead of dispatching another copy. Local test/docs follow-ups after
`f35389a` are not included in this candidate; no runtime change followed it.

Current Native candidate CI: run `34831107281` at
https://github.com/push-in/pam-native/actions/runs/34831107281 was confirmed
`in_progress` for exact SHA `8e8c3524f63fae19c87630547d67942e43019d7e`.
The commit was pushed to the new non-release branch
`audit/native-ui-candidate-20260914` and `ci.yml` was dispatched explicitly.
No tag, merge, release workflow or package publishing was performed. Resume
inspection of this run rather than dispatching a duplicate; a successful result
still requires inspection of its actual platform/test coverage.
The Rust/PHP job subsequently failed at `cargo fmt --all -- --check`, before
tests, with formatting diffs in `crates/pam-native-engine/src/layout.rs`.
Ran the official formatter locally; only that file changed and the formatting
check now passes. Android and Swift/UIKit jobs remained running at the last
poll. Keep their original run; rerunning the failed old SHA would reproduce
the formatting failure rather than validate the corrected candidate.

Remote infrastructure recheck: Verify run `34795441973` completed successfully
for UI `0c9fe92ef836626d77536ed8212cb6727600f3fb`, including jobs named
`iOS / PAM Native minimum` and `iOS / PAM Native current`. The macOS CI path
therefore exists; lack of a local Mac is not by itself an infrastructure blocker.
This historical run does not validate UI `f35389a1ae47f65f1a7740c27278ff3aed4826bb`
or its Native pair `8e8c3524f63fae19c87630547d67942e43019d7e`.
Use the non-publishing Verify workflow with the exact candidate revisions after
checking remote availability; do not trigger release to obtain validation.
Remote refs checked subsequently: UI topic branch still points to `0c9fe92`,
68 commits behind local HEAD; Native topic branch points to `46bfe506` rather
than `8e8c352`. Verify's minimum matrix remains pinned to `9468f8e3`, which
predates the newly imported OverlayCollisionResolver API. Before release,
resolve the minimum supported Native package/plugin contract and its CI matrix
consistently. Passing only the current-Native job cannot establish compatibility
with the declared minimum. Do not label this as missing macOS infrastructure.
Dependency preflight additionally found that GitHub's immutable `v1.0.27`
tag resolves to `a737904764a459d06169e241583815f856c2a1aa`, while Packagist's
P2 metadata still lists `v1.0.26` as its newest stable entry. The Native workspace
still declares 1.0.27 but contains later API additions. Do not move the existing
tag or assume its code includes those additions. Prepare a new Native release
version for the new plugin API, validate that candidate, then align UI Composer,
plugin minimum, showcase lock and minimum/current CI references. Packagist
freshness is a separate publication check. No dependency installation, tag
mutation or package publication was performed during this preflight.
Inspect job steps/results before treating job names as coverage of every
required iOS interaction or visual condition.

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

Data-family startup sample on emulator-5554/API 36:
`/tmp/pam-data-startup-20260914.json` records one cold launch per route on APK
`06492d475f7310589179b4c0a61d270064e1377afd2065c5caed6584ec2d8d02`:
Chart 367 ms, Data Grid 277 ms, Virtual List 424 ms, Section List 336 ms.
Each launch confirmed the route and Variations heading at the top. The sampled
run passed the runner's existing startup budgets. It is not a repeated benchmark,
frame-time measurement, physical-device result or full catalog approval.
No new build was needed. The startup runner now accepts `--tags`, validates them
against the full registry and records `fullCatalog`, `registeredRouteCount` and
`samplesPerRoute` so a selected subset cannot be mistaken for catalog coverage.

List configuration follow-up: the PHP matrix now explicitly covers Virtual List
and Section List default foreground, custom packed foreground and transparent
foreground, combined with a custom row height and disabled scrolling. All six
configurations pass. Inspection showed that explicit Style is applied after the
shared list default; no renderer change was needed. This proves compiled
properties, not physical row appearance or gesture behavior. No Android rebuild
was performed for this test-only change. Data table empty/loading rows already
exist in the renderer; their existence alone is not visual approval.

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
