package dev.pam.mobileui

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class MarkdownParserTest {
    @Test
    fun parsesBlocksAndNestedInlineStylesIntoOneIntrinsicDocument() {
        val document = MarkdownParser.parse(
            """
            # PAM Native
            - **Fast** and _native_
            > Runs on Android
            ```php
            echo 'PAM';
            ```
            [Docs](https://pam.dev)
            """.trimIndent(),
        )

        assertEquals(
            "PAM Native\n• Fast and native\nRuns on Android\necho 'PAM';\nDocs",
            document.text,
        )
        assertTrue(document.spans.any { it.kind == MarkdownSpanKind.HEADING })
        assertTrue(document.spans.any { it.kind == MarkdownSpanKind.LIST })
        assertTrue(document.spans.any { it.kind == MarkdownSpanKind.BOLD })
        assertTrue(document.spans.any { it.kind == MarkdownSpanKind.ITALIC })
        assertTrue(document.spans.any { it.kind == MarkdownSpanKind.QUOTE })
        assertTrue(document.spans.any { it.kind == MarkdownSpanKind.CODE_BLOCK })
        assertTrue(
            document.spans.any {
                it.kind == MarkdownSpanKind.LINK
                    && it.value == "https://pam.dev"
            },
        )
    }

    @Test
    fun refusesExecutableOrMalformedLinkSchemes() {
        val document = MarkdownParser.parse(
            "[safe](mailto:hello@pam.dev) [unsafe](javascript:alert(1))",
        )

        assertTrue(
            document.spans.any {
                it.kind == MarkdownSpanKind.LINK
                    && it.value == "mailto:hello@pam.dev"
            },
        )
        assertFalse(document.spans.any { it.value.startsWith("javascript:") })
        assertTrue(document.text.contains("[unsafe](javascript:alert(1))"))
    }
}
