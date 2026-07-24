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

## Native data views

`FlatList` and `VirtualizedList` keep the PAM core packed-string adapter:
Android recycles `TextView` rows, applies the fixed estimated row height and
detects the end threshold without calling PHP for visible-item rendering.
`SectionList` uses the equivalent packed section/header adapter. The mobile UI
facades only normalize familiar prop names (`rowHeight`, `itemHeight`,
`estimatedItemSize`, indicator and scrolling flags) onto those core
properties, so the optimized path does not gain an extra host or bridge layer.

This path intentionally accepts scalar rows. Rich heterogeneous rows use normal
PAM keyed composition or a purpose-built plugin whose recycling contract
matches that content; pretending a PHP `renderItem` closure can execute during
Android scrolling would reintroduce the frame-boundary traffic this
architecture removes.

`Table` remains authored as compound PAM children. A lightweight table root and
row host discover mounted text cells after structural changes and assign
Android collection row/column metadata. The metadata cache is invalidated only
when rows or cells change, so steady layout performs no tree walk, allocation or
bridge event.

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

Android Back is intercepted by PAM's window host and delivered through the same
bounded native dismissal event as backdrop/drag dismissal. Controlled overlays
remain visible until the application publishes `open=false`; after the window
closes, Android restores the view that held focus before presentation.

`BottomSheet`, `Actionsheet` and `Select` use the same anatomy-aware controller.
PHP packs authored snap percentages into one bounded newline payload. Android
then owns handle hit-testing, live `translationY`, velocity prediction, nearest
snap selection, backdrop opacity and entrance/dismiss motion. A drag emits no
progress messages and exactly one semantic snap index when it settles. Backdrop
behavior is represented by sequential integer codes (`close=1`, `collapse=2`,
`none=3`), and native item hosts can dismiss the enclosing sheet after their
single press event without another application round-trip.

The controller animates only transform and opacity, cancels in-flight motion
when a new gesture begins and bypasses animation when the system animator scale
is disabled. The authored backdrop remains stationary while only the content
surface moves. A 48 dp drag region, TalkBack scroll alternatives, modal
dismissal, collection/item semantics and focus trapping keep drag behavior
optional rather than required.

Anchored overlays keep the trigger in ordinary layout and tag only the trigger,
content, optional backdrop and arrow. Android converts the trigger bounds to
window coordinates, evaluates the requested and opposite placements, selects
the lower-overflow candidate, clamps it to a safe gutter and translates the
content back into host coordinates. `offset`, `crossOffset`, overlap and arrow
alignment therefore never require a PHP layout callback.

An uncontrolled Menu, Popover or Tooltip owns its open/close state locally.
Opening emits one lifecycle request for optional application observation;
positioning and motion emit nothing. Controlled overlays keep the authored
state until PHP responds. Menu items are native hosts coordinated by their
ancestor for single/multiple/none selection, disabled keys, type-ahead,
wrapping keyboard focus and close-on-select. The item sends its semantic press
first and the root sends at most one separate dismissal lifecycle event.

Compound `Input`, `Textarea` and `FormControl` keep their authored slots,
icons, label, helper and error as ordinary PAM children around a core Android
`EditText`. A lightweight native ancestor observes focus without replacing the
field's core callbacks, paints focus/invalid outlines, enforces read-only state
and performs clear/password actions locally. The root `on:change` is bound
directly to the field during PHP composition, so it has the same event path as
an explicitly authored `InputField`.

`sync=native`, blur and submit modes perform no bridge callback per keystroke;
debounced and immediate modes remain explicit application choices. Form labels
focus their descendant field, required markers are injected during composition,
inactive errors become `GONE`, and Android exposes the associated label,
helper, required/read-only state and current error through the field's
accessibility node.

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

Tabs use a lightweight native trigger host inside the ordinary authored
`TabsList`. The root discovers those hosts through the mounted view hierarchy,
coordinates single selection, panel visibility, indicator geometry and content
height, and implements automatic/manual keyboard activation. This lets custom
wrappers, icons and arbitrary panel content remain normal PAM children while
all transient tab motion and focus work stays on the UI thread.

Compound events are routed while PHP composes the immutable tree. A single
`on:change` on `Select`, `RadioGroup`, `CheckboxGroup`, `Accordion` or `Menu`
is bound to the relevant descendant item, even through layout wrappers. The
bound closure captures the authored scalar value (or computes the next
controlled value list), so Android emits only the normal press/toggle event and
no context lookup or second bridge message is required. Controlled item state
flows down the same context and becomes native `checked`, `selected` or
`expanded` state.

Calendar selection follows the same boundary. PHP sends scalar dates or
newline-delimited bounded sets once during a changed render. Android maintains
the visible month, grid geometry, disabled dates and pending range endpoint,
redraws locally, and returns a single scalar/set/range result. The PHP renderer
turns set and range payloads into ordinary arrays before invoking application
code. Month navigation emits a small native lifecycle map only when an
application explicitly subscribes to it.

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
- Small visual controls use PAM's shared native `TouchDelegate` groups, so
  touch and TalkBack regions expand without changing component geometry.
- Text follows system font scale and cannot encode meaning with color alone.
- Roles, labels, hints, disabled, selected, checked and expanded state are
  exposed to TalkBack.
- Calendar days are exposed as virtual collection-item buttons with native
  bounds, selected/disabled state, localized dates and direct click actions;
  this keeps the painted grid flat without sacrificing touch exploration.
- Modal focus is trapped and restored; dismiss/back remains available.
- Motion respects system animation scale and avoids required gesture-only paths.
- Light and dark semantic pairs meet WCAG AA contrast.

## Compatibility definition

Parity is behavioral. Public anatomy and variant names remain recognizable to
gluestack-ui users, but PAM uses integer-backed enums and Android conventions.
React-only implementation details, DOM-only props and CSS utility internals are
not copied into the runtime.
