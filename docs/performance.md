# Performance evidence

PAM Mobile UI keeps interaction progress inside Android and sends PHP only the
semantic result. Benchmarks are split by layer so a fast PHP microbenchmark
cannot be mistaken for smooth rendered frames.

## Android UI-thread microbenchmark

Reference run:

- date: 2026-07-23;
- device: Samsung SM-S918B (Galaxy S23 Ultra);
- Android API: 36;
- build: debug, instrumented;
- samples: 10,000 updates, 10,000 slider moves, 2,000 host lifecycles;
- warm-up: 1,000 updates.

| Operation | p50 | p95 | p99 | max |
| --- | ---: | ---: | ---: | ---: |
| Decode/apply native host update | 8 µs | 41 µs | 46 µs | 356 µs |
| Slider move and local redraw | 10 µs | 17 µs | 24 µs | 82 µs |
| Create, update and release host | 84 µs | 111 µs | 139 µs | 390 µs |

The 10,000 slider moves emitted zero per-move bridge callbacks and one final
semantic `CHANGE` event. This is a 10,000:1 reduction versus an implementation
that sends every sampled movement across the language boundary; it is not a
claim that PAM is 10,000 times faster than another framework.

The instrumented benchmark fails when update/gesture p99 exceeds 4 ms or
lifecycle p99 exceeds 8 ms. Run it on a connected device:

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
