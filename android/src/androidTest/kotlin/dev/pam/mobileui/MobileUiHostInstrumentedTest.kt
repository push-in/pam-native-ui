package dev.pam.mobileui

import android.content.Intent
import android.os.Looper
import android.view.KeyEvent
import android.view.MotionEvent
import android.view.View
import android.widget.FrameLayout
import android.widget.TextView
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
    fun checkboxHandlesReadOnlyIndeterminateAndAuthoredIconStateNatively() {
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
                    "defaultIsChecked" to WireValue.Flag(false),
                    "isReadOnly" to WireValue.Flag(true),
                ),
            )
            val indicator = FrameLayout(host.context).apply {
                tag = "pam:selection-indicator"
            }
            val icon = View(host.context).apply {
                tag = "pam:selection-icon"
            }
            indicator.addView(icon)
            val label = TextView(host.context).apply {
                text = "Receive updates"
            }
            host.addView(indicator)
            host.addView(label)
            host.layout(0, 0, 400, 100)
            indicator.layout(0, 25, 50, 75)
            icon.layout(0, 0, 50, 50)
            label.layout(60, 0, 400, 100)

            assertTrue(host.performClick())
            assertTrue(events.isEmpty())
            assertEquals(View.GONE, icon.visibility)

            val readOnlyInfo = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(readOnlyInfo)
            assertTrue(!readOnlyInfo.isClickable)
            assertTrue(
                readOnlyInfo.actionList.none {
                    it.id == AccessibilityNodeInfo.ACTION_CLICK
                },
            )
            assertEquals("Read only", host.stateDescription)
            readOnlyInfo.recycle()

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(10),
                    "isIndeterminate" to WireValue.Flag(true),
                ),
            )
            assertEquals(View.VISIBLE, icon.visibility)

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.CheckBox", info.className)
            assertEquals("Receive updates", host.contentDescription)
            assertEquals("Mixed", host.stateDescription)
            assertTrue(info.isCheckable)
            assertTrue(!info.isChecked)

            assertTrue(host.performClick())
            assertEquals(listOf(NativeViewEventKind.TOGGLE), events)
            host.onInitializeAccessibilityNodeInfo(info)
            assertTrue(info.isChecked)
            assertTrue(host.stateDescription == null)
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
    fun tabsUseActualTriggerGeometryAndDoNotClaimVisualGaps() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(mapOf("behavior" to WireValue.Integer(6)))
            val list = FrameLayout(host.context)
            list.addView(View(host.context).apply { tag = "short" })
            list.addView(View(host.context).apply { tag = "wide" })
            host.addView(list)
            host.layout(0, 0, 400, 160)
            list.layout(20, 20, 380, 100)
            list.getChildAt(0).layout(0, 0, 80, 80)
            list.getChildAt(1).layout(140, 0, 360, 80)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 40f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 40f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 120f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 120f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 300f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 300f, 40f))

            assertEquals(listOf("short", "wide"), payloads.map(ByteArray::decodeToString))
            host.release()
        }
    }

    @Test
    fun verticalTabsSelectByActualTriggerBounds() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(6),
                    "orientation" to WireValue.Integer(2),
                ),
            )
            val list = FrameLayout(host.context)
            list.addView(View(host.context).apply { tag = "overview" })
            list.addView(View(host.context).apply { tag = "settings" })
            host.addView(list)
            host.layout(0, 0, 240, 400)
            list.layout(20, 20, 220, 380)
            list.getChildAt(0).layout(0, 0, 200, 100)
            list.getChildAt(1).layout(0, 140, 200, 360)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 100f, 240f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 100f, 240f))

            assertEquals("settings", payloads.single().decodeToString())
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
    fun closedCompoundSheetLeavesItsTriggerInteractive() {
        onMain {
            var triggerPresses = 0
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, payload ->
                payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "open" to WireValue.Flag(false),
                ),
            )
            val trigger = View(host.context).apply {
                setOnClickListener { triggerPresses++ }
            }
            val content = FrameLayout(host.context)
            host.addView(trigger)
            host.addView(content)
            host.layout(0, 0, 300, 1_000)
            trigger.layout(0, 0, 300, 100)
            content.layout(0, 600, 300, 1_000)

            trigger.performClick()

            assertTrue(!host.acceptsOverlayInteraction())
            assertEquals(1, triggerPresses)
            assertTrue(payloads.isEmpty())
            host.release()
        }
    }

    @Test
    fun sheetContentOutsideTheHandleIsNotClaimedByTheDragGesture() {
        onMain {
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            host.update(mapOf("behavior" to WireValue.Integer(3)))
            val backdrop = View(host.context)
            val content = FrameLayout(host.context)
            host.addView(backdrop)
            host.addView(content)
            host.layout(0, 0, 300, 1_000)
            backdrop.layout(0, 0, 300, 1_000)
            content.layout(0, 600, 300, 1_000)

            assertTrue(host.isSheetHandle(150f, 650f))
            assertTrue(!host.isSheetHandle(150f, 850f))
            host.animate().cancel()
            host.translationY = 0f
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, 150f, 900f))
            assertEquals(0f, host.translationY, 0f)
            host.release()
        }
    }

    @Test
    fun overlayHitTestingIncludesNestedTranslationWithoutDismissing() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(mapOf("behavior" to WireValue.Integer(17)))
            val wrapper = FrameLayout(host.context)
            val content = View(host.context)
            wrapper.addView(content)
            host.addView(wrapper)
            host.layout(0, 0, 400, 600)
            wrapper.layout(0, 0, 400, 600)
            content.layout(20, 40, 220, 240)
            wrapper.translationX = 80f
            wrapper.translationY = 120f

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 150f, 200f))

            assertTrue(payloads.isEmpty())
            host.release()
        }
    }

    @Test
    fun calendarSelectionClaimsOnlyTheTaggedGridGeometry() {
        onMain {
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            host.update(mapOf("behavior" to WireValue.Integer(7)))
            val header = View(host.context)
            val grid = View(host.context).apply {
                tag = "pam:calendar-grid"
            }
            host.addView(header)
            host.addView(grid)
            host.layout(0, 0, 300, 600)
            header.layout(0, 0, 300, 100)
            grid.layout(0, 100, 300, 600)

            assertTrue(!host.isCalendarGridPoint(150f, 50f))
            assertTrue(host.isCalendarGridPoint(150f, 150f))
            host.release()
        }
    }

    @Test
    fun calendarMultipleSelectionEmitsOneBoundedSemanticPayload() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(7),
                    "mode" to WireValue.Integer(2),
                    "year" to WireValue.Integer(2026),
                    "month" to WireValue.Integer(7),
                    "fixedWeeks" to WireValue.Flag(true),
                    "selectedValues" to WireValue.Text("2026-07-23"),
                ),
            )
            val grid = View(host.context).apply {
                tag = "pam:calendar-grid"
            }
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            grid.layout(0, 100, 700, 700)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 550f, 450f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 550f, 450f))

            assertEquals(
                "M\n2026-07-23\n2026-07-24",
                payloads.single().decodeToString(),
            )
            host.release()
        }
    }

    @Test
    fun calendarExposesEveryVisibleDayAsATalkBackVirtualButton() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(7),
                    "year" to WireValue.Integer(2026),
                    "month" to WireValue.Integer(7),
                    "fixedWeeks" to WireValue.Flag(true),
                    "disabledDates" to WireValue.Text("2026-07-24"),
                    "locale" to WireValue.Text("en-US"),
                ),
            )
            val grid = View(host.context).apply {
                tag = "pam:calendar-grid"
            }
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            grid.layout(0, 100, 700, 700)

            val provider = host.accessibilityNodeProvider
            val july23 = provider?.createAccessibilityNodeInfo(25)
            val july24 = provider?.createAccessibilityNodeInfo(26)
            assertEquals("android.widget.Button", july23?.className)
            assertEquals("23", july23?.text)
            assertEquals("Thursday, July 23, 2026", july23?.contentDescription)
            assertTrue(july23?.isEnabled == true)
            assertTrue(july24?.isEnabled == false)
            assertTrue(
                provider?.performAction(
                    25,
                    AccessibilityNodeInfo.ACTION_CLICK,
                    null,
                ) == true,
            )
            assertEquals("2026-07-23", payloads.single().decodeToString())
            july23?.recycle()
            july24?.recycle()
            host.release()
        }
    }

    @Test
    fun calendarRangeSelectionAndDisabledDatesStayInsideTheNativeHost() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(7),
                    "mode" to WireValue.Integer(3),
                    "year" to WireValue.Integer(2026),
                    "month" to WireValue.Integer(7),
                    "fixedWeeks" to WireValue.Flag(true),
                    "disabledDates" to WireValue.Text("2026-07-23"),
                ),
            )
            val grid = View(host.context).apply {
                tag = "pam:calendar-grid"
            }
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            grid.layout(0, 100, 700, 700)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 550f, 250f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 550f, 250f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 450f, 350f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 450f, 350f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 450f, 450f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 450f, 450f))

            assertEquals(
                listOf(
                    "R\n2026-07-10\n",
                    "R\n2026-07-10\n2026-07-16",
                ),
                payloads.map(ByteArray::decodeToString),
            )
            host.release()
        }
    }

    @Test
    fun calendarNavigationUpdatesTheTitleAndEmitsOnlyTheSemanticMonth() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(7),
                    "year" to WireValue.Integer(2026),
                    "month" to WireValue.Integer(7),
                    "locale" to WireValue.Text("en-US"),
                ),
            )
            val previous = View(host.context).apply {
                tag = "pam:calendar-prev"
            }
            val title = TextView(host.context).apply {
                tag = "pam:calendar-title"
                text = "Placeholder"
            }
            val next = View(host.context).apply {
                tag = "pam:calendar-next"
            }
            val grid = View(host.context).apply {
                tag = "pam:calendar-grid"
            }
            host.addView(previous)
            host.addView(title)
            host.addView(next)
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            previous.layout(0, 0, 100, 100)
            title.layout(100, 0, 600, 100)
            next.layout(600, 0, 700, 100)
            grid.layout(0, 100, 700, 700)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 50f, 50f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 50f, 50f))

            val navigation = WireMap.decode(payloads.single())
            assertEquals("June 2026", title.text.toString())
            assertEquals(5L, (navigation["action"] as WireValue.Integer).value)
            assertEquals(2026L, (navigation["year"] as WireValue.Integer).value)
            assertEquals(6L, (navigation["month"] as WireValue.Integer).value)
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
    fun dateTimePickerUsesNativeModeSemanticsAndInterceptsItsAuthoredTrigger() {
        onMain {
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(22),
                    "mode" to WireValue.Integer(5),
                    "value" to WireValue.Text("14:35"),
                    "timeZoneOffsetInMinutes" to WireValue.Integer(-180),
                    "is24Hour" to WireValue.Flag(true),
                ),
            )
            host.addView(View(host.context).apply {
                isClickable = true
            })
            host.layout(0, 0, 400, 100)
            host.getChildAt(0).layout(0, 0, 400, 100)

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.TimePicker", info.className)
            assertTrue(info.isClickable)
            assertTrue(
                info.actionList.any {
                    it.id == AccessibilityNodeInfo.ACTION_CLICK
                },
            )
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 200f, 50f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 200f, 50f)))
            host.release()
            info.recycle()
        }
    }

    @Test
    fun dateTimePickerOpensTheSystemDialogFromAWrappedActivityContext() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = instrumentation.startActivitySync(
            Intent(
                instrumentation.targetContext,
                TestHostActivity::class.java,
            ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
        ) as TestHostActivity
        instrumentation.waitForIdleSync()
        val payloads = CopyOnWriteArrayList<ByteArray>()
        lateinit var host: MobileUiHost
        onMain {
            host = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(22),
                    "mode" to WireValue.Integer(4),
                    "value" to WireValue.Text("2026-07-23"),
                    "minimumDate" to WireValue.Text("2026-07-01"),
                    "maximumDate" to WireValue.Text("2026-07-31"),
                ),
            )
            activity.setContentView(host)
            assertTrue(host.performClick())
        }

        instrumentation.waitForIdleSync()
        instrumentation.sendKeyDownUpSync(KeyEvent.KEYCODE_BACK)
        instrumentation.waitForIdleSync()

        val dismissal = WireMap.decode(payloads.single())
        assertEquals(1L, (dismissal["action"] as WireValue.Integer).value)
        assertTrue((dismissal["dismissed"] as WireValue.Flag).value)
        onMain {
            host.release()
            activity.finish()
        }
    }

    @Test
    fun accordionOwnsHeaderTouchesAndRemovesCollapsedContentFromTalkBack() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(2),
                    "expanded" to WireValue.Flag(false),
                ),
            )
            val header = FrameLayout(host.context)
            val trigger = FrameLayout(host.context).apply {
                tag = "pam:accordion-trigger"
            }
            val title = TextView(host.context).apply {
                text = "Performance"
            }
            val icon = View(host.context).apply {
                tag = "pam:accordion-icon"
            }
            trigger.addView(title)
            trigger.addView(icon)
            header.addView(trigger)
            val content = FrameLayout(host.context).apply {
                tag = "pam:accordion-content"
            }
            host.addView(header)
            host.addView(content)
            host.layout(0, 0, 400, 300)
            header.layout(0, 0, 400, 100)
            trigger.layout(0, 0, 400, 100)
            title.layout(0, 0, 300, 100)
            icon.layout(300, 0, 400, 100)
            content.layout(0, 100, 400, 300)

            assertEquals(1f, header.alpha, 0f)
            assertEquals(View.GONE, content.visibility)
            assertEquals(0f, content.alpha, 0f)
            assertEquals(0.98f, content.scaleY, 0f)
            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS,
                content.importantForAccessibility,
            )
            assertEquals(0f, icon.rotation, 0f)
            assertEquals("Performance", host.contentDescription)

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.Button", info.className)
            assertTrue(
                info.actionList.any {
                    it.id == AccessibilityNodeInfo.ACTION_EXPAND
                },
            )
            assertTrue(
                host.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_EXPAND,
                    null,
                ),
            )
            assertEquals(View.VISIBLE, content.visibility)
            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_AUTO,
                content.importantForAccessibility,
            )
            assertEquals("Expanded", host.stateDescription)
            assertEquals(listOf("1"), payloads.map(ByteArray::decodeToString))

            assertTrue(!host.onTouchEvent(motion(MotionEvent.ACTION_DOWN, 200f, 200f)))
            assertEquals(1, payloads.size)
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 200f, 50f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 200f, 50f)))
            assertEquals(listOf("1", "0"), payloads.map(ByteArray::decodeToString))

            host.release()
            info.recycle()
        }
    }

    @Test
    fun accordionGroupCoordinatesSingleNonCollapsibleItemsWithoutPhpRoundTrips() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val emitter = { kind: NativeViewEventKind, payload: ByteArray ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            val group = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                emitter(kind, payload)
            }
            group.update(
                mapOf(
                    "behavior" to WireValue.Integer(24),
                    "type" to WireValue.Integer(1),
                    "isCollapsible" to WireValue.Flag(false),
                ),
            )
            val wrapper = FrameLayout(group.context)
            val first = MobileUiHost(group.context) { kind, payload ->
                emitter(kind, payload)
            }
            first.update(
                mapOf(
                    "behavior" to WireValue.Integer(2),
                    "expanded" to WireValue.Flag(true),
                    "isCollapsible" to WireValue.Flag(false),
                ),
            )
            val second = MobileUiHost(group.context) { kind, payload ->
                emitter(kind, payload)
            }
            second.update(
                mapOf(
                    "behavior" to WireValue.Integer(2),
                    "expanded" to WireValue.Flag(false),
                    "isCollapsible" to WireValue.Flag(false),
                ),
            )
            wrapper.addView(first)
            wrapper.addView(second)
            group.addView(wrapper)

            assertTrue(
                first.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_COLLAPSE,
                    null,
                ),
            )
            assertTrue(payloads.isEmpty())
            assertEquals("Expanded", first.stateDescription)

            assertTrue(
                second.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_EXPAND,
                    null,
                ),
            )
            assertEquals(listOf("1"), payloads.map(ByteArray::decodeToString))
            assertEquals("Collapsed", first.stateDescription)
            assertEquals("Expanded", second.stateDescription)

            first.release()
            second.release()
            group.release()
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
            assertEquals(listOf("1"), payloads.map(ByteArray::decodeToString))
            host.release()
        }
    }

    @Test
    fun radioGroupSelectsExactlyOneNestedItemOnTheUiThread() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val group = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            group.update(mapOf("behavior" to WireValue.Integer(26)))
            val wrapper = FrameLayout(group.context)
            val first = MobileUiHost(group.context) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            first.update(
                mapOf(
                    "behavior" to WireValue.Integer(11),
                    "checked" to WireValue.Flag(true),
                ),
            )
            val second = MobileUiHost(group.context) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            second.update(
                mapOf(
                    "behavior" to WireValue.Integer(11),
                    "checked" to WireValue.Flag(false),
                ),
            )
            wrapper.addView(first)
            wrapper.addView(second)
            group.addView(wrapper)

            assertTrue(second.performClick())
            assertTrue(second.performClick())
            assertEquals(listOf("1"), payloads.map(ByteArray::decodeToString))

            val firstInfo = AccessibilityNodeInfo.obtain()
            val secondInfo = AccessibilityNodeInfo.obtain()
            val groupInfo = AccessibilityNodeInfo.obtain()
            first.onInitializeAccessibilityNodeInfo(firstInfo)
            second.onInitializeAccessibilityNodeInfo(secondInfo)
            group.onInitializeAccessibilityNodeInfo(groupInfo)
            assertTrue(!firstInfo.isChecked)
            assertTrue(secondInfo.isChecked)
            assertEquals("android.widget.RadioGroup", groupInfo.className)
            assertEquals(2, groupInfo.collectionInfo?.rowCount)
            assertEquals(0, firstInfo.collectionItemInfo?.rowIndex)
            assertEquals(1, secondInfo.collectionItemInfo?.rowIndex)
            assertTrue(firstInfo.collectionItemInfo?.isSelected == false)
            assertTrue(secondInfo.collectionItemInfo?.isSelected == true)

            first.release()
            second.release()
            group.release()
            firstInfo.recycle()
            secondInfo.recycle()
            groupInfo.recycle()
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
