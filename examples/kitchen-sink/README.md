# PAM Native UI premium showcase

This executable example is both a polished five-destination product and PAM
Studio. It demonstrates the three supported authoring styles in one native
tree:

- `resources/native/catalog.pam` builds the screen with concise declarative
  tags and utility classes;
- `src/TypedCommunityCard.php` builds a reusable component with typed PHP
  facades and `Style`;
- PAM primitives, application components and third-party native plugin tags
  can be inserted beside either style without changing renderer or bridge.
- Overview, Orders, Activity and Profile demonstrate product states,
  persistent tab navigation, typed forms and branded light/dark themes.
- Studio keeps the complete component laboratory and pairs with the live
  `pam mobile devtools` overlay.

## Run from this workspace

Keep `pam-native-ui` and `pam-native` as sibling directories, then run:

```bash
cd pam-native-ui/examples/kitchen-sink
composer update
PAM_NATIVE_HOME=../../../pam-native pam mobile run
```

During PAM development, build the PAM CLI repository first if it is not already
available. The SDK and CLI are separate repositories.

```bash
cargo build --release --manifest-path ../../../../pam/Cargo.toml
```

The example's path repositories intentionally point to the two local packages.
For a normal application, remove the `repositories` section and install the
published packages instead:

```bash
composer require pushinbr/pam-native:^0.2 pushinbr/pam-native-ui:^0.2
pam mobile codegen
pam mobile run
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

`src/AppTheme.php` installs the contrast-gated PAM light/dark identity. Interaction, animation,
focus, overlays, scrolling, images and transient input state remain native on
Android in both authoring styles.
