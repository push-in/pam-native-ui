# Search Bar

`PSearchBar` composes the PAM Native editor with a search icon and an optional
clear action. Native owns keyboard, selection and editing; UI owns the visual
composition. No new native input implementation is required.

```php
use Pam\MobileUi\Material\PSearchBar;

// Inside a reactive component's render method:
$search = PSearchBar::make([
    'placeholder' => 'Buscar documentos',
    'accessibilityLabel' => 'Buscar documentos',
    'clearLabel' => 'Limpar busca',
])
    ->modelValue($this->query)
    ->clearable()
    ->onChange(function (string $query): void {
        $this->query = $query; // Your component's reactive state.
    })
    ->onSubmit(function (string $query): void {
        $this->searchDocuments($query); // Your application action.
    });
```

The example assumes `query` is reactive state in the owning component; assigning
a plain local variable alone does not schedule a render. Clearing emits `''`
through the same `onChange` callback. Always feed the updated query back through
`modelValue`. Without a change handler, the clear button is disabled.

## States and customization

- `clearable` defaults to false; enabling it reserves a 48×48 trailing action
  area, including when empty, so the editor does not jump as the query changes.
- `clearLabel` localizes the action's accessible name; default: `Clear search`.
- `readOnly()` and `disabled()` preserve the query and block editing/clearing.
  `editable: false` also blocks both. Empty queries disable the clear action.
- `placeholder`, `accessibilityLabel`, native keyboard options and style overrides
  remain available. The default keyboard return action is Search.
- Supplying custom children replaces the default composition; then the caller
  owns the editor, actions and event wiring.

## Native editor events

The fluent API forwards `onChange`, `onFocus`, `onBlur`, `onSubmit`,
`onEndEditing`, `onSelectionChange`, `onContentSizeChange` and `onKeyPress`
to the native editor without rewriting their payloads. Use the PAM Native event
contracts for each callback; selection/key events are not plain query strings.

## Validation scope

The clear action has Android emulator interaction and visual evidence. Public
fluent event forwarding and editing locks have PHP regression coverage. This
does not establish complete iOS, screen-reader, theme or large-text validation.
See [the evidence and remaining checks](search-family-validation-2026-09-14.md).
