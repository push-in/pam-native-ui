package dev.pam.mobileui

internal object SparklineData {
    fun barIndex(ratio: Float, count: Int): Int {
        require(count > 0)
        return (ratio.coerceIn(0f, 1f) * count).toInt().coerceIn(0, count - 1)
    }

    fun parse(source: String): List<Float> = source
        .split(',', '\n', ';', ' ')
        .mapNotNull { token -> token.toFloatOrNull()?.takeIf { it.isFinite() } }
}

internal data class SparklineScale(val low: Double, val high: Double) {
    fun fraction(value: Float): Float = if (high == low) 0.5f
        else ((value.toDouble() - low) / (high - low)).toFloat()

    companion object {
        fun from(points: List<Float>, includeZero: Boolean): SparklineScale {
            val minimum = points.minOrNull()?.toDouble() ?: 0.0
            val maximum = points.maxOrNull()?.toDouble() ?: 0.0
            return SparklineScale(
                if (includeZero) minOf(0.0, minimum) else minimum,
                if (includeZero) maxOf(0.0, maximum) else maximum,
            )
        }
    }
}
