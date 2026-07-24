# Parity contract

The authoritative machine-readable gate is
[`resources/parity.json`](../resources/parity.json).

Reference:

- upstream: `gluestack/gluestack-ui`
- commit: `be060b5d184826d34623e490447a467ffb5cfe56`
- committed: 2026-07-06
- package version: `5.0.3`
- captured surface: 61 modules, 404 exports, 326 PHP facades

The original documentation navigation exposes fewer cards than the source tree.
PAM tracks all 61 source modules, including `FlatList`, `SafeAreaView`,
`StatusBar`, provider/util modules, and the full Chat AI anatomy. Core
passthroughs use PAM primitives while remaining present in the public catalog.

A module reaches status `3` (`verified`) only when all applicable gates pass:

- root and documented subcomponent tags;
- typed PHP facade and raw escape hatch;
- sizes, variants, placements and orientations;
- controlled/uncontrolled and disabled/loading/invalid/read-only states;
- native Android interaction and lifecycle behavior;
- TalkBack semantics, focus order, font scale, contrast and target size;
- light/dark/custom theme token coverage;
- documented `.pam` and PHP examples;
- PHP level-9 static analysis and contract tests;
- Android unit/instrumentation tests;
- cold start, mount, update, event and frame-time benchmark evidence.

Alpha upstream components remain labeled `alpha` but are not excluded from PAM
parity.

`composer test:recipes` expands every generated facade through the light and
dark themes, every captured variant option, every compound rule, and the
active, checked, disabled, focused, hovered, invalid, selected and flip state
paths. A selected recipe utility without a packed native implementation fails
the build. Modules with no upstream variant axis use status `4`
(`not-applicable`) for that gate.

All coded fields use sequential integer IDs represented by PHP enums. Human
names under `definitions` are documentation labels; component records store the
IDs. `resources/parity.schema.json` fixes the module count at 61 and rejects
unknown fields.
