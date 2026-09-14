# Consolidated form review

Initial Samsung batch `/tmp/pam-fields-consolidated-20260914.json` exercised Text
Field, Textarea, Password Field, Masked Field, Currency Field, Tag Input and Multi
Select on the auto-fit candidate. The first five retained input; password reveal/
hide retained the value, phone masking produced `(21) 91234-5678`, and currency
produced `731,25`. Raw before/after images were viewed, including keyboard-open
states. Field labels and editors remained aligned in those captured states.

This initial batch was not full approval: tags and multiple selection only opened/
closed. Its password assertion also missed cursor position: the viewed image showed
the cursor at the beginning after a visibility change. PAM Native now preserves
the selection during input configuration; UI did not acquire native compensation.

The reusable audit was strengthened:

- Editable value checks target the focused editor instead of accepting matching
  text from any field.
- Password visibility is toggled three times, typing after reveal and hide without
  moving the cursor. The final visible value must contain both appended characters.
- Tags and Multi Select add an option, close/reopen, remove it and assert that the
  original field selection is restored. Opening a changed screenshot is insufficient.
- Clearing formatted fields batches the same 17 key events into one ADB invocation.

The first strengthened selection attempt failed in the harness: it used the clicked
wrapper's identity rather than the accessible Spinner's stable field label. XML
showed the correct Skills/Teams field still present. This selector was corrected;
the failure is retained in `/tmp/pam-fields-retention-20260914.json`, not classified
as a UI defect. Currency passed that attempt with batched key events.

Final password candidate SHA:
`13acbe124a45d398ac68cb03417d9c7684164790ceaaa73d428394b1dc031830`.
`/tmp/pam-fields-fixed-20260914.json` contains the passed password cursor flow;
its viewed final screenshot shows `PAM_AUDIT_2026XX` with the cursor at the end.
That same report retains selection-harness failures: the uncompressed hierarchy
contains a noninteractive Spinner wrapper and an interactive Spinner with the
same label/bounds. Selection lookup now requires the enabled clickable Spinner,
as the existing dedicated selection audits do. This was not a second UI failure.

`/tmp/pam-fields-selection-final-20260914.json` passed both strengthened selection
flows on the same final APK: Swift was added/removed from Skills, Engineering
from Teams, with the original selections restored after reopening. Each flow took
about 28 seconds. Added-state screenshots were viewed: chips remain inside the
field, with aligned labels and preserved spacing. These are scoped functional and
visual checks, not full approval of either component.

This batch does not establish custom tag creation, search, all variants, larger
fonts, readonly/disabled interactions, RTL, IME composition, iOS or performance
approval. Existing dedicated scripts cover additional flows but their historical
results are not promoted to current-candidate proof. No publication was performed.
