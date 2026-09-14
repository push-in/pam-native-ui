# Grid selection batch

UI-owned fixes shared by Data Table, Virtual Data Table and Data Grid:

- Disabled, loading and read-only selection controls have no press callback and
  explicitly disable the native control.
- Disabled rows cannot be toggled or added by select-all. Existing protected
  selections survive bulk select/deselect.
- Selection columns use 48 dp; remaining columns share available width, using
  existing native Row/View layout instead of the equal-width grid composition.
- Showcase preserves initial model values and provides protected, read-only and
  disabled examples. Its audit route now retains the actual catalog.

Matrix coverage includes three table families × six lock aliases, protected bulk
selection and selection-column geometry. PHPStan level 9 passed before the final
geometry assertion was added; the matrix passed after that assertion.

Android API 36 emulator, font scale 1.0:
`/tmp/pam-ui-grid-selection-width-20260914/report.json`, APK SHA-256
`d5d1ca1e08d1479be60ea74526670752bb0c5f6459c7f2c454d3018cc7ad5bc7`.
Bulk selection/deselection, retained protected selection, read-only rejection and
disabled rejection passed. The protected-selection PNG was inspected: the compact
checkbox column and adjacent header/cell text align. Script:
`tools/audit-grid-selection-android.py`.

Earlier evidence remains separate: the initial audit omitted catalog scenarios
(`/tmp/pam-ui-grid-selection-20260914`); the corrected catalog audit passed behavior
but exposed an excessively wide selection column
(`/tmp/pam-ui-grid-selection-catalog-20260914`). The final candidate fixes that width.

Not full approval: large fonts, multiple data columns, Samsung, iOS, assistive
technology and performance remain unverified for this candidate. The loading
showcase still leaves excessive empty space inside its fixed-height viewport;
these captures are diagnostic evidence, not publication-ready promotional media.

## Row-height follow-up

Cells now use the same `rowHeight` / `itemHeight` as the virtual list. Density
defaults are consistent (44 compact, 48 comfortable, 52 default). The showcase
adds 72-dp rows and compact 144-dp loading/empty viewports. Matrix regressions
verify explicit heights and aliases; updated PHPStan level 9 passes.

APK `f39861715e7883ca0c5c87b47893ac94d2f05d6f67a7505a77588b2c1660ce2d`:
`/tmp/pam-ui-grid-row-layout-20260914/report.json` records captures, not interaction
approval. The comfortable-row capture was inspected: three data columns align
across the header and visible rows; taller cells are rendered consistently. Its
320-dp scrolling viewport cuts through the next row at the lower boundary, as
expected for a scrollable list. Large-font and scroll interaction coverage remain
pending. Empty/loading examples no longer reserve the original 360-dp viewport.

Scroll follow-up on the same APK: `/tmp/pam-ui-grid-scroll-20260914/report.json`
and `/tmp/pam-ui-grid-scroll-large-20260914/report.json` confirm the last tall row
(`Studio 6`) is reachable inside the native grid viewport at font scales 1.0 and
2.0. Both final captures were inspected. Fully visible rows remain vertically
readable; the enlarged price column has little spare horizontal room. This is
coverage of the supplied short dataset, not arbitrary long values, every density,
or overall component approval. Device settings are restored after each run.

## Long values and column spacing

All generated data rows now use native Row/View composition, not the custom grid
which resets cell widths after internal layout. Text is constrained to the cell,
uses explicit tail ellipsis, and retains the complete accessible label.
The showcase includes long workspace names and monetary values.

Initial APK `50bfee08ccbc7ba0b0bbda745064bc18124477bbb19b50ffd77b4ed7be82bdad`
preserved accessible values at fonts 1/2 but its custom-grid composition left text
touching the next column (`/tmp/pam-ui-grid-long-20260914` and `...-long-large-20260914`).
Follow-up APK `2a7a30ee66368aaeeca7b847a4e5c64cb7f687a05cab1f918e42ba2f55ca7758`
uses native rows. `/tmp/pam-ui-grid-row-long-20260914/report.json` confirms complete
accessible values at font 2.0; its PNG was inspected and shows separated columns.
There is substantial truncation at this font scale. A visual way to inspect full
values is still needed; accessible labels alone are not a complete solution.
No full approval, TalkBack verification or publication is claimed.

## Inspect full cell values

Set `inspectable => true` on Data Grid, Data Table or Virtual Data Table to make
data cells open the existing PAM Native system alert with their column title and
complete value. Default behavior is unchanged; disabled/loading tables do not
offer inspection. Read-only tables can still be inspected without changing data.
No new native implementation or CLI capability was needed.

Example: `PDataGrid::make(['headers' => $headers, 'items' => $items, 'inspectable' => true])`.

APK `d09b8c036ce426b9945462188cdadbaf9710aa67fbac602826b0054431abf8f2`:
`/tmp/pam-ui-grid-inspect-20260914/report.json` verifies tapping a long-name cell,
showing the exact full text, dismissing with OK and returning to the same cell on
API 36 emulator at font 2.0. The dialog capture was inspected: title, full value
and dismissal control are readable. This closes the visual full-value access gap
for the tested opt-in example, not every table state or platform.
