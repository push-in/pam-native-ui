# Contributing to PAM Native UI

PAM Native UI welcomes components, accessibility fixes, theme work,
documentation and performance evidence. Public Composer packages use the
`pushinbr/pam-*` namespace.

## How the package is built

- `components/*.pam`: one PAM single-file component per file (Language 2,
  typed constructor props, `#[Tag]`, `#[Event]`, scoped CSS). PAM Native
  registers them from `pam-native.plugin.json` (`php.components`).
- `src/app.css`: tokens and fonts only; it is prepended to every component.
  Shared classes live in `src/theme/*.css` and are `@import`ed by the
  components that use them, so each compiled component stays small.
- `src/`: PHP helpers (options, calendar, field rules, forms).
- No Android or iOS code: components are drawn with PAM Native primitives.

## Rules for components

- Inputs bind with `p-model`: take `modelValue`, emit `model` and `change`.
- Every prop is a typed constructor parameter with `#[Prop(immutable: false)]`.
- Touch targets are at least 48 dp; set roles, labels, checked/selected state
  and live regions.
- Write light and dark rules (`prefers-color-scheme: dark`) and iOS rules
  (`platform: ios`) next to each other.
- Nothing runs while hidden: no timers or listeners outside the visible state.

## Checks

```bash
composer install
php tests/run.php
php tools/component-docs.php   # regenerate docs/components.md
python3 tools/e2e_android.py --serial <device>   # with examples/showcase running
```

Commit messages follow `type: summary` (`fix:`, `feat:`, `docs:`).
