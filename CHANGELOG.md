# Changelog

## 2.1.0 - 2026-11-17

Measured, not eyeballed: every component and event is driven on a device and
its geometry is checked to the pixel. Requires PAM Native 1.52.

- **Device E2E for every component and event.** The showcase has an Events
  screen that logs every event as PHP receives it. `tools/e2e_android.py`
  drives every input, button, chip, card, list item, alert, tab, segmented
  control, stepper, field focus/submit/blur, sheet (apply, back, scrim,
  swipe), dialog (confirm, cancel, back) and snackbar (action, timeout), in
  light and dark, and fails unless PHP state matches. `tools/e2e_ios.py`
  emits the same scenarios for the PAM Native iOS UI driver.
- **Geometry checks at every state** (`tools/geometry.py`, from PAM Native's
  layout snapshot): touch targets of at least 48dp (44pt on iOS), nothing
  under the status, navigation or gesture bar, no truncated or overlapping
  text, siblings that share an edge share its pixels, even spacing, shared
  baselines and pixel-centred icons.
- **Golden screenshots** (`tools/golden.py`, documented tolerance) and a
  **zero-errors gate** (`tools/zero_errors.py`: no warning or error from the
  app in logcat, also after a multi-file hot reload). CI runs all of it on an
  Android emulator (`.github/workflows/e2e.yml`).
- `p-segmented`: each segment is a 48dp touch target; the visible pill is
  40dp with 4dp gaps.
- `p-sheet`, `p-select` and `p-date-input`: the last row keeps its spacing
  above the gesture bar or home indicator (it used to sit flush on it).
- `p-select`: long option lists (more than 8) render with a virtualized list
  in the sheet; the search field is 48dp tall.
- Fixed: `testId` on `p-btn`, `p-card`, `p-list-item`, the fields and the
  choices reaches the native view (components wrote `testID`, which PAM
  Native dropped before 1.52).
- Fixed through PAM Native 1.52: the text you just typed reaches PHP before
  `@submit`, `@blur` or a button press; icons in buttons are centred to the
  pixel at fractional densities.

## 2.0.0 - 2026-11-17

A rebuild. 1.x could not get a value from an input to PHP: `p-model` on any
`p-*` input stopped the app at boot, `@change` listeners were dropped and
`on:change` landed on a wrapper that never fires (see
[docs/audit-1.0.md](docs/audit-1.0.md)). 2.0 is a set of PAM single-file
components drawn by the PAM Native renderer, with two-way binding on every
input. Upgrade notes: [UPGRADE.md](UPGRADE.md).

- **Two-way binding.** `p-model` on every input (`p-text-field`,
  `p-textarea`, `p-checkbox`, `p-switch`, `p-radio-group`, `p-select`,
  `p-date-input`, `p-slider`, `p-stepper`, `p-segmented`, `p-chip`, `p-tabs`)
  and overlay (`p-sheet`, `p-dialog`, `p-snackbar`), including
  `p-model="$form->field"` on a `NativeForm`. Requires PAM Native 1.51.
- **Forms.** `InteractsWithForm` (`error()`, `validateField()`,
  `submitForm()`, `submitting()`, `formFailed()`), `p-form` with an announced
  error summary, and the `Accepted`, `Between`, `Pattern` and `OneOf` rules.
- **Predictable events.** Each component declares its events
  (`#[Event('change', 'string')]`); `@change` and `on:change` are the same
  event; an unknown event or prop fails with the component and prop name.
- **Typed components.** Every prop is a typed PHP 8.5 constructor parameter.
- **Theme.** PAM brand (Instrument Sans, `#0A0A0A`/`#161615`/`#3E3E3A`,
  `#EDEDEC`/`#A1A09A`, accent `#F53003`) in light and dark, Material 3 shapes
  on Android and iOS shapes on iOS, 48 dp targets, UI-thread motion that
  honours reduce motion.
- **One renderer.** The 9,400-line Android host and 4,000-line iOS host are
  gone: no Kotlin, Swift or native view in the package, so both platforms
  share one implementation and PAM Native's renderer.
- **Showcase** (`examples/showcase`): every component, a validated sign-up
  form, a 500-row virtualized list, sheets, dialogs and snackbars.
- **Tests:** `tests/run.php` (85 assertions through the PAM renderer) and
  `tools/e2e_android.py` (14 input round-trips on a device).
- **Removed:** the 1.x Material facades (`Pam\MobileUi\Material\*`), the
  generated renderer, themes API and 60+ specialised tags. `PamUI::mode()` and
  `ThemeMode` remain as deprecated shims, and `<AppScreen>` renders
  `<p-screen>`.

## 1.0.9

- Add typed PHP constructors for common Material components while preserving
  the raw property escape hatch.
- Generate the UIKit component ID registry from the same catalog as Android.
- Align UIKit calendar and file-tree selection, menu selection, input slot
  actions, slider labels and tick styling, tooltip timing, and native property
  aliases with the Android host.
- Add UIKit interaction tests for the newly aligned behaviors.
- Expand the premium native catalog to 114 public components across 92
  modules, with Android, iOS, accessibility, style and render gate coverage.
- Redesign the showcase as a polished five-destination product and component
  studio with direct canvas presentation, responsive spacing and native PAM
  navigation.
- Add interaction audits, screenshots and recording provenance for every
  component while keeping physical-device approval distinct from emulator
  candidates.
- Require PAM Native 1.0.27 so currency and masked fields retain rapid input
  without cursor crashes or dropped keyboard events.
- Use the optional drawer and WebView plugins that left the PAM Native core in
  1.46 (`Pam\Native\Drawer\*`, `Pam\Native\WebView\WebView`). They are
  suggested, not required: `PNavigationDrawer` and `NativeCapabilities::web()`
  name the exact `composer require` when the plugin is missing.

## 1.0.8

- Dispatch the certified Composer distribution explicitly after GitHub Release
  publication, avoiding GitHub token event-recursion suppression.

## 1.0.7

- Normalize ZIP entry timestamps and Unix metadata from the source commit so
  subtree archives remain byte-for-byte reproducible across clock seconds.

## 1.0.6

- Make the GitHub Release publisher depend directly on the central ecosystem
  compatibility gate, in addition to its transitive artifact dependencies.

## 1.0.5

- Make the native release workflow the single authoritative tag compatibility
  gate, with Composer publication following the published GitHub release.

## 1.0.4

- Keep Android screenshot and GIF evidence in the source repository and public
  documentation while excluding those assets from Composer distributions.
- Sequence release publication before Composer/Packagist publication so tag
  workflows cannot compete for the shared ecosystem compatibility gate.

## 1.0.3

- Publish the complete Android showcase with verified screenshots for all 84
  public components and interaction recordings for representative flows.
- Fix Android layout, contrast, touch-target, virtualized-table and expansion
  panel behavior found during API 26 and API 36 emulator validation.
- Add reproducible visual-audit tooling and support Composer-installed PAM
  Native SDK layouts in the analysis bootstrap.

## 1.0.0

- Certify the complete accessible component system against the stable PAM
  Native 1.x ABI, retained renderer, CSS-to-native compiler and capability
  boundary.
- Make clean `pam init --template native-ui` plus Android launch a release gate.
- Keep UI independent from every sibling plugin and from any backend package.

## 0.10.1

- Certify Quantum UI against PAM Native 0.10.0 and its dependency-granular,
  high-refresh-rate renderer without changing the public component API.

## 0.9.0

- Require and certify PAM Native 0.9.1 so Quantum UI applications use the
  matching Style Engine protocol, Android renderer and UIKit host.
- Align the Composer graph, plugin contract, kitchen-sink fixture and all
  Android/iOS release boundaries to one immutable PAM Native revision.

## 0.8.2

- Publish the complete Quantum UI release from a version-consistent source
  archive so Composer, Android and iOS artifacts share the same immutable
  version.
- Keep the product-focused documentation and the certified PHP 8.5, Android
  and UIKit compatibility matrix introduced after 0.8.0.

## 0.8.0

- Adopt PAM Native's declarative Language 2 syntax with `{{ }}`, bound
  `:properties`, `@events` and two-way `p-model` fields.
- Follow the real Android and iOS system appearance through PAM Native while
  preserving explicit light, dark and custom theme overrides.
- Require PAM Native 0.8.5 so every UI application receives deterministic
  first-frame defaults and durable Android runtime installation.

- Add accessible product status banners for information, success, warning,
  error and progress feedback.
- Add adaptive metric cards with semantic, non-color-only trend announcements.
- Require byte-reproducible PHP, Android and iOS packages with attested,
  machine-readable evidence before release publication.
- Enforce separate iOS, Android and PHP release-size ceilings with attested
  reports reverified from downloaded artifacts before publication.
- Add official PAM light/dark themes and executable WCAG contrast measurement.
- Reject unknown native icon names instead of silently rendering blank glyphs.
- Redesign the PAM Studio entry screen around the real component workbench.
- Certify PHP 8.4/8.5 against both lowest and latest PAM Native dependency
  graphs, executing the full suite against each resolved vendor package after
  dependency dry-runs.
- Compile and test Android and UIKit sources against both the immutable PAM
  Native v0.8.5 minimum and the current certified revision.
- Block tag publication until the exact release ref repeats every Composer
  graph plus the minimum Android and UIKit builds.
- Restrict release credentials to read-only compatibility jobs, attestation-only
  producers and a single release-writing publisher.

## 0.4.1

- Publish the current Apache-2.0 community distribution.
- Require the supported PAM Native 0.6 release line.
- Include the latest Android portal menu ownership and interaction fixes.

## 0.4.0

- Ship the curated mobile-first Material component system with 127 public
  component parts across 75 modules, native Android and UIKit renderers,
  complete variants, semantic Vuetify colors, density, elevation and motion.
- Add the route-based native showcase with grouped drawer navigation, safe-area
  handling and physical-device interaction coverage.
- Remove desktop-only, runtime-owned and duplicate primitives in favor of PAM
  Native Text, Row, Column, Spacer, Animated and scoped runtime configuration.
- Add optimized forms, overlays, navigation, data display, feedback and media
  behavior with accessibility and reduced-motion support.

- Document the production capability lifecycle without adding a parallel
  renderer or system-module abstraction.
- Add additive `NativeCapabilities` factories for Bottom Sheets, WebView,
  video/audio, native interaction regions and declarative entrance motion.

## 0.3.0

- Add PAM's expanded semantic design language, density, typography, radius and
  motion tokens for professional light and dark products.
- Add `AppScreen`, `ContentState`, `AsyncButton` and `FormField` product
  primitives with accessible loading, empty, error, offline and stale states.
- Ship the premium five-destination showcase and PAM Studio component
  workbench.
- Integrate typed PAM Native forms, adaptive tabs, semantic motion, haptics and
  lazy destination mounting.
- Document flow generation, DevTools, accessibility and production performance
  gates.

## 0.2.1

- Ship the complete PamUI component and icon facade catalog under PAM-owned
  naming, tokens and themes.
- Add native Android interaction behavior for overlays, navigation controls,
  inputs, selection controls, grids, tables, media and AI components.
- Add responsive 12-column grid integration and rich virtualized component
  cells through PAM Native.
- Add functional instrumentation coverage, accessibility semantics, recipe
  matrices, PHPStan level 9 and reproducible performance evidence.
- Publish a community-facing Composer distribution compatible with PAM Native
  0.2.x.
