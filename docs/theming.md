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

## Your brand

Give the app its own palette without touching the components: write a CSS
file that declares only `:root` custom properties and point `pam-native.json`
at it.

```json
"theme": {"tokens": "src/theme.css"}
```

```css
/* src/theme.css */
:root {
    --p-accent: #166534;          /* light */
    --p-accent-dark: #4ade80;     /* dark */
    --p-on-accent: #ffffff;
    --p-on-accent-dark: #052e16;
    --p-radius: 16px;
}
```

PAM resolves the tokens when it compiles the components (PAM Native 1.53.9
or later), so a themed app renders exactly as fast as the default one, and an
app without the file is not affected. Edits recompile the components and hot
reload like any other change. The file holds 64 KiB at most and may only
declare `:root { --token: value; }`: any selector fails the build, so a theme
can change colours and sizes but never a component's structure. Set the same
backgrounds in `pam-native.json` `appearance` so the first frame matches.

Every token, each colour with a `-dark` twin:

| Group | Tokens |
| --- | --- |
| bg | `--p-bg` |
| surface | `--p-surface`, `--p-surface-muted`, `--p-surface-pressed` |
| border | `--p-border`, `--p-border-strong` |
| text | `--p-text`, `--p-text-muted`, `--p-text-subtle` |
| accent | `--p-accent`, `--p-accent-pressed`, `--p-accent-soft`, `--p-accent-soft-pressed`, `--p-accent-text` |
| on | `--p-on-accent` |
| danger | `--p-danger`, `--p-danger-soft` |
| success | `--p-success`, `--p-success-soft` |
| warning | `--p-warning`, `--p-warning-soft` |
| info | `--p-info`, `--p-info-soft` |
| scrim | `--p-scrim` |
| radius | `--p-radius-sm`, `--p-radius`, `--p-radius-lg`, `--p-radius-xl` |
| touch | `--p-touch` |
| motion | `--p-motion-fast`, `--p-motion` |

`pam init --template product` generates this file from its design-token
contract (`composer theme` regenerates it).

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
