# iOS UI end-to-end certification fixture

The manual `iOS UI end-to-end fixtures` workflow builds a real PHP/.pam app with
PAM Native UI through Composer's local candidate repositories. PAM's public
`mobile ios:prepare` command generates the Xcode host. `attach-uitests.py`
adds an XCUITest target to that generated project, leaving the product host
implementation untouched.

The first fixture covers autocomplete filtering and selection, combobox
filtering and selection, and six-digit OTP editing. Tests assert stable
accessibility identifiers and labels, interact through the simulator, and
attach screenshots to the `.xcresult`. The workflow uploads the result bundle,
exported captures, and a SHA-256 manifest.

This is an initial interaction sample. It does not certify the full iOS
component matrix or change the parity status.

For a quick Linux or macOS fixture check with sibling `pam-native` checkout:

```bash
PAM_NATIVE_ROOT=../pam-native php certification/ios-e2e/check-fixtures.php
```
