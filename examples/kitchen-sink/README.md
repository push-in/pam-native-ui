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

Keep `pam-native-ui` and `pam-native` as sibling directories. Build this example
from an isolated application directory outside both repositories, following
[the isolated Composer build procedure](../../docs/build-hygiene.md).
Do not mirror the UI package into `vendor` inside its own source tree: that can
copy build caches recursively or leave an older installed package in use.

Before installing dependencies, inspect the manifests and lockfile, check
package compatibility, and run `composer install --dry-run`. Verify the actual
installed Native/UI versions as well as the lockfile before building.

During PAM development, build the PAM CLI repository first if it is not already
available. The SDK and CLI are separate repositories.

```bash
cargo build --release --manifest-path /path/to/pam/Cargo.toml
```

The example's path repositories intentionally point to the two local packages;
adjust these paths in the isolated copy to the external package sources.
For a normal application, remove the `repositories` section and install the
published packages instead:

```bash
composer require pushinbr/pam-native:^1.0.27 pushinbr/pam-native-ui:^1.0.9
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
