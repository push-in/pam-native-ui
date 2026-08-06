# PAM UI mobile component curation

PAM UI exposes `p-*` components only when they add a useful mobile product
capability or a Vuetify-compatible interaction that is not already a first-class
PAM Native primitive.

## Native primitives, not duplicated

These removed aliases must use the existing PAM Native implementation:

| Removed alias | Native capability |
| --- | --- |
| `p-app` | `App`, `Screen`, `SafeAreaView` |
| `p-container` | Screen content owns its native width, insets and responsive constraints. |
| `p-layout`, `p-layout-item`, `p-main` | `Screen`, native routers and safe-area layout |
| `p-navigation-drawer` | `DrawerRouter`, `DrawerNavigator`, `DrawerLayoutAndroid` |
| `p-bottom-navigation` | `TabRouter`, `TabNavigator` |
| `p-pull-to-refresh` | `RefreshControl` |
| `p-virtual-scroll` | `VirtualizedList`, `FlatList`, `SectionList`, `VirtualGrid` |
| `p-system-bar` | `StatusBar` |
| `p-table` | `p-data-table` and native virtualized rows |

The retained `p-infinite-scroll` and `p-data-table-virtual` components add
mobile-facing loading and presentation behavior on top of native
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
| `p-breadcrumbs` | Native stack navigation, back actions and screen titles communicate hierarchy. |
| `p-pagination` | Mobile collections use native virtualization, infinite loading or an explicit load-more action. |
| `p-lazy` | Visibility-aware mounting belongs to the native renderer rather than a visual component. |
| `p-window` | Routers, tabs, carousels and steppers already own mobile screen transitions. |
| `p-data-iterator` | Data orchestration belongs to application state and repositories. |
| `p-data-table-server` | Server pagination and sorting are data-source concerns; `p-data-table` receives the resulting rows. |
| `p-field`, `p-input` | Low-level field shells are replaced by complete mobile controls such as `p-text-field`, `p-textarea`, `p-number-input` and `p-date-input`. |
| `p-card-item`, text/title/subtitle wrappers | PAM Native `Row`, `Column` and `Text` already provide composition and typography without a second component layer. |
| `p-list-group` | It did not provide disclosure anatomy; nested disclosure uses the operational `p-expansion-panels` family. |
| Date/time picker anatomy parts | Mobile date/time controls own their complete native surface; fake `controls`, `header`, `month`, `years` and `clock` aliases were removed. |

File picking and uploads are not UI components. Applications use PAM Native
document, media, camera or share capabilities and render progress with the
existing progress, list and feedback components. `p-file-input`,
`p-file-upload` and `p-file-upload-item` are not part of PAM UI.

## Retention rule

A component remains public when at least one of these is true:

1. It owns a distinct mobile interaction or state machine.
2. It composes native primitives into a reusable product pattern.
3. It adds a meaningful Vuetify-compatible API without replacing the native
   renderer underneath.
4. It is required for theme, locale, defaults, accessibility or validation
   propagation.

Removed concepts have no PAM UI facade, component ID, registered tag,
documentation entry or showcase route. Their native equivalents live only in
PAM Native.
