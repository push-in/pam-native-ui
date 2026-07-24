package dev.pam.mobileui

import android.os.Looper
import android.view.MotionEvent
import android.view.View
import android.widget.FrameLayout
import android.view.accessibility.AccessibilityNodeInfo
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.views.NativeViewEventKind
import java.util.concurrent.CopyOnWriteArrayList
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
@Suppress("DEPRECATION")
class MobileUiHostInstrumentedTest {
    @Test
    fun sliderKeepsTransientStateOnTheUiThreadAndExposesSeekBarSemantics() {
        onMain {
            assertEquals(Looper.getMainLooper(), Looper.myLooper())
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, _ ->
                events += kind
            }

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(5),
                    "component" to WireValue.Integer(GeneratedComponents.SLIDER.toLong()),
                    "value" to WireValue.Decimal(40.0),
                    "min" to WireValue.Decimal(0.0),
                    "max" to WireValue.Decimal(100.0),
                ),
            )

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)

            assertEquals("android.widget.SeekBar", info.className)
            assertTrue(host.minimumWidth >= dp(host, 48f))
            assertTrue(host.minimumHeight >= dp(host, 48f))
            host.release()
            info.recycle()
        }
    }

    @Test
    fun checkboxPublishesACompactToggleEventAndNativeCheckedState() {
        onMain {
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                events += kind
                payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(10),
                    "checked" to WireValue.Flag(false),
                ),
            )

            assertTrue(host.performClick())

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals(listOf(NativeViewEventKind.TOGGLE), events)
            assertTrue(info.isChecked)
            assertEquals("1", payloads.single().decodeToString())
            host.release()
            info.recycle()
        }
    }

    @Test
    fun releaseIsIdempotentAndDetachesInteractiveListeners() {
        onMain {
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            host.update(mapOf("behavior" to WireValue.Integer(8)))
            host.release()
            host.release()

            assertEquals(View.IMPORTANT_FOR_ACCESSIBILITY_YES, host.importantForAccessibility)
        }
    }

    @Test
    fun sliderAccessibilityAdjustmentSnapsLocallyAndEmitsOneSemanticValue() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(5),
                    "value" to WireValue.Decimal(40.0),
                    "min" to WireValue.Decimal(0.0),
                    "max" to WireValue.Decimal(100.0),
                    "step" to WireValue.Decimal(5.0),
                ),
            )

            assertTrue(
                host.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                    null,
                ),
            )
            assertEquals("45.0", payloads.single().decodeToString())
            host.release()
        }
    }

    @Test
    fun tabsPublishTheAuthoredSemanticValueInsteadOfAVisualIndex() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(mapOf("behavior" to WireValue.Integer(6)))
            val list = FrameLayout(host.context)
            repeat(3) { index ->
                list.addView(View(host.context).apply {
                    tag = listOf("account", "security", "billing")[index]
                })
            }
            host.addView(list)
            host.layout(0, 0, 300, 160)
            list.layout(0, 0, 300, 80)
            repeat(3) { index ->
                list.getChildAt(index).layout(index * 100, 0, (index + 1) * 100, 80)
            }

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 150f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 150f, 40f))

            assertEquals("security", payloads.single().decodeToString())
            host.release()
        }
    }

    @Test
    fun nonDismissibleSheetNeverAnimatesAwayOrEmitsDismissal() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "dismissible" to WireValue.Flag(false),
                ),
            )
            host.layout(0, 0, 300, 300)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 100f, 0f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, 100f, 200f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 100f, 200f))

            assertTrue(payloads.isEmpty())
            host.release()
        }
    }

    @Test
    fun modalExposesDismissActionAndPublishesCompactNativeDismissal() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(mapOf("behavior" to WireValue.Integer(17)))

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.app.Dialog", info.className)
            assertTrue(
                host.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_DISMISS,
                    null,
                ),
            )
            val payload = WireMap.decode(payloads.single())
            assertEquals(1L, (payload["action"] as WireValue.Integer).value)
            assertTrue((payload["dismissed"] as WireValue.Flag).value)
            host.release()
            info.recycle()
        }
    }

    @Test
    fun accordionKeepsItsHeaderVisibleWhileCollapsingContentLocally() {
        onMain {
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(2),
                    "expanded" to WireValue.Flag(false),
                ),
            )
            val header = View(host.context)
            val content = View(host.context)
            host.addView(header)
            host.addView(content)

            assertEquals(1f, header.alpha, 0f)
            assertEquals(0f, content.alpha, 0f)
            assertEquals(0.98f, content.scaleY, 0f)
            host.release()
        }
    }

    @Test
    fun radioSelectionCannotToggleItselfOffBeforeControlledStateReturns() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(11),
                    "checked" to WireValue.Flag(false),
                ),
            )

            assertTrue(host.performClick())
            assertTrue(host.performClick())
            assertEquals(2, payloads.size)
            payloads.forEach { payload ->
                assertEquals("1", payload.decodeToString())
            }
            host.release()
        }
    }

    private fun dp(view: View, value: Float): Int =
        (value * view.resources.displayMetrics.density + 0.5f).toInt()

    private fun motion(action: Int, x: Float, y: Float): MotionEvent =
        MotionEvent.obtain(0L, 0L, action, x, y, 0)

    private fun onMain(block: () -> Unit) {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(block)
    }
}
