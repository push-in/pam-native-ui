# Contributing to PAM Mobile UI

PAM Mobile UI welcomes components, themes, accessibility fixes, native
behaviors, documentation and performance evidence. Public Composer packages use
the `pushinbr/pam-*` namespace.

## Local setup

Keep `pam-native` and `pam-native-ui` as sibling directories. PHP 8.4, Java 21
and an Android SDK are required.

Install the isolated PHPStan toolchain:

```bash
composer install --working-dir=tools/phpstan
```

Run the complete PHP and generated-source gate:

```bash
PAM_NATIVE_ROOT=../pam-native composer verify
composer validate --strict --no-check-publish
```

Run Android compilation, unit tests and lint:

```bash
../pam-native/android/gradlew \
  -p android \
  -PpamNativeRoot="$(cd ../pam-native && pwd)" \
  testDebugUnitTest \
  lintDebug \
  compileDebugAndroidTestKotlin
```

With a device or emulator connected, run the behavioral suite:

```bash
../pam-native/android/gradlew \
  -p android \
  -PpamNativeRoot="$(cd ../pam-native && pwd)" \
  connectedDebugAndroidTest \
  -Pandroid.testInstrumentationRunnerArguments.class=dev.pam.mobileui.MobileUiHostInstrumentedTest
```

## Generated API and parity

Do not hand-edit `src/Generated/*`, `docs/catalog.md` or
`resources/parity.json`. Edit the source inventory, recipes or generator, then
run:

```bash
composer generate
composer verify
```

`resources/pam-ui-components.json` and `resources/parity.json` are internal
renderer-regression inventories. They must never be registered or autoloaded as
public PascalCase components. The public surface is authored in
`resources/pam-material-components.php` and gated by
`resources/material-parity.json`.

Every public tag must:

- have a typed facade and deterministic integer component ID;
- compile in light and dark themes;
- support every captured variant, compound variant and applicable state;
- emit only semantic events across the PAM boundary;
- preserve native accessibility semantics.

Transient gesture, focus, animation, scroll and input progress belongs on the
Android UI thread. Avoid callbacks to PHP on every frame or pointer sample.

## Performance evidence

Run `composer benchmark` for PHP composition. Physical Android results belong
in `docs/performance.md` with device, API level, build type, sample count and
percentiles. Do not convert bridge-event reduction into a framework speedup
claim, and do not mark comparative performance verified without a matched
end-to-end baseline.

## Licensing

Contributions are distributed under the repository's Apache License 2.0.
Preserve third-party notices and include the license of any incorporated
material.
