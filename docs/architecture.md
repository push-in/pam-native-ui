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
