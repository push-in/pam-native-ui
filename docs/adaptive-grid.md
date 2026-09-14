# Content-friendly grid columns (candidate)

```php
PResponsiveGrid::make(
    ['columns' => 4, 'minColumnWidth' => 120, 'columnGap' => 12, 'rowGap' => 12],
    ...$cells,
);
```

The column count is a maximum when `minColumnWidth` is supplied. Native layout
reduces it to fit the available content width, preserving authored padding,
font sizes and labels. Omit the minimum for fixed-count behavior. Choose a
minimum appropriate to your content and accessibility sizing requirements;
this value is in logical units, not font-scaled units.

Responsibility: PAM Native owns the minimum-width primitive, measurement and
reflow. PAM Native UI only maps the visual component API onto those capabilities.
This candidate requires PAM Native protocol property 466 and is not published.

Currently the minimum can be combined with fixed column/gutter/span values and
normal row direction. Mixing it with breakpoint maps or reversed direction is
explicitly rejected instead of silently ignoring the minimum. Migration of those
combinations remains open; existing configurations without the minimum are retained.

Samsung verification: `tools/audit-grid-autofit-android.py --serial <device>`
scrolls to the auto-fit example and asserts a fully visible, aligned 2x2 layout
on the narrow test phone. It records raw XML/PNG and APK SHA, restores device
settings, and explicitly does not report full component approval.
