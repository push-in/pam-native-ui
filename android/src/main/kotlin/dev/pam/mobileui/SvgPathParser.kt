package dev.pam.mobileui

import android.graphics.Path

/**
 * Allocation-light parser for the absolute SVG commands used by gluestack-ui.
 *
 * The captured upstream icon set is validated at generation time and currently
 * uses only M, L, H, V, C and Z. Parsing happens once when the integer icon id
 * changes, never during a frame.
 */
internal object SvgPathParser {
    fun parse(pathData: String): Path {
        val reader = Reader(pathData)
        val path = Path()
        var command = '\u0000'
        var currentX = 0f
        var currentY = 0f
        var startX = 0f
        var startY = 0f

        while (reader.hasMore()) {
            reader.skipSeparators()
            if (!reader.hasMore()) break

            if (reader.peek().isLetter()) {
                command = reader.read()
            } else {
                require(command != '\u0000') {
                    "SVG path data must start with a command"
                }
            }

            when (command) {
                'M' -> {
                    currentX = reader.number()
                    currentY = reader.number()
                    startX = currentX
                    startY = currentY
                    path.moveTo(currentX, currentY)

                    while (reader.nextIsNumber()) {
                        currentX = reader.number()
                        currentY = reader.number()
                        path.lineTo(currentX, currentY)
                    }
                }

                'L' -> while (reader.nextIsNumber()) {
                    currentX = reader.number()
                    currentY = reader.number()
                    path.lineTo(currentX, currentY)
                }

                'H' -> while (reader.nextIsNumber()) {
                    currentX = reader.number()
                    path.lineTo(currentX, currentY)
                }

                'V' -> while (reader.nextIsNumber()) {
                    currentY = reader.number()
                    path.lineTo(currentX, currentY)
                }

                'C' -> while (reader.nextIsNumber()) {
                    val firstControlX = reader.number()
                    val firstControlY = reader.number()
                    val secondControlX = reader.number()
                    val secondControlY = reader.number()
                    currentX = reader.number()
                    currentY = reader.number()
                    path.cubicTo(
                        firstControlX,
                        firstControlY,
                        secondControlX,
                        secondControlY,
                        currentX,
                        currentY,
                    )
                }

                'Z' -> {
                    path.close()
                    currentX = startX
                    currentY = startY
                    command = '\u0000'
                }

                else -> error("Unsupported SVG path command: $command")
            }
        }

        return path
    }

    private class Reader(
        private val source: String,
    ) {
        private var position = 0

        fun hasMore(): Boolean = position < source.length

        fun peek(): Char = source[position]

        fun read(): Char = source[position++]

        fun skipSeparators() {
            while (hasMore() && (peek().isWhitespace() || peek() == ',')) {
                position++
            }
        }

        fun nextIsNumber(): Boolean {
            skipSeparators()
            if (!hasMore()) return false
            val next = peek()
            return next == '-' || next == '+' || next == '.' || next.isDigit()
        }

        fun number(): Float {
            skipSeparators()
            val start = position

            if (hasMore() && (peek() == '-' || peek() == '+')) {
                position++
            }

            while (hasMore() && peek().isDigit()) {
                position++
            }

            if (hasMore() && peek() == '.') {
                position++
                while (hasMore() && peek().isDigit()) {
                    position++
                }
            }

            if (hasMore() && (peek() == 'e' || peek() == 'E')) {
                position++
                if (hasMore() && (peek() == '-' || peek() == '+')) {
                    position++
                }
                while (hasMore() && peek().isDigit()) {
                    position++
                }
            }

            require(position > start) {
                "Expected SVG number at offset $position"
            }

            return source.substring(start, position).toFloat()
        }
    }
}
