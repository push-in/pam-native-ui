package dev.pam.mobileui

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.Color
import android.graphics.Typeface
import android.text.Spannable
import android.text.SpannableStringBuilder
import android.text.TextPaint
import android.text.method.LinkMovementMethod
import android.text.style.BackgroundColorSpan
import android.text.style.ClickableSpan
import android.text.style.ForegroundColorSpan
import android.text.style.LeadingMarginSpan
import android.text.style.RelativeSizeSpan
import android.text.style.StrikethroughSpan
import android.text.style.StyleSpan
import android.text.style.TypefaceSpan
import android.view.View
import android.widget.TextView
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEmitter
import dev.pam.nativeapp.views.NativeViewEventKind
import dev.pam.nativeapp.views.NativeViewFactoryV2

class MobileUiMarkdownFactory(
    @Suppress("UNUSED_PARAMETER") context: Context,
) : NativeViewFactoryV2 {
    override fun create(
        context: Context,
        emitter: NativeViewEmitter,
    ): View = MobileUiMarkdownView(context, emitter)

    override fun update(
        view: View,
        properties: Map<String, WireValue>,
    ) {
        require(view is MobileUiMarkdownView) {
            "pam.mobile_ui.markdown requires MobileUiMarkdownView"
        }
        view.update(properties)
    }

    override fun release(view: View) {
        (view as? MobileUiMarkdownView)?.release()
    }
}

@SuppressLint("ViewConstructor")
private class MobileUiMarkdownView(
    context: Context,
    private val emitter: NativeViewEmitter,
) : TextView(context) {
    private var source = ""
    private var foregroundColor = Color.rgb(23, 23, 23)
    private var mutedColor = Color.rgb(115, 115, 115)
    private var linkColor = Color.rgb(37, 99, 235)
    private var codeBackgroundColor = Color.rgb(245, 245, 245)
    private var codeForegroundColor = Color.rgb(23, 23, 23)
    private var selectable = true

    init {
        includeFontPadding = false
        movementMethod = LinkMovementMethod.getInstance()
        highlightColor = Color.TRANSPARENT
        linksClickable = true
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_YES
    }

    fun update(properties: Map<String, WireValue>) {
        val nextSource = properties.text("source", source)
        val nextForeground = properties.color("foregroundColor", foregroundColor)
        val nextMuted = properties.color("mutedColor", mutedColor)
        val nextLink = properties.color("linkColor", linkColor)
        val nextCodeBackground = properties.color(
            "codeBackgroundColor",
            codeBackgroundColor,
        )
        val nextCodeForeground = properties.color(
            "codeForegroundColor",
            codeForegroundColor,
        )
        val nextSelectable = properties.flag("selectable", selectable)
        if (
            nextSource == source
            && nextForeground == foregroundColor
            && nextMuted == mutedColor
            && nextLink == linkColor
            && nextCodeBackground == codeBackgroundColor
            && nextCodeForeground == codeForegroundColor
            && nextSelectable == selectable
        ) {
            return
        }

        source = nextSource
        foregroundColor = nextForeground
        mutedColor = nextMuted
        linkColor = nextLink
        codeBackgroundColor = nextCodeBackground
        codeForegroundColor = nextCodeForeground
        selectable = nextSelectable
        render()
    }

    fun release() {
        text = ""
        movementMethod = null
    }

    private fun render() {
        val document = MarkdownParser.parse(source)
        val output = SpannableStringBuilder(document.text)
        document.spans.forEach { span ->
            if (span.start !in 0..output.length || span.end !in 0..output.length) {
                return@forEach
            }
            if (span.start >= span.end) return@forEach
            val style: Any = when (span.kind) {
                MarkdownSpanKind.BOLD -> StyleSpan(Typeface.BOLD)
                MarkdownSpanKind.ITALIC -> StyleSpan(Typeface.ITALIC)
                MarkdownSpanKind.STRIKE -> StrikethroughSpan()
                MarkdownSpanKind.INLINE_CODE -> TypefaceSpan("monospace")
                MarkdownSpanKind.CODE_BLOCK -> TypefaceSpan("monospace")
                MarkdownSpanKind.HEADING -> RelativeSizeSpan(
                    when (span.value.toIntOrNull()?.coerceIn(1, 6) ?: 1) {
                        1 -> 1.65f
                        2 -> 1.45f
                        3 -> 1.3f
                        4 -> 1.2f
                        5 -> 1.1f
                        else -> 1.0f
                    },
                )
                MarkdownSpanKind.QUOTE -> LeadingMarginSpan.Standard(
                    (12 * resources.displayMetrics.density).toInt(),
                )
                MarkdownSpanKind.LIST -> LeadingMarginSpan.Standard(
                    (16 * resources.displayMetrics.density).toInt(),
                )
                MarkdownSpanKind.LINK -> MarkdownLinkSpan(
                    span.value,
                    linkColor,
                ) { uri ->
                    emitter.emit(
                        NativeViewEventKind.NATIVE,
                        uri.encodeToByteArray(),
                    )
                }
            }
            output.setSpan(
                style,
                span.start,
                span.end,
                Spannable.SPAN_EXCLUSIVE_EXCLUSIVE,
            )
            when (span.kind) {
                MarkdownSpanKind.HEADING -> output.setSpan(
                    StyleSpan(Typeface.BOLD),
                    span.start,
                    span.end,
                    Spannable.SPAN_EXCLUSIVE_EXCLUSIVE,
                )
                MarkdownSpanKind.INLINE_CODE,
                MarkdownSpanKind.CODE_BLOCK -> {
                    output.setSpan(
                        BackgroundColorSpan(codeBackgroundColor),
                        span.start,
                        span.end,
                        Spannable.SPAN_EXCLUSIVE_EXCLUSIVE,
                    )
                    output.setSpan(
                        ForegroundColorSpan(codeForegroundColor),
                        span.start,
                        span.end,
                        Spannable.SPAN_EXCLUSIVE_EXCLUSIVE,
                    )
                }
                MarkdownSpanKind.QUOTE -> output.setSpan(
                    ForegroundColorSpan(mutedColor),
                    span.start,
                    span.end,
                    Spannable.SPAN_EXCLUSIVE_EXCLUSIVE,
                )
                else -> Unit
            }
        }
        setTextColor(foregroundColor)
        text = output
        setTextIsSelectable(selectable)
        movementMethod = LinkMovementMethod.getInstance()
    }
}

private class MarkdownLinkSpan(
    private val uri: String,
    private val color: Int,
    private val activate: (String) -> Unit,
) : ClickableSpan() {
    override fun onClick(widget: View) {
        activate(uri)
    }

    override fun updateDrawState(drawState: TextPaint) {
        drawState.color = color
        drawState.isUnderlineText = true
    }
}

internal enum class MarkdownSpanKind(val value: Int) {
    BOLD(1),
    ITALIC(2),
    STRIKE(3),
    INLINE_CODE(4),
    CODE_BLOCK(5),
    HEADING(6),
    QUOTE(7),
    LIST(8),
    LINK(9),
}

internal data class MarkdownSpan(
    val kind: MarkdownSpanKind,
    val start: Int,
    val end: Int,
    val value: String = "",
)

internal data class MarkdownDocument(
    val text: String,
    val spans: List<MarkdownSpan>,
)

internal object MarkdownParser {
    private const val MAX_INLINE_DEPTH = 16

    fun parse(markdown: String): MarkdownDocument {
        val normalized = normalizeMarkdownSource(markdown)
        val lines = normalized.split('\n')
        val output = StringBuilder(normalized.length)
        val spans = ArrayList<MarkdownSpan>()
        var lineIndex = 0

        while (lineIndex < lines.size) {
            val line = lines[lineIndex]
            val trimmed = line.trimStart()
            val fence = when {
                trimmed.startsWith("```") -> "```"
                trimmed.startsWith("~~~") -> "~~~"
                else -> null
            }
            if (fence != null) {
                val start = output.length
                lineIndex++
                while (
                    lineIndex < lines.size
                    && !lines[lineIndex].trimStart().startsWith(fence)
                ) {
                    if (output.length > start) output.append('\n')
                    output.append(lines[lineIndex])
                    lineIndex++
                }
                spans += MarkdownSpan(
                    MarkdownSpanKind.CODE_BLOCK,
                    start,
                    output.length,
                )
                if (lineIndex < lines.size) lineIndex++
                appendLineBreak(output, lineIndex, lines.size)
                continue
            }

            val headingLevel = trimmed.takeWhile { it == '#' }.length
            if (
                headingLevel in 1..6
                && trimmed.getOrNull(headingLevel) == ' '
            ) {
                val start = output.length
                appendInline(
                    trimmed.substring(headingLevel + 1),
                    output,
                    spans,
                    0,
                )
                spans += MarkdownSpan(
                    MarkdownSpanKind.HEADING,
                    start,
                    output.length,
                    headingLevel.toString(),
                )
                lineIndex++
                appendLineBreak(output, lineIndex, lines.size)
                continue
            }

            if (trimmed.startsWith("> ")) {
                val start = output.length
                appendInline(trimmed.substring(2), output, spans, 0)
                spans += MarkdownSpan(
                    MarkdownSpanKind.QUOTE,
                    start,
                    output.length,
                )
                lineIndex++
                appendLineBreak(output, lineIndex, lines.size)
                continue
            }

            val unordered = trimmed.length >= 2
                && trimmed[0] in charArrayOf('-', '*', '+')
                && trimmed[1] == ' '
            val orderedMarkerEnd = orderedMarkerEnd(trimmed)
            if (unordered || orderedMarkerEnd > 0) {
                val start = output.length
                val contentStart: Int
                if (unordered) {
                    output.append("• ")
                    contentStart = 2
                } else {
                    val marker = trimmed.substring(0, orderedMarkerEnd)
                    output.append(marker).append(' ')
                    contentStart = orderedMarkerEnd + 1
                }
                appendInline(
                    trimmed.substring(contentStart),
                    output,
                    spans,
                    0,
                )
                spans += MarkdownSpan(
                    MarkdownSpanKind.LIST,
                    start,
                    output.length,
                )
                lineIndex++
                appendLineBreak(output, lineIndex, lines.size)
                continue
            }

            appendInline(line, output, spans, 0)
            lineIndex++
            appendLineBreak(output, lineIndex, lines.size)
        }

        return MarkdownDocument(output.toString(), spans)
    }

    private fun normalizeMarkdownSource(markdown: String): String {
        val boundaryTrimmed = markdown
            .replace("\r\n", "\n")
            .replace('\r', '\n')
            .trim()
        val lines = boundaryTrimmed.lines()
        if (lines.size < 2) return boundaryTrimmed

        val firstIndent = lines.first().takeWhile(Char::isWhitespace).length
        val continuationIndent = lines
            .drop(1)
            .filter(String::isNotBlank)
            .minOfOrNull { line -> line.takeWhile(Char::isWhitespace).length }
            ?: 0
        if (firstIndent != 0 || continuationIndent == 0) {
            return boundaryTrimmed.trimIndent()
        }

        return buildString(boundaryTrimmed.length) {
            append(lines.first())
            lines.drop(1).forEach { line ->
                append('\n')
                if (line.isNotBlank()) {
                    append(line.drop(continuationIndent))
                }
            }
        }
    }

    private fun appendInline(
        source: String,
        output: StringBuilder,
        spans: MutableList<MarkdownSpan>,
        depth: Int,
    ) {
        if (depth >= MAX_INLINE_DEPTH) {
            output.append(source)
            return
        }
        var index = 0
        while (index < source.length) {
            if (source[index] == '\\' && index + 1 < source.length) {
                output.append(source[index + 1])
                index += 2
                continue
            }
            val match = when {
                source.startsWith("**", index) ->
                    inlineMarker(source, index, "**", MarkdownSpanKind.BOLD)
                source.startsWith("__", index) ->
                    inlineMarker(source, index, "__", MarkdownSpanKind.BOLD)
                source.startsWith("~~", index) ->
                    inlineMarker(source, index, "~~", MarkdownSpanKind.STRIKE)
                source[index] == '`' ->
                    inlineMarker(source, index, "`", MarkdownSpanKind.INLINE_CODE)
                source[index] == '*' ->
                    inlineMarker(source, index, "*", MarkdownSpanKind.ITALIC)
                source[index] == '_' ->
                    inlineMarker(source, index, "_", MarkdownSpanKind.ITALIC)
                else -> null
            }
            if (match != null) {
                val start = output.length
                val content = source.substring(match.contentStart, match.contentEnd)
                if (match.kind == MarkdownSpanKind.INLINE_CODE) {
                    output.append(content)
                } else {
                    appendInline(content, output, spans, depth + 1)
                }
                spans += MarkdownSpan(match.kind, start, output.length)
                index = match.nextIndex
                continue
            }
            val link = linkAt(source, index)
            if (link != null) {
                val start = output.length
                appendInline(link.label, output, spans, depth + 1)
                spans += MarkdownSpan(
                    MarkdownSpanKind.LINK,
                    start,
                    output.length,
                    link.uri,
                )
                index = link.nextIndex
                continue
            }
            output.append(source[index])
            index++
        }
    }

    private fun inlineMarker(
        source: String,
        index: Int,
        marker: String,
        kind: MarkdownSpanKind,
    ): InlineMatch? {
        val contentStart = index + marker.length
        val contentEnd = source.indexOf(marker, contentStart)
        if (contentEnd <= contentStart) return null

        return InlineMatch(
            kind,
            contentStart,
            contentEnd,
            contentEnd + marker.length,
        )
    }

    private fun linkAt(source: String, index: Int): LinkMatch? {
        if (source[index] == '<') {
            val end = source.indexOf('>', index + 1)
            if (end > index + 1) {
                val uri = source.substring(index + 1, end)
                if (safeUri(uri)) {
                    return LinkMatch(uri, uri, end + 1)
                }
            }
        }
        if (source[index] != '[') return null
        val labelEnd = source.indexOf("](", index + 1)
        if (labelEnd <= index + 1) return null
        val uriEnd = source.indexOf(')', labelEnd + 2)
        if (uriEnd <= labelEnd + 2) return null
        val uri = source.substring(labelEnd + 2, uriEnd).trim()
        if (!safeUri(uri)) return null

        return LinkMatch(
            source.substring(index + 1, labelEnd),
            uri,
            uriEnd + 1,
        )
    }

    private fun safeUri(uri: String): Boolean {
        val scheme = uri.substringBefore(':', "").lowercase()
        return (when (scheme) {
            "https", "http", "mailto", "tel" -> true
            else -> false
        })
            && !uri.contains('\n')
            && !uri.contains('\u0000')
    }

    private fun orderedMarkerEnd(line: String): Int {
        var index = 0
        while (index < line.length && line[index].isDigit()) index++
        return if (
            index > 0
            && line.getOrNull(index) == '.'
            && line.getOrNull(index + 1) == ' '
        ) {
            index + 1
        } else {
            0
        }
    }

    private fun appendLineBreak(
        output: StringBuilder,
        nextLine: Int,
        lineCount: Int,
    ) {
        if (nextLine < lineCount) output.append('\n')
    }

    private data class InlineMatch(
        val kind: MarkdownSpanKind,
        val contentStart: Int,
        val contentEnd: Int,
        val nextIndex: Int,
    )

    private data class LinkMatch(
        val label: String,
        val uri: String,
        val nextIndex: Int,
    )
}

private fun Map<String, WireValue>.text(name: String, fallback: String): String =
    (this[name] as? WireValue.Text)?.value ?: fallback

private fun Map<String, WireValue>.color(name: String, fallback: Int): Int =
    (this[name] as? WireValue.Integer)?.value?.toInt() ?: fallback

private fun Map<String, WireValue>.flag(name: String, fallback: Boolean): Boolean =
    (this[name] as? WireValue.Flag)?.value ?: fallback
