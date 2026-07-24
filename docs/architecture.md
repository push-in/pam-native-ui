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

`FlatList` and `VirtualizedList` keep PAM core's packed-string data source and
mount it through one AndroidX `RecyclerView`. Android recycles `TextView` rows,
applies the fixed estimated row height, schedules bounded GapWorker prefetch and
detects the end threshold without calling PHP for visible-item rendering.
`SectionList` uses the equivalent packed section/header adapter, with headers
spanning the complete native grid. The mobile UI facades only normalize
familiar prop names (`rowHeight`, `itemHeight`, `estimatedItemSize`,
`horizontal`, `numColumns`, `inverted`, `initialScrollIndex`,
`removeClippedSubviews`, `prefetch`, `maxToRenderPerBatch`,
`initialNumToRender`, indicator and scrolling flags) onto those core
properties, so the optimized path does not gain an extra host or bridge layer.
Visible-range changes stay native unless requested; `onScroll` is coalesced to
one semantic event per VSYNC.
The `Actionsheet*List`, `BottomSheet*List` and `Select*List` aliases resolve to
these exact native list nodes rather than styled generic containers.

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

Skeleton pulse alpha is owned by one Android property animator even when
`SkeletonText` contains multiple placeholder lines. `isLoaded=true` removes the
placeholder host and returns its content directly. Toasts use an identity-based
native timer: a stable rerender cannot postpone dismissal, persistent toasts do
not allocate timers, and Android performs the exit motion and accessibility
announcement before emitting one semantic dismissal.

`ImageViewer` receives its PHP gallery as bounded declarative context. During
composition, the renderer creates ordinary PAM image nodes with stable semantic
tags and keeps navigation, counter and close controls as authored compound
children. Android discovers those mounted nodes once structure changes, then
owns active-image visibility, looping, swipe selection, zoom/pan transforms,
counter text and control availability on the UI thread. Pinch and pan emit no
bridge traffic; a completed navigation emits one scalar index. The native host
also exposes collection metadata, accessibility scroll alternatives and a
position announcement, so touch gestures are never the only path.

Chat AI uses three small anatomy-aware hosts rather than a JavaScript state
provider. `Conversation` observes its descendant Android `ScrollView`, hides or
shows the latest-message affordance from native scroll position and follows a
new user message without a PHP layout callback. Authored message bubbles remain
ordinary PAM children, so application-specific content is unrestricted.

`MessageBranch` tags authored response pages during composition. Android owns
the active index, page visibility, looping, previous/next availability,
counter and pager accessibility metadata, then emits one scalar index after a
settled selection. `PromptInput` observes its mounted `EditText` directly,
derives Submit availability from trimmed text or mounted attachments, clears
locally when requested and emits one text-only `SUBMIT` payload. The registered
component event adapter joins that text with the provider's PHP-resident files
into the upstream `{text, files}` callback shape. Only an integer attachment
count enters the Android property map, so filenames, URLs and base64 data never
make a redundant bridge round-trip. Native input sync avoids per-keystroke PHP
callbacks; attachment removal and actual message creation remain application
state.

`MessageResponse` compiles each text part to one intrinsic Android `TextView`.
Its dependency-free parser turns headings, lists, quotes, emphasis, code and
safe links into `Spannable` ranges only when the source property changes; no
WebView, JavaScript runtime or nested text-view-per-token tree is created.
`http`, `https`, `mailto` and `tel` links emit one bounded native URI event for
application policy instead of opening an external activity implicitly. File
parts remain ordinary PAM images, including base64 data URIs.

`PamUIProvider` scopes light/dark/system token resolution while PHP
composes its subtree, then restores the previous theme before an adjacent tree
is rendered. Its one full-size View matches the upstream provider root.
Context-only `BlankProvider`, `BlankContext` and `PromptInputProvider` return
one child directly. A multi-child provider emits a property-free View that
PAM's layout-only optimization flattens, so Android allocates no provider host.
Prompt provider arrays are bounded declarative context; coded attachment types
are sequential integers represented by `AttachmentType`.

React Native image-source objects normalize their fallback URI, density/width
candidates, cache policy and bounded request headers once during PHP
composition. Android selects `srcSet` against density/measured width, coalesces
equal work, keeps bounded original bytes on disk and caches decoded bitmaps by
64 px target bucket. Downsampling happens off the UI thread before allocation;
only the final drawable, crossfade and rounded clip mount on the UI thread.
Source replacement/unmount invalidates its generation token, so a late network
result cannot overwrite a recycled view. The same path covers `Image`,
`ImageBackground`, `AvatarImage` and image-viewer content. `alt` marks the
native host accessible, while generated style aliases preserve
`AvatarFallback` as text rather than allocating a second avatar container.

`FileTree` packs controlled/default expanded paths into one bounded newline
payload. Folder and file hosts retain their authored nested PHP content while
the root coordinates selection, expansion, chevron/content animation and
hierarchical accessibility locally. A file emits one scalar selected path. A
folder emits that same selection plus one bounded native map whose sequential
action ID (`expanded=1`) carries the path and final boolean state.

Chat action anatomy that is interactive upstream (`AttachmentRemove`,
`MessageAction`, `PromptInputButton`, `ConversationDownload`) compiles to PAM
pressables. `AttachmentHoverCard` and `PromptInputActionMenu` reuse the same
collision-aware native Tooltip/Menu controller rather than introducing a
second overlay implementation. Their original interaction defaults are kept:
the hover card opens above its trigger with 0 ms open and 100 ms close delays;
the prompt action menu opens above its trigger with a 5 dp offset.

Every pressable uses the dedicated PAM Android gesture host. Hit rectangles,
press-retention rectangles, press-in/out delays, long-press recognition,
pressed opacity, ripple and click sound are resolved on the UI thread. No move
sample crosses the PHP boundary unless `onPressMove` is registered; when it is,
only the latest pointer snapshot is emitted once per display frame. The event
arrives as a typed `PressEvent` with local/page coordinates, timestamp and
pointer ID.

`Attachments` and every vertical or horizontal `ScrollView` compile to the same
PAM core scroll host. The host switches its Android axis without changing the
public node kind and accepts one Row/Column content container. Drag, fling,
snap/paging, deceleration, overscroll, nested scrolling, fading edges,
persistent scrollbars and keyboard dismissal remain native. It emits the
active-axis offset at most once per display frame and only when an `on:scroll`
handler exists. Grid/inline attachments use a Row; the upstream list variant
uses a Column. No gesture sample crosses PHP.

`Grid` is a dedicated `pam.mobile_ui.grid` ViewGroup rather than a simulated
vertical stack. PHP extracts the bounded `grid-cols-*`, responsive
`sm/md/lg/xl/2xl:grid-cols-*`, `col-span-*`, `gap-x-*` and `gap-y-*` rules once.
Android selects the active breakpoint from `screenWidthDp`, measures and wraps
items, handles row/row-reverse/column direction and RTL, and publishes
collection row/column metadata to accessibility services. Rotation and
responsive relayout do not require a PHP callback.

Controlled compound overlays keep their trigger subtree mounted when
`open=false`. `Select` renders a stable layout root plus a dedicated portal
child. `BottomSheet` and `ModelSelector` use keyed, layout-only provider roots
that Android flattens, matching React context/fragment semantics without
allocating containers. Window content is ordered after every trigger. Only the
portal/content child owns the Android `Dialog`/sheet window. Root event handlers
are copied to triggers and native child hosts at composition time, so open,
dismiss, selection and drag-end events still target the application callback
without a runtime lookup or an extra bridge hop.

The same composition path is used by fluent PHP and `.pam` tags. The template
renderer carries only bounded ancestor handler context while descending the
compiled tree; each registered child asks the UI renderer for its exact
inherited semantic event before creating the native element. No post-render
reflection walk or Android-side component-name lookup is required.

`ModelSelectorContent` composes its fixed upstream anatomy once: a modal header,
1 dp screen-reader title, native dismiss control, a maximum-500 dp scroll
viewport and modal body around the authored children. The root `size` resolves
through the original ModalContent width recipes. `asChild` applies trigger
styles and events to the existing child instead of wrapping it. Group headings
and controlled selected-item state are inherited during composition; opening,
selection and dismissal each emit one bounded semantic event.

Modal, AlertDialog, Actionsheet, Drawer, Select, BottomSheet, ModelSelector and
ImageViewer all begin closed when no open prop is supplied, matching their
upstream contracts; ImageViewer also begins at index zero and BottomSheet at
snap index zero. Closed portal/backdrop/content parts become `GONE`; their native controller
also disables click, focus, accessibility and gesture handling. Touches
therefore continue to the trigger and surrounding app. Opening the same keyed
window enables UI-thread positioning, focus capture and entrance motion without
recreating the trigger. Pure overlays such as `Modal`, `AlertDialog`,
`Actionsheet`, `Drawer`, `ImageViewerContent` and `Portal` use the native window
directly because they do not need to leave an inline trigger mounted.

Primitive interop props do not create a generic property bag on Android.
`pointerEvents`, `collapsable`, press feedback, hit slop, input colors and
`returnKeyType` are normalized to fixed protocol keys. The renderer applies
pointer routing, ripple/opacity state, text selection and `EditorInfo` actions
locally, so a press or keyboard action does not cross PHP until an authored
semantic callback actually fires.

Accessibility roles use one sequential integer enum shared by PHP templates,
fluent components and Android. Component anatomy supplies roles for headings,
links, selection controls, combo boxes, menus, ranges, tabs and collections;
an explicit React Native `role` has precedence over `accessibilityRole`.
Android maps the resolved role to its TalkBack class and heading/checkable
metadata during mount, without reflection or a component-name lookup.

The same delegate reads compact accessible, importance, live-region,
checked/mixed, expanded, busy and range-value properties from the mounted node
state. It exposes native range info and expand/collapse actions, uses AndroidX's
compatible state-description extras below API 30, and notifies TalkBack only
when semantic state changes on an attached view. PHP remains outside screen
reader traversal and announcement timing.

Android Back is intercepted by PAM's window host and delivered through the same
bounded native dismissal event as backdrop/drag dismissal. Controlled overlays
remain visible until the application publishes `open=false`; after the window
closes, Android restores the view that held focus before presentation.
The wrapper also forwards React Native's current modal window contract:
none/slide/fade animation, transparent or colored backdrop, hardware
acceleration, status/navigation bar translucency, request-close, show, dismiss
and typed portrait/landscape lifecycle events. PamUI windows default to
transparent so their authored backdrop remains the single visual backdrop.

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

The core field is a dedicated `PamEditText`, not a generic bridge wrapper. IME
configuration, autofill hints, capitalization/correction, secure rendering,
controlled selection, return actions, content measurement and cursor state
are applied on the UI thread. Soft-key input is observed through the native
`InputConnection`; selection is VSYNC-coalesced and all optional callbacks are
detached when no PHP handler exists.

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
- Conversation scrolling, branch navigation and prompt submission expose
  button/pager/list semantics and keyboard activation without gesture-only
  actions.
- Light and dark semantic pairs meet WCAG AA contrast.

## Compatibility definition

Parity is behavioral. Public anatomy and variant names remain recognizable to
cross-platform UI users, but PAM uses integer-backed enums and Android conventions.
React-only implementation details, DOM-only props and CSS utility internals are
not copied into the runtime.
