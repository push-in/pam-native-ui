package dev.pam.mobileui

import android.annotation.SuppressLint
import android.content.Context
import android.view.Choreographer
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.widget.HorizontalScrollView
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEmitter
import dev.pam.nativeapp.views.NativeViewEventKind
import dev.pam.nativeapp.views.NativeViewFactoryV2

class MobileUiHorizontalScrollFactory(
    @Suppress("UNUSED_PARAMETER") context: Context,
) : NativeViewFactoryV2 {
    override fun create(
        context: Context,
        emitter: NativeViewEmitter,
    ): View = MobileUiHorizontalScrollView(context, emitter)

    override fun update(
        view: View,
        properties: Map<String, WireValue>,
    ) {
        require(view is MobileUiHorizontalScrollView) {
            "pam.mobile_ui.horizontal_scroll requires MobileUiHorizontalScrollView"
        }
        view.update(properties)
    }

    override fun release(view: View) {
        (view as? MobileUiHorizontalScrollView)?.release()
    }
}

@SuppressLint("ViewConstructor")
internal class MobileUiHorizontalScrollView(
    context: Context,
    private val emitter: NativeViewEmitter,
) : HorizontalScrollView(context) {
    private val density = resources.displayMetrics.density
    private var scrollingEnabled = true
    private var scrollScheduled = false
    private var pendingScrollX = 0
    private var requestedContentOffset = 0f
    private var appliedContentOffset = Float.NaN
    private val emitScroll = Choreographer.FrameCallback {
        scrollScheduled = false
        emitter.emit(
            NativeViewEventKind.SCROLL,
            (pendingScrollX / density).toString().encodeToByteArray(),
        )
    }

    init {
        isFillViewport = true
        isHorizontalScrollBarEnabled = false
        isVerticalScrollBarEnabled = false
        isNestedScrollingEnabled = true
        clipToPadding = false
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_YES
        setOnScrollChangeListener { _, scrollX, _, _, _ ->
            pendingScrollX = scrollX
            if (!scrollScheduled) {
                scrollScheduled = true
                Choreographer.getInstance().postFrameCallback(emitScroll)
            }
        }
    }

    fun update(properties: Map<String, WireValue>) {
        scrollingEnabled = properties.flag("scrollEnabled", scrollingEnabled)
        isEnabled = scrollingEnabled
        isHorizontalScrollBarEnabled = properties.flag(
            "showsIndicator",
            isHorizontalScrollBarEnabled,
        )
        isFillViewport = properties.flag("fillViewport", isFillViewport)
        isNestedScrollingEnabled = properties.flag(
            "nestedScrollEnabled",
            isNestedScrollingEnabled,
        )
        overScrollMode = when (properties.text("overScrollMode", "auto")) {
            "always" -> OVER_SCROLL_ALWAYS
            "never" -> OVER_SCROLL_NEVER
            else -> OVER_SCROLL_IF_CONTENT_SCROLLS
        }
        requestedContentOffset = properties.number(
            "contentOffset",
            requestedContentOffset,
        ).coerceAtLeast(0f)
        if (isLaidOut && requestedContentOffset != appliedContentOffset) {
            applyContentOffset()
        }
    }

    override fun onInterceptTouchEvent(event: MotionEvent): Boolean =
        scrollingEnabled && super.onInterceptTouchEvent(event)

    override fun onMeasure(widthMeasureSpec: Int, heightMeasureSpec: Int) {
        getChildAt(0)?.let { child ->
            val current = child.layoutParams
            if (current.width == ViewGroup.LayoutParams.MATCH_PARENT) {
                current.width = ViewGroup.LayoutParams.WRAP_CONTENT
                child.layoutParams = current
            }
        }
        super.onMeasure(widthMeasureSpec, heightMeasureSpec)
    }

    override fun onLayout(
        changed: Boolean,
        left: Int,
        top: Int,
        right: Int,
        bottom: Int,
    ) {
        super.onLayout(changed, left, top, right, bottom)
        if (requestedContentOffset != appliedContentOffset) {
            applyContentOffset()
        }
    }

    fun release() {
        if (scrollScheduled) {
            Choreographer.getInstance().removeFrameCallback(emitScroll)
            scrollScheduled = false
        }
        setOnScrollChangeListener(null as View.OnScrollChangeListener?)
    }

    private fun applyContentOffset() {
        appliedContentOffset = requestedContentOffset
        scrollTo((requestedContentOffset * density).toInt(), 0)
    }
}

private fun Map<String, WireValue>.flag(name: String, fallback: Boolean): Boolean =
    (this[name] as? WireValue.Flag)?.value ?: fallback

private fun Map<String, WireValue>.text(name: String, fallback: String): String =
    (this[name] as? WireValue.Text)?.value ?: fallback

private fun Map<String, WireValue>.number(name: String, fallback: Float): Float =
    when (val value = this[name]) {
        is WireValue.Decimal -> value.value.toFloat()
        is WireValue.Integer -> value.value.toFloat()
        else -> fallback
    }
