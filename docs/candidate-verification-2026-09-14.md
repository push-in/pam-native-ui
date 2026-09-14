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

### UI run terminal result: PHP analysis memory failure

Run `34838465633` completed with six successful Android/UIKit jobs and a
successful lowest-dependency PHP job. PHP latest (`103958687654`) and the normal
PHP job (`103958687984`) failed when PHPStan 2.2.14 exhausted its 2 GiB limit
while analyzing the monolithic `tests/material-matrix.php`. This is not a native
platform test failure and must not be reported as a green full verification.

The new field-action and controlled-tree regressions have been extracted into
separate, scope-isolated files, still required by the matrix. No assertions were
removed, memory limits raised, baselines added or tests excluded. The runtime
matrix passes after extraction; full analysis and corrected-revision CI results
must be recorded separately before considering this issue closed.

Local full level-9 debug analysis with PHPStan 2.2.9 then passed in 163.34 seconds,
peak RSS 1,831,572 KiB, retaining the 2 GiB PHP memory limit. Its configuration
includes all repository PHPStan paths and changes only cache location/worker
count; the default local cache contains a root-owned subdirectory, so the
writable existing audit cache was used without changing ownership or deleting
user files. CI uses 2.2.14 and must still confirm the corrected revision.

Follow-up run `34839419510`, UI `d052b97`, has now completed both formerly failing
PHP jobs successfully (`103961996021`, `103961996069`). The latest-dependency log
confirms PHPStan 2.2.14 and `[OK] No errors`. All six Android/UIKit jobs also
passed; the lowest-dependency PHP job was still running at this checkpoint.
This closes the observed analysis-memory failure for `d052b97`, not the later
Treeview marker/dismissal changes or the full component approval inventory.

Native UIKit job `103957959244` also completed successfully: 69 simulator tests,
zero failures. Android build/unit contracts passed; Android API 26/36 runtime
jobs are subsequent checks, not implied by the library build result.

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
