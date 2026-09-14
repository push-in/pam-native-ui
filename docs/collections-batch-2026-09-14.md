# Collections batch

The PHP renderer delegates Virtual List and Section List to PAM Native list
primitives; UI does not implement a second recycling system.

Baseline Samsung report `/tmp/pam-collections-20260914.json` contains scoped
scroll checks for Virtual List and Section List, plus a static Responsive Grid
capture. These checks do not establish virtualization efficiency or responsive
behavior across device sizes. Visual review of the four-column grid found tight
trailing space around Discover; large-font/narrow-column layout remains open.

Added a 1,000-record Virtual List showcase with initialScrollIndex=990 and
prefetch=8. Samsung verified Record 991 on launch, then scrolled within the native
RecyclerView until Record 1000 was fully inside its viewport. The final capture
was inspected. Artifacts: `/tmp/pam-virtual-1000-20260914`.
APK: `98d8f679f6a2184620a2d1e6f55c63d3064ec230949ab46a7cdc782893b0ff2a`.

PHP matrix passed after the showcase change. The large-list check proves initial
index and last-item reachability, not a frame-time/memory budget, all scroll
directions, large-font layouts, item recycling state integrity or iOS behavior.
No full component approvals or online publication were made by this batch.

## Grid child ownership correction

Review found that `constrainCellContent` rewrote the first child's width for
every cell, including direct authored Columns with a narrow badge/icon. It now
only resizes explicit `pam:grid-item:` wrappers. Native layout measurement is
not allowed to reinterpret an arbitrary cell's first child as its content root.

`gridOnlyResizesExplicitGridItemWrapperContent` passed on API 36: a direct 24px
badge stays 24px while an explicit GridItem content root expands to its 180px
column. UI Kotlin unit tests also passed. This correction is not yet in the
Samsung showcase APK and does not by itself resolve the remaining tight text
in four-column layouts; that still needs end-to-end layout verification.

## Narrow-cell diagnosis

The ownership fix was built and captured on Samsung in
`/tmp/pam-grid-child-ownership-20260914`. Four-column text remains tight:
a 225px cell has 42px authored insets on each side, but its title reaches the
right boundary. An experiment synchronizing cell layoutParams.width with the
grid plan restored the narrower text constraint but clipped wrapped text because
its height remained engine-authored. Capture:
`/tmp/pam-grid-width-contract-20260914`. The width-only experiment was removed;
do not reintroduce it without height/flow reconciliation in PAM Native.

Required next step is coordinated subtree layout under a native host's changed
constraints, not shorter sample labels or a smaller font. The static audit's
PASS only proves route health/capture and did not detect this visual defect.

## Shared-engine integration

Fixed column/gutter configurations now emit native `GridColumns`, `GridSpan`,
`GridColumnGap` and `GridRowGap` on a Column. The engine can measure text at its
real column width before deriving row height. Static GridItem spans are retained;
breakpoint-dependent columns/spans/gutters and reversed layouts keep their host
contract pending the remaining migration. This is not full Responsive Grid approval.

Samsung capture `/tmp/pam-grid-engine-20260914` (APK SHA-256
`03d58236258ac4f413d3eadbf60593a1d883d19bfb36e0fc97f4168ba3f48b8d`)
confirmed four-column labels wrap without losing their trailing letters. Visual
inspection also exposed unequal cell heights; the follow-up belongs to PAM Native:
stretch automatic-height cells to row height, respecting explicit height, max height,
margins and AlignSelf/AlignItems. Native engine suite: 78 tests passed.

UI validation: material matrix passed (114 components, 456 render cases, 32,832
style cases); focused PHPStan level 9 passed. These are code contracts, not device
approval for all components. Narrow four-column word breaks still warrant a more
adaptive showcase composition, and breakpoint integration, large-font/RTL/iOS and
performance gates remain open. No publication was made for this candidate.

Follow-up Samsung capture `/tmp/pam-grid-alignment-20260914`, APK SHA-256
`a644f6f0fbaeea1960148decab5b861c1ce3e874bec1c8dda5380c8814df9463`,
confirms equal surface heights in the four-column row. Visual approval is still
withheld: `Create` has a lower text baseline than its neighbors (Android centers
text vertically within its engine-measured box), and the narrow word breaks are
not showcase-quality. Next work must reconcile text measurement/vertical alignment
and complete responsive constraints rather than declaring this screenshot finished.

The baseline issue is corrected in the subsequent PAM Native candidate
`186e4582e80da13fc343826cbf3d2974ecf78dee8273b473f01e0b7e4330f43a`.
Samsung evidence `/tmp/pam-text-baseline-final-20260914` shows all four first-line
titles aligned and the header badge still centered. Chip press feedback also
passed in this candidate. Native's `docs/intrinsic-text-baseline-2026-09-14.md`
records the implementation and remaining metric limitations. Four-column word
breaks and adaptive layout integration are still not approved for showcase release.

## Auto-fit capability and Samsung evidence

The showcase's final variation now requests up to four columns with a 120-unit
minimum, via the new PAM Native GridMinColumnWidth primitive. No labels or fonts
were shortened/shrunk. Shared layout computes count and height together.

`/tmp/pam-grid-autofit-20260914/report.json` records Samsung API 31, font scale
1.1, SHA `6f426e5d0cd206edde27f81620635de09e3f5bae53b45bd99ab266eb6d8c6b2e`.
The dedicated audit scrolled the real screen, asserted 2x2 label positions and
visibility, and retained XML/PNG. The viewed image confirms full Discover,
Create, Review and Ship labels, aligned starts and consistent gutters.

Engine (79), protocol (12), PHP SDK, Android unit tests, protocol parity, UI
material matrix and focused PHPStan 9 passed. Auto-fit plus breakpoint/reversed
configurations are explicitly rejected for now. See `adaptive-grid.md` for the
candidate API. Large font/RTL/iOS/device resize and full responsive migration
remain unapproved. No release or online media publication was performed.

## Authored native span preservation

The fixed-grid composition no longer overwrites an explicitly authored native
`GridSpan` with 1. Unspecified spans still default to one column; GridItem's
explicit span retains precedence. Native breakpoint span properties remain
untouched. Regression cases cover spans 0, 1, 2 and 8 plus GridSpanMd preservation;
the engine remains responsible for clamping a span to actual available columns.
The material matrix passed. This change has not yet been installed in the showcase.

Migration constraint confirmed from current source: UI GridSpec uses breakpoints
640/768/1024/1280/1536, whereas PAM Native's existing responsive span engine uses
600/840/1200/1600. Simply forwarding UI breakpoint spans into native keys would
silently change behavior and lose the sixth tier. Completing the responsive
migration therefore requires an explicit reusable breakpoint contract in PAM
Native, including column counts and gutters, before removing the UI host fallback.
Do not change existing breakpoints or shorten labels to bypass this requirement.
