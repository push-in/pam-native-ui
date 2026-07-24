package dev.pam.mobileui

import android.os.Looper
import android.view.View
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
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, _ ->
                events += kind
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
            val value = WireMap.decode(payloads.single())["value"] as WireValue.Decimal
            assertEquals(45.0, value.value, 0.0)
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
                assertTrue((WireMap.decode(payload)["checked"] as WireValue.Flag).value)
            }
            host.release()
        }
    }

    private fun dp(view: View, value: Float): Int =
        (value * view.resources.displayMetrics.density + 0.5f).toInt()

    private fun onMain(block: () -> Unit) {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(block)
    }
}
