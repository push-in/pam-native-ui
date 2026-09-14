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
