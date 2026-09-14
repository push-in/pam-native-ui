# Chart series edge cases

Android now renders singleton series as a centered point, centers constant series,
filters non-finite input and caches parsed values until the source changes. iOS
source has matching singleton/constant behavior and finite filtering; Swift/UIKit
compilation and runtime validation have not been performed in this environment.
PHP Chart events reject malformed payloads, negative/fractional indices and
non-finite values. Kotlin unit tests and the PHP render matrix pass.

APK `5c63663c40d04334ae590b257e0d3c7c1607ec9b0b21c934bafeb003600d48d3`:
`/tmp/pam-ui-chart-series-20260914/report.json` verifies tapping singleton and
constant series and receiving value 42 beneath the corresponding example.
The constant-selected capture was inspected: singleton point, constant line,
selection marker and feedback are visible. This is scoped emulator evidence,
not complete approval, performance measurement or publication-ready media.

Remaining finding: bar charts normalize against the minimum data value, making
the smallest positive bar disappear. Correct baseline handling for positive,
negative and mixed values is required. Single-value bar semantics also need
separate treatment; the current singleton fallback is a point for every style.
