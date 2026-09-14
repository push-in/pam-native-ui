# List Item direct-slot foreground

Bare native Text children of PListItem now receive the resolved item foreground
when they do not specify their own textColor. Selected-state color and an explicit
item style override are respected; explicit child colors are preserved. This is
UI slot composition, not a change to the native Text primitive. Nested custom
content is intentionally not recolored recursively.

The preceding shared-overlay regression run
`/tmp/pam-overlay-consumers-20260914.json` passed Menu, Tooltip and Speed Dial on
the collision-fix APK. Open screenshots were inspected: actions/content were
visible, but Menu's enabled items used platform gray. The Tooltip assertion is
pixel-based; its open screenshot was explicitly checked for actual tooltip text.

After the foreground change, `/tmp/pam-list-foreground-20260914.json` passes
on APK `fb71b23ebc1f422795b554c22c05ff74a00a834b71455fc8026e6169a04a11bb`:
Menu action/feedback/dismissal and the generic List Item press scenario on
emulator-5554/API 36 at font scale 1.0. The new Menu open screenshot was viewed:
enabled action labels now use the dark semantic foreground. This is not full
List Item state/interaction approval, contrast measurement, or theme/iOS coverage.

PHP regressions cover selected/unselected inherited color and explicit child
color preservation. The render matrix and targeted PHPStan pass. The tested APK
uses the equivalent nullsafe expression before PHPStan's redundant-nullsafe
cleanup; the cleanup removes only `?` before property access on the left of `??`.
No behavioral rebuild was performed for that syntax-only change.

A separate showcase issue remains: the Menu/Tooltip branch forces BottomStart,
so requested placement variations need review before their captions can count as
placement coverage. No publication was performed.
