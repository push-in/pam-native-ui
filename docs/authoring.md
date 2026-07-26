# Authoring applications

PAM Mobile UI has one renderer and three authoring styles. Tags, typed PHP and
raw/native extensions all produce the same retained PAM element tree; choosing
one does not add a WebView, JavaScript runtime or second bridge.

## Declarative tags

This is the shortest style and usually feels most familiar to Laravel and Vue
developers:

```xml
<SafeAreaView class="ui-bg">
    <VStack class="p-6 gap-4">
        <p-heading size="2xl">Create account</p-heading>
        <p-input>
            <p-input-field
                value="{{ $email }}"
                keyboardType="email"
                on:change="setEmail"
            />
        </p-input>
        <p-button on:press="submit">
            <p-button-text>Continue</p-button-text>
        </p-button>
    </VStack>
</SafeAreaView>
```

Templates are parsed and validated once. Utility classes compile into numeric
native properties before crossing the PAM protocol; Android never receives CSS
or class-name strings.

## Typed PHP

Use facades when IDE completion, refactoring and enum props matter more than
template terseness:

```php
use Pam\MobileUi\Component\Button;
use Pam\MobileUi\Component\ButtonText;
use Pam\MobileUi\Component\Card;
use Pam\MobileUi\Component\Heading;
use Pam\MobileUi\Component\VStack;
use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\Native\Style;

$screen = Card::make(
    null,
    VStack::make(
        ['space' => 'md'],
        Heading::make('Checkout')->size(ComponentSize::ExtraLarge),
        Button::make(
            null,
            ButtonText::make('Pay now'),
        )
            ->variant(ButtonVariant::Default)
            ->onPress($pay),
    ),
)->style(new Style(padding: 24.0));
```

Every public tag has a facade under `Pam\MobileUi\Component`; `Switch` is named
`SwitchControl` in PHP because `switch` is reserved.

## A typed component used as a tag

Reusable application components can expose a declarative surface:

```php
use Pam\Native\TemplateRegistry;

TemplateRegistry::component(
    'OrderSummary',
    static fn (array $props, array $_children, ?object $_scope): OrderSummary =>
        new OrderSummary(orderId: (int) ($props['orderId'] ?? 0)),
);
```

```xml
<OrderSummary orderId="{{ $order.id }}" />
```

The component may return PAM Mobile UI facades, PAM core primitives, another
template or a combination of them.

## Theme tokens

Override only the semantic tokens owned by the application:

```php
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\Themes;

PamUI::theme(
    Themes::light()->withColors([
        ColorToken::Primary->value => Color::rgb(37, 99, 235),
    ]),
    Themes::dark()->withColors([
        ColorToken::Primary->value => Color::rgb(147, 197, 253),
    ]),
);
```

Light, dark and system modes resolve per provider subtree. Tokens and recipes
are compiled in PHP; Android receives resolved integer colors.

## Escape hatches

You are not required to follow a component-class structure:

- use `Pam\Native\UI` primitives directly for low-level layout;
- use `Pam\MobileUi\Component\UiNode` for a component selected dynamically by
  tag name;
- register application tags through `TemplateRegistry`;
- install a PAM plugin that exposes Kotlin/Java Android views;
- use `CustomView::make()` for an application-owned generated native view.

Native extension views still participate in PAM identity, Rust reconciliation,
layout, accessibility and the bounded binary event channel. See the PAM Native
plugin guide for packaging Android sources, resources, AARs and JNI libraries.

## Native directives

PAM directives use the `p-*` namespace and compile directly to retained native
properties and listeners. They are not Vue directives and do not require a
JavaScript runtime:

```xml
<p-card
    p-ripple
    p-click-outside="closeMenu"
    p-intersect="visibilityChanged"
    p-resize="resized"
    p-touch-start="dragStarted"
    p-touch-move="dragMoved"
    p-touch-end="dragEnded"
>
    <p-text>Native interaction surface</p-text>
</p-card>
```

| Directive | Native behavior | Payload |
| --- | --- | --- |
| `p-ripple` | Android `RippleDrawable`; UIKit state layer | no application event |
| `p-click-outside` | Root pointer observer / simultaneous host recognizer | `pageX`, `pageY` |
| `p-intersect` | Visible-frame observer | intersection state |
| `p-mutate` | Deduplicated frame mutation observer | `x`, `y`, `width`, `height` |
| `p-resize` | Deduplicated size observer | `width`, `height` |
| `p-scroll` | Native scroll delegate, coalesced per frame | native scroll offset |
| `p-touch-start` | Native pointer recognizer | local and page coordinates |
| `p-touch-move` | Native pointer recognizer | local and page coordinates |
| `p-touch-end` | Native pointer recognizer | local and page coordinates |

`p-ripple` accepts a boolean, an ARGB integer, or a map containing `color`,
`alpha`, `borderless`, `radius`, and `foreground`. A color of `0` inherits the
component foreground and current appearance. Gesture animation never crosses
the PHP boundary. Observers and recognizers detach on update, unmount, hot
reload, and runtime shutdown.

The runnable combination of all styles is in
[`examples/kitchen-sink`](../examples/kitchen-sink).

For product screens, typed forms, async states, motion, tabs, generators and
DevTools, continue with
[`product-foundations.md`](product-foundations.md).
