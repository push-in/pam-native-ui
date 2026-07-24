# Architecture

## Goals

PAM Mobile UI has three execution layers:

1. **PHP composition** creates immutable element trees and resolves semantic
   tokens, variants, sizes and class overrides once per changed subtree.
2. **PAM binary protocol** diffs stable node identities and transfers only
   bounded scalar mutations.
3. **Android native hosts** keep transient interaction state on the UI thread:
   press/focus state, drag position, scrolling, overlay placement, selection,
   text editing and interruptible animation.

This keeps the application API familiar to PHP developers while avoiding
per-frame PHP calls. PHP receives semantic events only when application state
must change.

## Rendering policy

| Kind | Implementation |
| --- | --- |
| Static layout and typography | PAM native primitives, eligible for view flattening |
| Buttons, fields and switches | PAM primitives with native state/ripple/focus |
| Compound static components | PHP composition over primitives |
| Gesture-heavy controls | `pam.mobile_ui.host` with Android-owned state |
| Modal, sheet, menu and popover | Android overlay/window host |
| Long collections | PAM virtualized native lists |
| Theme and variants | Integer enum IDs resolved to immutable token tables |

Custom native hosts may contain normal PAM children. The renderer mounts these
children into a `ViewGroup` returned by the plugin factory; this is required for
compound controls whose content is still authored declaratively in PHP.

Controlled compound overlays keep their trigger subtree mounted when
`open=false`. `Select`, `BottomSheet` and `ModelSelector` render a stable
layout root plus a dedicated portal/content child backed by PAM's native
`Modal` node. Only that child owns the Android `Dialog`/sheet window. Root event
handlers are copied to the native child host at composition time, so dismiss,
selection and drag-end events still target the application callback without a
runtime lookup or an extra bridge hop.

Closed portal/backdrop/content parts become `GONE`; their native controller
also disables click, focus, accessibility and gesture handling. Touches
therefore continue to the trigger and surrounding app. Opening the same keyed
window enables UI-thread positioning, focus capture and entrance motion without
recreating the trigger. Pure overlays such as `Modal`, `AlertDialog`,
`Actionsheet`, `Drawer`, `ImageViewerContent` and `Portal` use the native window
directly because they do not need to leave an inline trigger mounted.

## Utility class pipeline

PAM's template registry accepts lazy class resolvers from plugins. PAM Mobile UI
uses that extension to compile application classes with the same
`TailwindStyleCompiler` used for upstream recipes. The result is validated
against `PropKey` and applied to the immutable element:

1. exact theme/application class lookup;
2. mobile utility compiler lookup;
3. explicit template error when no resolver recognizes the class;
4. numeric/string scalar properties encoded by the normal binary tree protocol.

The resolver never runs on Android and is not part of a frame loop. Platform
conditions that intentionally do not apply may resolve to an empty property map;
unknown utilities cannot silently disappear.

## Event payload policy

Controls whose public event has one semantic value emit that scalar directly:
slider progress, tab value, calendar/picker date, checked state and accordion
expanded state. A `.pam` handler therefore receives the same compact string
shape as a core input event, without decoding a map:

```php
public function selectTab(string $value): void
{
    $this->tab = $value;
}
```

Multi-field native lifecycle events such as dismiss, zoom and image navigation
remain bounded binary maps on `on:event`. This keeps ordinary form/state code
simple while preserving structured detail for advanced native interactions.
Tab trigger values are stored as scalar semantic tags on their Android views,
so reordering or styling a tab never changes the value delivered to PHP.

## Threading

- All view creation, updates, releases and Android view mutations run on the
  main looper.
- Gesture progress and animations never cross the PHP bridge per frame.
- Binary event payloads are bounded to one MiB and use integer discriminators.
- Expensive work must use a plugin-owned executor and post only its final UI
  mutation to the main looper.
- A component must stay within 8.33 ms at 120 Hz and 16.67 ms at 60 Hz during
  interaction; benchmark gates use frame percentiles rather than averages.

## Accessibility baseline

- Interactive Android targets are at least 48 × 48 dp with 8 dp separation.
- Text follows system font scale and cannot encode meaning with color alone.
- Roles, labels, hints, disabled, selected, checked and expanded state are
  exposed to TalkBack.
- Modal focus is trapped and restored; dismiss/back remains available.
- Motion respects system animation scale and avoids required gesture-only paths.
- Light and dark semantic pairs meet WCAG AA contrast.

## Compatibility definition

Parity is behavioral. Public anatomy and variant names remain recognizable to
gluestack-ui users, but PAM uses integer-backed enums and Android conventions.
React-only implementation details, DOM-only props and CSS utility internals are
not copied into the runtime.
