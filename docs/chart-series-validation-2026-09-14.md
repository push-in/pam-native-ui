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

## Bar baseline and selection follow-up

Bars now include zero in the scale, draw negative values below that baseline,
and retain bar geometry for a single value. Selection uses equal-width bar slots
instead of line-point rounding. Android scale arithmetic uses Double intermediates
to avoid overflow when finite Float extremes span the range.

APK `2aae714d35d10de36f5f7744ba5fe18bcb8f3a53760a9b359fede70021238d90`:
`/tmp/pam-ui-chart-bars-final-20260914/report.json` records successful taps on
all four mixed bars (-20, 10, -10, 30) and the single bar (42), with exact
index/value feedback. The mixed-bars-selected image was inspected: the smallest
positive bar remains visible, mixed bars extend to opposite sides of zero and
the singleton is a bar. PHP render matrix passes; Android build/install succeeds.

The first two attempts targeted Chart while the new samples were only in
Sparkline. They do not establish interaction coverage. Both catalog sections
now include these samples. iOS source has equivalent baseline/selection changes,
but remains uncompiled and untested here. This is not full Chart approval:
dragging, accessibility navigation, RTL, performance and complete visual polish
still need evidence. In particular, the mixed chart has no visible zero axis and
the singleton occupies 58% of the chart width; review those presentation choices
in the family-wide visual pass rather than treating this capture as final media.
