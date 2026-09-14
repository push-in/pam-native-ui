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

The minimum can be combined with fixed or breakpoint-dependent columns, gutters
and spans in normal row direction. Responsive rows use PAM Native's shared
`GridTemplate`, with thresholds 0/640/768/1024/1280/1536. The native engine resolves
width and row height together; UI does not resize child frames independently.
Reversed/non-row directions still reject `minColumnWidth` explicitly instead of
silently ignoring it. Existing configurations without the minimum are retained.

Samsung verification: `tools/audit-grid-autofit-android.py --serial <device>`
scrolls to the auto-fit example and asserts a fully visible, aligned 2x2 layout
on the narrow test phone. It records raw XML/PNG and APK SHA, restores device
settings, and explicitly does not report full component approval.

Use `--responsive` for the breakpoint specimen and `--font-scale 2.0` for the
large-text scenario. The audit restores the original system font scale. Its
2×2 assertion targets the narrow reference viewport, not every breakpoint or
application-defined grid.

## Large-text Android checkpoint

On emulator-5554/API 36, the responsive specimen passed at font scale 2.0 using
the existing APK `54c5843b003a6bd4325e18e19bd91dc7076545ec77a0212c82d740db33dc3d61`.
Report: `/tmp/pam-responsive-grid-large-text-route-20260914/report.json`.
The final screenshot was inspected: Discover, Create, Review and Ship fit fully
in aligned 2×2 cells with consistent gutters. The earlier auto-fit specimen is
also visible and legible; other fixed-column specimens are not approved by this
capture. The original font scale was restored and read back as 1.0.

The first attempt failed before inspecting cells because the shared route
precondition assumed the Variations heading was above 460 physical pixels.
At 2.0, the exact route title remained at y=168 while Variations reflowed to
y=693. The auditor now scales that heading threshold while retaining the
top-position requirement for the exact route title. Only the failed large-text
scenario was repeated; no APK rebuild was needed. This is layout evidence, not
gesture, RTL, all-breakpoint, iOS or full component approval.
