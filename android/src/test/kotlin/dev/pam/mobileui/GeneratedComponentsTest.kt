package dev.pam.mobileui

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class GeneratedComponentsTest {
    @Test
    fun generatedIdsAreSequentialAndCoverThePublicSurface() {
        val fields = GeneratedComponents::class.java.declaredFields
            .filter { it.type == Int::class.javaPrimitiveType }
            .map { it.getInt(null) }
            .sorted()

        assertTrue(fields.size >= 300)
        assertEquals((1..fields.size).toList(), fields)
    }
}
