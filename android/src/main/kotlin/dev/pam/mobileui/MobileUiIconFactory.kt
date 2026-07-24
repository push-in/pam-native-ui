package dev.pam.mobileui

import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Path
import android.view.View
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewFactory

class MobileUiIconFactory(
    @Suppress("UNUSED_PARAMETER") context: Context,
) : NativeViewFactory {
    override fun create(
        context: Context,
        emit: (ByteArray) -> Unit,
    ): View = MobileUiIcon(context)

    override fun update(
        view: View,
        properties: Map<String, WireValue>,
    ) {
        require(view is MobileUiIcon) { "pam.mobile_ui.icon requires MobileUiIcon" }
        view.icon = (properties["icon"] as? WireValue.Integer)?.value?.toInt() ?: 0
        view.color = (properties["color"] as? WireValue.Integer)?.value?.toInt() ?: Color.BLACK
        view.invalidate()
    }
}

private class MobileUiIcon(context: Context) : View(context) {
    var icon: Int = 0
        set(value) {
            if (field == value) return
            field = value
            parsedPaths = GeneratedIcons.paths[value]
                ?.map(SvgPathParser::parse)
                .orEmpty()
        }
    var color: Int = Color.BLACK
    private var parsedPaths: List<Path> = emptyList()
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeWidth = 2f
        strokeCap = Paint.Cap.ROUND
        strokeJoin = Paint.Join.ROUND
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        if (parsedPaths.isEmpty()) return
        val scale = minOf(width, height) / 24f
        val left = (width - 24f * scale) / 2f
        val top = (height - 24f * scale) / 2f
        paint.color = color
        paint.strokeWidth = 2f
        canvas.save()
        canvas.translate(left, top)
        canvas.scale(scale, scale)
        parsedPaths.forEach { path ->
            canvas.drawPath(path, paint)
        }
        canvas.restore()
    }
}
