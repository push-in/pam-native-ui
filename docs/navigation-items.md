# Controlled navigation items

Navigation Bar and Navigation Rail accept `items` when explicit children are
not supplied. Each item has `value` (or `id`), `label` (or `title`), optional
`icon`, and `disabled`/`isDisabled`. The component emits the destination value
through `onChange`; the application owns navigation and updates `modelValue`.
Use domain enums backed by sequential integers for destination identifiers.

The whole destination is a native pressable tab, including its visible label.
Selected destinations and disabled destinations do not emit another selection.
Explicit children still take precedence for fully custom composition. This is
UI composition over PAM Native primitives, not an additional navigation runtime.

Showcase defaults use integer destination 2, matching the generated items.
The preview resolves selection from an interaction value first, then the
variation's explicit modelValue, then the fallback. It no longer overwrites an
explicit initial selection with 2. PHP regressions cover resolved defaults and
explicit values 1/3 for both Bar and Rail; targeted showcase PHPStan passes.
This follow-up does not alter current catalog initial selections, so no new
Android build was made solely for it. Its custom-initial-selection render path
has not received separate device verification.

The rail uses icons with accessible labels when compact, and visible labels
when expanded (text-only items remain visible). Navigation Bar labels wrap and
retain their complete accessible name. Each generated destination uses its native
intrinsic content width with a minimum of 64dp; the bar wraps
whole destinations onto additional rows when the available width is insufficient.
Its height is content-driven, so applications must not impose a fixed height when
using enlarged text. Explicit custom children keep their own sizing policy.
The font is not reduced to fit. Text/plain/outlined/tonal surfaces and compact
density now have distinct styling; explicit elevation remains an override.

## Scoped evidence, not complete approval

The reports below document earlier candidates, including the now-superseded
single-line policy. Current adaptive-layout evidence is tracked in
`navigation-search-batch-2026-09-14.md`; do not treat these historical captures as
validation of the current composition.

`/tmp/pam-ui-navigation-items-20260914/report.json`, API 36 emulator APK
`7d35b0b957ce24a7577087f8911d9c2b1816bdb6d5f8b71a138c168854a509cc`:
both components passed selection and disabled-item rejection at font 1.0/2.0.
The bar was activated by tapping its label. Compact rail uses the icon target.

Screenshot review found `Activity` wrapping mid-word in four-destination bars
at 2.0. The subsequent single-line/ellipsis correction passed the material
matrix but is **not included in this APK evidence** and still needs visual
verification. Do not claim all layouts, variants, themes, TalkBack, expanded
rail behavior, Samsung or iOS are approved from this scoped test.

Follow-up APK `a0f60526641c65a6f4b3121c30446d85fcb93a00faea2cb6a1f5c459fa1aad7a`:
`/tmp/pam-ui-navigation-labels-20260914/report.json` passed selection/disabled
checks for the bar at 2.0. Inspected the new capture: labels no longer break
mid-word onto a second line, and four/five-destination rows remain aligned.
Narrow labels use visible ellipses, rather than smaller text. This is the intended
single-line behavior, not a claim that every label fits in full visually.
The complete label remains on the tab accessibility node; TalkBack was not run.
Configured PHPStan level 9 and the material matrix passed. No release or public
media publication was performed.
