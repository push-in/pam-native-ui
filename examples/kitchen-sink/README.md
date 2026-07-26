# PAM Mobile UI kitchen sink

This executable example demonstrates the three supported authoring styles in
one native tree:

- `resources/native/catalog.pam` builds the screen with concise declarative
  tags and utility classes;
- `src/TypedCommunityCard.php` builds a reusable component with typed PHP
  facades and `Style`;
- PAM primitives, application components and third-party native plugin tags
  can be inserted beside either style without changing renderer or bridge.

## Run from this workspace

Keep `pam-mobile-ui` and `pam-native` as sibling directories, then run:

```bash
cd pam-mobile-ui/examples/kitchen-sink
composer update
../../../pam-native/target/release/pam mobile run .
```

During PAM development, build the CLI first if it is not already available:

```bash
cargo build --release --manifest-path ../../../pam-native/Cargo.toml
```

The example's path repositories intentionally point to the two local packages.
For a normal application, remove the `repositories` section and install the
published packages instead:

```bash
composer require pushinbr/pam-native:^0.2 pushinbr/pam-mobile-ui:^0.2
pam mobile codegen
pam mobile run .
```

## Where to start

Edit `resources/native/catalog.pam` when you want Laravel/Vue-like templates.
Create a class like `TypedCommunityCard` when IDE discoverability, reuse or
strict enum props are more useful. Registering that class exposes it as a tag:

```php
TypedCommunityCard::register();
```

```xml
<TypedCommunityCard
    title="Your component"
    description="A typed PHP component embedded in a declarative screen."
/>
```

`src/AppTheme.php` shows light/dark token overrides. Interaction, animation,
focus, overlays, scrolling, images and transient input state remain native on
Android in both authoring styles.
