# Number Input: confirmation coverage gap

The existing Android audit types `11` into Hidden controls and checks editing,
rotation and keyboard geometry. Its minimum/maximum assertions exercise step
buttons, not an out-of-range typed value followed by confirmation. Do not count
those assertions as proof that typed bounds are reconciled visually.

Code inspection establishes this path:

1. ComponentRenderer wraps Change to clamp, snap and format numeric payloads.
2. The default input sync mode is Debounced (48 ms).
3. Android PamRenderer.applyInputValue ignores differing authored text while
   focused if nativeValueAcknowledged is false. This protects ongoing edits.
4. The blur listener dispatches Change for Native/OnBlur modes, but does not
   explicitly reconcile already-deferred authored text for Debounced mode.

This is a concrete hypothesis for the observed `731` text beyond the showcase's
maximum; it is not yet a device reproduction proving the cause. The next
focused test must type a value above max, confirm or move focus, assert the
visible normalized value and retained sibling value, then repeat below min and
with decimal precision. Also check that rapid typing and cursor position remain
stable. Do not solve this by making all inputs immediate or disabling the
existing stale-update protection. If confirmed, reusable synchronization belongs
in PAM Native; numeric bounds and formatting policy remain in PAM Native UI.

## Reproduction confirmed

`tools/audit-number-confirmation-android.py --serial emulator-5554 --output
/tmp/pam-number-confirmation-20260914` exited 1 on installed release APK
`f2b057e675f814d716aee1496f5c945a0a5bb0c36e6fe5a0cd107ab6bb0c5468`.
The first field displayed `731` during editing and still displayed `731` after
focus moved to the second editor. The second value remained `3`. The explicit
focus-loss assertion passed; the expected normalized `20` assertion failed.
Report, hierarchy dumps and screenshot are in the output directory. This
confirms the user-visible defect; the runtime cause still needs a regression
at the deferred authored-value boundary before implementation is approved.

## Android focused fix passed

PamRenderer now retains a deferred authored value when focused edit protection
rejects it, clears it on newer native typing or successful application, and
reconciles it on focus loss. Numeric policy remains unchanged in UI.
The same focused test passed after a 17-second Android build, on APK
`9baf0bf0f2409e87283cd456ce579172528c63d19e9b3febff113fae5f7908ac`:
editing `731` -> confirmed `20`; sibling remained `3`. Evidence directory:
`/tmp/pam-number-confirmation-fixed-20260914`. Build cleanup removed 96.8 MiB.
This proves the reproduced maximum-on-blur case, not minimum/decimal/rapid
typing, all input sync modes, or iOS parity. Those remain scoped regressions.

Renderer instrumentation now additionally covers preserving the active cursor,
applying the pending normalization on focus loss, sibling isolation and
discarding a pending normalization after newer typing. This new instrumentation
has not run yet. iOS code inspection shows `setFormattedTextFromRenderer` calls
`setTextFromRenderer` directly: it has no equivalent deferred-value gate, so the
Android patch was not mechanically copied into UIKit. That observation does
not replace iOS interaction validation.

The renderer instrumentation now passed on emulator-5554/API 36: one selected
test, zero failures, 7-second Gradle build/execution at Native `defacc0`.
Command used `:app:connectedDebugAndroidTest --offline` with
`android.testInstrumentationRunnerArguments.class=dev.pam.nativeapp.render.PamRendererInstrumentedTest#deferredAuthoredValueAppliesOnBlurWithoutOverwritingNewerTyping`
and `android.injected.build.abi=x86_64`. The first attempt stopped at linking
because the unused local arm64 engine archive predates the child-visibility
symbol; targeting the actual emulator ABI resolved this without rebuilding an
unused architecture. No test assertion failed in that setup attempt.

Additional same-APK confirmation: typed `3.6` remained intact while focused,
normalized to `4` on blur with step 1, and sibling stayed `3` (passed;
`/tmp/pam-number-rounding-fixed-20260914`). The attempted lower-bound input `-7`
became `7` during typing and remained `7` on blur (`/tmp/pam-number-minimum-fixed-20260914`).
That fails input acceptance before it can verify lower-bound normalization.
Android's decimal input mapping lacks TYPE_NUMBER_FLAG_SIGNED; signed numeric
entry requires a separate native capability/policy review. The audit now
explicitly asserts the requested text was accepted before asserting blur.

UI compatibility run 34849797154 completed successfully (all nine jobs), UI
`9d6cb884fd5b1d79f4ce18d793138b353d93f7f0` against Native
`1d3cb56f75bd69dd8189c39fc1ab649a4a10cb9e`. This certifies the preceding Treeview
integration candidate, not the later `defacc0` numeric fix or full UI approval.

Android decimal keyboard/input-mode mapping now includes the signed-number
flag; Number/Numeric remains digit-only for OTP-like fields. Focused renderer
instrumentation passed on API 36 (7 seconds): inserting `-7.5` into Decimal
retains it, switching to Number filters `-75` to `75`. This is native input
acceptance evidence, not yet a rebuilt showcase lower-bound confirmation.
UIKit inspection also found the renderer does not apply keyboardType/inputMode
properties; that platform gap remains to implement and test separately.

Signed lower-bound showcase roundtrip now passed on emulator-5554: `-7` was
accepted intact, normalized to `0` on blur, sibling stayed `3`. Installed APK
`98844101c40ae6e0eba8f49439ce5b61f5a6348ec83df44b0ac8c87203d34ab9`;
output `/tmp/pam-number-signed-minimum-fixed-20260914`. Build took 17 seconds and
cleaned 96.8 MiB. This closes the reproduced Android signed-entry/minimum case.
UIKit keyboard mapping was implemented in Native `4882875` and is being tested
in CI run 34851408115; do not count it as passing before that run completes.
