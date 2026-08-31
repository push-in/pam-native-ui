# Changelog

## Unreleased

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
