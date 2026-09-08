# PAM UI platform ownership

This contract prevents the UI package from duplicating capabilities that belong
to the runtime or the project CLI.

## Ownership rule

- `pam-native` owns reusable retained-native primitives, properties, events,
  accessibility semantics, focus, gestures, virtualization, insets, keyboard
  avoidance and platform adaptation.
- `pam` owns project generation, CLI commands, diagnostics, migration and
  developer workflows that span packages.
- `pam-native-ui` owns themed component APIs, visual anatomy, variants, state
  presentation, composition recipes and the showcase.

A UI component may compose existing PAM Native primitives. A new platform view
is justified only when composition cannot preserve native behavior,
accessibility or performance.

## Planned component allocation

| Public UI component | PAM Native capability | PAM CLI responsibility |
| --- | --- | --- |
| App Scaffold | safe-area, keyboard and navigation hosts | scaffold screen |
| Navigation Bar | selection, focus and route events | generate destinations |
| Navigation Rail | adaptive layout and route events | generate destinations |
| Navigation Drawer | drawer gesture and route events | generate destinations |
| Bottom App Bar | safe-area host | generate action recipe |
| Search Bar | text input, IME and focus | add/search recipe |
| Command Palette | focus trap, keyboard shortcuts, virtual list | command registry diagnostics |
| Pagination | selection events | none |
| Password Field | secure input, autofill and IME | form recipe |
| Masked Field | input transformation and cursor contract | mask validation |
| Currency Field | numeric IME and selection | locale diagnostics |
| Tag Input | text input, focus and list events | form recipe |
| Multi Select | selection and virtual list | form recipe |
| File Input | document/media picker module | permission diagnostics |
| Date Range Picker | native date events | form recipe |
| Time Range Picker | native time events | form recipe |
| Segmented Button | selection, focus and accessibility state | none |
| Filter Bar | text input and selection | screen recipe |
| Popover | anchored overlay, focus and dismissal | none |
| Responsive Grid | breakpoint-aware layout | layout diagnostics |
| Virtual List | recycler/collection virtualization | performance diagnostics |
| Section List | sectioned recycler/collection virtualization | performance diagnostics |
| Reorderable List | drag, haptics and accessible reorder events | performance diagnostics |
| Swipe Actions | gesture arbitration, haptics and action events | none |
| Pull to Refresh | refresh container and async event | none |
| Data Grid | two-axis virtualization, focus and selection | performance diagnostics |
| Tree Select | hierarchy, focus and selection | none |
| Progress Button | stable button bounds and progress semantics | none |
| Result State | accessibility announcement | screen recipe |
| Chart | vector canvas, gestures and accessible data summary | data validation |

## Release gate

A component is not complete until its Android and UIKit behavior, light and
dark themes, dynamic text, RTL, disabled/loading/error states, accessibility,
performance budget, showcase route, physical-device interaction and published
documentation evidence pass.
