package dev.pam.mobileui

import android.annotation.SuppressLint
import android.content.Context
import android.view.View
import android.view.ViewGroup
import android.view.accessibility.AccessibilityNodeInfo
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEmitter
import dev.pam.nativeapp.views.NativeViewFactoryV2
import kotlin.math.floor
import kotlin.math.max

class MobileUiGridFactory(
    @Suppress("UNUSED_PARAMETER") context: Context,
) : NativeViewFactoryV2 {
    override fun create(
        context: Context,
        @Suppress("UNUSED_PARAMETER") emitter: NativeViewEmitter,
    ): View = MobileUiGridView(context)

    override fun update(
        view: View,
        properties: Map<String, WireValue>,
    ) {
        require(view is MobileUiGridView) {
            "pam.mobile_ui.grid requires MobileUiGridView"
        }
        view.update(properties)
    }
}

@SuppressLint("ViewConstructor")
internal class MobileUiGridView(
    context: Context,
) : ViewGroup(context) {
    private val density = resources.displayMetrics.density
    private var columns = IntArray(GridSpec.RESPONSIVE_SLOTS) {
        GridSpec.DEFAULT_COLUMNS
    }
    private var columnGaps = FloatArray(GridSpec.RESPONSIVE_SLOTS)
    private var rowGaps = FloatArray(GridSpec.RESPONSIVE_SLOTS)
    private var direction = GridDirection.ROW
    private var lastPlan = GridPlan(emptyList(), 0, GridSpec.DEFAULT_COLUMNS)

    init {
        clipChildren = false
        clipToPadding = false
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_YES
    }

    fun update(properties: Map<String, WireValue>) {
        val nextColumns = GridSpec.columns(
            properties.text("columns"),
            columns,
        )
        val nextColumnGaps = GridSpec.gaps(
            properties.text("columnGaps"),
            columnGaps,
            density,
        )
        val nextRowGaps = GridSpec.gaps(
            properties.text("rowGaps"),
            rowGaps,
            density,
        )
        val nextDirection = GridDirection.from(
            properties.integer("direction", direction.value),
        )
        if (
            nextColumns.contentEquals(columns)
            && nextColumnGaps.contentEquals(columnGaps)
            && nextRowGaps.contentEquals(rowGaps)
            && nextDirection == direction
        ) {
            return
        }
        columns = nextColumns
        columnGaps = nextColumnGaps
        rowGaps = nextRowGaps
        direction = nextDirection
        requestLayout()
    }

    override fun onMeasure(widthMeasureSpec: Int, heightMeasureSpec: Int) {
        val width = MeasureSpec.getSize(widthMeasureSpec)
        val contentWidth = (width - paddingLeft - paddingRight).coerceAtLeast(0)
        val visibleChildren = visibleChildren()
        val responsiveIndex = GridSpec.breakpointIndex(
            resources.configuration.screenWidthDp,
        )
        val activeColumns = columns[responsiveIndex].coerceAtLeast(1)
        val spans = visibleChildren.map { child ->
            GridSpec.span(child.tag, responsiveIndex, activeColumns)
        }
        val widthPlan = GridPlanner.plan(
            contentWidth,
            activeColumns,
            columnGaps[responsiveIndex].rounded(),
            rowGaps[responsiveIndex].toInt(),
            spans,
            List(visibleChildren.size) { 1 },
            direction,
        )
        val childHeights = visibleChildren.mapIndexed { index, child ->
            val plannedWidth = widthPlan.items[index].bounds.width
            constrainCellContent(child, plannedWidth)
            child.measure(
                MeasureSpec.makeMeasureSpec(plannedWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(0, MeasureSpec.UNSPECIFIED),
            )
            val content = (child as? ViewGroup)?.getChildAt(0)
            val contentHeight = max(
                content?.measuredHeight ?: 0,
                content?.layoutParams?.height ?: 0,
            )
            max(
                max(child.measuredHeight, contentHeight),
                max(child.layoutParams?.height ?: 0, child.minimumHeight),
            ).coerceAtLeast(1)
        }
        lastPlan = GridPlanner.plan(
            contentWidth,
            activeColumns,
            columnGaps[responsiveIndex].rounded(),
            rowGaps[responsiveIndex].toInt(),
            spans,
            childHeights,
            direction,
        )
        lastPlan.items.forEachIndexed { index, item ->
            val child = visibleChildren[index]
            child.measure(
                MeasureSpec.makeMeasureSpec(item.bounds.width, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(
                    item.bounds.height,
                    MeasureSpec.EXACTLY,
                ),
            )
        }
        setMeasuredDimension(
            resolveSize(width, widthMeasureSpec),
            resolveSize(
                paddingTop + lastPlan.height + paddingBottom,
                heightMeasureSpec,
            ),
        )
    }

    override fun onLayout(
        changed: Boolean,
        left: Int,
        top: Int,
        right: Int,
        bottom: Int,
    ) {
        val visibleChildren = visibleChildren()
        val rtl = layoutDirection == LAYOUT_DIRECTION_RTL
        lastPlan.items.forEachIndexed { index, item ->
            val child = visibleChildren.getOrNull(index) ?: return@forEachIndexed
            val bounds = item.bounds
            val childLeft = if (rtl) {
                width - paddingRight - bounds.right
            } else {
                paddingLeft + bounds.left
            }
            child.layout(
                childLeft,
                paddingTop + bounds.top,
                childLeft + bounds.width,
                paddingTop + bounds.bottom,
            )
        }
    }

    @Suppress("DEPRECATION")
    override fun onInitializeAccessibilityNodeInfo(info: AccessibilityNodeInfo) {
        super.onInitializeAccessibilityNodeInfo(info)
        info.className = if ((tag as? String)?.startsWith("pam:table-row") == true) {
            "android.widget.TableRow"
        } else {
            "android.view.ViewGroup"
        }
        info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
            lastPlan.rows,
            lastPlan.columns,
            false,
            AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_NONE,
        )
    }

    private fun visibleChildren(): List<View> =
        buildList {
            repeat(childCount) { index ->
                getChildAt(index).takeIf { it.visibility != GONE }?.let(::add)
            }
        }

    private fun constrainCellContent(cell: View, width: Int) {
        val content = (cell as? ViewGroup)?.getChildAt(0) ?: return
        val params = content.layoutParams ?: return
        if (params.width == width) return
        params.width = width
        content.layoutParams = params
    }

    private fun Float.rounded(): Int = floor(this + 0.5f).toInt()
}

internal enum class GridDirection(val value: Int) {
    COLUMN(1),
    ROW(2),
    COLUMN_REVERSE(3),
    ROW_REVERSE(4);

    companion object {
        fun from(value: Int): GridDirection =
            entries.firstOrNull { it.value == value } ?: ROW
    }
}

internal data class GridBounds(
    val left: Int,
    val top: Int,
    val right: Int,
    val bottom: Int,
) {
    val width: Int
        get() = (right - left).coerceAtLeast(0)
    val height: Int
        get() = (bottom - top).coerceAtLeast(0)
}

internal data class GridPlanItem(
    val bounds: GridBounds,
    val row: Int,
    val column: Int,
    val span: Int,
)

internal data class GridPlan(
    val items: List<GridPlanItem>,
    val rows: Int,
    val columns: Int,
) {
    val height: Int
        get() = items.maxOfOrNull { it.bounds.bottom } ?: 0
}

internal object GridPlanner {
    fun plan(
        width: Int,
        columns: Int,
        columnGap: Int,
        rowGap: Int,
        spans: List<Int>,
        heights: List<Int>,
        direction: GridDirection,
    ): GridPlan {
        if (spans.isEmpty()) return GridPlan(emptyList(), 0, columns)
        if (direction == GridDirection.COLUMN || direction == GridDirection.COLUMN_REVERSE) {
            return columnPlan(width, rowGap, spans, heights, direction, columns)
        }
        val safeColumns = columns.coerceAtLeast(1)
        val safeGap = columnGap.coerceAtLeast(0)
        val available = (
            width.coerceAtLeast(0) - safeGap * (safeColumns - 1)
        ).coerceAtLeast(0)
        val columnWidth = available.toDouble() / safeColumns
        val placements = Array<GridPlanItem?>(spans.size) { null }
        var row = 0
        var column = 0
        var top = 0
        var rowHeight = 0
        val rowEntries = ArrayList<Int>()

        fun flushRow() {
            rowEntries.forEach { index ->
                val item = requireNotNull(placements[index])
                placements[index] = item.copy(
                    bounds = item.bounds.copy(
                        top = top,
                        bottom = top + rowHeight,
                    ),
                    row = row,
                )
            }
            top += rowHeight + rowGap.coerceAtLeast(0)
            row++
            column = 0
            rowHeight = 0
            rowEntries.clear()
        }

        spans.indices.forEach { index ->
            val requestedSpan = spans[index].coerceIn(1, safeColumns)
            if (column > 0 && column + requestedSpan > safeColumns) flushRow()
            val itemWidth = (
                requestedSpan * columnWidth + (requestedSpan - 1) * safeGap
            ).toInt().coerceAtLeast(0)
            val naturalLeft = (column * (columnWidth + safeGap)).toInt()
            val left = if (direction == GridDirection.ROW_REVERSE) {
                width.coerceAtLeast(0) - naturalLeft - itemWidth
            } else {
                naturalLeft
            }
            val logicalColumn = if (direction == GridDirection.ROW_REVERSE) {
                safeColumns - column - requestedSpan
            } else {
                column
            }
            val itemHeight = heights.getOrElse(index) { 1 }.coerceAtLeast(1)
            placements[index] = GridPlanItem(
                GridBounds(left, 0, left + itemWidth, itemHeight),
                row,
                logicalColumn,
                requestedSpan,
            )
            rowEntries += index
            rowHeight = max(rowHeight, itemHeight)
            column += requestedSpan
            if (column >= safeColumns) flushRow()
        }
        if (rowEntries.isNotEmpty()) flushRow()

        return GridPlan(
            placements.map(::requireNotNull),
            row,
            safeColumns,
        )
    }

    private fun columnPlan(
        width: Int,
        rowGap: Int,
        spans: List<Int>,
        heights: List<Int>,
        direction: GridDirection,
        columns: Int,
    ): GridPlan {
        val indices = spans.indices.toList().let { values ->
            if (direction == GridDirection.COLUMN_REVERSE) {
                values.reversed()
            } else {
                values
            }
        }
        val placements = Array<GridPlanItem?>(spans.size) { null }
        var top = 0
        indices.forEachIndexed { row, index ->
            val height = heights.getOrElse(index) { 1 }.coerceAtLeast(1)
            placements[index] = GridPlanItem(
                GridBounds(0, top, width.coerceAtLeast(0), top + height),
                row,
                0,
                columns,
            )
            top += height + rowGap.coerceAtLeast(0)
        }

        return GridPlan(
            placements.map(::requireNotNull),
            spans.size,
            columns.coerceAtLeast(1),
        )
    }
}

internal object GridSpec {
    const val DEFAULT_COLUMNS = 12
    const val RESPONSIVE_SLOTS = 6
    private const val MAX_COLUMNS = 64
    private val breakpoints = intArrayOf(640, 768, 1024, 1280, 1536)

    fun columns(source: String?, fallback: IntArray): IntArray =
        responsiveIntegers(source, fallback)

    fun gaps(
        source: String?,
        fallback: FloatArray,
        density: Float,
    ): FloatArray {
        val parsed = source
            ?.split(',')
            ?.mapNotNull(String::toFloatOrNull)
            .orEmpty()
        val result = FloatArray(RESPONSIVE_SLOTS)
        var current = fallback.firstOrNull()?.coerceAtLeast(0f) ?: 0f
        repeat(RESPONSIVE_SLOTS) { index ->
            current = (
                parsed.getOrNull(index)?.times(density)
                    ?: fallback.getOrNull(index)
                    ?: current
            ).coerceAtLeast(0f)
            result[index] = current
        }
        return result
    }

    fun span(tag: Any?, breakpoint: Int, columns: Int): Int {
        val source = (tag as? String)
            ?.takeIf { it.startsWith("pam:grid-item:") }
            ?.substringAfter("pam:grid-item:")
        return responsiveIntegers(source, IntArray(RESPONSIVE_SLOTS) { 1 })[
            breakpoint.coerceIn(0, RESPONSIVE_SLOTS - 1)
        ].coerceIn(1, columns.coerceAtLeast(1))
    }

    fun breakpointIndex(screenWidthDp: Int): Int {
        var index = 0
        breakpoints.forEach { breakpoint ->
            if (screenWidthDp >= breakpoint) index++
        }
        return index.coerceIn(0, RESPONSIVE_SLOTS - 1)
    }

    private fun responsiveIntegers(
        source: String?,
        fallback: IntArray,
    ): IntArray {
        val parsed = source
            ?.split(',')
            ?.mapNotNull(String::toIntOrNull)
            .orEmpty()
        val result = IntArray(RESPONSIVE_SLOTS)
        var current = fallback.firstOrNull()?.coerceIn(1, MAX_COLUMNS)
            ?: DEFAULT_COLUMNS
        repeat(RESPONSIVE_SLOTS) { index ->
            current = (
                parsed.getOrNull(index)
                    ?: fallback.getOrNull(index)
                    ?: current
            ).coerceIn(1, MAX_COLUMNS)
            result[index] = current
        }
        return result
    }
}

private fun Map<String, WireValue>.text(name: String): String? =
    (this[name] as? WireValue.Text)?.value

private fun Map<String, WireValue>.integer(name: String, fallback: Int): Int =
    (this[name] as? WireValue.Integer)?.value?.toInt() ?: fallback
