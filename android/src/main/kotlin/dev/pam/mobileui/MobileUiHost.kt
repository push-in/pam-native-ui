package dev.pam.mobileui

import android.graphics.drawable.Drawable
import android.graphics.drawable.GradientDrawable
import android.animation.ObjectAnimator
import android.animation.ValueAnimator
import android.annotation.SuppressLint
import android.app.Activity
import android.app.AlertDialog
import android.app.DatePickerDialog
import android.app.Dialog
import android.app.TimePickerDialog
import android.content.Context
import android.content.ContextWrapper
import android.content.res.ColorStateList
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.Rect
import android.graphics.RenderEffect
import android.graphics.RectF
import android.graphics.Shader
import android.graphics.drawable.ColorDrawable
import android.graphics.drawable.RippleDrawable
import android.os.Build
import android.os.Bundle
import android.os.Looper
import android.text.Editable
import android.text.method.PasswordTransformationMethod
import android.text.method.TransformationMethod
import android.text.method.KeyListener
import android.view.Gravity
import android.view.HapticFeedbackConstants
import android.view.KeyEvent
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.view.ViewTreeObserver
import android.view.VelocityTracker
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityManager
import android.view.accessibility.AccessibilityNodeInfo
import android.view.accessibility.AccessibilityNodeProvider
import android.widget.FrameLayout
import android.widget.EditText
import android.widget.NumberPicker
import android.widget.ScrollView
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
import kotlin.math.roundToInt

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
        CHECKBOX(9),
        RADIO(10),
        TOAST(11),
        PROGRESS(12),
        MODAL(13),
        POPOVER(14),
        MENU(15),
        TOOLTIP(16),
        DATE_TIME_PICKER(17),
        PORTAL(18),
        ACCORDION_GROUP(19),
        CHECKBOX_GROUP(20),
        RADIO_GROUP(21),
        SWITCH(22),
        TAB_TRIGGER(23),
        SHEET_ITEM(24),
        MENU_ITEM(25),
        OVERLAY_DISMISS(26),
        INPUT_GROUP(27),
        INPUT_SLOT(28),
        FORM_CONTROL(29),
        TABLE(30),
        TABLE_ROW(31),
        FILE_TREE(32),
        FILE_TREE_FOLDER(33),
        FILE_TREE_FILE(34),
        SPARKLINE(35),
        CHIP_GROUP(36),
        LIST_ITEM(37),
        TIMELINE(38),
        TIMELINE_ITEM(39);

        companion object {
            fun from(value: Int): Behavior =
                entries.firstOrNull { it.value == value } ?: CONTAINER
        }
    }

    private enum class HostAction(val value: Long) {
        DISMISS(1),
        OPEN(2),
        NAVIGATE(3),
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

    private data class TabContent(
        val view: View,
        val value: String,
        val forceMounted: Boolean,
    )

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
    private val selectionGlyphPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.WHITE
        style = Paint.Style.STROKE
        strokeCap = Paint.Cap.ROUND
        strokeJoin = Paint.Join.ROUND
    }
    private val switchTrackPaint = Paint(Paint.ANTI_ALIAS_FLAG)
    private val switchThumbPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        setShadowLayer(1.5f * density, 0f, density, 0x55000000)
    }
    private var behavior = Behavior.CONTAINER
    private var component = 0
    private var expanded = false
    private var checked = false
    private var indeterminate = false
    private var circularProgress = false
    private var progressRotation = 0f
    private var progressAnimator: ValueAnimator? = null
    private var selected = false
    private var buttonToggleItem = false
    private var buttonToggleBackground: Drawable? = null
    private var open = true
    private var openControlled = false
    private var openDefaultInitialized = false
    private var value = 0.0
    private var minimum = 0.0
    private var maximum = 100.0
    private var step = 1.0
    private var trackThickness = 6.0
    private var sliderThumbSize = 16.0
    private var orientation = 1
    private var reversed = false
    private var rangeEnabled = false
    private var lowerValue = 0.0
    private var upperValue = 100.0
    private var activeRangeThumb = 1
    private var showSliderTicks = false
    private var alwaysShowSliderTicks = false
    private var showThumbLabel = false
    private var alwaysShowThumbLabel = false
    private var anchor = 1
    private var placement = 1
    private var resolvedPlacement = 1
    private var offset = 8.0
    private var crossOffset = 0.0
    private var shouldFlip = true
    private var shouldOverlapWithTrigger = false
    private var openDelayMillis = 0L
    private var closeDelayMillis = 0L
    private var closeOnClick = true
    private var openOnClick = true
    private var openOnLongPress = false
    private var pendingAnchoredOpen: Runnable? = null
    private var pendingAnchoredClose: Runnable? = null
    private var pendingAnchoredLongPress: Runnable? = null
    private var anchoredLongPressOpened = false
    private var anchoredTouchCatcher: FrameLayout? = null
    private var anchoredPortalContent: View? = null
    private var anchoredPortalParent: ViewGroup? = null
    private var anchoredPortalIndex = -1
    private var anchoredPortalLayoutParams: ViewGroup.LayoutParams? = null
    private var anchoredTouchInsideContent = false
    private var dismissible = true
    private var closeOnOverlayClick = true
    private var backdropPressBehavior = BACKDROP_PRESS_CLOSE
    private var sheetSnapPoints = emptyList<Float>()
    private var sheetSnapIndex = 0
    private var sheetEnablePanDownToClose = true
    private var sheetEnableDynamicSizing = false
    private var sheetDragStartTranslation = 0f
    private var sheetBackdropPressed = false
    private var sheetBackdropBaseAlpha: Float? = null
    private var sheetScrimOpacity = 0.5f
    private var sheetVelocityTracker: VelocityTracker? = null
    private var closeSheetItemOnPress = false
    private var closeMenuItemOnPress = true
    private var menuSelectionMode = MENU_SELECTION_NONE
    private var menuCollectionOwner: MobileUiHost? = null
    private var anchoredTriggerTouchActive = false
    private var menuTypeaheadPrefix = ""
    private var menuTypeaheadAtMillis = 0L
    private var trapFocus = true
    private var keyboardDismissable = true
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
    private var showWeekNumbers = false
    private var fixedWeeks = false
    private var readOnly = false
    private var invalid = false
    private var required = false
    private var inputFocusColor = Color.rgb(23, 23, 23)
    private var inputInvalidColor = Color.rgb(220, 38, 38)
    private var inputOutlineRadius = 6f
    private var inputOutlineWidth = 1f
    private var inputFocused = false
    private var inputSlotAction = INPUT_SLOT_ACTION_FOCUS
    private var inputSlotFocusOnPress = true
    private var inputSlotAppliedLabel: String? = null
    private var managedInput: EditText? = null
    private var managedInputKeyListener: KeyListener? = null
    private var managedInputTransformation: TransformationMethod? = null
    private var inputFocusObserver: ViewTreeObserver.OnGlobalFocusChangeListener? = null
    private var formLabelTouchActive = false
    private var formInput: EditText? = null
    private var formSignature: String? = null
    private var formAppliedLabel: String? = null
    private var formAppliedHelper: String? = null
    private var tableHeaderRow = false
    private var tableSemanticsDirty = true
    private var skeletonPulseDurationMillis = 1_500L
    private var toastAction = TOAST_ACTION_MUTED
    private var toastScheduleSignature: String? = null
    private var toastAnnouncementSignature: String? = null
    private var accessibilityErrorMessage: String? = null
    private val inputOutlinePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
    }
    private var switchTrackOffColor = Color.rgb(212, 212, 212)
    private var switchTrackOnColor = Color.rgb(82, 82, 82)
    private var switchThumbColor = Color.rgb(250, 250, 250)
    private var switchActiveThumbColor = Color.rgb(250, 250, 250)
    private var switchVisualProgress = 0f
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
    private val fileTreeExpandedPaths = LinkedHashSet<String>()
    private var fileTreeSelectedPath: String? = null
    private var fileTreePath = ""
    private var fileTreeFolderExpanded = false
    private var fileTreeFolderInitialized = false
    private var animator: ValueAnimator? = null
    private var skeletonShimmerProgress = 0f
    private val skeletonShimmerPaint = Paint(Paint.ANTI_ALIAS_FLAG)
    private var nativeProperties: Map<String, WireValue> = emptyMap()
    private var activePickerDialog: Dialog? = null
    private var silentlyDismissedPickerDialog: Dialog? = null
    private var pendingDismiss: Runnable? = null
    private var previousFocus: View? = null
    private var customStateDescription: String? = null
    private var switchAnimator: ValueAnimator? = null
    private var sliderTouchActive = false
    private var sliderTouchInitialValue = 0.0
    private var sliderTouchMoved = false
    private var pendingSliderValue: Double? = null
    private var pendingSliderChange: Runnable? = null
    private var tabValue: String? = null
    private var tabsActivationMode = TABS_ACTIVATION_AUTOMATIC
    private var tabsIndicatorAnimator: ValueAnimator? = null
    private var tabsContentAnimator: ValueAnimator? = null
    private var navigationKind = 0
    private var carouselCycle = false
    private var carouselContinuous = true
    private var carouselIntervalMillis = 6_000L
    private var carouselAdvance: Runnable? = null
    private var carouselTouchDownX = 0f
    private var carouselTouchDownY = 0f
    private var carouselTouchActive = false
    private var treeReconciliationScheduled = false
    private val treeReconciliation = Runnable {
        treeReconciliationScheduled = false
        reconcileChildState()
    }

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

    override fun onDetachedFromWindow() {
        // The catcher is a sibling in android.R.id.content. Removing that
        // sibling while ViewGroup is iterating dispatchDetachedFromWindow()
        // corrupts the platform child traversal on Android 16. Detach first,
        // then remove it on the next main-loop turn.
        restoreAnchoredPortalContent()
        val catcher = anchoredTouchCatcher
        anchoredTouchCatcher = null
        anchoredTouchInsideContent = false
        catcher?.post {
            (catcher.parent as? ViewGroup)?.removeView(catcher)
        }
        progressAnimator?.cancel()
        progressAnimator = null
        super.onDetachedFromWindow()
    }

    override fun onAttachedToWindow() {
        super.onAttachedToWindow()
        updateProgressAnimation()
    }

    fun update(properties: Map<String, WireValue>) {
        require(Looper.myLooper() == Looper.getMainLooper()) {
            "PAM Mobile UI updates must run on Android's UI thread"
        }
        val previousBehavior = behavior
        val previousExpanded = expanded
        val previousChecked = checked
        val previousSelected = selected
        val previousOpen = open
        val previousComponentMode = componentMode
        val previousTabValue = tabValue
        val previousTableHeaderRow = tableHeaderRow
        val previousToastAction = toastAction
        nativeProperties = properties
        sheetScrimOpacity = properties
            .decimal("backdropOpacity", 0.5)
            .toFloat()
            .coerceIn(0f, 1f)
        pendingAnchoredOpen?.let(::removeCallbacks)
        pendingAnchoredOpen = null
        pendingAnchoredClose?.let(::removeCallbacks)
        pendingAnchoredClose = null
        behavior = Behavior.from(properties.integer("behavior", behavior.value.toLong()).toInt())
        if (previousBehavior != behavior) {
            openDefaultInitialized = false
            sheetBackdropBaseAlpha = null
        }
        pendingSliderChange?.let(::removeCallbacks)
        pendingSliderChange = null
        pendingSliderValue = null
        component = properties.integer("component", component.toLong()).toInt()
        if (behavior == Behavior.BOTTOM_SHEET) {
            sheetSnapPoints = properties.text("snapPoints")
                ?.lineSequence()
                ?.mapNotNull(String::toFloatOrNull)
                ?.map { point -> point.coerceIn(1f, 100f) }
                ?.distinct()
                ?.toList()
                ?: emptyList()
            sheetSearchable = properties.flag("searchable", false)
            sheetAllowCustomValue = properties.flag("allowCustomValue", false)
            sheetSearchPlaceholder = properties.text("searchPlaceholder")
                ?: "Search options"
        }
        sheetSnapIndex = properties.integer(
            "snapToIndex",
            properties.integer(
                "defaultSnapIndex",
                sheetSnapIndex.toLong(),
            ),
        ).toInt().coerceAtLeast(0)
        if (sheetSnapPoints.isNotEmpty()) {
            sheetSnapIndex = sheetSnapIndex.coerceAtMost(sheetSnapPoints.lastIndex)
        }
        sheetEnablePanDownToClose = properties.flag(
            "enablePanDownToClose",
            sheetEnablePanDownToClose,
        )
        sheetEnableDynamicSizing = properties.flag(
            "enableDynamicSizing",
            sheetEnableDynamicSizing,
        )
        backdropPressBehavior = properties.integer(
            "pressBehavior",
            backdropPressBehavior.toLong(),
        ).toInt().coerceIn(BACKDROP_PRESS_CLOSE, BACKDROP_PRESS_NONE)
        trapFocus = properties.flag(
            "trapFocus",
            properties.flag("focusScope", trapFocus),
        )
        keyboardDismissable = properties.flag(
            "isKeyboardDismissable",
            keyboardDismissable,
        )
        val defaultCloseSheetItem = component == GeneratedComponents.SELECT_ITEM
        closeSheetItemOnPress = properties.flag(
            "closeOnSelect",
            properties.flag(
                "closeOnPress",
                defaultCloseSheetItem,
            ),
        )
        tabsActivationMode = properties.integer(
            "activationMode",
            tabsActivationMode.toLong(),
        ).toInt().coerceIn(TABS_ACTIVATION_AUTOMATIC, TABS_ACTIVATION_MANUAL)
        navigationKind = properties.integer(
            "navigationKind",
            navigationKind.toLong(),
        ).toInt()
        carouselCycle = properties.flag("cycle", false)
        carouselContinuous = properties.flag("continuous", true)
        carouselIntervalMillis = properties.integer("interval", 6_000L)
            .coerceIn(750L, 60_000L)
        if (behavior == Behavior.TABS) {
            val controlledValue = properties.scalarText("value")
            if (controlledValue != null) {
                tabValue = controlledValue
            } else if (previousBehavior != Behavior.TABS) {
                tabValue = properties.scalarText("defaultValue")
            }
        } else if (behavior == Behavior.TAB_TRIGGER) {
            tabValue = properties.scalarText("value") ?: tabValue
        }
        if (behavior == Behavior.FILE_TREE) {
            if (
                properties.containsKey("expandedPaths")
                || previousBehavior != behavior
            ) {
                fileTreeExpandedPaths.clear()
                properties.text(
                    if (properties.containsKey("expandedPaths")) {
                        "expandedPaths"
                    } else {
                        "defaultExpandedPaths"
                    },
                )
                    ?.lineSequence()
                    ?.filter(String::isNotEmpty)
                    ?.forEach(fileTreeExpandedPaths::add)
            }
            fileTreeSelectedPath = properties.scalarText("selectedPath")
                ?: fileTreeSelectedPath
        } else if (
            behavior == Behavior.FILE_TREE_FOLDER
            || behavior == Behavior.FILE_TREE_FILE
        ) {
            fileTreePath = properties.text("path").orEmpty()
        }
        expanded = properties.flag("expanded", properties.flag("isExpanded", expanded))
        val defaultChecked = if (behavior == Behavior.SWITCH) {
            properties.flag(
                "value",
                properties.flag("defaultValue", checked),
            )
        } else {
            properties.flag("defaultIsChecked", checked)
        }
        checked = properties.flag(
            "checked",
            properties.flag(
                "isChecked",
                defaultChecked,
            ),
        )
        indeterminate = properties.flag(
            "indeterminate",
            properties.flag("isIndeterminate", false),
        )
        circularProgress = properties.flag("circular", false)
        selected = properties.flag("selected", properties.flag("isSelected", selected))
        buttonToggleItem = properties.flag("buttonToggleItem", false)
        val wasOpenControlled = openControlled
        openControlled = properties.containsKey("open")
            || properties.containsKey("isOpen")
        open = if (openControlled) {
            properties.flag(
                "open",
                properties.flag("isOpen", open),
            )
        } else if (
            wasOpenControlled
            || (
                !openDefaultInitialized
                && (
                    properties.containsKey("initiallyOpen")
                    || properties.containsKey("defaultIsOpen")
                )
            )
        ) {
            openDefaultInitialized = true
            properties.flag(
                "initiallyOpen",
                properties.flag("defaultIsOpen", behavior.isOpenByDefault()),
            )
        } else if (previousBehavior != behavior) {
            behavior.isOpenByDefault()
        } else {
            open
        }
        if (
            behavior != Behavior.SWITCH
            && behavior != Behavior.TABS
            && behavior != Behavior.TAB_TRIGGER
        ) {
            value = properties.decimal(
                "value",
                properties.decimal("defaultValue", value),
            )
        }
        minimum = properties.decimal(
            "min",
            properties.decimal("minValue", minimum),
        )
        maximum = max(
            minimum + 0.000_001,
            properties.decimal(
                "max",
                properties.decimal("maxValue", maximum),
            ),
        )
        step = properties.decimal("step", step).coerceAtLeast(0.000_001)
        value = snapped(value)
        rangeEnabled = properties.flag("range", rangeEnabled)
        lowerValue = snapped(properties.decimal("lowerValue", lowerValue))
        upperValue = snapped(properties.decimal("upperValue", upperValue))
        if (rangeEnabled) {
            lowerValue = minOf(lowerValue, upperValue)
            upperValue = maxOf(lowerValue, upperValue)
            value = upperValue
        }
        trackThickness = properties.decimal(
            "trackThickness",
            properties.decimal("sliderTrackHeight", trackThickness),
        ).coerceAtLeast(1.0)
        sliderThumbSize = properties.decimal("thumbSize", sliderThumbSize)
            .coerceAtLeast(1.0)
        orientation = properties.integer("orientation", orientation.toLong()).toInt()
        reversed = properties.flag("isReversed", properties.flag("reversed", reversed))
        showSliderTicks = properties.flag("showTicks", showSliderTicks)
        alwaysShowSliderTicks = properties.flag(
            "alwaysShowTicks",
            alwaysShowSliderTicks,
        )
        showThumbLabel = properties.flag("showThumbLabel", showThumbLabel)
        alwaysShowThumbLabel = properties.flag(
            "alwaysShowThumbLabel",
            alwaysShowThumbLabel,
        )
        anchor = properties.integer("anchor", anchor.toLong()).toInt()
        placement = properties.integer("placement", placement.toLong()).toInt().coerceIn(1, 13)
        resolvedPlacement = placement
        offset = properties.decimal("offset", offset)
        crossOffset = properties.decimal("crossOffset", crossOffset)
        shouldFlip = properties.flag("shouldFlip", shouldFlip)
        shouldOverlapWithTrigger = properties.flag(
            "shouldOverlapWithTrigger",
            shouldOverlapWithTrigger,
        )
        openDelayMillis = properties.integer("openDelay", openDelayMillis)
            .coerceIn(0L, MAX_ANCHORED_OVERLAY_DELAY_MILLIS)
        closeDelayMillis = properties.integer("closeDelay", closeDelayMillis)
            .coerceIn(0L, MAX_ANCHORED_OVERLAY_DELAY_MILLIS)
        closeOnClick = properties.flag("closeOnClick", closeOnClick)
        openOnClick = properties.flag(
            "openOnClick",
            behavior != Behavior.TOOLTIP,
        )
        openOnLongPress = properties.flag(
            "openOnLongPress",
            behavior == Behavior.TOOLTIP,
        )
        dismissible = properties.flag(
            "dismissible",
            properties.flag("isDismissable", dismissible),
        )
        closeOnOverlayClick = properties.flag(
            "closeOnOverlayClick",
            properties.flag("closeOnOverlay", closeOnOverlayClick),
        )
        menuSelectionMode = properties.integer(
            "selectionMode",
            menuSelectionMode.toLong(),
        ).toInt().coerceIn(MENU_SELECTION_SINGLE, MENU_SELECTION_NONE)
        closeMenuItemOnPress = properties.flag("closeOnSelect", true)
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
        showWeekNumbers = properties.flag("showWeek", false)
        fixedWeeks = properties.flag("fixedWeeks", false)
        readOnly = properties.flag("readOnly", properties.flag("isReadOnly", false))
        invalid = properties.flag("invalid", properties.flag("isInvalid", false))
        required = properties.flag("required", properties.flag("isRequired", false))
        inputFocusColor = properties.integer(
            "focusColor",
            inputFocusColor.toLong(),
        ).toInt()
        inputInvalidColor = properties.integer(
            "invalidColor",
            inputInvalidColor.toLong(),
        ).toInt()
        inputOutlineRadius = properties.decimal(
            "outlineRadius",
            inputOutlineRadius.toDouble(),
        ).toFloat().coerceAtLeast(0f)
        inputOutlineWidth = properties.decimal(
            "outlineWidth",
            inputOutlineWidth.toDouble(),
        ).toFloat().coerceAtLeast(1f)
        inputSlotAction = properties.integer(
            "slotAction",
            if (previousBehavior == behavior) {
                inputSlotAction.toLong()
            } else {
                INPUT_SLOT_ACTION_FOCUS.toLong()
            },
        ).toInt().coerceIn(INPUT_SLOT_ACTION_FOCUS, INPUT_SLOT_ACTION_NONE)
        inputSlotFocusOnPress = properties.flag(
            "focusOnPress",
            if (previousBehavior == behavior) inputSlotFocusOnPress else true,
        )
        tableHeaderRow = properties.flag("isHeaderRow", false)
        if (behavior == Behavior.TABLE && previousBehavior != behavior) {
            tableSemanticsDirty = true
        } else if (
            behavior == Behavior.TABLE_ROW
            && previousTableHeaderRow != tableHeaderRow
        ) {
            tableAncestor()?.tableSemanticsDirty = true
        }
        skeletonPulseDurationMillis = properties.integer(
            "pulseDuration",
            skeletonPulseDurationMillis,
        ).coerceIn(100L, 10_000L)
        toastAction = properties.integer(
            "action",
            TOAST_ACTION_MUTED.toLong(),
        ).toInt().coerceIn(TOAST_ACTION_MUTED, TOAST_ACTION_ATTENTION)
        accessibilityErrorMessage = properties.text("accessibilityErrorMessage")
            ?: properties.text("errorMessage")
        switchTrackOffColor = properties.integer(
            "trackOffColor",
            switchTrackOffColor.toLong(),
        ).toInt()
        switchTrackOnColor = properties.integer(
            "trackOnColor",
            switchTrackOnColor.toLong(),
        ).toInt()
        switchThumbColor = properties.integer(
            "thumbColor",
            switchThumbColor.toLong(),
        ).toInt()
        switchActiveThumbColor = properties.integer(
            "activeThumbColor",
            switchThumbColor.toLong(),
        ).toInt()
        collapsible = properties.flag(
            "collapsible",
            properties.flag("isCollapsible", true),
        )
        calendarLocale = properties.text("locale")
            ?.let(Locale::forLanguageTag)
            ?.takeUnless { it.language.isEmpty() }
            ?: Locale.getDefault()
        updateCalendarSelection(properties, previousComponentMode != componentMode)
        isEnabled = !properties.flag(
            "disabled",
            properties.flag("isDisabled", false),
        )
        if ((!isEnabled || behavior != Behavior.DATE_TIME_PICKER) && activePickerDialog != null) {
            dismissActivePickerSilently()
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
        selectionGlyphPaint.color = calendarSelectedTextColor
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            customStateDescription = properties.text("stateDescription")
            stateDescription = customStateDescription
        }

        if (previousBehavior != behavior) {
            installBehavior()
            requestLayout()
            if (open) {
                animateEntrance()
            }
            if (behavior.isOverlay() && open) {
                isFocusableInTouchMode = true
                captureAndMoveFocus()
            } else if (behavior.isAnchoredOverlay()) {
                post { applyAnchoredOverlayState(animate = false) }
            }
        } else if (previousOpen != open && behavior.isOverlay()) {
            if (open) {
                animateEntrance()
                captureAndMoveFocus()
            } else {
                animate().cancel()
                translationX = 0f
                translationY = 0f
                if (behavior.isAnchoredOverlay()) {
                    applyAnchoredOverlayState(animate = true)
                }
                restoreFocus()
            }
        }
        if (previousExpanded != expanded) {
            animateExpanded()
        }
        if (behavior == Behavior.SWITCH) {
            if (previousBehavior != behavior) {
                switchAnimator?.cancel()
                switchVisualProgress = if (checked) 1f else 0f
            } else if (previousChecked != checked) {
                animateSwitch()
            }
            updateSwitchAccessibility()
        }
        scheduleToast(properties)
        updateProgressAnimation()
        if (behavior == Behavior.SKELETON) {
            startSkeleton()
        } else if (
            behavior == Behavior.TOAST
            && (
                previousBehavior != behavior
                || previousToastAction != toastAction
            )
        ) {
            applyToastSemantics()
        }
        applyComponentDefaults()
        applyMaterialSpecialization(previousBehavior)
        applySelectionVisualState()
        updateSelectionAccessibility()
        applyRangeVisualState()
        updateRangeAccessibility()
        updateCalendarTitle()
        if (behavior == Behavior.TABS) {
            applyTabsState(
                animate = previousBehavior == Behavior.TABS
                    && previousTabValue != tabValue,
            )
            scheduleCarouselAdvance()
        } else if (behavior == Behavior.TAB_TRIGGER) {
            updateTabTriggerAccessibility()
        } else if (behavior == Behavior.SHEET_ITEM) {
            updateSheetItemAccessibility()
        } else if (behavior == Behavior.MENU_ITEM) {
            updateMenuItemAccessibility()
        } else if (behavior == Behavior.INPUT_GROUP) {
            applyInputGroupState()
        } else if (behavior == Behavior.INPUT_SLOT) {
            updateInputSlotAccessibility()
        } else if (behavior == Behavior.FORM_CONTROL) {
            applyFormControlSemantics()
        } else if (behavior == Behavior.TABLE) {
            applyTableSemantics()
        } else if (behavior == Behavior.FILE_TREE) {
            applyFileTreeState(announce = false)
        } else if (
            behavior == Behavior.FILE_TREE_FOLDER
            || behavior == Behavior.FILE_TREE_FILE
        ) {
            updateFileTreeItemAccessibility()
        }
        scheduleAttachedTreeReconciliation()
        if (behavior.isAnchoredOverlay() && previousOpen == open) {
            post { applyAnchoredOverlayState(animate = false) }
        }
        invalidate()
    }

    override fun onViewAdded(child: View) {
        super.onViewAdded(child)
        if (behavior == Behavior.TABLE) {
            tableSemanticsDirty = true
        } else if (behavior == Behavior.TABLE_ROW) {
            tableAncestor()?.tableSemanticsDirty = true
        }
        if (behavior == Behavior.INPUT_SLOT) {
            child.importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
        }
        if (behavior == Behavior.ACCORDION) {
            applyAccordionState(animate = false)
            updateAccordionAccessibility()
        }
        if (behavior == Behavior.CHECKBOX || behavior == Behavior.RADIO) {
            applySelectionVisualState()
            updateSelectionAccessibility()
        }
        if (behavior == Behavior.SLIDER || behavior == Behavior.PROGRESS) {
            applyRangeVisualState()
        }
        if (behavior == Behavior.CALENDAR) {
            updateCalendarTitle()
        }
        if (behavior == Behavior.TABS) {
            applyTabsState(animate = false)
        }
        if (behavior == Behavior.BOTTOM_SHEET) {
            post { applySheetLayout(animate = false) }
        }
        if (behavior == Behavior.MENU_ITEM) {
            updateMenuItemAccessibility()
        }
        if (behavior == Behavior.INPUT_GROUP) {
            applyInputGroupState()
        }
        if (behavior == Behavior.FORM_CONTROL) {
            applyFormControlSemantics()
        }
        if (behavior == Behavior.TABLE) {
            applyTableSemantics()
        }
        if (behavior == Behavior.TOAST) {
            applyToastSemantics()
        }
        if (behavior == Behavior.FILE_TREE) {
            applyFileTreeState(announce = false)
        }
        scheduleAttachedTreeReconciliation()
        if (behavior.isAnchoredOverlay()) {
            post { applyAnchoredOverlayState(animate = false) }
        }
    }

    override fun onViewRemoved(child: View) {
        super.onViewRemoved(child)
        if (behavior == Behavior.TABLE) {
            tableSemanticsDirty = true
        } else if (behavior == Behavior.TABLE_ROW) {
            tableAncestor()?.tableSemanticsDirty = true
        }
        if (behavior == Behavior.FILE_TREE) {
            applyFileTreeState(announce = false)
        }
        scheduleAttachedTreeReconciliation()
    }

    private fun scheduleAttachedTreeReconciliation() {
        if (
            !isAttachedToWindow
            || treeReconciliationScheduled
            || behavior !in CHILD_RECONCILIATION_BEHAVIORS
        ) {
            return
        }
        treeReconciliationScheduled = true
        post(treeReconciliation)
    }

    private fun reconcileChildState() {
        when (behavior) {
            Behavior.SLIDER,
            Behavior.PROGRESS,
            -> applyRangeVisualState()
            Behavior.CALENDAR -> updateCalendarTitle()
            Behavior.TABS -> applyTabsState(animate = false)
            Behavior.INPUT_GROUP -> applyInputGroupState()
            Behavior.FORM_CONTROL -> applyFormControlSemantics()
            Behavior.TABLE -> applyTableSemantics()
            Behavior.TOAST -> applyToastSemantics()
            Behavior.FILE_TREE -> applyFileTreeState(announce = false)
            else -> Unit
        }
    }

    override fun dispatchDraw(canvas: Canvas) {
        super.dispatchDraw(canvas)
        when (behavior) {
            Behavior.CHECKBOX -> if (
                !nativeProperties.flag("abstractSelectionItem", false)
            ) {
                drawSelectionGlyph(canvas, radio = false)
            }
            Behavior.RADIO -> drawSelectionGlyph(canvas, radio = true)
            Behavior.CALENDAR -> drawCalendar(canvas)
            Behavior.INPUT_GROUP -> drawInputOutline(canvas)
            Behavior.SLIDER -> if (nativeProperties.flag("rating", false)) {
                drawRating(canvas)
            } else {
                drawSliderDecorations(canvas)
            }
            Behavior.PROGRESS -> if (!circularProgress) {
                drawLinearProgress(canvas)
            }
            Behavior.SKELETON -> drawSkeletonShimmer(canvas)
            else -> Unit
        }
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        when (behavior) {
            Behavior.CHECKBOX -> if (
                !nativeProperties.flag("abstractSelectionItem", false)
            ) {
                drawSelectionIndicator(canvas, radio = false)
            }
            Behavior.RADIO -> drawSelectionIndicator(canvas, radio = true)
            Behavior.SWITCH -> drawSwitch(canvas)
            Behavior.PROGRESS -> if (circularProgress) drawCircularProgress(canvas)
            Behavior.SPARKLINE -> drawSparkline(canvas)
            Behavior.TIMELINE -> drawTimeline(canvas)
            else -> Unit
        }
    }

    private fun drawTimeline(canvas: Canvas) {
        if (childCount == 0) return
        val axis = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
            width - 20f * density
        } else {
            20f * density
        }
        val first = 32f * density
        val last = first + (childCount - 1) * 64f * density
        val previousStyle = trackPaint.style
        val previousWidth = trackPaint.strokeWidth
        trackPaint.style = Paint.Style.STROKE
        trackPaint.strokeWidth = 2f * density
        canvas.drawLine(axis, first, axis, last, trackPaint)
        fillPaint.style = Paint.Style.FILL
        repeat(childCount) { index ->
            canvas.drawCircle(axis, first + index * 64f * density, 6f * density, fillPaint)
        }
        trackPaint.style = previousStyle
        trackPaint.strokeWidth = previousWidth
    }

    override fun onMeasure(widthMeasureSpec: Int, heightMeasureSpec: Int) {
        if (behavior.isAnchoredOverlay() && childCount > 0) {
            measureAnchoredOverlay(widthMeasureSpec, heightMeasureSpec)
            return
        }
        if (behavior == Behavior.CHIP_GROUP && childCount > 0) {
            val width = MeasureSpec.getSize(widthMeasureSpec)
            val availableWidth = (width - paddingLeft - paddingRight).coerceAtLeast(0)
            repeat(childCount) { index ->
                getChildAt(index).measure(
                    MeasureSpec.makeMeasureSpec(availableWidth, MeasureSpec.AT_MOST),
                    MeasureSpec.makeMeasureSpec((40f * density).roundToInt(), MeasureSpec.AT_MOST),
                )
            }
            val gap = (8f * density).roundToInt()
            var rows = 1
            var used = 0
            repeat(childCount) { index ->
                val childWidth = getChildAt(index).measuredWidth
                if (used > 0 && used + gap + childWidth > availableWidth) {
                    rows += 1
                    used = childWidth
                } else {
                    used += (if (used == 0) 0 else gap) + childWidth
                }
            }
            setMeasuredDimension(
                resolveSize(width, widthMeasureSpec),
                resolveSize((rows * 40f * density).roundToInt(), heightMeasureSpec),
            )
            return
        }
        if (behavior == Behavior.LIST_ITEM && childCount > 0) {
            val width = MeasureSpec.getSize(widthMeasureSpec)
            val availableWidth = (width - paddingLeft - paddingRight - 32f * density)
                .roundToInt()
                .coerceAtLeast(0)
            repeat(childCount) { index ->
                getChildAt(index).measure(
                    MeasureSpec.makeMeasureSpec(availableWidth, MeasureSpec.AT_MOST),
                    MeasureSpec.makeMeasureSpec((48f * density).roundToInt(), MeasureSpec.AT_MOST),
                )
            }
            val lines = nativeProperties
                .integer("lines", if (childCount > 1) 2L else 1L)
                .toInt()
                .coerceIn(1, 3)
            val densityOffset = when (nativeProperties.text("density")) {
                "comfortable" -> -4f
                "compact" -> -8f
                else -> 0f
            }
            val baseHeight = when (lines) {
                2 -> 64f
                3 -> 88f
                else -> 48f
            }
            val desiredHeight = ((baseHeight + densityOffset) * density).roundToInt()
            setMeasuredDimension(
                resolveSize(width, widthMeasureSpec),
                resolveSize(desiredHeight, heightMeasureSpec),
            )
            return
        }
        if (behavior != Behavior.TABLE_ROW || childCount == 0) {
            super.onMeasure(widthMeasureSpec, heightMeasureSpec)
            return
        }
        val width = MeasureSpec.getSize(widthMeasureSpec)
        val height = MeasureSpec.getSize(heightMeasureSpec)
        val columnWidth = (
            width - paddingLeft - paddingRight
        ).coerceAtLeast(0) / childCount
        repeat(childCount) { index ->
            getChildAt(index).measure(
                MeasureSpec.makeMeasureSpec(columnWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(
                    (height - paddingTop - paddingBottom).coerceAtLeast(0),
                    MeasureSpec.EXACTLY,
                ),
            )
        }
        setMeasuredDimension(
            resolveSize(width, widthMeasureSpec),
            resolveSize(height, heightMeasureSpec),
        )
    }

    private fun measureAnchoredOverlay(
        widthMeasureSpec: Int,
        heightMeasureSpec: Int,
    ) {
        val content = anchoredOverlayContent()
        val contentBranch = content?.let(::directChildContaining)
        val backdrop = findTaggedDescendant(this, OVERLAY_BACKDROP_TAG)
            ?: findTaggedDescendantWithPrefix(this, "$OVERLAY_BACKDROP_TAG:")
        val backdropBranch = backdrop?.let(::directChildContaining)
        var desiredWidth = paddingLeft + paddingRight
        var desiredHeight = paddingTop + paddingBottom
        var childState = 0

        repeat(childCount) { index ->
            val child = getChildAt(index)
            if (
                child.visibility == GONE
                || child === contentBranch
                || child === backdropBranch
            ) {
                return@repeat
            }
            measureChildWithMargins(
                child,
                widthMeasureSpec,
                0,
                heightMeasureSpec,
                0,
            )
            val params = child.layoutParams as MarginLayoutParams
            desiredWidth = max(
                desiredWidth,
                paddingLeft + paddingRight + child.measuredWidth
                    + params.leftMargin + params.rightMargin,
            )
            desiredHeight = max(
                desiredHeight,
                paddingTop + paddingBottom + child.measuredHeight
                    + params.topMargin + params.bottomMargin,
            )
            childState = combineMeasuredStates(childState, child.measuredState)
        }

        val visibleFrame = Rect()
        rootView.getWindowVisibleDisplayFrame(visibleFrame)
        val screenMargin = (ANCHORED_OVERLAY_SCREEN_MARGIN_DP * density)
            .roundToInt() * 2
        val overlayWidth = (
            if (visibleFrame.width() > 0) {
                visibleFrame.width()
            } else {
                resources.displayMetrics.widthPixels
            } - screenMargin
        ).coerceAtLeast(0)
        val overlayHeight = (
            if (visibleFrame.height() > 0) {
                visibleFrame.height()
            } else {
                resources.displayMetrics.heightPixels
            } - screenMargin
        ).coerceAtLeast(0)
        contentBranch?.takeUnless { it.visibility == GONE }?.let { child ->
            child.measure(
                MeasureSpec.makeMeasureSpec(overlayWidth, MeasureSpec.AT_MOST),
                MeasureSpec.makeMeasureSpec(overlayHeight, MeasureSpec.AT_MOST),
            )
            desiredWidth = max(
                desiredWidth,
                paddingLeft + paddingRight + child.measuredWidth,
            )
            desiredHeight = max(
                desiredHeight,
                paddingTop + paddingBottom + desiredHeight
                    + (offset * density).roundToInt()
                    + child.measuredHeight,
            )
            childState = combineMeasuredStates(childState, child.measuredState)
        }
        backdropBranch
            ?.takeUnless { it.visibility == GONE || it === contentBranch }
            ?.measure(
                MeasureSpec.makeMeasureSpec(overlayWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(overlayHeight, MeasureSpec.EXACTLY),
            )

        setMeasuredDimension(
            resolveSizeAndState(
                max(desiredWidth, suggestedMinimumWidth),
                widthMeasureSpec,
                childState,
            ),
            resolveSizeAndState(
                max(desiredHeight, suggestedMinimumHeight),
                heightMeasureSpec,
                childState shl MEASURED_HEIGHT_STATE_SHIFT,
            ),
        )
    }

    private fun directChildContaining(descendant: View): View? {
        var current = descendant
        while (current.parent is View && current.parent !== this) {
            current = current.parent as View
        }
        return current.takeIf { it.parent === this }
    }

    override fun onLayout(
        changed: Boolean,
        left: Int,
        top: Int,
        right: Int,
        bottom: Int,
    ) {
        super.onLayout(changed, left, top, right, bottom)
        if (
            behavior == Behavior.MODAL || behavior == Behavior.PORTAL
        ) {
            repeat(childCount) { index ->
                val child = getChildAt(index)
                if (child.visibility != GONE) {
                    val childWidth = child.measuredWidth.coerceAtMost(width)
                    val childHeight = child.measuredHeight.coerceAtMost(height)
                    val childLeft = (width - childWidth) / 2
                    val childTop = (height - childHeight) / 2
                    child.layout(
                        childLeft,
                        childTop,
                        childLeft + childWidth,
                        childTop + childHeight,
                    )
                }
            }
        }
        if (behavior == Behavior.TABLE_ROW && childCount > 0) {
            val contentWidth = (width - paddingLeft - paddingRight).coerceAtLeast(0)
            repeat(childCount) { index ->
                val childLeft = paddingLeft + contentWidth * index / childCount
                val childRight = paddingLeft + contentWidth * (index + 1) / childCount
                getChildAt(index).layout(
                    childLeft,
                    paddingTop,
                    childRight,
                    height - paddingBottom,
                )
            }
        }
        if (behavior == Behavior.LIST_ITEM && childCount > 0) {
            val visible = (0 until childCount)
                .map(::getChildAt)
                .filter { it.visibility != GONE }
            val start = paddingLeft + (16f * density).roundToInt()
            val contentHeight = visible.sumOf { it.measuredHeight }
            var y = ((height - contentHeight) / 2).coerceAtLeast(paddingTop)
            visible.forEach { child ->
                val childWidth = child.measuredWidth.coerceAtMost(
                    width - start - paddingRight - (16f * density).roundToInt(),
                )
                val x = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                    width - start - childWidth
                } else {
                    start
                }
                child.layout(x, y, x + childWidth, y + child.measuredHeight)
                y += child.measuredHeight
            }
        }
        if (behavior == Behavior.CHIP_GROUP && childCount > 0) {
            val gap = (8f * density).roundToInt()
            val rowHeight = (40f * density).roundToInt()
            var logicalX = paddingLeft
            var y = paddingTop
            repeat(childCount) { index ->
                val child = getChildAt(index)
                if (
                    logicalX > paddingLeft
                    && logicalX + child.measuredWidth > width - paddingRight
                ) {
                    logicalX = paddingLeft
                    y += rowHeight
                }
                val x = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                    width - logicalX - child.measuredWidth
                } else {
                    logicalX
                }
                child.layout(x, y, x + child.measuredWidth, y + child.measuredHeight)
                logicalX += child.measuredWidth + gap
            }
        }
        if (behavior == Behavior.TIMELINE && childCount > 0) {
            val itemHeight = (64f * density).roundToInt()
            repeat(childCount) { index ->
                val child = getChildAt(index)
                val y = paddingTop + index * itemHeight
                child.layout(paddingLeft, y, width - paddingRight, minOf(height, y + itemHeight))
            }
        }
        if (behavior == Behavior.TIMELINE_ITEM && childCount > 0) {
            val inset = (40f * density).roundToInt()
            repeat(childCount) { index ->
                val child = getChildAt(index)
                val childHeight = child.measuredHeight.coerceAtMost(height)
                val y = (height - childHeight) / 2
                if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                    child.layout(0, y, width - inset, y + childHeight)
                } else {
                    child.layout(inset, y, width, y + childHeight)
                }
            }
        }
        applyRangeVisualState()
        if (behavior == Behavior.TABS) {
            applyTabsState(animate = false)
        }
        if (behavior == Behavior.BOTTOM_SHEET) {
            applySheetLayout(animate = false)
        }
        if (behavior == Behavior.INPUT_GROUP) {
            applyInputGroupState()
        }
        if (behavior == Behavior.FORM_CONTROL) {
            applyFormControlSemantics()
        }
        if (behavior == Behavior.TABLE) {
            applyTableSemantics()
        }
        if (behavior == Behavior.FILE_TREE) {
            applyFileTreeState(announce = false)
        }
        if (behavior.isAnchoredOverlay()) {
            positionAnchoredContent()
            if (!open) {
                applyAnchoredOverlayState(animate = false)
            }
        }
    }

    override fun onInterceptTouchEvent(event: MotionEvent): Boolean {
        if (behavior == Behavior.TABS && navigationKind == 1 && isEnabled) {
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    carouselTouchDownX = event.x
                    carouselTouchDownY = event.y
                    carouselTouchActive = false
                }
                MotionEvent.ACTION_MOVE -> {
                    val deltaX = abs(event.x - carouselTouchDownX)
                    val deltaY = abs(event.y - carouselTouchDownY)
                    val vertical = orientation == 2 || nativeProperties.flag("vertical", false)
                    val primary = if (vertical) deltaY else deltaX
                    val cross = if (vertical) deltaX else deltaY
                    if (primary >= 16f * density && primary > cross) {
                        carouselTouchActive = true
                        parent?.requestDisallowInterceptTouchEvent(false)
                        return true
                    }
                }
                MotionEvent.ACTION_UP,
                MotionEvent.ACTION_CANCEL,
                -> carouselTouchActive = false
            }
        }
        if (
            behavior == Behavior.FILE_TREE_FILE
        ) {
            return isEnabled
        }
        if (behavior == Behavior.FILE_TREE_FOLDER && isEnabled) {
            val header = findTaggedDescendant(this, FILE_TREE_HEADER_TAG)
            return header != null && boundsInHost(header).contains(event.x, event.y)
        }
        if (
            behavior.isAnchoredOverlay()
            && isEnabled
            && !open
        ) {
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    anchoredTriggerTouchActive = anchoredTriggerBounds()
                        ?.contains(event.x, event.y) == true
                    if (anchoredTriggerTouchActive) {
                        if (
                            behavior == Behavior.TOOLTIP
                            && openOnLongPress
                        ) {
                            pendingAnchoredLongPress?.let(::removeCallbacks)
                            val action = Runnable {
                                if (anchoredTriggerTouchActive && !open) {
                                    anchoredLongPressOpened = true
                                    requestAnchoredOverlayOpen()
                                }
                            }
                            pendingAnchoredLongPress = action
                            postDelayed(action, openDelayMillis.coerceAtLeast(500L))
                        }
                        return true
                    }
                }
                MotionEvent.ACTION_CANCEL -> {
                    pendingAnchoredLongPress?.let(::removeCallbacks)
                    pendingAnchoredLongPress = null
                    anchoredTriggerTouchActive = false
                    anchoredLongPressOpened = false
                }
            }
        }
        if (
            behavior == Behavior.SLIDER
            && isEnabled
            && !readOnly
            && event.actionMasked == MotionEvent.ACTION_DOWN
            && sliderTrackBounds().contains(event.x, event.y)
        ) {
            sliderTouchActive = true
            return true
        }
        if (behavior == Behavior.DATE_TIME_PICKER && isEnabled) {
            return true
        }
        if (
            behavior == Behavior.CALENDAR
            && isEnabled
            && !readOnly
            && event.actionMasked == MotionEvent.ACTION_DOWN
            && calendarTargetAt(event.x, event.y) != CALENDAR_TARGET_NONE
        ) {
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
        if (behavior == Behavior.FORM_CONTROL && isEnabled) {
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    formLabelTouchActive = formLabelBounds()
                        ?.contains(event.x, event.y) == true
                    if (formLabelTouchActive) return true
                }
                MotionEvent.ACTION_CANCEL -> formLabelTouchActive = false
            }
        }

        return super.onInterceptTouchEvent(event)
    }

    override fun onTouchEvent(event: MotionEvent): Boolean {
        if (behavior == Behavior.TABS && navigationKind == 1) {
            if (!isEnabled) return false
            return when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    carouselTouchDownX = event.x
                    carouselTouchDownY = event.y
                    carouselTouchActive = true
                    true
                }
                MotionEvent.ACTION_MOVE -> true
                MotionEvent.ACTION_UP -> {
                    val vertical = orientation == 2 || nativeProperties.flag("vertical", false)
                    val delta = if (vertical) {
                        event.y - carouselTouchDownY
                    } else {
                        event.x - carouselTouchDownX
                    }
                    val activate = carouselTouchActive && abs(delta) >= 40f * density
                    carouselTouchActive = false
                    if (activate) {
                        var direction = if (delta < 0f) 1 else -1
                        if (reversed) direction *= -1
                        moveCarousel(direction)
                    }
                    activate
                }
                MotionEvent.ACTION_CANCEL -> {
                    val claimed = carouselTouchActive
                    carouselTouchActive = false
                    claimed
                }
                else -> carouselTouchActive
            }
        }
        if (
            behavior == Behavior.TAB_TRIGGER
            || behavior == Behavior.FILE_TREE_FOLDER
            || behavior == Behavior.FILE_TREE_FILE
        ) {
            if (!isEffectivelyEnabled()) return false
            return when (event.actionMasked) {
                MotionEvent.ACTION_DOWN,
                MotionEvent.ACTION_MOVE,
                -> true
                MotionEvent.ACTION_UP -> {
                    performClick()
                    true
                }
                MotionEvent.ACTION_CANCEL -> true
                else -> true
            }
        }
        if (
            behavior == Behavior.TOOLTIP
            && anchoredLongPressOpened
            && event.actionMasked in setOf(
                MotionEvent.ACTION_UP,
                MotionEvent.ACTION_CANCEL,
            )
        ) {
            pendingAnchoredLongPress?.let(::removeCallbacks)
            pendingAnchoredLongPress = null
            anchoredTriggerTouchActive = false
            anchoredLongPressOpened = false
            if (open) {
                requestOverlayDismiss()
            }
            return true
        }
        if (
            behavior.isAnchoredOverlay()
            && !open
            && anchoredTriggerTouchActive
        ) {
            return when (event.actionMasked) {
                MotionEvent.ACTION_DOWN,
                MotionEvent.ACTION_MOVE,
                -> true
                MotionEvent.ACTION_UP -> {
                    val activate = anchoredTriggerBounds()
                        ?.contains(event.x, event.y) == true
                    pendingAnchoredLongPress?.let(::removeCallbacks)
                    pendingAnchoredLongPress = null
                    anchoredTriggerTouchActive = false
                    if (activate) {
                        anchoredTrigger()?.performClick()
                        if (
                            !anchoredLongPressOpened
                            && (
                                behavior != Behavior.TOOLTIP
                                || openOnClick
                            )
                        ) {
                            requestAnchoredOverlayOpen()
                        }
                        performClick()
                    }
                    anchoredLongPressOpened = false
                    activate
                }
                MotionEvent.ACTION_CANCEL -> {
                    pendingAnchoredLongPress?.let(::removeCallbacks)
                    pendingAnchoredLongPress = null
                    anchoredTriggerTouchActive = false
                    anchoredLongPressOpened = false
                    true
                }
                else -> true
            }
        }
        if (behavior == Behavior.FORM_CONTROL && formLabelTouchActive) {
            return when (event.actionMasked) {
                MotionEvent.ACTION_DOWN,
                MotionEvent.ACTION_MOVE,
                -> true
                MotionEvent.ACTION_UP -> {
                    val activate = formLabelBounds()
                        ?.contains(event.x, event.y) == true
                    formLabelTouchActive = false
                    if (activate) {
                        performClick()
                        requestInputFocus()
                    }
                    activate
                }
                MotionEvent.ACTION_CANCEL -> {
                    formLabelTouchActive = false
                    true
                }
                else -> true
            }
        }
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

    override fun performClick(): Boolean =
        when (behavior) {
            Behavior.TAB_TRIGGER ->
                tabsAncestor()?.selectTab(this, emit = true) == true
            Behavior.FILE_TREE_FOLDER ->
                fileTreeAncestor()?.toggleFileTreeFolder(this) == true
            Behavior.FILE_TREE_FILE ->
                fileTreeAncestor()?.selectFileTreeItem(this) == true
            else -> super.performClick()
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

    override fun getAccessibilityNodeProvider(): AccessibilityNodeProvider? =
        if (behavior == Behavior.CALENDAR) {
            calendarAccessibilityProvider
        } else {
            super.getAccessibilityNodeProvider()
        }

    override fun dispatchHoverEvent(event: MotionEvent): Boolean {
        if (behavior == Behavior.TOOLTIP && isEnabled) {
            when (event.actionMasked) {
                MotionEvent.ACTION_HOVER_ENTER -> scheduleTooltipState(true)
                MotionEvent.ACTION_HOVER_EXIT -> scheduleTooltipState(false)
            }
        }
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
        if (
            !behavior.isOverlay()
            || !trapFocus
            || candidate == null
            || contains(candidate)
        ) {
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
        val abstractSelectionItem = nativeProperties.flag("abstractSelectionItem", false)
        info.isSelected = if (abstractSelectionItem) checked else selected
        info.isChecked = checked
        info.isCheckable = (
            behavior in setOf(
                Behavior.CHECKBOX,
                Behavior.RADIO,
                Behavior.SWITCH,
            ) && !abstractSelectionItem
        ) || (
            behavior == Behavior.MENU_ITEM
                && menuSelectionMode != MENU_SELECTION_NONE
            )
        if (info.isCheckable) {
            info.isClickable = isEnabled && !readOnly
            info.isContentInvalid = invalid
            if (invalid && !accessibilityErrorMessage.isNullOrEmpty()) {
                info.error = accessibilityErrorMessage
            }
            if (!isEnabled || readOnly) {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
            selectionCollectionItemInfo()?.let { collectionItem ->
                info.collectionItemInfo = collectionItem
            }
        }
        if (behavior == Behavior.CHECKBOX_GROUP || behavior == Behavior.RADIO_GROUP) {
            val items = selectionItems(this).count()
            info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                items,
                1,
                false,
                if (behavior == Behavior.RADIO_GROUP) {
                    AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_SINGLE
                } else {
                    AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_MULTIPLE
                },
            )
        }
        if (behavior == Behavior.TABS) {
            val triggers = tabTriggers().filter(MobileUiHost::isEffectivelyEnabled)
            val horizontal = orientation == 1
            info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                if (horizontal) 1 else triggers.size,
                if (horizontal) triggers.size else 1,
                false,
                AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_SINGLE,
            )
        }
        if (behavior == Behavior.TAB_TRIGGER) {
            val tabs = tabsAncestor()
            val enabled = isEnabled && tabs?.isEnabled != false
            info.className = "android.widget.Button"
            info.extras.putCharSequence(
                "AccessibilityNodeInfo.roleDescription",
                "Tab",
            )
            info.isSelected = selected
            info.isEnabled = enabled
            info.isClickable = enabled
            if (enabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            } else {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
            tabs?.tabCollectionItemInfo(this)?.let { item ->
                info.collectionItemInfo = item
            }
        }
        if (behavior == Behavior.BOTTOM_SHEET) {
            val items = sheetItems().filter { item -> item.isEnabled }
            if (items.isNotEmpty()) {
                info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                    items.size,
                    1,
                    false,
                    if (component == GeneratedComponents.SELECT_PORTAL) {
                        AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_SINGLE
                    } else {
                        AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_NONE
                    },
                )
            }
        }
        if (behavior == Behavior.MENU) {
            val items = menuItems()
            items.forEach { it.menuCollectionOwner = this }
            info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                items.size,
                1,
                false,
                when (menuSelectionMode) {
                    MENU_SELECTION_SINGLE ->
                        AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_SINGLE
                    MENU_SELECTION_MULTIPLE ->
                        AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_MULTIPLE
                    else -> AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_NONE
                },
            )
        }
        if (behavior == Behavior.SHEET_ITEM) {
            val selectOption = component == GeneratedComponents.SELECT_ITEM
            info.className = if (selectOption) {
                "android.widget.CheckedTextView"
            } else {
                "android.widget.Button"
            }
            info.isClickable = isEnabled
            info.isSelected = checked || selected
            info.isCheckable = selectOption
            info.isChecked = selectOption && (checked || selected)
            if (isEnabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            } else {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
            sheetAncestor()?.sheetCollectionItemInfo(this)?.let { item ->
                info.collectionItemInfo = item
            }
        }
        if (behavior == Behavior.MENU_ITEM) {
            val selectable = menuSelectionMode != MENU_SELECTION_NONE
            info.className = if (selectable) {
                "android.widget.CheckedTextView"
            } else {
                "android.widget.Button"
            }
            info.isClickable = isEnabled
            info.isSelected = selected
            info.isCheckable = selectable
            info.isChecked = selectable && selected
            if (isEnabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            } else {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
            (menuAncestor() ?: menuCollectionOwner)
                ?.menuCollectionItemInfo(this)?.let { item ->
                info.collectionItemInfo = item
            }
        }
        if (behavior == Behavior.INPUT_SLOT) {
            info.className = "android.widget.Button"
            info.isEnabled = isEnabled
            info.isClickable = isEnabled
            if (isEnabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            } else {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
        }
        if (
            behavior == Behavior.INPUT_GROUP
            || behavior == Behavior.FORM_CONTROL
        ) {
            info.className = "android.view.ViewGroup"
        }
        if (behavior == Behavior.TABLE) {
            val rows = tableRows()
            val columns = rows.maxOfOrNull { row -> tableCells(row).size } ?: 0
            info.className = "android.widget.TableLayout"
            info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                rows.size,
                columns,
                false,
            )
        }
        if (behavior == Behavior.TABLE_ROW) {
            info.className = "android.widget.TableRow"
            tableAncestor()?.tableRowCollectionItemInfo(this)?.let { item ->
                info.collectionItemInfo = item
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                info.isHeading = tableHeaderRow
            }
        }
        if (behavior == Behavior.TOAST) {
            info.className = "android.widget.Toast"
        }
        if (behavior == Behavior.SKELETON) {
            info.className = "android.view.View"
        }
        if (behavior == Behavior.FILE_TREE) {
            val items = fileTreeItems().filter(::isFileTreeItemVisible)
            info.className = "android.widget.ListView"
            info.collectionInfo = AccessibilityNodeInfo.CollectionInfo.obtain(
                items.size,
                1,
                true,
                AccessibilityNodeInfo.CollectionInfo.SELECTION_MODE_SINGLE,
            )
        }
        if (
            behavior == Behavior.FILE_TREE_FOLDER
            || behavior == Behavior.FILE_TREE_FILE
        ) {
            updateFileTreeItemAccessibility()
            info.className = "android.widget.Button"
            info.contentDescription = contentDescription
            info.isSelected = isSelected
            info.isClickable = isEnabled
            if (isEnabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            } else {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
            }
            fileTreeAncestor()?.fileTreeCollectionItemInfo(this)?.let { item ->
                info.collectionItemInfo = item
            }
        }
        info.isScrollable = behavior in setOf(
            Behavior.BOTTOM_SHEET,
            Behavior.CALENDAR,
            Behavior.SLIDER,
        )
        if (behavior == Behavior.BOTTOM_SHEET && sheetSnapPoints.size > 1) {
            info.addAction(AccessibilityNodeInfo.ACTION_SCROLL_FORWARD)
            info.addAction(AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD)
        }
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
            if (behavior == Behavior.SLIDER && isEnabled && !readOnly) {
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
            Behavior.CHECKBOX -> if (
                nativeProperties.flag("abstractSelectionItem", false)
            ) {
                "android.widget.Button"
            } else {
                "android.widget.CheckBox"
            }
            Behavior.RADIO -> "android.widget.RadioButton"
            Behavior.SWITCH -> "android.widget.Switch"
            Behavior.CHECKBOX_GROUP -> "android.view.ViewGroup"
            Behavior.RADIO_GROUP -> "android.widget.RadioGroup"
            Behavior.TABS -> "android.widget.TabWidget"
            Behavior.TAB_TRIGGER -> "android.widget.Button"
            Behavior.SHEET_ITEM -> if (component == GeneratedComponents.SELECT_ITEM) {
                "android.widget.CheckedTextView"
            } else {
                "android.widget.Button"
            }
            Behavior.MENU -> "android.widget.ListView"
            Behavior.MENU_ITEM -> if (menuSelectionMode == MENU_SELECTION_NONE) {
                "android.widget.Button"
            } else {
                "android.widget.CheckedTextView"
            }
            Behavior.OVERLAY_DISMISS -> "android.widget.Button"
            Behavior.INPUT_GROUP,
            Behavior.FORM_CONTROL,
            -> "android.view.ViewGroup"
            Behavior.INPUT_SLOT -> "android.widget.Button"
            Behavior.FILE_TREE -> "android.widget.ListView"
            Behavior.FILE_TREE_FOLDER,
            Behavior.FILE_TREE_FILE,
            -> "android.widget.Button"
            Behavior.TABLE -> "android.widget.TableLayout"
            Behavior.TABLE_ROW -> "android.widget.TableRow"
            Behavior.DATE_TIME_PICKER -> "android.widget.DatePicker"
            Behavior.MODAL -> "android.app.Dialog"
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
        if (behavior == Behavior.SWITCH) {
            info.className = "android.widget.Switch"
            info.isClickable = isEnabled
            if (isEnabled) {
                info.addAction(AccessibilityNodeInfo.ACTION_CLICK)
            } else {
                info.removeAction(AccessibilityNodeInfo.AccessibilityAction.ACTION_CLICK)
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
        if (
            behavior == Behavior.SLIDER
            && isEnabled
            && !readOnly
            && action in sliderActions
        ) {
            val positive = action in setOf(
                AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_RIGHT.id,
                AccessibilityNodeInfo.AccessibilityAction.ACTION_SCROLL_UP.id,
            )
            val direction = if (positive) 1.0 else -1.0
            val requested = snapped(value + direction * step)
            if (requested == value) return false
            value = requested
            applyRangeVisualState()
            emitSliderChangeAndEnd()
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
            if (behavior == Behavior.BOTTOM_SHEET) {
                dismissSheet()
            } else {
                requestOverlayDismiss()
            }
            return true
        }
        if (
            behavior == Behavior.BOTTOM_SHEET
            && action in setOf(
                AccessibilityNodeInfo.ACTION_SCROLL_FORWARD,
                AccessibilityNodeInfo.ACTION_SCROLL_BACKWARD,
            )
            && sheetSnapPoints.size > 1
        ) {
            val direction = if (
                action == AccessibilityNodeInfo.ACTION_SCROLL_FORWARD
            ) {
                1
            } else {
                -1
            }
            val requested = (sheetSnapIndex + direction).coerceIn(
                0,
                sheetSnapPoints.lastIndex,
            )
            if (requested == sheetSnapIndex) return false
            settleSheetTo(requested, emit = true)
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
        if (
            behavior == Behavior.SWITCH
            && action == AccessibilityNodeInfo.ACTION_CLICK
            && isEnabled
        ) {
            return performClick()
        }

        return super.performAccessibilityAction(action, arguments)
    }

    override fun dispatchKeyEvent(event: KeyEvent): Boolean {
        if (event.action == KeyEvent.ACTION_UP && event.keyCode == KeyEvent.KEYCODE_BACK) {
            if (behavior.isOverlay() && dismissible && keyboardDismissable) {
                if (behavior == Behavior.BOTTOM_SHEET) {
                    dismissSheet()
                } else {
                    requestOverlayDismiss()
                }
                return true
            }
        }
        if (
            behavior in setOf(
                Behavior.CHECKBOX,
                Behavior.RADIO,
                Behavior.SWITCH,
                Behavior.TAB_TRIGGER,
                Behavior.SHEET_ITEM,
                Behavior.MENU_ITEM,
                Behavior.OVERLAY_DISMISS,
                Behavior.INPUT_SLOT,
                Behavior.FILE_TREE_FOLDER,
                Behavior.FILE_TREE_FILE,
            )
            && isEnabled
            && (behavior == Behavior.SWITCH || !readOnly)
            && event.action == KeyEvent.ACTION_UP
            && event.keyCode in setOf(
                KeyEvent.KEYCODE_SPACE,
                KeyEvent.KEYCODE_ENTER,
                KeyEvent.KEYCODE_DPAD_CENTER,
            )
        ) {
            performClick()
            return true
        }
        if (
            behavior == Behavior.MENU_ITEM
            && isEnabled
            && event.action == KeyEvent.ACTION_DOWN
            && (menuAncestor() ?: menuCollectionOwner)
                ?.handleMenuKey(this, event) == true
        ) {
            return true
        }
        if (
            behavior == Behavior.MENU
            && event.action == KeyEvent.ACTION_DOWN
            && handleMenuKey(null, event)
        ) {
            return true
        }
        if (
            behavior == Behavior.TAB_TRIGGER
            && isEnabled
            && event.action == KeyEvent.ACTION_DOWN
        ) {
            val tabs = tabsAncestor() ?: return super.dispatchKeyEvent(event)
            val direction = when (event.keyCode) {
                KeyEvent.KEYCODE_DPAD_LEFT ->
                    if (tabs.orientation == 1) -1 else 0
                KeyEvent.KEYCODE_DPAD_RIGHT ->
                    if (tabs.orientation == 1) 1 else 0
                KeyEvent.KEYCODE_DPAD_UP ->
                    if (tabs.orientation == 2) -1 else 0
                KeyEvent.KEYCODE_DPAD_DOWN ->
                    if (tabs.orientation == 2) 1 else 0
                KeyEvent.KEYCODE_MOVE_HOME -> Int.MIN_VALUE
                KeyEvent.KEYCODE_MOVE_END -> Int.MAX_VALUE
                else -> 0
            }
            if (direction != 0) {
                return tabs.moveTabFocus(this, direction)
            }
        }
        if (
            behavior == Behavior.SLIDER
            && isEnabled
            && !readOnly
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
            val requested = snapped(value + if (positive) step else -step)
            if (requested == value) return false
            value = requested
            applyRangeVisualState()
            emitSliderChangeAndEnd()
            invalidate()
            return true
        }

        return super.dispatchKeyEvent(event)
    }

    fun release() {
        removeAnchoredTouchDelegate()
        animator?.cancel()
        animator = null
        pendingDismiss?.let(::removeCallbacks)
        pendingDismiss = null
        toastScheduleSignature = null
        toastAnnouncementSignature = null
        pendingAnchoredOpen?.let(::removeCallbacks)
        pendingAnchoredOpen = null
        pendingAnchoredClose?.let(::removeCallbacks)
        pendingAnchoredClose = null
        pendingAnchoredLongPress?.let(::removeCallbacks)
        pendingAnchoredLongPress = null
        anchoredLongPressOpened = false
        dismissActivePickerSilently()
        accordionTouchActive = false
        sliderTouchActive = false
        pendingSliderChange?.let(::removeCallbacks)
        pendingSliderChange = null
        pendingSliderValue = null
        switchAnimator?.cancel()
        switchAnimator = null
        tabsIndicatorAnimator?.cancel()
        tabsIndicatorAnimator = null
        tabsContentAnimator?.cancel()
        tabsContentAnimator = null
        removeCallbacks(treeReconciliation)
        treeReconciliationScheduled = false
        sheetVelocityTracker?.recycle()
        sheetVelocityTracker = null
        anchoredTriggerTouchActive = false
        menuTypeaheadPrefix = ""
        menuTypeaheadAtMillis = 0L
        removeInputFocusObserver()
        restoreManagedInput()
        formLabelTouchActive = false
        formInput = null
        formSignature = null
        formAppliedLabel = null
        formAppliedHelper = null
        inputSlotAppliedLabel = null
        fileTreeExpandedPaths.clear()
        fileTreeSelectedPath = null
        fileTreePath = ""
        fileTreeFolderExpanded = false
        fileTreeFolderInitialized = false
        animate().cancel()
        setOnTouchListener(null)
        setOnClickListener(null)
        restoreFocus()
        accessibilityFocusedCalendarCell = CALENDAR_TARGET_NONE
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            setRenderEffect(null)
        }
    }

    private fun installBehavior() {
        if (behavior == Behavior.INPUT_GROUP) {
            installInputFocusObserver()
        } else {
            removeInputFocusObserver()
            restoreManagedInput()
        }
        setLayerType(
            if (behavior == Behavior.SWITCH) LAYER_TYPE_SOFTWARE else LAYER_TYPE_NONE,
            null,
        )
        setOnTouchListener(
            when (behavior) {
                Behavior.SLIDER -> sliderTouchListener()
                Behavior.BOTTOM_SHEET -> sheetTouchListener()
                Behavior.CALENDAR -> calendarTouchListener()
                Behavior.OVERLAY,
                Behavior.MODAL,
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
            setOnClickListener { toggleSelection() }
        } else if (behavior == Behavior.SWITCH) {
            setOnClickListener { toggleSwitch() }
        } else if (behavior == Behavior.TAB_TRIGGER) {
            setOnClickListener {
                if (isEnabled) {
                    tabsAncestor()?.selectTab(this, emit = true)
                }
            }
        } else if (behavior == Behavior.SHEET_ITEM) {
            setOnClickListener {
                if (isEnabled) {
                    emitter.emit(
                        NativeViewEventKind.PRESS,
                        byteArrayOf(),
                    )
                    if (closeSheetItemOnPress) {
                        sheetAncestor()?.let { sheet ->
                            sheet.clearSheetSearch()
                            sheet.dismissSheetFromItem()
                        }
                    }
                }
            }
        } else if (behavior == Behavior.MENU_ITEM) {
            setOnClickListener {
                if (isEnabled) {
                    val menu = menuAncestor() ?: menuCollectionOwner
                    if (menu != null) {
                        menu.activateMenuItem(this)
                    } else {
                        emitter.emit(NativeViewEventKind.PRESS, byteArrayOf())
                    }
                }
            }
        } else if (behavior == Behavior.OVERLAY_DISMISS) {
            setOnClickListener {
                if (isEnabled) {
                    emitter.emit(NativeViewEventKind.PRESS, byteArrayOf())
                    overlayAncestor()?.requestOverlayDismiss()
                }
            }
        } else if (behavior == Behavior.INPUT_GROUP) {
            setOnClickListener {
                if (isEnabled) requestInputFocus()
            }
        } else if (behavior == Behavior.INPUT_SLOT) {
            setOnClickListener {
                if (isEnabled) activateInputSlot()
            }
        } else if (behavior == Behavior.FILE_TREE_FOLDER) {
            setOnClickListener {
                if (isEnabled) {
                    fileTreeAncestor()?.toggleFileTreeFolder(this)
                }
            }
        } else if (behavior == Behavior.FILE_TREE_FILE) {
            setOnClickListener {
                if (isEnabled) {
                    fileTreeAncestor()?.selectFileTreeItem(this)
                }
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

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            setRenderEffect(null)
        }
    }

    private fun applyComponentDefaults() {
        val interactive = behavior in setOf(
            Behavior.ACCORDION,
            Behavior.SLIDER,
            Behavior.CHECKBOX,
            Behavior.RADIO,
            Behavior.SWITCH,
            Behavior.TABS,
            Behavior.TAB_TRIGGER,
            Behavior.SHEET_ITEM,
            Behavior.MENU_ITEM,
            Behavior.OVERLAY_DISMISS,
            Behavior.INPUT_SLOT,
            Behavior.FILE_TREE_FOLDER,
            Behavior.FILE_TREE_FILE,
            Behavior.CALENDAR,
            Behavior.DATE_TIME_PICKER,
        ) || component in setOf(
            GeneratedComponents.BUTTON,
            GeneratedComponents.FAB,
        )
        if (interactive) {
            minimumWidth = max(minimumWidth, (48f * density).toInt())
            minimumHeight = max(minimumHeight, (48f * density).toInt())
        } else {
            // Hosts are pooled across reconciliations. Do not retain the
            // previous interactive component's minimum touch target.
            minimumWidth = 0
            minimumHeight = 0
        }
        if (behavior == Behavior.SWITCH) {
            minimumWidth = max(minimumWidth, (SWITCH_TRACK_WIDTH_DP * density).toInt())
        }
        foreground = if (
            behavior in setOf(
                Behavior.TAB_TRIGGER,
                Behavior.SHEET_ITEM,
                Behavior.MENU_ITEM,
                Behavior.OVERLAY_DISMISS,
                Behavior.INPUT_SLOT,
                Behavior.FILE_TREE_FOLDER,
                Behavior.FILE_TREE_FILE,
            )
        ) {
            RippleDrawable(
                ColorStateList.valueOf(
                    (fillPaint.color and 0x00ffffff) or RIPPLE_ALPHA_MASK,
                ),
                null,
                ColorDrawable(Color.WHITE),
            )
        } else {
            null
        }
        importantForAccessibility = when {
            behavior == Behavior.INPUT_SLOT
                && inputSlotAction == INPUT_SLOT_ACTION_FOCUS ->
                IMPORTANT_FOR_ACCESSIBILITY_NO
            behavior == Behavior.TABLE_ROW -> IMPORTANT_FOR_ACCESSIBILITY_NO
            behavior == Behavior.SKELETON ->
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            behavior in setOf(
                Behavior.ACCORDION_GROUP,
                Behavior.CHECKBOX_GROUP,
                Behavior.FORM_CONTROL,
                Behavior.INPUT_GROUP,
                Behavior.RADIO_GROUP,
            ) -> IMPORTANT_FOR_ACCESSIBILITY_NO
            behavior.isOverlay() && !open -> IMPORTANT_FOR_ACCESSIBILITY_NO
            else -> IMPORTANT_FOR_ACCESSIBILITY_YES
        }
        if (behavior == Behavior.SKELETON) {
            isClickable = false
            isFocusable = false
            contentDescription = null
            accessibilityLiveRegion = ACCESSIBILITY_LIVE_REGION_NONE
        } else if (behavior == Behavior.TOAST) {
            isFocusable = false
            accessibilityLiveRegion = if (
                toastAction == TOAST_ACTION_WARNING
                || toastAction == TOAST_ACTION_ERROR
                || toastAction == TOAST_ACTION_ATTENTION
            ) {
                ACCESSIBILITY_LIVE_REGION_ASSERTIVE
            } else {
                ACCESSIBILITY_LIVE_REGION_POLITE
            }
        } else {
            accessibilityLiveRegion = ACCESSIBILITY_LIVE_REGION_NONE
        }
    }

    private fun applyMaterialSpecialization(previousBehavior: Behavior) {
        when (behavior) {
            Behavior.SPARKLINE -> {
                if (
                    previousBehavior != behavior
                    && nativeProperties.flag("autoDraw", false)
                    && animationsEnabled()
                ) {
                    animate().cancel()
                    alpha = 0f
                    scaleX = 0.15f
                    pivotX = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                        width.toFloat()
                    } else {
                        0f
                    }
                    animate()
                        .alpha(1f)
                        .scaleX(1f)
                        .setDuration(
                            nativeProperties.integer("autoDrawDuration", 800L)
                                .coerceIn(120L, 4_000L),
                        )
                        .start()
                }
            }
            else -> Unit
        }
    }

    private fun drawSparkline(canvas: Canvas) {
        val points = (
            nativeProperties.text("values")
                ?: nativeProperties.text("value")
                ?: return
        ).split(',', '\n', ';', ' ')
            .mapNotNull(String::toFloatOrNull)
        if (points.size < 2 || width <= 0 || height <= 0) return
        val low = points.minOrNull() ?: return
        val high = points.maxOrNull() ?: return
        val spread = (high - low).takeIf { it > 0f } ?: 1f
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            color = fillPaint.color
            style = Paint.Style.STROKE
            strokeWidth = nativeProperties.decimal("lineWidth", 2.5).toFloat() * density
            strokeCap = Paint.Cap.ROUND
            strokeJoin = Paint.Join.ROUND
        }
        val horizontal = width.toFloat() / (points.size - 1)
        val path = android.graphics.Path()
        points.forEachIndexed { index, point ->
            val x = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                width - index * horizontal
            } else {
                index * horizontal
            }
            val y = height - ((point - low) / spread * height)
            if (index == 0) path.moveTo(x, y) else path.lineTo(x, y)
        }
        canvas.drawPath(path, paint)
    }

    private fun installInputFocusObserver() {
        if (inputFocusObserver != null) return
        inputFocusObserver = ViewTreeObserver.OnGlobalFocusChangeListener { _, focused ->
            val next = focused != null && containsView(this, focused)
            if (next == inputFocused) return@OnGlobalFocusChangeListener
            inputFocused = next
            isActivated = next
            invalidate()
        }.also { listener ->
            viewTreeObserver.addOnGlobalFocusChangeListener(listener)
        }
    }

    private fun removeInputFocusObserver() {
        val listener = inputFocusObserver ?: return
        val observer = viewTreeObserver
        if (observer.isAlive) {
            observer.removeOnGlobalFocusChangeListener(listener)
        }
        inputFocusObserver = null
        inputFocused = false
        isActivated = false
    }

    private fun applyInputGroupState() {
        if (behavior != Behavior.INPUT_GROUP) return
        val input = findFirstEditText(this)
        if (managedInput !== input) {
            restoreManagedInput()
            managedInput = input
            managedInputKeyListener = input?.keyListener
            managedInputTransformation = input?.transformationMethod
        }
        input ?: return

        input.isEnabled = isEnabled
        input.isFocusable = isEnabled
        input.isFocusableInTouchMode = isEnabled
        input.isCursorVisible = isEnabled && !readOnly
        input.showSoftInputOnFocus = isEnabled && !readOnly
        if (readOnly) {
            if (input.keyListener != null) {
                managedInputKeyListener = input.keyListener
            }
            input.keyListener = null
        } else if (input.keyListener == null && managedInputKeyListener != null) {
            input.keyListener = managedInputKeyListener
        }
        val nextFocused = input.hasFocus()
        if (nextFocused != inputFocused) {
            inputFocused = nextFocused
            isActivated = nextFocused
            invalidate()
        }
        updateInputSlotDescendants(this)
    }

    private fun restoreManagedInput() {
        managedInput?.let { input ->
            if (input.keyListener == null && managedInputKeyListener != null) {
                input.keyListener = managedInputKeyListener
            }
            if (managedInputTransformation != null) {
                input.transformationMethod = managedInputTransformation
            }
            input.showSoftInputOnFocus = true
            input.isCursorVisible = true
        }
        managedInput = null
        managedInputKeyListener = null
        managedInputTransformation = null
    }

    private fun requestInputFocus(): Boolean {
        if (!isEnabled) return false
        val input = findFirstEditText(this) ?: return false
        input.isFocusable = true
        input.isFocusableInTouchMode = true
        val focused = if (input.isInTouchMode) {
            input.requestFocusFromTouch()
        } else {
            input.requestFocus()
        }
        if (focused && !readOnly) {
            input.post {
                input.setSelection(input.text.length)
            }
        }
        return focused
    }

    private fun activateInputSlot() {
        emitter.emit(NativeViewEventKind.PRESS, byteArrayOf())
        val group = inputGroupAncestor()
        when (inputSlotAction) {
            INPUT_SLOT_ACTION_CLEAR -> group?.clearInput()
            INPUT_SLOT_ACTION_TOGGLE_PASSWORD -> group?.toggleInputPassword()
            INPUT_SLOT_ACTION_FOCUS -> group?.requestInputFocus()
        }
        if (
            inputSlotFocusOnPress
            && inputSlotAction != INPUT_SLOT_ACTION_FOCUS
            && inputSlotAction != INPUT_SLOT_ACTION_NONE
        ) {
            group?.requestInputFocus()
        }
        updateInputSlotAccessibility()
    }

    private fun clearInput() {
        if (!isEnabled || readOnly) return
        val input = findFirstEditText(this) ?: return
        input.text?.clear()
    }

    private fun toggleInputPassword() {
        if (!isEnabled) return
        val input = findFirstEditText(this) ?: return
        val cursor = input.selectionStart.coerceAtLeast(0)
        input.transformationMethod = if (
            input.transformationMethod is PasswordTransformationMethod
        ) {
            null
        } else {
            PasswordTransformationMethod.getInstance()
        }
        input.setSelection(cursor.coerceAtMost(input.text.length))
    }

    private fun updateInputSlotAccessibility() {
        if (behavior != Behavior.INPUT_SLOT) return
        val group = inputGroupAncestor()
        val passwordHidden = group
            ?.findFirstEditText(group)
            ?.transformationMethod is PasswordTransformationMethod
        val fallback = when (inputSlotAction) {
            INPUT_SLOT_ACTION_CLEAR -> "Clear input"
            INPUT_SLOT_ACTION_TOGGLE_PASSWORD -> if (passwordHidden) {
                "Show password"
            } else {
                "Hide password"
            }
            else -> null
        }
        if (
            fallback != null
            && (
                contentDescription.isNullOrEmpty()
                    || contentDescription == inputSlotAppliedLabel
            )
        ) {
            contentDescription = fallback
            inputSlotAppliedLabel = fallback
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = if (
                inputSlotAction == INPUT_SLOT_ACTION_TOGGLE_PASSWORD
            ) {
                if (passwordHidden) "Password hidden" else "Password visible"
            } else {
                null
            }
        }
    }

    private fun inputGroupAncestor(): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (current is MobileUiHost && current.behavior == Behavior.INPUT_GROUP) {
                return current
            }
            current = current.parent
        }
        return null
    }

    private fun updateInputSlotDescendants(root: ViewGroup) {
        repeat(root.childCount) { index ->
            val child = root.getChildAt(index)
            if (child is MobileUiHost && child.behavior == Behavior.INPUT_SLOT) {
                child.updateInputSlotAccessibility()
            } else if (
                child is ViewGroup
                && !(child is MobileUiHost && child.behavior == Behavior.INPUT_GROUP)
            ) {
                updateInputSlotDescendants(child)
            }
        }
    }

    private fun applyFormControlSemantics() {
        if (behavior != Behavior.FORM_CONTROL) return
        val input = findFirstEditText(this) ?: return
        val label = findFirstText(findTaggedDescendant(this, FORM_LABEL_TAG))
        val helper = findFirstText(findTaggedDescendant(this, FORM_HELPER_TAG))
        val error = accessibilityErrorMessage
            ?: findFirstText(findTaggedDescendant(this, FORM_ERROR_TAG))
        val signature = listOf(
            label,
            helper,
            error,
            required.toString(),
            invalid.toString(),
            readOnly.toString(),
            isEnabled.toString(),
        ).joinToString("\u0000")
        if (formInput === input && formSignature == signature) return
        formInput = input
        formSignature = signature

        if (
            !label.isNullOrEmpty()
            && (
                input.contentDescription.isNullOrEmpty()
                    || input.contentDescription == formAppliedLabel
            )
        ) {
            input.contentDescription = label
            formAppliedLabel = label
        } else if (
            label.isNullOrEmpty()
            && input.contentDescription == formAppliedLabel
        ) {
            input.contentDescription = null
            formAppliedLabel = null
        }
        if (
            !helper.isNullOrEmpty()
            && (
                input.tooltipText.isNullOrEmpty()
                    || input.tooltipText == formAppliedHelper
            )
        ) {
            input.tooltipText = helper
            formAppliedHelper = helper
        } else if (
            helper.isNullOrEmpty()
            && input.tooltipText == formAppliedHelper
        ) {
            input.tooltipText = null
            formAppliedHelper = null
        }
        findTaggedDescendant(this, FORM_ERROR_TAG)?.accessibilityLiveRegion =
            if (invalid) {
                ACCESSIBILITY_LIVE_REGION_ASSERTIVE
            } else {
                ACCESSIBILITY_LIVE_REGION_NONE
            }
        input.accessibilityDelegate = object : View.AccessibilityDelegate() {
            override fun onInitializeAccessibilityNodeInfo(
                host: View,
                info: AccessibilityNodeInfo,
            ) {
                super.onInitializeAccessibilityNodeInfo(host, info)
                info.className = "android.widget.EditText"
                info.isEnabled = this@MobileUiHost.isEnabled
                info.isContentInvalid = this@MobileUiHost.invalid
                if (this@MobileUiHost.invalid && !error.isNullOrEmpty()) {
                    info.error = error
                }
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                    info.stateDescription = buildList {
                        if (this@MobileUiHost.required) add("Required")
                        if (this@MobileUiHost.readOnly) add("Read only")
                        if (this@MobileUiHost.invalid) add("Invalid")
                    }.joinToString(", ").ifEmpty { null }
                }
            }
        }
    }

    private fun formLabelBounds(): RectF? =
        findTaggedDescendant(this, FORM_LABEL_TAG)?.let(::boundsInHost)

    private fun drawInputOutline(canvas: Canvas) {
        if ((!inputFocused && !invalid) || width <= 0 || height <= 0) return
        inputOutlinePaint.color = if (invalid) inputInvalidColor else inputFocusColor
        inputOutlinePaint.strokeWidth = inputOutlineWidth * density
        val halfStroke = inputOutlinePaint.strokeWidth / 2f
        val bounds = RectF(
            halfStroke,
            halfStroke,
            width - halfStroke,
            height - halfStroke,
        )
        val radius = inputOutlineRadius * density
        canvas.drawRoundRect(bounds, radius, radius, inputOutlinePaint)
    }

    @Suppress("DEPRECATION")
    private fun applyTableSemantics() {
        if (behavior != Behavior.TABLE || !tableSemanticsDirty) return
        tableSemanticsDirty = false
        tableRows().forEachIndexed { rowIndex, row ->
            val heading = row.tableHeaderRow
            tableCells(row).forEachIndexed { columnIndex, cell ->
                cell.accessibilityDelegate = object : View.AccessibilityDelegate() {
                    override fun onInitializeAccessibilityNodeInfo(
                        host: View,
                        info: AccessibilityNodeInfo,
                    ) {
                        super.onInitializeAccessibilityNodeInfo(host, info)
                        info.collectionItemInfo =
                            AccessibilityNodeInfo.CollectionItemInfo.obtain(
                                rowIndex,
                                1,
                                columnIndex,
                                1,
                                heading,
                                false,
                            )
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                            info.isHeading = heading
                        }
                    }
                }
            }
        }
    }

    private fun tableRows(root: ViewGroup = this): List<MobileUiHost> =
        buildList {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (child is MobileUiHost && child.behavior == Behavior.TABLE_ROW) {
                    add(child)
                } else if (
                    child is MobileUiHost
                    && child.behavior == Behavior.TABLE
                ) {
                    Unit
                } else if (child is ViewGroup) {
                    addAll(tableRows(child))
                }
            }
        }

    private fun tableCells(root: ViewGroup): List<TextView> =
        buildList {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (child is TextView && child !is EditText) {
                    add(child)
                } else if (
                    child is ViewGroup
                    && !(child is MobileUiHost && child.behavior == Behavior.TABLE_ROW)
                ) {
                    addAll(tableCells(child))
                }
            }
        }

    private fun tableAncestor(): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (current is MobileUiHost && current.behavior == Behavior.TABLE) {
                return current
            }
            current = current.parent
        }
        return null
    }

    @Suppress("DEPRECATION")
    private fun tableRowCollectionItemInfo(
        row: MobileUiHost,
    ): AccessibilityNodeInfo.CollectionItemInfo? {
        val rows = tableRows()
        val rowIndex = rows.indexOf(row)
        if (rowIndex < 0) return null
        return AccessibilityNodeInfo.CollectionItemInfo.obtain(
            rowIndex,
            1,
            0,
            max(1, tableCells(row).size),
            row.tableHeaderRow,
            false,
        )
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

    private fun toggleSwitch() {
        if (!isEnabled) return
        checked = !checked
        isActivated = checked
        animateSwitch()
        updateSwitchAccessibility()
        performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
        emitter.emit(NativeViewEventKind.TOGGLE, checked.toEventPayload())
        sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_CLICKED)
    }

    private fun animateSwitch() {
        val target = if (checked) 1f else 0f
        switchAnimator?.cancel()
        if (!animationsEnabled()) {
            switchVisualProgress = target
            invalidate()
            return
        }
        switchAnimator = ValueAnimator.ofFloat(switchVisualProgress, target).apply {
            duration = SWITCH_ANIMATION_DURATION_MILLIS
            addUpdateListener { animation ->
                switchVisualProgress = animation.animatedValue as Float
                invalidate()
            }
            start()
        }
    }

    private fun updateSwitchAccessibility() {
        if (behavior != Behavior.SWITCH) return
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = customStateDescription
                ?: if (checked) "On" else "Off"
        }
    }

    private fun toggleSelection() {
        if (!isEnabled || readOnly) return
        if (behavior == Behavior.RADIO) {
            if (checked || !radioGroupAllowsSelection()) return
            setSelectionChecked(true)
        } else {
            checked = if (indeterminate) true else !checked
            indeterminate = false
            setSelectionChecked(checked, force = true)
        }
        performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
        emitter.emit(NativeViewEventKind.TOGGLE, checked.toEventPayload())
        sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_CLICKED)
    }

    private fun setSelectionChecked(
        requested: Boolean,
        force: Boolean = false,
    ) {
        if (!force && checked == requested) return
        checked = requested
        isActivated = requested
        applySelectionVisualState()
        updateSelectionAccessibility()
        invalidate()
        sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)
    }

    private fun radioGroupAllowsSelection(): Boolean {
        val group = selectionGroupAncestor()
        return group?.takeIf { it.behavior == Behavior.RADIO_GROUP }
            ?.selectRadioItem(this)
            ?: true
    }

    private fun selectRadioItem(item: MobileUiHost): Boolean {
        if (!isEnabled || readOnly) return false
        selectionItems(this).forEach { sibling ->
            if (
                sibling !== item
                && sibling.behavior == Behavior.RADIO
                && sibling.checked
            ) {
                sibling.setSelectionChecked(false)
            }
        }
        return true
    }

    private fun selectionItems(root: ViewGroup): Sequence<MobileUiHost> =
        sequence {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                when {
                    child is MobileUiHost
                        && child.behavior in setOf(
                            Behavior.CHECKBOX,
                            Behavior.RADIO,
                        ) -> yield(child)
                    child is MobileUiHost
                        && child.behavior in setOf(
                            Behavior.CHECKBOX_GROUP,
                            Behavior.RADIO_GROUP,
                        ) -> Unit
                    child is ViewGroup -> yieldAll(selectionItems(child))
                }
            }
        }

    @Suppress("DEPRECATION")
    private fun selectionCollectionItemInfo():
        AccessibilityNodeInfo.CollectionItemInfo? {
        val group = selectionGroupAncestor() ?: return null
        val items = selectionItems(group).toList()
        val index = items.indexOf(this)
        if (index < 0) return null
        return AccessibilityNodeInfo.CollectionItemInfo.obtain(
            index,
            1,
            0,
            1,
            false,
            checked,
        )
    }

    private fun selectionGroupAncestor(): MobileUiHost? {
        var ancestor = parent
        while (ancestor is View) {
            if (
                ancestor is MobileUiHost
                && ancestor.behavior in setOf(
                    Behavior.CHECKBOX_GROUP,
                    Behavior.RADIO_GROUP,
                )
            ) {
                return ancestor
            }
            ancestor = ancestor.parent
        }
        return null
    }

    private fun applySelectionVisualState() {
        if (behavior != Behavior.CHECKBOX && behavior != Behavior.RADIO) return
        findTaggedDescendant(this, SELECTION_INDICATOR_TAG)?.apply {
            isClickable = false
            isLongClickable = false
            isFocusable = false
            importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
        }
        val forcedIcon = findTaggedDescendant(this, SELECTION_FORCE_ICON_TAG)
        val icon = forcedIcon
            ?: findTaggedDescendant(this, SELECTION_ICON_TAG)
            ?: return
        icon.visibility = if (
            forcedIcon != null
            || checked
            || indeterminate
        ) {
            VISIBLE
        } else {
            GONE
        }
        icon.isClickable = false
        icon.isLongClickable = false
        icon.isFocusable = false
        icon.importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
    }

    private fun updateSelectionAccessibility() {
        if (behavior != Behavior.CHECKBOX && behavior != Behavior.RADIO) return
        val label = findFirstText(this)
        if (contentDescription.isNullOrEmpty() && !label.isNullOrEmpty()) {
            contentDescription = label
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = when {
                indeterminate && readOnly -> "Mixed, read only"
                indeterminate -> "Mixed"
                readOnly -> "Read only"
                else -> customStateDescription
            }
        }
    }

    private fun applyRangeVisualState() {
        if (behavior != Behavior.SLIDER && behavior != Behavior.PROGRESS) return
        if (
            behavior == Behavior.SLIDER
            && nativeProperties.flag("rating", false)
        ) {
            findTaggedDescendant(this, SLIDER_TRACK_TAG)?.alpha = 0f
            findTaggedDescendant(this, SLIDER_FILLED_TRACK_TAG)?.alpha = 0f
            findTaggedDescendant(this, SLIDER_THUMB_TAG)?.alpha = 0f
            invalidate()
            return
        }
        val progress = rangeProgress()
        val filledTag = if (behavior == Behavior.SLIDER) {
            SLIDER_FILLED_TRACK_TAG
        } else {
            PROGRESS_FILLED_TRACK_TAG
        }
        val filled = findTaggedDescendant(this, filledTag)
        val filledParent = filled?.parent as? ViewGroup
        if (
            filled != null
            && filledParent != null
            && filledParent.width > 0
            && filledParent.height > 0
        ) {
            filled.alpha = if (rangeEnabled && behavior == Behavior.SLIDER) 0f else 1f
            filled.translationX = 0f
            filled.translationY = 0f
            filled.layout(0, 0, filledParent.width, filledParent.height)
            if (orientation == 2) {
                filled.pivotX = 0f
                filled.pivotY = if (
                    behavior == Behavior.SLIDER && reversed
                ) {
                    0f
                } else {
                    filledParent.height.toFloat()
                }
                filled.scaleX = 1f
                filled.scaleY = progress
            } else {
                filled.pivotX = if (
                    behavior == Behavior.SLIDER && reversed
                ) {
                    filledParent.width.toFloat()
                } else {
                    0f
                }
                filled.pivotY = 0f
                filled.scaleX = progress
                filled.scaleY = 1f
            }
            filled.importantForAccessibility =
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
        }

        if (behavior != Behavior.SLIDER) return
        val track = findTaggedDescendant(this, SLIDER_TRACK_TAG)
        if (track != null) {
            track.importantForAccessibility =
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            if (orientation == 2 && track.width > 0) {
                track.scaleX = (trackThickness * density / track.width).toFloat()
                track.scaleY = 1f
                track.pivotX = track.width / 2f
            } else if (orientation != 2 && track.height > 0) {
                track.scaleX = 1f
                track.scaleY = (trackThickness * density / track.height).toFloat()
                track.pivotY = track.height / 2f
            }
        }
        val thumb = findTaggedDescendant(this, SLIDER_THUMB_TAG) ?: return
        thumb.importantForAccessibility =
            IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
        thumb.translationX = 0f
        thumb.translationY = 0f
        if (thumb.width <= 0 || thumb.height <= 0) return
        val authoredThumbSize = max(thumb.width, thumb.height).toFloat()
        val thumbScale = (sliderThumbSize * density / authoredThumbSize).toFloat()
        thumb.scaleX = thumbScale
        thumb.scaleY = thumbScale
        thumb.pivotX = thumb.width / 2f
        thumb.pivotY = thumb.height / 2f
        val trackBounds = sliderTrackBounds(includeHitSlop = false)
        val thumbBounds = descendantBounds(thumb)
        val position = if (reversed) 1f - progress else progress
        if (orientation == 2) {
            val targetY = trackBounds.bottom - trackBounds.height() * position
            thumb.translationY = targetY - thumbBounds.centerY()
            thumb.translationX = trackBounds.centerX() - thumbBounds.centerX()
        } else {
            val targetX = trackBounds.left + trackBounds.width() * position
            thumb.translationX = targetX - thumbBounds.centerX()
            thumb.translationY = trackBounds.centerY() - thumbBounds.centerY()
        }
    }

    private fun rangeProgress(): Float =
        ((value - minimum) / (maximum - minimum))
            .coerceIn(0.0, 1.0)
            .toFloat()

    private fun sliderProgress(current: Double): Float =
        ((current - minimum) / (maximum - minimum))
            .coerceIn(0.0, 1.0)
            .toFloat()

    private fun sliderPoint(bounds: RectF, current: Double): android.graphics.PointF {
        var progress = sliderProgress(current)
        if (reversed) progress = 1f - progress
        return if (orientation == 2) {
            android.graphics.PointF(
                bounds.centerX(),
                bounds.bottom - bounds.height() * progress,
            )
        } else {
            android.graphics.PointF(
                bounds.left + bounds.width() * progress,
                bounds.centerY(),
            )
        }
    }

    private fun drawSliderDecorations(canvas: Canvas) {
        if (behavior != Behavior.SLIDER) return
        val bounds = sliderTrackBounds(includeHitSlop = false)
        if (bounds.width() <= 0f || bounds.height() <= 0f) return

        val previousTrackStyle = trackPaint.style
        val previousTrackWidth = trackPaint.strokeWidth
        val previousFillStyle = fillPaint.style
        val previousFillWidth = fillPaint.strokeWidth
        val previousFillCap = fillPaint.strokeCap

        if (showSliderTicks || alwaysShowSliderTicks) {
            val intervals = minOf(
                100,
                maxOf(1, round((maximum - minimum) / step).toInt()),
            )
            trackPaint.style = Paint.Style.FILL
            for (index in 0..intervals) {
                val tickValue = minimum + (maximum - minimum) * index / intervals
                val point = sliderPoint(bounds, tickValue)
                canvas.drawCircle(point.x, point.y, 1.5f * density, trackPaint)
            }
        }

        if (rangeEnabled) {
            val lower = sliderPoint(bounds, lowerValue)
            val upper = sliderPoint(bounds, upperValue)
            fillPaint.style = Paint.Style.STROKE
            fillPaint.strokeWidth = (trackThickness * density).toFloat()
            fillPaint.strokeCap = Paint.Cap.ROUND
            canvas.drawLine(lower.x, lower.y, upper.x, upper.y, fillPaint)

            fillPaint.style = Paint.Style.FILL
            val radius = (sliderThumbSize * density / 2.0).toFloat()
            canvas.drawCircle(lower.x, lower.y, radius, fillPaint)
        }

        if (showThumbLabel || alwaysShowThumbLabel) {
            val values = if (rangeEnabled) {
                listOf(lowerValue, upperValue)
            } else {
                listOf(value)
            }
            val textPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = fillPaint.color
                textAlign = Paint.Align.CENTER
                textSize = 12f * density
                typeface = android.graphics.Typeface.create(
                    android.graphics.Typeface.DEFAULT,
                    android.graphics.Typeface.BOLD,
                )
            }
            values.forEach { current ->
                val point = sliderPoint(bounds, current)
                val label = formatRangeValue(current)
                val bubbleWidth = maxOf(
                    32f * density,
                    textPaint.measureText(label) + 16f * density,
                )
                val bubbleHeight = 28f * density
                val bubble = if (orientation == 2) {
                    RectF(
                        point.x + 16f * density,
                        point.y - bubbleHeight / 2f,
                        point.x + 16f * density + bubbleWidth,
                        point.y + bubbleHeight / 2f,
                    )
                } else {
                    RectF(
                        point.x - bubbleWidth / 2f,
                        point.y - 40f * density,
                        point.x + bubbleWidth / 2f,
                        point.y - 12f * density,
                    )
                }
                canvas.drawRoundRect(
                    bubble,
                    6f * density,
                    6f * density,
                    fillPaint,
                )
                textPaint.color = Color.WHITE
                val baseline = bubble.centerY() -
                    (textPaint.ascent() + textPaint.descent()) / 2f
                canvas.drawText(label, bubble.centerX(), baseline, textPaint)
            }
        }

        trackPaint.style = previousTrackStyle
        trackPaint.strokeWidth = previousTrackWidth
        fillPaint.style = previousFillStyle
        fillPaint.strokeWidth = previousFillWidth
        fillPaint.strokeCap = previousFillCap
    }

    private fun drawRating(canvas: Canvas) {
        if (width <= 0 || height <= 0) return
        val length = nativeProperties.integer("length", 5L)
            .toInt()
            .coerceIn(1, 20)
        val star = "\u2605"
        val stars = star.repeat(length)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            typeface = android.graphics.Typeface.create(
                android.graphics.Typeface.DEFAULT,
                android.graphics.Typeface.NORMAL,
            )
            textAlign = Paint.Align.LEFT
            textSize = minOf(
                height * 0.78f,
                width.toFloat() / length * 0.88f,
            )
        }
        val textWidth = paint.measureText(stars)
        val left = (width - textWidth) / 2f
        val baseline = height / 2f - (paint.ascent() + paint.descent()) / 2f
        paint.color = trackPaint.color
        canvas.drawText(stars, left, baseline, paint)

        val fraction = rangeProgress()
        val fillFromEnd = reversed xor (
            layoutDirection == LAYOUT_DIRECTION_RTL
        )
        val checkpoint = canvas.save()
        canvas.clipRect(
            if (fillFromEnd) {
                left + textWidth * (1f - fraction)
            } else {
                left
            },
            0f,
            if (fillFromEnd) {
                left + textWidth
            } else {
                left + textWidth * fraction
            },
            height.toFloat(),
        )
        paint.color = fillPaint.color
        canvas.drawText(stars, left, baseline, paint)
        canvas.restoreToCount(checkpoint)
    }

    private fun updateProgressAnimation() {
        if (
            behavior != Behavior.PROGRESS
            || !indeterminate
            || !animationsEnabled()
        ) {
            progressAnimator?.cancel()
            progressAnimator = null
            progressRotation = 0f
            return
        }
        if (progressAnimator?.isRunning == true) return
        progressAnimator = ValueAnimator.ofFloat(0f, 360f).apply {
            duration = 1_333L
            interpolator = android.view.animation.LinearInterpolator()
            repeatCount = ValueAnimator.INFINITE
            addUpdateListener {
                progressRotation = it.animatedValue as Float
                invalidate()
            }
            start()
        }
    }

    private fun drawLinearProgress(canvas: Canvas) {
        if (width <= 0 || height <= 0) return
        val thickness = (trackThickness * density).toFloat()
            .coerceIn(1f, height.toFloat())
        val top = (height - thickness) / 2f
        val track = RectF(0f, top, width.toFloat(), top + thickness)
        val radius = thickness / 2f
        canvas.drawRoundRect(track, radius, radius, trackPaint)

        val reverse = reversed xor (layoutDirection == LAYOUT_DIRECTION_RTL)
        val animated = (progressRotation / 360f).coerceIn(0f, 1f)
        val fraction = if (indeterminate) 0.34f else rangeProgress()
        val startFraction = if (indeterminate) {
            (animated * 1.34f - 0.34f).coerceIn(0f, 1f)
        } else {
            0f
        }
        val endFraction = if (indeterminate) {
            (startFraction + fraction).coerceIn(0f, 1f)
        } else {
            fraction
        }
        val fill = if (reverse) {
            RectF(
                width * (1f - endFraction),
                top,
                width * (1f - startFraction),
                top + thickness,
            )
        } else {
            RectF(
                width * startFraction,
                top,
                width * endFraction,
                top + thickness,
            )
        }
        canvas.drawRoundRect(fill, radius, radius, fillPaint)

        if (nativeProperties.flag("striped", false) && fill.width() > 0f) {
            val checkpoint = canvas.save()
            canvas.clipRect(fill)
            val stripePaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = Color.argb(72, 255, 255, 255)
                strokeWidth = maxOf(2f * density, thickness / 2f)
            }
            var x = fill.left - thickness
            while (x < fill.right + thickness) {
                canvas.drawLine(
                    x,
                    fill.bottom,
                    x + thickness,
                    fill.top,
                    stripePaint,
                )
                x += thickness
            }
            canvas.restoreToCount(checkpoint)
        }

        if (nativeProperties.flag("stream", false)) {
            val streamPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = fillPaint.color
                alpha = 96
                strokeWidth = maxOf(1f, thickness / 3f)
                pathEffect = android.graphics.DashPathEffect(
                    floatArrayOf(4f * density, 4f * density),
                    progressRotation / 18f,
                )
            }
            val y = track.bottom + 3f * density
            canvas.drawLine(track.left, y, track.right, y, streamPaint)
        }
    }

    private fun drawCircularProgress(canvas: Canvas) {
        val stroke = (trackThickness * density).toFloat()
            .coerceAtMost(minOf(width, height) / 2f)
        if (stroke <= 0f) return
        val inset = stroke / 2f
        val bounds = RectF(
            inset,
            inset,
            width.toFloat() - inset,
            height.toFloat() - inset,
        )
        if (bounds.width() <= 0f || bounds.height() <= 0f) return

        val previousTrackStyle = trackPaint.style
        val previousTrackWidth = trackPaint.strokeWidth
        val previousFillStyle = fillPaint.style
        val previousFillWidth = fillPaint.strokeWidth
        val previousFillCap = fillPaint.strokeCap
        trackPaint.style = Paint.Style.STROKE
        trackPaint.strokeWidth = stroke
        fillPaint.style = Paint.Style.STROKE
        fillPaint.strokeWidth = stroke
        fillPaint.strokeCap = Paint.Cap.ROUND
        canvas.drawOval(bounds, trackPaint)
        canvas.drawArc(
            bounds,
            if (indeterminate) progressRotation - 90f else -90f,
            if (indeterminate) 270f else 360f * rangeProgress(),
            false,
            fillPaint,
        )
        trackPaint.style = previousTrackStyle
        trackPaint.strokeWidth = previousTrackWidth
        fillPaint.style = previousFillStyle
        fillPaint.strokeWidth = previousFillWidth
        fillPaint.strokeCap = previousFillCap
    }

    private fun updateRangeAccessibility() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.R) return
        stateDescription = when (behavior) {
            Behavior.PROGRESS -> "${(rangeProgress() * 100f).roundToInt()}%"
            Behavior.SLIDER -> customStateDescription ?: if (rangeEnabled) {
                "${formatRangeValue(lowerValue)} to ${formatRangeValue(upperValue)}"
            } else {
                formatRangeValue(value)
            }
            else -> stateDescription
        }
    }

    private fun formatRangeValue(current: Double): String =
        if (current == round(current)) {
            current.toLong().toString()
        } else {
            current.toString()
        }

    private fun sliderTrackBounds(includeHitSlop: Boolean = true): RectF {
        val track = findTaggedDescendant(this, SLIDER_TRACK_TAG)
        val bounds = if (track == null || track.width <= 0 || track.height <= 0) {
            RectF(0f, 0f, width.toFloat(), height.toFloat())
        } else {
            descendantBounds(track)
        }
        if (includeHitSlop) {
            val minimumTarget = 48f * density
            val horizontalInset = max(0f, (minimumTarget - bounds.width()) / 2f)
            val verticalInset = max(0f, (minimumTarget - bounds.height()) / 2f)
            bounds.inset(-horizontalInset, -verticalInset)
        }

        return bounds
    }

    private fun descendantBounds(descendant: View): RectF {
        val bounds = Rect(0, 0, descendant.width, descendant.height)
        offsetDescendantRectToMyCoords(descendant, bounds)
        return RectF(bounds)
    }

    private fun drawSwitch(canvas: Canvas) {
        val anchor = findTaggedDescendant(this, SWITCH_TRACK_TAG)
        val anchorBounds = if (anchor != null && anchor.width > 0 && anchor.height > 0) {
            descendantBounds(anchor)
        } else {
            RectF(0f, 0f, width.toFloat(), height.toFloat())
        }
        val trackWidth = minOf(anchorBounds.width(), SWITCH_TRACK_WIDTH_DP * density)
        val trackHeight = minOf(anchorBounds.height(), SWITCH_TRACK_HEIGHT_DP * density)
        if (trackWidth <= 0f || trackHeight <= 0f) return
        val left = anchorBounds.left + (anchorBounds.width() - trackWidth) / 2f
        val top = anchorBounds.top + (anchorBounds.height() - trackHeight) / 2f
        val track = RectF(left, top, left + trackWidth, top + trackHeight)
        val radius = trackHeight / 2f
        switchTrackPaint.color = blendColor(
            switchTrackOffColor,
            switchTrackOnColor,
            switchVisualProgress,
        )
        canvas.drawRoundRect(track, radius, radius, switchTrackPaint)

        val inset = SWITCH_THUMB_INSET_DP * density
        val thumbRadius = max(0f, (trackHeight - inset * 2f) / 2f)
        val startX = track.left + inset + thumbRadius
        val endX = track.right - inset - thumbRadius
        val centerX = startX + (endX - startX) * switchVisualProgress
        switchThumbPaint.color = blendColor(
            switchThumbColor,
            switchActiveThumbColor,
            switchVisualProgress,
        )
        canvas.drawCircle(centerX, track.centerY(), thumbRadius, switchThumbPaint)
    }

    private fun blendColor(from: Int, to: Int, progress: Float): Int {
        val amount = progress.coerceIn(0f, 1f)
        val alpha = Color.alpha(from) + (
            (Color.alpha(to) - Color.alpha(from)) * amount
        ).roundToInt()
        val red = Color.red(from) + (
            (Color.red(to) - Color.red(from)) * amount
        ).roundToInt()
        val green = Color.green(from) + (
            (Color.green(to) - Color.green(from)) * amount
        ).roundToInt()
        val blue = Color.blue(from) + (
            (Color.blue(to) - Color.blue(from)) * amount
        ).roundToInt()
        return Color.argb(alpha, red, green, blue)
    }

    private fun animateEntrance() {
        if (!open) return
        if (!behavior.isOverlay() && behavior != Behavior.TOAST) return
        if (behavior.isAnchoredOverlay()) {
            applyAnchoredOverlayState(animate = true)
            return
        }
        if (!animationsEnabled()) return
        if (behavior == Behavior.BOTTOM_SHEET) {
            alpha = 1f
            post {
                val content = sheetContent() ?: return@post
                applySheetLayout(animate = false)
                val target = content.translationY
                content.translationY = sheetDismissTranslation(content)
                content.animate()
                    .translationY(target)
                    .setDuration(SHEET_ENTRANCE_ANIMATION_DURATION_MILLIS)
                    .start()
                val backdrop = sheetBackdrop()
                if (backdrop != null) {
                    val targetAlpha = sheetBackdropBaseAlpha ?: backdrop.alpha
                    sheetBackdropBaseAlpha = targetAlpha
                    backdrop.alpha = 0f
                    backdrop.animate()
                        .alpha(targetAlpha)
                        .setDuration(SHEET_ENTRANCE_ANIMATION_DURATION_MILLIS)
                        .start()
                }
            }
            return
        }
        alpha = 0f
        animate()
            .alpha(1f)
            .translationX(0f)
            .translationY(0f)
            .setDuration(200L)
            .start()
    }

    private fun startSkeleton() {
        if (
            !animationsEnabled()
            || nativeProperties.flag("boilerplate", false)
            || nativeProperties.flag("isLoaded", false)
        ) {
            animator?.cancel()
            animator = null
            alpha = 1f
            skeletonShimmerProgress = 0f
            invalidate()
            return
        }
        if (animator?.duration == skeletonPulseDurationMillis && animator?.isRunning == true) {
            return
        }
        animator?.cancel()
        alpha = 1f
        animator = ValueAnimator.ofFloat(0f, 1f).apply {
            duration = skeletonPulseDurationMillis
            repeatMode = ValueAnimator.RESTART
            repeatCount = ValueAnimator.INFINITE
            addUpdateListener {
                skeletonShimmerProgress = it.animatedValue as Float
                invalidate()
            }
            start()
        }
    }

    private fun drawSkeletonShimmer(canvas: Canvas) {
        if (
            animator?.isRunning != true
            || width <= 0
            || height <= 0
        ) {
            return
        }
        val band = max(width * 0.36f, 48f * density)
        val center = -band + (width + band * 2f) * skeletonShimmerProgress
        skeletonShimmerPaint.shader = LinearGradient(
            center - band,
            0f,
            center + band,
            0f,
            intArrayOf(
                Color.TRANSPARENT,
                Color.argb(64, 255, 255, 255),
                Color.TRANSPARENT,
            ),
            floatArrayOf(0f, 0.5f, 1f),
            Shader.TileMode.CLAMP,
        )
        canvas.drawRect(0f, 0f, width.toFloat(), height.toFloat(), skeletonShimmerPaint)
        skeletonShimmerPaint.shader = null
    }

    private fun sliderTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!isEnabled || readOnly || width <= 0 || height <= 0) {
                return@OnTouchListener false
            }
            val hitBounds = sliderTrackBounds()
            val trackBounds = sliderTrackBounds(includeHitSlop = false)
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN,
                MotionEvent.ACTION_MOVE,
                -> {
                    if (
                        event.actionMasked == MotionEvent.ACTION_DOWN
                        && !hitBounds.contains(event.x, event.y)
                    ) {
                        sliderTouchActive = false
                        return@OnTouchListener false
                    }
                    sliderTouchActive = true
                    if (event.actionMasked == MotionEvent.ACTION_DOWN) {
                        sliderTouchInitialValue = value
                        sliderTouchMoved = false
                    } else {
                        sliderTouchMoved = true
                    }
                    var progress = if (orientation == 2) {
                        1.0 - (event.y - trackBounds.top).toDouble() /
                            trackBounds.height().coerceAtLeast(1f).toDouble()
                    } else {
                        (event.x - trackBounds.left).toDouble() /
                            trackBounds.width().coerceAtLeast(1f).toDouble()
                    }
                    progress = progress.coerceIn(0.0, 1.0)
                    if (reversed) progress = 1.0 - progress
                    val requested = snapped(
                        minimum + (maximum - minimum) * progress,
                    )
                    if (rangeEnabled && event.actionMasked == MotionEvent.ACTION_DOWN) {
                        activeRangeThumb = if (
                            abs(requested - lowerValue) <= abs(requested - upperValue)
                        ) {
                            0
                        } else {
                            1
                        }
                    }
                    val changed = if (rangeEnabled) {
                        if (activeRangeThumb == 0) {
                            val next = minOf(requested, upperValue)
                            val changed = next != lowerValue
                            lowerValue = next
                            changed
                        } else {
                            val next = maxOf(requested, lowerValue)
                            val changed = next != upperValue
                            upperValue = next
                            value = upperValue
                            changed
                        }
                    } else {
                        val changed = requested != value
                        value = requested
                        changed
                    }
                    if (changed) {
                        applyRangeVisualState()
                        scheduleSliderChange()
                        invalidate()
                    }
                    true
                }
                MotionEvent.ACTION_UP -> {
                    if (!sliderTouchActive) return@OnTouchListener false
                    sliderTouchActive = false
                    if (
                        nativeProperties.flag("rating", false)
                        && nativeProperties.flag("clearable", false)
                        && !sliderTouchMoved
                        && value == sliderTouchInitialValue
                        && value != minimum
                    ) {
                        value = minimum
                        applyRangeVisualState()
                        scheduleSliderChange()
                        invalidate()
                    }
                    performHapticFeedback(HapticFeedbackConstants.CLOCK_TICK)
                    flushSliderChange()
                    emitSliderChangeEnd()
                    performClick()
                    true
                }
                MotionEvent.ACTION_CANCEL -> {
                    val claimed = sliderTouchActive
                    sliderTouchActive = false
                    claimed
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
                val insideTrigger = anchoredTriggerBounds()
                    ?.contains(event.x, event.y) == true
                if (
                    behavior == Behavior.TOOLTIP
                    && insideTrigger
                    && !closeOnClick
                ) {
                    return@OnTouchListener false
                }
                requestOverlayDismiss()
                performClick()
                true
            } else {
                false
            }
        }

    private fun sheetTouchListener(): OnTouchListener =
        OnTouchListener { _, event ->
            if (!acceptsOverlayInteraction()) return@OnTouchListener false
            val content = sheetContent() ?: return@OnTouchListener false
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    sheetVelocityTracker?.recycle()
                    sheetVelocityTracker = VelocityTracker.obtain().also {
                        it.addMovement(event)
                    }
                    sheetBackdropPressed = !boundsInHost(content).contains(
                        event.x,
                        event.y,
                    )
                    if (sheetBackdropPressed) {
                        dragging = false
                        return@OnTouchListener true
                    }
                    if (!isSheetHandle(event.x, event.y)) {
                        dragging = false
                        return@OnTouchListener false
                    }
                    dragging = true
                    dragOrigin = event.rawY
                    sheetDragStartTranslation = content.translationY
                    content.animate().cancel()
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    if (!dragging) return@OnTouchListener false
                    sheetVelocityTracker?.addMovement(event)
                    val maximum = if (sheetEnablePanDownToClose) {
                        sheetDismissTranslation(content)
                    } else if (sheetSnapPoints.isNotEmpty()) {
                        sheetSnapTranslation(0, content)
                    } else {
                        0f
                    }
                    content.translationY = (
                        sheetDragStartTranslation + event.rawY - dragOrigin
                    ).coerceIn(0f, maximum)
                    updateSheetBackdrop(content.translationY)
                    true
                }
                MotionEvent.ACTION_UP,
                MotionEvent.ACTION_CANCEL,
                -> {
                    if (event.actionMasked == MotionEvent.ACTION_UP) {
                        performClick()
                    }
                    sheetVelocityTracker?.addMovement(event)
                    if (sheetBackdropPressed) {
                        sheetBackdropPressed = false
                        sheetVelocityTracker?.recycle()
                        sheetVelocityTracker = null
                        if (
                            event.actionMasked == MotionEvent.ACTION_UP
                            && dismissible
                            && closeOnOverlayClick
                        ) {
                            when (backdropPressBehavior) {
                                BACKDROP_PRESS_COLLAPSE -> {
                                    if (sheetSnapPoints.isEmpty()) {
                                        dismissSheet()
                                    } else {
                                        settleSheetTo(0, emit = true)
                                    }
                                }
                                BACKDROP_PRESS_NONE -> return@OnTouchListener false
                                else -> dismissSheet()
                            }
                            return@OnTouchListener true
                        }
                        return@OnTouchListener false
                    }
                    if (!dragging) {
                        sheetVelocityTracker?.recycle()
                        sheetVelocityTracker = null
                        return@OnTouchListener false
                    }
                    dragging = false
                    sheetVelocityTracker?.computeCurrentVelocity(1_000)
                    val velocityY = sheetVelocityTracker?.yVelocity ?: 0f
                    sheetVelocityTracker?.recycle()
                    sheetVelocityTracker = null
                    if (event.actionMasked == MotionEvent.ACTION_CANCEL) {
                        settleSheetTo(sheetSnapIndex, emit = false)
                        return@OnTouchListener true
                    }

                    val dismissDistance = sheetDismissTranslation(content)
                    val shouldDismiss = dismissible
                        && sheetEnablePanDownToClose
                        && (
                            content.translationY > dismissDistance * 0.5f
                                || (
                                    velocityY > SHEET_DISMISS_VELOCITY_PX_PER_SECOND
                                        && content.translationY > 8f * density
                                    )
                            )
                    if (shouldDismiss) {
                        dismissSheet()
                        return@OnTouchListener true
                    }

                    if (sheetSnapPoints.isEmpty()) {
                        settleSheetTo(0, emit = false)
                        return@OnTouchListener true
                    }
                    val predicted = (
                        content.translationY + velocityY * SHEET_VELOCITY_PREDICTION_SECONDS
                    ).coerceIn(0f, dismissDistance)
                    val closest = sheetSnapPoints.indices.minByOrNull { index ->
                        abs(sheetSnapTranslation(index, content) - predicted)
                    } ?: sheetSnapIndex
                    settleSheetTo(closest, emit = true)
                    true
                }
                else -> false
            }
        }

    private fun tabsAncestor(): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (current is MobileUiHost && current.behavior == Behavior.TABS) {
                return current
            }
            current = current.parent
        }
        return null
    }

    private fun sheetAncestor(): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (
                current is MobileUiHost
                && current.behavior == Behavior.BOTTOM_SHEET
            ) {
                return current
            }
            current = current.parent
        }
        return null
    }

    private fun menuAncestor(): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (current is MobileUiHost && current.behavior == Behavior.MENU) {
                return current
            }
            current = current.parent
        }
        return null
    }

    private fun overlayAncestor(): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (current is MobileUiHost && current.behavior.isOverlay()) {
                return current
            }
            current = current.parent
        }
        return null
    }

    private fun menuItems(root: ViewGroup = this): List<MobileUiHost> =
        buildList {
            if (root === this@MobileUiHost) {
                (anchoredPortalContent as? ViewGroup)
                    ?.takeIf { it.parent !== root }
                    ?.let { addAll(menuItems(it)) }
            }
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (child is MobileUiHost && child.behavior == Behavior.MENU_ITEM) {
                    add(child)
                } else if (
                    child is MobileUiHost
                    && child.behavior == Behavior.MENU
                ) {
                    Unit
                } else if (child is ViewGroup) {
                    addAll(menuItems(child))
                }
            }
        }

    @Suppress("DEPRECATION")
    private fun menuCollectionItemInfo(
        item: MobileUiHost,
    ): AccessibilityNodeInfo.CollectionItemInfo? {
        val items = menuItems()
        val index = items.indexOf(item)
        if (index < 0) return null
        return AccessibilityNodeInfo.CollectionItemInfo.obtain(
            index,
            1,
            0,
            1,
            false,
            item.selected,
        )
    }

    private fun updateMenuItemAccessibility() {
        if (behavior != Behavior.MENU_ITEM) return
        val label = findFirstText(this)
        if (contentDescription.isNullOrEmpty() && !label.isNullOrEmpty()) {
            contentDescription = label
        }
        isActivated = selected
    }

    private fun activateMenuItem(item: MobileUiHost): Boolean {
        if (
            behavior != Behavior.MENU
            || !open
            || !isEnabled
            || !item.isEnabled
        ) {
            return false
        }
        when (menuSelectionMode) {
            MENU_SELECTION_SINGLE -> {
                menuItems().forEach { candidate ->
                    candidate.selected = candidate === item
                    candidate.isSelected = candidate.selected
                    candidate.isActivated = candidate.selected
                    candidate.invalidate()
                }
            }
            MENU_SELECTION_MULTIPLE -> {
                item.selected = !item.selected
                item.isSelected = item.selected
                item.isActivated = item.selected
                item.invalidate()
            }
            else -> Unit
        }
        item.emitter.emit(NativeViewEventKind.PRESS, byteArrayOf())
        item.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
        item.sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_SELECTED)
        if (item.closeMenuItemOnPress) {
            requestOverlayDismiss()
        }
        return true
    }

    private fun handleMenuKey(
        source: MobileUiHost?,
        event: KeyEvent,
    ): Boolean {
        if (behavior != Behavior.MENU || !open || !isEnabled) return false
        if (event.keyCode == KeyEvent.KEYCODE_ESCAPE) {
            requestOverlayDismiss()
            return true
        }
        val items = menuItems().filter { item -> item.isEnabled }
        if (items.isEmpty()) return false
        val current = items.indexOf(source).takeIf { index -> index >= 0 }
            ?: items.indexOfFirst(View::hasFocus).takeIf { index -> index >= 0 }
            ?: 0
        val targetIndex = when (event.keyCode) {
            KeyEvent.KEYCODE_DPAD_DOWN -> (current + 1).mod(items.size)
            KeyEvent.KEYCODE_DPAD_UP -> (current - 1).mod(items.size)
            KeyEvent.KEYCODE_MOVE_HOME -> 0
            KeyEvent.KEYCODE_MOVE_END -> items.lastIndex
            else -> null
        }
        if (targetIndex != null) {
            val target = items[targetIndex]
            target.requestKeyboardFocus()
            target.sendAccessibilityEvent(
                AccessibilityEvent.TYPE_VIEW_FOCUSED,
            )
            return true
        }

        val character = event.unicodeChar
            .takeIf { code -> code > 0 && !event.isAltPressed && !event.isCtrlPressed }
            ?.toChar()
            ?.lowercaseChar()
            ?: return false
        val now = event.eventTime
        if (now - menuTypeaheadAtMillis > MENU_TYPEAHEAD_TIMEOUT_MILLIS) {
            menuTypeaheadPrefix = ""
        }
        menuTypeaheadAtMillis = now
        menuTypeaheadPrefix += character
        val ordered = (items.drop(current + 1) + items.take(current + 1))
        val match = ordered.firstOrNull { item ->
            findFirstText(item)
                ?.trim()
                ?.lowercase(Locale.getDefault())
                ?.startsWith(menuTypeaheadPrefix) == true
        } ?: return false
        match.requestKeyboardFocus()
        match.sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_FOCUSED)
        return true
    }

    private fun View.requestKeyboardFocus(): Boolean {
        isFocusable = true
        isFocusableInTouchMode = true

        return requestFocus()
    }

    private fun sheetItems(root: ViewGroup = this): List<MobileUiHost> =
        buildList {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (child is MobileUiHost && child.behavior == Behavior.SHEET_ITEM) {
                    add(child)
                } else if (child is ViewGroup) {
                    addAll(sheetItems(child))
                }
            }
        }

    @Suppress("DEPRECATION")
    private fun sheetCollectionItemInfo(
        item: MobileUiHost,
    ): AccessibilityNodeInfo.CollectionItemInfo? {
        val items = sheetItems()
        val index = items.indexOf(item)
        if (index < 0) return null
        return AccessibilityNodeInfo.CollectionItemInfo.obtain(
            index,
            1,
            0,
            1,
            false,
            item.checked || item.selected,
        )
    }

    private fun updateSheetItemAccessibility() {
        if (behavior != Behavior.SHEET_ITEM) return
        val label = findFirstText(this)
        if (contentDescription.isNullOrEmpty() && !label.isNullOrEmpty()) {
            contentDescription = label
        }
        isSelected = checked || selected
        isActivated = checked || selected
    }

    private fun dismissSheetFromItem() {
        if (behavior != Behavior.BOTTOM_SHEET || !dismissible) return
        dismissSheet()
    }

    private fun dismissSheet() {
        if (behavior != Behavior.BOTTOM_SHEET || !dismissible) return
        val content = sheetContent()
        if (content != null) {
            val target = sheetDismissTranslation(content)
            content.animate().cancel()
            if (animationsEnabled()) {
                content.animate()
                    .translationY(target)
                    .setDuration(SHEET_SETTLE_ANIMATION_DURATION_MILLIS)
                    .start()
            } else {
                content.translationY = target
            }
        }
        val backdrop = sheetBackdrop()
        if (backdrop != null) {
            backdrop.animate().cancel()
            if (animationsEnabled()) {
                backdrop.animate()
                    .alpha(0f)
                    .setDuration(SHEET_SETTLE_ANIMATION_DURATION_MILLIS)
                    .start()
            } else {
                backdrop.alpha = 0f
            }
        }
        emitDismiss()
    }

    private fun tabTriggers(root: ViewGroup = this): List<MobileUiHost> =
        buildList {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (child is MobileUiHost && child.behavior == Behavior.TAB_TRIGGER) {
                    add(child)
                } else if (child is ViewGroup) {
                    addAll(tabTriggers(child))
                }
            }
        }

    private fun isEffectivelyEnabled(): Boolean =
        isEnabled && tabsAncestor()?.isEnabled != false

    private fun selectTab(trigger: MobileUiHost, emit: Boolean): Boolean {
        if (
            behavior != Behavior.TABS
            || !isEnabled
            || !trigger.isEffectivelyEnabled()
        ) {
            return false
        }
        val requested = trigger.tabValue ?: return false
        val changed = requested != tabValue
        tabValue = requested
        applyTabsState(animate = changed)
        if (trigger.isAttachedToWindow) {
            trigger.requestKeyboardFocus()
            if (changed) {
                trigger.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
            }
        }
        if (changed) {
            sendAccessibilityEvent(AccessibilityEvent.TYPE_VIEW_SELECTED)
        }
        if (emit) {
            emitter.emit(
                NativeViewEventKind.CHANGE,
                requested.encodeToByteArray(),
            )
        }
        return true
    }

    private fun moveTabFocus(trigger: MobileUiHost, direction: Int): Boolean {
        val triggers = tabTriggers().filter(MobileUiHost::isEffectivelyEnabled)
        if (triggers.isEmpty()) return false
        val current = triggers.indexOf(trigger)
        if (current < 0) return false
        val nextIndex = when (direction) {
            Int.MIN_VALUE -> 0
            Int.MAX_VALUE -> triggers.lastIndex
            else -> (current + direction).mod(triggers.size)
        }
        val next = triggers[nextIndex]
        next.requestKeyboardFocus()
        if (tabsActivationMode == TABS_ACTIVATION_AUTOMATIC) {
            selectTab(next, emit = next.tabValue != tabValue)
        }
        return true
    }

    @Suppress("DEPRECATION")
    private fun tabCollectionItemInfo(
        trigger: MobileUiHost,
    ): AccessibilityNodeInfo.CollectionItemInfo? {
        val triggers = tabTriggers()
        val index = triggers.indexOf(trigger)
        if (index < 0) return null
        val horizontal = orientation == 1
        return AccessibilityNodeInfo.CollectionItemInfo.obtain(
            if (horizontal) 0 else index,
            1,
            if (horizontal) index else 0,
            1,
            false,
            trigger.selected,
        )
    }

    private fun updateTabTriggerAccessibility() {
        if (behavior != Behavior.TAB_TRIGGER) return
        isSelected = selected
        isActivated = selected
        alpha = when {
            !isEffectivelyEnabled() -> 0.4f
            selected -> 1f
            else -> 0.72f
        }
    }

    private fun applyTabsState(animate: Boolean) {
        if (behavior != Behavior.TABS) return
        val triggers = tabTriggers()
        triggers.forEach { trigger ->
            val nextSelected = trigger.tabValue == tabValue
            if (navigationKind == 1) {
                trigger.visibility = if (nextSelected) VISIBLE else GONE
            }
            if (trigger.selected != nextSelected) {
                trigger.selected = nextSelected
                trigger.updateTabTriggerAccessibility()
                trigger.sendAccessibilityEvent(
                    AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED,
                )
            } else {
                trigger.updateTabTriggerAccessibility()
            }
            trigger.applyButtonToggleVisualState()
            trigger.applyTabTextVisualState()
        }

        val contents = tabContents()
        val selectedContent = contents.firstOrNull { content ->
            content.value == tabValue
        }
        contents.forEach { content ->
            content.view.visibility = if (
                content.forceMounted || content.value == tabValue
            ) {
                VISIBLE
            } else {
                GONE
            }
        }
        applyTabsIndicator(
            triggers.firstOrNull { trigger -> trigger.selected },
            animate,
        )
        animateTabsContentHeight(selectedContent?.view, animate)
    }

    private fun scheduleCarouselAdvance() {
        carouselAdvance?.let(::removeCallbacks)
        carouselAdvance = null
        if (
            behavior != Behavior.TABS
            || navigationKind != 1
            || !carouselCycle
        ) {
            return
        }
        carouselAdvance = Runnable {
            val triggers = carouselTriggers()
            if (triggers.size < 2) return@Runnable
            val current = triggers.indexOfFirst { trigger ->
                trigger.tabValue == tabValue
            }.let { index -> if (index >= 0) index else 0 }
            val next = current + 1
            if (next >= triggers.size && !carouselContinuous) {
                carouselAdvance = null
                return@Runnable
            }
            val target = triggers[next % triggers.size]
            tabValue = target.tabValue
            applyTabsState(animate = true)
            tabValue?.let { selected ->
                emitter.emit(
                    NativeViewEventKind.CHANGE,
                    selected.encodeToByteArray(),
                )
            }
            scheduleCarouselAdvance()
        }.also { action ->
            postDelayed(action, carouselIntervalMillis)
        }
    }

    private fun carouselTriggers(): List<MobileUiHost> =
        tabTriggers().filter { trigger ->
            trigger.isEnabled
                && !trigger.nativeProperties.flag("carouselControl", false)
        }

    private fun moveCarousel(direction: Int): Boolean {
        val triggers = carouselTriggers()
        if (triggers.size < 2) return false
        val current = triggers.indexOfFirst { trigger ->
            trigger.tabValue == tabValue
        }.let { index -> if (index >= 0) index else 0 }
        val requested = current + direction
        if (!carouselContinuous && requested !in triggers.indices) return false
        val target = (requested % triggers.size + triggers.size) % triggers.size
        val changed = selectTab(triggers[target], emit = true)
        if (changed) scheduleCarouselAdvance()
        return changed
    }

    private fun tabContents(): List<TabContent> =
        buildList {
            descendantViews(this@MobileUiHost).forEach { view ->
                val value = view.tag as? String ?: return@forEach
                when {
                    value.startsWith(TABS_FORCE_CONTENT_TAG_PREFIX) -> add(
                        TabContent(
                            view,
                            value.removePrefix(TABS_FORCE_CONTENT_TAG_PREFIX),
                            true,
                        ),
                    )
                    value.startsWith(TABS_CONTENT_TAG_PREFIX) -> add(
                        TabContent(
                            view,
                            value.removePrefix(TABS_CONTENT_TAG_PREFIX),
                            false,
                        ),
                    )
                }
            }
        }

    private fun applyButtonToggleVisualState() {
        if (!buttonToggleItem || behavior != Behavior.TAB_TRIGGER) return
        if (buttonToggleBackground == null) {
            buttonToggleBackground = background?.constantState?.newDrawable()?.mutate()
        }
        backgroundTintList = null
        background = if (selected) {
            GradientDrawable().apply {
                shape = GradientDrawable.RECTANGLE
                cornerRadius = nativeProperties.decimal(
                    "selectionCornerRadius",
                    8.0,
                ).toFloat() * density
                setColor(fillPaint.color)
            }
        } else {
            buttonToggleBackground?.constantState?.newDrawable()?.mutate()
        }
        applyTabTextVisualState()
        invalidate()
    }

    private fun applyTabTextVisualState() {
        if (behavior != Behavior.TAB_TRIGGER) return
        descendantViews(this)
            .filterIsInstance<TextView>()
            .forEach { text ->
                text.setTextColor(
                    if (selected) calendarSelectedTextColor else calendarTextPaint.color,
                )
            }
        invalidate()
    }

    private fun descendantViews(root: ViewGroup): Sequence<View> =
        sequence {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                yield(child)
                if (child is ViewGroup) {
                    yieldAll(descendantViews(child))
                }
            }
        }

    private fun applyTabsIndicator(
        trigger: MobileUiHost?,
        animate: Boolean,
    ) {
        val indicator = findTaggedDescendant(this, TABS_INDICATOR_TAG) ?: return
        if (trigger == null || trigger.width <= 0 || trigger.height <= 0) {
            indicator.visibility = GONE
            return
        }
        indicator.visibility = VISIBLE
        indicator.isClickable = false
        indicator.isLongClickable = false
        indicator.isFocusable = false
        indicator.isEnabled = false
        indicator.importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
        tabsIndicatorAnimator?.cancel()
        val params = indicator.layoutParams
        params.width = trigger.width
        params.height = (2f * density).roundToInt().coerceAtLeast(1)
        indicator.layoutParams = params
        val targetX = trigger.x - indicator.left
        val targetY = trigger.y + trigger.height - params.height - indicator.top
        if (animate && animationsEnabled()) {
            indicator.animate()
                .translationX(targetX)
                .translationY(targetY)
                .setDuration(TABS_INDICATOR_ANIMATION_DURATION_MILLIS)
                .start()
        } else {
            indicator.animate().cancel()
            indicator.translationX = targetX
            indicator.translationY = targetY
        }
    }

    private fun animateTabsContentHeight(content: View?, animate: Boolean) {
        if (content == null) return
        val wrapper = findTaggedDescendant(
            this,
            TABS_CONTENT_WRAPPER_TAG,
        ) as? ViewGroup ?: return
        val width = wrapper.width.coerceAtLeast(content.measuredWidth)
        val laidOutHeight = content.height
        content.measure(
            View.MeasureSpec.makeMeasureSpec(width, View.MeasureSpec.EXACTLY),
            View.MeasureSpec.makeMeasureSpec(0, View.MeasureSpec.UNSPECIFIED),
        )
        val targetHeight = maxOf(laidOutHeight, content.measuredHeight)
        if (targetHeight <= 0) return
        val layout = wrapper.layoutParams
        val currentHeight = wrapper.height.takeIf { it > 0 } ?: targetHeight
        tabsContentAnimator?.cancel()
        if (!animate || !animationsEnabled() || currentHeight == targetHeight) {
            if (layout.height != targetHeight) {
                layout.height = targetHeight
                wrapper.layoutParams = layout
            }
            return
        }
        tabsContentAnimator = ValueAnimator.ofInt(currentHeight, targetHeight).apply {
            duration = TABS_CONTENT_ANIMATION_DURATION_MILLIS
            addUpdateListener { animation ->
                layout.height = animation.animatedValue as Int
                wrapper.layoutParams = layout
            }
            start()
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
                        CALENDAR_TARGET_MONTH -> showCalendarSelector(
                            calendarMonthSelector = true,
                        )
                        CALENDAR_TARGET_YEAR -> showCalendarSelector(
                            calendarMonthSelector = false,
                        )
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
        var resolved = false
        lateinit var dialog: DatePickerDialog
        dialog = DatePickerDialog(
            activity,
            { _, year, zeroBasedMonth, day ->
                resolved = true
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
            setOnCancelListener {
                if (!resolved && silentlyDismissedPickerDialog !== dialog) {
                    resolved = true
                    emitDismiss()
                }
            }
            setOnDismissListener {
                if (!resolved && silentlyDismissedPickerDialog !== dialog) {
                    resolved = true
                    emitDismiss()
                }
                if (silentlyDismissedPickerDialog === dialog) {
                    silentlyDismissedPickerDialog = null
                }
                if (activePickerDialog === dialog) {
                    activePickerDialog = null
                }
            }
        }
        activePickerDialog = dialog
        dialog.show()
    }

    private fun showCalendarSelector(calendarMonthSelector: Boolean): Boolean {
        if (!isEnabled || readOnly) return false
        if (activePickerDialog?.isShowing == true) return false
        val activity = context.findActivity() ?: return false
        if (activity.isFinishing || activity.isDestroyed) return false
        val picker = NumberPicker(activity).apply {
            wrapSelectorWheel = calendarMonthSelector
            if (calendarMonthSelector) {
                minValue = 1
                maxValue = MONTHS_PER_YEAR
                displayedValues = (1..MONTHS_PER_YEAR)
                    .map { month ->
                        LocalDate.of(calendarYear, month, 1)
                            .format(DateTimeFormatter.ofPattern("MMMM", calendarLocale))
                    }
                    .toTypedArray()
                value = calendarMonth
            } else {
                minValue = (
                    minimumLocalDate?.year
                        ?: minimumCalendarYear
                        ?: calendarYear - CALENDAR_YEAR_RANGE
                )
                maxValue = (
                    maximumLocalDate?.year
                        ?: maximumCalendarYear
                        ?: calendarYear + CALENDAR_YEAR_RANGE
                ).coerceAtLeast(minValue)
                value = calendarYear.coerceIn(minValue, maxValue)
            }
        }
        lateinit var dialog: AlertDialog
        dialog = AlertDialog.Builder(activity)
            .setTitle(
                if (calendarMonthSelector) {
                    context.getString(R.string.pam_calendar_select_month)
                } else {
                    context.getString(R.string.pam_calendar_select_year)
                },
            )
            .setView(picker)
            .setNegativeButton(android.R.string.cancel, null)
            .setPositiveButton(android.R.string.ok) { _, _ ->
                setCalendarMonthOrYear(
                    calendarMonthSelector,
                    picker.value,
                )
            }
            .create()
            .apply {
                setOnDismissListener {
                    if (activePickerDialog === dialog) {
                        activePickerDialog = null
                    }
                }
            }
        activePickerDialog = dialog
        dialog.show()

        return true
    }

    private fun setCalendarMonthOrYear(
        calendarMonthSelector: Boolean,
        selectedValue: Int,
    ) {
        val requested = if (calendarMonthSelector) {
            LocalDate.of(
                calendarYear,
                selectedValue.coerceIn(1, MONTHS_PER_YEAR),
                1,
            )
        } else {
            LocalDate.of(selectedValue, calendarMonth, 1)
        }
        val firstAllowed = minimumLocalDate?.withDayOfMonth(1)
        val lastAllowed = maximumLocalDate?.withDayOfMonth(1)
        val clamped = requested
            .let { value -> firstAllowed?.let { maxOf(value, it) } ?: value }
            .let { value -> lastAllowed?.let { minOf(value, it) } ?: value }
        calendarYear = clamped.year.coerceIn(
            minimumCalendarYear ?: Int.MIN_VALUE,
            maximumCalendarYear ?: Int.MAX_VALUE,
        )
        calendarMonth = clamped.monthValue
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
    }

    private fun showTimePicker(
        activity: Activity,
        date: LocalDate,
        time: LocalTime,
    ) {
        var resolved = false
        lateinit var dialog: TimePickerDialog
        dialog = TimePickerDialog(
            activity,
            { _, hour, minute ->
                resolved = true
                emitDateTime(LocalDateTime.of(date, LocalTime.of(hour, minute)))
            },
            time.hour,
            time.minute,
            is24Hour,
        ).apply {
            setOnCancelListener {
                if (!resolved && silentlyDismissedPickerDialog !== dialog) {
                    resolved = true
                    emitDismiss()
                }
            }
            setOnDismissListener {
                if (!resolved && silentlyDismissedPickerDialog !== dialog) {
                    resolved = true
                    emitDismiss()
                }
                if (silentlyDismissedPickerDialog === dialog) {
                    silentlyDismissedPickerDialog = null
                }
                if (activePickerDialog === dialog) {
                    activePickerDialog = null
                }
            }
        }
        activePickerDialog = dialog
        dialog.show()
    }

    internal fun cancelActivePicker(): Boolean {
        val dialog = activePickerDialog ?: return false
        dialog.cancel()
        return true
    }

    private fun dismissActivePickerSilently() {
        val dialog = activePickerDialog ?: return
        silentlyDismissedPickerDialog = dialog
        activePickerDialog = null
        dialog.dismiss()
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
        val columns = DAYS_PER_WEEK + if (showWeekNumbers) 1 else 0
        val cellWidth = bounds.width() / columns
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
        if (expandedTaggedBounds("pam:calendar-month-select").contains(x, y)) {
            return CALENDAR_TARGET_MONTH
        }
        if (expandedTaggedBounds("pam:calendar-year-select").contains(x, y)) {
            return CALENDAR_TARGET_YEAR
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
        val month = calendarFirstDate()
            .format(DateTimeFormatter.ofPattern("MMMM yyyy", calendarLocale))
            .replaceFirstChar { character ->
                if (character.isLowerCase()) {
                    character.titlecase(calendarLocale)
                } else {
                    character.toString()
                }
            }
        (findTaggedDescendant(this, "pam:calendar-title") as? TextView)?.let { title ->
            title.text = month
            title.contentDescription = month
        }
        val monthSelect = findTaggedDescendant(
            this,
            "pam:calendar-month-select",
        )
        findFirstTextView(monthSelect)?.let { label ->
            label.text = calendarFirstDate().format(
                DateTimeFormatter.ofPattern("MMMM", calendarLocale),
            )
        }
        monthSelect?.let { view ->
            view.contentDescription = context.getString(
                R.string.pam_calendar_selected_month,
                calendarMonth,
            )
        }
        val yearSelect = findTaggedDescendant(
            this,
            "pam:calendar-year-select",
        )
        findFirstTextView(yearSelect)?.text = String.format(
            calendarLocale,
            "%d",
            calendarYear,
        )
        yearSelect?.let { view ->
            view.contentDescription = context.getString(
                R.string.pam_calendar_selected_year,
                calendarYear,
            )
        }
    }

    private fun LocalDate?.orEmptyDate(): String = this?.toString().orEmpty()

    private fun fileTreeItems(root: ViewGroup = this): List<MobileUiHost> =
        buildList {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (
                    child is MobileUiHost
                    && child.behavior in setOf(
                        Behavior.FILE_TREE_FOLDER,
                        Behavior.FILE_TREE_FILE,
                    )
                ) {
                    add(child)
                    addAll(fileTreeItems(child))
                } else if (
                    child is ViewGroup
                    && !(child is MobileUiHost && child.behavior == Behavior.FILE_TREE)
                ) {
                    addAll(fileTreeItems(child))
                }
            }
        }

    private fun applyFileTreeState(announce: Boolean) {
        if (behavior != Behavior.FILE_TREE) return
        fileTreeItems().forEach { item ->
            val selected = item.fileTreePath != ""
                && item.fileTreePath == fileTreeSelectedPath
            if (item.behavior == Behavior.FILE_TREE_FOLDER) {
                item.applyFileTreeFolderState(
                    expanded = fileTreeExpandedPaths.contains(item.fileTreePath),
                    selected = selected,
                    animate = item.isAttachedToWindow,
                )
            } else {
                item.isSelected = selected
                item.updateFileTreeItemAccessibility()
            }
        }
        if (announce) {
            sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)
        }
    }

    private fun applyFileTreeFolderState(
        expanded: Boolean,
        selected: Boolean,
        animate: Boolean,
    ) {
        if (behavior != Behavior.FILE_TREE_FOLDER) return
        val changed = !fileTreeFolderInitialized
            || fileTreeFolderExpanded != expanded
        fileTreeFolderExpanded = expanded
        fileTreeFolderInitialized = true
        isSelected = selected
        val content = findTaggedDescendant(this, FILE_TREE_CONTENT_TAG)
        if (content != null && changed) {
            content.animate().cancel()
            content.importantForAccessibility = if (expanded) {
                IMPORTANT_FOR_ACCESSIBILITY_AUTO
            } else {
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            }
            if (expanded) {
                content.visibility = VISIBLE
                if (animate && animationsEnabled()) {
                    content.alpha = 0f
                    content.animate()
                        .alpha(1f)
                        .setDuration(FILE_TREE_ANIMATION_DURATION_MILLIS)
                        .start()
                } else {
                    content.alpha = 1f
                }
            } else if (animate && animationsEnabled() && content.visibility == VISIBLE) {
                content.animate()
                    .alpha(0f)
                    .setDuration(FILE_TREE_ANIMATION_DURATION_MILLIS)
                    .withEndAction {
                        if (!fileTreeFolderExpanded) {
                            content.visibility = GONE
                            content.alpha = 1f
                        }
                    }
                    .start()
            } else {
                content.visibility = GONE
                content.alpha = 1f
            }
        }
        findTaggedDescendant(this, FILE_TREE_CHEVRON_TAG)?.let { chevron ->
            chevron.animate().cancel()
            val rotation = if (expanded) 90f else 0f
            if (changed && animate && animationsEnabled()) {
                chevron.animate()
                    .rotation(rotation)
                    .setDuration(FILE_TREE_ANIMATION_DURATION_MILLIS)
                    .start()
            } else {
                chevron.rotation = rotation
            }
        }
        updateFileTreeItemAccessibility()
    }

    private fun toggleFileTreeFolder(folder: MobileUiHost): Boolean {
        if (behavior != Behavior.FILE_TREE || folder.fileTreePath == "") return false
        val path = folder.fileTreePath
        val expanded = if (fileTreeExpandedPaths.remove(path)) {
            false
        } else {
            fileTreeExpandedPaths.add(path)
            true
        }
        fileTreeSelectedPath = path
        applyFileTreeState(announce = true)
        emitter.emit(
            NativeViewEventKind.CHANGE,
            path.encodeToByteArray(),
        )
        emitter.emit(
            NativeViewEventKind.NATIVE,
            WireMap.encode(
                mapOf(
                    "action" to WireValue.Integer(FILE_TREE_ACTION_EXPANDED),
                    "path" to WireValue.Text(path),
                    "expanded" to WireValue.Flag(expanded),
                ),
            ),
        )
        return true
    }

    private fun selectFileTreeItem(item: MobileUiHost): Boolean {
        if (behavior != Behavior.FILE_TREE || item.fileTreePath == "") return false
        if (fileTreeSelectedPath == item.fileTreePath) return false
        fileTreeSelectedPath = item.fileTreePath
        applyFileTreeState(announce = true)
        emitter.emit(
            NativeViewEventKind.CHANGE,
            item.fileTreePath.encodeToByteArray(),
        )
        return true
    }

    @Suppress("DEPRECATION")
    private fun fileTreeCollectionItemInfo(
        item: MobileUiHost,
    ): AccessibilityNodeInfo.CollectionItemInfo? {
        val visible = fileTreeItems().filter(::isFileTreeItemVisible)
        val index = visible.indexOf(item)
        if (index < 0) return null
        return AccessibilityNodeInfo.CollectionItemInfo.obtain(
            index,
            1,
            0,
            1,
            item.behavior == Behavior.FILE_TREE_FOLDER,
            item.isSelected,
        )
    }

    private fun isFileTreeItemVisible(item: MobileUiHost): Boolean {
        var current: View? = item
        while (current != null && current !== this) {
            if (current.visibility != VISIBLE) return false
            current = current.parent as? View
        }
        return current === this
    }

    private fun fileTreeAncestor(): MobileUiHost? =
        ancestorWithBehavior(Behavior.FILE_TREE)

    private fun updateFileTreeItemAccessibility() {
        if (
            behavior != Behavior.FILE_TREE_FOLDER
            && behavior != Behavior.FILE_TREE_FILE
        ) {
            return
        }
        if (contentDescription.isNullOrEmpty()) {
            contentDescription = (
                findFirstText(
                    findTaggedDescendant(this, FILE_TREE_NAME_TAG),
                ) ?: fileTreePath.substringAfterLast('/')
            ).ifEmpty {
                if (behavior == Behavior.FILE_TREE_FOLDER) "Folder" else "File"
            }
        }
        if (
            behavior == Behavior.FILE_TREE_FOLDER
            && Build.VERSION.SDK_INT >= Build.VERSION_CODES.R
        ) {
            stateDescription = context.getString(
                if (fileTreeFolderExpanded) {
                    R.string.pam_file_tree_expanded
                } else {
                    R.string.pam_file_tree_collapsed
                },
            )
        }
    }

    private fun ancestorWithBehavior(expected: Behavior): MobileUiHost? {
        var current = parent
        while (current is View) {
            if (current is MobileUiHost && current.behavior == expected) {
                return current
            }
            current = current.parent
        }
        return null
    }

    private fun scheduleToast(properties: Map<String, WireValue>) {
        if (behavior != Behavior.TOAST) {
            pendingDismiss?.let(::removeCallbacks)
            pendingDismiss = null
            toastScheduleSignature = null
            return
        }
        val persistent = properties.flag("persistent", false)
        val duration = properties.integer("duration", 4_000L).coerceIn(500L, 60_000L)
        val identity = properties.scalarText("toastId")
            ?: properties.scalarText("id")
            ?: ""
        val signature = "$identity\u0000$duration\u0000$persistent\u0000$open"
        if (signature == toastScheduleSignature) return
        toastScheduleSignature = signature
        pendingDismiss?.let(::removeCallbacks)
        pendingDismiss = null
        if (!open) {
            animate().cancel()
            visibility = GONE
            alpha = 1f
            translationY = 0f
            importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
            return
        }
        val entersFromTop = properties.scalarText("location")
            ?.lowercase()
            ?.contains("top") == true
        visibility = VISIBLE
        importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_YES
        animate().cancel()
        if (animationsEnabled()) {
            alpha = 0f
            translationY = (if (entersFromTop) -8f else 8f) * density
            animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(TOAST_ENTER_ANIMATION_DURATION_MILLIS)
                .start()
        } else {
            alpha = 1f
            translationY = 0f
        }
        if (persistent) return
        pendingDismiss = Runnable {
            pendingDismiss = null
            if (!openControlled) {
                open = false
                if (animationsEnabled()) {
                    animate()
                        .alpha(0f)
                        .translationY(-8f * density)
                        .setDuration(TOAST_EXIT_ANIMATION_DURATION_MILLIS)
                        .withEndAction {
                            visibility = GONE
                            alpha = 1f
                            translationY = 0f
                            importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
                        }
                        .start()
                } else {
                    visibility = GONE
                    importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
                }
            }
            emitDismiss()
        }.also { postDelayed(it, duration) }
    }

    @Suppress("DEPRECATION")
    private fun applyToastSemantics() {
        if (behavior != Behavior.TOAST) return
        val announcement = descendantTexts(this)
            .joinToString(". ")
            .trim()
        if (announcement.isEmpty()) return
        contentDescription = announcement
        val signature = "$toastAction\u0000$announcement"
        if (signature == toastAnnouncementSignature) return
        toastAnnouncementSignature = signature
        if (isAttachedToWindow && visibility == VISIBLE) {
            sendAccessibilityEvent(AccessibilityEvent.TYPE_ANNOUNCEMENT)
        }
    }

    private fun animationsEnabled(): Boolean =
        ValueAnimator.areAnimatorsEnabled()
            && !nativeProperties.flag("reduceMotion", false)
            && nativeProperties.integer("animationDuration", 1L) > 0L

    private fun drawSelectionIndicator(canvas: Canvas, radio: Boolean) {
        val bounds = selectionIndicatorBounds()
        if (bounds.width() <= 0f || bounds.height() <= 0f) return
        val radius = if (radio) {
            minOf(bounds.width(), bounds.height()) / 2f
        } else {
            4f * density
        }
        val previousTrackStyle = trackPaint.style
        val previousTrackWidth = trackPaint.strokeWidth
        val previousFillStyle = fillPaint.style

        if (!radio && (checked || indeterminate)) {
            fillPaint.style = Paint.Style.FILL
            canvas.drawRoundRect(bounds, radius, radius, fillPaint)
        }
        trackPaint.style = Paint.Style.STROKE
        trackPaint.strokeWidth = 2f * density
        canvas.drawRoundRect(bounds, radius, radius, trackPaint)

        trackPaint.style = previousTrackStyle
        trackPaint.strokeWidth = previousTrackWidth
        fillPaint.style = previousFillStyle
    }

    private fun drawSelectionGlyph(canvas: Canvas, radio: Boolean) {
        val bounds = selectionIndicatorBounds()
        if (bounds.width() <= 0f || bounds.height() <= 0f) return
        val centerX = bounds.centerX()
        val centerY = bounds.centerY()
        val unit = minOf(bounds.width(), bounds.height())

        if (radio) {
            if (checked || indeterminate) {
                canvas.drawCircle(centerX, centerY, unit * 0.28f, fillPaint)
            }
            return
        }

        if (indeterminate) {
            selectionGlyphPaint.strokeWidth = maxOf(2f * density, unit * 0.12f)
            canvas.drawLine(
                centerX - unit * 0.25f,
                centerY,
                centerX + unit * 0.25f,
                centerY,
                selectionGlyphPaint,
            )
        } else if (checked) {
            selectionGlyphPaint.strokeWidth = maxOf(2f * density, unit * 0.11f)
            canvas.drawLine(
                centerX - unit * 0.27f,
                centerY,
                centerX - unit * 0.07f,
                centerY + unit * 0.2f,
                selectionGlyphPaint,
            )
            canvas.drawLine(
                centerX - unit * 0.07f,
                centerY + unit * 0.2f,
                centerX + unit * 0.3f,
                centerY - unit * 0.24f,
                selectionGlyphPaint,
            )
        }
    }

    private fun selectionIndicatorBounds(): RectF {
        val indicator = findTaggedDescendant(this, SELECTION_INDICATOR_TAG)
            ?: getChildAtOrNull(0)
        if (indicator != null && indicator.width > 0 && indicator.height > 0) {
            return boundsInHost(indicator)
        }
        val size = 20f * density
        val left = (width - size) / 2f
        val top = (height - size) / 2f
        return RectF(left, top, left + size, top + size)
    }

    private fun drawCalendar(canvas: Canvas) {
        val grid = findTaggedDescendant(this, "pam:calendar-grid") ?: return
        if (grid is ViewGroup && grid.childCount > 0) return
        val bounds = boundsInHost(grid)
        if (bounds.width() <= 0f || bounds.height() <= 0f) return

        val rows = calendarRowCount()
        val columns = DAYS_PER_WEEK + if (showWeekNumbers) 1 else 0
        val cellWidth = bounds.width() / columns
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

        if (showWeekNumbers) {
            val weekFields = java.time.temporal.WeekFields.of(calendarLocale)
            repeat(rows) { row ->
                val date = firstVisibleDate.plusDays((row * DAYS_PER_WEEK).toLong())
                val week = date.get(weekFields.weekOfWeekBasedYear())
                val column = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                    columns - 1
                } else {
                    0
                }
                val centerX = bounds.left + (column + 0.5f) * cellWidth
                val centerY = bounds.top + (row + 0.5f) * cellHeight
                val baseline = centerY - (
                    calendarTextPaint.ascent() + calendarTextPaint.descent()
                ) / 2f
                calendarTextPaint.alpha = OUTSIDE_MONTH_ALPHA
                canvas.drawText(week.toString(), centerX, baseline, calendarTextPaint)
            }
        }

        repeat(rows * DAYS_PER_WEEK) { index ->
            val date = firstVisibleDate.plusDays(index.toLong())
            val outside = date.monthValue != calendarMonth
            val disabled = calendarDateDisabled(date)
            val selectedDate = calendarDateSelected(date)
            val insideRange = rangeFrom?.let { start ->
                rangeTo?.let { end -> date > start && date < end }
            } == true
            val dayColumn = index % DAYS_PER_WEEK
            val visualColumn = if (layoutDirection == LAYOUT_DIRECTION_RTL) {
                DAYS_PER_WEEK - 1 - dayColumn
            } else {
                dayColumn
            } + if (showWeekNumbers && layoutDirection != LAYOUT_DIRECTION_RTL) {
                1
            } else {
                0
            }
            val centerX = bounds.left + (visualColumn + 0.5f) * cellWidth
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

    private fun findFirstTextView(root: View?): TextView? {
        if (root is TextView && root !is EditText) return root
        if (root !is ViewGroup) return null
        repeat(root.childCount) { index ->
            findFirstTextView(root.getChildAt(index))?.let { return it }
        }
        return null
    }

    private fun descendantTexts(root: ViewGroup): List<String> =
        buildList {
            repeat(root.childCount) { index ->
                val child = root.getChildAt(index)
                if (child is TextView && child.text.isNotEmpty()) {
                    add(child.text.toString())
                } else if (child is ViewGroup) {
                    addAll(descendantTexts(child))
                }
            }
        }

    private fun findFirstEditText(root: View?): EditText? {
        if (root is EditText) return root
        if (root !is ViewGroup) return null
        repeat(root.childCount) { index ->
            findFirstEditText(root.getChildAt(index))?.let { return it }
        }
        return null
    }

    private fun getChildAtOrNull(index: Int): View? =
        if (index in 0 until childCount) getChildAt(index) else null

    private fun overlayContent(): View? =
        findTaggedDescendant(this, OVERLAY_CONTENT_TAG)
            ?: if (childCount > 0) getChildAt(childCount - 1) else null

    private fun anchoredTrigger(): View? =
        findTaggedDescendant(this, OVERLAY_TRIGGER_TAG)
            ?: getChildAtOrNull(0)?.takeUnless { candidate ->
                candidate === overlayContent()
            }

    private fun anchoredTriggerBounds(): RectF? =
        anchoredTrigger()?.let(::boundsInHost)

    private fun sheetContent(): View? = overlayContent()

    private fun sheetBackdrop(): View? =
        findTaggedDescendant(this, OVERLAY_BACKDROP_TAG)
            ?: findTaggedDescendantWithPrefix(
                this,
                "$OVERLAY_BACKDROP_TAG:",
            )

    private fun applySheetLayout(animate: Boolean) {
        if (behavior != Behavior.BOTTOM_SHEET || dragging) return
        val content = sheetContent() ?: return
        val backdrop = sheetBackdrop()
        val contentLayout = (content.layoutParams as? FrameLayout.LayoutParams)
            ?: FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                content.layoutParams?.height ?: ViewGroup.LayoutParams.WRAP_CONTENT,
            )
        contentLayout.width = ViewGroup.LayoutParams.MATCH_PARENT
        contentLayout.gravity = Gravity.BOTTOM
        content.layoutParams = contentLayout
        if (content is ViewGroup) {
            ensureSheetSearchInput(content)
            repeat(content.childCount) { index ->
                val child = content.getChildAt(index)
                val childLayout = child.layoutParams ?: return@repeat
                if (childLayout.width != ViewGroup.LayoutParams.MATCH_PARENT) {
                    childLayout.width = ViewGroup.LayoutParams.MATCH_PARENT
                    child.layoutParams = childLayout
                }
            }
        }
        if (backdrop != null) {
            backdrop.layoutParams = FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT,
            )
            (backdrop.tag as? String)
                ?.substringAfter("$OVERLAY_BACKDROP_TAG:", "")
                ?.toIntOrNull()
                ?.let { behavior ->
                    backdropPressBehavior = behavior.coerceIn(
                        BACKDROP_PRESS_CLOSE,
                        BACKDROP_PRESS_NONE,
                    )
                }
            if (sheetBackdropBaseAlpha == null) {
                sheetBackdropBaseAlpha = sheetScrimOpacity
            }
            backdrop.alpha = sheetBackdropBaseAlpha ?: sheetScrimOpacity
            backdrop.importantForAccessibility =
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
        }
        findTaggedDescendant(this, SHEET_DRAG_INDICATOR_TAG)
            ?.importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO

        if (
            sheetSnapPoints.isNotEmpty()
            && height > 0
            && !sheetEnableDynamicSizing
        ) {
            val viewportHeight = minOf(
                height,
                resources.displayMetrics.heightPixels,
            )
            val maximumHeight = (
                viewportHeight * (sheetSnapPoints.maxOrNull() ?: 100f) / 100f
            ).roundToInt().coerceAtLeast(1)
            val layout = content.layoutParams
            if (layout.height != maximumHeight) {
                layout.height = maximumHeight
                content.layoutParams = layout
            }
        }

        if (sheetSnapPoints.isNotEmpty()) {
            sheetSnapIndex = sheetSnapIndex.coerceIn(0, sheetSnapPoints.lastIndex)
        } else {
            sheetSnapIndex = 0
        }
        val target = sheetSnapTranslation(sheetSnapIndex, content)
        if (animate && animationsEnabled()) {
            content.animate()
                .translationY(target)
                .setDuration(SHEET_SETTLE_ANIMATION_DURATION_MILLIS)
                .start()
        } else {
            content.animate().cancel()
            content.translationY = target
        }
        updateSheetBackdrop(target)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = if (sheetSnapPoints.isEmpty()) {
                "Expanded"
            } else {
                "Snap ${sheetSnapIndex + 1} of ${sheetSnapPoints.size}"
            }
        }
    
        (content as? ViewGroup)?.let(::layoutNativeSheetItems)
    }

    /**
     * Bottom-sheet contents are laid out outside the protocol/Yoga pass. Keep native
     * selection rows in a deterministic mobile list instead of stretching every row
     * over the complete sheet viewport.
     */
    private var sheetSearchable = false
    private var sheetAllowCustomValue = false
    private var sheetSearchPlaceholder = "Search options"
    private var sheetSearchInput: android.widget.EditText? = null
    private var sheetCustomAction: android.widget.TextView? = null
    private var sheetEmptyState: android.widget.TextView? = null
    private val sheetSearchBindings =
        java.util.WeakHashMap<android.widget.EditText, android.text.TextWatcher>()

    private fun ensureSheetSearchInput(content: ViewGroup) {
        if (!sheetSearchable) {
            sheetSearchInput?.let { input ->
                (input.parent as? ViewGroup)?.removeView(input)
            }
            sheetSearchInput = null
            sheetCustomAction?.let { (it.parent as? ViewGroup)?.removeView(it) }
            sheetEmptyState?.let { (it.parent as? ViewGroup)?.removeView(it) }
            sheetCustomAction = null
            sheetEmptyState = null
            return
        }
        val input = sheetSearchInput ?: android.widget.EditText(context).also { search ->
            search.isSingleLine = true
            search.textSize = 16f
            search.setPadding(
                (16f * resources.displayMetrics.density).roundToInt(),
                0,
                (48f * resources.displayMetrics.density).roundToInt(),
                0,
            )
            search.background = android.graphics.drawable.GradientDrawable().apply {
                shape = android.graphics.drawable.GradientDrawable.RECTANGLE
                cornerRadius = 12f * resources.displayMetrics.density
                setColor(
                    nativeProperties.integer(
                        "searchBackgroundColor",
                        android.graphics.Color.WHITE.toLong(),
                    ).toInt(),
                )
                setStroke(
                    (1f * resources.displayMetrics.density).roundToInt(),
                    nativeProperties.integer(
                        "searchBorderColor",
                        android.graphics.Color.LTGRAY.toLong(),
                    ).toInt(),
                )
            }
            search.setTextColor(
                nativeProperties.integer(
                    "searchTextColor",
                    android.graphics.Color.BLACK.toLong(),
                ).toInt(),
            )
            search.setHintTextColor(
                nativeProperties.integer(
                    "searchHintColor",
                    android.graphics.Color.GRAY.toLong(),
                ).toInt(),
            )
            search.contentDescription = sheetSearchPlaceholder
            search.minimumHeight = (48f * resources.displayMetrics.density).roundToInt()
            search.gravity = android.view.Gravity.CENTER_VERTICAL
            search.setCompoundDrawablesRelativeWithIntrinsicBounds(
                android.R.drawable.ic_menu_search,
                0,
                0,
                0,
            )
            search.compoundDrawablePadding =
                (12f * resources.displayMetrics.density).roundToInt()
            sheetSearchInput = search
        }
        input.hint = sheetSearchPlaceholder
        if (input.parent !== content) {
            (input.parent as? ViewGroup)?.removeView(input)
            content.addView(input)
        }
        val customAction = sheetCustomAction ?: android.widget.TextView(context).also { action ->
            action.gravity = android.view.Gravity.CENTER_VERTICAL
            action.textSize = 16f
            action.setPadding(
                (16f * resources.displayMetrics.density).roundToInt(),
                0,
                (16f * resources.displayMetrics.density).roundToInt(),
                0,
            )
            action.setTextColor(
                nativeProperties.integer(
                    "searchTextColor",
                    android.graphics.Color.BLACK.toLong(),
                ).toInt(),
            )
            action.isClickable = true
            action.isFocusable = true
            action.setOnClickListener {
                val value = input.text?.toString()?.trim().orEmpty()
                if (value.isEmpty()) return@setOnClickListener
                emitter.emit(NativeViewEventKind.CHANGE, value.encodeToByteArray())
                clearSheetSearch()
                emitter.emit(NativeViewEventKind.NATIVE, byteArrayOf())
            }
            sheetCustomAction = action
        }
        if (customAction.parent !== content) {
            (customAction.parent as? ViewGroup)?.removeView(customAction)
            content.addView(customAction)
        }
        val emptyState = sheetEmptyState ?: android.widget.TextView(context).also { empty ->
            empty.gravity = android.view.Gravity.CENTER_VERTICAL
            empty.text = nativeProperties.text("noDataText") ?: "No options available"
            empty.textSize = 14f
            empty.setPadding(
                (16f * resources.displayMetrics.density).roundToInt(),
                0,
                (16f * resources.displayMetrics.density).roundToInt(),
                0,
            )
            empty.setTextColor(
                nativeProperties.integer(
                    "searchHintColor",
                    android.graphics.Color.GRAY.toLong(),
                ).toInt(),
            )
            empty.accessibilityLiveRegion = View.ACCESSIBILITY_LIVE_REGION_POLITE
            sheetEmptyState = empty
        }
        if (emptyState.parent !== content) {
            (emptyState.parent as? ViewGroup)?.removeView(emptyState)
            content.addView(emptyState)
        }
    }

    private fun clearSheetSearch() {
        sheetSearchInput?.text?.clear()
    }

    private fun layoutNativeSheetItems(root: ViewGroup) {
        val items = ArrayList<MobileUiHost>()
        val inputs = ArrayList<android.widget.EditText>()

        fun collect(group: ViewGroup) {
            for (index in 0 until group.childCount) {
                val child = group.getChildAt(index)
                if (child is MobileUiHost && child.behavior == Behavior.SHEET_ITEM) {
                    items += child
                } else if (child is android.widget.EditText) {
                    inputs += child
                } else if (child is ViewGroup) {
                    collect(child)
                }
            }
        }

        collect(root)
        if (items.isEmpty()) return
        val query = inputs.firstOrNull()?.text?.toString()?.trim().orEmpty()
        items.forEach { item ->
            item.visibility = if (
                query.isEmpty() ||
                item.contentDescription?.toString()?.contains(query, ignoreCase = true) == true
            ) {
                View.VISIBLE
            } else {
                View.GONE
            }
        }
        val visibleItems = items.filter { it.visibility == View.VISIBLE }
        val hasExactMatch = visibleItems.any { item ->
            item.contentDescription?.toString()?.equals(query, ignoreCase = true) == true
        }
        val showCustomAction = sheetAllowCustomValue && query.isNotEmpty() && !hasExactMatch
        sheetCustomAction?.apply {
            visibility = if (showCustomAction) View.VISIBLE else View.GONE
            text = context.getString(R.string.pam_use_custom_value, query)
            contentDescription = text
        }
        val showEmptyState = query.isNotEmpty() && visibleItems.isEmpty() && !showCustomAction
        sheetEmptyState?.visibility = if (showEmptyState) View.VISIBLE else View.GONE

        val density = resources.displayMetrics.density
        val rowHeight = (56f * density).roundToInt()
        val searchHeight = (48f * density).roundToInt()
        val topInset = (12f * density).roundToInt()
        val horizontalInset = (16f * density).roundToInt()
        val availableWidth = (root.width - horizontalInset * 2).coerceAtLeast(0)
        val inputOffset = if (inputs.isEmpty()) 0 else searchHeight + topInset
        val supplementaryOffset = if (showCustomAction || showEmptyState) rowHeight else 0

        inputs.firstOrNull()?.let { input ->
            if (!sheetSearchBindings.containsKey(input)) {
                val watcher = object : android.text.TextWatcher {
                    override fun beforeTextChanged(
                        text: CharSequence?,
                        start: Int,
                        count: Int,
                        after: Int,
                    ) = Unit

                    override fun onTextChanged(
                        text: CharSequence?,
                        start: Int,
                        before: Int,
                        count: Int,
                    ) {
                        root.requestLayout()
                    }

                    override fun afterTextChanged(text: android.text.Editable?) = Unit
                }
                input.addTextChangedListener(watcher)
                sheetSearchBindings[input] = watcher
            }
            input.measure(
                MeasureSpec.makeMeasureSpec(availableWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(searchHeight, MeasureSpec.EXACTLY),
            )
            input.layout(
                horizontalInset,
                topInset,
                horizontalInset + availableWidth,
                topInset + searchHeight,
            )
            if (!input.hasFocus()) {
                input.post {
                    if (!input.isAttachedToWindow) return@post
                    input.requestFocus()
                    val keyboard = input.context.getSystemService(
                        android.content.Context.INPUT_METHOD_SERVICE,
                    ) as? android.view.inputmethod.InputMethodManager
                    keyboard?.showSoftInput(
                        input,
                        android.view.inputmethod.InputMethodManager.SHOW_IMPLICIT,
                    )
                }
            }
        }
        sheetCustomAction?.takeIf { it.visibility == View.VISIBLE }?.let { action ->
            val top = topInset + inputOffset
            action.measure(
                MeasureSpec.makeMeasureSpec(availableWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(rowHeight, MeasureSpec.EXACTLY),
            )
            action.layout(
                horizontalInset,
                top,
                horizontalInset + availableWidth,
                top + rowHeight,
            )
        }
        sheetEmptyState?.takeIf { it.visibility == View.VISIBLE }?.let { empty ->
            val top = topInset + inputOffset
            empty.measure(
                MeasureSpec.makeMeasureSpec(availableWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(rowHeight, MeasureSpec.EXACTLY),
            )
            empty.layout(
                horizontalInset,
                top,
                horizontalInset + availableWidth,
                top + rowHeight,
            )
        }

        visibleItems.forEachIndexed { index, item ->
            val rootTop = topInset + inputOffset + supplementaryOffset + index * rowHeight
            val parent = item.parent as? ViewGroup ?: return@forEachIndexed
            var parentLeftInRoot = 0
            var parentTopInRoot = 0
            var ancestor: View? = parent
            while (ancestor != null && ancestor !== root) {
                parentLeftInRoot += ancestor.left - ancestor.scrollX
                parentTopInRoot += ancestor.top - ancestor.scrollY
                ancestor = ancestor.parent as? View
            }
            if (ancestor !== root && parent !== root) return@forEachIndexed
            val left = horizontalInset - parentLeftInRoot
            val top = rootTop - parentTopInRoot

            item.layoutParams = item.layoutParams.apply {
                this.width = ViewGroup.LayoutParams.MATCH_PARENT
                height = rowHeight
            }
            item.measure(
                MeasureSpec.makeMeasureSpec(availableWidth, MeasureSpec.EXACTLY),
                MeasureSpec.makeMeasureSpec(rowHeight, MeasureSpec.EXACTLY),
            )
            item.layout(left, top, left + availableWidth, top + rowHeight)
        }
    }


    private fun sheetSnapTranslation(index: Int, content: View): Float {
        if (sheetSnapPoints.isEmpty() || height <= 0) return 0f
        val safeIndex = index.coerceIn(0, sheetSnapPoints.lastIndex)
        val viewportHeight = minOf(height, resources.displayMetrics.heightPixels)
        val maximumHeight =
            viewportHeight * (sheetSnapPoints.maxOrNull() ?: 100f) / 100f
        val selectedHeight = viewportHeight * sheetSnapPoints[safeIndex] / 100f
        return (maximumHeight - selectedHeight)
            .coerceIn(0f, sheetDismissTranslation(content))
    }

    private fun sheetDismissTranslation(content: View): Float {
        val bounds = boundsInHost(content)
        return maxOf(
            content.height.toFloat(),
            height - bounds.top,
            1f,
        ) + 32f * density
    }

    private fun updateSheetBackdrop(contentTranslation: Float) {
        val backdrop = sheetBackdrop() ?: return
        val baseAlpha = sheetBackdropBaseAlpha ?: backdrop.alpha.also {
            sheetBackdropBaseAlpha = it
        }
        val content = sheetContent() ?: return
        val dismissDistance = sheetDismissTranslation(content)
        val progress = (
            contentTranslation / dismissDistance
        ).coerceIn(0f, 1f)
        backdrop.alpha = baseAlpha * (1f - progress)
    }

    private fun settleSheetTo(index: Int, emit: Boolean) {
        val content = sheetContent() ?: return
        val previous = sheetSnapIndex
        sheetSnapIndex = if (sheetSnapPoints.isEmpty()) {
            0
        } else {
            index.coerceIn(0, sheetSnapPoints.lastIndex)
        }
        val target = sheetSnapTranslation(sheetSnapIndex, content)
        content.animate().cancel()
        val backdrop = sheetBackdrop()
        backdrop?.animate()?.cancel()
        if (animationsEnabled()) {
            content.animate()
                .translationY(target)
                .setDuration(SHEET_SETTLE_ANIMATION_DURATION_MILLIS)
                .start()
            backdrop?.animate()
                ?.alpha(sheetBackdropBaseAlpha ?: 1f)
                ?.setDuration(SHEET_SETTLE_ANIMATION_DURATION_MILLIS)
                ?.start()
        } else {
            content.translationY = target
            backdrop?.alpha = sheetBackdropBaseAlpha ?: 1f
        }
        if (emit && previous != sheetSnapIndex) {
            emitter.emit(
                NativeViewEventKind.CHANGE,
                sheetSnapIndex.toString().encodeToByteArray(),
            )
            sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            stateDescription = if (sheetSnapPoints.isEmpty()) {
                "Expanded"
            } else {
                "Snap ${sheetSnapIndex + 1} of ${sheetSnapPoints.size}"
            }
        }
    }

    internal fun isSheetHandle(x: Float, y: Float): Boolean {
        val explicitHandle = findTaggedDescendant(
            this,
            SHEET_DRAG_INDICATOR_WRAPPER_TAG,
        ) ?: findTaggedDescendant(this, SHEET_DRAG_INDICATOR_TAG)
        if (explicitHandle != null) {
            val bounds = boundsInHost(explicitHandle)
            val minimumTarget = 48f * density
            val verticalInset = max(0f, (minimumTarget - bounds.height()) / 2f)
            return bounds.apply {
                inset(0f, -verticalInset)
            }.contains(x, y)
        }
        val content = sheetContent()
        if (content == null) return y <= 64f * density
        val bounds = boundsInHost(content)
        return x in bounds.left..bounds.right
            && y in bounds.top..minOf(bounds.bottom, bounds.top + 64f * density)
    }

    internal fun isCalendarGridPoint(x: Float, y: Float): Boolean =
        calendarGridBounds().contains(x, y)

    internal fun acceptsOverlayInteraction(): Boolean = isEnabled && open

    private fun findTaggedDescendant(root: View, tag: String): View? {
        if (root.tag == tag) return root
        if (root !is ViewGroup) return null
        repeat(root.childCount) { index ->
            findTaggedDescendant(root.getChildAt(index), tag)?.let { return it }
        }
        return null
    }

    private fun findTaggedDescendantWithPrefix(
        root: View,
        prefix: String,
    ): View? {
        if ((root.tag as? String)?.startsWith(prefix) == true) return root
        if (root !is ViewGroup) return null
        repeat(root.childCount) { index ->
            findTaggedDescendantWithPrefix(
                root.getChildAt(index),
                prefix,
            )?.let { return it }
        }
        return null
    }

    private fun calendarGridBounds(): RectF =
        boundsInHost(findTaggedDescendant(this, "pam:calendar-grid") ?: this)

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

    private fun applyAnchoredOverlayState(animate: Boolean) {
        if (!behavior.isAnchoredOverlay()) return
        val content = anchoredOverlayContent() ?: return
        val backdrop = findTaggedDescendant(this, OVERLAY_BACKDROP_TAG)
            ?: findTaggedDescendantWithPrefix(this, "$OVERLAY_BACKDROP_TAG:")
        val trigger = anchoredTrigger()
        trigger?.isActivated = open
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            trigger?.stateDescription = if (open) "Expanded" else "Collapsed"
        }
        if (behavior == Behavior.TOOLTIP) {
            trigger?.tooltipText = findFirstText(content)
        }
        isClickable = open || (!openControlled && trigger != null)
        isFocusable = open
        content.animate().cancel()
        backdrop?.animate()?.cancel()
        if (open) {
            content.visibility = VISIBLE
            content.importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_AUTO
            backdrop?.visibility = VISIBLE
            backdrop?.importantForAccessibility =
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            positionAnchoredContent()
            installAnchoredTouchDelegate()
            if (animate && animationsEnabled()) {
                content.alpha = 0f
                content.scaleX = ANCHORED_OVERLAY_ENTRANCE_SCALE
                content.scaleY = ANCHORED_OVERLAY_ENTRANCE_SCALE
                content.animate()
                    .alpha(1f)
                    .scaleX(1f)
                    .scaleY(1f)
                    .setDuration(ANCHORED_OVERLAY_ENTRANCE_DURATION_MILLIS)
                    .start()
                backdrop?.alpha = 0f
                backdrop?.animate()
                    ?.alpha(1f)
                    ?.setDuration(ANCHORED_OVERLAY_ENTRANCE_DURATION_MILLIS)
                    ?.start()
            } else {
                content.alpha = 1f
                content.scaleX = 1f
                content.scaleY = 1f
                backdrop?.alpha = 1f
            }
        } else {
            removeAnchoredTouchDelegate()
            content.importantForAccessibility =
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            backdrop?.importantForAccessibility =
                IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS
            if (
                animate
                && animationsEnabled()
                && content.visibility == VISIBLE
            ) {
                content.animate()
                    .alpha(0f)
                    .scaleX(ANCHORED_OVERLAY_ENTRANCE_SCALE)
                    .scaleY(ANCHORED_OVERLAY_ENTRANCE_SCALE)
                    .setDuration(ANCHORED_OVERLAY_EXIT_DURATION_MILLIS)
                    .withEndAction {
                        if (!open) {
                            content.visibility = GONE
                        }
                    }
                    .start()
                backdrop?.animate()
                    ?.alpha(0f)
                    ?.setDuration(ANCHORED_OVERLAY_EXIT_DURATION_MILLIS)
                    ?.withEndAction {
                        if (!open) {
                            backdrop.visibility = GONE
                        }
                    }
                    ?.start()
            } else {
                content.alpha = 0f
                content.scaleX = ANCHORED_OVERLAY_ENTRANCE_SCALE
                content.scaleY = ANCHORED_OVERLAY_ENTRANCE_SCALE
                content.visibility = GONE
                backdrop?.alpha = 0f
                backdrop?.visibility = GONE
            }
        }
    }

    private fun setAnchoredOverlayOpen(requested: Boolean, emit: Boolean) {
        if (!behavior.isAnchoredOverlay() || openControlled || open == requested) {
            return
        }
        open = requested
        requestLayout()
        if (requested) {
            applyAnchoredOverlayState(animate = true)
            captureAndMoveFocus()
        } else {
            applyAnchoredOverlayState(animate = true)
            restoreFocus()
        }
        if (emit) {
            emitter.emit(
                NativeViewEventKind.NATIVE,
                WireMap.encode(
                    mapOf(
                        "action" to WireValue.Integer(
                            if (requested) {
                                HostAction.OPEN.value
                            } else {
                                HostAction.DISMISS.value
                            },
                        ),
                        "open" to WireValue.Flag(requested),
                    ),
                ),
            )
        }
    }

    private fun requestAnchoredOverlayOpen() {
        if (!behavior.isAnchoredOverlay() || !isEnabled || open) return
        if (openControlled) {
            emitAnchoredOpenRequest()
        } else {
            setAnchoredOverlayOpen(true, emit = true)
        }
    }

    private fun emitAnchoredOpenRequest() {
        emitter.emit(
            NativeViewEventKind.NATIVE,
            WireMap.encode(
                mapOf(
                    "action" to WireValue.Integer(HostAction.OPEN.value),
                    "open" to WireValue.Flag(true),
                ),
            ),
        )
    }

    private fun scheduleTooltipState(requested: Boolean) {
        if (behavior != Behavior.TOOLTIP || !isEnabled) return
        pendingAnchoredOpen?.let(::removeCallbacks)
        pendingAnchoredOpen = null
        pendingAnchoredClose?.let(::removeCallbacks)
        pendingAnchoredClose = null
        val action = Runnable {
            if (requested) {
                requestAnchoredOverlayOpen()
            } else if (open) {
                requestOverlayDismiss()
            }
        }
        val delay = if (requested) openDelayMillis else closeDelayMillis
        if (requested) {
            pendingAnchoredOpen = action
        } else {
            pendingAnchoredClose = action
        }
        if (delay == 0L) {
            action.run()
        } else {
            postDelayed(action, delay)
        }
    }

    private fun requestOverlayDismiss() {
        if (!behavior.isOverlay() || !dismissible || !open) return
        if (behavior.isAnchoredOverlay() && !openControlled) {
            setAnchoredOverlayOpen(false, emit = false)
        }
        emitDismiss()
    }

    private fun positionAnchoredContent() {
        if (!behavior.isAnchoredOverlay() || !open) return
        val trigger = anchoredTrigger() ?: return
        val content = anchoredOverlayContent() ?: return
        if (
            trigger === content
            || trigger.width <= 0
            || trigger.height <= 0
            || content.width <= 0
            || content.height <= 0
        ) {
            return
        }

        val visibleFrame = Rect()
        rootView.getWindowVisibleDisplayFrame(visibleFrame)
        val safeMargin = ANCHORED_OVERLAY_SCREEN_MARGIN_DP * density
        val available = RectF(
            visibleFrame.left + safeMargin,
            visibleFrame.top + safeMargin,
            visibleFrame.right - safeMargin,
            visibleFrame.bottom - safeMargin,
        )
        val triggerLocation = IntArray(2)
        val hostLocation = IntArray(2)
        trigger.getLocationOnScreen(triggerLocation)
        getLocationOnScreen(hostLocation)
        val triggerBounds = RectF(
            triggerLocation[0].toFloat(),
            triggerLocation[1].toFloat(),
            triggerLocation[0] + trigger.width.toFloat(),
            triggerLocation[1] + trigger.height.toFloat(),
        )
        val requested = anchoredPlacementCandidate(
            placement,
            triggerBounds,
            content.width.toFloat(),
            content.height.toFloat(),
        )
        val oppositePlacement = oppositePlacement(placement)
        val opposite = anchoredPlacementCandidate(
            oppositePlacement,
            triggerBounds,
            content.width.toFloat(),
            content.height.toFloat(),
        )
        val requestedOverflow = overflowScore(
            requested,
            content.width.toFloat(),
            content.height.toFloat(),
            available,
        )
        val oppositeOverflow = overflowScore(
            opposite,
            content.width.toFloat(),
            content.height.toFloat(),
            available,
        )
        val selected = if (
            shouldFlip
            && oppositePlacement != placement
            && oppositeOverflow < requestedOverflow
        ) {
            resolvedPlacement = oppositePlacement
            opposite
        } else {
            resolvedPlacement = placement
            requested
        }
        val targetX = selected.first.coerceIn(
            available.left,
            max(available.left, available.right - content.width),
        )
        val targetY = selected.second.coerceIn(
            available.top,
            max(available.top, available.bottom - content.height),
        )
        content.translationX = targetX - hostLocation[0] - content.left
        content.translationY = targetY - hostLocation[1] - content.top
        positionAnchoredArrow(
            content,
            triggerBounds,
            targetX,
            targetY,
        )
        installAnchoredTouchDelegate()
    }

    private fun installAnchoredTouchDelegate() {
        if (!behavior.isAnchoredOverlay() || !open || !isAttachedToWindow) return
        if (anchoredTouchCatcher != null) return
        val root = context.findActivity()
            ?.findViewById<ViewGroup>(android.R.id.content)
            ?: return
        val content = anchoredOverlayContent() ?: return
        val contentLocation = IntArray(2)
        val rootLocation = IntArray(2)
        content.getLocationOnScreen(contentLocation)
        root.getLocationOnScreen(rootLocation)
        anchoredTouchCatcher = FrameLayout(context).apply {
            isClickable = true
            importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
            setBackgroundColor(Color.TRANSPARENT)
            setOnTouchListener { catcher, event ->
                val handled = forwardAnchoredOverlayTouch(catcher, event)
                if (event.actionMasked == MotionEvent.ACTION_UP) {
                    catcher.performClick()
                }
                handled
            }
        }.also { catcher ->
            root.addView(
                catcher,
                ViewGroup.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT,
                    ViewGroup.LayoutParams.MATCH_PARENT,
                ),
            )
            val parent = content.parent as? ViewGroup ?: return@also
            anchoredPortalContent = content
            anchoredPortalParent = parent
            anchoredPortalIndex = parent.indexOfChild(content)
            anchoredPortalLayoutParams = content.layoutParams
            parent.removeView(content)
            content.translationX = 0f
            content.translationY = 0f
            catcher.addView(
                content,
                FrameLayout.LayoutParams(
                    content.measuredWidth,
                    content.measuredHeight,
                ).apply {
                    leftMargin = contentLocation[0] - rootLocation[0]
                    topMargin = contentLocation[1] - rootLocation[1]
                },
            )
        }
    }

    private fun removeAnchoredTouchDelegate() {
        restoreAnchoredPortalContent()
        anchoredTouchCatcher?.let { catcher ->
            (catcher.parent as? ViewGroup)?.removeView(catcher)
        }
        anchoredTouchCatcher = null
        anchoredTouchInsideContent = false
    }

    private fun anchoredOverlayContent(): View? =
        anchoredPortalContent ?: findTaggedDescendant(this, OVERLAY_CONTENT_TAG)

    private fun restoreAnchoredPortalContent() {
        val content = anchoredPortalContent ?: return
        val parent = anchoredPortalParent
        (content.parent as? ViewGroup)?.removeView(content)
        content.translationX = 0f
        content.translationY = 0f
        if (parent != null) {
            parent.addView(
                content,
                anchoredPortalIndex.coerceIn(0, parent.childCount),
                anchoredPortalLayoutParams,
            )
        }
        anchoredPortalContent = null
        anchoredPortalParent = null
        anchoredPortalIndex = -1
        anchoredPortalLayoutParams = null
    }

    private fun forwardAnchoredOverlayTouch(
        catcher: View,
        event: MotionEvent,
    ): Boolean {
        val content = anchoredOverlayContent()
            ?: return true
        val catcherLocation = IntArray(2)
        val contentLocation = IntArray(2)
        catcher.getLocationOnScreen(catcherLocation)
        content.getLocationOnScreen(contentLocation)
        val contentBounds = RectF(
            contentLocation[0].toFloat(),
            contentLocation[1].toFloat(),
            contentLocation[0] + content.width.toFloat(),
            contentLocation[1] + content.height.toFloat(),
        )
        val screenX = catcherLocation[0] + event.x
        val screenY = catcherLocation[1] + event.y
        if (event.actionMasked == MotionEvent.ACTION_DOWN) {
            anchoredTouchInsideContent = contentBounds.contains(screenX, screenY)
        }
        if (anchoredTouchInsideContent) {
            val delegated = MotionEvent.obtain(event)
            delegated.setLocation(
                screenX - contentLocation[0],
                screenY - contentLocation[1],
            )
            content.dispatchTouchEvent(delegated)
            delegated.recycle()
        } else if (event.actionMasked == MotionEvent.ACTION_UP) {
            requestOverlayDismiss()
        }
        if (
            event.actionMasked == MotionEvent.ACTION_CANCEL
            || event.actionMasked == MotionEvent.ACTION_UP
        ) {
            anchoredTouchInsideContent = false
        }
        return true
    }

    private fun anchoredPlacementCandidate(
        requestedPlacement: Int,
        trigger: RectF,
        contentWidth: Float,
        contentHeight: Float,
    ): Pair<Float, Float> {
        val vertical = requestedPlacement in 1..6
        val overlap = if (shouldOverlapWithTrigger) {
            if (vertical) {
                minOf(trigger.height(), contentHeight)
            } else {
                minOf(trigger.width(), contentWidth)
            }
        } else {
            0f
        }
        val gap = (offset * density).toFloat() - overlap
        val cross = (crossOffset * density).toFloat()
        val centeredX = trigger.centerX() - contentWidth / 2f + cross
        val centeredY = trigger.centerY() - contentHeight / 2f + cross
        return when (requestedPlacement) {
            1 -> centeredX to (trigger.top - contentHeight - gap)
            2 -> (trigger.left + cross) to (trigger.top - contentHeight - gap)
            3 -> (trigger.right - contentWidth + cross) to
                (trigger.top - contentHeight - gap)
            4 -> centeredX to (trigger.bottom + gap)
            5 -> (trigger.left + cross) to (trigger.bottom + gap)
            6 -> (trigger.right - contentWidth + cross) to (trigger.bottom + gap)
            7 -> (trigger.left - contentWidth - gap) to centeredY
            8 -> (trigger.left - contentWidth - gap) to (trigger.top + cross)
            9 -> (trigger.left - contentWidth - gap) to
                (trigger.bottom - contentHeight + cross)
            10 -> (trigger.right + gap) to centeredY
            11 -> (trigger.right + gap) to (trigger.top + cross)
            12 -> (trigger.right + gap) to
                (trigger.bottom - contentHeight + cross)
            else -> trigger.centerX() - contentWidth / 2f to
                trigger.centerY() - contentHeight / 2f
        }
    }

    private fun oppositePlacement(requestedPlacement: Int): Int =
        when (requestedPlacement) {
            1 -> 4
            2 -> 5
            3 -> 6
            4 -> 1
            5 -> 2
            6 -> 3
            7 -> 10
            8 -> 11
            9 -> 12
            10 -> 7
            11 -> 8
            12 -> 9
            else -> requestedPlacement
        }

    private fun overflowScore(
        origin: Pair<Float, Float>,
        contentWidth: Float,
        contentHeight: Float,
        available: RectF,
    ): Float =
        max(0f, available.left - origin.first) +
            max(0f, origin.first + contentWidth - available.right) +
            max(0f, available.top - origin.second) +
            max(0f, origin.second + contentHeight - available.bottom)

    private fun positionAnchoredArrow(
        content: View,
        trigger: RectF,
        contentScreenX: Float,
        contentScreenY: Float,
    ) {
        val arrow = findTaggedDescendant(content, OVERLAY_ARROW_TAG) ?: return
        arrow.importantForAccessibility = IMPORTANT_FOR_ACCESSIBILITY_NO
        val edgeInset = 8f * density
        if (resolvedPlacement in 1..6) {
            val desiredCenter = clampedArrowCenter(
                desired = trigger.centerX() - contentScreenX,
                contentExtent = content.width.toFloat(),
                arrowExtent = arrow.width.toFloat(),
                edgeInset = edgeInset,
            )
            arrow.translationX = desiredCenter - arrow.left - arrow.width / 2f
            arrow.translationY = if (resolvedPlacement in 1..3) {
                content.height - arrow.top - arrow.height / 2f
            } else {
                -arrow.top - arrow.height / 2f
            }
            arrow.rotation = if (resolvedPlacement in 1..3) 180f else 0f
        } else {
            val desiredCenter = clampedArrowCenter(
                desired = trigger.centerY() - contentScreenY,
                contentExtent = content.height.toFloat(),
                arrowExtent = arrow.height.toFloat(),
                edgeInset = edgeInset,
            )
            arrow.translationY = desiredCenter - arrow.top - arrow.height / 2f
            arrow.translationX = if (resolvedPlacement in 7..9) {
                content.width - arrow.left - arrow.width / 2f
            } else {
                -arrow.left - arrow.width / 2f
            }
            arrow.rotation = if (resolvedPlacement in 7..9) 90f else -90f
        }
    }

    private fun captureAndMoveFocus() {
        if (!open || !isShown) return
        val focused = rootView.findFocus()
        val anchoredContent = if (behavior.isAnchoredOverlay()) {
            findTaggedDescendant(this, OVERLAY_CONTENT_TAG)
        } else {
            null
        }
        if (
            focused != null
            && focused !== this
            && (
                anchoredContent?.let { scope ->
                    !containsView(scope, focused)
                } ?: !contains(focused)
                )
        ) {
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
        val scope = if (behavior.isAnchoredOverlay()) {
            findTaggedDescendant(this, OVERLAY_CONTENT_TAG) ?: this
        } else {
            this
        }
        scope.addFocusables(focusables, FOCUS_FORWARD, FOCUSABLES_ALL)
        return focusables.filter {
            it !== this && it.visibility == VISIBLE && it.isEnabled
        }
    }

    private fun containsView(scope: View, candidate: View): Boolean {
        var current: View? = candidate
        while (current != null) {
            if (current === scope) return true
            current = current.parent as? View
        }
        return false
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

    private fun scheduleSliderChange() {
        pendingSliderValue = value
        if (pendingSliderChange != null) return
        pendingSliderChange = Runnable {
            val pending = pendingSliderValue
            pendingSliderValue = null
            pendingSliderChange = null
            if (pending != null) {
                emitSliderValue(NativeViewEventKind.CHANGE, pending)
                updateRangeAccessibility()
            }
        }.also(::postOnAnimation)
    }

    private fun flushSliderChange() {
        pendingSliderChange?.let(::removeCallbacks)
        pendingSliderChange = null
        val pending = pendingSliderValue
        pendingSliderValue = null
        if (pending != null) {
            emitSliderValue(NativeViewEventKind.CHANGE, pending)
        }
    }

    private fun emitSliderChangeAndEnd() {
        emitSliderValue(NativeViewEventKind.CHANGE, value)
        emitSliderChangeEnd()
    }

    private fun emitSliderChangeEnd() {
        emitSliderValue(NativeViewEventKind.NATIVE, value)
        updateRangeAccessibility()
        sendAccessibilityEvent(AccessibilityEvent.TYPE_WINDOW_CONTENT_CHANGED)
    }

    private fun emitSliderValue(kind: NativeViewEventKind, current: Double) {
        val payload = if (rangeEnabled) {
            "[${formatRangeValue(lowerValue)},${formatRangeValue(upperValue)}]"
        } else {
            formatRangeValue(current)
        }
        emitter.emit(
            kind,
            payload.encodeToByteArray(),
        )
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
            Behavior.MODAL,
            Behavior.POPOVER,
            Behavior.MENU,
            Behavior.TOOLTIP,
            Behavior.PORTAL,
        )

    private fun Behavior.isAnchoredOverlay(): Boolean =
        this == Behavior.POPOVER || this == Behavior.MENU || this == Behavior.TOOLTIP

    private fun Behavior.isOpenByDefault(): Boolean = !isAnchoredOverlay()

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

    private fun Map<String, WireValue>.scalarText(key: String): String? =
        when (val value = this[key]) {
            is WireValue.Text -> value.value
            is WireValue.Integer -> value.value.toString()
            is WireValue.Decimal -> formatRangeValue(value.value)
            is WireValue.Flag -> if (value.value) "1" else "0"
            else -> null
        }

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
        val CHILD_RECONCILIATION_BEHAVIORS = setOf(
            Behavior.SLIDER,
            Behavior.PROGRESS,
            Behavior.CALENDAR,
            Behavior.TABS,
            Behavior.INPUT_GROUP,
            Behavior.FORM_CONTROL,
            Behavior.TABLE,
            Behavior.TOAST,
            Behavior.FILE_TREE,
        )
        const val DAYS_PER_WEEK = 7
        const val MIN_CALENDAR_ROWS = 4
        const val MAX_CALENDAR_ROWS = 6
        const val CALENDAR_TARGET_NONE = -1
        const val CALENDAR_TARGET_PREVIOUS = -2
        const val CALENDAR_TARGET_NEXT = -3
        const val CALENDAR_TARGET_MONTH = -4
        const val CALENDAR_TARGET_YEAR = -5
        const val MONTHS_PER_YEAR = 12
        const val CALENDAR_YEAR_RANGE = 100
        const val DISABLED_ALPHA = 76
        const val OUTSIDE_MONTH_ALPHA = 112
        const val RANGE_BACKGROUND_ALPHA = 42
        const val RIPPLE_ALPHA_MASK = 0x22000000
        const val OPAQUE_ALPHA = 255
        const val ACCORDION_TRIGGER_TAG = "pam:accordion-trigger"
        const val ACCORDION_CONTENT_TAG = "pam:accordion-content"
        const val ACCORDION_ICON_TAG = "pam:accordion-icon"
        const val ACCORDION_EXPANDED_ROTATION = 180f
        const val ACCORDION_COLLAPSED_SCALE = 0.98f
        const val ACCORDION_ANIMATION_DURATION_MILLIS = 200L
        const val SELECTION_INDICATOR_TAG = "pam:selection-indicator"
        const val SELECTION_ICON_TAG = "pam:selection-icon"
        const val SELECTION_FORCE_ICON_TAG = "pam:selection-icon-force"
        const val SWITCH_TRACK_TAG = "pam:switch-track"
        const val SLIDER_TRACK_TAG = "pam:slider-track"
        const val SLIDER_FILLED_TRACK_TAG = "pam:slider-filled-track"
        const val SLIDER_THUMB_TAG = "pam:slider-thumb"
        const val PROGRESS_FILLED_TRACK_TAG = "pam:progress-filled-track"
        const val TABS_CONTENT_TAG_PREFIX = "pam:tabs-content:"
        const val TABS_FORCE_CONTENT_TAG_PREFIX = "pam:tabs-content-force:"
        const val TABS_CONTENT_WRAPPER_TAG = "pam:tabs-content-wrapper"
        const val TABS_INDICATOR_TAG = "pam:tabs-indicator"
        const val TABS_ACTIVATION_AUTOMATIC = 1
        const val TABS_ACTIVATION_MANUAL = 2
        const val TABS_INDICATOR_ANIMATION_DURATION_MILLIS = 180L
        const val TABS_CONTENT_ANIMATION_DURATION_MILLIS = 100L
        const val OVERLAY_TRIGGER_TAG = "pam:overlay-trigger"
        const val OVERLAY_BACKDROP_TAG = "pam:overlay-backdrop"
        const val OVERLAY_CONTENT_TAG = "pam:overlay-content"
        const val OVERLAY_ARROW_TAG = "pam:overlay-arrow"
        const val ANCHORED_OVERLAY_SCREEN_MARGIN_DP = 8f
        const val ANCHORED_OVERLAY_ENTRANCE_SCALE = 0.96f
        const val ANCHORED_OVERLAY_ENTRANCE_DURATION_MILLIS = 180L
        const val ANCHORED_OVERLAY_EXIT_DURATION_MILLIS = 120L
        const val MAX_ANCHORED_OVERLAY_DELAY_MILLIS = 60_000L
        const val MENU_SELECTION_SINGLE = 1
        const val MENU_SELECTION_MULTIPLE = 2
        const val MENU_SELECTION_NONE = 3
        const val MENU_TYPEAHEAD_TIMEOUT_MILLIS = 700L
        const val FORM_LABEL_TAG = "pam:form-label"
        const val FORM_HELPER_TAG = "pam:form-helper"
        const val FORM_ERROR_TAG = "pam:form-error"
        const val INPUT_SLOT_ACTION_FOCUS = 1
        const val INPUT_SLOT_ACTION_CLEAR = 2
        const val INPUT_SLOT_ACTION_TOGGLE_PASSWORD = 3
        const val INPUT_SLOT_ACTION_NONE = 4
        const val SHEET_DRAG_INDICATOR_TAG = "pam:sheet-drag-indicator"
        const val SHEET_DRAG_INDICATOR_WRAPPER_TAG =
            "pam:sheet-drag-indicator-wrapper"
        const val BACKDROP_PRESS_CLOSE = 1
        const val BACKDROP_PRESS_COLLAPSE = 2
        const val BACKDROP_PRESS_NONE = 3
        const val SHEET_SETTLE_ANIMATION_DURATION_MILLIS = 220L
        const val SHEET_ENTRANCE_ANIMATION_DURATION_MILLIS = 200L
        const val SHEET_DISMISS_VELOCITY_PX_PER_SECOND = 1_250f
        const val SHEET_VELOCITY_PREDICTION_SECONDS = 0.08f
        const val SWITCH_TRACK_WIDTH_DP = 52f
        const val SWITCH_TRACK_HEIGHT_DP = 32f
        const val SWITCH_THUMB_INSET_DP = 2f
        const val SWITCH_ANIMATION_DURATION_MILLIS = 160L
        const val TOAST_ACTION_MUTED = 1
        const val TOAST_ACTION_WARNING = 3
        const val TOAST_ACTION_ERROR = 4
        const val TOAST_ACTION_ATTENTION = 6
        const val TOAST_ENTER_ANIMATION_DURATION_MILLIS = 180L
        const val TOAST_EXIT_ANIMATION_DURATION_MILLIS = 140L
        const val DISABLED_CONTROL_ALPHA = 0.5f
        const val FILE_TREE_ACTION_EXPANDED = 1L
        const val FILE_TREE_CONTENT_TAG = "pam:file-tree-content"
        const val FILE_TREE_HEADER_TAG = "pam:file-tree-header"
        const val FILE_TREE_CHEVRON_TAG = "pam:file-tree-chevron"
        const val FILE_TREE_NAME_TAG = "pam:file-tree-name"
        const val FILE_TREE_ANIMATION_DURATION_MILLIS = 180L
        const val MIN_TIME_ZONE_OFFSET_MINUTES = -18 * 60
        const val MAX_TIME_ZONE_OFFSET_MINUTES = 18 * 60
        const val SECONDS_PER_MINUTE = 60
    }
}

internal fun clampedArrowCenter(
    desired: Float,
    contentExtent: Float,
    arrowExtent: Float,
    edgeInset: Float,
): Float {
    if (
        !desired.isFinite() ||
        !contentExtent.isFinite() ||
        !arrowExtent.isFinite() ||
        !edgeInset.isFinite()
    ) {
        return 0f
    }

    val safeContentExtent = contentExtent.coerceAtLeast(0f)
    val safeArrowExtent = arrowExtent.coerceAtLeast(0f)
    val safeEdgeInset = edgeInset.coerceAtLeast(0f)
    val minimum = safeEdgeInset + safeArrowExtent / 2f
    val maximum = safeContentExtent - safeEdgeInset - safeArrowExtent / 2f

    return if (minimum <= maximum) {
        desired.coerceIn(minimum, maximum)
    } else {
        safeContentExtent / 2f
    }
}
