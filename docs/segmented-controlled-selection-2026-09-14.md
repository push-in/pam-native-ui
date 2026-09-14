# Segmented Button controlled selection

The integrated Android scenario now asserts exact checked/selected states:
single Day → Week, multiple Day/Month → Day → Day/Week, readonly rejection,
disabled-group rejection, and disabled Edit retaining selected View. It also
checks initial Week selection in Icons and Disabled examples. A pixel change
alone no longer passes this component's integrated scenario.

The first run `/tmp/pam-segmented-contract-20260914.json` exposed a showcase
defect: the default value was the string `week`, while the actual item values
were integers 1/2/3. Icons and Disabled therefore displayed no selected item.
The showcase default is now integer 2, with a PHP regression. No native runtime
or component workaround was needed.

Final candidate SHA-256:
`fd5a9f8bf7a26ad90ceb1383306be439bf073b7574750283ba4486cb906249c4`.
`/tmp/pam-segmented-fixed-20260914.json` passes the strengthened scenario on
emulator-5554/API 36, font scale 1.0. The before screenshot was inspected:
Week is selected in Icons and Disabled, and visible labels/icons fit their
segments. The report proves the stated interactions, not all variations or
full component approval. Large text, RTL, TalkBack, motion and iOS remain open.

The PHP material matrix, targeted showcase PHPStan, Python syntax and diff
checks pass. One changed-showcase Android build took 10 seconds and cleaned
96.8 MiB of old development artifacts. Only the failing component scenario was
rerun; no publication occurred.
