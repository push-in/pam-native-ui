# Consolidated candidate verification

## Exact revisions under verification

- UI: `902cd5d820f0a5a267512f3445251fc55252e2e6`, audit branch
  `audit/ui-candidate-20260914`.
- Native: `e963ef5dd2cdbca9ea232a030dfc2e2a1a911776`, audit branch
  `audit/native-ui-candidate-20260914`.
- Local Android showcase APK:
  `54c5843b003a6bd4325e18e19bd91dc7076545ec77a0212c82d740db33dc3d61`.

The UI branch was advanced without force from `fca5827` to `902cd5d`. This adds
controlled multiple Treeview selection/expansion, authored tree accessibility
labels, vector field actions and unified field validation state to the previously
verified candidate. Existing local evidence is recorded in the tree and form
batch documents. No main branch, release tag or published package was changed.

## Live verification handles

At dispatch checkpoint, these runs are **in progress**, not approved:

- [UI Verify 34838465633](https://github.com/push-in/pam-native-ui/actions/runs/34838465633):
  six Android/iOS jobs started; PHP compatibility jobs follow the UIKit job.
- [Native CI 34838564521](https://github.com/push-in/pam-native/actions/runs/34838564521):
  verifies the corrected formatting and 1.0.28 candidate on the exact Native
  revision used by UI. The older run `34831107281` failed formatting at `8e8c352`;
  its successful platform jobs cannot prove the later revision's Rust gate.

Continue observing these handles; do not dispatch duplicate runs because a job
takes time or output is temporarily unavailable. Fetch failed-job evidence before
changing code. UI minimum/current jobs both target this 1.0.28 candidate and do
not establish compatibility with two distinct Native versions.

## Publication is not yet allowed

### Verified intermediate results

UI UIKit current job `103957650930` completed successfully: 11 tests, zero
failures, including FileTree activation/disabled ancestry and multiple-selection
retention. Both Android library build jobs, UIKit minimum and Android API 26
behavior also completed successfully. Android API 36 and PHP graph checks were
still running at this checkpoint; the overall UI run is not yet called green.

Native contracts job `103957959288` completed successfully at `e963ef5`.
Its logs confirm PHP SDK tests, deterministic tree fuzz (1,000 frames), and the
core performance contract passed. This closes the earlier formatting failure
for this revision. Native Android/UIKit jobs were still running; core timings
are not proof of showcase gesture/frame performance.

`python3 tools/validate-android-component-approvals.py --require-complete` currently
fails with **5/114** complete recorded approvals. Historical approvals are not
proof for this exact APK. Broad emulator smoke results from September 8 likewise
do not establish full visual/behavior approval of the current candidate.

Still required: current component/variant evidence, outstanding visual and
interaction corrections, large text/RTL/theme coverage, platform accessibility
and performance checks, approved showcase media, and coordinated publication.
Neither a future green CI result nor these scoped local checks replaces those
requirements. Preserve the full goal; do not relax the evidence gate to publish.
