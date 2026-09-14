package dev.pam.mobileui

internal object SparklineData {
    fun parse(source: String): List<Float> = source
        .split(',', '\n', ';', ' ')
        .mapNotNull { token -> token.toFloatOrNull()?.takeIf { it.isFinite() } }
}
