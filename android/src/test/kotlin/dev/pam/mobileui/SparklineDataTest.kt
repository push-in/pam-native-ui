package dev.pam.mobileui

import org.junit.Assert.assertEquals
import org.junit.Test

class SparklineDataTest {
    @Test fun barSelectionUsesSlotsRatherThanLinePointRounding() {
        assertEquals(0, SparklineData.barIndex(0.24f, 4))
        assertEquals(1, SparklineData.barIndex(0.25f, 4))
        assertEquals(3, SparklineData.barIndex(1f, 4))
        assertEquals(0, SparklineData.barIndex(-1f, 4))
        assertEquals(0, SparklineData.barIndex(0.9f, 1))
    }

    @Test fun barsIncludeZeroForPositiveNegativeAndMixedSeries() {
        val positive = SparklineScale.from(listOf(12f, 64f), true)
        assertEquals(0f, positive.fraction(0f), 0f)
        assertEquals(0.1875f, positive.fraction(12f), 0.0001f)
        val negative = SparklineScale.from(listOf(-12f, -64f), true)
        assertEquals(1f, negative.fraction(0f), 0f)
        assertEquals(0f, negative.fraction(-64f), 0f)
        val mixed = SparklineScale.from(listOf(-20f, 20f), true)
        assertEquals(0.5f, mixed.fraction(0f), 0f)
    }

    @Test fun singletonBarsAndExtremeFiniteValuesHaveFiniteScale() {
        assertEquals(1f, SparklineScale.from(listOf(42f), true).fraction(42f), 0f)
        assertEquals(0.5f, SparklineScale.from(listOf(0f), true).fraction(0f), 0f)
        val extreme = SparklineScale.from(listOf(-Float.MAX_VALUE, Float.MAX_VALUE), true)
        assertEquals(0.5f, extreme.fraction(0f), 0f)
        assertEquals(1f, extreme.fraction(Float.MAX_VALUE), 0f)
    }

    @Test fun parsesFiniteValuesAcrossSupportedSeparators() {
        assertEquals(listOf(1f, -2f, 3.5f, 4f), SparklineData.parse("1,-2;3.5\n4"))
    }

    @Test fun discardsNonFiniteAndMalformedValues() {
        assertEquals(listOf(42f), SparklineData.parse("NaN Infinity -Infinity 1e999 invalid 42"))
    }

    @Test fun retainsSingletonAndEmptySeries() {
        assertEquals(listOf(0f), SparklineData.parse("0"))
        assertEquals(emptyList<Float>(), SparklineData.parse(""))
    }
}
