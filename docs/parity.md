# Parity contract

The authoritative PAM Material machine-readable gate is
[`resources/material-parity.json`](../resources/material-parity.json).
`resources/parity.json` is an internal renderer-regression inventory; its
historical PascalCase names are neither registered tags nor Composer-autoloaded
public facades.

Reference:

- source: the manually maintained PAM Material component specification;
- namespace: `p-*` only, with no `v-*` aliases;
- captured surface: 62 mobile modules and 84 public component parts;
- targets: retained native Android and UIKit renderers;
- metadata import: none.

The original documentation navigation exposes fewer cards than the source tree.
The private renderer fixture inventory contains only parts reachable from the
curated public surface, including dynamically selected icons. It exercises
native behavior and style recipes in tests but is not Composer-autoloaded.
Public applications use PAM Native primitives for layout and the curated
`p-*` surface for Material components.
`SafeAreaView` forwards per-edge padding/margin insets and
`KeyboardAvoidingView` forwards height/position/padding behavior, vertical
offset and enabled state to the Android UI thread.
`RefreshControl` forwards Android colors, background, progress offset, size
and enabled state, with native vertical-gesture arbitration and indicator
animation.
Text passthroughs preserve Android selection, ellipsis, accessible font
scaling/fitting, break/hyphenation strategy and local data detectors.
Status bars merge by mount order and forward animated color, icon appearance,
visibility and pre-Android-15 translucency while respecting mandatory modern
edge-to-edge behavior.
Flat, virtualized and section lists forward fixed row extent, bounded prefetch,
horizontal/grid/inverted layout, initial position, clipping policy, scrolling,
indicators and end threshold to one AndroidX RecyclerView host. Packed rows are
decoded only while binding visible/prefetched holders, and requested scroll
events are limited to one per display frame.
Scroll views use one PAM core host for both axes and forward content offsets,
viewport filling, nested scrolling, overscroll, fading edge, persistent
scrollbar, paging/snap, deceleration and keyboard dismissal. Spinner forwards
animation, stopped visibility, tint and small/large or numeric size; Switch
retains its Android-owned state and disabled-aware track/thumb tinting.
Image, ImageBackground and AvatarImage forward source
objects/candidates, `src`/`srcSet`, all five resize modes, placeholders, fade,
resize method/multiplier, progressive flag, cache policy, safe headers, tint,
overlay, blur, accessibility alt and the five lifecycle callbacks. Android
owns cancellation, redirects, measured-size decode, RAM/disk caching and
crossfade; unobserved lifecycle work emits no PHP event.
InputField, TextareaInput, BottomSheetTextInput, SelectInput and
DateTimePickerInput share the full
native input contract: keyboard/input mode, capitalization, correction,
autofill, secure/read-only state, selection, cursor styling, multiline sizing,
submit behavior and typed end/key/selection/content-size events.

A module reaches status `3` (`verified`) only when all applicable gates pass:

- root and documented subcomponent tags;
- typed PHP facade and raw escape hatch;
- sizes, variants, placements and orientations;
- controlled/uncontrolled and disabled/loading/invalid/read-only states;
- native Android and iOS interaction and lifecycle behavior;
- TalkBack semantics, focus order, font scale, contrast and target size;
- light/dark/custom theme token coverage;
- documented `.pam` and PHP examples;
- PHP level-9 static analysis and contract tests;
- Android unit/instrumentation and iOS package/application tests;
- cold start, mount, update, event and frame-time benchmark evidence.

Alpha upstream components remain labeled `alpha` but are not excluded from PAM
parity.

`composer test:recipes` expands every generated facade through the light and
dark themes, every captured variant option, every compound rule, and the
active, checked, disabled, focused, hovered, invalid, selected and flip state
paths. A selected recipe utility without a packed native implementation fails
the build. Modules with no upstream variant axis use status `4`
(`not-applicable`) for that gate.

All coded fields use sequential integer IDs represented by PHP enums. Human
names under `definitions` are documentation labels; component records store the
IDs. `resources/material-parity.schema.json` fixes the curated inventory at
62 mobile modules and 84 public `p-*` components, requires both native targets, rejects
metadata import, and rejects unknown fields.
