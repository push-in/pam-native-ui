# Selection composition batch — scoped validation

Shared fixes cover Select, Autocomplete, Combobox, Tag Input and Multi Select:
all three readonly aliases agree, disabled/readonly options have no mutation
callbacks, blocked fields cannot initially open their portal, and tag/selection
content reserves the trailing indicator's width. Long tags can wrap within the
field. The showcase adds long-label and readonly examples for both collections.
All changes belong to UI composition, not PAM core or native primitives.

Code checks passed: the material matrix (114 components, 32,832 style cases,
456 render cases), 25 selection/lock combinations, and configured PHPStan level 9.

API 36 emulator, optimized APK SHA-256:
`ac94b16de8cdfa3ee998ce1c9250141256610cd9eb89a71bc78da553d41f5780`.

- `/tmp/pam-ui-selection-tags-20260914/report.json`: add/remove existing tags,
  retain/remove custom tags after reopening, instance isolation, sheet retention.
- `/tmp/pam-ui-selection-multi-20260914/report.json`: add/remove selections,
  filtered selection, select from empty, instance isolation, sheet retention.
- `/tmp/pam-ui-selection-layout-visible-20260914/report.json`: long-label bounds
  and rejected readonly taps for both controls at font scale 1.0 and 2.0.
- Inspected both 2.0 long-label screenshots: complete wrapped labels, separated
  tags, indicator inside field. These checks do not approve every theme/state,
  TalkBack, iOS or Samsung behavior.

The first layout audit failed its visibility precondition: the fully visible
readonly field ended at y=2096 while the script's arbitrary 87% cutoff was 2088.
Evidence remains in `/tmp/pam-ui-selection-layout-20260914`. The cutoff was
corrected to 94%, still above system navigation; only that audit was rerun,
without rebuilding. Settings restoration is in `finally`.

No formal full-component approvals or published media were added. Build cleanup
removed 88.1 MiB of regenerable artifacts. Temporary captures are diagnostic only.
