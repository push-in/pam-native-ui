# Performance

Measured on 2026-11-17 with the showcase debug build on an Android 16
emulator (`pam-ci-36`, x86_64, 1080x2400, host GPU) and PAM Native 1.51.0.

| Scenario | Result |
| --- | --- |
| 500-row `VirtualizedList` of `p-list-item` (avatar, title, subtitle), 9 flings | 268 frames, 2.2% janky, p50/p90/p95 16 ms, p99 24 ms (`dumpsys gfxinfo`) |
| Memory after the flings | 147 MB PSS total, 29 MB native heap, 22 MB Java heap (`dumpsys meminfo`, debug build with the PHP runtime) |
| CPU while idle on a screen | 0.0% (`top`) |
| Compiled component cache (31 components) | 4.2 MB, from 31 MB before PAM Native 1.51 stopped copying the stylesheet IR into every `@media` block |

What keeps it lean:

- `p-slider` moves its thumb, fill and value label on the UI thread with PAM
  Native's `Drag`; PHP gets one `model` event on release.
- `p-text-field` syncs with the `debounced` mode by default: live validation
  without one bridge call per keystroke, and typing never fights a stale echo.
- `p-snackbar` starts its timer only while shown and cancels it on hide or
  unmount; nothing else in the package schedules work.
- Switch thumbs, pressed states and the tab indicator are CSS transitions run
  natively; `prefers-reduced-motion` disables them.
- The package theme (`src/app.css`) holds only tokens and fonts; shared
  classes are imported only by the components that use them.

Reproduce: run `examples/showcase` with `pam dev`, open Lists, then

```sh
adb shell dumpsys gfxinfo dev.pam.nativeui.showcase.debug reset
# fling the list
adb shell dumpsys gfxinfo dev.pam.nativeui.showcase.debug
adb shell dumpsys meminfo dev.pam.nativeui.showcase.debug
```

Known limit: `p-select` renders its options in a `ScrollView` inside the
sheet. A `VirtualizedList` inside a `BottomSheet` fails on Android today
([push-in/pam-native#262](https://github.com/push-in/pam-native/issues/262));
the sheet will switch to a virtualized list for very long option lists once
that is fixed. Lists on a screen (`p-screen scroll="false"` +
`VirtualizedList`, as in the showcase) are virtualized already.
