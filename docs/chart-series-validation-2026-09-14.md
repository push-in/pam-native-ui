# Chart series edge cases

## Bar presentation follow-up

Chart and Sparkline bars now cap their width at 48 logical pixels by default,
including singleton series. Dense bars stay within 58% of their slot rather than
expanding to a forced minimum that can overlap adjacent slots. A subtle zero
baseline makes positive/negative direction explicit. Both renderers implement
these defaults; UIKit source remains uncompiled/unexecuted in this environment.

Configuration is preserved through the PHP API:

```php
$chart::make([
    'type' => 'bar',
    'modelValue' => [-20, 10, -10, 30],
    'barMaxWidth' => 24.0,
    'showBaseline' => false,
]);
```

`barMaxWidth` is a positive logical-pixel maximum, not a forced width. Invalid
or nonpositive values fall back to 48. `showBaseline` defaults to true for bars;
it does not alter line/area series. The axis derives its color from the series.

One 16-second Android build installed APK
`06492d475f7310589179b4c0a61d270064e1377afd2065c5caed6584ec2d8d02`
and cleaned 96.8 MiB. `/tmp/pam-chart-bar-polish-20260914/report.json` verifies
exact feedback for all four mixed bars and the singleton. Both selected images
were viewed: the zero axis is visible and the single bar is narrow and centered.
The PHP matrix passes, including transmission of customization props on Chart
and Sparkline. Dense-series pixels, customized-width device rendering, RTL,
font scaling, performance and iOS still require evidence. This is not full
component approval or publication-ready documentation media.

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
