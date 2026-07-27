# PAM UI mobile component curation

PAM UI exposes `p-*` components only when they add a useful mobile product
capability or a Vuetify-compatible interaction that is not already a first-class
PAM Native primitive.

## Native primitives, not duplicated

These removed aliases must use the existing PAM Native implementation:

| Removed alias | Native capability |
| --- | --- |
| `p-app` | `App`, `Screen`, `SafeAreaView` |
| `p-container`, `p-row`, `p-col`, `p-spacer` | `View`, `Row`, `Column`, `Spacer`, `Grid` |
| `p-layout`, `p-layout-item`, `p-main` | `Screen`, native routers and safe-area layout |
| `p-navigation-drawer` | `DrawerRouter`, `DrawerNavigator`, `DrawerLayoutAndroid` |
| `p-bottom-navigation` | `TabRouter`, `TabNavigator` |
| `p-pull-to-refresh` | `RefreshControl` |
| `p-virtual-scroll` | `VirtualizedList`, `FlatList`, `SectionList`, `VirtualGrid` |
| `p-system-bar` | `StatusBar` |
| `p-table` | `p-data-table` and native virtualized rows |

The retained `p-infinite-scroll`, `p-data-table-virtual` and
`p-data-iterator` components add data orchestration on top of native
virtualization; they do not implement a second scrolling engine.

## Removed web-oriented surface

The following Vuetify concepts do not justify a mobile component API:

| Removed component | Reason |
| --- | --- |
| `p-hover` | Hover is not a primary mobile interaction; pointer hover remains a directive event where supported. |
| `p-hotkey` | App-wide keyboard shortcuts belong to the native input/command layer. |
| `p-kbd` | Keyboard-key documentation markup is web-oriented. |
| `p-code` | Showcase code markup is intentionally outside the mobile component library. |
| `p-footer` | Web document semantics do not map to a distinct native control. |
| `p-responsive` | PAM Native layout and image sizing already provide responsive constraints. |

## Retention rule

A component remains public when at least one of these is true:

1. It owns a distinct mobile interaction or state machine.
2. It composes native primitives into a reusable product pattern.
3. It adds a meaningful Vuetify-compatible API without replacing the native
   renderer underneath.
4. It is required for theme, locale, defaults, accessibility or validation
   propagation.

Generated facade classes for previously available aliases may remain internally
for source compatibility, but removed tags are not registered, documented or
shown as new PAM UI components.
