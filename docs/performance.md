# Performance evidence

PAM Mobile UI keeps interaction progress inside Android and sends PHP only the
semantic result. Benchmarks are split by layer so a fast PHP microbenchmark
cannot be mistaken for smooth rendered frames.

## Android UI-thread microbenchmark

Reference run:

- date: 2026-07-24;
- device: Samsung SM-S918B (Galaxy S23 Ultra);
- Android API: 36;
- build: debug, instrumented;
- samples: 10,000 host updates, 10,000 slider moves, 2,000 calendar
  draws, 10,000 DateTimePicker updates, 10,000 Accordion toggles and
  2,000 host lifecycles;
- warm-up: 1,000 host updates, 1,000 DateTimePicker updates, 1,000
  Accordion toggles and 200 calendar draws.

| Operation | p50 | p95 | p99 | max |
| --- | ---: | ---: | ---: | ---: |
| Decode/apply native host update | 15 µs | 36 µs | 39 µs | 116 µs |
| Slider move and local redraw | 11 µs | 19 µs | 23 µs | 1,851 µs |
| Calendar grid draw | 1,197 µs | 1,288 µs | 1,330 µs | 1,399 µs |
| DateTimePicker property update | 21 µs | 25 µs | 44 µs | 5,164 µs |
| Accordion semantic toggle | 10 µs | 16 µs | 20 µs | 2,804 µs |
| Create, update and release host | 96 µs | 120 µs | 143 µs | 454 µs |

The 10,000 slider moves emitted zero per-move bridge callbacks and one final
semantic `CHANGE` event. This is a 10,000:1 reduction versus an implementation
that sends every sampled movement across the language boundary; it is not a
claim that PAM is 10,000 times faster than another framework.

The 2,000 calendar frames drew the complete six-week range state, today
indicator and disabled dates directly into an Android canvas with zero bridge
events. The 10,000 DateTimePicker updates parsed limits, time-zone offsets and
changing ISO values on the native UI thread with zero bridge events. The
10,000 Accordion state changes each emitted exactly one final semantic toggle;
content visibility and animation do not emit frame callbacks. The instrumented
benchmark fails when update, DateTimePicker update, Accordion toggle, gesture
or calendar draw p99 exceeds 4 ms, or lifecycle p99 exceeds 8 ms. Run it on a
connected device:

```bash
../pam-native/android/gradlew \
  -p android \
  connectedDebugAndroidTest \
  -Pandroid.testInstrumentationRunnerArguments.class=dev.pam.mobileui.MobileUiHostPerformanceInstrumentedTest
```

The JSON result is logged under `PamMobileUiBench`.

## PHP composition and binary encoding

The reference local run composed a realistic styled form subtree and encoded
the complete PAM binary tree 2,000 times:

| Metric | Result |
| --- | ---: |
| Total | 679.350 ms |
| Throughput | 2,944 trees/s |
| Encoded frame | 1,430 bytes |
| Peak PHP memory | 4 MiB |
| PHP | 8.4.23 |

Reproduce it with `composer benchmark`.

## Claim boundary

These measurements validate bounded host work and the absence of per-frame PHP
traffic on one high-end physical device. They do not yet establish an end-to-end
speedup over React Native Nitro Modules. Cold start, mount/update through the
full PAM renderer, frame p50/p95/p99, jank, memory and matched-device competitor
baselines remain required before the performance gate can move from `planned`
to `verified`.
