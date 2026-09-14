# Navigation and search integrated checkpoint

`/tmp/pam-navigation-search-batch-20260914.json` completes eight scoped scenarios
on emulator-5554/API 36, without rebuilding candidate
`8ba90170d03b28ca91f9578d6681906040c4d4d9406b39368dd5546eaacc1c97`.
All eight pass their current assertions; this is not eight component approvals.

| Component | Actual scope in this run |
| --- | --- |
| App Scaffold | Route health and static capture only |
| Navigation Bar / Rail | Generic destination press and resulting visual/hierarchy change |
| Navigation Drawer / Bottom App Bar | Generic enabled target press and resulting change |
| Search Bar | Controlled clear, stable bounds, native text entry |
| Command Palette | Open/close, not keyboard filtering or command execution |
| Tree Select | Generic selection change, not the full tree contract |

Navigation Bar, Rail, Drawer, Bottom App Bar and Scaffold captures were inspected.
Bar/Rail indicators and labels are aligned in the captured standard-size examples.
Rail's expanded example continues below the viewport. Drawer capture is closed:
it cannot prove open-drawer layout or selected destination retention.

Scaffold still displayed decorative rounded preview cards. Its showcase source
now uses the page Background token and removes the border/radius. This source
change follows the no-decorative-card rule and postdates the APK above; device
verification must occur with the next changed-showcase candidate, not by relabeling
this batch as proof. Matrix coverage remains code-only.

Remaining concrete gaps for the next implementation batch:

- Scaffold's Keyboard aware variation contains no editor; it cannot demonstrate
  IME avoidance. Add a real input/interaction demonstration before calling it tested.
- The partial Keyboard aware capture shows a clipped LIVE badge. Determine whether
  this is viewport clipping or incorrect child geometry; do not approve that media.
- Command filtering/selection has dedicated historical portrait/landscape evidence,
  but the integrated branch still only opens/closes. Preserve this distinction.
- Navigation destination identity, disabled rejection, reopening and selected-state
  retention need stronger integrated assertions; pixel changes alone are insufficient.
- Full layouts, large text, TalkBack, motion/performance and iOS remain unapproved.

No release gates were changed and no publication occurred.
