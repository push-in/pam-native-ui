# Performance evidence

PAM Mobile UI keeps interaction progress inside Android and sends PHP only the
semantic result. Benchmarks are split by layer so a fast PHP microbenchmark
cannot be mistaken for smooth rendered frames.

The same boundary applies to UIKit. `p-ripple`, state layers, overlay motion,
intersection, resize and mutation observation execute in native code. Only
requested semantic events cross the bounded binary channel; listeners are
absent when a directive is not authored.

## Android UI-thread microbenchmark

Reference run:

- date: 2026-07-24;
- device: Samsung SM-S918B (Galaxy S23 Ultra);
- Android API: 36;
- build: debug, instrumented;
- samples: 10,000 host updates, 10,000 slider moves, 2,000 calendar
  draws, 10,000 DateTimePicker updates, 10,000 Accordion toggles, 10,000
  Checkbox toggles, 10,000 RadioGroup selections and 2,000 host lifecycles;
- warm-up: 1,000 host updates, 1,000 DateTimePicker updates, 1,000
  Accordion toggles, 1,000 Checkbox toggles, 1,000 RadioGroup selections
  and 200 calendar draws.

| Operation | p50 | p95 | p99 | max |
| --- | ---: | ---: | ---: | ---: |
| Decode/apply native host update | 18 µs | 44 µs | 53 µs | 1,937 µs |
| Slider move and local redraw | 18 µs | 21 µs | 25 µs | 58 µs |
| Calendar grid draw | 1,269 µs | 1,356 µs | 1,403 µs | 1,468 µs |
| DateTimePicker property update | 24 µs | 28 µs | 45 µs | 2,206 µs |
| Accordion semantic toggle | 11 µs | 17 µs | 22 µs | 1,796 µs |
| Checkbox semantic toggle | 3 µs | 4 µs | 4 µs | 44 µs |
| RadioGroup exclusive selection | 9 µs | 13 µs | 17 µs | 103 µs |
| Create, update and release host | 128 µs | 154 µs | 192 µs | 1,946 µs |

The complete rerun on the same device passed on 2026-07-24. Its machine-readable
result is checked in at
[`benchmarks/android-sm-s918b-api36-2026-07-24.json`](../benchmarks/android-sm-s918b-api36-2026-07-24.json).
All 24 measured operation families stayed below their enforced p99 budget;
notable results include tabs at 42 µs, menu selection at 6 µs, sheet snapping
at 504 µs, rich markdown updates at 127 µs, and table layout at 2 µs.

A second run on Android 12/API 31 using a Samsung Galaxy S10 passed all 39
functional instrumentation tests and all performance gates. This older-device
evidence is stored separately in
[`benchmarks/android-sm-g973f-api31-2026-07-24.json`](../benchmarks/android-sm-g973f-api31-2026-07-24.json);
it is not mixed into the Galaxy S23 reference baseline.

The 10,000 slider moves emitted zero per-move bridge callbacks and one final
semantic `CHANGE` event. This is a 10,000:1 reduction versus an implementation
that sends every sampled movement across the language boundary; it is not a
claim that PAM is 10,000 times faster than another framework.

The 2,000 calendar frames drew the complete six-week range state, today
indicator and disabled dates directly into an Android canvas with zero bridge
events. The 10,000 DateTimePicker updates parsed limits, time-zone offsets and
changing ISO values on the native UI thread with zero bridge events. The
10,000 Accordion state changes each emitted exactly one final semantic toggle;
content visibility and animation do not emit frame callbacks. Checkbox and
RadioGroup each emitted exactly 10,000 semantic events while drawing state and,
for radio, unchecking the sibling locally without an extra bridge callback.
The instrumented benchmark fails when update, DateTimePicker update, Accordion
toggle, Checkbox toggle, RadioGroup selection, gesture or calendar draw p99
exceeds 4 ms, or lifecycle p99 exceeds 8 ms. Run it on a connected device:

```bash
../pam-native/android/gradlew \
  -p android \
  connectedDebugAndroidTest \
  -Pandroid.testInstrumentationRunnerArguments.class=dev.pam.mobileui.MobileUiHostPerformanceInstrumentedTest
```

The JSON result is logged under `PamMobileUiBench`.

The current benchmark source additionally gates 10,000 native sheet snap
changes and 10,000 sheet-item activations at p99 below 4 ms, with exactly one
semantic event per operation. Those two rows will be added to the reference
table only after a new physical-device run; static compilation is not reported
as measured runtime evidence.

The same pending physical run now includes 10,000 collision-aware anchored
layouts and 10,000 Menu selections. Positioning must emit zero bridge events;
each Menu activation must emit exactly one semantic event. Both use the same
p99 below 4 ms gate and remain excluded from the reference table until measured
on hardware.

Compound inputs add two more pending hardware gates: 10,000 focus/invalid state
updates with zero bridge events, and 10,000 native slot activations with exactly
one semantic press each. Clear and password visibility mutate the mounted
`EditText` directly; normal typing follows the selected native/debounced/blur/
submit synchronization policy. These rows also remain excluded from the
reference table until a physical run records them.

The pending physical suite also lays out a 20-row by 4-column semantic table
10,000 times. Its cached accessibility coordinates must keep steady-layout p99
below 4 ms and emit zero bridge events. Scalar `FlatList`, `VirtualizedList`
and `SectionList` use PAM core's RecyclerView host, recycled pool and bounded
GapWorker prefetch; an end-to-end scroll and memory profile remains part of the
full-renderer benchmark rather than this plugin-host microbenchmark.

The same suite performs 10,000 paired steady updates of a multi-line Skeleton
and persistent Toast. The single pulse animator and identity-stable timer must
keep p99 below 4 ms with zero bridge events; the result remains excluded from
the reference table until measured on hardware.

ImageViewer adds a pending 10,000-operation native navigation gate. Every
selection must update active-image visibility, counter, control state and
accessibility metadata below 4 ms p99 while emitting exactly one scalar semantic
index. Pinch, pan and the animation between settled states remain bridge-free.
This row is excluded from the reference table until the physical-device suite
records it.

Chat AI adds two pending 10,000-operation gates. MessageBranch navigation must
update page visibility, counter, controls and accessibility below 4 ms p99 with
exactly one index event per activation. PromptInput must recompute enabled
state, submit, trim and clear below 4 ms p99 with exactly one `SUBMIT` event.
Conversation scroll progress and button visibility remain entirely native; its
large-history memory/frame profile belongs to the full-renderer benchmark.

FileTree adds a pending 10,000-toggle gate below 4 ms p99. Each folder
activation performs its selection and expansion updates locally and emits
exactly two semantic results (selected path plus bounded expansion map);
animation frames never cross the bridge.

MessageResponse adds a pending 10,000-update gate below 4 ms p99 for a mixed
heading/emphasis/list/link/code document. Parsing and span application happen
once per changed source on the Android UI thread and emit zero bridge events;
only explicit safe-link activation emits one URI.

The unified vertical/horizontal ScrollView adds a pending native fling and
10,000 property-update gate. Drag, momentum, paging and snap remain inside the
core Android host; registered scroll progress is coalesced to one scalar per
display frame, while an unobserved scroll emits zero bridge events. Static
compilation is not reported as physical runtime evidence.

The image pipeline adds pending cold/warm/cache-hit gates for 1,000 mixed
avatar, card and background requests. The physical-device run must record
deduplicated network count, disk-hit ratio, decoded allocation, cancellation
correctness, main-thread mount time and frame p95/p99. A 4096 px source used in
a 40 dp avatar must decode to the nearest bounded target bucket rather than its
full dimensions. Progress may cross at most once per display frame when
observed; images without lifecycle handlers emit zero bridge events.

Inputs add a pending 10,000-keystroke and 1,000-field focus/selection gate.
Native and blur/submit sync modes must preserve every character and cursor
position with zero per-keystroke PHP callbacks; observed selection may emit at
most once per display frame. The run records IME latency, dropped characters,
main-thread p95/p99 and bridge event counts on physical hardware.

Grid adds a pending 10,000-layout gate using responsive columns, mixed spans,
independent row/column gaps and two wrapped rows. Breakpoint selection,
measurement, RTL mirroring and placement must remain below 4 ms p99 with zero
bridge events; this row stays excluded until recorded on physical hardware.

## PHP composition and binary encoding

The reference local run composed a realistic styled form subtree and encoded
the complete PAM binary tree 2,000 times per sample. The table reports the
median of five measured samples after the benchmark's 100-tree warm-up:

| Metric | Result |
| --- | ---: |
| Median total | 996.910 ms |
| Median throughput | 2,006.2 trees/s |
| Encoded frame | 2,148 bytes |
| Peak PHP memory | 8 MiB |
| PHP | 8.4.23 |

Reference host: Intel Core Ultra 9 185H, 22 logical CPUs, 30 GiB RAM, Linux
7.0.11 x86-64. The five measured totals were 1,096.115 ms, 974.398 ms,
981.180 ms, 1,042.991 ms and 996.910 ms.

Reproduce it with `composer benchmark`.

## Claim boundary

These measurements validate bounded host work and the absence of per-frame PHP
traffic on one high-end physical device. They do not yet establish an end-to-end
speedup over React Native Nitro Modules. Cold start, mount/update through the
full PAM renderer, frame p50/p95/p99, jank, memory and matched-device competitor
baselines remain required before the performance gate can move from `planned`
to `verified`.
