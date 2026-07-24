package dev.pam.mobileui

import android.animation.ObjectAnimator
import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.app.Activity
import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RenderEffect
import android.graphics.Shader
import android.os.Build
import android.os.Bundle
import android.os.Looper
import android.view.GestureDetector
import android.view.HapticFeedbackConstants
import android.view.KeyEvent
import android.view.MotionEvent
import android.view.ScaleGestureDetector
import android.view.View
import android.view.ViewGroup
import android.view.accessibility.AccessibilityNodeInfo
import android.widget.FrameLayout
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEmitter
import dev.pam.nativeapp.views.NativeViewEventKind
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.ZoneId
import java.time.format.DateTimeParseException
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.round

@SuppressLint("ViewConstructor")
internal class MobileUiHost(
    context: Context,
    private val emitter: NativeViewEmitter,
) : FrameLayout(context) {
    private enum class Behavior(val value: Int) {
        CONTAINER(1),
        ACCORDION(2),
        BOTTOM_SHEET(3),
        OVERLAY(4),
        SLIDER(5),
        TABS(6),
        CALENDAR(7),
        SKELETON(8),
        GLASS(9),
        CHECKBOX(10),
        RADIO(11),
        TOAST(12),
        IMAGE_VIEWER(13),
        CHAT(14),
        PROGRESS(15),
        DRAWER(16),
        MODAL(17),
        ALERT_DIALOG(18),
        POPOVER(19),
        MENU(20),
        TOOLTIP(21),
        DATE_TIME_PICKER(22),
        PORTAL(23);

        companion object {
            fun from(value: Int): Behavior =
                entries.firstOrNull { it.value == value } ?: CONTAINER
        }
    }

    private enum class HostAction(val value: Long) {
        DISMISS(1),
        SELECT(2),
        OPEN(3),
        ZOOM(4),
    }

    private val density = resources.displayMetrics.density
    private val trackPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.rgb(229, 229, 229)
    }
    private val fillPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.rgb(23, 23, 23)
    }
    private var behavior = Behavior.CONTAINER
    private var component = 0
    private var expanded = false
    private var checked = false
    private var selected = false
    private var value = 0.0
    private var minimum = 0.0
    private var maximum = 100.0
    private var step = 1.0
    private var orientation = 1
    private var reversed = false
    private var anchor = 1
    private var placement = 1
    private var offset = 8.0
    private var dismissible = true
    private var closeOnOverlayClick = true
    private var dateTimeMode = "datetime"
    private var dateTimeValue: String? = null
    private var minimumDate: String? = null
    private var maximumDate: String? = null
    private var is24Hour = false
    private var calendarYear = LocalDate.now().year
    private var calendarMonth = LocalDate.now().monthValue
    private var dragOrigin = 0f
    private var dragOriginSecondary = 0f
    private var imageScale = 1f
    private var imageTranslationX = 0f
    private var imageTranslationY = 0f
    private var animator: ValueAnimator? = null
    private var pendingDismiss: Runnable? = null
    private var previousFocus: View? = null
    private val scaleDetector = ScaleGestureDetector(
        context,
        object : ScaleGestureDetector.SimpleOnScaleGestureListener() {
            override fun onScale(detector: ScaleGestureDetector): Boolean {
                imageScale = (imageScale * detector.scaleFactor).coerceIn(1f, 4f)
                applyImageTransform()
                return true
            }

            override fun onScaleEnd(detector: ScaleGestureDetector) {
                emitZoom()
            }
        },
    )
    private val gestureDetector = GestureDetector(
        context,
        object : GestureDetector.SimpleOnGestureListener() {
            override fun onDown(event: MotionEvent): Boolean = true

            override fun onDoubleTap(event: MotionEvent): Boolean {
                imageScale = if (imageScale > 1f) 1f else 2f
                if (imageScale == 1f) {
                    imageTranslationX = 0f
                    imageTranslationY = 0f
                }
                applyImageTransform(animate = true)
                emitZoom()
                return true
            }
        },
    )

    init {
        require(Looper.myLooper() == Looper.getMainLooper()) {
            "PAM Mobile UI views must be created on Android's UI thread"
        }
        clipChildren = false
        clipToPadding = false
        isClickable = true
        isFocusable = true
        setWillNotDraw(false)
    }

    fun update(properties: Map<String, WireValue>) {
        require(Looper.myLooper() == Looper.getMainLooper()) {
            "PAM Mobile UI updates must run on Android's UI thread"
        }
        val previousBehavior = behavior
        val previousExpanded = expanded
        val previousSelected = selected
        behavior = Behavior.from(properties.integer("behavior", behavior.value.toLong()).toInt())
        component = properties.integer("component", component.toLong()).toInt()
        expanded = properties.flag("expanded", properties.flag("isExpanded", expanded))
        checked = properties.flag("checked", properties.flag("isChecked", checked))
        selected = properties.flag("selected", properties.flag("isSelected", selected))
        value = properties.decimal("value", value)
        minimum = properties.decimal("min", minimum)
        maximum = max(minimum + 0.000_001, properties.decimal("max", maximum))
        step = properties.decimal("step", step).coerceAtLeast(0.000_001)
        orientation = properties.integer("orientation", orientation.toLong()).toInt()
        reversed = properties.flag("isReversed", properties.flag("reversed", reversed))
        anchor = properties.integer("anchor", anchor.toLong()).toInt()
        placement = properties.integer("placement", placement.toLong()).toInt().coerceIn(1, 13)
        offset = properties.decimal("offset", offset)
        dismissible = properties.flag(
            "dismissible",
            properties.flag("isDismissable", dismissible),
        )
        closeOnOverlayClick = properties.flag(
            "closeOnOverlayClick",
            properties.flag("closeOnOverlay", closeOnOverlayClick),
        )
        dateTimeMode = properties.text("mode") ?: dateTimeMode
        dateTimeValue = properties.text("value") ?: dateTimeValue
        minimumDate = properties.text("minimumDate") ?: minimumDate
        maximumDate = properties.text("maximumDate") ?: maximumDate
        is24Hour = properties.flag("is24Hour", is24Hour)
        calendarYear = properties.integer("year", calendarYear.toLong()).toInt()
        calendarMonth = properties.integer("month", calendarMonth.toLong()).toInt().coerceIn(1, 12)
        isEnabled = !properties.flag("disabled", false)
        isSelected = selected
        isActivated = checked || expanded
        contentDescription = properties.text("accessibilityLabel")
        tooltipText = properties.text("accessibilityHint")
        trackPaint.color = properties.integer("trackColor", trackPaint.color.toLong()).toInt()
        fillPaint.color = properties.integer("fillColor", fillPaint.color.toLong()).toInt()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = properties.text("stateDescription")
        }

        if (previousBehavior != behavior) {
            installBehavior()
            animateEntrance()
            if (behavior.isOverlay()) {
                isFocusableInTouchMode = true
                captureAndMoveFocus()
            }
        }
        if (previousExpanded != expanded) {
            animateExpanded()
        }
        if (previousSelected != selected && behavior == Behavior.TABS) {
            announceForAccessibility(contentDescription)
        }
        scheduleToast(properties)
        applyComponentDefaults()
        invalidate()
    }

    override fun onViewAdded(child: View) {
        super.onViewAdded(child)
        if (
            behavior == Behavior.ACCORDION
            && indexOfChild(child) > 0
            && !expanded
        ) {
            child.alpha = 0f
            child.scaleY = 0.98f
        }
    }

    override fun dispatchDraw(canvas: Canvas) {
        super.dispatchDraw(canvas)
        when (behavior) {
            Behavior.SLIDER -> drawSlider(canvas)
            Behavior.PROGRESS -> drawProgress(canvas)
            Behavior.CHECKBOX -> drawCheckbox(canvas, radio = false)
            Behavior.RADIO -> drawCheckbox(canvas, radio = true)
            else -> Unit
        }
    }

    override fun onLayout(
        changed: Boolean,
        left: Int,
        top: Int,
        right: Int,
        bottom: Int,
    ) {
        super.onLayout(changed, left, top, right, bottom)
        if (behavior.isAnchoredOverlay()) {
            positionAnchoredContent()
        }
    }

    override fun onVisibilityChanged(changedView: View, visibility: Int) {
        super.onVisibilityChanged(changedView, visibility)
        if (changedView !== this || !behavior.isOverlay()) return
        if (visibility == VISIBLE) {
            captureAndMoveFocus()
        } else {
            restoreFocus()
        }
    }

    override fun focusSearch(focused: View?, direction: Int): View? {
        val candidate = super.focusSearch(focused, direction)
        if (!behavior.isOverlay() || candidate == null || contains(candidate)) {
            return candidate
        }
        val focusables = overlayFocusables()
        if (focusables.isEmpty()) return this
        return if (
            direction == FOCUS_BACKWARD
            || direction == FOCUS_LEFT
            || direction == FOCUS_UP
        ) {
            focusables.last()
        } else {
            focusables.first()
        }
    }

    @Suppress("DEPRECATION")
    override fun onInitializeAccessibilityNodeInfo(info: AccessibilityNodeInfo) {
        super.onInitializeAccessibilityNodeInfo(info)
        info.isEnabled = isEnabled
        info.isSelected = selected
        info.isChecked = checked
        info.isCheckable = behavior == Behavior.CHECKBOX || behavior == Behavior.RADIO
        info.isScrollable = behavior == Behavior.BOTTOM_SHEET || behavior == Behavior.IMAGE_VIEWER
        if (behavior == Behavior.ACCORDION) {
            info.className = "android.widget.Button"
            info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                stateDescription = if (expanded) "Expanded" else "Collapsed"
            }
        }
        if (behavior == Behavior.SLIDER || behavior == Behavior.PROGRESS) {
            info.rangeInfo = AccessibilityNodeInfo.RangeInfo.obtain(
                AccessibilityNodeInfo.RangeInfo.RANGE_TYPE_FLOAT,
                minimum.toFloat(),
                maximum.toFloat(),
                value.toFloat(),
            )
            if (behavior == Behavior.SLIDER) {
                info.addAction(AccessibilityNodeInfo.ACTION_SCROLL_FORWARD)
                info.addAction(AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD)
            }
        }
        if (behavior.isOverlay() && dismissible) {
            info.addAction(AccessibilityNodeInfo.ACTION_DISMISS)
        }
        info.className = when (behavior) {
            Behavior.SLIDER -> "android.widget.SeekBar"
            Behavior.CHECKBOX -> "android.widget.CheckBox"
            Behavior.RADIO -> "android.widget.RadioButton"
            Behavior.TABS -> "android.widget.TabWidget"
            Behavior.DATE_TIME_PICKER -> "android.widget.DatePicker"
            Behavior.MODAL,
            Behavior.ALERT_DIALOG,
            -> "android.app.Dialog"
            else -> info.className
        }
    }

    override fun performAccessibilityAction(action: Int, arguments: Bundle?): Boolean {
        if (
            behavior == Behavior.SLIDER
            && action in setOf(
                AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD,
            )
        ) {
            val direction = if (action == AccessibilityNodeInfo.ACTION_SCROLL_FORWARD) 1.0 else -1.0
            value = snapped(value + direction * step)
            emitValue()
            invalidate()
            return true
        }
        if (action == AccessibilityNodeInfo.ACTION_DISMISS && behavior.isOverlay()) {
            emitDismiss()
            return true
        }

        return super.performAccessibilityAction(action, arguments)
    }

    override fun dispatchKeyEvent(event: KeyEvent): Boolean {
        if (event.action == KeyEvent.ACTION_UP && event.keyCode == KeyEvent.KEYCODE_BACK) {
            if (behavior.isOverlay() && dismissible) {
                emitDismiss()
                return true
            }
        }
        if (
            behavior == Behavior.SLIDER
            && event.action == KeyEvent.ACTION_DOWN
            && event.keyCode in setOf(
                KeyEvent.KEYCODE_DPAD_LEFT,
                KeyEvent.KEYCODE_DPAD_DOWN,
                KeyEvent.KEYCODE_DPAD_RIGHT,
                KeyEvent.KEYCODE_DPAD_UP,
            )
        ) {
            val positive = event.keyCode == KeyEvent.KEYCODE_DPAD_RIGHT
                || event.keyCode == KeyEvent.KEYCODE_DPAD_UP
            value = snapped(value + if (positive) step else -step)
            emitValue()
            invalidate()
            return true
        }

        return super.dispatchKeyEvent(event)
    }

    fun release() {
        animator?.cancel()
        animator = null
        pendingDismiss?.let(::removeCallbacks)
        pendingDismiss = null
        animate().cancel()
        setOnTouchListener(null)
        setOnClickListener(null)
        imageScale = 1f
        imageTranslationX = 0f
        imageTranslationY = 0f
        applyImageTransform()
        restoreFocus()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            setRenderEffect(null)
        }
    }

    private fun installBehavior() {
        setOnTouchListener(
            when (behavior) {
                Behavior.SLIDER -> sliderTouchListener()
                Behavior.BOTTOM_SHEET -> sheetTouchListener()
                Behavior.DRAWER -> drawerTouchListener()
                Behavior.TABS -> indexedSelectionTouchListener()
                Behavior.CALENDAR -> calendarTouchListener()
                Behavior.IMAGE_VIEWER -> imageViewerTouchListener()
                Behavior.OVERLAY,
                Behavior.MODAL,
                Behavior.ALERT_DIALOG,
                Behavior.POPOVER,
                Behavior.MENU,
                Behavior.TOOLTIP,
                Behavior.PORTAL,
                -> overlayTouchListener()
                else -> null
            },
        )

        if (
            behavior == Behavior.CHECKBOX ||
            behavior == Behavior.RADIO ||
            behavior == Behavior.ACCORDION
        ) {
            setOnClickListener {
                if (behavior == Behavior.ACCORDION) {
                    expanded = !expanded
                    isActivated = expanded
                    animateExpanded()
                } else if (behavior == Behavior.RADIO) {
                    checked = true
                    isActivated = true
                } else {
                    checked = !checked
                    isActivated = checked
                }
                performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                invalidate()
                emitter.emit(
                    NativeViewEventKind.TOGGLE,
                    (if (behavior == Behavior.ACCORDION) expanded else checked)
                        .toEventPayload(),
                )
            }
        } else if (behavior == Behavior.DATE_TIME_PICKER) {
            setOnClickListener { showDateTimePicker() }
        } else {
            setOnClickListener(null)
        }

        if (behavior == Behavior.SKELETON) {
            startSkeleton()
        } else {
            animator?.cancel()
            animator = null
        }

        if (behavior == Behavior.GLASS && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            setRenderEffect(
                RenderEffect.createBlurEffect(
                    12f * density,
                    12f * density,
                    Shader.TileMode.CLAMP,
                ),
            )
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            setRenderEffect(null)
        }
    }

    private fun applyComponentDefaults() {
        val interactive = behavior in setOf(
            Behavior.SLIDER,
            Behavior.CHECKBOX,
            Behavior.RADIO,
            Behavior.TABS,
            Behavior.CALENDAR,
            Behavior.DATE_TIME_PICKER,
        ) || component in setOf(
            GeneratedComponents.BUTTON,
            GeneratedComponents.FAB,
        )
        if (interactive) {
            minimumWidth = max(minimumWidth, (48f * density).toInt())
            minimumHeight = max(minimumHeight, (48f * density).toInt())
        }
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_YES
    }

    private fun animateExpanded() {
        if (!animationsEnabled()) {
            accordionChildren().forEach { child ->
                child.alpha = if (expanded) 1f else 0f
                child.scaleY = if (expanded) 1f else 0.98f
            }
            return
        }
        accordionChildren().forEach { child ->
            child.animate()
                .alpha(if (expanded) 1f else 0f)
                .scaleY(if (expanded) 1f else 0.98f)
                .setDuration(180L)
                .start()
        }
    }

    private fun animateEntrance() {
        if (!behavior.isOverlay() && behavior != Behavior.TOAST) return
        if (!animationsEnabled()) return
        alpha = 0f
        when (behavior) {
            Behavior.BOTTOM_SHEET -> translationY = 24f * density
            Behavior.DRAWER -> when (anchor) {
                1 -> translationX = -24f * density
                2 -> translationX = 24f * density
                3 -> translationY = -24f * density
                else -> translationY = 24f * density
            }
            else -> Unit
        }
        animate()
            .alpha(1f)
            .translationX(0f)
            .translationY(0f)
            .setDuration(200L)
            .start()
    }

    private fun startSkeleton() {
        if (animator != null || !animationsEnabled()) return
        animator = ObjectAnimator.ofFloat(this, View.ALPHA, 0.55f, 1f).apply {
            duration = 1_500L
            repeatMode = ValueAnimator.REVERSE
            repeatCount = ValueAnimator.INFINITE
            start()
        }
    }

    private fun sliderTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled || width <= 0 || height <= 0) return@OnTouchListener false
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN,
                MotionEvent.ACTION_MOVE,
                -> {
                    var progress = if (orientation == 2) {
                        1.0 - event.y / height.toDouble()
                    } else {
                        event.x / width.toDouble()
                    }
                    progress = progress.coerceIn(0.0, 1.0)
                    if (reversed) progress = 1.0 - progress
                    value = snapped(minimum + (maximum - minimum) * progress)
                    invalidate()
                    true
                }
                MotionEvent.ACTION_UP -> {
                    performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
                    emitValue()
                    performClick()
                    true
                }
                MotionEvent.ACTION_CANCEL -> true
                else -> false
            }
        }

    private fun drawerTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled) return@OnTouchListener false
            val horizontal = anchor == 1 || anchor == 2
            val coordinate = if (horizontal) event.rawX else event.rawY
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    dragOrigin = coordinate
                    animate().cancel()
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    val delta = coordinate - dragOrigin
                    when (anchor) {
                        1 -> translationX = delta.coerceAtMost(0f)
                        2 -> translationX = delta.coerceAtLeast(0f)
                        3 -> translationY = delta.coerceAtMost(0f)
                        else -> translationY = delta.coerceAtLeast(0f)
                    }
                    true
                }
                MotionEvent.ACTION_UP,
                MotionEvent.ACTION_CANCEL,
                -> {
                    if (event.actionMasked == MotionEvent.ACTION_UP) {
                        performClick()
                    }
                    val distance = if (horizontal) abs(translationX) else abs(translationY)
                    val size = if (horizontal) width else height
                    val dismiss = event.actionMasked == MotionEvent.ACTION_UP
                        && dismissible
                        && distance > size * 0.28f
                    val target = when (anchor) {
                        1, 3 -> -size.toFloat()
                        else -> size.toFloat()
                    }
                    val animation = animate().setDuration(180L)
                    if (horizontal) {
                        animation.translationX(if (dismiss) target else 0f)
                    } else {
                        animation.translationY(if (dismiss) target else 0f)
                    }
                    animation.withEndAction { if (dismiss) emitDismiss() }.start()
                    true
                }
                else -> false
            }
        }

    private fun overlayTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (
                !dismissible
                || !closeOnOverlayClick
                || event.actionMasked != MotionEvent.ACTION_UP
            ) {
                return@OnTouchListener false
            }
            val content = if (childCount == 0) null else getChildAt(childCount - 1)
            val contentLeft = content?.x ?: 0f
            val contentTop = content?.y ?: 0f
            val insideContent = content != null
                && event.x >= contentLeft
                && event.x <= contentLeft + content.width
                && event.y >= contentTop
                && event.y <= contentTop + content.height
            if (!insideContent) {
                emitDismiss()
                performClick()
                true
            } else {
                false
            }
        }

    private fun sheetTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled) return@OnTouchListener false
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    dragOrigin = event.rawY
                    animate().cancel()
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    translationY = max(0f, event.rawY - dragOrigin)
                    true
                }
                MotionEvent.ACTION_UP,
                MotionEvent.ACTION_CANCEL,
                -> {
                    if (event.actionMasked == MotionEvent.ACTION_UP) {
                        performClick()
                    }
                    val dismiss = event.actionMasked == MotionEvent.ACTION_UP
                        && dismissible
                        && translationY > height * 0.28f
                    animate()
                        .translationY(if (dismiss) height.toFloat() else 0f)
                        .setDuration(180L)
                        .withEndAction {
                            if (dismiss) {
                                emitDismiss()
                            }
                        }
                        .start()
                    true
                }
                else -> false
            }
        }

    private fun indexedSelectionTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled) return@OnTouchListener false
            val list = getChildAt(0) as? ViewGroup ?: this
            val insideList = event.x >= list.left
                && event.x <= list.right
                && event.y >= list.top
                && event.y <= list.bottom
            if (!insideList) return@OnTouchListener false
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> true
                MotionEvent.ACTION_UP -> {
                    val count = list.childCount.coerceAtLeast(1)
                    val localX = event.x - list.left
                    val index = ((localX / list.width.coerceAtLeast(1)) * count)
                        .toInt()
                        .coerceIn(0, count - 1)
                    selected = true
                    isSelected = true
                    performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                    val trigger = list.getChildAt(index)
                    emitter.emit(
                        NativeViewEventKind.CHANGE,
                        trigger.tag.toEventPayload(index + 1),
                    )
                    performClick()
                    true
                }
                MotionEvent.ACTION_CANCEL -> true
                else -> false
            }
        }

    private fun calendarTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled) return@OnTouchListener false
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> true
                MotionEvent.ACTION_UP -> {
                    val column = ((event.x / width.coerceAtLeast(1)) * 7)
                        .toInt()
                        .coerceIn(0, 6)
                    val row = ((event.y / height.coerceAtLeast(1)) * 6)
                        .toInt()
                        .coerceIn(0, 5)
                    val first = LocalDate.of(calendarYear, calendarMonth, 1)
                    val firstColumn = first.dayOfWeek.value % 7
                    val day = row * 7 + column - firstColumn + 1
                    if (day in 1..first.lengthOfMonth()) {
                        emitter.emit(
                            NativeViewEventKind.CHANGE,
                            LocalDate.of(calendarYear, calendarMonth, day)
                                .toString()
                                .encodeToByteArray(),
                        )
                        performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                    }
                    performClick()
                    true
                }
                MotionEvent.ACTION_CANCEL -> true
                else -> false
            }
        }

    private fun showDateTimePicker() {
        if (!isEnabled) return
        val activity = context as? Activity ?: return
        val initial = parsedDateTime()

        if (dateTimeMode == "time") {
            showTimePicker(activity, initial.toLocalDate(), initial.toLocalTime())
            return
        }

        DatePickerDialog(
            activity,
            { _, year, zeroBasedMonth, day ->
                val date = LocalDate.of(year, zeroBasedMonth + 1, day)
                if (dateTimeMode == "datetime") {
                    showTimePicker(activity, date, initial.toLocalTime())
                } else {
                    emitDateTime(LocalDateTime.of(date, LocalTime.MIDNIGHT))
                }
            },
            initial.year,
            initial.monthValue - 1,
            initial.dayOfMonth,
        ).apply {
            minimumDate?.let(::parseDate)?.let { datePicker.minDate = it.toEpochMillis() }
            maximumDate?.let(::parseDate)?.let { datePicker.maxDate = it.toEpochMillis() }
            setOnCancelListener { emitDismiss() }
            show()
        }
    }

    private fun showTimePicker(
        activity: Activity,
        date: LocalDate,
        time: LocalTime,
    ) {
        TimePickerDialog(
            activity,
            { _, hour, minute ->
                emitDateTime(LocalDateTime.of(date, LocalTime.of(hour, minute)))
            },
            time.hour,
            time.minute,
            is24Hour,
        ).apply {
            setOnCancelListener { emitDismiss() }
            show()
        }
    }

    private fun emitDateTime(dateTime: LocalDateTime) {
        dateTimeValue = when (dateTimeMode) {
            "date" -> dateTime.toLocalDate().toString()
            "time" -> dateTime.toLocalTime().toString()
            else -> dateTime.toString()
        }
        emitter.emit(
            NativeViewEventKind.CHANGE,
            dateTimeValue.orEmpty().encodeToByteArray(),
        )
    }

    private fun parsedDateTime(): LocalDateTime {
        val raw = dateTimeValue ?: return LocalDateTime.now()
        return try {
            LocalDateTime.parse(raw)
        } catch (_: DateTimeParseException) {
            parseDate(raw)?.atStartOfDay() ?: LocalDateTime.now()
        }
    }

    private fun parseDate(value: String): LocalDate? =
        try {
            LocalDate.parse(value.take(10))
        } catch (_: DateTimeParseException) {
            null
        }

    private fun LocalDate.toEpochMillis(): Long =
        atStartOfDay(ZoneId.systemDefault()).toInstant().toEpochMilli()

    private fun imageViewerTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled) return@OnTouchListener false
            scaleDetector.onTouchEvent(event)
            gestureDetector.onTouchEvent(event)
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    dragOrigin = event.rawX
                    dragOriginSecondary = event.rawY
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    if (event.pointerCount > 1) {
                        true
                    } else if (imageScale > 1f) {
                        val target = imageTarget()
                        val maxX = max(0f, ((imageScale - 1f) * (target?.width ?: width)) / 2f)
                        val maxY = max(0f, ((imageScale - 1f) * (target?.height ?: height)) / 2f)
                        imageTranslationX = (event.rawX - dragOrigin).coerceIn(-maxX, maxX)
                        imageTranslationY = (event.rawY - dragOriginSecondary).coerceIn(-maxY, maxY)
                        applyImageTransform()
                    } else {
                        translationX = event.rawX - dragOrigin
                        translationY = event.rawY - dragOriginSecondary
                        alpha = (1f - abs(translationY) / height.coerceAtLeast(1)).coerceIn(0.35f, 1f)
                    }
                    true
                }
                MotionEvent.ACTION_UP -> {
                    if (imageScale <= 1f && abs(translationY) > height * 0.18f) {
                        emitDismiss()
                    } else if (imageScale <= 1f) {
                        val direction = when {
                            translationX > width * 0.2f -> -1L
                            translationX < -width * 0.2f -> 1L
                            else -> 0L
                        }
                        if (direction != 0L) {
                            emitter.emit(
                                NativeViewEventKind.CHANGE,
                                WireMap.encode(
                                    mapOf(
                                        "action" to WireValue.Integer(HostAction.SELECT.value),
                                        "direction" to WireValue.Integer(direction),
                                    ),
                                ),
                            )
                        }
                    }
                    if (animationsEnabled()) {
                        animate()
                            .translationX(0f)
                            .translationY(0f)
                            .alpha(1f)
                            .setDuration(160L)
                            .start()
                    } else {
                        translationX = 0f
                        translationY = 0f
                        alpha = 1f
                    }
                    performClick()
                    true
                }
                MotionEvent.ACTION_CANCEL -> {
                    animate().cancel()
                    translationX = 0f
                    translationY = 0f
                    alpha = 1f
                    true
                }
                else -> false
            }
        }

    private fun imageTarget(): View? = if (childCount > 0) getChildAt(0) else null

    private fun applyImageTransform(animate: Boolean = false) {
        val target = imageTarget() ?: return
        if (animate && animationsEnabled()) {
            target.animate()
                .scaleX(imageScale)
                .scaleY(imageScale)
                .translationX(imageTranslationX)
                .translationY(imageTranslationY)
                .setDuration(180L)
                .start()
        } else {
            target.scaleX = imageScale
            target.scaleY = imageScale
            target.translationX = imageTranslationX
            target.translationY = imageTranslationY
        }
    }

    private fun emitZoom() {
        emitter.emit(
            NativeViewEventKind.NATIVE,
            WireMap.encode(
                mapOf(
                    "action" to WireValue.Integer(HostAction.ZOOM.value),
                    "scale" to WireValue.Decimal(imageScale.toDouble()),
                    "zoomed" to WireValue.Flag(imageScale > 1f),
                ),
            ),
        )
    }

    private fun scheduleToast(properties: Map<String, WireValue>) {
        pendingDismiss?.let(::removeCallbacks)
        pendingDismiss = null
        if (behavior != Behavior.TOAST || properties.flag("persistent", false)) return
        val duration = properties.integer("duration", 4_000L).coerceIn(500L, 60_000L)
        pendingDismiss = Runnable {
            emitDismiss()
        }.also { postDelayed(it, duration) }
    }

    private fun animationsEnabled(): Boolean = ValueAnimator.areAnimatorsEnabled()

    private fun drawSlider(canvas: Canvas) {
        val radius = 2f * density
        var progress = ((value - minimum) / (maximum - minimum)).coerceIn(0.0, 1.0).toFloat()
        if (reversed) progress = 1f - progress
        if (orientation == 2) {
            val centerX = width / 2f
            val end = height * (1f - progress)
            canvas.drawRoundRect(
                centerX - radius,
                0f,
                centerX + radius,
                height.toFloat(),
                radius,
                radius,
                trackPaint,
            )
            canvas.drawRoundRect(
                centerX - radius,
                end,
                centerX + radius,
                height.toFloat(),
                radius,
                radius,
                fillPaint,
            )
            canvas.drawCircle(centerX, end, 10f * density, fillPaint)
        } else {
            val centerY = height / 2f
            val end = width * progress
            canvas.drawRoundRect(
                0f,
                centerY - radius,
                width.toFloat(),
                centerY + radius,
                radius,
                radius,
                trackPaint,
            )
            canvas.drawRoundRect(
                0f,
                centerY - radius,
                end,
                centerY + radius,
                radius,
                radius,
                fillPaint,
            )
            canvas.drawCircle(end, centerY, 10f * density, fillPaint)
        }
    }

    private fun drawProgress(canvas: Canvas) {
        val progress = ((value - minimum) / (maximum - minimum)).coerceIn(0.0, 1.0).toFloat()
        val radius = height / 2f
        canvas.drawRoundRect(0f, 0f, width.toFloat(), height.toFloat(), radius, radius, trackPaint)
        canvas.drawRoundRect(0f, 0f, width * progress, height.toFloat(), radius, radius, fillPaint)
    }

    private fun drawCheckbox(canvas: Canvas, radio: Boolean) {
        val size = 20f * density
        val indicator = if (childCount > 0) getChildAt(0) else null
        val left = indicator?.let { it.left + (it.width - size) / 2f }
            ?: ((width - size) / 2f)
        val top = indicator?.let { it.top + (it.height - size) / 2f }
            ?: ((height - size) / 2f)
        val radius = if (radio) size / 2f else 4f * density
        trackPaint.style = Paint.Style.STROKE
        trackPaint.strokeWidth = 2f * density
        canvas.drawRoundRect(left, top, left + size, top + size, radius, radius, trackPaint)
        trackPaint.style = Paint.Style.FILL
        if (checked) {
            canvas.drawCircle(
                left + size / 2f,
                top + size / 2f,
                if (radio) 5f * density else 6f * density,
                fillPaint,
            )
        }
    }

    private fun children(): Sequence<View> =
        sequence {
            repeat(childCount) { index -> yield(getChildAt(index)) }
        }

    private fun accordionChildren(): Sequence<View> =
        children().filterIndexed { index, _ -> index > 0 }

    private fun positionAnchoredContent() {
        if (childCount < 2) return
        val trigger = getChildAt(0)
        val content = getChildAt(childCount - 1)
        if (trigger === content || content.width <= 0 || content.height <= 0) return
        val gap = (offset * density).toFloat()
        val centeredX = trigger.left + (trigger.width - content.width) / 2f
        val centeredY = trigger.top + (trigger.height - content.height) / 2f
        val target = when (placement) {
            1 -> centeredX to (trigger.top - content.height - gap)
            2 -> trigger.left.toFloat() to (trigger.top - content.height - gap)
            3 -> (trigger.right - content.width).toFloat() to
                (trigger.top - content.height - gap)
            4 -> centeredX to (trigger.bottom + gap)
            5 -> trigger.left.toFloat() to (trigger.bottom + gap)
            6 -> (trigger.right - content.width).toFloat() to (trigger.bottom + gap)
            7 -> (trigger.left - content.width - gap) to centeredY
            8 -> (trigger.left - content.width - gap) to trigger.top.toFloat()
            9 -> (trigger.left - content.width - gap) to
                (trigger.bottom - content.height).toFloat()
            10 -> (trigger.right + gap) to centeredY
            11 -> (trigger.right + gap) to trigger.top.toFloat()
            12 -> (trigger.right + gap) to (trigger.bottom - content.height).toFloat()
            else -> ((width - content.width) / 2f) to ((height - content.height) / 2f)
        }
        val targetX = target.first.coerceIn(0f, max(0, width - content.width).toFloat())
        val targetY = target.second.coerceIn(0f, max(0, height - content.height).toFloat())
        content.translationX = targetX - content.left
        content.translationY = targetY - content.top
    }

    private fun captureAndMoveFocus() {
        if (!isShown) return
        val focused = rootView.findFocus()
        if (focused != null && focused !== this && !contains(focused)) {
            previousFocus = focused
        }
        post {
            val target = overlayFocusables().firstOrNull() ?: this
            target.requestFocus()
        }
    }

    private fun restoreFocus() {
        val target = previousFocus
        previousFocus = null
        if (target != null && target.isAttachedToWindow && target.visibility == VISIBLE) {
            target.post { target.requestFocus() }
        }
    }

    private fun overlayFocusables(): List<View> {
        val focusables = ArrayList<View>()
        addFocusables(focusables, FOCUS_FORWARD, FOCUSABLES_ALL)
        return focusables.filter {
            it !== this && it.visibility == VISIBLE && it.isEnabled
        }
    }

    private fun contains(candidate: View): Boolean {
        var current: View? = candidate
        while (current != null) {
            if (current === this) return true
            current = current.parent as? View
        }
        return false
    }

    private fun snapped(candidate: Double): Double {
        val clamped = candidate.coerceIn(minimum, maximum)
        val steps = round((clamped - minimum) / step)
        return (minimum + steps * step).coerceIn(minimum, maximum)
    }

    private fun emitValue() {
        emitter.emit(
            NativeViewEventKind.CHANGE,
            value.toString().encodeToByteArray(),
        )
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = value.toString()
        }
    }

    private fun emitDismiss() {
        if (!dismissible) return
        emitter.emit(
            NativeViewEventKind.NATIVE,
            WireMap.encode(
                mapOf(
                    "action" to WireValue.Integer(HostAction.DISMISS.value),
                    "dismissed" to WireValue.Flag(true),
                ),
            ),
        )
    }

    private fun Behavior.isOverlay(): Boolean =
        this in setOf(
            Behavior.OVERLAY,
            Behavior.BOTTOM_SHEET,
            Behavior.DRAWER,
            Behavior.MODAL,
            Behavior.ALERT_DIALOG,
            Behavior.POPOVER,
            Behavior.MENU,
            Behavior.TOOLTIP,
            Behavior.IMAGE_VIEWER,
            Behavior.PORTAL,
        )

    private fun Behavior.isAnchoredOverlay(): Boolean =
        this == Behavior.POPOVER || this == Behavior.MENU || this == Behavior.TOOLTIP

    private fun Map<String, WireValue>.integer(key: String, fallback: Long): Long =
        (this[key] as? WireValue.Integer)?.value ?: fallback

    private fun Map<String, WireValue>.decimal(key: String, fallback: Double): Double =
        when (val value = this[key]) {
            is WireValue.Decimal -> value.value
            is WireValue.Integer -> value.value.toDouble()
            else -> fallback
        }

    private fun Map<String, WireValue>.flag(key: String, fallback: Boolean): Boolean =
        (this[key] as? WireValue.Flag)?.value ?: fallback

    private fun Map<String, WireValue>.text(key: String): String? =
        (this[key] as? WireValue.Text)?.value

    private fun Boolean.toEventPayload(): ByteArray =
        if (this) byteArrayOf('1'.code.toByte()) else byteArrayOf('0'.code.toByte())

    private fun Any?.toEventPayload(fallback: Int): ByteArray =
        when (this) {
            is String -> encodeToByteArray()
            is Long -> toString().encodeToByteArray()
            is Int -> toString().encodeToByteArray()
            is Double -> toString().encodeToByteArray()
            is Float -> toString().encodeToByteArray()
            is Boolean -> toEventPayload()
            else -> fallback.toString().encodeToByteArray()
        }
}
