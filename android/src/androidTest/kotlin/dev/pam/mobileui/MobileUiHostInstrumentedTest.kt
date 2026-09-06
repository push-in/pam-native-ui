package dev.pam.mobileui

import android.content.Intent
import android.content.res.Configuration
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.os.Build
import android.os.Debug
import android.os.Looper
import android.text.Spanned
import android.text.method.PasswordTransformationMethod
import android.text.style.ClickableSpan
import android.text.style.StyleSpan
import android.util.Log
import android.view.KeyEvent
import android.view.MotionEvent
import android.view.View
import android.view.Gravity
import android.view.ViewGroup
import android.view.ViewOutlineProvider
import android.view.inputmethod.BaseInputConnection
import android.widget.FrameLayout
import android.widget.EditText
import android.widget.TextView
import android.view.accessibility.AccessibilityNodeInfo
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import androidx.test.platform.app.InstrumentationRegistry
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.views.NativeViewEventKind
import java.util.concurrent.CountDownLatch
import java.util.concurrent.CopyOnWriteArrayList
import java.util.concurrent.TimeUnit
import kotlin.math.roundToInt
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
@Suppress("DEPRECATION")
class MobileUiHostInstrumentedTest {
    @Test
    fun bottomSheetDetentsGrowOnlyWhenTheirContentWouldBeClipped() {
        assertEquals(
            1_632 to 816,
            adaptiveBottomSheetHeightsPx(
                viewportHeight = 2_400,
                maximumSnapPercent = 68f,
                selectedSnapPercent = 34f,
                requiredContentHeight = 600,
            ),
        )
        assertEquals(
            600 to 600,
            adaptiveBottomSheetHeightsPx(
                viewportHeight = 1_080,
                maximumSnapPercent = 34f,
                selectedSnapPercent = 34f,
                requiredContentHeight = 600,
            ),
        )
        assertEquals(
            972 to 972,
            adaptiveBottomSheetHeightsPx(
                viewportHeight = 1_080,
                maximumSnapPercent = 34f,
                selectedSnapPercent = 34f,
                requiredContentHeight = 1_400,
            ),
        )
        assertEquals(
            1_061 to 1_061,
            adaptiveBottomSheetHeightsPx(
                viewportHeight = 2_400,
                maximumSnapPercent = 34f,
                selectedSnapPercent = 34f,
                requiredContentHeight = 600,
                fontScale = 1.3f,
            ),
        )
        assertEquals(
            748 to 748,
            adaptiveBottomSheetHeightsPx(
                viewportHeight = 1_080,
                maximumSnapPercent = 34f,
                selectedSnapPercent = 34f,
                requiredContentHeight = 528,
                density = 2.75f,
            ),
        )
        assertEquals(
            900 to 250,
            adaptiveBottomSheetHeightsPx(
                viewportHeight = 1_000,
                maximumSnapPercent = 90f,
                selectedSnapPercent = 25f,
                requiredContentHeight = 48,
                density = 2.75f,
                snapPointCount = 3,
            ),
        )
    }

    @Test
    fun adaptiveSingleDetentNeverHidesItsRequiredContentBelowTheViewport() {
        assertEquals(
            0f,
            bottomSheetTranslationPx(
                maximumHeight = 616,
                selectedHeight = 528,
                snapPointCount = 1,
            ),
            0f,
        )
        assertEquals(
            88f,
            bottomSheetTranslationPx(
                maximumHeight = 616,
                selectedHeight = 528,
                snapPointCount = 2,
            ),
            0f,
        )
    }

    @Test
    fun bottomSheetHeightIncludesOverflowInsideNestedNativeContainers() {
        onMain {
            val root = FrameLayout(ApplicationProvider.getApplicationContext())
            val nested = FrameLayout(root.context)
            val action = View(root.context)
            root.addView(nested)
            nested.addView(action)
            root.layout(0, 0, 300, 528)
            nested.layout(0, 0, 300, 528)
            action.layout(0, 484, 300, 616)

            assertEquals(616, descendantContentBottomPx(root))
        }
    }

    @Test
    fun selectionSheetItemClipsFocusAndRippleToMaterialCorners() {
        onMain {
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(24),
                    "component" to WireValue.Integer(91),
                ),
            )
            host.layout(0, 0, dp(host, 328f), dp(host, 56f))

            assertTrue(host.clipToOutline)
            assertTrue(host.outlineProvider !== ViewOutlineProvider.BACKGROUND)

            host.release()
        }
    }

    @Test
    fun zeroWidthInputOutlineLeavesSlotOwnedFocusVisualsUntouched() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        onMain {
            val host = MobileUiHost(activity) { _, _ -> }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(27),
                    "focusColor" to WireValue.Integer(0xff15803d),
                    "outlineWidth" to WireValue.Decimal(0.0),
                ),
            )
            val input = EditText(activity).apply { background = null }
            host.addView(input)
            activity.setContentView(host)
            host.layout(0, 0, 600, 120)
            input.layout(0, 0, 600, 120)
            assertTrue(input.requestFocus())

            val rendered = Bitmap.createBitmap(600, 120, Bitmap.Config.ARGB_8888)
            host.draw(Canvas(rendered))
            assertEquals(Color.TRANSPARENT, rendered.getPixel(300, 119))

            host.release()
            activity.finish()
        }
        instrumentation.waitForIdleSync()
    }

    @Test
    fun inputFocusIndicatorStaysOnFieldSurfaceAboveHelperText() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        onMain {
            val focusColor = Color.rgb(21, 128, 61)
            val host = MobileUiHost(activity) { _, _ -> }
            val properties = mapOf(
                "behavior" to WireValue.Integer(27),
                "focusColor" to WireValue.Integer(focusColor.toLong()),
                "outlineWidth" to WireValue.Decimal(2.0),
                "indicatorOnly" to WireValue.Flag(true),
            )
            host.update(properties)

            val surface = FrameLayout(activity)
            val input = EditText(activity).apply { background = null }
            val helper = TextView(activity).apply { text = "Supporting text" }
            surface.addView(input)
            host.addView(surface)
            host.addView(helper)
            activity.setContentView(host)

            host.layout(0, 0, 600, 180)
            surface.layout(20, 0, 580, 120)
            input.layout(16, 32, 544, 104)
            helper.layout(36, 128, 564, 160)
            assertTrue(input.requestFocus())
            // Reconcile the aggregate input state after focus just as a native
            // property frame does in production.
            host.update(properties)

            val rendered = Bitmap.createBitmap(600, 180, Bitmap.Config.ARGB_8888)
            host.draw(Canvas(rendered))
            assertEquals(focusColor, rendered.getPixel(300, 118))
            assertEquals(Color.TRANSPARENT, rendered.getPixel(300, 179))

            host.release()
            activity.finish()
        }
        instrumentation.waitForIdleSync()
    }

    @Test
    fun searchableSelectionSheetUsesBoundedContentHeight() {
        assertEquals(
            960,
            selectionSheetContentHeightPx(
                viewportHeight = 2_400,
                density = 3f,
                visibleItemCount = 4,
                searchable = true,
                supplementaryRow = false,
            ),
        )
        assertEquals(
            1_020,
            selectionSheetContentHeightPx(
                viewportHeight = 2_400,
                density = 3f,
                visibleItemCount = 4,
                searchable = true,
                supplementaryRow = false,
                dragHandle = true,
            ),
        )
        assertEquals(
            456,
            selectionSheetContentHeightPx(
                viewportHeight = 2_400,
                density = 3f,
                visibleItemCount = 1,
                searchable = true,
                supplementaryRow = false,
            ),
        )
        assertEquals(
            456,
            selectionSheetContentHeightPx(
                viewportHeight = 1_080,
                density = 3f,
                visibleItemCount = 0,
                searchable = true,
                supplementaryRow = false,
            ),
        )
        assertEquals(
            2_160,
            selectionSheetContentHeightPx(
                viewportHeight = 2_400,
                density = 3f,
                visibleItemCount = 40,
                searchable = true,
                supplementaryRow = false,
            ),
        )
    }

    @Test
    fun emptySearchableSheetSeparatesSearchAndNoDataMessage() {
        onMain {
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "component" to WireValue.Integer(
                        GeneratedComponents.SELECT_PORTAL.toLong(),
                    ),
                    "open" to WireValue.Flag(true),
                    "searchable" to WireValue.Flag(true),
                    "allowCustomValue" to WireValue.Flag(true),
                    "enableDynamicSizing" to WireValue.Flag(true),
                    "noDataText" to WireValue.Text("Nothing matches"),
                    "customActionTextColor" to WireValue.Integer(0xff166534),
                    "customActionBackgroundColor" to WireValue.Integer(0xffdcfce7),
                    "customActionPressedBackgroundColor" to WireValue.Integer(0xffbbf7d0),
                ),
            )
            val backdrop = View(host.context).apply {
                tag = "pam:overlay-backdrop"
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:overlay-content"
            }
            val handle = View(host.context).apply {
                tag = "pam:sheet-drag-indicator"
            }
            val handleWrapper = FrameLayout(host.context).apply {
                tag = "pam:sheet-drag-indicator-wrapper"
                addView(handle)
            }
            content.addView(handleWrapper)
            host.addView(backdrop)
            host.addView(content)

            val width = dp(host, 360f)
            val height = dp(host, 800f)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)

            val search = (0 until content.childCount)
                .map(content::getChildAt)
                .filterIsInstance<EditText>()
                .single()
            val message = (0 until content.childCount)
                .map(content::getChildAt)
                .filterIsInstance<TextView>()
                .single { it.text?.toString() == "Nothing matches" }

            assertEquals(View.VISIBLE, message.visibility)
            assertEquals(dp(host, 48f), search.height)
            assertEquals(dp(host, 56f), message.height)
            assertTrue(message.top >= search.bottom + dp(host, 8f))
            assertTrue(kotlin.math.abs(search.top - dp(host, 32f)) <= 1)
            assertEquals(dp(host, 32f), handle.width)
            assertEquals(dp(host, 4f), handle.height)
            assertEquals((width - handle.width) / 2, handle.left)
            assertEquals(dp(host, 16f), search.left)
            assertEquals(width - dp(host, 16f), search.right)
            assertEquals(search.left, message.left)
            assertEquals(search.right, message.right)

            assertTrue(search.requestFocus())
            search.setText("pro")
            BaseInputConnection.setComposingSpans(search.text)
            assertTrue(BaseInputConnection.getComposingSpanStart(search.text) >= 0)
            search.clearFocus()
            assertEquals(-1, BaseInputConnection.getComposingSpanStart(search.text))

            search.setText("custom")
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)
            val customAction = (0 until content.childCount)
                .map(content::getChildAt)
                .filterIsInstance<TextView>()
                .single { it.text?.toString() == "Use custom" }
            assertEquals(View.VISIBLE, customAction.visibility)
            assertEquals(dp(host, 56f), customAction.height)
            assertTrue(customAction.top >= search.bottom + dp(host, 8f))
            assertEquals(0xff166534.toInt(), customAction.currentTextColor)
            assertTrue(
                customAction.background is android.graphics.drawable.StateListDrawable,
            )

            // Local selection portals reuse their Dialog and content tree. A
            // dismissed window must not leak the previous query into the next
            // opening, while configuration changes keep the active query.
            host.handleSheetWindowVisibilityChanged(View.VISIBLE)
            search.setText("stale query")
            host.handleSheetWindowVisibilityChanged(View.INVISIBLE)
            assertEquals("", search.text?.toString())

            host.release()
        }
    }

    @Test
    fun emptyNonSearchableSelectionSheetOwnsOneMaterialRowBelowItsHandle() {
        onMain {
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "component" to WireValue.Integer(
                        GeneratedComponents.SELECT_PORTAL.toLong(),
                    ),
                    "open" to WireValue.Flag(true),
                    "searchable" to WireValue.Flag(false),
                    "enableDynamicSizing" to WireValue.Flag(true),
                    "noDataText" to WireValue.Text("No choices"),
                ),
            )
            val backdrop = View(host.context).apply {
                tag = "pam:overlay-backdrop"
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:overlay-content"
            }
            val indicator = View(host.context).apply {
                tag = "pam:sheet-drag-indicator"
            }
            content.addView(FrameLayout(host.context).apply {
                tag = "pam:sheet-drag-indicator-wrapper"
                addView(indicator)
            })
            host.addView(backdrop)
            host.addView(content)

            val width = dp(host, 360f)
            val height = dp(host, 800f)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)

            assertTrue(
                (0 until content.childCount)
                    .map(content::getChildAt)
                    .none { it is EditText },
            )
            val message = (0 until content.childCount)
                .map(content::getChildAt)
                .filterIsInstance<TextView>()
                .single { it.text?.toString() == "No choices" }
            assertEquals(View.VISIBLE, message.visibility)
            assertEquals(dp(host, 56f), message.height)
            assertTrue(kotlin.math.abs(message.top - dp(host, 32f)) <= 1)
            assertEquals(dp(host, 16f), message.left)
            assertEquals(width - dp(host, 16f), message.right)

            host.release()
        }
    }

    @Test
    fun genericBottomSheetNeverInjectsASelectionEmptyState() {
        onMain {
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "component" to WireValue.Integer(
                        GeneratedComponents.BOTTOM_SHEET_PORTAL.toLong(),
                    ),
                    "open" to WireValue.Flag(true),
                    "searchable" to WireValue.Flag(false),
                    "snapPoints" to WireValue.Text("28"),
                    "enableDynamicSizing" to WireValue.Flag(false),
                ),
            )
            val backdrop = View(host.context).apply {
                tag = "pam:overlay-backdrop"
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:overlay-content"
            }
            val title = TextView(host.context).apply {
                text = "Choose an action"
            }
            content.addView(FrameLayout(host.context).apply {
                tag = "pam:sheet-drag-indicator-wrapper"
                addView(View(host.context).apply {
                    tag = "pam:sheet-drag-indicator"
                })
            })
            content.addView(title)
            host.addView(backdrop)
            host.addView(content)

            val width = dp(host, 360f)
            val height = dp(host, 800f)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)

            val textChildren = (0 until content.childCount)
                .map(content::getChildAt)
                .filterIsInstance<TextView>()
            assertEquals(listOf("Choose an action"), textChildren.map { it.text.toString() })
            assertTrue(textChildren.single() === title)

            host.release()
        }
    }

    @Test
    fun closedBottomSheetDoesNotStealFocusWhenItsHostBecomesVisible() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        lateinit var outside: EditText
        lateinit var host: MobileUiHost
        lateinit var inside: TextView
        onMain {
            val root = FrameLayout(activity)
            outside = EditText(activity).apply {
                contentDescription = "Outside field"
            }
            host = MobileUiHost(activity) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(3),
                    "component" to WireValue.Integer(
                        GeneratedComponents.BOTTOM_SHEET_PORTAL.toLong(),
                    ),
                    "open" to WireValue.Flag(false),
                ),
            )
            inside = TextView(activity).apply {
                text = "Closed sheet action"
                isFocusable = true
                isFocusableInTouchMode = true
            }
            host.addView(FrameLayout(activity).apply {
                tag = "pam:overlay-content"
                addView(inside)
            })
            root.addView(outside)
            root.addView(host)
            activity.setContentView(root)
            assertTrue(outside.requestFocus())
            host.visibility = View.INVISIBLE
            host.visibility = View.VISIBLE
        }
        instrumentation.waitForIdleSync()
        onMain {
            assertTrue(outside.hasFocus())
            assertTrue(!inside.hasFocus())
            host.release()
            activity.finish()
        }
    }

    @Test
    fun canvasTypographyFollowsTheSystemFontScale() {
        val context = ApplicationProvider.getApplicationContext<android.content.Context>()
        val normal = context.createConfigurationContext(
            Configuration(context.resources.configuration).apply { fontScale = 1f },
        )
        val enlarged = context.createConfigurationContext(
            Configuration(context.resources.configuration).apply { fontScale = 2f },
        )

        val normalPixels = scaledTextSizePx(normal, 14f)
        val enlargedPixels = scaledTextSizePx(enlarged, 14f)
        assertTrue(normalPixels > 0f)
        assertTrue(enlargedPixels > normalPixels)
    }

    @Test
    fun abstractSelectionItemUsesButtonSemanticsWithoutCheckboxChrome() {
        onMain {
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(9),
                    "abstractSelectionItem" to WireValue.Flag(true),
                    "checked" to WireValue.Flag(true),
                    "accessibilityLabel" to WireValue.Text("Grid view"),
                ),
            )
            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.Button", info.className)
            assertTrue(info.isSelected)
            assertTrue(!info.isCheckable)
            info.recycle()
            host.release()
        }
    }

    @Test
    fun abstractSelectionItemPaintsSelectedContainerAndForeground() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> Unit }
            val content = FrameLayout(context).apply {
                setBackgroundColor(Color.RED)
            }
            val label = TextView(context).apply {
                text = "Design"
                setTextColor(Color.BLACK)
            }
            content.addView(label)
            host.addView(
                content,
                ViewGroup.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT,
                    ViewGroup.LayoutParams.MATCH_PARENT,
                ),
            )
            val base = mapOf(
                "behavior" to WireValue.Integer(9),
                "abstractSelectionItem" to WireValue.Flag(true),
                "foregroundColor" to WireValue.Integer(Color.BLACK.toLong()),
                "selectedForegroundColor" to WireValue.Integer(Color.WHITE.toLong()),
                "selectedContainerColor" to WireValue.Integer(Color.BLUE.toLong()),
            )
            host.update(base + ("checked" to WireValue.Flag(false)))
            host.measure(
                View.MeasureSpec.makeMeasureSpec(240, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(64, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, 240, 64)
            assertEquals(Color.BLACK, label.currentTextColor)

            host.update(base + ("checked" to WireValue.Flag(true)))
            val rendered = Bitmap.createBitmap(240, 64, Bitmap.Config.ARGB_8888)
            host.draw(Canvas(rendered))
            assertEquals(Color.WHITE, label.currentTextColor)
            // The Material medium shape deliberately leaves the extreme
            // corner transparent; sample inside the selected container.
            assertEquals(Color.BLUE, rendered.getPixel(16, 16))
            host.release()
        }
    }

    @Test
    fun listItemMeasurementFollowsMaterialLinesAndDensity() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> Unit }
            host.addView(TextView(context).apply { text = "Title" })
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(37),
                    "lines" to WireValue.Integer(1),
                    "density" to WireValue.Text("compact"),
                ),
            )
            host.measure(
                View.MeasureSpec.makeMeasureSpec(dp(host, 320f), View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(dp(host, 200f), View.MeasureSpec.AT_MOST),
            )
            assertEquals(dp(host, 48f), host.measuredHeight)

            host.addView(TextView(context).apply { text = "Subtitle" })
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(37),
                    "lines" to WireValue.Integer(2),
                    "density" to WireValue.Text("comfortable"),
                ),
            )
            host.measure(
                View.MeasureSpec.makeMeasureSpec(dp(host, 320f), View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(dp(host, 200f), View.MeasureSpec.AT_MOST),
            )
            assertEquals(dp(host, 68f), host.measuredHeight)
            host.release()
        }
    }

    @Test
    fun listItemPlacesLeadingBodyAndTrailingContentOnOneRow() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> Unit }
            val inset = dp(host, 16f)
            host.setPadding(inset, 0, inset, 0)
            val leading = View(context).apply {
                layoutParams = ViewGroup.LayoutParams(dp(host, 24f), dp(host, 24f))
            }
            val title = TextView(context).apply { text = "Actions" }
            val subtitle = TextView(context).apply { text = "Buttons and chips" }
            val trailing = View(context).apply {
                layoutParams = ViewGroup.LayoutParams(dp(host, 24f), dp(host, 24f))
            }
            host.addView(leading)
            host.addView(title)
            host.addView(subtitle)
            host.addView(trailing)
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(37),
                    "lines" to WireValue.Integer(2),
                ),
            )
            host.measure(
                View.MeasureSpec.makeMeasureSpec(dp(host, 320f), View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(dp(host, 200f), View.MeasureSpec.AT_MOST),
            )
            host.layout(0, 0, host.measuredWidth, host.measuredHeight)

            assertEquals(inset, leading.left)
            assertTrue(title.left > leading.right)
            assertEquals(title.left, subtitle.left)
            assertTrue(title.right < trailing.left)
            assertTrue(subtitle.right < trailing.left)
            assertTrue(subtitle.top >= title.bottom)
            assertEquals(host.measuredWidth - inset, trailing.right)
            assertEquals(leading.top, trailing.top)
            host.release()
        }
    }

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
            assertEquals(
                dp(grid, 102f).toDouble(),
                grid.getChildAt(1).left.toDouble(),
                1.0,
            )
            assertEquals(dp(grid, 306f), grid.getChildAt(2).left)
            assertEquals(0, grid.getChildAt(3).left)
            assertEquals(dp(grid, 306f), grid.getChildAt(4).left)
            assertEquals(
                dp(grid, 68f).toDouble(),
                grid.getChildAt(3).top.toDouble(),
                1.0,
            )
            val info = AccessibilityNodeInfo.obtain()
            grid.onInitializeAccessibilityNodeInfo(info)
            assertEquals(2, info.collectionInfo.rowCount)
            assertEquals(4, info.collectionInfo.columnCount)
            info.recycle()
        }
    }

    @Test
    fun horizontalScrollAppliesNativeInteractionAndViewportProperties() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        lateinit var scroll: MobileUiHorizontalScrollView
        onMain {
            val context = activity
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

            scroll = view as MobileUiHorizontalScrollView
            scroll.addView(
                FrameLayout(context),
                ViewGroup.LayoutParams(800, 80),
            )
            val root = FrameLayout(context)
            root.addView(
                scroll,
                FrameLayout.LayoutParams(320, 80),
            )
            activity.setContentView(root)
        }
        instrumentation.waitForIdleSync()
        awaitNextFrame(scroll)
        onMain {
            assertTrue(!scroll.isEnabled)
            assertTrue(scroll.isHorizontalScrollBarEnabled)
            assertTrue(!scroll.isFillViewport)
            assertTrue(!scroll.isNestedScrollingEnabled)
            assertEquals(View.OVER_SCROLL_NEVER, scroll.overScrollMode)
            assertEquals(dp(scroll, 24f), scroll.scrollX)
            scroll.release()
            activity.finish()
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
    fun sliderDrawsExpressiveTrackPillHandleAndMaterialGapInOneNativePass() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> }
            val primary = 0xff166534.toInt()
            val inactive = 0xffdfe5e0.toInt()
            val properties = mapOf(
                "behavior" to WireValue.Integer(5),
                "value" to WireValue.Decimal(50.0),
                "min" to WireValue.Decimal(0.0),
                "max" to WireValue.Decimal(100.0),
                "trackThickness" to WireValue.Decimal(16.0),
                "thumbWidth" to WireValue.Decimal(4.0),
                "thumbHeight" to WireValue.Decimal(44.0),
                "thumbTrackGap" to WireValue.Decimal(6.0),
                "trackColor" to WireValue.Integer(inactive.toLong()),
                "fillColor" to WireValue.Integer(primary.toLong()),
                "thumbColor" to WireValue.Integer(primary.toLong()),
            )
            host.update(properties)

            val width = dp(host, 320f)
            val height = dp(host, 96f)
            val trackHeight = dp(host, 16f)
            val thumbWidth = dp(host, 4f)
            val thumbHeight = dp(host, 44f)
            val track = FrameLayout(context).apply {
                tag = "pam:slider-track"
                layoutParams = FrameLayout.LayoutParams(width, trackHeight).apply {
                    topMargin = (height - trackHeight) / 2
                }
            }
            track.addView(View(context).apply {
                tag = "pam:slider-filled-track"
                layoutParams = FrameLayout.LayoutParams(width, trackHeight)
            })
            val thumb = View(context).apply {
                tag = "pam:slider-thumb"
                layoutParams = FrameLayout.LayoutParams(thumbWidth, thumbHeight).apply {
                    topMargin = (height - thumbHeight) / 2
                }
            }
            host.addView(track)
            host.addView(thumb)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)
            host.update(properties)

            val rendered = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
            host.draw(Canvas(rendered))
            val centerX = width / 2
            val centerY = height / 2

            assertEquals(View.INVISIBLE, track.visibility)
            assertEquals(View.INVISIBLE, thumb.visibility)
            assertEquals(primary, rendered.getPixel(width / 4, centerY))
            assertEquals(inactive, rendered.getPixel(width * 3 / 4, centerY))
            assertEquals(primary, rendered.getPixel(centerX, centerY))
            assertEquals(primary, rendered.getPixel(centerX, centerY - dp(host, 18f)))
            assertEquals(primary, rendered.getPixel(centerX, centerY + dp(host, 18f)))
            val gapPixel = rendered.getPixel(centerX + dp(host, 5f), centerY)
            assertEquals(Color.TRANSPARENT, gapPixel)

            host.update(properties + mapOf(
                "value" to WireValue.Decimal(20.0),
                "step" to WireValue.Decimal(10.0),
            ))
            host.update(properties + mapOf(
                "value" to WireValue.Decimal(25.0),
                "reversed" to WireValue.Flag(true),
            ))
            rendered.eraseColor(Color.TRANSPARENT)
            host.draw(Canvas(rendered))
            val reversedHandleX = (width * 0.75f).roundToInt()
            assertEquals(primary, rendered.getPixel(reversedHandleX, centerY))
            assertEquals(
                Color.TRANSPARENT,
                rendered.getPixel(reversedHandleX - dp(host, 5f), centerY),
            )

            host.release()
        }
    }

    @Test
    fun rangeSliderKeepsBothEndpointsAndEmitsTypedPairPayloads() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val events = CopyOnWriteArrayList<NativeViewEventKind>()
            val payloads = CopyOnWriteArrayList<String>()
            val host = MobileUiHost(context) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE || kind == NativeViewEventKind.NATIVE) {
                    events += kind
                    payloads += payload.decodeToString()
                }
            }
            val width = dp(host, 320f)
            val height = dp(host, 48f)
            val trackHeight = dp(host, 16f)
            val properties = mapOf(
                "behavior" to WireValue.Integer(5),
                "range" to WireValue.Flag(true),
                "lowerValue" to WireValue.Decimal(20.0),
                "upperValue" to WireValue.Decimal(80.0),
                "value" to WireValue.Decimal(80.0),
                "min" to WireValue.Decimal(0.0),
                "max" to WireValue.Decimal(100.0),
                "trackThickness" to WireValue.Decimal(16.0),
                "thumbWidth" to WireValue.Decimal(4.0),
                "thumbHeight" to WireValue.Decimal(44.0),
                "thumbTrackGap" to WireValue.Decimal(6.0),
            )
            host.update(properties)
            val track = FrameLayout(context).apply {
                tag = "pam:slider-track"
                layoutParams = FrameLayout.LayoutParams(width, trackHeight).apply {
                    topMargin = (height - trackHeight) / 2
                }
            }
            track.addView(View(context).apply {
                tag = "pam:slider-filled-track"
                layoutParams = FrameLayout.LayoutParams(width, trackHeight)
            })
            host.addView(track)
            host.addView(View(context).apply {
                tag = "pam:slider-thumb"
                layoutParams = FrameLayout.LayoutParams(dp(host, 4f), dp(host, 44f)).apply {
                    topMargin = (height - dp(host, 44f)) / 2
                }
            })
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)
            host.update(properties)

            val parentInterceptRequests = CopyOnWriteArrayList<Boolean>()
            val trackingParent = object : FrameLayout(context) {
                override fun requestDisallowInterceptTouchEvent(
                    disallowIntercept: Boolean,
                ) {
                    parentInterceptRequests += disallowIntercept
                    super.requestDisallowInterceptTouchEvent(disallowIntercept)
                }
            }
            trackingParent.addView(host)

            host.update(properties + mapOf(
                "lowerValue" to WireValue.Decimal(80.0),
                "upperValue" to WireValue.Decimal(20.0),
                "value" to WireValue.Decimal(20.0),
            ))
            assertStateDescription(host, "20 to 80")
            host.update(properties)

            val outsideTrackY = -1f
            host.dispatchTouchEvent(
                motion(MotionEvent.ACTION_DOWN, width * 0.20f, outsideTrackY),
            )
            host.dispatchTouchEvent(
                motion(MotionEvent.ACTION_MOVE, width * 0.40f, outsideTrackY),
            )
            host.dispatchTouchEvent(
                motion(MotionEvent.ACTION_UP, width * 0.40f, outsideTrackY),
            )
            assertTrue(events.isEmpty())
            assertStateDescription(host, "20 to 80")
            parentInterceptRequests.clear()

            val y = height / 2f
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, width * 0.20f, y))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, width * 0.35f, y))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, width * 0.35f, y))

            assertTrue(parentInterceptRequests.first())
            assertTrue(!parentInterceptRequests.last())
            assertEquals(
                listOf(NativeViewEventKind.CHANGE, NativeViewEventKind.NATIVE),
                events,
            )
            assertEquals(listOf("[35,80]", "[35,80]"), payloads)
            assertStateDescription(host, "35 to 80")

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            val lowerIncrease = info.actionList.single {
                it.label?.toString() == "Increase lower value"
            }
            val upperDecrease = info.actionList.single {
                it.label?.toString() == "Decrease upper value"
            }
            assertTrue(host.performAccessibilityAction(lowerIncrease.id, null))
            assertEquals("[36,80]", payloads.last())
            assertTrue(host.performAccessibilityAction(upperDecrease.id, null))
            assertEquals("[36,79]", payloads.last())
            assertStateDescription(host, "36 to 79")

            host.update(properties + mapOf(
                "lowerValue" to WireValue.Decimal(35.0),
                "upperValue" to WireValue.Decimal(65.0),
                "value" to WireValue.Decimal(65.0),
            ))
            assertStateDescription(host, "35 to 65")
            events.clear()
            payloads.clear()
            assertTrue(
                host.dispatchKeyEvent(
                    KeyEvent(KeyEvent.ACTION_DOWN, KeyEvent.KEYCODE_DPAD_RIGHT),
                ),
            )
            assertEquals(
                listOf(NativeViewEventKind.CHANGE, NativeViewEventKind.NATIVE),
                events,
            )
            assertEquals(listOf("[35,66]", "[35,66]"), payloads)

            events.clear()
            payloads.clear()
            host.update(properties + mapOf(
                "lowerValue" to WireValue.Decimal(50.0),
                "upperValue" to WireValue.Decimal(50.0),
                "value" to WireValue.Decimal(50.0),
            ))
            assertStateDescription(host, "50 to 50")
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, width * 0.50f, y))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, width * 0.40f, y))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, width * 0.40f, y))
            assertEquals(
                listOf(NativeViewEventKind.CHANGE, NativeViewEventKind.NATIVE),
                events,
            )
            assertEquals(listOf("[40,50]", "[40,50]"), payloads)
            assertStateDescription(host, "40 to 50")

            events.clear()
            payloads.clear()
            host.update(properties + ("readOnly" to WireValue.Flag(true)))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, width * 0.20f, y))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, width * 0.45f, y))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, width * 0.45f, y))
            assertTrue(events.isEmpty())
            assertStateDescription(host, "20 to 80")
            val readOnlyInfo = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(readOnlyInfo)
            assertTrue(
                readOnlyInfo.actionList.none {
                    it.id == AccessibilityNodeInfo.ACTION_SCROLL_FORWARD
                        || it.label?.toString() == "Increase lower value"
                        || it.label?.toString() == "Decrease upper value"
                },
            )
            readOnlyInfo.recycle()

            host.update(properties + mapOf(
                "showTicks" to WireValue.Flag(true),
                "alwaysShowTicks" to WireValue.Flag(true),
                "tickLabels" to WireValue.Text("[\"0\",\"25\",\"50\",\"75\",\"100\"]"),
                "tickLabelColor" to WireValue.Integer(0xff334155),
            ))
            val labelledHeight = dp(host, 72f)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(labelledHeight, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, labelledHeight)
            val labelled = Bitmap.createBitmap(
                width,
                labelledHeight,
                Bitmap.Config.ARGB_8888,
            )
            host.draw(Canvas(labelled))
            var tickLabelPixels = 0
            for (x in 0 until width) {
                for (labelY in dp(host, 50f) until labelledHeight) {
                    if (Color.alpha(labelled.getPixel(x, labelY)) > 0) {
                        tickLabelPixels += 1
                    }
                }
            }
            assertTrue(tickLabelPixels > 40)

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(5),
                    "range" to WireValue.Flag(true),
                    "min" to WireValue.Decimal(0.0),
                    "max" to WireValue.Decimal(100.0),
                ),
            )
            assertStateDescription(host, "0 to 100")
            info.recycle()
            host.release()
        }
    }

    @Test
    fun rangeSliderClampsStaleTrackGeometryInsideAdaptiveHost() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> Unit }
            val width = dp(host, 320f)
            val height = dp(host, 48f)
            val trackHeight = dp(host, 16f)
            val oversizedWidth = dp(host, 520f)
            val properties = mapOf(
                "behavior" to WireValue.Integer(5),
                "range" to WireValue.Flag(true),
                "lowerValue" to WireValue.Decimal(20.0),
                "upperValue" to WireValue.Decimal(80.0),
                "value" to WireValue.Decimal(80.0),
                "min" to WireValue.Decimal(0.0),
                "max" to WireValue.Decimal(100.0),
                "trackThickness" to WireValue.Decimal(16.0),
                "thumbWidth" to WireValue.Decimal(4.0),
                "thumbHeight" to WireValue.Decimal(44.0),
                "thumbTrackGap" to WireValue.Decimal(6.0),
                "primaryColor" to WireValue.Integer(0xff146c2e),
                "trackColor" to WireValue.Integer(0xffdce5dd),
                "thumbColor" to WireValue.Integer(0xff146c2e),
            )
            host.update(properties)
            val track = FrameLayout(context).apply {
                tag = "pam:slider-track"
                layoutParams = FrameLayout.LayoutParams(oversizedWidth, trackHeight).apply {
                    leftMargin = -dp(host, 100f)
                    topMargin = (height - trackHeight) / 2
                }
                addView(View(context).apply {
                    tag = "pam:slider-filled-track"
                    layoutParams = FrameLayout.LayoutParams(oversizedWidth, trackHeight)
                })
            }
            host.addView(track)
            host.addView(View(context).apply {
                tag = "pam:slider-thumb"
                layoutParams = FrameLayout.LayoutParams(dp(host, 4f), dp(host, 44f)).apply {
                    topMargin = (height - dp(host, 44f)) / 2
                }
            })
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)
            track.layout(
                -dp(host, 100f),
                (height - trackHeight) / 2,
                width + dp(host, 100f),
                (height + trackHeight) / 2,
            )
            host.update(properties)

            val rendered = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
            host.draw(Canvas(rendered))
            val primary = 0xff146c2e.toInt()
            val expectedInset = dp(host, 8f)
            val usableWidth = width - expectedInset * 2
            val lowerX = expectedInset + (usableWidth * 0.20f).roundToInt()
            val upperX = expectedInset + (usableWidth * 0.80f).roundToInt()

            assertEquals(primary, rendered.getPixel(lowerX, dp(host, 4f)))
            assertEquals(primary, rendered.getPixel(upperX, dp(host, 4f)))
            host.release()
        }
    }

    @Test
    fun rangeSliderHundredTickDrawPathDoesNotAllocatePerFrame() {
        onMain {
            val context = ApplicationProvider.getApplicationContext<android.content.Context>()
            val host = MobileUiHost(context) { _, _ -> Unit }
            val width = dp(host, 320f)
            val height = dp(host, 80f)
            val trackHeight = dp(host, 16f)
            val properties = mapOf(
                "behavior" to WireValue.Integer(5),
                "range" to WireValue.Flag(true),
                "lowerValue" to WireValue.Decimal(20.0),
                "upperValue" to WireValue.Decimal(80.0),
                "value" to WireValue.Decimal(80.0),
                "min" to WireValue.Decimal(0.0),
                "max" to WireValue.Decimal(100.0),
                "step" to WireValue.Decimal(1.0),
                "showTicks" to WireValue.Flag(true),
                "alwaysShowTicks" to WireValue.Flag(true),
                "trackThickness" to WireValue.Decimal(16.0),
                "thumbWidth" to WireValue.Decimal(4.0),
                "thumbHeight" to WireValue.Decimal(44.0),
                "thumbTrackGap" to WireValue.Decimal(6.0),
            )
            host.update(properties)
            val track = FrameLayout(context).apply {
                tag = "pam:slider-track"
                layoutParams = FrameLayout.LayoutParams(width, trackHeight).apply {
                    topMargin = (height - trackHeight) / 2
                }
            }
            track.addView(View(context).apply {
                tag = "pam:slider-filled-track"
                layoutParams = FrameLayout.LayoutParams(width, trackHeight)
            })
            host.addView(track)
            host.addView(View(context).apply {
                tag = "pam:slider-thumb"
                layoutParams = FrameLayout.LayoutParams(dp(host, 4f), dp(host, 44f)).apply {
                    topMargin = (height - dp(host, 44f)) / 2
                }
            })
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)
            host.update(properties)

            val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
            val canvas = Canvas(bitmap)
            repeat(12) { host.draw(canvas) }
            Debug.startAllocCounting()
            val allocationsBefore = Debug.getThreadAllocCount()
            repeat(120) { host.draw(canvas) }
            val allocations = Debug.getThreadAllocCount() - allocationsBefore
            Debug.stopAllocCounting()
            Log.i(
                "PamMobileUiBench",
                "rangeSliderDrawAllocations=$allocations frames=120 ticks=100",
            )

            assertTrue(
                "Range Slider allocated $allocations objects while drawing 120 warmed frames",
                allocations < 120,
            )
            host.release()
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
                    "behavior" to WireValue.Integer(9),
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
            assertStateDescription(host, "Read only")
            readOnlyInfo.recycle()

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(9),
                    "isIndeterminate" to WireValue.Flag(true),
                ),
            )
            assertEquals(View.VISIBLE, icon.visibility)

            val info = AccessibilityNodeInfo.obtain()
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals("android.widget.CheckBox", info.className)
            assertEquals("Receive updates", host.contentDescription)
            assertStateDescription(host, "Mixed")
            assertTrue(info.isCheckable)
            assertTrue(!info.isChecked)

            assertTrue(host.performClick())
            assertEquals(listOf(NativeViewEventKind.TOGGLE), events)
            host.onInitializeAccessibilityNodeInfo(info)
            assertTrue(info.isChecked)
            assertStateDescription(host, null)
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
                    "behavior" to WireValue.Integer(12),
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
            assertStateDescription(progress, "25%")

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
                    "behavior" to WireValue.Integer(22),
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
            assertStateDescription(host, "On")

            assertTrue(host.performClick())
            host.onInitializeAccessibilityNodeInfo(info)
            assertEquals(listOf("0"), payloads.map(ByteArray::decodeToString))
            assertTrue(!info.isChecked)
            assertStateDescription(host, "Off")

            assertTrue(
                host.dispatchKeyEvent(
                    KeyEvent(KeyEvent.ACTION_UP, KeyEvent.KEYCODE_SPACE),
                ),
            )
            assertEquals(listOf("0", "1"), payloads.map(ByteArray::decodeToString))

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(22),
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
            assertEquals(
                (2f * host.resources.displayMetrics.density).roundToInt(),
                indicator.layoutParams.height,
            )

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
    fun carouselShowsOnlyTheSelectedNativePage() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { kind, payload ->
                if (kind == NativeViewEventKind.CHANGE) payloads += payload
            }
            val overview = tabTrigger(host, "overview", selected = true)
            val details = tabTrigger(host, "details")
            host.addView(overview)
            host.addView(details)
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(6),
                    "navigationKind" to WireValue.Integer(1),
                    "value" to WireValue.Text("overview"),
                    "continuous" to WireValue.Flag(true),
                ),
            )

            assertEquals(View.VISIBLE, overview.visibility)
            assertEquals(View.GONE, details.visibility)
            assertTrue(details.performClick())
            assertEquals(View.GONE, overview.visibility)
            assertEquals(View.VISIBLE, details.visibility)
            assertEquals(listOf("details"), payloads.map(ByteArray::decodeToString))

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
            assertStateDescription(host, "Snap 1 of 3")
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
                    "behavior" to WireValue.Integer(24),
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

            selectItem.release()
            sheet.release()
            sheetInfo.recycle()
            itemInfo.recycle()
        }
    }

    @Test
    fun selectedSheetItemDrawsItsIndicatorAtTheTrailingEdge() {
        onMain {
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { _, _ -> Unit }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(24),
                    "component" to WireValue.Integer(
                        GeneratedComponents.SELECT_ITEM.toLong(),
                    ),
                    "checked" to WireValue.Flag(true),
                    "fillColor" to WireValue.Integer(Color.MAGENTA.toLong()),
                ),
            )
            val width = dp(host, 300f)
            val height = dp(host, 56f)
            host.measure(
                View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
                View.MeasureSpec.makeMeasureSpec(height, View.MeasureSpec.EXACTLY),
            )
            host.layout(0, 0, width, height)
            val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
            host.draw(Canvas(bitmap))

            fun magentaPixels(left: Int, right: Int): Int {
                var count = 0
                for (x in left.coerceAtLeast(0) until right.coerceAtMost(width)) {
                    for (y in 0 until height) {
                        val pixel = bitmap.getPixel(x, y)
                        if (
                            Color.alpha(pixel) > 0
                            && Color.red(pixel) > 180
                            && Color.blue(pixel) > 180
                            && Color.green(pixel) < 120
                        ) {
                            count++
                        }
                    }
                }
                return count
            }

            val trailing = magentaPixels(width - dp(host, 40f), width)
            val center = magentaPixels(
                width / 2 - dp(host, 24f),
                width / 2 + dp(host, 24f),
            )
            assertTrue("selection indicator must be visible at trailing", trailing > 0)
            assertEquals("selection indicator must not drift to center", 0, center)

            bitmap.recycle()
            host.release()
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
                    "behavior" to WireValue.Integer(14),
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
            close.update(mapOf("behavior" to WireValue.Integer(26)))
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
            assertStateDescription(trigger, "Expanded")

            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 20f, 700f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 20f, 700f)))
            assertTrue(!host.acceptsOverlayInteraction())
            assertStateDescription(trigger, "Collapsed")
            assertEquals(2, payloads.size)
            val opening = WireMap.decode(payloads[0])
            val dismissal = WireMap.decode(payloads[1])
            assertEquals(2L, (opening["action"] as WireValue.Integer).value)
            assertTrue((opening["open"] as WireValue.Flag).value)
            assertEquals(1L, (dismissal["action"] as WireValue.Integer).value)
            assertTrue((dismissal["dismissed"] as WireValue.Flag).value)

            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 180f, 140f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 180f, 140f)))
            assertTrue(close.performClick())
            assertEquals(1, closePresses.size)
            assertEquals(4, payloads.size)
            assertEquals(
                2L,
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
    fun controlledAnchoredOverlayEmitsOpenRequestFromItsTrigger() {
        onMain {
            val payloads = CopyOnWriteArrayList<ByteArray>()
            val host = MobileUiHost(
                ApplicationProvider.getApplicationContext(),
            ) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(15),
                    "open" to WireValue.Flag(false),
                    "openOnClick" to WireValue.Flag(true),
                ),
            )
            val trigger = View(host.context).apply {
                tag = "pam:overlay-trigger"
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:overlay-content"
            }
            host.addView(trigger)
            host.addView(content)
            host.layout(0, 0, 400, 600)
            trigger.layout(40, 40, 240, 120)
            content.layout(40, 120, 320, 360)

            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 100f, 80f)))
            assertTrue(host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 100f, 80f)))
            assertEquals(1, payloads.size)
            val request = WireMap.decode(payloads.single())
            assertEquals(2L, (request["action"] as WireValue.Integer).value)
            assertTrue((request["open"] as WireValue.Flag).value)
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
                    "behavior" to WireValue.Integer(15),
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
                    "behavior" to WireValue.Integer(25),
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
                    "behavior" to WireValue.Integer(25),
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
                    "behavior" to WireValue.Integer(14),
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
            val arrowCenter = content.x + arrow.x + arrow.width / 2f
            val triggerCenter = trigger.x + trigger.width / 2f
            assertEquals(triggerCenter, arrowCenter, dp(host, 8f).toFloat())
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
            host.update(mapOf("behavior" to WireValue.Integer(13)))
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
    fun calendarDragInsideOneCellNeverChangesSelection() {
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
                    "selectedValues" to WireValue.Text("2026-07-08\n2026-07-15"),
                ),
            )
            val grid = View(host.context).apply { tag = "pam:calendar-grid" }
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            grid.layout(0, 100, 700, 700)

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 350f, 250f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_MOVE, 390f, 250f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 390f, 250f))
            assertTrue(payloads.isEmpty())

            host.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 350f, 250f))
            host.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 350f, 250f))
            assertEquals("M\n2026-07-15", payloads.single().decodeToString())
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
            val previous = View(host.context).apply { tag = "pam:calendar-prev" }
            val month = View(host.context).apply { tag = "pam:calendar-month-select" }
            val year = View(host.context).apply { tag = "pam:calendar-year-select" }
            val next = View(host.context).apply { tag = "pam:calendar-next" }
            host.addView(previous)
            host.addView(month)
            host.addView(year)
            host.addView(next)
            host.addView(grid)
            host.layout(0, 0, 700, 700)
            previous.layout(0, 0, 100, 100)
            month.layout(100, 0, 300, 100)
            year.layout(300, 0, 500, 100)
            next.layout(600, 0, 700, 100)
            grid.layout(0, 100, 700, 700)

            val provider = host.accessibilityNodeProvider
            val previousMonth = provider?.createAccessibilityNodeInfo(1_000)
            val monthSelector = provider?.createAccessibilityNodeInfo(1_001)
            val yearSelector = provider?.createAccessibilityNodeInfo(1_002)
            val nextMonth = provider?.createAccessibilityNodeInfo(1_003)
            val july23 = provider?.createAccessibilityNodeInfo(25)
            val july24 = provider?.createAccessibilityNodeInfo(26)
            assertEquals("android.widget.Button", previousMonth?.className)
            assertEquals("Previous month", previousMonth?.contentDescription)
            assertEquals("Select month, July", monthSelector?.contentDescription)
            assertEquals("Select year, 2026", yearSelector?.contentDescription)
            assertEquals("Next month", nextMonth?.contentDescription)
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
            previousMonth?.recycle()
            monthSelector?.recycle()
            yearSelector?.recycle()
            nextMonth?.recycle()
            host.release()
        }
    }

    @Test
    fun calendarWeekNumberColumnNeverStealsDaySelectionInLtrOrRtl() {
        onMain {
            fun calendar(layoutDirectionValue: Int): Pair<MobileUiHost, CopyOnWriteArrayList<ByteArray>> {
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
                        "showWeek" to WireValue.Flag(true),
                        "rtl" to WireValue.Flag(
                            layoutDirectionValue == View.LAYOUT_DIRECTION_RTL,
                        ),
                    ),
                )
                host.addView(View(host.context).apply { tag = "pam:calendar-grid" })
                host.layout(0, 0, 800, 700)
                host.getChildAt(0).layout(0, 100, 800, 700)
                return host to payloads
            }

            val (ltr, ltrPayloads) = calendar(View.LAYOUT_DIRECTION_LTR)
            val ltrProvider = ltr.accessibilityNodeProvider
            val ltrFirstDay = ltrProvider?.createAccessibilityNodeInfo(0)
            val ltrWeek = ltrProvider?.createAccessibilityNodeInfo(1_100)
            val ltrBounds = android.graphics.Rect()
            ltrFirstDay?.getBoundsInParent(ltrBounds)
            assertEquals(100, ltrBounds.left)
            assertEquals(200, ltrBounds.right)
            assertEquals("android.widget.TextView", ltrWeek?.className)
            assertEquals("Week 27", ltrWeek?.contentDescription)
            assertTrue(ltrWeek?.isClickable == false)
            ltr.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 50f, 150f))
            ltr.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 50f, 150f))
            assertTrue(ltrPayloads.isEmpty())
            ltr.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 150f, 150f))
            ltr.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 150f, 150f))
            assertEquals("2026-06-28", ltrPayloads.single().decodeToString())
            ltrFirstDay?.recycle()
            ltrWeek?.recycle()
            ltr.release()

            val (rtl, rtlPayloads) = calendar(View.LAYOUT_DIRECTION_RTL)
            val rtlProvider = rtl.accessibilityNodeProvider
            val rtlFirstDay = rtlProvider?.createAccessibilityNodeInfo(0)
            val rtlWeek = rtlProvider?.createAccessibilityNodeInfo(1_100)
            val rtlBounds = android.graphics.Rect()
            rtlFirstDay?.getBoundsInParent(rtlBounds)
            assertEquals(600, rtlBounds.left)
            assertEquals(700, rtlBounds.right)
            val rtlWeekBounds = android.graphics.Rect()
            rtlWeek?.getBoundsInParent(rtlWeekBounds)
            assertEquals(700, rtlWeekBounds.left)
            assertEquals(800, rtlWeekBounds.right)
            rtl.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 750f, 150f))
            rtl.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 750f, 150f))
            assertTrue(rtlPayloads.isEmpty())
            rtl.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 650f, 150f))
            rtl.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 650f, 150f))
            assertEquals("2026-06-28", rtlPayloads.single().decodeToString())
            rtlFirstDay?.recycle()
            rtlWeek?.recycle()
            rtl.release()
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
            assertEquals(3L, (navigation["action"] as WireValue.Integer).value)
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
            host.update(mapOf("behavior" to WireValue.Integer(13)))

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
                    "behavior" to WireValue.Integer(17),
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
    fun dateTimePickerEmitsDismissalFromAWrappedActivityContext() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = instrumentation.startActivitySync(
            Intent(
                instrumentation.targetContext,
                TestHostActivity::class.java,
            ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
        ) as TestHostActivity
        instrumentation.waitForIdleSync()
        val payloads = CopyOnWriteArrayList<ByteArray>()
        val dismissed = CountDownLatch(1)
        lateinit var host: MobileUiHost
        onMain {
            host = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) {
                    payloads += payload
                    dismissed.countDown()
                }
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(17),
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
        onMain {
            assertTrue("date picker was not active", host.cancelActivePicker())
        }
        assertTrue(
            "date picker did not emit dismissal after cancel",
            dismissed.await(5, TimeUnit.SECONDS),
        )

        val dismissal = WireMap.decode(payloads.single())
        assertEquals(1L, (dismissal["action"] as WireValue.Integer).value)
        assertTrue((dismissal["dismissed"] as WireValue.Flag).value)
        onMain {
            host.release()
            activity.finish()
        }
    }

    @Test
    fun readOnlyDateTimePickerDoesNotOpenTheSystemDialog() {
        val activity = launchTestHostActivity()
        lateinit var host: MobileUiHost
        onMain {
            host = MobileUiHost(activity) { _, _ -> }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(17),
                    "mode" to WireValue.Integer(4),
                    "value" to WireValue.Text("2026-07-23"),
                    "readOnly" to WireValue.Flag(true),
                ),
            )
            activity.setContentView(host)

            host.performClick()

            assertFalse("read-only picker opened a system dialog", host.cancelActivePicker())
            host.release()
            activity.finish()
        }
    }

    @Test
    fun dateTimePickerReleaseDismissesTheSystemDialogSilently() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        val payloads = CopyOnWriteArrayList<ByteArray>()
        onMain {
            val host = MobileUiHost(activity) { kind, payload ->
                if (kind == NativeViewEventKind.NATIVE) payloads += payload
            }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(17),
                    "mode" to WireValue.Integer(4),
                    "value" to WireValue.Text("2026-07-23"),
                ),
            )
            activity.setContentView(host)
            assertTrue(host.performClick())
            host.release()
        }

        instrumentation.waitForIdleSync()
        assertTrue(payloads.isEmpty())
        onMain { activity.finish() }
    }

    @Test
    fun accordionAppliesInitialCollapsedStateWhenBehaviorIsInstalled() {
        onMain {
            val host = MobileUiHost(ApplicationProvider.getApplicationContext()) { _, _ -> }
            val trigger = FrameLayout(host.context).apply {
                tag = "pam:accordion-trigger"
            }
            val content = FrameLayout(host.context).apply {
                tag = "pam:accordion-content"
                visibility = View.VISIBLE
                alpha = 1f
                scaleY = 1f
            }
            host.addView(trigger)
            host.addView(content)

            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(2),
                    "expanded" to WireValue.Flag(false),
                ),
            )

            assertEquals(View.VISIBLE, trigger.visibility)
            assertEquals(View.GONE, content.visibility)
            assertEquals(0f, content.alpha, 0f)
            assertEquals(0.98f, content.scaleY, 0f)
            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS,
                content.importantForAccessibility,
            )
            host.release()
        }
    }

    @Test
    fun accordionReconcilesCollapsedContentAfterIncrementalMount() {
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        lateinit var host: MobileUiHost
        lateinit var content: FrameLayout

        onMain {
            host = MobileUiHost(activity) { _, _ -> }
            host.update(
                mapOf(
                    "behavior" to WireValue.Integer(2),
                    "expanded" to WireValue.Flag(false),
                ),
            )
            activity.setContentView(host)
        }
        instrumentation.waitForIdleSync()

        onMain {
            host.addView(FrameLayout(host.context).apply {
                tag = "pam:accordion-trigger"
            })
            content = FrameLayout(host.context).apply {
                tag = "pam:accordion-content"
            }
            host.addView(content)

            // Simulate the renderer applying the child's own properties after
            // the parent receives onViewAdded during incremental mounting.
            content.visibility = View.VISIBLE
            content.alpha = 1f
            content.scaleY = 1f
        }
        instrumentation.waitForIdleSync()

        onMain {
            assertEquals(View.GONE, content.visibility)
            assertEquals(0f, content.alpha, 0f)
            assertEquals(0.98f, content.scaleY, 0f)
            assertEquals(
                View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS,
                content.importantForAccessibility,
            )
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
            assertStateDescription(host, "Expanded")
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
                    "behavior" to WireValue.Integer(19),
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
            assertStateDescription(first, "Expanded")

            assertTrue(
                second.performAccessibilityAction(
                    AccessibilityNodeInfo.ACTION_EXPAND,
                    null,
                ),
            )
            assertEquals(listOf("1"), payloads.map(ByteArray::decodeToString))
            assertStateDescription(first, "Collapsed")
            assertStateDescription(second, "Expanded")

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
                    "behavior" to WireValue.Integer(10),
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
            group.update(mapOf("behavior" to WireValue.Integer(21)))
            val wrapper = FrameLayout(group.context)
            val first = MobileUiHost(group.context) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            first.update(
                mapOf(
                    "behavior" to WireValue.Integer(10),
                    "checked" to WireValue.Flag(true),
                ),
            )
            val second = MobileUiHost(group.context) { kind, payload ->
                if (kind == NativeViewEventKind.TOGGLE) payloads += payload
            }
            second.update(
                mapOf(
                    "behavior" to WireValue.Integer(10),
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
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        val activity = launchTestHostActivity()
        val inputEvents = CopyOnWriteArrayList<NativeViewEventKind>()
        lateinit var root: FrameLayout
        lateinit var inputGroup: MobileUiHost
        lateinit var inputLabel: TextView
        lateinit var input: EditText
        lateinit var clear: MobileUiHost
        lateinit var password: MobileUiHost
        lateinit var form: MobileUiHost
        lateinit var formInput: EditText
        lateinit var error: FrameLayout
        onMain {
            val context = activity
            root = FrameLayout(context)
            inputGroup = MobileUiHost(context) { _, _ -> }
            inputGroup.update(
                mapOf(
                    "behavior" to WireValue.Integer(27),
                    "focusColor" to WireValue.Integer(0xff2563eb),
                    "invalidColor" to WireValue.Integer(0xffdc2626),
                ),
            )
            input = EditText(context).apply {
                setText("secret")
                transformationMethod = PasswordTransformationMethod.getInstance()
            }
            inputLabel = TextView(context).apply { text = "Password" }
            clear = MobileUiHost(context) { kind, _ -> inputEvents += kind }
            clear.update(
                mapOf(
                    "behavior" to WireValue.Integer(28),
                    "slotAction" to WireValue.Integer(2),
                ),
            )
            password = MobileUiHost(context) { kind, _ -> inputEvents += kind }
            password.update(
                mapOf(
                    "behavior" to WireValue.Integer(28),
                    "slotAction" to WireValue.Integer(3),
                ),
            )
            inputGroup.addView(inputLabel)
            inputGroup.addView(input)
            inputGroup.addView(clear)
            inputGroup.addView(password)
            root.addView(inputGroup)
            activity.setContentView(root)
        }
        instrumentation.waitForIdleSync()
        onMain {
            val context = activity
            inputGroup.layout(0, 0, 600, 120)
            inputLabel.layout(0, 0, 600, 40)
            input.layout(0, 40, 600, 100)
            clear.layout(500, 40, 550, 100)
            password.layout(550, 40, 600, 100)

            assertTrue(inputGroup.performClick())
            assertTrue(input.hasFocus())
            input.clearFocus()
            inputGroup.dispatchTouchEvent(motion(MotionEvent.ACTION_DOWN, 20f, 20f))
            inputGroup.dispatchTouchEvent(motion(MotionEvent.ACTION_UP, 20f, 20f))
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
                    "behavior" to WireValue.Integer(27),
                    "readOnly" to WireValue.Flag(true),
                ),
            )
            inputGroup.layout(0, 0, 601, 120)
            assertTrue(input.isEnabled)
            assertTrue(input.keyListener == null)

            form = MobileUiHost(context) { _, _ -> }
            form.update(
                mapOf(
                    "behavior" to WireValue.Integer(29),
                    "required" to WireValue.Flag(true),
                    "invalid" to WireValue.Flag(true),
                ),
            )
            val label = FrameLayout(context).apply {
                tag = "pam:form-label"
                addView(TextView(context).apply { text = "Email" })
            }
            formInput = EditText(context)
            val field = FrameLayout(context).apply { addView(formInput) }
            val helper = FrameLayout(context).apply {
                tag = "pam:form-helper"
                addView(TextView(context).apply { text = "Use your work email." })
            }
            error = FrameLayout(context).apply {
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
        }
        instrumentation.waitForIdleSync()
        onMain {
            assertTrue(formInput.width > 0)
            assertTrue(formInput.height > 0)

            val info = formInput.createAccessibilityNodeInfo()
            assertEquals("Email", formInput.contentDescription)
            assertEquals("Use your work email.", formInput.tooltipText)
            assertTrue(info.isContentInvalid)
            assertEquals("Email is invalid.", info.error)
            assertStateDescription(info, "Required, Invalid")
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
            table.update(mapOf("behavior" to WireValue.Integer(30)))
            val headerWrapper = FrameLayout(context)
            val header = MobileUiHost(context) { _, _ -> }
            header.update(
                mapOf(
                    "behavior" to WireValue.Integer(31),
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
            body.update(mapOf("behavior" to WireValue.Integer(31)))
            val packageCell = TextView(context).apply { text = "pushinbr/pam-native-ui" }
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
            assertTrue(headerCellInfo.collectionItemInfo?.isHeading == true)
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
                        GeneratedComponents.SKELETON.toLong(),
                    ),
                    "pulseDuration" to WireValue.Integer(2_000),
                    "lines" to WireValue.Integer(3),
                ),
            )
            toast = MobileUiHost(context) { _, _ -> }
            toast.update(
                mapOf(
                    "behavior" to WireValue.Integer(11),
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
                    "behavior" to WireValue.Integer(23),
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

    private fun awaitNextFrame(view: View) {
        val frame = CountDownLatch(1)
        onMain {
            view.postOnAnimation(frame::countDown)
        }
        assertTrue(
            "Android did not produce the next UI frame.",
            frame.await(5, TimeUnit.SECONDS),
        )
    }

    private fun onMain(block: () -> Unit) {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(block)
    }

    private fun assertStateDescription(view: View, expected: CharSequence?) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            assertEquals(expected, view.stateDescription)
        }
    }

    private fun assertStateDescription(
        info: AccessibilityNodeInfo,
        expected: CharSequence?,
    ) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            assertEquals(expected, info.stateDescription)
        }
    }
}
