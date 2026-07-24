package dev.pam.mobileui

import org.junit.Assert.assertArrayEquals
import org.junit.Assert.assertEquals
import org.junit.Test

class GridPlannerTest {
    @Test
    fun resolvesResponsiveColumnsSpansAndBreakpoints() {
        assertArrayEquals(
            intArrayOf(2, 3, 4, 4, 6, 6),
            GridSpec.columns(
                "2,3,4,4,6,6",
                IntArray(GridSpec.RESPONSIVE_SLOTS) { 12 },
            ),
        )
        assertEquals(0, GridSpec.breakpointIndex(639))
        assertEquals(1, GridSpec.breakpointIndex(640))
        assertEquals(5, GridSpec.breakpointIndex(1536))
        assertEquals(3, GridSpec.span("pam:grid-item:1,2,3,4,5,6", 2, 4))
        assertEquals(4, GridSpec.span("pam:grid-item:64", 0, 4))
        assertEquals(1, GridSpec.span("not-a-grid-item", 0, 4))
    }

    @Test
    fun laysOutSpansAndWrapsRowsWithoutRoundingOverflow() {
        val plan = GridPlanner.plan(
            width = 400,
            columns = 4,
            columnGap = 8,
            rowGap = 12,
            spans = listOf(1, 2, 1, 3, 1),
            heights = listOf(40, 56, 48, 64, 32),
            direction = GridDirection.ROW,
        )

        assertEquals(2, plan.rows)
        assertEquals(4, plan.columns)
        assertEquals(0, plan.items[0].bounds.left)
        assertEquals(102, plan.items[1].bounds.left)
        assertEquals(306, plan.items[2].bounds.left)
        assertEquals(0, plan.items[3].bounds.left)
        assertEquals(306, plan.items[4].bounds.left)
        assertEquals(56, plan.items[0].bounds.height)
        assertEquals(64, plan.items[3].bounds.height)
        assertEquals(132, plan.height)
        assertEquals(3, plan.items[3].span)
    }
}
