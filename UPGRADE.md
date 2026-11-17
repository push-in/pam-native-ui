# Upgrading from PAM Native UI 1.x to 2.0

2.0 keeps the `p-*` tag names you are most likely to use and makes them work:
values reach PHP, events fire, and the same code runs on Android and iOS.
Most screens move over by changing how inputs are bound.

## 1. Update the packages

```sh
pam composer require "pushinbr/pam-native:^1.51" "pushinbr/pam-native-ui:^2.0"
mkdir -p assets/fonts && cp vendor/pushinbr/pam-native-ui/resources/fonts/*.ttf assets/fonts/
```

The fonts are Instrument Sans (SIL OFL 1.1). Without them the components fall
back to the system font.

## 2. Bind inputs with `p-model`

1.x documented three binding styles and none of them reached PHP. 2.0 has one:

```diff
- <p-text-field :value="$email" @change="setEmail" label="Email" />
- <p-text-field value="{{ $email }}" on:change="setEmail" label="Email" />
+ <p-text-field p-model="$email" label="Email" type="email" />
```

| 1.x | 2.0 |
| --- | --- |
| `:modelValue` + `@update` / `@change` on any input | `p-model="$property"` |
| `p-checkbox :checked` / `p-switch :checked` | `p-model="$bool"` |
| `p-select :items` | `p-select p-model="$value" :options` |
| `p-radio` children inside `p-radio-group` | `p-radio-group p-model="$value" :options` |
| `p-number-input` | `p-stepper p-model="$int"` |
| `p-btn-toggle`, `p-segmented-button` | `p-segmented p-model="$value" :options` |
| `p-bottom-sheet`, `p-sheet :open` | `p-sheet p-model="$open"` |
| `p-dialog :open` | `p-dialog p-model="$open"` |
| `p-progress-linear`, `p-progress-circular` | `p-progress :value` / `p-progress circular="true"` |
| `p-skeleton-loader` | `p-skeleton` |
| `AppScreen` | `p-screen` (`AppScreen` still renders, deprecated) |
| `<p-btn><Text>Save</Text></p-btn>` | `<p-btn label="Save" />` (the slot still works) |

`:options` accepts a list (`['Red', 'Green']`), a value => label map
(`['br' => 'Brazil']`), rows (`[['value' => 1, 'label' => 'One',
'description' => '…', 'disabled' => false]]`) or backed enum cases.

You can still listen to changes: `@change="method"` (or `on:change`) runs after
the bound property is written.

## 3. Events

`@press` on `p-btn`, `p-card pressable="true"`, `p-list-item` and
`p-icon-btn`; `@change` on inputs; `@submit`, `@focus` and `@blur` on text
fields; `@confirm` / `@cancel` on `p-dialog`; `@close` on `p-alert`;
`@action` on `p-snackbar`; `@back` on `p-screen`. An event a component does not
declare is an error that names the component, instead of being ignored.

## 4. Styling and themes

1.x utility classes (`class="pa-6 ui-text-muted"`) and the `Themes` /
`ThemeManager` / `MaterialTokens` API are gone. Components are styled by the
PAM theme; style your own layout with scoped CSS in your components, as PAM
Native recommends. Light and dark follow the system; switch with
`NativeUi::appearance(AppearanceMode::Dark)`. `PamUI::mode(ThemeMode::System)`
from 1.x project templates keeps working (deprecated).

## 5. Removed components

Tags without a 2.0 equivalent: `p-app-bar`, `p-autocomplete`, `p-banner`,
`p-calendar`, `p-carousel`, `p-color-input`, `p-combobox`, `p-data-table`,
`p-data-grid`, `p-expansion-panels`, `p-fab`, `p-menu`, `p-otp-input`,
`p-rating`, `p-timeline`, `p-tooltip`, `p-treeview`, `p-chart` and the other
specialised 1.x parts. Use PAM Native primitives (`VirtualizedList`,
`BottomSheet`, `Modal`, `Pressable`, scoped CSS) for those screens, or stay on
`^1.0` until you migrate them; 1.x stays installable.

## 6. PHP facades

`Pam\MobileUi\Material\*` (`PBtn::make()` …), `ComponentRenderer` and
`UiComponent` are removed. Build screens with `.pam` templates; in PHP-built
screens use PAM Native's `Pam\Native\UI\*` primitives.
