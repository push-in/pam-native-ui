package dev.pam.mobileui

import org.junit.Assert.assertEquals
import org.junit.Test

class AnchoredArrowPositionTest {
    @Test
    fun centersArrowWhenContentCannotFitTheRequestedInsets() {
        assertEquals(
            10f,
            clampedArrowCenter(
                desired = 200f,
                contentExtent = 20f,
                arrowExtent = 16f,
                edgeInset = 8f,
            ),
        )
    }

    @Test
    fun clampsArrowInsideAValidContentRange() {
        assertEquals(
            16f,
            clampedArrowCenter(
                desired = -20f,
                contentExtent = 100f,
                arrowExtent = 16f,
                edgeInset = 8f,
            ),
        )
        assertEquals(
            84f,
            clampedArrowCenter(
                desired = 200f,
                contentExtent = 100f,
                arrowExtent = 16f,
                edgeInset = 8f,
            ),
        )
    }
}
