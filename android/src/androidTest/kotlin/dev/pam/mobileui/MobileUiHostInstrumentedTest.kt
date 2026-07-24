package dev.pam.mobileui

import android.content.Intent
import android.os.Looper
import android.text.Spanned
import android.text.method.PasswordTransformationMethod
import android.text.style.ClickableSpan
import android.text.style.StyleSpan
import android.view.KeyEvent
import android.view.MotionEvent
import android.view.View
import android.view.Gravity
import android.view.ViewGroup
import android.widget.FrameLayout
import android.widget.EditText
import android.widget.HorizontalScrollView
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
    fun gridMeasuresSpansAndExposesCollectionSemantics() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val factory = MobileUiGridFactory(context)
            val grid = factory.create(context) { _, _ -> Unit } as MobileUiGridView
            factory.update(
                grid,
                mapOf(
                    "columns" to WireValue.Text("4,4,4,4,4,4"),
                    "columnGaps" to WireValue.Text("8,8,8,8,8,8"),
                    "rowGaps" to WireValue.Text("12,12,12,12,12,12"),
                    "direction" to WireValue.Integer(2),
                ),
            )
            listOf(1, 2, 1, 3, 1).forEachIndexed { index, span ->
                grid.addView(
                    TextView(context).apply {
                        text = "Item $index"
                        tag = "pam:grid-item:$span"
                    },
                    ViewGroup.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT,
                        when (index) {
                            1 -> dp(grid, 56f)
                            3 -> dp(grid, 64f)
                            else -> dp(grid, 48f)
                        },
                    ),
                )
            }
            grid.measure(
                View.MeasureSpec.makeMeasureSpec(
                    dp(grid, 400f),
                    View.MeasureSpec.EXACTLY,
                ),
                View.MeasureSpec.makeMeasureSpec(
                    dp(grid, 132f),
                    View.MeasureSpec.EXACTLY,
                ),
            )
            grid.layout(0, 0, dp(grid, 400f), dp(grid, 132f))

            assertEquals(0, grid.getChildAt(0).left)
            assertEquals(dp(grid, 102f), grid.getChildAt(1).left)
            assertEquals(dp(grid, 306f), grid.getChildAt(2).left)
            assertEquals(0, grid.getChildAt(3).left)
            assertEquals(dp(grid, 306f), grid.getChildAt(4).left)
            assertEquals(dp(grid, 68f), grid.getChildAt(3).top)
            val info = AccessibilityNodeInfo.obtain()
            grid.onInitializeAccessibilityNodeInfo(info)
            assertEquals(2, info.collectionInfo.rowCount)
            assertEquals(4, info.collectionInfo.columnCount)
            info.recycle()
        }
    }

    @Test
    fun horizontalScrollAppliesNativeInteractionAndViewportProperties() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val factory = MobileUiHorizontalScrollFactory(context)
            val view = factory.create(context) { _, _ -> Unit }
            factory.update(
                view,
                mapOf(
                    "scrollEnabled" to WireValue.Flag(false),
                    "showsIndicator" to WireValue.Flag(true),
                    "fillViewport" to WireValue.Flag(false),
                    "nestedScrollEnabled" to WireValue.Flag(false),
                    "contentOffset" to WireValue.Decimal(24.0),
                    "overScrollMode" to WireValue.Text("never"),
                ),
            )

            val scroll = view as HorizontalScrollView
            scroll.addView(
                FrameLayout(context),
                ViewGroup.LayoutParams(800, 80),
            )
            scroll.measure(
                View.MeasureSpec.makeMeasureSpec(320, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(80, View.MeasureSpec.EXACTLY),
            )
            scroll.layout(0, 0, 320, 80)

            assertTrue(!scroll.isEnabled)
            assertTrue(scroll.isHorizontalScrollBarEnabled)
            assertTrue(!scroll.isFillViewport)
            assertTrue(!scroll.isNestedScrollingEnabled)
            assertEquals(View.OVER_SCROLL_NEVER, scroll.overScrollMode)
            assertEquals(dp(scroll, 24f), scroll.scrollX)
            factory.release(scroll)
        }
    }

    @Test
    fun markdownRendersIntrinsicSpansAndEmitsSafeLinksOnTheUiThread() {
        onMain {
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val factory = MobileUiMarkdownFactory(context)
            val view = factory.create(context) { kind, payload ->
                events += kind
                payloads += payload
            }
            factory.update(
                view,
                mapOf(
                    "source" to WireValue.Text(
                        "# PAM\n**Fast** [docs](https://pam.dev)",
                    ),
                    "foregroundColor" to WireValue.Integer(0xff171717),
                    "linkColor" to WireValue.Integer(0xff2563eb),
                    "selectable" to WireValue.Flag(true),
                ),
            )

            val textView = view as TextView
            val styled = textView.text as Spanned
            assertEquals("PAM\nFast docs", styled.toString())
            assertTrue(
                styled.getSpans(0, styled.length, StyleSpan::class.java)
                    .isNotEmpty(),
            )
            val link = styled.getSpans(
                0,
                styled.length,
                ClickableSpan::class.java,
            ).single()
            link.onClick(textView)
            assertEquals(listOf(NativeViewEventKind.NATIVE), events)
            assertEquals("https://pam.dev", payloads.single().decodeToString())
            assertTrue(textView.isTextSelectable)
            factory.release(view)
        }
    }

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

            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS,
                host.importantForAccessibility,
            )
        }
    }

    @Test
    fun sliderAccessibilityAdjustmentSnapsLocallyAndEmitsOneSemanticValue() {
        onMain {
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                events += kind
                payloads += payload
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
            assertEquals(
                listOf(
                    NativeViewEventKind.CHANGE,
                    NativeViewEventKind.NATIVE,
                ),
                events,
            )
            assertEquals(listOf("45", "45"), payloads.map(ByteArray::decodeToString))
            host.release()
        }
    }

    @Test
    fun sliderAndProgressMoveAuthoredAnatomyWithoutPhpFrames() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val slider = MobileUiHost(context) { _, _ -> }
            slider.update(
                mapOf(
                    "behavior" to WireValue.Integer(5),
                    "value" to WireValue.Decimal(25.0),
                    "minValue" to WireValue.Decimal(0.0),
                    "maxValue" to WireValue.Decimal(100.0),
                    "isReversed" to WireValue.Flag(true),
                    "trackThickness" to WireValue.Decimal(6.0),
                    "thumbSize" to WireValue.Decimal(16.0),
                ),
            )
            val trackHeight = dp(slider, 6f)
            val thumbSize = dp(slider, 16f)
            val track = FrameLayout(context).apply {
                tag = "pam:slider-track"
                layoutParams = FrameLayout.LayoutParams(400, trackHeight).apply {
                    topMargin = 50 - trackHeight / 2
                }
            }
            val filled = View(context).apply {
                tag = "pam:slider-filled-track"
                layoutParams = FrameLayout.LayoutParams(400, trackHeight)
            }
            track.addView(filled)
            val thumb = View(context).apply {
                tag = "pam:slider-thumb"
                layoutParams = FrameLayout.LayoutParams(thumbSize, thumbSize).apply {
                    topMargin = 50 - thumbSize / 2
                }
            }
            slider.addView(track)
            slider.addView(thumb)
            slider.measure(
                View.MeasureSpec.makeMeasureSpec(400, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(100, View.MeasureSpec.EXACTLY),
            )
            slider.layout(0, 0, 400, 100)

            assertEquals(0.25f, filled.scaleX, 0.001f)
            assertEquals(400f, filled.pivotX, 0.001f)
            assertTrue(thumb.translationX > 250f)

            slider.update(
                mapOf(
                    "behavior" to WireValue.Integer(5),
                    "value" to WireValue.Decimal(75.0),
                    "min" to WireValue.Decimal(0.0),
                    "max" to WireValue.Decimal(100.0),
                    "isReversed" to WireValue.Flag(true),
                ),
            )
            assertEquals(0.75f, filled.scaleX, 0.001f)
            assertTrue(thumb.translationX < 150f)

            val progress = MobileUiHost(context) { _, _ -> }
            progress.update(
                mapOf(
                    "behavior" to WireValue.Integer(15),
                    "value" to WireValue.Decimal(25.0),
                    "min" to WireValue.Decimal(0.0),
                    "max" to WireValue.Decimal(100.0),
                    "orientation" to WireValue.Integer(2),
                ),
            )
            val progressFill = View(context).apply {
                tag = "pam:progress-filled-track"
                layoutParams = FrameLayout.LayoutParams(24, 400)
            }
            progress.addView(progressFill)
            progress.measure(
                View.MeasureSpec.makeMeasureSpec(24, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(400, View.MeasureSpec.EXACTLY),
            )
            progress.layout(0, 0, 24, 400)

            val progressInfo = AccessibilityNodeInfo.obtain()
            progress.onInitializeAccessibilityNodeInfo(progressInfo)
            assertEquals(0.25f, progressFill.scaleY, 0.001f)
            assertEquals(400f, progressFill.pivotY, 0.001f)
            assertEquals("android.widget.ProgressBar", progressInfo.className)
            assertEquals("25%", progress.stateDescription)

            slider.release()
            progress.release()
            progressInfo.recycle()
        }
    }

    @Test
    fun switchUsesNativeUiThreadStateColorsKeyboardAndAccessibility() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(27),
                    "defaultValue" to WireValue.Flag(true),
                    "trackOffColor" to WireValue.Integer(0xffd4d4d4),
                    "trackOnColor" to WireValue.Integer(0xff525252),
                    "thumbColor" to WireValue.Integer(0xfffafafa),
                ),
            )
            host.layout(0, 0, dp(host, 52f), dp(host, 48f))

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.Switch", info.className)
            assertTrue(info.isCheckable)
            assertTrue(info.isChecked)
            assertEquals("On", host.stateDescription)

            assertTrue(host.performClick())
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals(listOf("0"), payloads.map(ByteArray::decodeToString))
            assertTrue(!info.isChecked)
            assertEquals("Off", host.stateDescription)

            assertTrue(
                host.dispatchKeyEvent(
                    KeyEvent(KeyEvent.ACTION_UP, KeyEvent.KEYCODE_SPACE),
                ),
            )
            assertEquals(listOf("0", "1"), payloads.map(ByteArray::decodeToString))

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(27),
                    "checked" to WireValue.Flag(true),
                    "isDisabled" to WireValue.Flag(true),
                    "isInvalid" to WireValue.Flag(true),
                    "errorMessage" to WireValue.Text("Required setting"),
                ),
            )
            host.onInitializeAccessibilityNodeInfo(info)
            assertTrue(!info.isEnabled)
            assertTrue(info.isContentInvalid)
            assertEquals("Required setting", info.error)
            assertTrue(
                info.actionList.none {
                    it.id == AccessibilityNodeInfo.ACTION_CLICK
                },
            )
            assertTrue(host.performClick())
            assertEquals(2, payloads.size)

            host.release()
            info.recycle()
        }
    }

    @Test
    fun tabsPublishTheAuthoredSemanticValueInsteadOfAVisualIndex() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        val payloads = CopyOnWriteArrayList<ByteArray>()
        lateinit var host: MobileUiHost
        lateinit var list: FrameLayout
        onMain {
            host = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(6),
                    "defaultValue" to WireValue.Text("account"),
                ),
            )
            list = FrameLayout(host.context)
            listOf("account", "security", "billing").forEach { value ->
                list.addView(tabTrigger(host, value))
            }
            host.addView(list)
            activity.setContentView(host)
        }
        instrumentation.waitForIdleSync()
        onMain {
            host.layout(0, 0, 300, 160)
            list.layout(0, 0, 300, 80)
            repeat(3) { index ->
                list.getChildAt(index).layout(index * 100, 0, (index + 1) * 100, 80)
            }

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 150f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 150f, 40f))
        }
        instrumentation.waitForIdleSync()
        onMain {
            assertEquals("security", payloads.single().decodeToString())
            host.release()
            activity.finish()
        }
    }

    @Test
    fun tabsUseActualTriggerGeometryAndDoNotClaimVisualGaps() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        val payloads = CopyOnWriteArrayList<ByteArray>()
        lateinit var host: MobileUiHost
        lateinit var list: FrameLayout
        onMain {
            host = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(mapOf("behavior" to WireValue.Integer(6)))
            list = FrameLayout(host.context)
            list.addView(tabTrigger(host, "short"))
            list.addView(tabTrigger(host, "wide"))
            host.addView(list)
            activity.setContentView(host)
        }
        instrumentation.waitForIdleSync()
        onMain {
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
        }
        instrumentation.waitForIdleSync()
        onMain {
            assertEquals(listOf("short", "wide"), payloads.map(ByteArray::decodeToString))
            host.release()
            activity.finish()
        }
    }

    @Test
    fun verticalTabsSelectByActualTriggerBounds() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        val payloads = CopyOnWriteArrayList<ByteArray>()
        lateinit var host: MobileUiHost
        lateinit var list: FrameLayout
        onMain {
            host = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(6),
                    "orientation" to WireValue.Integer(2),
                ),
            )
            list = FrameLayout(host.context)
            list.addView(tabTrigger(host, "overview"))
            list.addView(tabTrigger(host, "settings"))
            host.addView(list)
            activity.setContentView(host)
        }
        instrumentation.waitForIdleSync()
        onMain {
            host.layout(0, 0, 240, 400)
            list.layout(20, 20, 220, 380)
            list.getChildAt(0).layout(0, 0, 200, 100)
            list.getChildAt(1).layout(0, 140, 200, 360)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 100f, 240f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 100f, 240f))
        }
        instrumentation.waitForIdleSync()
        onMain {
            assertEquals("settings", payloads.single().decodeToString())
            host.release()
            activity.finish()
        }
    }

    @Test
    fun tabsCoordinateIndicatorContentKeyboardAndAccessibilityLocally() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(6),
                    "value" to WireValue.Text("account"),
                    "orientation" to WireValue.Integer(1),
                    "activationMode" to WireValue.Integer(1),
                ),
            )
            val list = FrameLayout(host.context).apply { tag = "pam:tabs-list" }
            val account = tabTrigger(host, "account", selected = true)
            val security = tabTrigger(host, "security")
            val indicator = View(host.context).apply { tag = "pam:tabs-indicator" }
            list.addView(account)
            list.addView(security)
            list.addView(indicator)
            val contentWrapper = FrameLayout(host.context).apply {
                tag = "pam:tabs-content-wrapper"
            }
            val accountContent = View(host.context).apply {
                tag = "pam:tabs-content:account"
            }
            val securityContent = View(host.context).apply {
                tag = "pam:tabs-content:security"
            }
            contentWrapper.addView(accountContent)
            contentWrapper.addView(securityContent)
            host.addView(list)
            host.addView(contentWrapper)

            host.layout(0, 0, 400, 320)
            list.layout(20, 20, 380, 100)
            account.layout(0, 0, 140, 80)
            security.layout(160, 0, 360, 80)
            indicator.layout(0, 0, 1, 1)
            contentWrapper.layout(20, 120, 380, 300)
            accountContent.layout(0, 0, 360, 80)
            securityContent.layout(0, 0, 360, 140)

            assertTrue(security.performClick())
            assertEquals(listOf("security"), payloads.map(ByteArray::decodeToString))
            assertTrue(security.isSelected)
            assertTrue(!account.isSelected)
            assertEquals(View.GONE, accountContent.visibility)
            assertEquals(View.VISIBLE, securityContent.visibility)
            assertEquals(200, indicator.layoutParams.width)
            assertEquals(80, indicator.layoutParams.height)

            val rootInfo = AccessibilityNodeInfo.obtain()
            val securityInfo = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(rootInfo)
            security.onInitializeAccessibilityNodeInfo(securityInfo)
            assertEquals("android.widget.TabWidget", rootInfo.className)
            assertEquals(1, rootInfo.collectionInfo?.rowCount)
            assertEquals(2, rootInfo.collectionInfo?.columnCount)
            assertEquals(
                "Tab",
                securityInfo.extras.getCharSequence(
                    "AccessibilityNodeInfo.roleDescription",
                ),
            )
            assertTrue(securityInfo.isSelected)
            assertEquals(1, securityInfo.collectionItemInfo?.columnIndex)

            assertTrue(
                security.dispatchKeyEvent(
                    KeyEvent(KeyEvent.ACTION_DOWN, KeyEvent.KEYCODE_DPAD_RIGHT),
                ),
            )
            assertEquals(
                listOf("security", "account"),
                payloads.map(ByteArray::decodeToString),
            )
            assertTrue(account.isSelected)

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(6),
                    "value" to WireValue.Text("account"),
                    "orientation" to WireValue.Integer(1),
                    "activationMode" to WireValue.Integer(2),
                ),
            )
            assertTrue(
                account.dispatchKeyEvent(
                    KeyEvent(KeyEvent.ACTION_DOWN, KeyEvent.KEYCODE_DPAD_RIGHT),
                ),
            )
            assertEquals(2, payloads.size)
            assertTrue(account.isSelected)

            rootInfo.recycle()
            securityInfo.recycle()
            account.release()
            security.release()
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
    fun sheetSnapsOnceAfterDragAndCollapsesFromTheBackdrop() {
        onMain {
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                events += kind
                payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "open" to WireValue.Flag(true),
                    "snapPoints" to WireValue.Text("25\n50\n90"),
                    "snapToIndex" to WireValue.Integer(2),
                    "pressBehavior" to WireValue.Integer(2),
                ),
            )
            val backdrop = View(host.context).apply {
                tag = "pam:overlay-backdrop"
                alpha = 0.5f
                layoutParams = FrameLayout.LayoutParams(
                    FrameLayout.LayoutParams.MATCH_PARENT,
                    FrameLayout.LayoutParams.MATCH_PARENT,
                )
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:overlay-content"
                layoutParams = FrameLayout.LayoutParams(
                    FrameLayout.LayoutParams.MATCH_PARENT,
                    900,
                    Gravity.BOTTOM,
                )
            }
            val handle = View(host.context).apply {
                tag = "pam:sheet-drag-indicator"
                layoutParams = FrameLayout.LayoutParams(120, 48, Gravity.TOP or Gravity.CENTER_HORIZONTAL)
            }
            content.addView(handle)
            host.addView(backdrop)
            host.addView(content)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(300, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(1_000, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, 300, 1_000)

            assertEquals(0f, content.translationY, 0f)
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 150f, 120f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, 150f, 510f))
            assertTrue(events.isEmpty())
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 150f, 510f))

            assertEquals(
                listOf(NativeViewEventKind.CHANGE),
                events,
            )
            assertEquals(listOf("1"), payloads.map(ByteArray::decodeToString))

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 150f, 40f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 150f, 40f))
            assertEquals(
                listOf(
                    NativeViewEventKind.CHANGE,
                    NativeViewEventKind.CHANGE,
                ),
                events,
            )
            assertEquals(listOf("1", "0"), payloads.map(ByteArray::decodeToString))

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("Snap 1 of 3", host.stateDescription)
            assertTrue(info.isScrollable)
            assertTrue(
                info.actionList.any {
                    it.id == AccessibilityNodeInfo.ACTION_SCROLL_FORWARD
                },
            )

            host.release()
            info.recycle()
        }
    }

    @Test
    fun sheetItemsPublishTheirPressAndCloseAccordingToComponentPolicy() {
        onMain {
            val dismissals = CopyOnWriteArrayList<ByteArray>()
            val presses = CopyOnWriteArrayList<ByteArray>()
            val sheet = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) dismissals += payload
            }
            sheet.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "open" to WireValue.Flag(true),
                ),
            )
            val content = FrameLayout(sheet.context).apply {
                tag = "pam:overlay-content"
            }
            val selectItem = MobileUiHost(sheet.context) { kind, payload ->
                if (kind == NativeViewEventKind.PRESS) presses += payload
            }
            selectItem.update(
                mapOf(
                    "behavior" to WireValue.Integer(29),
                    "component" to WireValue.Integer(GeneratedComponents.SELECT_ITEM.toLong()),
                    "checked" to WireValue.Flag(true),
                ),
            )
            content.addView(selectItem)
            sheet.addView(content)

            assertTrue(selectItem.performClick())
            assertEquals(1, presses.size)
            assertEquals(1, dismissals.size)

            val sheetInfo = AccessibilityNodeInfo.obtain()
            val itemInfo = AccessibilityNodeInfo.obtain()
            sheet.onInitializeAccessibilityNodeInfo(sheetInfo)
            selectItem.onInitializeAccessibilityNodeInfo(itemInfo)
            assertEquals(1, sheetInfo.collectionInfo?.rowCount)
            assertEquals(0, itemInfo.collectionItemInfo?.rowIndex)
            assertTrue(itemInfo.isSelected)
            assertTrue(itemInfo.isChecked)
            assertEquals("android.widget.CheckedTextView", itemInfo.className)

            dismissals.clear()
            val actionItem = MobileUiHost(sheet.context) { kind, payload ->
                if (kind == NativeViewEventKind.PRESS) presses += payload
            }
            actionItem.update(
                mapOf(
                    "behavior" to WireValue.Integer(29),
                    "component" to WireValue.Integer(
                        GeneratedComponents.ACTIONSHEET_ITEM.toLong(),
                    ),
                ),
            )
            content.addView(actionItem)
            assertTrue(actionItem.performClick())
            assertEquals(2, presses.size)
            assertTrue(dismissals.isEmpty())

            selectItem.release()
            actionItem.release()
            sheet.release()
            sheetInfo.recycle()
            itemInfo.recycle()
        }
    }

    @Test
    fun anchoredOverlayOpensAndDismissesUncontrolledContentNatively() {
        onMain {
            var triggerPresses = 0
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val closePresses = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(19),
                    "defaultIsOpen" to WireValue.Flag(false),
                    "placement" to WireValue.Integer(4),
                ),
            )
            val trigger = View(host.context).apply {
                tag = "pam:overlay-trigger"
                isClickable = true
                setOnClickListener { triggerPresses++ }
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:overlay-content"
            }
            content.addView(View(host.context).apply {
                tag = "pam:overlay-arrow"
            })
            val close = MobileUiHost(host.context) { kind, payload ->
                if (kind == NativeViewEventKind.PRESS) closePresses += payload
            }
            close.update(mapOf("behavior" to WireValue.Integer(31)))
            content.addView(close)
            host.addView(trigger)
            host.addView(content)
            host.layout(0, 0, 500, 800)
            trigger.layout(100, 100, 300, 180)
            content.layout(100, 200, 400, 500)

            assertEquals(View.GONE, content.visibility)
            assertTrue(!host.acceptsOverlayInteraction())
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 180f, 140f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 180f, 140f)))
            assertEquals(1, triggerPresses)
            assertEquals(View.VISIBLE, content.visibility)
            assertTrue(host.acceptsOverlayInteraction())
            assertEquals("Expanded", trigger.stateDescription)

            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 20f, 700f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 20f, 700f)))
            assertTrue(!host.acceptsOverlayInteraction())
            assertEquals("Collapsed", trigger.stateDescription)
            assertEquals(2, payloads.size)
            val opening = WireMap.decode(payloads[0])
            val dismissal = WireMap.decode(payloads[1])
            assertEquals(3L, (opening["action"] as WireValue.Integer).value)
            assertTrue((opening["open"] as WireValue.Flag).value)
            assertEquals(1L, (dismissal["action"] as WireValue.Integer).value)
            assertTrue((dismissal["dismissed"] as WireValue.Flag).value)

            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 180f, 140f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 180f, 140f)))
            assertTrue(close.performClick())
            assertEquals(1, closePresses.size)
            assertEquals(4, payloads.size)
            assertEquals(
                3L,
                (WireMap.decode(payloads[2])["action"] as WireValue.Integer).value,
            )
            assertEquals(
                1L,
                (WireMap.decode(payloads[3])["action"] as WireValue.Integer).value,
            )
            assertTrue(!host.acceptsOverlayInteraction())

            close.release()
            host.release()
        }
    }

    @Test
    fun menuCoordinatesSelectionKeyboardAndCollectionSemanticsOnTheUiThread() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        val rootEvents = CopyOnWriteArrayList<ByteArray>()
        val itemEvents = CopyOnWriteArrayList<ByteArray>()
        lateinit var menu: MobileUiHost
        lateinit var first: MobileUiHost
        lateinit var second: MobileUiHost
        onMain {
            menu = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) rootEvents += payload
            }
            menu.update(
                mapOf(
                    "behavior" to WireValue.Integer(20),
                    "defaultIsOpen" to WireValue.Flag(true),
                    "selectionMode" to WireValue.Integer(2),
                    "closeOnSelect" to WireValue.Flag(false),
                ),
            )
            val content = FrameLayout(menu.context).apply {
                tag = "pam:overlay-content"
            }
            first = MobileUiHost(menu.context) { kind, payload ->
                if (kind == NativeViewEventKind.PRESS) itemEvents += payload
            }
            first.update(
                mapOf(
                    "behavior" to WireValue.Integer(30),
                    "selectionMode" to WireValue.Integer(2),
                    "selected" to WireValue.Flag(false),
                    "closeOnSelect" to WireValue.Flag(false),
                ),
            )
            first.addView(TextView(menu.context).apply { text = "Settings" })
            second = MobileUiHost(menu.context) { kind, payload ->
                if (kind == NativeViewEventKind.PRESS) itemEvents += payload
            }
            second.update(
                mapOf(
                    "behavior" to WireValue.Integer(30),
                    "selectionMode" to WireValue.Integer(2),
                    "selected" to WireValue.Flag(true),
                    "closeOnSelect" to WireValue.Flag(false),
                ),
            )
            second.addView(TextView(menu.context).apply { text = "Billing" })
            content.addView(first)
            content.addView(second)
            menu.addView(content)
            activity.setContentView(menu)
        }
        instrumentation.waitForIdleSync()
        onMain {

            assertTrue(first.performClick())
            assertEquals(1, itemEvents.size)
            assertTrue(rootEvents.isEmpty())
            assertTrue(first.isSelected)
            assertTrue(second.isSelected)

            val menuInfo = AccessibilityNodeInfo.obtain()
            val firstInfo = AccessibilityNodeInfo.obtain()
            menu.onInitializeAccessibilityNodeInfo(menuInfo)
            first.onInitializeAccessibilityNodeInfo(firstInfo)
            assertEquals("android.widget.ListView", menuInfo.className)
            assertEquals(2, menuInfo.collectionInfo?.rowCount)
            assertEquals(
                AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_MULTIPLE,
                menuInfo.collectionInfo?.selectionMode,
            )
            assertEquals("android.widget.CheckedTextView", firstInfo.className)
            assertTrue(firstInfo.isCheckable)
            assertTrue(firstInfo.isChecked)
            assertEquals(0, firstInfo.collectionItemInfo?.rowIndex)
            assertEquals("Settings", first.contentDescription)

            assertTrue(
                first.dispatchKeyEvent(
                    KeyEvent(KeyEvent.ACTION_DOWN, KeyEvent.KEYCODE_DPAD_DOWN),
                ),
            )
            assertTrue(second.hasFocus())

            first.release()
            second.release()
            menu.release()
            activity.finish()
            menuInfo.recycle()
            firstInfo.recycle()
        }
    }

    @Test
    fun anchoredOverlayFlipsAboveTheTriggerAndKeepsItsArrowAligned() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = instrumentation.startActivitySync(
            Intent(
                instrumentation.targetContext,
                TestHostActivity::class.java,
            ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
        ) as TestHostActivity
        instrumentation.waitForIdleSync()
        lateinit var host: MobileUiHost
        lateinit var trigger: View
        lateinit var content: FrameLayout
        lateinit var arrow: View
        onMain {
            host = MobileUiHost(activity) { _, _ -> }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(19),
                    "isOpen" to WireValue.Flag(true),
                    "placement" to WireValue.Integer(4),
                    "shouldFlip" to WireValue.Flag(true),
                    "offset" to WireValue.Decimal(8.0),
                ),
            )
            trigger = View(activity).apply {
                tag = "pam:overlay-trigger"
                layoutParams = FrameLayout.LayoutParams(180, 80, Gravity.BOTTOM).apply {
                    leftMargin = 120
                    bottomMargin = 12
                }
            }
            content = FrameLayout(activity).apply {
                tag = "pam:overlay-content"
                layoutParams = FrameLayout.LayoutParams(320, 240)
            }
            arrow = View(activity).apply {
                tag = "pam:overlay-arrow"
                layoutParams = FrameLayout.LayoutParams(24, 24)
            }
            content.addView(arrow)
            host.addView(trigger)
            host.addView(content)
            activity.setContentView(host)
        }
        instrumentation.waitForIdleSync()
        onMain {
            val contentTop = content.y
            assertTrue(contentTop + content.height <= trigger.y)
            assertEquals(180f, arrow.rotation, 0f)
            assertTrue(arrow.translationX > 0f)
            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_NO,
                arrow.importantForAccessibility,
            )
            host.release()
            activity.finish()
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
            val monthLabel = TextView(host.context).apply {
                text = "Month"
            }
            val monthSelect = FrameLayout(host.context).apply {
                tag = "pam:calendar-month-select"
                addView(monthLabel)
            }
            val yearLabel = TextView(host.context).apply {
                text = "Year"
            }
            val yearSelect = FrameLayout(host.context).apply {
                tag = "pam:calendar-year-select"
                addView(yearLabel)
            }
            val next = View(host.context).apply {
                tag = "pam:calendar-next"
            }
            val grid = View(host.context).apply {
                tag = "pam:calendar-grid"
            }
            host.addView(previous)
            host.addView(title)
            host.addView(monthSelect)
            host.addView(yearSelect)
            host.addView(next)
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            previous.layout(0, 0, 100, 100)
            title.layout(100, 0, 600, 100)
            monthSelect.layout(200, 0, 350, 100)
            monthLabel.layout(0, 0, 150, 100)
            yearSelect.layout(350, 0, 500, 100)
            yearLabel.layout(0, 0, 150, 100)
            next.layout(600, 0, 700, 100)
            grid.layout(0, 100, 700, 700)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 50f, 50f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 50f, 50f))

            val navigation = WireMap.decode(payloads.single())
            assertEquals("June 2026", title.text.toString())
            assertEquals("June", monthLabel.text.toString())
            assertEquals("2026", yearLabel.text.toString())
            assertEquals("Selected month 6", monthSelect.contentDescription)
            assertEquals("Selected year 2026", yearSelect.contentDescription)
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

    @Test
    fun inputAndFormControlKeepCompoundInteractionAndSemanticsOnTheUiThread() {
        val activity = launchTestHostActivity()
        onMain {
            val context = activity
            val inputEvents = CopyOnWriteArrayList<NativeViewEventKind>()
            val root = FrameLayout(context)
            val inputGroup = MobileUiHost(context) { _, _ -> }
            inputGroup.update(
                mapOf(
                    "behavior" to WireValue.Integer(32),
                    "focusColor" to WireValue.Integer(0xff2563eb),
                    "invalidColor" to WireValue.Integer(0xffdc2626),
                ),
            )
            val input = EditText(context).apply {
                setText("secret")
                transformationMethod = PasswordTransformationMethod.getInstance()
            }
            val clear = MobileUiHost(context) { kind, _ -> inputEvents += kind }
            clear.update(
                mapOf(
                    "behavior" to WireValue.Integer(33),
                    "slotAction" to WireValue.Integer(2),
                ),
            )
            val password = MobileUiHost(context) { kind, _ -> inputEvents += kind }
            password.update(
                mapOf(
                    "behavior" to WireValue.Integer(33),
                    "slotAction" to WireValue.Integer(3),
                ),
            )
            inputGroup.addView(input)
            inputGroup.addView(clear)
            inputGroup.addView(password)
            root.addView(inputGroup)
            activity.setContentView(root)
            inputGroup.layout(0, 0, 600, 120)

            assertTrue(inputGroup.performClick())
            assertTrue(input.hasFocus())
            assertTrue(clear.performClick())
            assertEquals("", input.text.toString())
            input.setText("secret")
            assertTrue(password.performClick())
            assertTrue(input.transformationMethod == null)
            assertEquals("Hide password", password.contentDescription)
            assertEquals(
                listOf(NativeViewEventKind.PRESS, NativeViewEventKind.PRESS),
                inputEvents,
            )

            inputGroup.update(
                mapOf(
                    "behavior" to WireValue.Integer(32),
                    "readOnly" to WireValue.Flag(true),
                ),
            )
            inputGroup.layout(0, 0, 601, 120)
            assertTrue(input.isEnabled)
            assertTrue(input.keyListener == null)

            val form = MobileUiHost(context) { _, _ -> }
            form.update(
                mapOf(
                    "behavior" to WireValue.Integer(34),
                    "required" to WireValue.Flag(true),
                    "invalid" to WireValue.Flag(true),
                ),
            )
            val label = FrameLayout(context).apply {
                tag = "pam:form-label"
                addView(TextView(context).apply { text = "Email" })
            }
            val formInput = EditText(context)
            val field = FrameLayout(context).apply { addView(formInput) }
            val helper = FrameLayout(context).apply {
                tag = "pam:form-helper"
                addView(TextView(context).apply { text = "Use your work email." })
            }
            val error = FrameLayout(context).apply {
                tag = "pam:form-error"
                addView(TextView(context).apply { text = "Email is invalid." })
            }
            form.addView(label)
            form.addView(field)
            form.addView(helper)
            form.addView(error)
            root.addView(form)
            form.layout(0, 0, 600, 360)
            label.layout(0, 0, 600, 72)
            field.layout(0, 72, 600, 180)
            form.layout(0, 0, 601, 360)

            val info = formInput.createAccessibilityNodeInfo()
            assertEquals("Email", formInput.contentDescription)
            assertEquals("Use your work email.", formInput.tooltipText)
            assertTrue(info.isContentInvalid)
            assertEquals("Email is invalid.", info.error)
            assertEquals("Required, Invalid", info.stateDescription)
            assertEquals(
                View.ACCESSIBILITY_LIVE_REGION_ASSERTIVE,
                error.accessibilityLiveRegion,
            )

            formInput.clearFocus()
            form.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 20f, 20f))
            form.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 20f, 20f))
            assertTrue(formInput.hasFocus())

            info.recycle()
            clear.release()
            password.release()
            inputGroup.release()
            form.release()
            activity.finish()
        }
    }

    @Test
    fun tableKeepsAuthoredCellsAndExposesNativeCollectionCoordinates() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val table = MobileUiHost(context) { _, _ -> }
            table.update(mapOf("behavior" to WireValue.Integer(35)))
            val headerWrapper = FrameLayout(context)
            val header = MobileUiHost(context) { _, _ -> }
            header.update(
                mapOf(
                    "behavior" to WireValue.Integer(36),
                    "isHeaderRow" to WireValue.Flag(true),
                ),
            )
            val packageHead = TextView(context).apply { text = "Package" }
            val runtimeHead = TextView(context).apply { text = "Runtime" }
            header.addView(packageHead)
            header.addView(runtimeHead)
            headerWrapper.addView(header)

            val bodyWrapper = FrameLayout(context)
            val body = MobileUiHost(context) { _, _ -> }
            body.update(mapOf("behavior" to WireValue.Integer(36)))
            val packageCell = TextView(context).apply { text = "pushinbr/pam-mobile-ui" }
            val runtimeCell = TextView(context).apply { text = "Android" }
            body.addView(packageCell)
            body.addView(runtimeCell)
            bodyWrapper.addView(body)
            table.addView(headerWrapper)
            table.addView(bodyWrapper)
            table.layout(0, 0, 800, 240)

            val tableInfo = AccessibilityNodeInfo.obtain()
            val rowInfo = AccessibilityNodeInfo.obtain()
            table.onInitializeAccessibilityNodeInfo(tableInfo)
            header.onInitializeAccessibilityNodeInfo(rowInfo)
            val headerCellInfo = runtimeHead.createAccessibilityNodeInfo()
            val bodyCellInfo = packageCell.createAccessibilityNodeInfo()

            assertEquals("android.widget.TableLayout", tableInfo.className)
            assertEquals(2, tableInfo.collectionInfo?.rowCount)
            assertEquals(2, tableInfo.collectionInfo?.columnCount)
            assertEquals("android.widget.TableRow", rowInfo.className)
            assertEquals(0, rowInfo.collectionItemInfo?.rowIndex)
            assertEquals(1, headerCellInfo.collectionItemInfo?.columnIndex)
            assertTrue(headerCellInfo.isHeading)
            assertEquals(1, bodyCellInfo.collectionItemInfo?.rowIndex)
            assertEquals(0, bodyCellInfo.collectionItemInfo?.columnIndex)

            tableInfo.recycle()
            rowInfo.recycle()
            headerCellInfo.recycle()
            bodyCellInfo.recycle()
            header.release()
            body.release()
            table.release()
        }
    }

    @Test
    fun skeletonAndToastKeepFeedbackAnimationAndAnnouncementsNative() {
        lateinit var skeleton: MobileUiHost
        lateinit var toast: MobileUiHost
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            skeleton = MobileUiHost(context) { _, _ -> }
            skeleton.update(
                mapOf(
                    "behavior" to WireValue.Integer(8),
                    "component" to WireValue.Integer(
                        GeneratedComponents.SKELETON_TEXT.toLong(),
                    ),
                    "pulseDuration" to WireValue.Integer(2_000),
                    "lines" to WireValue.Integer(3),
                ),
            )
            toast = MobileUiHost(context) { _, _ -> }
            toast.update(
                mapOf(
                    "behavior" to WireValue.Integer(12),
                    "action" to WireValue.Integer(4),
                    "persistent" to WireValue.Flag(true),
                ),
            )
            toast.addView(TextView(context).apply { text = "Could not save" })
            toast.addView(TextView(context).apply { text = "Try again" })
        }
        InstrumentationRegistry.getInstrumentation().waitForIdleSync()
        onMain {
            val skeletonInfo = AccessibilityNodeInfo.obtain()
            val toastInfo = AccessibilityNodeInfo.obtain()
            skeleton.onInitializeAccessibilityNodeInfo(skeletonInfo)
            toast.onInitializeAccessibilityNodeInfo(toastInfo)

            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS,
                skeleton.importantForAccessibility,
            )
            assertEquals("android.view.View", skeletonInfo.className)
            assertEquals("android.widget.Toast", toastInfo.className)
            assertEquals("Could not save. Try again", toast.contentDescription)
            assertEquals(
                View.ACCESSIBILITY_LIVE_REGION_ASSERTIVE,
                toast.accessibilityLiveRegion,
            )

            skeletonInfo.recycle()
            toastInfo.recycle()
            skeleton.release()
            toast.release()
        }
    }

    @Test
    fun imageViewerCoordinatesGalleryNavigationAndDismissalOnTheUiThread() {
        onMain {
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val viewer = MobileUiHost(context) { kind, payload ->
                events += kind
                payloads += payload
            }
            viewer.update(
                mapOf(
                    "behavior" to WireValue.Integer(13),
                    "initialIndex" to WireValue.Integer(0),
                ),
            )
            val first = View(context).apply {
                tag = "pam:image-viewer-image:0"
                contentDescription = "Mountain"
            }
            val second = View(context).apply {
                tag = "pam:image-viewer-image:1"
                contentDescription = "Ocean"
            }
            val third = View(context).apply {
                tag = "pam:image-viewer-image:2"
                contentDescription = "Desert"
            }
            val counter = TextView(context).apply {
                tag = "pam:image-viewer-counter"
            }
            val previous = MobileUiHost(context) { _, _ -> }
            previous.update(
                mapOf(
                    "behavior" to WireValue.Integer(37),
                    "navigationAction" to WireValue.Integer(1),
                ),
            )
            val next = MobileUiHost(context) { _, _ -> }
            next.update(
                mapOf(
                    "behavior" to WireValue.Integer(37),
                    "navigationAction" to WireValue.Integer(2),
                ),
            )
            val close = MobileUiHost(context) { _, _ -> }
            close.update(mapOf("behavior" to WireValue.Integer(31)))
            viewer.addView(first)
            viewer.addView(second)
            viewer.addView(third)
            viewer.addView(counter)
            viewer.addView(previous)
            viewer.addView(next)
            viewer.addView(close)
            viewer.layout(0, 0, 1_080, 2_000)

            assertEquals(View.VISIBLE, first.visibility)
            assertEquals(View.GONE, second.visibility)
            assertEquals(View.INVISIBLE, previous.visibility)
            assertEquals(View.VISIBLE, next.visibility)
            assertEquals("1 / 3", counter.text.toString())

            assertTrue(
                viewer.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                    null,
                ),
            )
            assertEquals(View.GONE, first.visibility)
            assertEquals(View.VISIBLE, second.visibility)
            assertEquals("2 / 3", counter.text.toString())
            assertEquals(NativeViewEventKind.CHANGE, events[0])
            assertEquals("1", payloads[0].decodeToString())

            assertTrue(previous.performClick())
            assertEquals(View.VISIBLE, first.visibility)
            assertEquals("0", payloads[1].decodeToString())
            assertTrue(close.performClick())
            val dismissal = WireMap.decode(payloads.last())
            assertEquals(NativeViewEventKind.NATIVE, events.last())
            assertEquals(1L, (dismissal["action"] as WireValue.Integer).value)
            assertTrue((dismissal["dismissed"] as WireValue.Flag).value)

            val info = AccessibilityNodeInfo.obtain()
            viewer.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.Gallery", info.className)
            assertEquals(3, info.collectionInfo?.columnCount)

            info.recycle()
            previous.release()
            next.release()
            close.release()
            viewer.release()
        }
    }

    @Test
    fun chatAiCoordinatesBranchesPromptSubmissionAndScrollControlsNatively() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val branchEvents = CopyOnWriteArrayList<NativeViewEventKind>()
            val branchPayloads = CopyOnWriteArrayList<ByteArray>()
            val branch = MobileUiHost(context) { kind, payload ->
                branchEvents += kind
                branchPayloads += payload
            }
            branch.update(
                mapOf(
                    "behavior" to WireValue.Integer(38),
                    "defaultBranch" to WireValue.Integer(0),
                    "loop" to WireValue.Flag(false),
                ),
            )
            val first = View(context).apply {
                tag = "pam:message-branch-page:0"
            }
            val second = View(context).apply {
                tag = "pam:message-branch-page:1"
            }
            val selector = FrameLayout(context).apply {
                tag = "pam:message-branch-selector"
            }
            val previous = MobileUiHost(context) { _, _ -> }
            previous.update(
                mapOf(
                    "behavior" to WireValue.Integer(39),
                    "navigationAction" to WireValue.Integer(1),
                ),
            )
            val page = TextView(context).apply {
                tag = "pam:message-branch-counter"
            }
            val next = MobileUiHost(context) { _, _ -> }
            next.update(
                mapOf(
                    "behavior" to WireValue.Integer(39),
                    "navigationAction" to WireValue.Integer(2),
                ),
            )
            selector.addView(previous)
            selector.addView(page)
            selector.addView(next)
            branch.addView(first)
            branch.addView(second)
            branch.addView(selector)
            branch.layout(0, 0, 1_080, 800)

            assertEquals(View.VISIBLE, first.visibility)
            assertEquals(View.GONE, second.visibility)
            assertTrue(!previous.isEnabled)
            assertTrue(next.isEnabled)
            assertEquals("1 of 2", page.text.toString())
            assertTrue(next.performClick())
            assertEquals(View.GONE, first.visibility)
            assertEquals(View.VISIBLE, second.visibility)
            assertEquals("2 of 2", page.text.toString())
            assertEquals(NativeViewEventKind.CHANGE, branchEvents.single())
            assertEquals("1", branchPayloads.single().decodeToString())

            val branchInfo = AccessibilityNodeInfo.obtain()
            branch.onInitializeAccessibilityNodeInfo(branchInfo)
            assertEquals("androidx.viewpager.widget.ViewPager", branchInfo.className)
            assertEquals(2, branchInfo.collectionInfo?.columnCount)

            val promptEvents = CopyOnWriteArrayList<NativeViewEventKind>()
            val promptPayloads = CopyOnWriteArrayList<ByteArray>()
            val prompt = MobileUiHost(context) { kind, payload ->
                promptEvents += kind
                promptPayloads += payload
            }
            prompt.update(
                mapOf(
                    "behavior" to WireValue.Integer(40),
                    "clearOnSubmit" to WireValue.Flag(true),
                    "trimOnSubmit" to WireValue.Flag(true),
                ),
            )
            val input = EditText(context)
            val submit = MobileUiHost(context) { _, _ -> }
            submit.update(mapOf("behavior" to WireValue.Integer(41)))
            prompt.addView(input)
            prompt.addView(submit)
            prompt.layout(0, 0, 1_080, 240)

            assertTrue(!submit.isEnabled)
            input.setText("  Build PAM  ")
            assertTrue(submit.isEnabled)
            assertTrue(submit.performClick())
            assertEquals(NativeViewEventKind.SUBMIT, promptEvents.single())
            assertEquals("Build PAM", promptPayloads.single().decodeToString())
            assertEquals("", input.text.toString())
            assertTrue(!submit.isEnabled)
            assertEquals("Send prompt", submit.contentDescription)

            prompt.update(
                mapOf(
                    "behavior" to WireValue.Integer(40),
                    "clearOnSubmit" to WireValue.Flag(true),
                    "trimOnSubmit" to WireValue.Flag(true),
                    "attachmentCount" to WireValue.Integer(1),
                ),
            )
            assertTrue(submit.isEnabled)
            assertTrue(submit.performClick())
            assertEquals(2, promptEvents.size)
            assertEquals("", promptPayloads.last().decodeToString())

            val conversation = MobileUiHost(context) { _, _ -> }
            conversation.update(
                mapOf(
                    "behavior" to WireValue.Integer(14),
                    "autoScroll" to WireValue.Flag(true),
                ),
            )
            val scrollButton = MobileUiHost(context) { _, _ -> }
            scrollButton.update(mapOf("behavior" to WireValue.Integer(42)))
            conversation.addView(scrollButton)
            conversation.layout(0, 0, 1_080, 800)
            val conversationInfo = AccessibilityNodeInfo.obtain()
            conversation.onInitializeAccessibilityNodeInfo(conversationInfo)
            assertEquals("android.widget.ListView", conversationInfo.className)
            assertEquals("Scroll to latest message", scrollButton.contentDescription)

            val treeEvents = CopyOnWriteArrayList<NativeViewEventKind>()
            val treePayloads = CopyOnWriteArrayList<ByteArray>()
            val tree = MobileUiHost(context) { kind, payload ->
                treeEvents += kind
                treePayloads += payload
            }
            tree.update(
                mapOf(
                    "behavior" to WireValue.Integer(43),
                    "expandedPaths" to WireValue.Text("/src"),
                    "selectedPath" to WireValue.Text("/src/App.php"),
                ),
            )
            val folder = MobileUiHost(context) { _, _ -> }
            folder.update(
                mapOf(
                    "behavior" to WireValue.Integer(44),
                    "path" to WireValue.Text("/src"),
                ),
            )
            val header = FrameLayout(context)
            val name = TextView(context).apply {
                tag = "pam:file-tree-name"
                text = "src"
            }
            header.addView(name)
            val content = FrameLayout(context).apply {
                tag = "pam:file-tree-content"
            }
            val file = MobileUiHost(context) { _, _ -> }
            file.update(
                mapOf(
                    "behavior" to WireValue.Integer(45),
                    "path" to WireValue.Text("/src/App.php"),
                ),
            )
            content.addView(file)
            folder.addView(header)
            folder.addView(content)
            tree.addView(folder)
            tree.layout(0, 0, 1_080, 800)

            assertEquals(View.VISIBLE, content.visibility)
            assertTrue(file.isSelected)
            assertTrue(folder.performClick())
            assertEquals(View.GONE, content.visibility)
            assertTrue(folder.isSelected)
            assertEquals(NativeViewEventKind.CHANGE, treeEvents[0])
            assertEquals("/src", treePayloads[0].decodeToString())
            assertEquals(NativeViewEventKind.NATIVE, treeEvents[1])
            val expandedEvent = WireMap.decode(treePayloads[1])
            assertEquals(
                1L,
                (expandedEvent["action"] as WireValue.Integer).value,
            )
            assertEquals(
                false,
                (expandedEvent["expanded"] as WireValue.Flag).value,
            )
            assertEquals(
                "/src",
                (expandedEvent["path"] as WireValue.Text).value,
            )
            val folderInfo = AccessibilityNodeInfo.obtain()
            folder.onInitializeAccessibilityNodeInfo(folderInfo)
            assertEquals("android.widget.Button", folderInfo.className)
            assertEquals("src", folderInfo.contentDescription)

            folderInfo.recycle()
            conversationInfo.recycle()
            branchInfo.recycle()
            file.release()
            folder.release()
            tree.release()
            scrollButton.release()
            conversation.release()
            submit.release()
            prompt.release()
            previous.release()
            next.release()
            branch.release()
        }
    }

    private fun dp(view: View, value: Float): Int =
        (value * view.resources.displayMetrics.density + 0.5f).toInt()

    private fun tabTrigger(
        parent: MobileUiHost,
        value: String,
        selected: Boolean = false,
        disabled: Boolean = false,
    ): MobileUiHost =
        MobileUiHost(parent.context) { _, _ -> }.apply {
            update(
                mapOf(
                    "behavior" to WireValue.Integer(28),
                    "value" to WireValue.Text(value),
                    "selected" to WireValue.Flag(selected),
                    "disabled" to WireValue.Flag(disabled),
                ),
            )
        }

    private fun motion(action: Int, x: Float, y: Float): MotionEvent =
        MotionEvent.obtain(0L, 0L, action, x, y, 0)

    private fun launchTestHostActivity(): TestHostActivity {
        val instrumentation = InstrumentationRegistry.getInstrumentation()

        return instrumentation.startActivitySync(
            Intent(
                instrumentation.targetContext,
                TestHostActivity::class.java,
            ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
        ) as TestHostActivity
    }

    private fun onMain(block: () -> Unit) {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(block)
    }
}
