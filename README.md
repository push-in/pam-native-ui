# PAM Mobile UI

`pushinbr/pam-mobile-ui` is the official accessible component library for PAM Native.
It gives PHP applications a declarative tag API, typed fluent components, and
native Android behavior without adding a JavaScript runtime.

The catalog tracks all 61 gluestack-ui technical modules, 404 upstream exports,
and 326 component/icon facades at the reference commit recorded in
[`resources/parity.json`](resources/parity.json).
Compatibility means equivalent component anatomy, variants, state transitions,
accessibility semantics, and user-visible behavior adapted to Android rather
than a byte-for-byte port of React code.

## Install

```bash
composer require pushinbr/pam-mobile-ui:^0.1
pam mobile codegen
```

Composer autolinking registers the PHP provider and Android host. No manual
Gradle edit or JavaScript runtime is required.

## APIs

Tags remain terse:

```xml
<VStack class="gap-4 p-4">
    <Heading size="xl">Create account</Heading>
    <FormControl required="true">
        <FormControlLabel>
            <FormControlLabelText>Email</FormControlLabelText>
        </FormControlLabel>
        <Input variant="outline" size="md">
            <InputField
                value="$email"
                keyboard="email"
                on:change="setEmail"
            />
        </Input>
    </FormControl>
    <Button variant="default" size="lg" on:press="submit">
        <ButtonText>Continue</ButtonText>
    </Button>
</VStack>
```

PHP classes provide discoverability and strict types:

```php
use Pam\MobileUi\Component\Button;
use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Enum\ComponentSize;

$button = Button::make('Continue')
    ->variant(ButtonVariant::Default)
    ->size(ComponentSize::Large)
    ->onPress($submit);
```

Applications are never forced into either form. Raw `Pam\Native\UI` elements,
custom components, `.pam` tags, fluent classes, and native plugins can be mixed
in the same tree.

Application utility classes use the same mobile compiler as the captured
component recipes:

```xml
<Card class="w-1/2 p-4 gap-2 justify-between">
    <Heading size="lg">Fast by construction</Heading>
    <Text class="ui-text-muted">Only packed numeric style properties cross.</Text>
</Card>
```

Plugins resolve these classes lazily while composing a changed tree. Unsupported
utilities fail explicitly; CSS or class strings are never sent to Android.

See the installable catalog in
[`examples/kitchen-sink`](examples/kitchen-sink) and the generated inventory in
[`docs/catalog.md`](docs/catalog.md).

## Architecture

- PHP resolves tokens, variants and static composition before encoding.
- The PAM binary tree carries integers/scalars, not JSON or CSS strings.
- Android owns focus, ripple, gestures, overlays, scrolling and local state.
- `create`, `update`, typed event dispatch and release execute on the UI thread.
- Network, disk and expensive decoding stay away from the UI thread.
- Animations use native properties and respect the system animator scale.

See [`docs/architecture.md`](docs/architecture.md) and
[`docs/parity.md`](docs/parity.md). The reproducible PHP and physical-device
results, commands, limits and claim boundary are recorded in
[`docs/performance.md`](docs/performance.md).

## Status

The parity file is the release gate. PHP/tag generation, packed native hosts,
themes, recipe/state matrix expansion, PHPStan level 9, Android
compilation/lint, and the initial kitchen sink are implemented. Entries remain
explicitly `planned`, `implemented`, `verified`, or `not-applicable` per gate;
no unverified component is presented as finished.
