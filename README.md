# PAM Mobile UI

PAM Mobile UI is a retained native Material Design 3 component library for
PAM Native. It exposes 163 `p-*` component parts across 103 manually authored
modules and renders through Android views and UIKit without a WebView,
JavaScript runtime, CSS engine, or Vuetify metadata importer.

## Principles

- Public templates use only the `p-*` namespace.
- Components are implemented manually for Android and iOS.
- Theme, layout, motion and interaction resolve to compact numeric native
  properties.
- Coded variants use sequential integer enums rather than string protocol
  discriminators.
- Gesture animation and transient state remain on the native UI thread.
- PHP receives requested semantic results, not animation frames.

## Installation

```bash
composer require pushinbr/pam-mobile-ui
```

PAM Native discovers `pam-native.plugin.json`, links the Android and iOS
sources, and generates the native view registries during `pam mobile prepare`.

## Quick start

```xml
<p-app>
    <p-theme-provider mode="system">
        <p-card class="pa-6">
            <p-card-title>Production dashboard</p-card-title>
            <p-card-subtitle>Retained native Material UI</p-card-subtitle>
            <p-card-text>
                <p-text>One component contract, two native renderers.</p-text>
            </p-card-text>
            <p-card-actions>
                <p-btn on:press="continue">Continue</p-btn>
            </p-card-actions>
        </p-card>
    </p-theme-provider>
</p-app>
```

Templates are compiled once. Android and iOS receive resolved integers,
floats, booleans and bounded payloads rather than markup or style strings.

## Native directives

```xml
<p-card
    p-ripple
    p-click-outside="close"
    p-intersect="visibilityChanged"
    p-resize="resized"
    p-touch-start="touchStarted"
    p-touch-move="touchMoved"
    p-touch-end="touchEnded"
>
    <p-text>Interactive native surface</p-text>
</p-card>
```

- `p-ripple` uses `RippleDrawable` on Android and a UIKit state layer.
- `p-click-outside` observes the native root without consuming child input.
- `p-intersect`, `p-mutate` and `p-resize` emit only changed geometry.
- `p-scroll` uses native scroll delegates and display-frame coalescing.
- `p-touch-*` reports logical local/page coordinates.

All observers and recognizers detach on update, unmount, hot reload and runtime
shutdown. Ripple and motion do not cross the PHP boundary.

## Themes

```php
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\Themes;

PamUI::theme(
    Themes::light()->withColors([
        ColorToken::Primary->value => Color::rgb(0, 95, 184),
    ]),
    Themes::dark()->withColors([
        ColorToken::Primary->value => Color::rgb(167, 201, 255),
    ]),
);
```

System, light, dark and custom themes resolve per provider subtree. Components
consume semantic MD3 color, typography, shape, elevation and motion tokens.

## Verification

```bash
composer generate:material
composer test
composer test:recipes
composer analyse
composer release:check
```

`resources/material-parity.json` is the release gate for the new generation.
It is generated from the manual specification and requires:

- 103 sequential modules;
- 163 unique `p-*` components;
- Android and iOS targets;
- `metadataImport=false`;
- exact equality with `MaterialComponentMap`.

Android contracts run as unit tests, emulator instrumentation and physical ADB
tests. UIKit contracts build and test through Xcode on a macOS runner before a
release can be published.

## Documentation

- [Authoring](docs/authoring.md)
- [Component catalog](docs/catalog.md)
- [Parity contract](docs/parity.md)
- [Product foundations](docs/product-foundations.md)
- [Architecture](docs/architecture.md)
- [Performance evidence](docs/performance.md)

## License

BUSL-1.1. See [LICENSE](LICENSE) and [LICENSING.md](LICENSING.md).
