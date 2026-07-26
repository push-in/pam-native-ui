# Product foundations

PAM Mobile UI 0.3 turns the component catalog into a product layer. The
foundations are shared by template tags and typed PHP, resolve to integer native
properties, and do not introduce JavaScript or a WebView.

## Design language

Use semantic `ColorToken` values rather than raw colors. Light and dark themes
cover background, elevated and sunken surfaces, foregrounds, focus, overlays,
success, warning and information. `DesignTokens` owns radius, typography,
motion duration and density-aware spacing.

Every coded state is an integer-backed enum. This includes `ThemeMode`,
`InterfaceDensity`, `ActionStatus`, `AsyncStatus`, `FormStatus`,
`MotionPreset`, `TabPresentation` and `HapticFeedback`.

## Product primitives

- `AppScreen` composes safe areas, a semantic header, optional actions,
  keyboard avoidance, bounded scrolling and a bottom action slot.
- `ContentState` renders loading, content, empty, error, offline and stale
  states and keeps cached content visible when possible.
- `AsyncButton` owns idle, loading, success and error feedback and blocks
  duplicate submission.
- `FormField` binds a `NativeForm`, validates on blur, exposes server errors,
  announces failures and focuses the first invalid field.

## Typed forms

Define a form with `NativeForm` and attribute-backed rules such as `Required`,
`Email`, `MinLength`, `MaxLength` and `Matches`. Draft persistence uses PAM
`State`; submission transitions are explicit through `beginSubmit()`,
`succeed()` and `fail()`.

Laravel validation errors can be passed directly to `serverErrors()`. Keep
controllers thin: map the API error resource into the form in the component or
application service, then let `FormField` render and announce each error.

## Motion and feedback

`MotionPreset` provides fade, scale, directional slide, success and shake
motion. Android respects disabled animator scale and iOS respects Reduce Motion.
Use `Haptics::trigger()` for semantic selection, impact and result feedback.
Motion must clarify state change; it must not be required to understand it.

## Navigation

Use `Router::stack()` for hierarchical routes and `Router::tabs()` for up to
five top-level destinations. Adaptive tabs become a navigation rail at 840 dp.
Only the selected tab is mounted, while PHP component and persisted state remain
retained. Configure a branded bar with `appearance()` using ARGB integers.

## Generate a complete flow

```bash
pam mobile make:flow Checkout .
php tests/CheckoutFlowTest.php
```

The command atomically creates a typed integer enum, persistent PHP component,
`.pam` template and executable contract test. Existing files are never
overwritten and partial output is rolled back on failure.

## PAM Studio and DevTools

The premium showcase includes a PAM Studio destination containing the complete
component laboratory. Toggle live Android metrics with:

```bash
pam mobile devtools .
```

UIKit hosts can add `PamDevToolsOverlay`, forward `PamRuntime`'s
`onFrameCommitted` callback to `update(_:)`, and call `toggle()` from a debug
gesture. Both overlays report FPS, mount/decode duration, nodes, batches and
full-versus-patch commits.

## Release gates

Before release, run PHP tests, recipe/style audits, PHPStan level 9, Rust tests,
Android Kotlin compilation and device benchmarks. Validate keyboard navigation,
screen-reader labels, live-region announcements, reduced motion, light/dark
themes, narrow phones and adaptive rail widths. iOS compilation belongs on a
macOS CI runner when Swift is unavailable locally.
