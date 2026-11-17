# Theme, light and dark

## Brand

| Role | Light | Dark |
| --- | --- | --- |
| Background | `#FDFDFC` | `#0A0A0A` |
| Surface | `#FFFFFF` | `#161615` |
| Muted surface | `#F5F5F3` | `#1F1F1D` |
| Border | `#E3E3E0` | `#3E3E3A` |
| Text | `#1B1B18` | `#EDEDEC` |
| Muted text | `#706F6C` | `#A1A09A` |
| Accent | `#F53003` | `#FF4433` |
| Danger / success / warning / info | `#D92D20` / `#12805C` / `#B54708` / `#175CD3` | `#F97066` / `#47CD89` / `#FDB022` / `#84CAFF` |

Typeface: Instrument Sans 400, 500 and 600 (SIL OFL 1.1, in
`resources/fonts`). Copy the files to your project's `assets/fonts/`;
`pam init --template native-ui` does it for you.

## Light and dark

The components follow the system appearance. Override it from PHP:

```php
use Pam\Native\AppearanceMode;
use Pam\NativeUi\NativeUi;

NativeUi::appearance(AppearanceMode::Dark);   // or Light, System
```

PAM Native persists the choice natively and restyles every component in
place (no remount, no flash). Give the window the same colours in
`pam-native.json` so the first frame matches:

```json
"appearance": {
    "defaultMode": "system",
    "light": {"background": "#FDFDFC"},
    "dark": {"background": "#0A0A0A"}
}
```

`p-screen` sets the status bar icons for the current appearance.

## Platform feel

| | Android | iOS |
| --- | --- | --- |
| Buttons | Full pill, 48 dp | 12 pt radius, 50 pt |
| Switch | Material 3 (outlined off track, growing thumb) | iOS proportions and shadow |
| Fields | 12 dp radius, 16 sp text | 10 pt radius, 17 pt text |
| Titles | 32 sp semibold | 34 pt bold large title |
| Section | Uppercase overline, outlined group | Inset grouped |

Styles use `@media (platform: ios)` and `@media (prefers-color-scheme: dark)`
compiled at build time; nothing is parsed while rendering.

## Motion

Press feedback (scale 0.98), the switch thumb, the tab indicator and the
slider run on the UI thread. `prefers-reduced-motion` turns transitions off.

## Your own screens

Style your own components with scoped CSS (`<style scoped>`) as PAM Native
recommends; the package's theme tokens are compiled into its components and do
not leak into yours.
