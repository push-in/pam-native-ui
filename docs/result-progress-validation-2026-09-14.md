# Result feedback and selection semantics

Result State now displays a native activity indicator while loading, with
progress-oriented default copy and a busy action. Nonloading status icons use
their semantic foreground tokens instead of fixed white; title/description
width is bounded. Blocked selection fields now report collapsed rather than
echoing `open=true` while their portal is hidden.

Material matrix and configured PHPStan level 9 passed. API 36 emulator APK:
`19939394b8711d13717af39868d67a1437d8c36a779c17b16b43e01e95faf277`.
`/tmp/pam-ui-result-progress-host-20260914/report.json` records the enabled action
and rejected disabled/loading actions. Inspected the loading screenshot from
the same APK: native spinner visible inside the accent circle, preparing text,
and disabled action aligned. Full approval remains false.

The initial audit at `/tmp/pam-ui-result-progress-20260914` failed because it
incorrectly required `android.widget.ProgressBar` in the accessibility XML.
PAM uses a custom native host. The visible spinner was confirmed in
`loading-inspection.png`; the script now checks the progress copy and captures
the screen for visual inspection instead of claiming the XML proves its pixels.
No animation smoothness or TalkBack claim is made from that screenshot.

Only result interactions were rerun after correcting the harness; no repeated
range tests or second build. Other component evidence retains its original APK
hash and is not promoted to coverage of this APK. No release/media publication.
