# Component reference

Generated from the component classes by `tools/component-docs.php`. Every prop is a typed constructor parameter: a static attribute (`label="Name"`) or a bound expression (`:options="$countries"`). Inputs and overlays bind with `p-model`; their value is the `modelValue` prop.

## Inputs

### `p-checkbox`

Checkbox with a label and an optional description; the whole row is the
touch target. Shows an error under the row.

```xml
<p-checkbox p-model="$form->terms" label="I accept the terms" :error="error('terms')" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `bool` | `false` |
| `label` | `string` | `''` |
| `description` | `?string` | `null` |
| `error` | `?string` | `null` |
| `indeterminate` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `model` (bool), `change` (bool). Bind with `p-model`.

### `p-chip`

Filter chip (binds a bool with p-model) or assist chip (`@press`).

```xml
<p-chip p-model="$onlyUnread" label="Unread" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `bool` | `false` |
| `label` | `string` | `''` |
| `icon` | `?string` | `null` |
| `selectable` | `bool` | `true` |
| `disabled` | `bool` | `false` |

Events: `model` (bool), `change` (bool), `press` (null). Bind with `p-model`.

### `p-date-input`

Date field with a month calendar in a bottom sheet. Binds an ISO date
string (`Y-m-d`) or null.

```xml
<p-date-input p-model="$birthday" label="Birthday" max="2010-12-31" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `?string` | `null` |
| `label` | `string` | `''` |
| `placeholder` | `string` | `'Select a date'` |
| `min` | `?string` | `null` |
| `max` | `?string` | `null` |
| `format` | `string` | `'D, M j, Y'` |
| `firstDay` | `int` | `0` |
| `helper` | `?string` | `null` |
| `error` | `?string` | `null` |
| `required` | `bool` | `false` |
| `clearable` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `testId` | `?string` | `null` |

Events: `model` (mixed), `change` (mixed). Bind with `p-model`.

### `p-radio-group`



```xml
<p-radio-group p-model="$plan" label="Plan" :options="['free' => 'Free', 'pro' => 'Pro']" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `string|int|float|null` | `null` |
| `options` | `array` | `[]` |
| `label` | `string` | `''` |
| `error` | `?string` | `null` |
| `disabled` | `bool` | `false` |
| `testId` | `?string` | `null` |

Events: `model` (mixed), `change` (mixed). Bind with `p-model`.

### `p-segmented`

Segmented control (iOS) / segmented button (Material 3) for 2–5 options.

```xml
<p-segmented p-model="$period" :options="['day' => 'Day', 'week' => 'Week', 'month' => 'Month']" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `string|int|float|null` | `null` |
| `options` | `array` | `[]` |
| `disabled` | `bool` | `false` |
| `accessibilityLabel` | `?string` | `null` |

Events: `model` (mixed), `change` (mixed). Bind with `p-model`.

### `p-select`

A field that opens a native bottom sheet with the options. Long lists get a
search box and a virtualized list; `multiple` binds an array of values.

```xml
<p-select p-model="$country" label="Country" :options="$countries" searchable="true" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `mixed` | `null` |
| `options` | `array` | `[]` |
| `label` | `string` | `''` |
| `placeholder` | `string` | `'Select'` |
| `helper` | `?string` | `null` |
| `error` | `?string` | `null` |
| `multiple` | `bool` | `false` |
| `searchable` | `bool` | `false` |
| `required` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `sheetTitle` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `model` (mixed), `change` (mixed). Bind with `p-model`.

### `p-slider`

Continuous or stepped slider. The thumb, the filled track and the value
label move on the UI thread while dragging; PHP receives one `model` event
when the finger lifts (or on a tap, or a screen reader increment).

```xml
<p-slider p-model="$volume" label="Volume" :min="0" :max="100" :step="5" showValue="true" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `float` | `0.0` |
| `min` | `float` | `0.0` |
| `max` | `float` | `100.0` |
| `step` | `float` | `1.0` |
| `label` | `string` | `''` |
| `showValue` | `bool` | `false` |
| `unit` | `string` | `''` |
| `disabled` | `bool` | `false` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `model` (float), `change` (float). Bind with `p-model`.

### `p-stepper`

Number input with − and + buttons (also exposed as `p-number-input`'s
successor). Binds an int; use `step` for increments.

```xml
<p-stepper p-model="$guests" label="Guests" :min="1" :max="12" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `int` | `0` |
| `min` | `int` | `0` |
| `max` | `int` | `999` |
| `step` | `int` | `1` |
| `label` | `string` | `''` |
| `description` | `?string` | `null` |
| `disabled` | `bool` | `false` |

Events: `model` (int), `change` (int). Bind with `p-model`.

### `p-switch`

Switch row: a Material 3 switch on Android and an iOS-style switch on iOS,
animated on the UI thread. The whole row is the touch target.

```xml
<p-switch p-model="$notifications" label="Notifications" description="Push and email" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `bool` | `false` |
| `label` | `string` | `''` |
| `description` | `?string` | `null` |
| `disabled` | `bool` | `false` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `model` (bool), `change` (bool). Bind with `p-model`.

### `p-tabs`

In-page tab bar with an underline indicator (Material 3 primary tabs).
Render each panel with `p-if` on the bound value. For app-level tabs use
PAM Native's tab navigator.

```xml
<p-tabs p-model="$tab" :options="['overview' => 'Overview', 'activity' => 'Activity']" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `string|int|null` | `null` |
| `options` | `array` | `[]` |
| `scrollable` | `bool` | `false` |

Events: `model` (mixed), `change` (mixed). Bind with `p-model`.

### `p-text-field`

Single-line text input with a label, helper text and an error message.

```xml
<p-text-field p-model="$email" label="Email" type="email" :error="error('email')" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `string` | `''` |
| `label` | `string` | `''` |
| `placeholder` | `string` | `''` |
| `type` | `string` | `'text'` |
| `helper` | `?string` | `null` |
| `error` | `?string` | `null` |
| `required` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `readonly` | `bool` | `false` |
| `clearable` | `bool` | `false` |
| `autofocus` | `bool` | `false` |
| `maxlength` | `?int` | `null` |
| `counter` | `bool` | `false` |
| `prefix` | `?string` | `null` |
| `suffix` | `?string` | `null` |
| `autocomplete` | `?string` | `null` |
| `returnKey` | `string` | `'done'` |
| `sync` | `string` | `'debounced'` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `model` (string), `change` (string), `submit` (string), `focus` (null), `blur` (null). Bind with `p-model`.

### `p-textarea`

Multi-line text input that grows with its content up to `rows` lines.

```xml
<p-textarea p-model="$bio" label="Bio" :maxlength="160" counter="true" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `string` | `''` |
| `label` | `string` | `''` |
| `placeholder` | `string` | `''` |
| `rows` | `int` | `4` |
| `helper` | `?string` | `null` |
| `error` | `?string` | `null` |
| `required` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `readonly` | `bool` | `false` |
| `autofocus` | `bool` | `false` |
| `maxlength` | `?int` | `null` |
| `counter` | `bool` | `false` |
| `autocomplete` | `?string` | `null` |
| `sync` | `string` | `'debounced'` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `model` (string), `change` (string), `focus` (null), `blur` (null). Bind with `p-model`.

## Actions

### `p-btn`

Button with five variants and three sizes. A loading or disabled button
ignores presses, so a double tap never submits twice.

```xml
<p-btn label="Save" @press="save" :loading="$saving" />
<p-btn variant="tonal" icon="plus" @press="add">Add item</p-btn>
```

| Prop | Type | Default |
| --- | --- | --- |
| `label` | `string` | `''` |
| `variant` | `string` | `'filled'` |
| `size` | `string` | `'md'` |
| `icon` | `?string` | `null` |
| `block` | `bool` | `false` |
| `loading` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `press` (null).

Slots: `slot`.

### `p-icon-btn`



```xml
<p-icon-btn icon="search" label="Search" @press="openSearch" /> (48 dp target, label is read aloud)
```

| Prop | Type | Default |
| --- | --- | --- |
| `icon` | `string` | `required` |
| `label` | `string` | `required` |
| `variant` | `string` | `'plain'` |
| `disabled` | `bool` | `false` |

Events: `press` (null).

## Layout

### `AppScreen`

1.x name of {@see Screen}, kept so `pam init` 1.x projects keep rendering.

| Prop | Type | Default |
| --- | --- | --- |
| `title` | `string` | `''` |
| `subtitle` | `?string` | `null` |

Slots: `slot`.

### `p-card`

Surface for grouped content. Pass `pressable="true"` and listen to `@press`
to make the whole card a button.

```xml
<p-card title="Revenue" subtitle="Last 30 days"><Text>…</Text></p-card>
```

| Prop | Type | Default |
| --- | --- | --- |
| `variant` | `string` | `'outlined'` |
| `title` | `?string` | `null` |
| `subtitle` | `?string` | `null` |
| `pressable` | `bool` | `false` |
| `padded` | `bool` | `true` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `press` (null).

Slots: `slot`, `actions`.

### `p-divider`



```xml
<p-divider /> or <p-divider label="or" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `label` | `?string` | `null` |
| `vertical` | `bool` | `false` |

### `p-form`

Form layout: consistent spacing between fields and an error summary that is
announced to screen readers after a failed submit.

```xml
<p-form :error="formFailed() ? 'Fix the highlighted fields.' : null">…fields…</p-form>
```

| Prop | Type | Default |
| --- | --- | --- |
| `error` | `?string` | `null` |
| `success` | `?string` | `null` |

Slots: `slot`.

### `p-list-item`

One row of a list or settings section: optional leading icon or avatar,
title, subtitle and a trailing value, slot or chevron.

```xml
<p-list-item title="Account" subtitle="dev@pam.dev" icon="info" chevron="true" @press="openAccount" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `title` | `string` | `''` |
| `subtitle` | `?string` | `null` |
| `value` | `?string` | `null` |
| `icon` | `?string` | `null` |
| `avatar` | `?string` | `null` |
| `chevron` | `bool` | `false` |
| `pressable` | `bool` | `false` |
| `divider` | `bool` | `true` |
| `destructive` | `bool` | `false` |
| `disabled` | `bool` | `false` |
| `accessibilityLabel` | `?string` | `null` |
| `testId` | `?string` | `null` |

Events: `press` (null).

Slots: `leading`, `trailing`.

### `p-screen`

A full screen: themed background, safe areas on every edge, a large title,
scrolling content that keeps the focused field above the keyboard, and an
optional footer pinned above the home indicator.

```xml
<p-screen title="Settings" subtitle="Account and privacy" back="true" @back="goBack">…</p-screen>
```

| Prop | Type | Default |
| --- | --- | --- |
| `title` | `string` | `''` |
| `subtitle` | `?string` | `null` |
| `back` | `bool` | `false` |
| `scroll` | `bool` | `true` |
| `padded` | `bool` | `true` |
| `refreshing` | `bool` | `false` |
| `refreshable` | `bool` | `false` |

Events: `back` (null), `refresh` (null).

Slots: `slot`, `actions`, `footer`.

### `p-section`

Grouped list section (settings style) with an optional header and footer.

```xml
<p-section title="Account" footer="Signed in on this device.">
<p-list-item title="Email" value="dev@pam.dev" />
</p-section>
```

| Prop | Type | Default |
| --- | --- | --- |
| `title` | `?string` | `null` |
| `footer` | `?string` | `null` |
| `inset` | `bool` | `true` |

Slots: `slot`.

## Feedback

### `p-alert`

Inline message. Screen readers announce it when it appears.

```xml
<p-alert tone="danger" title="Payment failed" text="Check your card and try again." closable="true" @close="dismiss" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `tone` | `string` | `'info'` |
| `title` | `?string` | `null` |
| `text` | `?string` | `null` |
| `closable` | `bool` | `false` |

Events: `close` (null).

Slots: `slot`.

### `p-avatar`



```xml
<p-avatar name="Taylor Otwell" /> · <p-avatar :src="$user->photo" name="Taylor" size="lg" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |
| `src` | `?string` | `null` |
| `size` | `string` | `'md'` |

### `p-badge`



```xml
<p-badge label="New" tone="accent" /> · <p-badge :count="3" /> · <p-badge dot="true" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `label` | `?string` | `null` |
| `count` | `?int` | `null` |
| `max` | `int` | `99` |
| `tone` | `string` | `'accent'` |
| `dot` | `bool` | `false` |

### `p-empty-state`



```xml
<p-empty-state icon="search" title="No results" text="Try another search.">
<p-btn variant="tonal" label="Clear filters" @press="clear" />
</p-empty-state>
```

| Prop | Type | Default |
| --- | --- | --- |
| `title` | `string` | `''` |
| `text` | `?string` | `null` |
| `icon` | `?string` | `'info'` |

Slots: `slot`.

### `p-icon`

The package's built-in glyphs, drawn with native views: crisp at any size,
themed for light and dark, no font or image asset to ship.

check, close, plus, minus, chevron-right, chevron-left, chevron-down,
chevron-up, arrow-left, search, calendar, info, alert, dot.

| Prop | Type | Default |
| --- | --- | --- |
| `name` | `string` | `'dot'` |
| `tone` | `string` | `'text'` |
| `size` | `float` | `24.0` |
| `accessibilityLabel` | `?string` | `null` |

### `p-progress`

Linear progress (0–1) or, without a value, an indeterminate bar that
shimmers on the UI thread and stops while off screen. `circular="true"`
uses the platform activity indicator.

```xml
<p-progress :value="$upload" label="Uploading" /> · <p-progress circular="true" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `value` | `?float` | `null` |
| `label` | `string` | `''` |
| `circular` | `bool` | `false` |
| `showValue` | `bool` | `false` |

### `p-skeleton`

Loading placeholder. One shared native shimmer clock drives every
skeleton; it pauses while off screen.

```xml
<p-skeleton :lines="3" /> · <p-skeleton :width="48" :height="48" :radius="24" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `lines` | `int` | `1` |
| `width` | `?float` | `null` |
| `height` | `float` | `14.0` |
| `radius` | `float` | `7.0` |

## Overlays

### `p-dialog`

Centered confirmation dialog bound to a bool. With `confirmLabel` it renders
the actions itself and emits `confirm` / `cancel`.

```xml
<p-dialog p-model="$confirmDelete" title="Delete project?" text="This cannot be undone." confirmLabel="Delete" destructive="true" @confirm="delete" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `bool` | `false` |
| `title` | `string` | `''` |
| `text` | `?string` | `null` |
| `confirmLabel` | `?string` | `null` |
| `cancelLabel` | `string` | `'Cancel'` |
| `destructive` | `bool` | `false` |
| `dismissible` | `bool` | `true` |

Events: `model` (bool), `confirm` (null), `cancel` (null). Bind with `p-model`.

Slots: `slot`, `actions`.

### `p-sheet`

Native bottom sheet bound to a bool. Content sits above the navigation bar
and home indicator; tall content scrolls.

```xml
<p-sheet p-model="$filtersOpen" title="Filters">…<template #footer><p-btn block="true" label="Apply" @press="apply" /></template></p-sheet>
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `bool` | `false` |
| `title` | `?string` | `null` |
| `scroll` | `bool` | `true` |
| `fullHeight` | `bool` | `false` |
| `dismissible` | `bool` | `true` |

Events: `model` (bool), `close` (null). Bind with `p-model`.

Slots: `slot`, `footer`.

### `p-snackbar`

Brief message at the bottom of the screen, above the navigation bar and
home indicator. Bound to a bool; hides itself after `duration` ms (0 keeps
it until dismissed). The timer only runs while the snackbar is shown.

```xml
<p-snackbar p-model="$saved" text="Changes saved" actionLabel="Undo" @action="undo" />
```

| Prop | Type | Default |
| --- | --- | --- |
| `modelValue` | `bool` | `false` |
| `text` | `string` | `''` |
| `actionLabel` | `?string` | `null` |
| `duration` | `int` | `4000` |

Events: `model` (bool), `action` (null). Bind with `p-model`.
