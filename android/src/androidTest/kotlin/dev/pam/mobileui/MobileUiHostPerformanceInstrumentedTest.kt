package dev.pam.mobileui

import android.os.Build
import android.graphics.Bitmap
import android.graphics.Canvas
import android.util.Log
import android.view.MotionEvent
import android.view.View
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEventKind
import kotlin.math.roundToLong
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class MobileUiHostPerformanceInstrumentedTest {
    @Test
    fun uiThreadLifecycleAndSliderGestureStayInsideTheFrameBudget() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> }
            host.layout(0, 0, 1_080, 180)
            repeat(WARMUP_ITERATIONS) { iteration ->
                host.update(sliderProperties(iteration))
            }

            val update = measure(SAMPLE_ITERATIONS) { iteration ->
                host.update(sliderProperties(iteration))
            }
            assertTrue(
                "Host update p99 ${update.p99Micros}µs exceeded 4ms",
                update.p99Nanos < FOUR_MILLISECONDS_NANOS,
            )

            val events = ArrayList<NativeViewEventKind>()
            val slider = MobileUiHost(context) { kind, _ -> events += kind }
            slider.update(sliderProperties(0))
            slider.layout(0, 0, 1_080, 180)
            slider.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 0f, 90f))
            val gesture = measure(GESTURE_ITERATIONS) { iteration ->
                val event = motion(
                    MotionEvent.ACTION_MOVE,
                    (iteration % 1_080).toFloat(),
                    90f,
                )
                slider.dispatchTouchEvent(event)
                event.recycle()
            }
            slider.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 1_079f, 90f))
            assertTrue(
                "Slider move p99 ${gesture.p99Micros}µs exceeded 4ms",
                gesture.p99Nanos < FOUR_MILLISECONDS_NANOS,
            )
            assertEquals(
                "Per-frame slider movement must never cross the PAM bridge",
                listOf(NativeViewEventKind.CHANGE),
                events,
            )

            val calendarEvents = ArrayList<NativeViewEventKind>()
            val calendar = MobileUiHost(context) { kind, _ -> calendarEvents += kind }
            calendar.update(calendarProperties())
            calendar.layout(0, 0, 1_080, 1_080)
            calendar.addView(View(context).apply {
                tag = "pam:calendar-grid"
                layout(0, 120, 1_080, 1_080)
            })
            val calendarBitmap = Bitmap.createBitmap(1_080, 1_080, Bitmap.Config.ARGB_8888)
            val calendarCanvas = Canvas(calendarBitmap)
            repeat(CALENDAR_WARMUP_ITERATIONS) {
                calendar.draw(calendarCanvas)
            }
            val calendarDraw = measure(CALENDAR_DRAW_ITERATIONS) {
                calendar.draw(calendarCanvas)
            }
            assertTrue(
                "Calendar draw p99 ${calendarDraw.p99Micros}µs exceeded 4ms",
                calendarDraw.p99Nanos < FOUR_MILLISECONDS_NANOS,
            )
            assertTrue(
                "Calendar frame drawing must never cross the PAM bridge",
                calendarEvents.isEmpty(),
            )

            val lifecycle = measure(LIFECYCLE_ITERATIONS) { iteration ->
                MobileUiHost(context) { _, _ -> }
                    .also {
                        it.update(progressProperties(iteration))
                        it.release()
                    }
            }
            assertTrue(
                "Host lifecycle p99 ${lifecycle.p99Micros}µs exceeded 8ms",
                lifecycle.p99Nanos < EIGHT_MILLISECONDS_NANOS,
            )

            Log.i(
                BENCHMARK_TAG,
                buildString {
                    append('{')
                    append("\"device\":\"${Build.MANUFACTURER} ${Build.MODEL}\",")
                    append("\"android\":${Build.VERSION.SDK_INT},")
                    append("\"build\":\"debug\",")
                    append("\"update\":${update.json()},")
                    append("\"sliderMove\":${gesture.json()},")
                    append("\"calendarDraw\":${calendarDraw.json()},")
                    append("\"lifecycle\":${lifecycle.json()},")
                    append("\"sliderMoves\":$GESTURE_ITERATIONS,")
                    append("\"bridgeEvents\":${events.size},")
                    append("\"calendarBridgeEvents\":${calendarEvents.size}")
                    append('}')
                },
            )

            host.release()
            slider.release()
            calendar.release()
            calendarBitmap.recycle()
        }
    }

    private fun sliderProperties(iteration: Int): Map<String, WireValue> =
        mapOf(
            "behavior" to WireValue.Integer(5),
            "component" to WireValue.Integer(GeneratedComponents.SLIDER.toLong()),
            "value" to WireValue.Decimal((iteration % 101).toDouble()),
            "min" to WireValue.Decimal(0.0),
            "max" to WireValue.Decimal(100.0),
            "step" to WireValue.Decimal(1.0),
        )

    private fun progressProperties(iteration: Int): Map<String, WireValue> =
        mapOf(
            "behavior" to WireValue.Integer(15),
            "component" to WireValue.Integer(GeneratedComponents.PROGRESS.toLong()),
            "value" to WireValue.Decimal((iteration % 101).toDouble()),
        )

    private fun calendarProperties(): Map<String, WireValue> =
        mapOf(
            "behavior" to WireValue.Integer(7),
            "component" to WireValue.Integer(GeneratedComponents.CALENDAR.toLong()),
            "mode" to WireValue.Integer(3),
            "year" to WireValue.Integer(2026),
            "month" to WireValue.Integer(7),
            "fixedWeeks" to WireValue.Flag(true),
            "rangeFrom" to WireValue.Text("2026-07-10"),
            "rangeTo" to WireValue.Text("2026-07-23"),
            "disabledDates" to WireValue.Text("2026-07-04\n2026-07-11"),
        )

    private fun measure(iterations: Int, block: (Int) -> Unit): Statistics {
        val samples = LongArray(iterations)
        repeat(iterations) { iteration ->
            val started = System.nanoTime()
            block(iteration)
            samples[iteration] = System.nanoTime() - started
        }
        samples.sort()
        return Statistics(
            p50Nanos = samples.percentile(0.50),
            p95Nanos = samples.percentile(0.95),
            p99Nanos = samples.percentile(0.99),
            maxNanos = samples.last(),
        )
    }

    private fun LongArray.percentile(percentile: Double): Long =
        this[((size - 1) * percentile).roundToLong().toInt()]

    private fun motion(action: Int, x: Float, y: Float): MotionEvent =
        MotionEvent.obtain(0L, 0L, action, x, y, 0)

    private fun onMain(block: () -> Unit) {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(block)
    }

    private data class Statistics(
        val p50Nanos: Long,
        val p95Nanos: Long,
        val p99Nanos: Long,
        val maxNanos: Long,
    ) {
        val p99Micros: Long
            get() = p99Nanos / NANOS_PER_MICROSECOND

        fun json(): String = buildString {
            append("{\"p50Us\":${p50Nanos / NANOS_PER_MICROSECOND},")
            append("\"p95Us\":${p95Nanos / NANOS_PER_MICROSECOND},")
            append("\"p99Us\":${p99Nanos / NANOS_PER_MICROSECOND},")
            append("\"maxUs\":${maxNanos / NANOS_PER_MICROSECOND}}")
        }
    }

    private companion object {
        const val BENCHMARK_TAG = "PamMobileUiBench"
        const val WARMUP_ITERATIONS = 1_000
        const val SAMPLE_ITERATIONS = 10_000
        const val GESTURE_ITERATIONS = 10_000
        const val CALENDAR_WARMUP_ITERATIONS = 200
        const val CALENDAR_DRAW_ITERATIONS = 2_000
        const val LIFECYCLE_ITERATIONS = 2_000
        const val NANOS_PER_MICROSECOND = 1_000L
        const val FOUR_MILLISECONDS_NANOS = 4_000_000L
        const val EIGHT_MILLISECONDS_NANOS = 8_000_000L
    }
}
