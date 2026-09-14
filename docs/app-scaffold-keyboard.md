# App Scaffold keyboard composition

`PAppScaffold` now connects `keyboardAware: true` to PAM Native's existing
`KeyboardAvoidingView`, inside the safe-area root. Previously that prop did not
create any keyboard-aware native element. This is UI composition over an existing
native capability, not a separate keyboard or inset implementation.

The composition forwards `behavior` (native Resize/Pan/Padding values or the
existing supported aliases), `keyboardVerticalOffset` and
`keyboardAvoidingEnabled` (default true). `keyboardAware` defaults false so
existing scaffold child anatomy is unchanged unless explicitly enabled.

The showcase Keyboard aware variation now contains a controlled PTextField,
“Workspace note”, and allocates additional height for the form content. Its
decorative card border/radius has been removed along with the other scaffold
examples. The integrated audit now treats Scaffold as an input scenario rather
than returning a static pass.

PHP matrix regressions cover boolean/string opt-in/out and native behavior,
offset and enablement forwarding. Matrix and targeted PHPStan level 9 pass.
The initial Android test `/tmp/pam-scaffold-keyboard-20260914.json` stopped because
scrolling to the editor moved the route title offscreen. The test now retains
runtime/foreground checks while allowing the title to scroll out, as list tests
already do. The original failing report is retained, not relabeled as a pass.

Android report `/tmp/pam-scaffold-keyboard-scoped-20260914.json` passes on
emulator-5554/API 36, candidate
`ad9367fe9acecb49a648d9639eabaec35f6571795ee2701ee9e6f3958ed7b15a`.
The inspected after screenshot shows the complete editor and typed PAM_AUDIT
above the visible keyboard. A subsequent `keyboard-closed.xml` assertion confirms
the text remains after dismissal. These results cover the demonstrated embedded
field, not arbitrary full-screen layouts or every keyboard behavior.

This contract alone does not prove all keyboard avoidance modes, content resize,
large text, rotation, focus traversal or iOS. An embedded preview cannot substitute
for a full-screen application integration check of safe areas and IME geometry.
