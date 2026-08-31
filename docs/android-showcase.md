# Android visual showcase

PAM Native UI ships a real Android catalog application for the complete
Material surface. The evidence in this page was captured from the production
renderer on an Android API 36 emulator at 420 dpi; it is not a web mockup.

**84/84 components · 90 verified screens · 7 interaction recordings**

The automated visual audit rejects empty renders, unexpected routes, invalid
viewports and overlapping text. Its machine-readable result is available in
[`visual-audit.json`](assets/android/components/visual-audit.json), alongside
the [device manifest](assets/android/components/manifest.json).

## Catalog application

<p align="center">
  <img src="assets/android/components/overview.png" width="31%" alt="PAM Studio Android showcase home" />
  <img src="assets/android/components/screen-all.png" width="31%" alt="Complete PAM Native UI component workbench" />
  <img src="assets/android/components/screen-forms.png" width="31%" alt="PAM Native UI forms showcase screen" />
</p>

The home screen presents the product promise, verified component count and
native targets. The workbench groups actions, forms, navigation, data and
overlays while preserving direct routes for deterministic testing and docs.

## Native interaction evidence

These recordings are produced with Android `screenrecord` while the app is
running. Touch indicators are enabled so state changes can be inspected.

| Navigation | Expansion panels |
| --- | --- |
| ![Opening the component workbench](assets/android/gifs/navigation.gif) | ![Switching the expanded native panel](assets/android/gifs/expansion-panels.gif) |

| Dialog | Menu |
| --- | --- |
| ![Opening and dismissing a native dialog](assets/android/gifs/dialog.gif) | ![Opening and dismissing a native menu](assets/android/gifs/menu.gif) |

| Tabs | Slider |
| --- | --- |
| ![Changing a native tab](assets/android/gifs/tabs.gif) | ![Dragging a native slider](assets/android/gifs/slider.gif) |

| System time picker |
| --- |
| ![Opening the Android system time picker](assets/android/gifs/time-picker.gif) |

## Complete component evidence

Every public tag has an individual full-device screenshot. Open the
[component catalog](catalog.md) to access all 84 files next to their matching
component names. Parent and anatomy tags intentionally receive separate files
even when they share the same composed preview.

Representative surfaces:

<p align="center">
  <img src="assets/android/components/p-data-table-virtual.png" width="23%" alt="Bounded virtual data table" />
  <img src="assets/android/components/p-expansion-panels.png" width="23%" alt="Expansion panel variations" />
  <img src="assets/android/components/p-time-picker.png" width="23%" alt="Time picker variations" />
  <img src="assets/android/components/p-snackbar.png" width="23%" alt="Snackbar variations" />
</p>

## Reproduce the evidence

Install the kitchen-sink application on exactly one emulator, or select a
device explicitly, then run:

```bash
ANDROID_SERIAL=emulator-5554 \
  tools/capture-showcase-android.sh docs/assets/android/components

ANDROID_SERIAL=emulator-5554 \
  tools/capture-showcase-android-gifs.sh docs/assets/android/gifs
```

The screenshot command validates all 90 screens before it writes the device
manifest. The GIF command reopens each deep link, performs the real native
interaction and encodes a documentation-sized animation with FFmpeg.
