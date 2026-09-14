# Table selection and loading consistency

PDataTable, PDataTableVirtual and PDataGrid now normalize `isLoading` before
composition, like `loading`; an explicit `loading=false` retains precedence.
Loading copy, removal of body rows and blocked header selection consequently
follow the same path. Selection also reuses the UI mutation guard for consistent
readonly alias precedence.

Selectable rows now have a minimum height of 48 dp, including compact density and
smaller explicit rowHeight/itemHeight values. The same resolved height reaches
the native virtual list and composed checkbox cells. Non-selectable compact
tables retain their existing density. This is UI touch-target policy built on
existing PAM Native geometry properties, not a new native implementation.

The material matrix passed, including loading-alias selection guards in all
three table variants and nine compact/explicit-height selection render cases.
Focused level-9 PHPStan passed for the renderer, isolated regression file and
showcase route. One optimized emulator build took 10 seconds; cleanup removed
96.8 MiB of regenerable artifacts. The protected-selection showcase now uses
compact density so the Android audit checks the actual minimum-size case.

Android audit passed on emulator-5554/API 36, font scale 1.0, APK
`8386ed7b004b491fd0af80abe94112afbce7033b41d2955aa550bc4878fcc23a`.
`/tmp/pam-grid-compact-selection-20260914/report.json` confirms physical bounds
converted with the device density are at least 48 dp in both dimensions for all
four compact selection controls. Actual taps selected/deselected the two mutable
rows while retaining the protected row; readonly/disabled tables rejected taps.
The protected-selection capture was inspected: checkbox marks and row text remain
aligned. The fixed-height viewport retains intentional empty space below its rows.

This device flow validates Data Grid; shared PHP regressions cover the other two
table variants. Loading alias behavior is PHP-tested, not separately device-tested
here. No iOS/device-wide approval or publication is claimed by this batch.
