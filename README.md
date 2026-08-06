<div align="center">

# PAM Mobile UI

### Material Design 3, rebuilt for a truly native PHP stack.

**The official accessible component system for PAM Native: manually authored
for Android and iOS, retained by design, and free from WebViews, JavaScript,
CSS engines, and metadata-generated imitations.**

[![Documentation](https://img.shields.io/badge/docs-component%20catalog-5b50d6?style=flat-square)](https://push-in.github.io/pam-docs/mobile-ui/overview/)
![Material](https://img.shields.io/badge/Material%20Design-3-6750A4?style=flat-square)
![Components](https://img.shields.io/badge/components-124-22c55e?style=flat-square)
![Platforms](https://img.shields.io/badge/platforms-Android%20%7C%20iOS-2563eb?style=flat-square)
![License](https://img.shields.io/badge/license-Apache--2.0-22c55e?style=flat-square)

**[Documentation](https://push-in.github.io/pam-docs/mobile-ui/overview/) ·
[Component catalog](https://push-in.github.io/pam-docs/mobile-ui/components/) ·
[Themes](https://push-in.github.io/pam-docs/mobile-ui/themes/) ·
[Performance](https://push-in.github.io/pam-docs/mobile-ui/performance/) ·
[Contributing](CONTRIBUTING.md)**

</div>

---

PAM Mobile UI is a retained native Material Design 3 component library for
PAM Native. It exposes 124 mobile `p-*` component parts across 73 manually authored
modules and renders through Android views and UIKit without a WebView,
JavaScript runtime, CSS engine, or Vuetify metadata importer.

## Part of the PAM ecosystem

- [PAM Native core](https://github.com/push-in/pam-native) — required native
  runtime, renderer, navigation and platform modules.
- [PAM Native Nitro](https://github.com/push-in/pam-native-nitro) —
  high-performance offline-first data for native applications.
- [PAM Native documentation](https://push-in.github.io/pam-docs/native/overview/)
  — start here if you are new to native PHP applications.
- [PAM Mobile UI documentation](https://push-in.github.io/pam-docs/mobile-ui/overview/)
  — components, themes, accessibility and performance.

This library does not wrap a web design system and call it native. Its
components, themes, motion, interaction, accessibility semantics, responsive
behavior, and platform contracts are designed for the PAM renderer itself.
The public API stays expressive and familiar; the work underneath stays close
to the operating system.

## What we are building

| Promise | Implementation |
| --- | --- |
| One component language | Typed PHP and declarative `p-*` tags share the same retained tree |
| Real native output | Android Views and UIKit, not HTML or a canvas |
| Material Design 3 | Semantic color, typography, shape, elevation and motion tokens |
| Native performance | Gesture animation and transient interaction stay on the UI thread |
| Accessibility by contract | Semantics are part of component parity and release verification |
| Honest parity | A generated manifest and executable tests gate the published surface |
| Extensible foundations | Themes, directives and native capabilities compose without forking the renderer |

## Principles

- Public templates use only the `p-*` namespace.
- Components are implemented manually for Android and iOS.
- Theme, layout, motion and interaction resolve to compact numeric native
  properties.
- Coded variants use sequential integer enums rather than string protocol
  discriminators.
- Gesture animation and transient state remain on the native UI thread.
- PHP receives requested semantic results, not animation frames.

Product-safe factories for Bottom Sheet, WebView, native media, context menus
and keyframe entrance motion are available through `NativeCapabilities`; see
[`docs/native-capabilities.md`](docs/native-capabilities.md).

## Installation

```bash
composer require pushinbr/pam-mobile-ui
```

PAM Native discovers `pam-native.plugin.json`, links the Android and iOS
sources, and generates the native view registries during `pam mobile prepare`.

## Quick start

```xml
<AppScreen>
        <p-card class="pa-6">
            <Text size="xl">Production dashboard</Text>
            <Text>Retained native Material UI</Text>
            <Text>One component contract, two native renderers.</Text>
            <p-card-actions>
                <p-btn on:press="continue">Continue</p-btn>
            </p-card-actions>
        </p-card>
</AppScreen>
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
    <Text>Interactive native surface</Text>
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

- 73 sequential modules;
- 124 mobile `p-*` components with sequential component IDs;
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

Apache-2.0. See [LICENSE](LICENSE) and [LICENSING.md](LICENSING.md).
