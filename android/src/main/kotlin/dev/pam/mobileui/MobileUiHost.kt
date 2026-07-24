package dev.pam.mobileui

import android.animation.ObjectAnimator
import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.app.Activity
import android.app.DatePickerDialog
import android.app.Dialog
import android.app.TimePickerDialog
import android.content.Context
import android.content.ContextWrapper
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Rect
import android.graphics.RenderEffect
import android.graphics.RectF
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
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityManager
import android.view.accessibility.AccessibilityNodeInfo
import android.view.accessibility.AccessibilityNodeProvider
import android.widget.FrameLayout
import android.widget.TextView
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEmitter
import dev.pam.nativeapp.views.NativeViewEventKind
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.Instant
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.ZoneOffset
import java.time.format.DateTimeParseException
import java.time.format.DateTimeFormatter
import java.util.LinkedHashSet
import java.util.Locale
import kotlin.math.abs
import kotlin.math.ceil
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
        PORTAL(23),
        ACCORDION_GROUP(24);

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
        NAVIGATE(5),
    }

    private enum class ComponentMode(val value: Int) {
        SINGLE(1),
        MULTIPLE(2),
        RANGE(3),
        DATE(4),
        TIME(5),
        DATETIME(6);

        companion object {
            fun from(value: Int): ComponentMode =
                entries.firstOrNull { it.value == value } ?: SINGLE
        }
    }

    private val density = resources.displayMetrics.density
    private val trackPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.rgb(229, 229, 229)
    }
    private val fillPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.rgb(23, 23, 23)
    }
    private val calendarTextPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.rgb(23, 23, 23)
        textAlign = Paint.Align.CENTER
        textSize = 14f * density
    }
    private var behavior = Behavior.CONTAINER
    private var component = 0
    private var expanded = false
    private var checked = false
    private var selected = false
    private var open = true
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
    private var componentMode = ComponentMode.DATETIME
    private var dateTimeValue: String? = null
    private var minimumDate: String? = null
    private var maximumDate: String? = null
    private var minimumLocalDate: LocalDate? = null
    private var maximumLocalDate: LocalDate? = null
    private var is24Hour = false
    private var timeZoneOffsetInMinutes: Int? = null
    private var calendarYear = LocalDate.now().year
    private var calendarMonth = LocalDate.now().monthValue
    private var minimumCalendarYear: Int? = null
    private var maximumCalendarYear: Int? = null
    private var firstDayOfWeek = 0
    private var showOutsideDays = true
    private var fixedWeeks = false
    private var readOnly = false
    private var collapsible = true
    private var calendarLocale = Locale.getDefault()
    private var calendarSelectedTextColor = Color.WHITE
    private val selectedDates = LinkedHashSet<LocalDate>()
    private val disabledCalendarDates = HashSet<LocalDate>()
    private var rangeFrom: LocalDate? = null
    private var rangeTo: LocalDate? = null
    private var pressedCalendarTarget = CALENDAR_TARGET_NONE
    private var accessibilityFocusedCalendarCell = CALENDAR_TARGET_NONE
    private val accessibilityManager by lazy {
        context.getSystemService(AccessibilityManager::class.java)
    }
    @Suppress("DEPRECATION")
    private val calendarAccessibilityProvider = object : AccessibilityNodeProvider() {
        override fun createAccessibilityNodeInfo(virtualViewId: Int): AccessibilityNodeInfo? {
            if (behavior != Behavior.CALENDAR) return null
            if (virtualViewId == HOST_VIEW_ID) {
                return AccessibilityNodeInfo.obtain(this@MobileUiHost).also {
                    this@MobileUiHost.onInitializeAccessibilityNodeInfo(it)
                }
            }

            return calendarVirtualNode(virtualViewId)
        }

        override fun performAction(
            virtualViewId: Int,
            action: Int,
            arguments: Bundle?,
        ): Boolean {
            if (behavior != Behavior.CALENDAR) return false
            if (virtualViewId == HOST_VIEW_ID) {
                return this@MobileUiHost.performAccessibilityAction(action, arguments)
            }
            if (virtualViewId !in calendarCellRange()) return false

            return when (action) {
                AccessibilityNodeInfo.ACTION_CLICK -> {
                    val selected = selectCalendarDate(calendarDateAt(virtualViewId))
                    if (selected) {
                        sendCalendarVirtualEvent(
                            virtualViewId,
                            AccessibilityEvent.TYPE_VIEW_CLICKED,
                        )
                    }
                    selected
                }
                AccessibilityNodeInfo.ACTION_ACCESSIBILITY_FOCUS -> {
                    if (accessibilityFocusedCalendarCell == virtualViewId) return true
                    val previous = accessibilityFocusedCalendarCell
                    accessibilityFocusedCalendarCell = virtualViewId
                    if (previous >= 0) {
                        sendCalendarVirtualEvent(
                            previous,
                            AccessibilityEvent.TYPE_VIEW_ACCESSIBILITY_FOCUS_CLEARED,
                        )
                    }
                    sendCalendarVirtualEvent(
                        virtualViewId,
                        AccessibilityEvent.TYPE_VIEW_ACCESSIBILITY_FOCUSED,
                    )
                    invalidate()
                    true
                }
                AccessibilityNodeInfo.ACTION_CLEAR_ACCESSIBILITY_FOCUS -> {
                    if (accessibilityFocusedCalendarCell != virtualViewId) return false
                    accessibilityFocusedCalendarCell = CALENDAR_TARGET_NONE
                    sendCalendarVirtualEvent(
                        virtualViewId,
                        AccessibilityEvent.TYPE_VIEW_ACCESSIBILITY_FOCUS_CLEARED,
                    )
                    invalidate()
                    true
                }
                else -> false
            }
        }
    }
    private var dragOrigin = 0f
    private var dragOriginSecondary = 0f
    private var dragging = false
    private var accordionTouchActive = false
    private var pressedSelectionIndex = -1
    private var imageScale = 1f
    private var imageTranslationX = 0f
    private var imageTranslationY = 0f
    private var animator: ValueAnimator? = null
    private var activePickerDialog: Dialog? = null
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
        val previousOpen = open
        val previousComponentMode = componentMode
        behavior = Behavior.from(properties.integer("behavior", behavior.value.toLong()).toInt())
        component = properties.integer("component", component.toLong()).toInt()
        expanded = properties.flag("expanded", properties.flag("isExpanded", expanded))
        checked = properties.flag("checked", properties.flag("isChecked", checked))
        selected = properties.flag("selected", properties.flag("isSelected", selected))
        open = properties.flag("open", properties.flag("isOpen", open))
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
        componentMode = ComponentMode.from(
            properties.integer(
                "mode",
                properties.integer(
                    "type",
                    if (
                        behavior == Behavior.CALENDAR
                        || behavior == Behavior.ACCORDION_GROUP
                    ) {
                        ComponentMode.SINGLE.value.toLong()
                    } else {
                        ComponentMode.DATETIME.value.toLong()
                    },
                ),
            ).toInt(),
        )
        dateTimeValue = properties.text("value") ?: dateTimeValue
        minimumDate = properties.text("minDate") ?: properties.text("minimumDate")
        maximumDate = properties.text("maxDate") ?: properties.text("maximumDate")
        minimumLocalDate = minimumDate?.let(::parseDate)
        maximumLocalDate = maximumDate?.let(::parseDate)
        is24Hour = properties.flag("is24Hour", is24Hour)
        timeZoneOffsetInMinutes = properties.integerOrNull("timeZoneOffsetInMinutes")
        calendarYear = properties.integer("year", calendarYear.toLong()).toInt()
        calendarMonth = properties.integer("month", calendarMonth.toLong()).toInt().coerceIn(1, 12)
        minimumCalendarYear = properties.integerOrNull("minYear")
        maximumCalendarYear = properties.integerOrNull("maxYear")
        firstDayOfWeek = properties.integer("firstDayOfWeek", 0L).toInt().coerceIn(0, 6)
        showOutsideDays = properties.flag("showOutsideDays", true)
        fixedWeeks = properties.flag("fixedWeeks", false)
        readOnly = properties.flag("readOnly", properties.flag("isReadOnly", false))
        collapsible = properties.flag(
            "collapsible",
            properties.flag("isCollapsible", true),
        )
        calendarLocale = properties.text("locale")
            ?.let(Locale::forLanguageTag)
            ?.takeUnless { it.language.isEmpty() }
            ?: Locale.getDefault()
        updateCalendarSelection(properties, previousComponentMode != componentMode)
        isEnabled = !properties.flag("disabled", false)
        if ((!isEnabled || behavior != Behavior.DATE_TIME_PICKER) && activePickerDialog != null) {
            activePickerDialog?.dismiss()
            activePickerDialog = null
        }
        isClickable = !behavior.isOverlay() || open
        isFocusable = !behavior.isOverlay() || open
        isSelected = selected
        isActivated = checked || expanded
        contentDescription = properties.text("accessibilityLabel")
        tooltipText = properties.text("accessibilityHint")
        trackPaint.color = properties.integer("trackColor", trackPaint.color.toLong()).toInt()
        fillPaint.color = properties.integer("fillColor", fillPaint.color.toLong()).toInt()
        calendarTextPaint.color = properties.integer(
            "foregroundColor",
            calendarTextPaint.color.toLong(),
        ).toInt()
        calendarSelectedTextColor = properties.integer(
            "selectedForegroundColor",
            calendarSelectedTextColor.toLong(),
        ).toInt()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = properties.text("stateDescription")
        }

        if (previousBehavior != behavior) {
            installBehavior()
            if (open) {
                animateEntrance()
            }
            if (behavior.isOverlay() && open) {
                isFocusableInTouchMode = true
                captureAndMoveFocus()
            }
        } else if (previousOpen != open && behavior.isOverlay()) {
            if (open) {
                animateEntrance()
                captureAndMoveFocus()
            } else {
                animate().cancel()
                translationX = 0f
                translationY = 0f
                restoreFocus()
            }
        }
        if (previousExpanded != expanded) {
            animateExpanded()
        }
        if (previousSelected != selected && behavior == Behavior.TABS) {
            sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_SELECTED)
        }
        scheduleToast(properties)
        applyComponentDefaults()
        updateCalendarTitle()
        invalidate()
    }

    override fun onViewAdded(child: View) {
        super.onViewAdded(child)
        if (behavior == Behavior.ACCORDION) {
            applyAccordionState(animate = false)
            updateAccordionAccessibility()
        }
        if (behavior == Behavior.CALENDAR) {
            post(::updateCalendarTitle)
        }
    }

    override fun dispatchDraw(canvas: Canvas) {
        super.dispatchDraw(canvas)
        when (behavior) {
            Behavior.SLIDER -> drawSlider(canvas)
            Behavior.PROGRESS -> drawProgress(canvas)
            Behavior.CHECKBOX -> drawCheckbox(canvas, radio = false)
            Behavior.RADIO -> drawCheckbox(canvas, radio = true)
            Behavior.CALENDAR -> drawCalendar(canvas)
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

    override fun onInterceptTouchEvent(event: MotionEvent): Boolean {
        if (behavior == Behavior.DATE_TIME_PICKER && isEnabled) {
            return true
        }
        if (
            behavior == Behavior.ACCORDION
            && isEnabled
            && event.actionMasked == MotionEvent.ACTION_DOWN
            && accordionTriggerBounds().contains(event.x, event.y)
        ) {
            return true
        }

        return super.onInterceptTouchEvent(event)
    }

    override fun onTouchEvent(event: MotionEvent): Boolean {
        if (behavior != Behavior.ACCORDION) {
            return super.onTouchEvent(event)
        }
        if (!isEnabled) return false

        return when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> {
                accordionTouchActive = accordionTriggerBounds().contains(event.x, event.y)
                accordionTouchActive
            }
            MotionEvent.ACTION_MOVE -> accordionTouchActive
            MotionEvent.ACTION_UP -> {
                val activate = accordionTouchActive
                    && accordionTriggerBounds().contains(event.x, event.y)
                accordionTouchActive = false
                if (activate) performClick()
                activate
            }
            MotionEvent.ACTION_CANCEL -> {
                val claimed = accordionTouchActive
                accordionTouchActive = false
                claimed
            }
            else -> accordionTouchActive
        }
    }

    override fun performClick(): Boolean = super.performClick()

    override fun onVisibilityChanged(changedView: View, visibility: Int) {
        super.onVisibilityChanged(changedView, visibility)
        if (changedView !== this || !behavior.isOverlay()) return
        if (visibility == VISIBLE) {
            captureAndMoveFocus()
        } else {
            restoreFocus()
        }
    }

    override fun getAccessibilityNodeProvider(): AccessibilityNodeProvider? =
        if (behavior == Behavior.CALENDAR) {
            calendarAccessibilityProvider
        } else {
            super.getAccessibilityNodeProvider()
        }

    override fun dispatchHoverEvent(event: MotionEvent): Boolean {
        if (
            behavior == Behavior.CALENDAR
            && accessibilityManager?.isTouchExplorationEnabled == true
        ) {
            val target = calendarTargetAt(event.x, event.y)
            when (event.actionMasked) {
                MotionEvent.ACTION_HOVER_ENTER,
                MotionEvent.ACTION_HOVER_MOVE,
                -> if (target >= 0) {
                    calendarAccessibilityProvider.performAction(
                        target,
                        AccessibilityNodeInfo.ACTION_ACCESSIBILITY_FOCUS,
                        null,
                    )
                    return true
                }
                MotionEvent.ACTION_HOVER_EXIT -> {
                    val focused = accessibilityFocusedCalendarCell
                    if (focused >= 0) {
                        calendarAccessibilityProvider.performAction(
                            focused,
                            AccessibilityNodeInfo.ACTION_CLEAR_ACCESSIBILITY_FOCUS,
                            null,
                        )
                        return true
                    }
                }
            }
        }

        return super.dispatchHoverEvent(event)
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
        info.isScrollable = behavior in setOf(
            Behavior.BOTTOM_SHEET,
            Behavior.CALENDAR,
            Behavior.IMAGE_VIEWER,
            Behavior.SLIDER,
        )
        if (behavior == Behavior.ACCORDION) {
            info.className = "android.widget.Button"
            info.isClickable = isEnabled
            info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            info.addAction(
                if (expanded) {
                    AccessibilityNodeInfo.ACTION_COLLAPSE
                } else {
                    AccessibilityNodeInfo.ACTION_EXPAND
                },
            )
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
                if (orientation == 2) {
                    info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_UP)
                    info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_DOWN)
                } else {
                    info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_LEFT)
                    info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT)
                }
            }
        }
        if (behavior == Behavior.PROGRESS) {
            info.className = "android.widget.ProgressBar"
        }
        if (behavior == Behavior.CALENDAR) {
            info.className = "android.widget.CalendarView"
            info.addAction(AccessibilityNodeInfo.ACTION_SCROLL_FORWARD)
            info.addAction(AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD)
            info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_LEFT)
            info.addAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT)
            info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                calendarRowCount(),
                DAYS_PER_WEEK,
                false,
            )
            calendarCellRange().forEach { index ->
                val date = calendarDateAt(index)
                if (showOutsideDays || date.monthValue == calendarMonth) {
                    info.addChild(this, index)
                }
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
        if (behavior == Behavior.DATE_TIME_PICKER) {
            info.className = if (componentMode == ComponentMode.TIME) {
                "android.widget.TimePicker"
            } else {
                "android.widget.DatePicker"
            }
            info.isClickable = isEnabled
            if (isEnabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                stateDescription = dateTimeValue
            }
        }
    }

    override fun performAccessibilityAction(action: Int, arguments: Bundle?): Boolean {
        val sliderActions = setOf(
            AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
            AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD,
            AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_LEFT.id,
            AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT.id,
            AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_UP.id,
            AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_DOWN.id,
        )
        if (behavior == Behavior.SLIDER && action in sliderActions) {
            val positive = action in setOf(
                AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT.id,
                AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_UP.id,
            )
            val direction = if (positive) 1.0 else -1.0
            value = snapped(value + direction * step)
            emitValue()
            invalidate()
            return true
        }
        if (
            behavior == Behavior.CALENDAR
            && action in setOf(
                AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD,
                AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_LEFT.id,
                AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT.id,
            )
        ) {
            val forward = action == AccessibilityNodeInfo.ACTION_SCROLL_FORWARD
                || action == AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT.id
            return navigateCalendar(if (forward) 1 else -1)
        }
        if (
            action == AccessibilityNodeInfo.ACTION_DISMISS
            && behavior.isOverlay()
            && dismissible
        ) {
            emitDismiss()
            return true
        }
        if (
            behavior == Behavior.DATE_TIME_PICKER
            && action == AccessibilityNodeInfo.ACTION_CLICK
            && isEnabled
        ) {
            showDateTimePicker()
            return true
        }
        if (
            behavior == Behavior.ACCORDION
            && isEnabled
            && action in setOf(
                AccessibilityNodeInfo.ACTION_CLICK,
                AccessibilityNodeInfo.ACTION_EXPAND,
                AccessibilityNodeInfo.ACTION_COLLAPSE,
            )
        ) {
            val requested = when (action) {
                AccessibilityNodeInfo.ACTION_EXPAND -> true
                AccessibilityNodeInfo.ACTION_COLLAPSE -> false
                else -> !expanded
            }
            setAccordionExpanded(requested)
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
        activePickerDialog?.dismiss()
        activePickerDialog = null
        accordionTouchActive = false
        animate().cancel()
        setOnTouchListener(null)
        setOnClickListener(null)
        imageScale = 1f
        imageTranslationX = 0f
        imageTranslationY = 0f
        applyImageTransform()
        restoreFocus()
        accessibilityFocusedCalendarCell = CALENDAR_TARGET_NONE
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
            behavior == Behavior.RADIO
        ) {
            setOnClickListener {
                if (behavior == Behavior.RADIO) {
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
        } else if (behavior == Behavior.ACCORDION) {
            setOnClickListener {
                if (isEnabled) {
                    setAccordionExpanded(!expanded)
                }
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
            Behavior.ACCORDION,
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
        importantForAccessibility = when {
            behavior == Behavior.ACCORDION_GROUP -> IMPORTANT_FOR_ACCESSIBILITY_NO
            behavior.isOverlay() && !open -> IMPORTANT_FOR_ACCESSIBILITY_NO
            else -> IMPORTANT_FOR_ACCESSIBILITY_YES
        }
    }

    private fun animateExpanded() {
        applyAccordionState(animate = true)
    }

    private fun applyAccordionState(animate: Boolean) {
        val content = accordionContentViews()
        val icon = findTaggedDescendant(this, ACCORDION_ICON_TAG)
        val targetRotation = if (expanded) ACCORDION_EXPANDED_ROTATION else 0f

        content.forEach { child ->
            child.animate().cancel()
            child.importantForAccessibility = if (expanded) {
                IMPORTANT_FOR_ACCESSIBILITY_AUTO
            } else {
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            }
            if (expanded) {
                child.visibility = VISIBLE
            }
            if (!animate || !animationsEnabled()) {
                child.alpha = if (expanded) 1f else 0f
                child.scaleY = if (expanded) 1f else ACCORDION_COLLAPSED_SCALE
                child.visibility = if (expanded) VISIBLE else GONE
            } else {
                child.animate()
                    .alpha(if (expanded) 1f else 0f)
                    .scaleY(if (expanded) 1f else ACCORDION_COLLAPSED_SCALE)
                    .setDuration(ACCORDION_ANIMATION_DURATION_MILLIS)
                    .withEndAction {
                        if (!expanded) {
                            child.visibility = GONE
                        }
                    }
                    .start()
            }
        }

        icon?.animate()?.cancel()
        if (icon != null) {
            if (animate && animationsEnabled()) {
                icon.animate()
                    .rotation(targetRotation)
                    .setDuration(ACCORDION_ANIMATION_DURATION_MILLIS)
                    .start()
            } else {
                icon.rotation = targetRotation
            }
        }
        updateAccordionAccessibility()
    }

    private fun setAccordionExpanded(
        requested: Boolean,
        emit: Boolean = true,
        fromGroup: Boolean = false,
    ) {
        if (!fromGroup && !requested && !collapsible) return
        if (!fromGroup && !accordionGroupAllows(requested)) return
        if (expanded == requested) return
        expanded = requested
        isActivated = requested
        animateExpanded()
        performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
        if (emit) {
            emitter.emit(NativeViewEventKind.TOGGLE, requested.toEventPayload())
        }
        sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)
    }

    private fun accordionGroupAllows(requested: Boolean): Boolean {
        var ancestor = parent
        while (ancestor is View) {
            if (
                ancestor is MobileUiHost
                && ancestor.behavior == Behavior.ACCORDION_GROUP
            ) {
                return ancestor.handleAccordionItemRequest(this, requested)
            }
            ancestor = ancestor.parent
        }
        return requested || collapsible
    }

    private fun handleAccordionItemRequest(
        item: MobileUiHost,
        requested: Boolean,
    ): Boolean {
        if (!isEnabled || (!requested && !collapsible)) return false
        if (requested && componentMode == ComponentMode.SINGLE) {
            accordionItems(this).forEach { sibling ->
                if (sibling !== item && sibling.expanded) {
                    sibling.setAccordionExpanded(
                        requested = false,
                        emit = false,
                        fromGroup = true,
                    )
                }
            }
        }
        return true
    }

    private fun accordionItems(root: ViewGroup): Sequence<MobileUiHost> =
        sequence {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                when {
                    child is MobileUiHost
                        && child.behavior == Behavior.ACCORDION -> yield(child)
                    child is MobileUiHost
                        && child.behavior == Behavior.ACCORDION_GROUP -> Unit
                    child is ViewGroup -> yieldAll(accordionItems(child))
                }
            }
        }

    private fun updateAccordionAccessibility() {
        if (behavior != Behavior.ACCORDION) return
        val trigger = findTaggedDescendant(this, ACCORDION_TRIGGER_TAG)
        val title = findFirstText(trigger ?: getChildAtOrNull(0))
        if (contentDescription.isNullOrEmpty() && !title.isNullOrEmpty()) {
            contentDescription = title
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = if (expanded) "Expanded" else "Collapsed"
        }
    }

    private fun animateEntrance() {
        if (!open) return
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
            if (!acceptsOverlayInteraction()) return@OnTouchListener false
            val horizontal = anchor == 1 || anchor == 2
            val coordinate = if (horizontal) event.rawX else event.rawY
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    if (!isDrawerHandle(event.x, event.y)) {
                        dragging = false
                        return@OnTouchListener false
                    }
                    dragging = true
                    dragOrigin = coordinate
                    animate().cancel()
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    if (!dragging) return@OnTouchListener false
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
                    if (!dragging) return@OnTouchListener false
                    dragging = false
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
                !acceptsOverlayInteraction()
                || !dismissible
                || !closeOnOverlayClick
                || event.actionMasked != MotionEvent.ACTION_UP
            ) {
                return@OnTouchListener false
            }
            val content = overlayContent()
            val insideContent = content != null
                && boundsInHost(content).contains(event.x, event.y)
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
            if (!acceptsOverlayInteraction()) return@OnTouchListener false
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    if (!isSheetHandle(event.x, event.y)) {
                        dragging = false
                        return@OnTouchListener false
                    }
                    dragging = true
                    dragOrigin = event.rawY
                    animate().cancel()
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    if (!dragging) return@OnTouchListener false
                    translationY = max(0f, event.rawY - dragOrigin)
                    true
                }
                MotionEvent.ACTION_UP,
                MotionEvent.ACTION_CANCEL,
                -> {
                    if (!dragging) return@OnTouchListener false
                    dragging = false
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
            val index = selectionIndexAt(list, event.x, event.y)
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    pressedSelectionIndex = index
                    index >= 0
                }
                MotionEvent.ACTION_UP -> {
                    if (index < 0 || index != pressedSelectionIndex) {
                        pressedSelectionIndex = -1
                        return@OnTouchListener false
                    }
                    pressedSelectionIndex = -1
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
                MotionEvent.ACTION_CANCEL -> {
                    val claimed = pressedSelectionIndex >= 0
                    pressedSelectionIndex = -1
                    claimed
                }
                else -> false
            }
        }

    private fun calendarTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled || readOnly) return@OnTouchListener false
            val target = calendarTargetAt(event.x, event.y)
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    pressedCalendarTarget = target
                    target != CALENDAR_TARGET_NONE
                }
                MotionEvent.ACTION_UP -> {
                    if (
                        target == CALENDAR_TARGET_NONE
                        || target != pressedCalendarTarget
                    ) {
                        pressedCalendarTarget = CALENDAR_TARGET_NONE
                        return@OnTouchListener false
                    }
                    pressedCalendarTarget = CALENDAR_TARGET_NONE
                    val handled = when (target) {
                        CALENDAR_TARGET_PREVIOUS -> navigateCalendar(-1)
                        CALENDAR_TARGET_NEXT -> navigateCalendar(1)
                        else -> selectCalendarDate(calendarDateAt(target))
                    }
                    if (handled) {
                        performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                        performClick()
                    }
                    handled
                }
                MotionEvent.ACTION_CANCEL -> {
                    val claimed = pressedCalendarTarget != CALENDAR_TARGET_NONE
                    pressedCalendarTarget = CALENDAR_TARGET_NONE
                    claimed
                }
                else -> false
            }
        }

    private fun showDateTimePicker() {
        if (!isEnabled) return
        if (activePickerDialog?.isShowing == true) return
        val activity = context.findActivity() ?: return
        if (activity.isFinishing || activity.isDestroyed) return
        val initial = parsedDateTime()

        if (componentMode == ComponentMode.TIME) {
            showTimePicker(activity, initial.toLocalDate(), initial.toLocalTime())
            return
        }

        val initialDate = clampedDate(initial.toLocalDate())
        lateinit var dialog: DatePickerDialog
        dialog = DatePickerDialog(
            activity,
            { _, year, zeroBasedMonth, day ->
                val date = LocalDate.of(year, zeroBasedMonth + 1, day)
                if (componentMode == ComponentMode.DATETIME) {
                    showTimePicker(activity, date, initial.toLocalTime())
                } else {
                    emitDateTime(LocalDateTime.of(date, LocalTime.MIDNIGHT))
                }
            },
            initialDate.year,
            initialDate.monthValue - 1,
            initialDate.dayOfMonth,
        ).apply {
            minimumLocalDate?.let { datePicker.minDate = it.toEpochMillis() }
            maximumLocalDate?.let { datePicker.maxDate = it.toEpochMillis() }
            setOnCancelListener { emitDismiss() }
            setOnDismissListener {
                if (activePickerDialog === dialog) {
                    activePickerDialog = null
                }
            }
        }
        activePickerDialog = dialog
        dialog.show()
    }

    private fun showTimePicker(
        activity: Activity,
        date: LocalDate,
        time: LocalTime,
    ) {
        lateinit var dialog: TimePickerDialog
        dialog = TimePickerDialog(
            activity,
            { _, hour, minute ->
                emitDateTime(LocalDateTime.of(date, LocalTime.of(hour, minute)))
            },
            time.hour,
            time.minute,
            is24Hour,
        ).apply {
            setOnCancelListener { emitDismiss() }
            setOnDismissListener {
                if (activePickerDialog === dialog) {
                    activePickerDialog = null
                }
            }
        }
        activePickerDialog = dialog
        dialog.show()
    }

    private fun emitDateTime(dateTime: LocalDateTime) {
        dateTimeValue = when (componentMode) {
            ComponentMode.DATE -> dateTime.toLocalDate().toString()
            ComponentMode.TIME -> dateTime.toLocalTime().toString()
            else -> timeZoneOffsetInMinutes
                ?.let(::zoneOffset)
                ?.let(dateTime::atOffset)
                ?.toString()
                ?: dateTime.toString()
        }
        emitter.emit(
            NativeViewEventKind.CHANGE,
            dateTimeValue.orEmpty().encodeToByteArray(),
        )
    }

    private fun parsedDateTime(): LocalDateTime {
        val raw = dateTimeValue ?: return LocalDateTime.now()

        if (componentMode == ComponentMode.TIME) {
            return try {
                LocalTime.parse(raw).atDate(LocalDate.now())
            } catch (_: DateTimeParseException) {
                LocalDateTime.now()
            }
        }
        parseDate(raw)?.let { date ->
            if (componentMode == ComponentMode.DATE || raw.length <= 10) {
                return date.atStartOfDay()
            }
        }

        return parseOffsetDateTime(raw)
            ?: try {
                LocalDateTime.parse(raw)
            } catch (_: DateTimeParseException) {
                LocalDateTime.now()
            }
    }

    private fun parseOffsetDateTime(raw: String): LocalDateTime? {
        val targetOffset = timeZoneOffsetInMinutes?.let(::zoneOffset)
            ?: ZoneId.systemDefault().rules.getOffset(Instant.now())

        return try {
            OffsetDateTime.parse(raw)
                .withOffsetSameInstant(targetOffset)
                .toLocalDateTime()
        } catch (_: DateTimeParseException) {
            try {
                Instant.parse(raw)
                    .atOffset(targetOffset)
                    .toLocalDateTime()
            } catch (_: DateTimeParseException) {
                null
            }
        }
    }

    private fun clampedDate(date: LocalDate): LocalDate =
        date
            .let { value -> minimumLocalDate?.let { maxOf(value, it) } ?: value }
            .let { value -> maximumLocalDate?.let { minOf(value, it) } ?: value }

    private fun zoneOffset(minutes: Int): ZoneOffset =
        ZoneOffset.ofTotalSeconds(
            minutes.coerceIn(MIN_TIME_ZONE_OFFSET_MINUTES, MAX_TIME_ZONE_OFFSET_MINUTES)
                * SECONDS_PER_MINUTE,
        )

    private fun Context.findActivity(): Activity? =
        when (this) {
            is Activity -> this
            is ContextWrapper -> baseContext
                .takeUnless { it === this }
                ?.findActivity()
            else -> null
        }

    private fun parseDate(value: String): LocalDate? =
        try {
            LocalDate.parse(value.take(10))
        } catch (_: DateTimeParseException) {
            null
        }

    private fun LocalDate.toEpochMillis(): Long =
        atStartOfDay(ZoneId.systemDefault()).toInstant().toEpochMilli()

    private fun updateCalendarSelection(
        properties: Map<String, WireValue>,
        modeChanged: Boolean,
    ) {
        if (behavior != Behavior.CALENDAR) return

        if (modeChanged) {
            selectedDates.clear()
            rangeFrom = null
            rangeTo = null
        }

        disabledCalendarDates.clear()
        properties.text("disabledDates")
            .orEmpty()
            .lineSequence()
            .mapNotNull(::parseDate)
            .forEach(disabledCalendarDates::add)

        when (componentMode) {
            ComponentMode.SINGLE -> {
                val key = when {
                    properties.containsKey("value") -> "value"
                    properties.containsKey("defaultValue") -> "defaultValue"
                    else -> null
                }
                if (key != null) {
                    selectedDates.clear()
                    properties.text(key)?.let(::parseDate)?.let(selectedDates::add)
                }
            }
            ComponentMode.MULTIPLE -> {
                if (properties.containsKey("selectedValues")) {
                    selectedDates.clear()
                    properties.text("selectedValues")
                        .orEmpty()
                        .lineSequence()
                        .mapNotNull(::parseDate)
                        .forEach(selectedDates::add)
                }
            }
            ComponentMode.RANGE -> {
                if (
                    modeChanged
                    || properties.containsKey("rangeFrom")
                    || properties.containsKey("rangeTo")
                ) {
                    rangeFrom = properties.text("rangeFrom")?.let(::parseDate)
                    rangeTo = properties.text("rangeTo")?.let(::parseDate)
                }
            }
            else -> Unit
        }
    }

    private fun calendarFirstDate(): LocalDate =
        LocalDate.of(calendarYear, calendarMonth, 1)

    private fun calendarStartOffset(): Int {
        val firstDay = calendarFirstDate().dayOfWeek.value % DAYS_PER_WEEK
        return (firstDay - firstDayOfWeek + DAYS_PER_WEEK) % DAYS_PER_WEEK
    }

    private fun calendarRowCount(): Int {
        if (fixedWeeks) return MAX_CALENDAR_ROWS
        val occupiedCells = calendarStartOffset() + calendarFirstDate().lengthOfMonth()

        return ceil(occupiedCells / DAYS_PER_WEEK.toDouble())
            .toInt()
            .coerceIn(MIN_CALENDAR_ROWS, MAX_CALENDAR_ROWS)
    }

    private fun calendarDateAt(index: Int): LocalDate =
        calendarFirstDate()
            .minusDays(calendarStartOffset().toLong())
            .plusDays(index.toLong())

    private fun calendarCellRange(): IntRange =
        0 until calendarRowCount() * DAYS_PER_WEEK

    @Suppress("DEPRECATION")
    private fun calendarVirtualNode(index: Int): AccessibilityNodeInfo? {
        if (index !in calendarCellRange()) return null
        val date = calendarDateAt(index)
        if (!showOutsideDays && date.monthValue != calendarMonth) return null
        val selectedDate = calendarDateSelected(date)
        val cell = calendarCellBounds(index)
        val screen = IntArray(2)
        getLocationOnScreen(screen)
        val screenBounds = Rect(cell).apply {
            offset(screen[0], screen[1])
        }

        return AccessibilityNodeInfo.obtain().apply {
            setSource(this@MobileUiHost, index)
            setParent(this@MobileUiHost)
            packageName = context.packageName
            className = "android.widget.Button"
            text = date.dayOfMonth.toString()
            contentDescription = date.format(
                DateTimeFormatter.ofPattern("EEEE, MMMM d, yyyy", calendarLocale),
            )
            isEnabled = !calendarDateDisabled(date)
            isClickable = isEnabled
            isFocusable = true
            isSelected = selectedDate
            isVisibleToUser = isShown
            isAccessibilityFocused = accessibilityFocusedCalendarCell == index
            setBoundsInParent(cell)
            setBoundsInScreen(screenBounds)
            collectionItemInfo = AccessibilityNodeInfo.CollectionItemInfo.obtain(
                index / DAYS_PER_WEEK,
                1,
                index % DAYS_PER_WEEK,
                1,
                false,
                selectedDate,
            )
            if (isEnabled) {
                addAction(AccessibilityNodeInfo.ACTION_CLICK)
            }
            if (isAccessibilityFocused) {
                addAction(AccessibilityNodeInfo.ACTION_CLEAR_ACCESSIBILITY_FOCUS)
            } else {
                addAction(AccessibilityNodeInfo.ACTION_ACCESSIBILITY_FOCUS)
            }
        }
    }

    private fun calendarCellBounds(index: Int): Rect {
        val bounds = calendarGridBounds()
        val rows = calendarRowCount()
        val cellWidth = bounds.width() / DAYS_PER_WEEK
        val cellHeight = bounds.height() / rows
        val column = index % DAYS_PER_WEEK
        val row = index / DAYS_PER_WEEK

        return Rect(
            (bounds.left + column * cellWidth).toInt(),
            (bounds.top + row * cellHeight).toInt(),
            (bounds.left + (column + 1) * cellWidth).toInt(),
            (bounds.top + (row + 1) * cellHeight).toInt(),
        )
    }

    @Suppress("DEPRECATION")
    private fun sendCalendarVirtualEvent(index: Int, kind: Int) {
        val event = AccessibilityEvent.obtain(kind).apply {
            packageName = context.packageName
            className = "android.widget.Button"
            setSource(this@MobileUiHost, index)
            text.add(calendarDateAt(index).dayOfMonth.toString())
        }
        parent?.requestSendAccessibilityEvent(this, event)
    }

    private fun calendarTargetAt(x: Float, y: Float): Int {
        if (expandedTaggedBounds("pam:calendar-prev").contains(x, y)) {
            return CALENDAR_TARGET_PREVIOUS
        }
        if (expandedTaggedBounds("pam:calendar-next").contains(x, y)) {
            return CALENDAR_TARGET_NEXT
        }

        val bounds = calendarGridBounds()
        if (!bounds.contains(x, y) || bounds.width() <= 0f || bounds.height() <= 0f) {
            return CALENDAR_TARGET_NONE
        }
        val column = (
            (x - bounds.left) / (bounds.width() / DAYS_PER_WEEK)
        ).toInt().coerceIn(0, DAYS_PER_WEEK - 1)
        val rows = calendarRowCount()
        val row = (
            (y - bounds.top) / (bounds.height() / rows)
        ).toInt().coerceIn(0, rows - 1)

        return row * DAYS_PER_WEEK + column
    }

    private fun expandedTaggedBounds(tag: String): RectF {
        val view = findTaggedDescendant(this, tag) ?: return RectF()
        val expansion = 8f * density

        return boundsInHost(view).apply {
            inset(-expansion, -expansion)
        }
    }

    private fun calendarDateDisabled(date: LocalDate): Boolean {
        if (!isEnabled || readOnly || date in disabledCalendarDates) return true
        if (!showOutsideDays && date.monthValue != calendarMonth) return true
        if (minimumLocalDate?.let { date < it } == true) return true
        if (maximumLocalDate?.let { date > it } == true) return true
        if (minimumCalendarYear?.let { date.year < it } == true) return true
        if (maximumCalendarYear?.let { date.year > it } == true) return true

        return false
    }

    private fun calendarDateSelected(date: LocalDate): Boolean =
        date in selectedDates || date == rangeFrom || date == rangeTo

    private fun selectCalendarDate(date: LocalDate): Boolean {
        if (calendarDateDisabled(date)) return false

        val payload = when (componentMode) {
            ComponentMode.MULTIPLE -> {
                if (!selectedDates.add(date)) {
                    selectedDates.remove(date)
                }
                buildString {
                    append("M\n")
                    append(selectedDates.sorted().joinToString("\n"))
                }
            }
            ComponentMode.RANGE -> {
                if (rangeFrom == null || rangeTo != null) {
                    rangeFrom = date
                    rangeTo = null
                } else {
                    val start = checkNotNull(rangeFrom)
                    rangeFrom = minOf(start, date)
                    rangeTo = maxOf(start, date)
                }
                "R\n${rangeFrom.orEmptyDate()}\n${rangeTo.orEmptyDate()}"
            }
            else -> {
                selectedDates.clear()
                selectedDates.add(date)
                date.toString()
            }
        }

        if (date.year != calendarYear || date.monthValue != calendarMonth) {
            calendarYear = date.year
            calendarMonth = date.monthValue
            updateCalendarTitle()
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = when (componentMode) {
                ComponentMode.MULTIPLE -> "${selectedDates.size} dates selected"
                ComponentMode.RANGE -> if (rangeTo == null) {
                    "Range starts ${rangeFrom.orEmptyDate()}"
                } else {
                    "${rangeFrom.orEmptyDate()} to ${rangeTo.orEmptyDate()}"
                }
                else -> date.toString()
            }
        }
        emitter.emit(NativeViewEventKind.CHANGE, payload.encodeToByteArray())
        sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_SELECTED)
        invalidate()

        return true
    }

    private fun navigateCalendar(monthDelta: Long): Boolean {
        val requested = calendarFirstDate().plusMonths(monthDelta)
        val firstAllowed = minimumLocalDate?.withDayOfMonth(1)
        val lastAllowed = maximumLocalDate?.withDayOfMonth(1)
        val allowed = when {
            minimumCalendarYear?.let { requested.year < it } == true -> false
            maximumCalendarYear?.let { requested.year > it } == true -> false
            firstAllowed != null && requested < firstAllowed -> false
            lastAllowed != null && requested > lastAllowed -> false
            else -> true
        }
        if (!allowed) return false

        calendarYear = requested.year
        calendarMonth = requested.monthValue
        updateCalendarTitle()
        invalidate()
        emitter.emit(
            NativeViewEventKind.NATIVE,
            WireMap.encode(
                mapOf(
                    "action" to WireValue.Integer(HostAction.NAVIGATE.value),
                    "year" to WireValue.Integer(calendarYear.toLong()),
                    "month" to WireValue.Integer(calendarMonth.toLong()),
                ),
            ),
        )
        sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)

        return true
    }

    private fun updateCalendarTitle() {
        if (behavior != Behavior.CALENDAR) return
        val title = findTaggedDescendant(this, "pam:calendar-title") as? TextView ?: return
        val month = calendarFirstDate()
            .format(DateTimeFormatter.ofPattern("MMMM yyyy", calendarLocale))
            .replaceFirstChar { character ->
                if (character.isLowerCase()) {
                    character.titlecase(calendarLocale)
                } else {
                    character.toString()
                }
            }
        title.text = month
        title.contentDescription = month
    }

    private fun LocalDate?.orEmptyDate(): String = this?.toString().orEmpty()

    private fun imageViewerTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!acceptsOverlayInteraction()) return@OnTouchListener false
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

    private fun drawCalendar(canvas: Canvas) {
        val grid = findTaggedDescendant(this, "pam:calendar-grid") ?: return
        if (grid is ViewGroup && grid.childCount > 0) return
        val bounds = boundsInHost(grid)
        if (bounds.width() <= 0f || bounds.height() <= 0f) return

        val rows = calendarRowCount()
        val cellWidth = bounds.width() / DAYS_PER_WEEK
        val cellHeight = bounds.height() / rows
        val radius = minOf(cellWidth, cellHeight) * 0.38f
        val today = LocalDate.now()
        val firstVisibleDate = calendarFirstDate().minusDays(calendarStartOffset().toLong())
        val originalFillAlpha = fillPaint.alpha
        val originalTrackAlpha = trackPaint.alpha
        val originalTrackStyle = trackPaint.style
        val originalTrackWidth = trackPaint.strokeWidth
        val originalTextAlpha = calendarTextPaint.alpha
        val originalTextColor = calendarTextPaint.color

        repeat(rows * DAYS_PER_WEEK) { index ->
            val date = firstVisibleDate.plusDays(index.toLong())
            val outside = date.monthValue != calendarMonth
            val disabled = calendarDateDisabled(date)
            val selectedDate = calendarDateSelected(date)
            val insideRange = rangeFrom?.let { start ->
                rangeTo?.let { end -> date > start && date < end }
            } == true
            val centerX = bounds.left + (index % DAYS_PER_WEEK + 0.5f) * cellWidth
            val centerY = bounds.top + (index / DAYS_PER_WEEK + 0.5f) * cellHeight

            if (insideRange) {
                fillPaint.alpha = RANGE_BACKGROUND_ALPHA
                canvas.drawRect(
                    centerX - cellWidth / 2f,
                    centerY - radius,
                    centerX + cellWidth / 2f,
                    centerY + radius,
                    fillPaint,
                )
            }
            if (selectedDate) {
                fillPaint.alpha = if (disabled) DISABLED_ALPHA else OPAQUE_ALPHA
                canvas.drawCircle(centerX, centerY, radius, fillPaint)
            }
            if (date == today && !selectedDate) {
                trackPaint.alpha = if (disabled) DISABLED_ALPHA else OPAQUE_ALPHA
                trackPaint.style = Paint.Style.STROKE
                trackPaint.strokeWidth = density
                canvas.drawCircle(centerX, centerY, radius, trackPaint)
            }
            if (!outside || showOutsideDays) {
                calendarTextPaint.alpha = when {
                    disabled -> DISABLED_ALPHA
                    outside -> OUTSIDE_MONTH_ALPHA
                    else -> OPAQUE_ALPHA
                }
                if (selectedDate) {
                    calendarTextPaint.color = calendarSelectedTextColor
                }
                val baseline = centerY - (
                    calendarTextPaint.ascent() + calendarTextPaint.descent()
                ) / 2f
                canvas.drawText(date.dayOfMonth.toString(), centerX, baseline, calendarTextPaint)
                if (selectedDate) {
                    calendarTextPaint.color = originalTextColor
                }
            }
        }

        fillPaint.alpha = originalFillAlpha
        trackPaint.alpha = originalTrackAlpha
        trackPaint.style = originalTrackStyle
        trackPaint.strokeWidth = originalTrackWidth
        calendarTextPaint.alpha = originalTextAlpha
        calendarTextPaint.color = originalTextColor
    }

    private fun children(): Sequence<View> =
        sequence {
            repeat(childCount) { index -> yield(getChildAt(index)) }
        }

    private fun accordionContentViews(): List<View> {
        val tagged = findTaggedDescendant(this, ACCORDION_CONTENT_TAG)
        if (tagged != null) return listOf(tagged)

        return children()
            .filterIndexed { index, _ -> index > 0 }
            .toList()
    }

    private fun accordionTriggerBounds(): RectF {
        val trigger = findTaggedDescendant(this, ACCORDION_TRIGGER_TAG)
            ?: getChildAtOrNull(0)
            ?: this

        return boundsInHost(trigger)
    }

    private fun findFirstText(root: View?): String? {
        if (root is TextView && root.text.isNotEmpty()) return root.text.toString()
        if (root !is ViewGroup) return null
        repeat(root.childCount) { index ->
            findFirstText(root.getChildAt(index))?.let { return it }
        }
        return null
    }

    private fun getChildAtOrNull(index: Int): View? =
        if (index in 0 until childCount) getChildAt(index) else null

    private fun overlayContent(): View? =
        if (childCount > 0) getChildAt(childCount - 1) else null

    internal fun isSheetHandle(x: Float, y: Float): Boolean {
        val content = if (childCount > 0) getChildAt(childCount - 1) else null
        if (content == null) return y <= 64f * density
        val bounds = boundsInHost(content)
        return x in bounds.left..bounds.right
            && y in bounds.top..minOf(bounds.bottom, bounds.top + 64f * density)
    }

    internal fun isCalendarGridPoint(x: Float, y: Float): Boolean =
        calendarGridBounds().contains(x, y)

    internal fun acceptsOverlayInteraction(): Boolean = isEnabled && open

    private fun isDrawerHandle(x: Float, y: Float): Boolean {
        val content = if (childCount > 0) getChildAt(childCount - 1) else null
            ?: return true
        val bounds = boundsInHost(content)
        val edge = 32f * density
        return when (anchor) {
            1 -> x in maxOf(bounds.left, bounds.right - edge)..bounds.right
                && y in bounds.top..bounds.bottom
            2 -> x in bounds.left..minOf(bounds.right, bounds.left + edge)
                && y in bounds.top..bounds.bottom
            3 -> y in maxOf(bounds.top, bounds.bottom - edge)..bounds.bottom
                && x in bounds.left..bounds.right
            else -> y in bounds.top..minOf(bounds.bottom, bounds.top + edge)
                && x in bounds.left..bounds.right
        }
    }

    private fun findTaggedDescendant(root: View, tag: String): View? {
        if (root.tag == tag) return root
        if (root !is ViewGroup) return null
        repeat(root.childCount) { index ->
            findTaggedDescendant(root.getChildAt(index), tag)?.let { return it }
        }
        return null
    }

    private fun calendarGridBounds(): RectF =
        boundsInHost(findTaggedDescendant(this, "pam:calendar-grid") ?: this)

    private fun selectionIndexAt(list: ViewGroup, x: Float, y: Float): Int {
        repeat(list.childCount) { index ->
            if (boundsInHost(list.getChildAt(index)).contains(x, y)) {
                return index
            }
        }
        return -1
    }

    private fun boundsInHost(view: View): RectF {
        if (view === this) return RectF(0f, 0f, width.toFloat(), height.toFloat())
        var current = view
        var left = 0f
        var top = 0f
        while (current !== this) {
            left += current.x
            top += current.y
            current = current.parent as? View
                ?: return RectF(0f, 0f, view.width.toFloat(), view.height.toFloat())
        }
        return RectF(left, top, left + view.width, top + view.height)
    }

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
        if (!open || !isShown) return
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

    private fun Map<String, WireValue>.integerOrNull(key: String): Int? =
        (this[key] as? WireValue.Integer)?.value?.toInt()

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

    private companion object {
        const val DAYS_PER_WEEK = 7
        const val MIN_CALENDAR_ROWS = 4
        const val MAX_CALENDAR_ROWS = 6
        const val CALENDAR_TARGET_NONE = -1
        const val CALENDAR_TARGET_PREVIOUS = -2
        const val CALENDAR_TARGET_NEXT = -3
        const val DISABLED_ALPHA = 76
        const val OUTSIDE_MONTH_ALPHA = 112
        const val RANGE_BACKGROUND_ALPHA = 42
        const val OPAQUE_ALPHA = 255
        const val ACCORDION_TRIGGER_TAG = "pam:accordion-trigger"
        const val ACCORDION_CONTENT_TAG = "pam:accordion-content"
        const val ACCORDION_ICON_TAG = "pam:accordion-icon"
        const val ACCORDION_EXPANDED_ROTATION = 180f
        const val ACCORDION_COLLAPSED_SCALE = 0.98f
        const val ACCORDION_ANIMATION_DURATION_MILLIS = 200L
        const val MIN_TIME_ZONE_OFFSET_MINUTES = -18 * 60
        const val MAX_TIME_ZONE_OFFSET_MINUTES = 18 * 60
        const val SECONDS_PER_MINUTE = 60
    }
}
