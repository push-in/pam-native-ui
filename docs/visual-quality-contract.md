# PAM Native UI visual quality contract

This contract is a release gate for every public component and for the showcase.
A structural render or a changed value is evidence of execution, not visual
approval.

## Foundations

- PAM Spectrum uses forest green for primary actions and confirmed progress,
  expressive violet for tonal/selected emphasis, cobalt for information, and
  coral for warnings or destructive attention. A screen must not assign these
  colors decoratively in ways that contradict their semantic role.
- The light canvas is a cool near-white, not pure gray. White is reserved for
  the lowest/elevated surface; lavender container steps establish depth without
  adding borders around every group.
- Layout follows a 4 dp base grid and an 8 dp spacing rhythm. Exceptions must
  come from a platform control's native geometry, not arbitrary screen values.
- Interactive controls preserve a minimum 48×48 dp target even when their
  visible indicator is smaller.
- Text uses the PAM type scale with explicit size, line height, weight and
  semantic foreground color. Android uses the platform Roboto family and iOS
  uses San Francisco so controls retain native metrics; brand character comes
  from hierarchy, weight and color rather than an unregistered display font.
  Font scaling must not clip or overlap controls.
- Body copy defaults to 14/20 or 16/24, component titles to 22/28, and compact
  metadata to 11/16. Uppercase is reserved for short eyebrows and status
  markers, never paragraphs or control labels.
- Color, radius, elevation, state layers and motion come from shared tokens.
  Showcase-only styling must never disguise a defect in the public component.
- Focused, pressed, selected, disabled, loading, error and read-only states are
  visually distinguishable without relying on color alone.
- Motion explains state changes, respects reduced-motion settings and remains
  native-thread smooth during continuous gestures.

## Showcase composition

- Demonstrations sit directly on the page canvas with headings and deliberate
  vertical rhythm.
- Variation markers identify the specimen but never become the dominant object:
  26 dp high, 11–12 sp bold, semantic color, and an 8 dp gap before the real
  component.
- A card is used only when the card itself is being demonstrated or represents
  a real domain grouping. Decorative wrapper cards and card-inside-card layouts
  are prohibited.
- Every public component exposes at least four meaningful specimens. Controls
  additionally show the applicable interactive, disabled, error, loading,
  read-only and edge-case states.
- Specimens must demonstrate materially different capability; renaming the same
  rendering or forwarding an ignored property does not count as a variation.

## Approval

A component is approved only when all of the following are true:

1. Its public implementation and shared tokens pass structural, accessibility
   and state tests.
2. Every showcase specimen is visible without clipping, accidental nesting,
   edge collisions or ambiguous hierarchy on the reference Android viewport.
3. Its real interactions are exercised on the physical Samsung reference
   device, including continuous gestures where applicable.
4. A human visual review approves typography, spacing, alignment, proportions,
   contrast, motion and all exposed variations.
5. Documentation screenshots and GIFs are captured from that approved build and
   match its artifact hash.

Any failed item keeps the component pending regardless of automated `PASS`
output.
