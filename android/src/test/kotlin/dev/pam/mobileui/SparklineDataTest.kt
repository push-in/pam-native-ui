package dev.pam.mobileui

import org.junit.Assert.assertEquals
import org.junit.Test

class SparklineDataTest {
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
