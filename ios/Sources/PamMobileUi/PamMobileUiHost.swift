import Foundation
import PamNative
import UIKit

private extension WireValue {
    var pamText: String? {
        guard case let .text(value) = self else { return nil }
        return value
    }

    var pamInteger: Int? {
        guard case let .integer(value) = self else { return nil }
        return Int(value)
    }

    var pamDecimal: CGFloat? {
        switch self {
        case let .decimal(value): CGFloat(value)
        case let .integer(value): CGFloat(value)
        default: nil
        }
    }

    var pamFlag: Bool? {
        guard case let .flag(value) = self else { return nil }
        return value
    }
}

private enum PamMobileBehavior: Int {
    case container = 1
    case accordion = 2
    case bottomSheet = 3
    case overlay = 4
    case slider = 5
    case tabs = 6
    case calendar = 7
    case skeleton = 8
    case checkbox = 9
    case radio = 10
    case toast = 11
    case progress = 12
    case modal = 13
    case popover = 14
    case menu = 15
    case tooltip = 16
    case dateTimePicker = 17
    case portal = 18
    case accordionGroup = 19
    case checkboxGroup = 20
    case radioGroup = 21
    case switchControl = 22
    case tabTrigger = 23
    case sheetItem = 24
    case menuItem = 25
    case overlayDismiss = 26
    case inputGroup = 27
    case inputSlot = 28
    case formControl = 29
    case table = 30
    case tableRow = 31
    case fileTree = 32
    case fileTreeFolder = 33
    case fileTreeFile = 34
    case sparkline = 35
    case chipGroup = 36
    case listItem = 37
    case timeline = 38
    case timelineItem = 39

    var isOverlay: Bool {
        switch self {
        case .bottomSheet, .overlay, .modal,
             .popover, .menu, .tooltip, .portal:
            true
        default:
            false
        }
    }
}

private enum PamHostAction: Int64 {
    case dismiss = 1
    case open = 2
    case navigate = 3
}

private enum CalendarHeaderAction {
    case previous
    case next
    case month
    case year
}

final class PamMobileUiHost: UIView, UIGestureRecognizerDelegate {
    typealias EventEmitter = (NativeViewEventKind, Data) -> Void

    private var emit: EventEmitter?
    private var behavior = PamMobileBehavior.container
    private var component = 0
    private var properties: [String: WireValue] = [:]
    private var isOpen = true
    private var isControlled = false
    private var openDefaultInitialized = false
    private var isChecked = false
    private var isSelectedState = false
    private var buttonToggleItem = false
    private var isExpanded = false
    private var minimum: CGFloat = 0
    private var maximum: CGFloat = 100
    private var step: CGFloat = 1
    private var value: CGFloat = 0
    private var rangeEnabled = false
    private var lowerValue: CGFloat = 0
    private var upperValue: CGFloat = 100
    private var activeRangeThumb = 1
    private var orientation = 1
    private var reversed = false
    private var showSliderTicks = false
    private var showThumbLabel = false
    private var sliderThumbWidth: CGFloat = 20
    private var sliderThumbHeight: CGFloat = 20
    private var sliderTrackThickness: CGFloat = 4
    private var snapPoints: [CGFloat] = []
    private var snapIndex = 0
    private var dragOrigin: CGFloat = 0
    private var activeSheetHeight: CGFloat = 0
    private var sheetSearchable = false
    private var sheetAllowCustomValue = false
    private var sheetSearchPlaceholder = "Search options"
    private var calendarMode = 1
    private var calendarYear = Calendar.current.component(.year, from: Date())
    private var calendarMonth = Calendar.current.component(.month, from: Date())
    private var calendarSelectedDates: Set<String> = []
    private var calendarRangeFrom: String?
    private var calendarRangeTo: String?
    private weak var activeDateTimePicker: PamDateTimePickerController?
    private weak var sheetSearchField: UITextField?
    private weak var sheetCustomAction: UIButton?
    private weak var sheetEmptyState: UILabel?
    private var stateLayerColor = UIColor.label
    private var fillColor = UIColor.tintColor
    private var trackColor = UIColor.secondarySystemFill
    private var switchTrackOutlineColor = UIColor.separator
    private var switchThumbColor = UIColor.secondaryLabel
    private var switchActiveThumbColor = UIColor.white
    private var selectedForegroundColor = UIColor.white
    private var selectedContainerColor = UIColor.tintColor
    private var sliderActiveTickColor = UIColor.white
    private var sliderInactiveTickColor = UIColor.secondaryLabel
    private var sliderTickLabelColor = UIColor.secondaryLabel
    private var sliderThumbLabelTextColor = UIColor.white
    private var sliderTickSize: CGFloat = 4
    private var sliderStopIndicatorSize: CGFloat = 0
    private var sliderTickLabels: [String] = []
    private var fileTreeExpandedPaths: Set<String> = []
    private var fileTreeSelectedPath: String?
    private var fileTreeInitialized = false
    private var pressAnimator: UIViewPropertyAnimator?
    private var shimmerLayer: CAGradientLayer?
    private var progressTrackLayer: CAShapeLayer?
    private var progressFillLayer: CAShapeLayer?
    private var toastDismissWorkItem: DispatchWorkItem?
    private var tooltipCloseWorkItem: DispatchWorkItem?
    private var toastScheduleSignature: String?
    private var toastAnnouncementSignature: String?
    private weak var anchoredPortalParent: UIView?
    private weak var anchoredPortalContent: UIView?
    private weak var anchoredPortalCatcher: UIControl?
    private weak var anchoredOverlayOwner: PamMobileUiHost?
    private var anchoredPortalIndex = 0
    private var anchoredPortalFrame = CGRect.zero
    private var navigationKind = 0
    private var tabsValue: String?
    private var tabsActivationMode = 1
    private var carouselCycle = false
    private var carouselContinuous = true
    private var carouselInterval: TimeInterval = 6
    private var carouselWorkItem: DispatchWorkItem?
    private var sparklineAutoDrawApplied = false
    private var sparklineSelectedIndex = -1

    private var animationsEnabled: Bool {
        !UIAccessibility.isReduceMotionEnabled
            && !(properties["reduceMotion"]?.pamFlag ?? false)
            && (properties["animationDuration"]?.pamInteger ?? 1) > 0
    }

    init(emit: @escaping EventEmitter) {
        self.emit = emit
        super.init(frame: .zero)
        clipsToBounds = false
        isAccessibilityElement = false
        isMultipleTouchEnabled = false

        let tap = UITapGestureRecognizer(target: self, action: #selector(onTap(_:)))
        tap.delegate = self
        addGestureRecognizer(tap)

        let press = UILongPressGestureRecognizer(target: self, action: #selector(onPressState(_:)))
        press.minimumPressDuration = 0
        press.cancelsTouchesInView = false
        press.delegate = self
        addGestureRecognizer(press)

        let overlayLongPress = UILongPressGestureRecognizer(
            target: self,
            action: #selector(onOverlayLongPress(_:))
        )
        overlayLongPress.minimumPressDuration = 0.5
        overlayLongPress.cancelsTouchesInView = false
        overlayLongPress.delegate = self
        addGestureRecognizer(overlayLongPress)

        let pan = UIPanGestureRecognizer(target: self, action: #selector(onPan(_:)))
        pan.maximumNumberOfTouches = 1
        pan.delegate = self
        addGestureRecognizer(pan)

    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    override var canBecomeFirstResponder: Bool {
        behavior == .tabTrigger
            && (properties["enabled"]?.pamFlag ?? true)
            && isUserInteractionEnabled
    }

    override var keyCommands: [UIKeyCommand]? {
        guard behavior == .tabTrigger else { return super.keyCommands }
        let inputs = [
            UIKeyCommand.inputLeftArrow,
            UIKeyCommand.inputRightArrow,
            UIKeyCommand.inputUpArrow,
            UIKeyCommand.inputDownArrow,
            "\u{F729}", // Home
            "\u{F72B}", // End
            " ",
            "\r",
        ]
        return inputs.map {
            UIKeyCommand(input: $0, modifierFlags: [], action: #selector(onTabKeyCommand(_:)))
        }
    }

    func update(_ next: [String: WireValue]) {
        let previousBehavior = behavior
        let wasControlled = isControlled
        properties = next
        behavior = PamMobileBehavior(
            rawValue: next["behavior"]?.pamInteger ?? behavior.rawValue
        ) ?? .container
        component = next["component"]?.pamInteger ?? 0
        if activeDateTimePicker != nil,
           (behavior != .dateTimePicker
                || next["enabled"]?.pamFlag == false
                || next["interactionDisabled"]?.pamFlag == true
                || next["readOnly"]?.pamFlag == true
                || next["isReadOnly"]?.pamFlag == true) {
            activeDateTimePicker?.dismissSilently()
            activeDateTimePicker = nil
        }
        if behavior == .tooltip {
            let delay = max(500, next["openDelay"]?.pamInteger ?? 500)
            gestureRecognizers?
                .compactMap { $0 as? UILongPressGestureRecognizer }
                .filter { $0.minimumPressDuration > 0 }
                .forEach { $0.minimumPressDuration = TimeInterval(delay) / 1_000 }
        }
        if previousBehavior != behavior {
            if previousBehavior == .calendar { accessibilityElements = nil }
            openDefaultInitialized = false
            fileTreeInitialized = false
            fileTreeExpandedPaths.removeAll()
            fileTreeSelectedPath = nil
            tooltipCloseWorkItem?.cancel()
            tooltipCloseWorkItem = nil
        }
        if behavior == .tabs {
            tabsActivationMode = min(2, max(1,
                next["activationMode"]?.pamInteger ?? tabsActivationMode
            ))
            if let controlled = next["value"]?.pamText
                ?? next["modelValue"]?.pamText {
                tabsValue = controlled
            } else if previousBehavior != .tabs {
                tabsValue = next["defaultValue"]?.pamText
            }
        }
        if behavior == .fileTree {
            let controlled = next["expandedPaths"]?.pamText
            if let paths = controlled ?? (!fileTreeInitialized
                ? next["defaultExpandedPaths"]?.pamText : nil) {
                fileTreeExpandedPaths = Set(paths.split(separator: "\n").map(String.init))
                fileTreeInitialized = true
            }
            if let selected = next["selectedPath"]?.pamText {
                fileTreeSelectedPath = selected
            }
        }
        isControlled = next["open"] != nil || next["isOpen"] != nil
        if isControlled {
            isOpen = next["open"]?.pamFlag ?? next["isOpen"]?.pamFlag ?? isOpen
        } else if wasControlled
            || (!openDefaultInitialized
                && (next["initiallyOpen"] != nil || next["defaultIsOpen"] != nil)) {
            openDefaultInitialized = true
            isOpen = next["initiallyOpen"]?.pamFlag
                ?? next["defaultIsOpen"]?.pamFlag
                ?? false
        } else if previousBehavior != behavior {
            isOpen = !(behavior == .popover || behavior == .menu || behavior == .tooltip)
        }
        let defaultChecked = behavior == .switchControl
            ? (next["value"]?.pamFlag ?? next["defaultValue"]?.pamFlag ?? isChecked)
            : (next["defaultIsChecked"]?.pamFlag ?? isChecked)
        isChecked = next["checked"]?.pamFlag
            ?? next["isChecked"]?.pamFlag
            ?? next["modelValue"]?.pamFlag
            ?? defaultChecked
        isSelectedState = next["selected"]?.pamFlag
            ?? next["isSelected"]?.pamFlag
            ?? isSelectedState
        buttonToggleItem = next["buttonToggleItem"]?.pamFlag ?? false
        isExpanded = next["expanded"]?.pamFlag
            ?? next["isExpanded"]?.pamFlag
            ?? isExpanded
        minimum = next["minimum"]?.pamDecimal
            ?? next["min"]?.pamDecimal
            ?? next["minValue"]?.pamDecimal
            ?? minimum
        maximum = max(minimum + 0.000_001,
            next["maximum"]?.pamDecimal
                ?? next["max"]?.pamDecimal
                ?? next["maxValue"]?.pamDecimal
                ?? maximum)
        step = max(0.000_001, next["step"]?.pamDecimal ?? step)
        value = clamped(next["value"]?.pamDecimal ?? next["modelValue"]?.pamDecimal ?? value)
        rangeEnabled = next["range"]?.pamFlag ?? rangeEnabled
        lowerValue = clamped(next["lowerValue"]?.pamDecimal ?? lowerValue)
        upperValue = clamped(next["upperValue"]?.pamDecimal ?? upperValue)
        if rangeEnabled {
            lowerValue = min(lowerValue, upperValue)
            upperValue = max(lowerValue, upperValue)
            value = upperValue
        }
        orientation = next["orientation"]?.pamInteger ?? orientation
        reversed = next["reversed"]?.pamFlag
            ?? next["isReversed"]?.pamFlag
            ?? reversed
        showSliderTicks = next["showTicks"]?.pamFlag == true
            || next["alwaysShowTicks"]?.pamFlag == true
        sliderTickSize = max(0, next["tickSize"]?.pamDecimal ?? 4)
        sliderStopIndicatorSize = max(0,
            next["stopIndicatorSize"]?.pamDecimal ?? 0)
        if let data = next["tickLabels"]?.pamText?.data(using: .utf8),
           let object = try? JSONSerialization.jsonObject(with: data),
           let labels = object as? [String] {
            sliderTickLabels = Array(labels.prefix(101))
        } else {
            sliderTickLabels = []
        }
        showThumbLabel = next["showThumbLabel"]?.pamFlag == true
            || next["alwaysShowThumbLabel"]?.pamFlag == true
        let legacyThumbSize = next["thumbSize"]?.pamDecimal
        sliderThumbWidth = max(
            1,
            next["thumbWidth"]?.pamDecimal
                ?? legacyThumbSize
                ?? sliderThumbWidth
        )
        sliderThumbHeight = max(
            1,
            next["thumbHeight"]?.pamDecimal
                ?? legacyThumbSize
                ?? sliderThumbHeight
        )
        sliderTrackThickness = max(
            1,
            next["trackThickness"]?.pamDecimal
                ?? next["sliderTrackHeight"]?.pamDecimal
                ?? sliderTrackThickness
        )
        navigationKind = next["navigationKind"]?.pamInteger ?? navigationKind
        carouselCycle = next["cycle"]?.pamFlag ?? false
        carouselContinuous = next["continuous"]?.pamFlag ?? true
        carouselInterval = min(
            60,
            max(0.75, (next["interval"]?.pamDecimal ?? 6_000) / 1_000)
        )
        if let selectedIndex = next["selectedIndex"]?.pamInteger {
            sparklineSelectedIndex = selectedIndex
        }
        snapPoints = parseNumbers(next["snapPoints"]?.pamText)
        snapIndex = min(
            max(0, next["snapToIndex"]?.pamInteger
                ?? next["defaultSnapIndex"]?.pamInteger
                ?? snapIndex),
            max(0, snapPoints.count - 1)
        )
        sheetSearchable = component == GeneratedComponents.SELECT_PORTAL
            && (next["searchable"]?.pamFlag ?? false)
        sheetAllowCustomValue = component == GeneratedComponents.SELECT_PORTAL
            && (next["allowCustomValue"]?.pamFlag ?? false)
        sheetSearchPlaceholder = next["searchPlaceholder"]?.pamText
            ?? "Search options"
        if behavior == .calendar {
            calendarYear = next["year"]?.pamInteger ?? calendarYear
            calendarMonth = min(12, max(1, next["month"]?.pamInteger ?? calendarMonth))
            let nextMode = next["mode"]?.pamInteger ?? 1
            if previousBehavior != .calendar || nextMode != calendarMode {
                calendarSelectedDates.removeAll()
                calendarRangeFrom = nil
                calendarRangeTo = nil
            }
            calendarMode = nextMode
            if nextMode == 2, let values = next["selectedValues"]?.pamText {
                calendarSelectedDates = Set(values.split(whereSeparator: \.isNewline).map(String.init))
            } else if nextMode == 3 {
                if let from = next["rangeFrom"]?.pamText {
                    calendarRangeFrom = from
                }
                if let to = next["rangeTo"]?.pamText {
                    calendarRangeTo = to
                }
            } else if let value = next["value"]?.pamText
                ?? next["defaultValue"]?.pamText {
                calendarSelectedDates = [String(value.prefix(10))]
            }
        }
        fillColor = color(next["fillColor"]?.pamInteger, fallback: fillColor)
        trackColor = color(next["trackColor"]?.pamInteger, fallback: trackColor)
        if behavior == .switchControl {
            trackColor = color(next["trackOffColor"]?.pamInteger, fallback: trackColor)
            fillColor = color(next["trackOnColor"]?.pamInteger, fallback: fillColor)
            switchTrackOutlineColor = color(
                next["trackOutlineColor"]?.pamInteger,
                fallback: switchTrackOutlineColor
            )
            switchThumbColor = color(
                next["thumbColor"]?.pamInteger,
                fallback: switchThumbColor
            )
            switchActiveThumbColor = color(
                next["activeThumbColor"]?.pamInteger,
                fallback: switchActiveThumbColor
            )
        }
        stateLayerColor = color(
            next["foregroundColor"]?.pamInteger,
            fallback: stateLayerColor
        )
        selectedForegroundColor = color(
            next["selectedForegroundColor"]?.pamInteger,
            fallback: selectedForegroundColor
        )
        selectedContainerColor = color(
            next["selectedContainerColor"]?.pamInteger,
            fallback: fillColor
        )
        sliderActiveTickColor = color(
            next["activeTickColor"]?.pamInteger,
            fallback: fillColor
        )
        sliderInactiveTickColor = color(
            next["inactiveTickColor"]?.pamInteger,
            fallback: trackColor
        )
        sliderTickLabelColor = color(
            next["tickLabelColor"]?.pamInteger,
            fallback: stateLayerColor
        )
        sliderThumbLabelTextColor = color(
            next["thumbLabelTextColor"]?.pamInteger,
            fallback: selectedForegroundColor
        )

        applySemantics()
        applyVisibility()
        applyBehaviorState()
        applyButtonToggleVisualState()
        applyTabTextVisualState()
        applyProgressState()
        scheduleCarousel()
        setNeedsLayout()
        setNeedsDisplay()
    }

    func releaseCallbacks() {
        activeDateTimePicker?.dismissSilently()
        activeDateTimePicker = nil
        restoreAnchoredPortalContent()
        pressAnimator?.stopAnimation(true)
        pressAnimator = nil
        shimmerLayer?.removeAllAnimations()
        shimmerLayer?.removeFromSuperlayer()
        shimmerLayer = nil
        toastDismissWorkItem?.cancel()
        toastDismissWorkItem = nil
        tooltipCloseWorkItem?.cancel()
        tooltipCloseWorkItem = nil
        carouselWorkItem?.cancel()
        carouselWorkItem = nil
        removeProgressLayers()
        emit = nil
        gestureRecognizers?.forEach(removeGestureRecognizer)
    }

    override func didAddSubview(_ subview: UIView) {
        super.didAddSubview(subview)
        setNeedsLayout()
        applySemantics()
        applyButtonToggleVisualState()
        applyTabTextVisualState()
    }

    override func point(inside point: CGPoint, with event: UIEvent?) -> Bool {
        guard isUserInteractionEnabled, !isHidden, alpha > 0.01 else {
            return false
        }
        guard requiresMinimumTouchTarget else {
            return super.point(inside: point, with: event)
        }
        let horizontalInset = max(0, (44 - bounds.width) / 2)
        let verticalInset = max(0, (44 - bounds.height) / 2)

        return bounds.insetBy(
            dx: -horizontalInset,
            dy: -verticalInset
        ).contains(point)
    }

    override func layoutSubviews() {
        super.layoutSubviews()
        layoutProgressLayers()
        switch behavior {
        case .bottomSheet:
            layoutBottomSheet()
        case .overlay, .modal, .portal:
            layoutOverlay()
        case .popover, .menu, .tooltip:
            layoutAnchoredOverlay()
        case .tabs:
            layoutTabs()
        case .tableRow:
            layoutTableRow()
        case .chipGroup:
            layoutChipGroup()
        case .listItem:
            layoutListItem()
        case .timeline:
            layoutTimeline()
        case .timelineItem:
            layoutTimelineItem()
        case .fileTree:
            layoutFileTree()
        case .calendar:
            updateCalendarAccessibilityElements()
        default:
            break
        }
        setNeedsDisplay()
    }

    override func draw(_ rect: CGRect) {
        super.draw(rect)
        guard let context = UIGraphicsGetCurrentContext() else { return }
        switch behavior {
        case .progress:
            drawProgress(context)
        case .slider:
            drawSlider(context)
        case .switchControl:
            drawSwitch(context)
        case .checkbox, .radio:
            if !(properties["abstractSelectionItem"]?.pamFlag ?? false) {
                drawSelection(context)
            }
        case .skeleton:
            drawSkeleton(context)
        case .calendar:
            drawCalendar(context)
        case .sparkline:
            drawSparkline(context)
        case .timeline:
            drawTimeline(context)
        case .sheetItem:
            drawSheetItemSelection(context)
        default:
            break
        }
    }

    private func drawSheetItemSelection(_ context: CGContext) {
        guard isChecked || isSelectedState else { return }
        let rtl = effectiveUserInterfaceLayoutDirection == .rightToLeft
        let centerX: CGFloat = rtl ? 24 : bounds.width - 24
        let centerY = bounds.midY
        let direction: CGFloat = rtl ? -1 : 1
        context.saveGState()
        context.setStrokeColor(fillColor.cgColor)
        context.setLineWidth(2.5)
        context.setLineCap(.round)
        context.setLineJoin(.round)
        context.move(to: CGPoint(x: centerX - 8 * direction, y: centerY))
        context.addLine(to: CGPoint(x: centerX - 2 * direction, y: centerY + 6))
        context.addLine(to: CGPoint(x: centerX + 9 * direction, y: centerY - 7))
        context.strokePath()
        context.restoreGState()
    }

    override func accessibilityIncrement() {
        if behavior == .sparkline, properties["interactive"]?.pamFlag == true {
            selectSparklineIndex(sparklineSelectedIndex + 1, emitChange: true)
            return
        }
        guard behavior == .slider || behavior == .progress || behavior == .bottomSheet else {
            return
        }
        if behavior == .bottomSheet {
            settleSheet(to: snapIndex + 1, emitChange: true)
        } else {
            setRangeValue(value + step, emitChange: true)
        }
    }

    override func accessibilityDecrement() {
        if behavior == .sparkline, properties["interactive"]?.pamFlag == true {
            selectSparklineIndex(sparklineSelectedIndex - 1, emitChange: true)
            return
        }
        guard behavior == .slider || behavior == .progress || behavior == .bottomSheet else {
            return
        }
        if behavior == .bottomSheet {
            settleSheet(to: snapIndex - 1, emitChange: true)
        } else {
            setRangeValue(value - step, emitChange: true)
        }
    }

    @objc private func onTap(_ recognizer: UITapGestureRecognizer) {
        guard isUserInteractionEnabled,
              !(properties["interactionDisabled"]?.pamFlag ?? false),
              (properties["enabled"]?.pamFlag ?? true) else { return }
        let point = recognizer.location(in: self)
        if behavior.isOverlay {
            if let content = overlayContent(),
               content.convert(content.bounds, to: self).contains(point) {
                return
            }
            if let backdrop = overlayBackdrop(),
               backdrop.convert(backdrop.bounds, to: self).contains(point) {
                requestBackdropDismiss()
                return
            }
        }

        switch behavior {
        case .checkbox, .switchControl:
            isChecked.toggle()
            setNeedsDisplay()
            emit?(.toggle, Data((isChecked ? "1" : "0").utf8))
        case .radio:
            if !isChecked {
                isChecked = true
                setNeedsDisplay()
                emit?(.toggle, Data("1".utf8))
            }
        case .accordion:
            isExpanded.toggle()
            applyAccordion()
            emit?(.toggle, Data((isExpanded ? "1" : "0").utf8))
        case .slider:
            let requested = sliderValue(at: point)
            let next = properties["rating"]?.pamFlag == true
                && properties["clearable"]?.pamFlag == true
                && requested == value
                ? minimum
                : requested
            setRangeValue(next, emitChange: true)
            emit?(.native, Data(formatted(value).utf8))
        case .tabTrigger:
            _ = tabsAncestor()?.selectTab(self, emitChange: true)
        case .inputSlot:
            activateInputSlot()
        case .formControl, .inputGroup:
            guard focusInputFromLabel(at: point) else { return }
            emit?(.press, Data())
        case .menuItem:
            activateMenuItem()
        case .fileTreeFolder, .fileTreeFile:
            if let tree = ancestor(where: { $0.behavior == .fileTree }) {
                tree.activateFileTreeItem(self)
            } else {
                if behavior == .fileTreeFolder { isExpanded.toggle() }
                isSelectedState = true
                applySemantics()
                emit?(.press, Data())
            }
        case .sheetItem:
            emit?(.press, Data())
            if behavior == .sheetItem,
               properties["closeOnSelect"]?.pamFlag
                   ?? properties["closeOnPress"]?.pamFlag
                   ?? (component == GeneratedComponents.SELECT_ITEM) {
                sheetAncestor()?.clearSheetSearch()
                sheetAncestor()?.requestDismiss()
            }
        case .popover, .menu:
            setOpen(!isOpen, shouldEmit: true)
        case .tooltip:
            if properties["openOnClick"]?.pamFlag ?? false {
                setOpen(!isOpen, shouldEmit: true)
            }
        case .dateTimePicker:
            presentDateTimePicker()
        case .calendar:
            if handleCalendarHeaderTap(at: point) {
                break
            }
            if let date = calendarDate(at: point) {
                _ = selectCalendarDate(date)
            }
        case .sparkline where properties["interactive"]?.pamFlag == true:
            updateSparklineSelection(at: point.x, emitChange: true)
        case .overlayDismiss:
            overlayAncestor()?.requestDismiss()
        default:
            emit?(.press, Data())
        }
        UIImpactFeedbackGenerator(style: .light).impactOccurred()
    }

    @objc private func onPressState(_ recognizer: UILongPressGestureRecognizer) {
        guard isUserInteractionEnabled else { return }
        switch recognizer.state {
        case .began:
            animateStateLayer(to: 0.92, duration: 0.075)
        case .ended, .cancelled, .failed:
            animateStateLayer(to: 1, duration: 0.18)
        default:
            break
        }
    }

    @objc private func onOverlayLongPress(_ recognizer: UILongPressGestureRecognizer) {
        guard behavior == .tooltip,
              properties["openOnLongPress"]?.pamFlag ?? true,
              isUserInteractionEnabled else {
            return
        }
        switch recognizer.state {
        case .began:
            tooltipCloseWorkItem?.cancel()
            tooltipCloseWorkItem = nil
            setOpen(true, shouldEmit: true)
        case .ended, .cancelled, .failed:
            tooltipCloseWorkItem?.cancel()
            let delay = max(0, properties["closeDelay"]?.pamInteger ?? 100)
            let workItem = DispatchWorkItem { [weak self] in
                self?.requestDismiss()
            }
            tooltipCloseWorkItem = workItem
            DispatchQueue.main.asyncAfter(
                deadline: .now() + TimeInterval(delay) / 1_000,
                execute: workItem
            )
        default:
            break
        }
    }

    private func tabsAncestor() -> PamMobileUiHost? {
        var ancestor = superview
        while let view = ancestor {
            if let host = view as? PamMobileUiHost, host.behavior == .tabs {
                return host
            }
            ancestor = view.superview
        }
        return nil
    }

    @discardableResult
    func selectTab(_ trigger: PamMobileUiHost, emitChange: Bool) -> Bool {
        guard behavior == .tabs,
              properties["enabled"]?.pamFlag ?? true,
              trigger.properties["enabled"]?.pamFlag ?? true,
              trigger.isUserInteractionEnabled,
              trigger.tabsAncestor() === self,
              let target = trigger.properties["value"]?.pamText else { return false }
        let changed = target != tabsValue
        tabsValue = target
        if trigger.window != nil { trigger.becomeFirstResponder() }
        setNeedsLayout()
        UIView.animate(
            withDuration: changed && animationsEnabled ? 0.2 : 0,
            delay: 0,
            options: [.beginFromCurrentState, .curveEaseInOut, .allowUserInteraction]
        ) {
            self.layoutTabs()
        }
        if changed {
            UIAccessibility.post(notification: .layoutChanged, argument: trigger)
            if trigger.window != nil { UISelectionFeedbackGenerator().selectionChanged() }
        }
        if emitChange { emit?(.change, Data(target.utf8)) }
        return true
    }

    @discardableResult
    func moveTabFocus(from trigger: PamMobileUiHost, direction: Int) -> Bool {
        guard behavior == .tabs,
              properties["enabled"]?.pamFlag ?? true else { return false }
        let triggers = tabTriggers(in: self).filter {
            ($0.properties["enabled"]?.pamFlag ?? true) && $0.isUserInteractionEnabled
        }
        guard !triggers.isEmpty,
              let current = triggers.firstIndex(of: trigger) else { return false }
        let nextIndex: Int
        switch direction {
        case Int.min: nextIndex = 0
        case Int.max: nextIndex = triggers.count - 1
        default: nextIndex = (current + direction % triggers.count + triggers.count) % triggers.count
        }
        let next = triggers[nextIndex]
        if next.window != nil { next.becomeFirstResponder() }
        if tabsActivationMode == 1 {
            _ = selectTab(next, emitChange: next.properties["value"]?.pamText != tabsValue)
        }
        return true
    }

    @objc private func onTabKeyCommand(_ command: UIKeyCommand) {
        guard behavior == .tabTrigger,
              let tabs = tabsAncestor(),
              let input = command.input else { return }
        let horizontal = tabs.orientation == 1
        let direction: Int
        switch input {
        case UIKeyCommand.inputLeftArrow where horizontal: direction = -1
        case UIKeyCommand.inputRightArrow where horizontal: direction = 1
        case UIKeyCommand.inputUpArrow where !horizontal: direction = -1
        case UIKeyCommand.inputDownArrow where !horizontal: direction = 1
        case "\u{F729}": direction = Int.min
        case "\u{F72B}": direction = Int.max
        case " ", "\r":
            _ = tabs.selectTab(self, emitChange: true)
            return
        default: return
        }
        _ = tabs.moveTabFocus(from: self, direction: direction)
    }

    private func scheduleCarousel() {
        carouselWorkItem?.cancel()
        carouselWorkItem = nil
        guard behavior == .tabs, navigationKind == 1, carouselCycle else {
            return
        }
        let workItem = DispatchWorkItem { [weak self] in
            guard let self else { return }
            let triggers = self.carouselTriggers()
            guard triggers.count > 1 else { return }
            let current = triggers.firstIndex(where: { $0.isSelectedState }) ?? 0
            let next = current + 1
            guard next < triggers.count || self.carouselContinuous else { return }
            self.selectTab(triggers[next % triggers.count], emitChange: true)
            self.scheduleCarousel()
        }
        carouselWorkItem = workItem
        DispatchQueue.main.asyncAfter(
            deadline: .now() + carouselInterval,
            execute: workItem
        )
    }

    private func panCarousel(_ recognizer: UIPanGestureRecognizer) {
        guard recognizer.state == .ended else { return }
        let velocity = recognizer.velocity(in: self)
        let horizontal = abs(velocity.x) >= abs(velocity.y)
        let primaryVelocity = horizontal ? velocity.x : velocity.y
        guard abs(primaryVelocity) >= 360 else { return }
        let triggers = carouselTriggers()
        guard triggers.count > 1 else { return }
        let current = triggers.firstIndex(where: { $0.isSelectedState }) ?? 0
        var direction = primaryVelocity < 0 ? 1 : -1
        if properties["reverse"]?.pamFlag == true {
            direction *= -1
        }
        let requested = current + direction
        let target: Int
        if carouselContinuous {
            target = (requested % triggers.count + triggers.count) % triggers.count
        } else {
            target = min(triggers.count - 1, max(0, requested))
        }
        guard target != current else { return }
        selectTab(triggers[target], emitChange: true)
        scheduleCarousel()
    }

    private func tabTriggers(in root: UIView) -> [PamMobileUiHost] {
        root.subviews.flatMap { child -> [PamMobileUiHost] in
            var matches: [PamMobileUiHost] = []
            if let host = child as? PamMobileUiHost, host.behavior == .tabTrigger {
                matches.append(host)
            }
            matches.append(contentsOf: tabTriggers(in: child))
            return matches
        }
    }

    private func carouselTriggers() -> [PamMobileUiHost] {
        tabTriggers(in: self).filter {
            $0.isUserInteractionEnabled
                && !($0.properties["carouselControl"]?.pamFlag ?? false)
        }
    }

    private func applyButtonToggleVisualState() {
        guard buttonToggleItem, behavior == .tabTrigger else { return }
        backgroundColor = isSelectedState ? selectedContainerColor : .clear
        layer.cornerRadius = CGFloat(
            properties["selectionCornerRadius"]?.pamDecimal ?? 8
        )
        layer.masksToBounds = true
        applyTabTextVisualState()
    }

    private func applyTabTextVisualState() {
        guard behavior == .tabTrigger else { return }
        if properties["preserveChildForeground"]?.pamFlag == true { return }
        setTextColor(
            in: self,
            color: isSelectedState ? selectedForegroundColor : stateLayerColor
        )
    }

    private func setTextColor(in root: UIView, color: UIColor) {
        root.subviews.forEach { child in
            (child as? UILabel)?.textColor = color
            (child as? UIButton)?.setTitleColor(color, for: .normal)
            setTextColor(in: child, color: color)
        }
    }

    func activateInputSlot() {
        emit?(.press, Data())
        let group = ancestor { $0.behavior == .inputGroup }
        guard let field = group?.allDescendants().compactMap({ $0 as? UITextField }).first,
              field.isEnabled else { return }
        let action = properties["slotAction"]?.pamInteger ?? 1
        let readOnly = group?.properties["readOnly"]?.pamFlag == true
            || group?.properties["interactionDisabled"]?.pamFlag == true
        switch action {
        case 2:
            if !readOnly {
                field.text = ""
                field.sendActions(for: .editingChanged)
            }
        case 3:
            if !readOnly {
                let selection = field.selectedTextRange
                field.isSecureTextEntry.toggle()
                field.selectedTextRange = selection
            }
        case 4:
            break
        default:
            field.becomeFirstResponder()
        }
        if action == 2 || action == 3,
           properties["focusOnPress"]?.pamFlag ?? true {
            field.becomeFirstResponder()
        }
    }

    func focusInputFromLabel(at point: CGPoint) -> Bool {
        guard behavior == .formControl || behavior == .inputGroup,
              properties["readOnly"]?.pamFlag != true,
              properties["isReadOnly"]?.pamFlag != true,
              let field = allDescendants().first(where: {
                  $0 is UITextField || $0 is UITextView
              }),
              (field as? UITextField)?.isEnabled ?? true,
              field.isUserInteractionEnabled else { return false }
        let label: UIView?
        if behavior == .formControl {
            label = descendant(tag: "pam:form-label")
        } else {
            let inputTop = convert(field.bounds, from: field).minY
            label = allDescendants().compactMap { $0 as? UILabel }.first { candidate in
                let bounds = convert(candidate.bounds, from: candidate)
                return bounds.height > 0 && bounds.maxY <= inputTop + 1
            }
        }
        guard let label,
              convert(label.bounds, from: label).contains(point) else { return false }
        _ = field.becomeFirstResponder()
        return true
    }

    func activateMenuItem() {
        guard let menu = ancestor(where: { $0.behavior == .menu })
            ?? anchoredOverlayOwner,
              menu.behavior == .menu, menu.isOpen,
              menu.properties["enabled"]?.pamFlag ?? true,
              properties["enabled"]?.pamFlag ?? true,
              isUserInteractionEnabled else { return }
        let mode = menu.properties["selectionMode"]?.pamInteger ?? 3
        if mode == 1 {
            for candidate in menu.menuItems() {
                candidate.isSelectedState = candidate === self
                candidate.applySemantics()
            }
        } else if mode == 2 {
            isSelectedState.toggle()
            applySemantics()
        }
        if properties["closeOnSelect"]?.pamFlag ?? true {
            menu.requestDismiss()
        }
        emit?(.press, Data())
    }

    func activateFileTreeItem(_ item: PamMobileUiHost) {
        guard behavior == .fileTree,
              let path = item.properties["path"]?.pamText,
              !path.isEmpty else { return }
        if item.behavior == .fileTreeFolder {
            if fileTreeExpandedPaths.contains(path) {
                fileTreeExpandedPaths.remove(path)
            } else {
                fileTreeExpandedPaths.insert(path)
            }
            fileTreeSelectedPath = path
            layoutFileTree()
            emit?(.change, Data(path.utf8))
            emitMap([
                "action": .integer(1),
                "path": .text(path),
                "expanded": .flag(fileTreeExpandedPaths.contains(path)),
            ])
        } else if item.behavior == .fileTreeFile, fileTreeSelectedPath != path {
            fileTreeSelectedPath = path
            layoutFileTree()
            emit?(.change, Data(path.utf8))
        }
    }

    @objc private func onPan(_ recognizer: UIPanGestureRecognizer) {
        switch behavior {
        case .bottomSheet:
            panSheet(recognizer)
        case .slider:
            panSlider(recognizer)
        case .tabs:
            if navigationKind == 1 {
                panCarousel(recognizer)
            }
        case .sparkline:
            panSparkline(recognizer)
        default:
            break
        }
    }

    private func applySemantics() {
        accessibilityIdentifier = properties["testId"]?.pamText
            ?? properties["value"]?.pamText
            ?? accessibilityIdentifier
        accessibilityLabel = properties["accessibilityLabel"]?.pamText
            ?? properties["ariaLabel"]?.pamText
            ?? accessibilityLabel
        accessibilityHint = properties["accessibilityHint"]?.pamText
            ?? accessibilityHint

        var traits: UIAccessibilityTraits = []
        switch behavior {
        case .checkbox where properties["abstractSelectionItem"]?.pamFlag ?? false:
            isAccessibilityElement = true
            traits = [.button]
            if isChecked { traits.insert(.selected) }
            accessibilityValue = isChecked ? "Selected" : "Not selected"
        case .checkbox, .radio, .switchControl:
            isAccessibilityElement = true
            traits = [.button]
            if isChecked { traits.insert(.selected) }
            accessibilityValue = isChecked ? "On" : "Off"
        case .slider, .progress, .bottomSheet:
            isAccessibilityElement = true
            traits = [.adjustable]
            accessibilityValue = behavior == .bottomSheet
                ? "Position \(snapIndex + 1) of \(max(1, snapPoints.count))"
                : (rangeEnabled && behavior == .slider
                    ? "\(formatted(lowerValue)) to \(formatted(upperValue))"
                    : formatted(value))
        case .sparkline where properties["interactive"]?.pamFlag == true:
            isAccessibilityElement = true
            traits = [.adjustable]
            accessibilityValue = sparklineAccessibilityValue
        case .tabTrigger:
            isAccessibilityElement = true
            traits = [.button]
            if isSelectedState { traits.insert(.selected) }
        case .sheetItem where component == GeneratedComponents.SELECT_ITEM:
            isAccessibilityElement = true
            traits = [.button]
            if isChecked || isSelectedState { traits.insert(.selected) }
            accessibilityValue = isChecked || isSelectedState ? "Selected" : "Not selected"
        case .tableRow where properties["isHeaderRow"]?.pamFlag == true:
            isAccessibilityElement = true
            traits = [.header]
        case .menuItem:
            isAccessibilityElement = true
            traits = [.button]
            let selectable = ((ancestor(where: { $0.behavior == .menu })
                ?? anchoredOverlayOwner)?
                .properties["selectionMode"]?.pamInteger ?? 3) != 3
            if selectable {
                if isSelectedState { traits.insert(.selected) }
                accessibilityValue = isSelectedState ? "Selected" : "Not selected"
            } else {
                accessibilityValue = nil
            }
        case .fileTreeFolder, .fileTreeFile:
            isAccessibilityElement = true
            traits = [.button]
            if isSelectedState { traits.insert(.selected) }
        case .sheetItem, .overlayDismiss, .inputSlot:
            isAccessibilityElement = true
            traits = [.button]
            accessibilityValue = nil
        default:
            isAccessibilityElement = accessibilityLabel != nil
        }
        if !(properties["enabled"]?.pamFlag ?? true) {
            traits.insert(.notEnabled)
        }
        accessibilityTraits = traits
    }

    private var requiresMinimumTouchTarget: Bool {
        if component == GeneratedComponents.BUTTON || component == GeneratedComponents.FAB {
            return true
        }
        switch behavior {
        case .accordion, .slider, .checkbox, .radio, .switchControl,
             .tabTrigger, .sheetItem, .menuItem, .overlayDismiss, .inputSlot,
             .fileTreeFolder, .fileTreeFile,
             .calendar, .dateTimePicker, .sparkline:
            return true
        default:
            return false
        }
    }

    private func applyVisibility() {
        if behavior.isOverlay {
            isHidden = !isOpen
            accessibilityViewIsModal = isOpen
                && (properties["trapFocus"]?.pamFlag
                    ?? properties["focusScope"]?.pamFlag
                    ?? true)
            if !isOpen {
                restoreAnchoredPortalContent()
            }
        }
        if behavior == .accordion {
            applyAccordion()
        }
    }

    private func applyBehaviorState() {
        switch behavior {
        case .inputGroup:
            applyInputState()
        case .formControl:
            applyFormState()
        case .toast:
            applyToastState()
        case .skeleton:
            applyShimmer()
        case .fileTree, .fileTreeFolder:
            setNeedsLayout()
        case .sparkline:
            applySparklineAutoDraw()
        default:
            shimmerLayer?.removeAllAnimations()
            shimmerLayer?.removeFromSuperlayer()
            shimmerLayer = nil
        }
    }

    private func applyAccordion() {
        guard behavior == .accordion else { return }
        if let content = descendant(tag: "pam:accordion-content") {
            content.isHidden = !isExpanded
            content.alpha = isExpanded ? 1 : 0
        }
        accessibilityValue = isExpanded ? "Expanded" : "Collapsed"
    }

    private func layoutBottomSheet() {
        guard let content = overlayContent() else { return }
        overlayBackdrop()?.frame = bounds
        overlayBackdrop()?.autoresizingMask = [.flexibleWidth, .flexibleHeight]

        let safeBottom = safeAreaInsets.bottom
        let viewport = max(1, bounds.height)
        let points = snapPoints.isEmpty ? [55] : snapPoints
        snapIndex = min(max(0, snapIndex), points.count - 1)
        activeSheetHeight = viewport * min(100, max(1, points.max() ?? 55)) / 100
        let selectedHeight = viewport * min(100, max(1, points[snapIndex])) / 100
        content.frame = CGRect(
            x: 0,
            y: viewport - activeSheetHeight,
            width: bounds.width,
            height: activeSheetHeight + safeBottom
        )
        content.layer.cornerRadius = 28
        content.layer.cornerCurve = .continuous
        content.layer.maskedCorners = [.layerMinXMinYCorner, .layerMaxXMinYCorner]
        content.clipsToBounds = true
        content.transform = CGAffineTransform(
            translationX: 0,
            y: activeSheetHeight - selectedHeight
        )
        let handleWrapper = descendant(tag: "pam:sheet-drag-indicator-wrapper")
        let handleIndicator = descendant(tag: "pam:sheet-drag-indicator")
        let handleBlock: CGFloat = handleWrapper == nil ? 0 : 24
        if let handleWrapper {
            handleWrapper.frame = CGRect(x: 0, y: 0, width: content.bounds.width, height: 24)
            handleWrapper.isAccessibilityElement = false
        }
        if let handleIndicator {
            handleIndicator.frame = CGRect(
                x: (content.bounds.width - 32) / 2,
                y: 10,
                width: 32,
                height: 4
            )
            handleIndicator.isAccessibilityElement = false
        }
        let search = ensureSheetSearchField(in: content)
        let rowHeight: CGFloat = 56
        let inset: CGFloat = 8
        let contentTop = inset + handleBlock
        if let search {
            search.frame = CGRect(
                x: inset,
                y: contentTop,
                width: max(0, content.bounds.width - inset * 2),
                height: rowHeight
            )
        }
        let itemOffset = search == nil ? contentTop : contentTop + rowHeight + inset
        let items = sheetItems(in: content).filter { !$0.isHidden }
        let supplementary = sheetCustomAction?.isHidden == false
            ? sheetCustomAction
            : (sheetEmptyState?.isHidden == false ? sheetEmptyState : nil)
        if let supplementary {
            supplementary.frame = CGRect(
                x: inset,
                y: itemOffset,
                width: max(0, content.bounds.width - inset * 2),
                height: rowHeight
            )
        }
        let supplementaryOffset: CGFloat = supplementary == nil ? 0 : rowHeight
        for (index, item) in items.enumerated() {
            let parentWidth = item.superview?.bounds.width ?? content.bounds.width
            item.frame = CGRect(
                x: item.superview === content ? inset : 0,
                y: itemOffset + supplementaryOffset + CGFloat(index) * rowHeight,
                width: max(0, parentWidth - (item.superview === content ? inset * 2 : 0)),
                height: rowHeight
            )
        }
        accessibilityValue = "Position \(snapIndex + 1) of \(points.count)"
    }

    private func ensureSheetSearchField(in content: UIView) -> UITextField? {
        guard sheetSearchable else {
            sheetSearchField?.removeFromSuperview()
            sheetCustomAction?.removeFromSuperview()
            sheetSearchField = nil
            sheetCustomAction = nil
            _ = ensureSheetEmptyState(in: content)
            updateSheetSupplementary(query: "", content: content)
            return nil
        }
        let field = sheetSearchField ?? {
            let input = UITextField(frame: .zero)
            input.borderStyle = .none
            input.clearButtonMode = .whileEditing
            input.returnKeyType = .done
            input.autocorrectionType = .no
            input.font = .preferredFont(forTextStyle: .body)
            input.adjustsFontForContentSizeCategory = true
            input.layer.cornerRadius = 16
            input.layer.cornerCurve = .continuous
            input.backgroundColor = color(
                properties["searchBackgroundColor"]?.pamInteger,
                fallback: .secondarySystemBackground
            )
            input.textColor = color(
                properties["searchTextColor"]?.pamInteger,
                fallback: .label
            )
            input.addTarget(
                self,
                action: #selector(filterSheetItems(_:)),
                for: .editingChanged
            )
            let padding = UIView(frame: CGRect(x: 0, y: 0, width: 16, height: 1))
            input.leftView = padding
            input.leftViewMode = .always
            sheetSearchField = input
            return input
        }()
        field.placeholder = sheetSearchPlaceholder
        field.accessibilityLabel = sheetSearchPlaceholder
        if field.superview !== content {
            field.removeFromSuperview()
            content.addSubview(field)
        }
        let customAction = sheetCustomAction ?? {
            let button = UIButton(type: .system)
            button.contentHorizontalAlignment = .leading
            button.titleLabel?.font = UIFontMetrics(forTextStyle: .body).scaledFont(
                for: .systemFont(ofSize: 17, weight: .medium)
            )
            button.titleLabel?.adjustsFontForContentSizeCategory = true
            button.layer.cornerRadius = 12
            button.layer.cornerCurve = .continuous
            button.addTarget(
                self,
                action: #selector(acceptCustomSheetValue),
                for: .touchUpInside
            )
            sheetCustomAction = button
            return button
        }()
        customAction.setTitleColor(
            color(properties["customActionTextColor"]?.pamInteger, fallback: .systemBlue),
            for: .normal
        )
        customAction.backgroundColor = color(
            properties["customActionBackgroundColor"]?.pamInteger,
            fallback: .secondarySystemBackground
        )
        if customAction.superview !== content {
            customAction.removeFromSuperview()
            content.addSubview(customAction)
        }
        _ = ensureSheetEmptyState(in: content)
        updateSheetSupplementary(query: field.text ?? "", content: content)
        return field
    }

    private func ensureSheetEmptyState(in content: UIView) -> UILabel {
        let emptyState = sheetEmptyState ?? {
            let label = UILabel(frame: .zero)
            label.font = .preferredFont(forTextStyle: .subheadline)
            label.adjustsFontForContentSizeCategory = true
            label.textColor = .secondaryLabel
            label.text = properties["noDataText"]?.pamText ?? "No options available"
            label.accessibilityTraits = [.staticText]
            sheetEmptyState = label
            return label
        }()
        if emptyState.superview !== content {
            emptyState.removeFromSuperview()
            content.addSubview(emptyState)
        }
        emptyState.text = properties["noDataText"]?.pamText ?? "No options available"
        return emptyState
    }

    @objc
    private func filterSheetItems(_ field: UITextField) {
        guard let content = overlayContent() else { return }
        let query = field.text?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        for item in sheetItems(in: content) {
            let label = item.accessibilityLabel ?? findFirstText(in: item) ?? ""
            item.isHidden = !query.isEmpty
                && label.range(of: query, options: [.caseInsensitive, .diacriticInsensitive]) == nil
        }
        updateSheetSupplementary(query: query, content: content)
        setNeedsLayout()
    }

    private func updateSheetSupplementary(query: String, content: UIView) {
        let visibleItems = sheetItems(in: content).filter { !$0.isHidden }
        let exact = visibleItems.contains { item in
            let label = item.accessibilityLabel ?? findFirstText(in: item) ?? ""
            return label.compare(
                query,
                options: [.caseInsensitive, .diacriticInsensitive]
            ) == .orderedSame
        }
        let showCustom = sheetAllowCustomValue && !query.isEmpty && !exact
        sheetCustomAction?.isHidden = !showCustom
        sheetCustomAction?.setTitle("Use \"\(query)\"", for: .normal)
        sheetEmptyState?.isHidden = !visibleItems.isEmpty || showCustom
    }

    @objc
    private func acceptCustomSheetValue() {
        guard let value = sheetSearchField?.text?
            .trimmingCharacters(in: .whitespacesAndNewlines),
            !value.isEmpty else { return }
        emit?(.change, Data(value.utf8))
        clearSheetSearch()
        emit?(.native, Data())
    }

    private func clearSheetSearch() {
        sheetSearchField?.text = ""
        sheetSearchField?.resignFirstResponder()
        if let content = overlayContent() {
            for item in sheetItems(in: content) {
                item.isHidden = false
            }
            updateSheetSupplementary(query: "", content: content)
        }
    }

    private func sheetItems(in root: UIView) -> [PamMobileUiHost] {
        var items: [PamMobileUiHost] = []
        func collect(_ view: UIView) {
            for child in view.subviews {
                if let host = child as? PamMobileUiHost, host.behavior == .sheetItem {
                    items.append(host)
                } else {
                    collect(child)
                }
            }
        }
        collect(root)
        return items
    }

    private func layoutOverlay() {
        overlayBackdrop()?.frame = bounds
        guard let content = overlayContent() else { return }
        if behavior == .modal {
            let width = min(max(280, bounds.width - 48), 560)
            let measured = content.sizeThatFits(
                CGSize(width: width, height: bounds.height - 96)
            )
            let height = min(max(120, measured.height), bounds.height - 96)
            content.frame = CGRect(
                x: (bounds.width - width) / 2,
                y: (bounds.height - height) / 2,
                width: width,
                height: height
            )
        } else {
            content.frame = bounds
        }
    }

    private func layoutAnchoredOverlay() {
        guard isOpen else {
            restoreAnchoredPortalContent()
            return
        }
        presentAnchoredPortalContent()
    }

    private func layoutTabs() {
        let triggers = tabTriggers(in: self)
        let selectedTrigger = tabsValue.flatMap { value in
            triggers.first { $0.properties["value"]?.pamText == value }
        }
        for trigger in triggers {
            let selected = trigger === selectedTrigger
            if trigger.isSelectedState != selected {
                trigger.isSelectedState = selected
                trigger.applyButtonToggleVisualState()
                trigger.applyTabTextVisualState()
                trigger.applySemantics()
            }
        }
        if navigationKind == 1 {
            triggers.forEach { trigger in
                let visible = trigger === selectedTrigger
                trigger.isHidden = !visible
                trigger.accessibilityElementsHidden = !visible
            }
        }
        descendants(prefix: "pam:tabs-content:").forEach { child in
            let value = child.accessibilityIdentifier.map {
                String($0.dropFirst("pam:tabs-content:".count))
            }
            let visible = value == tabsValue
            child.isHidden = !visible
            child.accessibilityElementsHidden = !visible
        }
        descendants(prefix: "pam:tabs-content-force:").forEach { child in
            child.isHidden = false
            child.accessibilityElementsHidden = false
        }
        guard
            let trigger = selectedTrigger,
            let indicator = descendant(tag: "pam:tabs-indicator")
        else {
            descendant(tag: "pam:tabs-indicator")?.isHidden = true
            return
        }
        let triggerFrame = trigger.convert(trigger.bounds, to: self)
        indicator.isHidden = false
        indicator.isUserInteractionEnabled = false
        indicator.accessibilityElementsHidden = true
        indicator.frame = CGRect(
            x: triggerFrame.minX,
            y: triggerFrame.maxY - 2,
            width: triggerFrame.width,
            height: 2
        )
    }

    private func layoutTableRow() {
        let visible = subviews.filter { !$0.isHidden }
        guard !visible.isEmpty else { return }
        let width = bounds.width / CGFloat(visible.count)
        for (index, child) in visible.enumerated() {
            let logical = CGFloat(index) * width
            let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? bounds.width - logical - width
                : logical
            child.frame = CGRect(x: x, y: 0, width: width, height: bounds.height)
        }
    }

    private func layoutListItem() {
        let visible = subviews.filter { !$0.isHidden }
        guard !visible.isEmpty else { return }
        let inset: CGFloat = 16
        let gap: CGFloat = 12
        let rightToLeft = effectiveUserInterfaceLayoutDirection == .rightToLeft

        if visible.count >= 3 {
            let leading = visible[0]
            let trailing = visible[visible.count - 1]
            let body = Array(visible[1..<(visible.count - 1)])
            let leadingX = rightToLeft
                ? bounds.width - inset - leading.bounds.width
                : inset
            let trailingX = rightToLeft
                ? inset
                : bounds.width - inset - trailing.bounds.width
            let centeredY: (UIView) -> CGFloat = { child in
                max(0, (self.bounds.height - child.bounds.height) / 2)
            }
            leading.frame.origin = CGPoint(x: leadingX, y: centeredY(leading))
            trailing.frame.origin = CGPoint(x: trailingX, y: centeredY(trailing))

            let bodyStart = rightToLeft
                ? trailing.frame.maxX + gap
                : leading.frame.maxX + gap
            let bodyEnd = rightToLeft
                ? leading.frame.minX - gap
                : trailing.frame.minX - gap
            let availableBodyWidth = max(0, bodyEnd - bodyStart)
            let bodyHeight = body.reduce(CGFloat.zero) { $0 + $1.bounds.height }
            var bodyY = max(0, (bounds.height - bodyHeight) / 2)
            for child in body {
                let childWidth = min(child.bounds.width, availableBodyWidth)
                let x = rightToLeft ? bodyEnd - childWidth : bodyStart
                child.frame = CGRect(
                    x: x,
                    y: bodyY,
                    width: childWidth,
                    height: child.bounds.height
                )
                bodyY += child.bounds.height
            }
            return
        }

        let totalHeight = visible.reduce(CGFloat.zero) { $0 + $1.bounds.height }
        var y = max(0, (bounds.height - totalHeight) / 2)
        for child in visible {
            let width = min(child.bounds.width, bounds.width - inset * 2)
            let x = rightToLeft ? bounds.width - inset - width : inset
            child.frame = CGRect(x: x, y: y, width: width, height: child.bounds.height)
            y += child.bounds.height
        }
    }

    private func layoutChipGroup() {
        let gap: CGFloat = 8
        let rowHeight: CGFloat = 40
        var logicalX: CGFloat = 0
        var y: CGFloat = 0
        for child in subviews where !child.isHidden {
            if logicalX > 0, logicalX + child.bounds.width > bounds.width {
                logicalX = 0
                y += rowHeight
            }
            let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? bounds.width - logicalX - child.bounds.width : logicalX
            child.frame = CGRect(x: x, y: y, width: child.bounds.width, height: child.bounds.height)
            logicalX += child.bounds.width + gap
        }
    }

    private func layoutTimeline() {
        let visible = subviews.filter { !$0.isHidden }
        for (index, child) in visible.enumerated() {
            child.frame = CGRect(x: 0, y: CGFloat(index) * 64, width: bounds.width, height: 64)
        }
    }

    private func layoutTimelineItem() {
        let inset: CGFloat = 40
        for child in subviews where !child.isHidden {
            let height = min(child.bounds.height, bounds.height)
            let y = (bounds.height - height) / 2
            child.frame = CGRect(
                x: effectiveUserInterfaceLayoutDirection == .rightToLeft ? 0 : inset,
                y: y,
                width: max(0, bounds.width - inset),
                height: height
            )
        }
    }

    private func drawTimeline(_ context: CGContext) {
        let visible = subviews.filter { !$0.isHidden }
        guard !visible.isEmpty else { return }
        let axis: CGFloat = effectiveUserInterfaceLayoutDirection == .rightToLeft
            ? bounds.width - 20 : 20
        context.setStrokeColor(trackColor.cgColor)
        context.setLineWidth(2)
        context.move(to: CGPoint(x: axis, y: 32))
        context.addLine(to: CGPoint(x: axis, y: 32 + CGFloat(visible.count - 1) * 64))
        context.strokePath()
        context.setFillColor(fillColor.cgColor)
        for index in visible.indices {
            context.fillEllipse(in: CGRect(
                x: axis - 6,
                y: 26 + CGFloat(index) * 64,
                width: 12,
                height: 12
            ))
        }
    }

    private func layoutFileTree() {
        guard behavior == .fileTree else { return }
        for item in allDescendants().compactMap({ $0 as? PamMobileUiHost })
            where item.behavior == .fileTreeFolder || item.behavior == .fileTreeFile {
            guard let path = item.properties["path"]?.pamText else { continue }
            item.isSelectedState = path == fileTreeSelectedPath
            item.applySemantics()
            if item.behavior == .fileTreeFolder {
                let expanded = fileTreeExpandedPaths.contains(path)
                item.accessibilityValue = expanded ? "Expanded" : "Collapsed"
                if let content = item.descendant(tag: "pam:file-tree-content") {
                    content.isHidden = !expanded
                    content.accessibilityElementsHidden = !expanded
                }
                if let chevron = item.descendant(tag: "pam:file-tree-chevron") {
                    chevron.transform = CGAffineTransform(rotationAngle: expanded ? .pi / 2 : 0)
                }
            }
        }
    }

    private func applyInputState() {
        let readOnly = properties["readOnly"]?.pamFlag == true
            || properties["interactionDisabled"]?.pamFlag == true
        let enabled = properties["enabled"]?.pamFlag ?? true
        let invalid = properties["invalid"]?.pamFlag
            ?? properties["error"]?.pamFlag
            ?? false
        let fields = allDescendants().compactMap { $0 as? UITextField }
        for field in fields {
            field.isEnabled = enabled
            field.isUserInteractionEnabled = enabled && !readOnly
            field.adjustsFontForContentSizeCategory = true
            field.clearButtonMode = properties["clearable"]?.pamFlag == true
                ? .whileEditing : .never
            if properties["passwordVisible"]?.pamFlag != true,
               properties["secureTextEntry"]?.pamFlag == true {
                field.isSecureTextEntry = true
            }
            field.accessibilityValue = readOnly
                ? "\(field.text ?? ""), read only"
                : field.text
        }
        let indicatorOnly = properties["indicatorOnly"]?.pamFlag ?? false
        layer.cornerRadius = max(0,
            properties["outlineRadius"]?.pamDecimal ?? layer.cornerRadius)
        layer.borderWidth = indicatorOnly
            ? 0
            : (invalid ? 2 : (properties["focused"]?.pamFlag == true
                ? 2 : max(0, properties["outlineWidth"]?.pamDecimal ?? 0)))
        layer.borderColor = color(
            invalid
                ? properties["invalidColor"]?.pamInteger
                : properties["focusColor"]?.pamInteger,
            fallback: invalid ? UIColor.systemRed : tintColor
        ).cgColor
    }

    private func applyFormState() {
        let invalid = properties["invalid"]?.pamFlag
            ?? properties["error"]?.pamFlag
            ?? false
        let required = properties["required"]?.pamFlag ?? false
        accessibilityLabel = properties["label"]?.pamText ?? accessibilityLabel
        accessibilityHint = properties["helperText"]?.pamText
            ?? properties["errorMessage"]?.pamText
            ?? accessibilityHint
        accessibilityValue = [
            required ? "Required" : nil,
            invalid ? "Invalid" : nil,
        ].compactMap { $0 }.joined(separator: ", ")
        if invalid {
            UIAccessibility.post(
                notification: .announcement,
                argument: properties["errorMessage"]?.pamText ?? "Invalid field"
            )
        }
    }

    private func applyToastState() {
        accessibilityViewIsModal = false
        isAccessibilityElement = true
        accessibilityTraits = [.staticText]
        let persistent = properties["persistent"]?.pamFlag ?? false
        let duration = max(
            500,
            min(
                60_000,
                properties["duration"]?.pamInteger
                    ?? properties["timeout"]?.pamInteger
                    ?? 4_000
            )
        )
        let identity = properties["toastId"]?.pamText
            ?? properties["id"]?.pamText
            ?? ""
        let action = min(6, max(1, properties["action"]?.pamInteger ?? 1))
        let signature = "\(identity)\u{0}\(duration)\u{0}\(persistent)\u{0}\(isOpen)"

        guard isOpen else {
            toastDismissWorkItem?.cancel()
            toastDismissWorkItem = nil
            layer.removeAllAnimations()
            isHidden = true
            alpha = 1
            transform = .identity
            accessibilityElementsHidden = true
            toastScheduleSignature = signature
            return
        }

        isHidden = false
        accessibilityElementsHidden = false
        let announcement = accessibilityLabel ?? findFirstText(in: self) ?? "Notification"
        let announcementSignature = "\(identity)\u{0}\(action)\u{0}\(announcement)"
        if announcementSignature != toastAnnouncementSignature {
            toastAnnouncementSignature = announcementSignature
            UIAccessibility.post(
                notification: .announcement,
                argument: announcement
            )
        }

        guard signature != toastScheduleSignature else { return }
        toastScheduleSignature = signature
        toastDismissWorkItem?.cancel()
        toastDismissWorkItem = nil
        layer.removeAllAnimations()
        if animationsEnabled {
            let entersFromTop = properties["location"]?.pamText?
                .lowercased()
                .contains("top") ?? false
            alpha = 0
            transform = CGAffineTransform(
                translationX: 0,
                y: entersFromTop ? -8 : 8
            )
            UIView.animate(
                withDuration: 0.18,
                delay: 0,
                options: [.beginFromCurrentState, .curveEaseOut]
            ) {
                self.alpha = 1
                self.transform = .identity
            }
        } else {
            alpha = 1
            transform = .identity
        }

        guard !persistent else { return }
        let workItem = DispatchWorkItem { [weak self] in
            guard let self, self.isOpen else { return }
            if !self.isControlled {
                self.isOpen = false
                let hide = {
                    self.isHidden = true
                    self.alpha = 1
                    self.transform = .identity
                    self.accessibilityElementsHidden = true
                }
                if self.animationsEnabled {
                    UIView.animate(
                        withDuration: 0.14,
                        delay: 0,
                        options: [.beginFromCurrentState, .curveEaseIn],
                        animations: {
                            self.alpha = 0
                            self.transform = CGAffineTransform(
                                translationX: 0,
                                y: -8
                            )
                        },
                        completion: { _ in hide() }
                    )
                } else {
                    hide()
                }
            }
            self.emitMap([
                "action": .integer(PamHostAction.dismiss.rawValue),
                "dismissed": .flag(true),
            ])
        }
        toastDismissWorkItem = workItem
        DispatchQueue.main.asyncAfter(
            deadline: .now() + .milliseconds(Int(duration)),
            execute: workItem
        )
    }

    private func applyShimmer() {
        guard animationsEnabled,
              !(properties["isLoaded"]?.pamFlag ?? false),
              !(properties["boilerplate"]?.pamFlag ?? false) else {
            shimmerLayer?.removeAllAnimations()
            shimmerLayer?.removeFromSuperlayer()
            shimmerLayer = nil
            return
        }
        let gradient = shimmerLayer ?? CAGradientLayer()
        gradient.colors = [
            UIColor.clear.cgColor,
            UIColor.white.withAlphaComponent(0.22).cgColor,
            UIColor.clear.cgColor,
        ]
        gradient.locations = [0, 0.5, 1]
        gradient.startPoint = CGPoint(x: 0, y: 0.5)
        gradient.endPoint = CGPoint(x: 1, y: 0.5)
        gradient.frame = bounds.insetBy(dx: -bounds.width, dy: 0)
        if gradient.superlayer == nil { layer.addSublayer(gradient) }
        if gradient.animation(forKey: "pam.shimmer") == nil {
            let animation = CABasicAnimation(keyPath: "transform.translation.x")
            animation.fromValue = -bounds.width
            animation.toValue = bounds.width
            animation.duration = properties["pulseDuration"]?.pamDecimal.map {
                TimeInterval($0 / 1_000)
            } ?? 1.2
            animation.repeatCount = .infinity
            gradient.add(animation, forKey: "pam.shimmer")
        }
        shimmerLayer = gradient
    }

    func configuredDatePicker() -> UIDatePicker {
        let picker = UIDatePicker()
        picker.preferredDatePickerStyle = .wheels
        picker.datePickerMode = switch pickerMode {
        case 5: .time
        case 6: .dateAndTime
        default: .date
        }
        let locale = properties["locale"]?.pamText ?? Locale.current.identifier
        picker.locale = Locale(identifier: (properties["is24Hour"]?.pamFlag ?? false)
            ? "\(locale)@hours=h23" : locale)
        picker.timeZone = pickerTimeZone()
        let dateFormatter = pickerFormatter("yyyy-MM-dd")
        let lower = properties["minDate"]?.pamText
            ?? properties["minimumDate"]?.pamText
        let upper = properties["maxDate"]?.pamText
            ?? properties["maximumDate"]?.pamText
        if picker.datePickerMode != .time {
            picker.minimumDate = lower.flatMap { dateFormatter.date(from: String($0.prefix(10))) }
            picker.maximumDate = upper.flatMap { dateFormatter.date(from: String($0.prefix(10))) }
            if picker.datePickerMode == .dateAndTime,
               let upperDate = picker.maximumDate {
                picker.maximumDate = pickerCalendar().date(
                    byAdding: DateComponents(day: 1, second: -1), to: upperDate
                )
            }
        }
        if let initial = pickerInitialDate() {
            picker.date = min(picker.maximumDate ?? initial,
                              max(picker.minimumDate ?? initial, initial))
        }
        return picker
    }

    private func pickerTimeZone() -> TimeZone {
        guard let minutes = properties["timeZoneOffsetInMinutes"]?.pamInteger,
              (-1_080...1_080).contains(minutes) else { return .current }
        return TimeZone(secondsFromGMT: minutes * 60) ?? .current
    }

    private var pickerMode: Int {
        properties["mode"]?.pamInteger ?? properties["type"]?.pamInteger ?? 6
    }

    private func pickerCalendar() -> Calendar {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = pickerTimeZone()
        return calendar
    }

    private func pickerFormatter(_ pattern: String) -> DateFormatter {
        let formatter = DateFormatter()
        formatter.calendar = pickerCalendar()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = pickerTimeZone()
        formatter.dateFormat = pattern
        formatter.isLenient = false
        return formatter
    }

    func pickerInitialDate() -> Date? {
        guard let raw = properties["value"]?.pamText
            ?? properties["modelValue"]?.pamText,
            !raw.isEmpty else { return nil }
        if pickerMode == 5 {
            let fields = raw.split(separator: ":", omittingEmptySubsequences: false)
            let parts = fields.compactMap { Int($0) }
            guard parts.count == fields.count, parts.count >= 2, parts.count <= 3,
                  (0...23).contains(parts[0]), (0...59).contains(parts[1]),
                  parts.count < 3 || (0...59).contains(parts[2]) else { return nil }
            return pickerCalendar().date(
                bySettingHour: parts[0], minute: parts[1],
                second: parts.count == 3 ? parts[2] : 0, of: Date()
            )
        }
        if raw.count == 10 { return pickerFormatter("yyyy-MM-dd").date(from: raw) }
        for pattern in [
            "yyyy-MM-dd'T'HH:mm:ss.SSSXXXXX",
            "yyyy-MM-dd'T'HH:mm:ssXXXXX",
            "yyyy-MM-dd'T'HH:mmXXXXX",
            "yyyy-MM-dd'T'HH:mm:ss.SSS",
            "yyyy-MM-dd'T'HH:mm:ss",
            "yyyy-MM-dd'T'HH:mm",
        ] {
            if let date = pickerFormatter(pattern).date(from: raw) { return date }
        }
        return nil
    }

    func pickerValue(for date: Date) -> String {
        switch pickerMode {
        case 4: return pickerFormatter("yyyy-MM-dd").string(from: date)
        case 5: return pickerFormatter("HH:mm").string(from: date)
        default:
            let suffix = properties["timeZoneOffsetInMinutes"]?.pamInteger == nil
                ? "" : "XXXXX"
            return pickerFormatter("yyyy-MM-dd'T'HH:mm\(suffix)").string(from: date)
        }
    }

    private func pickerDismissed() {
        activeDateTimePicker = nil
        emitMap([
            "action": .integer(PamHostAction.dismiss.rawValue),
            "dismissed": .flag(true),
        ])
    }

    private func presentDateTimePicker() {
        guard behavior == .dateTimePicker,
              properties["readOnly"]?.pamFlag != true,
              properties["isReadOnly"]?.pamFlag != true,
              properties["interactionDisabled"]?.pamFlag != true,
              properties["enabled"]?.pamFlag != false,
              activeDateTimePicker == nil,
              let controller = nearestViewController() else { return }
        let picker = configuredDatePicker()
        let sheet = PamDateTimePickerController(
            picker: picker,
            title: properties["label"]?.pamText ?? "Select date",
            onDone: { [weak self] date in
                guard let self else { return }
                self.activeDateTimePicker = nil
                self.emit?(.change, Data(self.pickerValue(for: date).utf8))
            },
            onDismiss: { [weak self] in self?.pickerDismissed() }
        )
        activeDateTimePicker = sheet
        controller.present(sheet, animated: animationsEnabled)
    }

    private func panSheet(_ recognizer: UIPanGestureRecognizer) {
        guard properties["enablePanDownToClose"]?.pamFlag ?? true else { return }
        guard let content = overlayContent() else { return }
        let translation = max(0, recognizer.translation(in: self).y)
        switch recognizer.state {
        case .began:
            dragOrigin = content.transform.ty
        case .changed:
            content.transform = CGAffineTransform(
                translationX: 0,
                y: min(activeSheetHeight + 32, dragOrigin + translation)
            )
            let progress = min(1, content.transform.ty / max(1, activeSheetHeight))
            overlayBackdrop()?.alpha = 1 - progress
        case .ended, .cancelled:
            let velocity = recognizer.velocity(in: self).y
            if velocity > 720 || content.transform.ty > activeSheetHeight * 0.72 {
                requestDismiss()
            } else {
                settleSheet(to: nearestSnap(for: content.transform.ty), emitChange: true)
            }
        default:
            break
        }
    }

    private func settleSheet(to requested: Int, emitChange: Bool) {
        guard !snapPoints.isEmpty else { return }
        snapIndex = min(max(0, requested), snapPoints.count - 1)
        setNeedsLayout()
        layoutIfNeeded()
        if emitChange {
            emit?(.change, Data(String(snapIndex).utf8))
        }
    }

    private func nearestSnap(for translation: CGFloat) -> Int {
        guard !snapPoints.isEmpty else { return 0 }
        let viewport = max(1, bounds.height)
        return snapPoints.enumerated().min { lhs, rhs in
            let left = abs((activeSheetHeight - viewport * lhs.element / 100) - translation)
            let right = abs((activeSheetHeight - viewport * rhs.element / 100) - translation)
            return left < right
        }?.offset ?? snapIndex
    }

    private func panSlider(_ recognizer: UIPanGestureRecognizer) {
        guard bounds.width > 0, bounds.height > 0 else { return }
        let point = recognizer.location(in: self)
        let requested = sliderValue(at: point)
        if rangeEnabled {
            if recognizer.state == .began {
                activeRangeThumb = abs(requested - lowerValue) <= abs(requested - upperValue)
                    ? 0 : 1
            }
            if activeRangeThumb == 0 {
                lowerValue = min(requested, upperValue)
            } else {
                upperValue = max(requested, lowerValue)
                value = upperValue
            }
            setNeedsDisplay()
            accessibilityValue = "\(formatted(lowerValue)) to \(formatted(upperValue))"
            emit?(.change, rangePayload())
        } else {
            setRangeValue(requested, emitChange: true)
        }
        if recognizer.state == .ended || recognizer.state == .cancelled {
            emit?(.native, rangeEnabled ? rangePayload() : Data(formatted(value).utf8))
        }
    }

    private func sliderValue(at point: CGPoint) -> CGFloat {
        guard bounds.width > 0, bounds.height > 0 else { return value }
        var fraction: CGFloat
        if orientation == 2 {
            fraction = 1 - point.y / bounds.height
        } else {
            fraction = point.x / bounds.width
            if effectiveUserInterfaceLayoutDirection == .rightToLeft {
                fraction = 1 - fraction
            }
        }
        fraction = min(1, max(0, fraction))
        if reversed { fraction = 1 - fraction }

        return snapped(minimum + fraction * (maximum - minimum))
    }

    private func snapped(_ requested: CGFloat) -> CGFloat {
        clamped(round((requested - minimum) / step) * step + minimum)
    }

    private func rangePayload() -> Data {
        Data("[\(formatted(lowerValue)),\(formatted(upperValue))]".utf8)
    }

    private func setRangeValue(_ requested: CGFloat, emitChange: Bool) {
        value = snapped(requested)
        setNeedsDisplay()
        accessibilityValue = formatted(value)
        if emitChange {
            emit?(.change, Data(formatted(value).utf8))
        }
    }

    private func setOpen(_ requested: Bool, shouldEmit: Bool) {
        if !isControlled {
            isOpen = requested
            applyVisibility()
            setNeedsLayout()
        }
        if shouldEmit {
            emitMap([
                "action": .integer(requested ? PamHostAction.open.rawValue : PamHostAction.dismiss.rawValue),
                "open": .flag(requested),
            ])
        }
    }

    private func requestDismiss() {
        guard behavior.isOverlay, isOpen else { return }
        guard properties["dismissible"]?.pamFlag
            ?? properties["isDismissable"]?.pamFlag
            ?? true else { return }
        setOpen(false, shouldEmit: false)
        emitMap([
            "action": .integer(PamHostAction.dismiss.rawValue),
            "dismissed": .flag(true),
        ])
    }

    private func requestBackdropDismiss() {
        guard properties["closeOnOverlayClick"]?.pamFlag
            ?? properties["closeOnOverlay"]?.pamFlag
            ?? true else { return }
        requestDismiss()
    }

    override func accessibilityPerformEscape() -> Bool {
        guard behavior.isOverlay, isOpen,
              properties["isKeyboardDismissable"]?.pamFlag ?? true,
              properties["dismissible"]?.pamFlag
                ?? properties["isDismissable"]?.pamFlag
                ?? true else { return false }
        requestDismiss()
        return true
    }

    private func drawProgress(_ context: CGContext) {
        if behavior == .progress, properties["circular"]?.pamFlag == true {
            return
        }
        let fraction = maximum > minimum
            ? min(1, max(0, (value - minimum) / (maximum - minimum)))
            : 0
        let height = max(4, min(bounds.height, properties["thickness"]?.pamDecimal ?? 4))
        let track = CGRect(x: 0, y: (bounds.height - height) / 2, width: bounds.width, height: height)
        context.setFillColor(trackColor.cgColor)
        context.addPath(UIBezierPath(roundedRect: track, cornerRadius: height / 2).cgPath)
        context.fillPath()
        context.setFillColor(fillColor.cgColor)
        var fill = track
        fill.size.width *= properties["indeterminate"]?.pamFlag == true
            ? 0.34 : fraction
        let reverse = properties["reverse"]?.pamFlag == true
            || properties["reversed"]?.pamFlag == true
        if reverse != (effectiveUserInterfaceLayoutDirection == .rightToLeft) {
            fill.origin.x = bounds.width - fill.width
        }
        context.addPath(UIBezierPath(roundedRect: fill, cornerRadius: height / 2).cgPath)
        context.fillPath()

        if properties["striped"]?.pamFlag == true, fill.width > 0 {
            context.saveGState()
            context.clip(to: fill)
            context.setStrokeColor(UIColor.white.withAlphaComponent(0.28).cgColor)
            context.setLineWidth(max(2, height / 2))
            var x = fill.minX - height
            while x < fill.maxX + height {
                context.move(to: CGPoint(x: x, y: fill.maxY))
                context.addLine(to: CGPoint(x: x + height, y: fill.minY))
                x += height
            }
            context.strokePath()
            context.restoreGState()
        }

        if properties["stream"]?.pamFlag == true {
            context.saveGState()
            context.setStrokeColor(fillColor.withAlphaComponent(0.38).cgColor)
            context.setLineWidth(max(1, height / 3))
            context.setLineDash(phase: 0, lengths: [4, 4])
            let y = track.maxY + 3
            context.move(to: CGPoint(x: track.minX, y: y))
            context.addLine(to: CGPoint(x: track.maxX, y: y))
            context.strokePath()
            context.restoreGState()
        }
    }

    private func applyProgressState() {
        guard behavior == .progress, properties["circular"]?.pamFlag == true else {
            removeProgressLayers()
            return
        }

        let track = progressTrackLayer ?? CAShapeLayer()
        let fill = progressFillLayer ?? CAShapeLayer()
        if progressTrackLayer == nil {
            track.fillColor = UIColor.clear.cgColor
            track.lineCap = .round
            layer.addSublayer(track)
            progressTrackLayer = track
        }
        if progressFillLayer == nil {
            fill.fillColor = UIColor.clear.cgColor
            fill.lineCap = .round
            layer.addSublayer(fill)
            progressFillLayer = fill
        }

        track.strokeColor = trackColor.cgColor
        fill.strokeColor = fillColor.cgColor
        let fraction = maximum > minimum
            ? max(0, min(1, (value - minimum) / (maximum - minimum)))
            : 0
        let indeterminate = properties["indeterminate"]?.pamFlag == true
        fill.strokeStart = 0
        fill.strokeEnd = indeterminate ? 0.72 : fraction

        if indeterminate && animationsEnabled {
            if fill.animation(forKey: "pam.progress.rotation") == nil {
                let rotation = CABasicAnimation(keyPath: "transform.rotation.z")
                rotation.fromValue = 0
                rotation.toValue = CGFloat.pi * 2
                rotation.duration = 1.333
                rotation.repeatCount = .infinity
                rotation.timingFunction = CAMediaTimingFunction(name: .linear)
                rotation.isRemovedOnCompletion = false
                fill.add(rotation, forKey: "pam.progress.rotation")
            }
        } else {
            fill.removeAnimation(forKey: "pam.progress.rotation")
        }
        layoutProgressLayers()
    }

    private func layoutProgressLayers() {
        guard let track = progressTrackLayer, let fill = progressFillLayer else {
            return
        }
        let thickness = max(1, min(bounds.width, properties["thickness"]?.pamDecimal ?? 4))
        let side = min(bounds.width, bounds.height)
        let radius = max(0, (side - thickness) / 2)
        let path = UIBezierPath(
            arcCenter: CGPoint(x: bounds.midX, y: bounds.midY),
            radius: radius,
            startAngle: -.pi / 2,
            endAngle: .pi * 1.5,
            clockwise: true
        ).cgPath
        CATransaction.begin()
        CATransaction.setDisableActions(true)
        track.frame = bounds
        fill.frame = bounds
        track.lineWidth = thickness
        fill.lineWidth = thickness
        track.path = path
        fill.path = path
        CATransaction.commit()
    }

    private func removeProgressLayers() {
        progressTrackLayer?.removeAllAnimations()
        progressFillLayer?.removeAllAnimations()
        progressTrackLayer?.removeFromSuperlayer()
        progressFillLayer?.removeFromSuperlayer()
        progressTrackLayer = nil
        progressFillLayer = nil
    }

    private func drawSlider(_ context: CGContext) {
        let rating = properties["rating"]?.pamFlag == true
        subviews.forEach { $0.isHidden = rating }
        if rating {
            drawRating(context)
            return
        }
        let trackInset = orientation == 2
            ? sliderThumbHeight / 2
            : sliderThumbWidth / 2
        let track = orientation == 2
            ? CGRect(
                x: bounds.midX - sliderTrackThickness / 2,
                y: trackInset,
                width: sliderTrackThickness,
                height: max(1, bounds.height - sliderThumbHeight)
            )
            : CGRect(
                x: trackInset,
                y: bounds.midY - sliderTrackThickness / 2,
                width: max(1, bounds.width - sliderThumbWidth),
                height: sliderTrackThickness
            )
        context.setFillColor(trackColor.cgColor)
        UIBezierPath(
            roundedRect: track,
            cornerRadius: sliderTrackThickness / 2
        ).fill()

        let lower = sliderPoint(rangeEnabled ? lowerValue : minimum, in: track)
        let upper = sliderPoint(value, in: track)
        context.setStrokeColor(fillColor.cgColor)
        context.setLineWidth(sliderTrackThickness)
        context.setLineCap(.round)
        context.move(to: lower)
        context.addLine(to: upper)
        context.strokePath()

        if showSliderTicks && sliderTickSize > 0 {
            let intervals = min(100, max(1, Int(round((maximum - minimum) / step))))
            for index in 0...intervals {
                let tickValue = minimum
                    + (maximum - minimum) * CGFloat(index) / CGFloat(intervals)
                let point = sliderPoint(tickValue, in: track)
                context.setFillColor((tickValue <= value
                    ? sliderActiveTickColor : sliderInactiveTickColor).cgColor)
                context.fillEllipse(in: CGRect(
                    x: point.x - sliderTickSize / 2,
                    y: point.y - sliderTickSize / 2,
                    width: sliderTickSize,
                    height: sliderTickSize
                ))
            }
        }

        if sliderStopIndicatorSize > 0 {
            context.setFillColor(sliderInactiveTickColor.cgColor)
            for endpoint in [minimum, maximum] {
                let point = sliderPoint(endpoint, in: track)
                context.fillEllipse(in: CGRect(
                    x: point.x - sliderStopIndicatorSize / 2,
                    y: point.y - sliderStopIndicatorSize / 2,
                    width: sliderStopIndicatorSize,
                    height: sliderStopIndicatorSize
                ))
            }
        }

        if !sliderTickLabels.isEmpty {
            let last = max(1, sliderTickLabels.count - 1)
            let attributes: [NSAttributedString.Key: Any] = [
                .font: UIFont.preferredFont(forTextStyle: .caption2),
                .foregroundColor: sliderTickLabelColor,
            ]
            for (index, label) in sliderTickLabels.enumerated() {
                let tickValue = minimum
                    + (maximum - minimum) * CGFloat(index) / CGFloat(last)
                let point = sliderPoint(tickValue, in: track)
                let size = (label as NSString).size(withAttributes: attributes)
                let origin = orientation == 2
                    ? CGPoint(x: track.maxX + 8, y: point.y - size.height / 2)
                    : CGPoint(x: point.x - size.width / 2, y: track.maxY + 8)
                (label as NSString).draw(at: origin, withAttributes: attributes)
            }
        }

        context.setFillColor(fillColor.cgColor)
        let thumbValues = rangeEnabled ? [lowerValue, upperValue] : [value]
        for current in thumbValues {
            let point = sliderPoint(current, in: track)
            let thumb = CGRect(
                x: point.x - sliderThumbWidth / 2,
                y: point.y - sliderThumbHeight / 2,
                width: sliderThumbWidth,
                height: sliderThumbHeight
            )
            UIBezierPath(
                roundedRect: thumb,
                cornerRadius: min(sliderThumbWidth, sliderThumbHeight) / 2
            ).fill()
            if showThumbLabel {
                drawThumbLabel(formatted(current), at: point, context: context)
            }
        }
    }

    private func drawRating(_ context: CGContext) {
        let length = min(
            20,
            max(1, properties["length"]?.pamInteger ?? 5)
        )
        let stars = String(repeating: "\u{2605}", count: length)
        let font = UIFont.systemFont(
            ofSize: min(bounds.height * 0.78, bounds.width / CGFloat(length) * 0.88),
            weight: .regular
        )
        let paragraph = NSMutableParagraphStyle()
        paragraph.alignment = .left
        let baseAttributes: [NSAttributedString.Key: Any] = [
            .font: font,
            .foregroundColor: trackColor,
            .paragraphStyle: paragraph,
        ]
        let textSize = (stars as NSString).size(withAttributes: baseAttributes)
        let origin = CGPoint(
            x: (bounds.width - textSize.width) / 2,
            y: (bounds.height - textSize.height) / 2
        )
        (stars as NSString).draw(at: origin, withAttributes: baseAttributes)

        let fraction = maximum > minimum
            ? min(1, max(0, (value - minimum) / (maximum - minimum)))
            : 0
        let fillFromEnd = (properties["reverse"]?.pamFlag == true)
            != (effectiveUserInterfaceLayoutDirection == .rightToLeft)
        let clip = CGRect(
            x: fillFromEnd
                ? origin.x + textSize.width * (1 - fraction)
                : origin.x,
            y: origin.y,
            width: textSize.width * fraction,
            height: textSize.height
        )
        context.saveGState()
        context.clip(to: clip)
        var fillAttributes = baseAttributes
        fillAttributes[.foregroundColor] = fillColor
        (stars as NSString).draw(at: origin, withAttributes: fillAttributes)
        context.restoreGState()
    }

    private func sliderPoint(_ current: CGFloat, in track: CGRect) -> CGPoint {
        var fraction = maximum > minimum
            ? min(1, max(0, (current - minimum) / (maximum - minimum)))
            : 0
        if reversed { fraction = 1 - fraction }
        if orientation == 2 {
            return CGPoint(x: track.midX, y: track.maxY - track.height * fraction)
        }
        if effectiveUserInterfaceLayoutDirection == .rightToLeft {
            fraction = 1 - fraction
        }
        return CGPoint(x: track.minX + track.width * fraction, y: track.midY)
    }

    private func drawThumbLabel(
        _ text: String,
        at point: CGPoint,
        context: CGContext
    ) {
        let attributes: [NSAttributedString.Key: Any] = [
            .font: UIFont.systemFont(ofSize: 12, weight: .semibold),
            .foregroundColor: sliderThumbLabelTextColor,
        ]
        let size = (text as NSString).size(withAttributes: attributes)
        let width = max(32, size.width + 16)
        let bubble = orientation == 2
            ? CGRect(x: point.x + 16, y: point.y - 14, width: width, height: 28)
            : CGRect(x: point.x - width / 2, y: point.y - 40, width: width, height: 28)
        context.setFillColor(fillColor.cgColor)
        UIBezierPath(roundedRect: bubble, cornerRadius: 6).fill()
        (text as NSString).draw(
            at: CGPoint(
                x: bubble.midX - size.width / 2,
                y: bubble.midY - size.height / 2
            ),
            withAttributes: attributes
        )
    }

    private func drawSwitch(_ context: CGContext) {
        let track = descendant(tag: "pam:switch-track").map {
            $0.convert($0.bounds, to: self)
        } ?? CGRect(
            x: max(0, (bounds.width - 52) / 2),
            y: max(0, (bounds.height - 32) / 2),
            width: 52,
            height: 32
        )
        context.setFillColor((isChecked ? fillColor : trackColor).cgColor)
        context.fillEllipse(in: track)
        if !isChecked {
            context.setStrokeColor(switchTrackOutlineColor.cgColor)
            context.setLineWidth(2)
            context.strokeEllipse(in: track.insetBy(dx: 1, dy: 1))
        }
        let diameter: CGFloat = isChecked ? 24 : 16
        let left = track.minX
        let right = track.maxX - diameter
        let checkedX = effectiveUserInterfaceLayoutDirection == .rightToLeft ? left : right
        let uncheckedX = effectiveUserInterfaceLayoutDirection == .rightToLeft ? right : left
        context.setFillColor(
            (isChecked ? switchActiveThumbColor : switchThumbColor).cgColor
        )
        context.fillEllipse(in: CGRect(
            x: isChecked ? checkedX : uncheckedX,
            y: track.midY - diameter / 2,
            width: diameter,
            height: diameter
        ))
    }

    private func drawSelection(_ context: CGContext) {
        let size = min(24, min(bounds.width, bounds.height))
        let rect = CGRect(
            x: (bounds.width - size) / 2,
            y: (bounds.height - size) / 2,
            width: size,
            height: size
        )
        context.setStrokeColor((isChecked ? fillColor : trackColor).cgColor)
        context.setLineWidth(2)
        if behavior == .radio {
            context.strokeEllipse(in: rect.insetBy(dx: 1, dy: 1))
            if isChecked {
                context.setFillColor(fillColor.cgColor)
                context.fillEllipse(in: rect.insetBy(dx: 6, dy: 6))
            }
        } else {
            let path = UIBezierPath(roundedRect: rect, cornerRadius: 2)
            context.addPath(path.cgPath)
            if isChecked {
                context.setFillColor(fillColor.cgColor)
                context.fillPath()
            } else {
                context.strokePath()
            }
        }
    }

    private func drawSkeleton(_ context: CGContext) {
        context.setFillColor(trackColor.withAlphaComponent(0.52).cgColor)
        context.fill(bounds)
    }

    func configuredCalendar() -> Calendar {
        var calendar = Calendar(identifier: .gregorian)
        calendar.locale = Locale(
            identifier: properties["locale"]?.pamText ?? Locale.current.identifier
        )
        calendar.firstWeekday = min(6, max(0,
            properties["firstDayOfWeek"]?.pamInteger ?? 0
        )) + 1
        return calendar
    }

    private func calendarFirstDate() -> Date {
        configuredCalendar().date(from: DateComponents(
            year: calendarYear, month: calendarMonth, day: 1
        )) ?? Date()
    }

    @discardableResult
    func handleCalendarHeaderTap(at point: CGPoint) -> Bool {
        guard behavior == .calendar else { return false }
        let targets: [(String, CalendarHeaderAction)] = [
            ("pam:calendar-prev", .previous),
            ("pam:calendar-next", .next),
            ("pam:calendar-month-select", .month),
            ("pam:calendar-year-select", .year),
        ]
        for (tag, action) in targets {
            guard let target = descendant(tag: tag),
                  convert(target.bounds, from: target)
                    .insetBy(dx: -8, dy: -8).contains(point) else { continue }
            switch action {
            case .previous: _ = navigateCalendar(months: -1)
            case .next: _ = navigateCalendar(months: 1)
            case .month: presentCalendarSelector(month: true)
            case .year: presentCalendarSelector(month: false)
            }
            return true
        }
        return false
    }

    private func presentCalendarSelector(month: Bool) {
        guard isUserInteractionEnabled,
              !(properties["interactionDisabled"]?.pamFlag ?? false),
              !(properties["readOnly"]?.pamFlag ?? false),
              !(properties["disabled"]?.pamFlag ?? false),
              let presenter = nearestViewController() else { return }
        let values: [Int]
        let labels: [String]
        if month {
            values = Array(1...12)
            let formatter = DateFormatter()
            formatter.locale = configuredCalendar().locale
            labels = formatter.monthSymbols
        } else {
            let bounds = calendarMonthBounds()
            let lower = max(1, max((bounds.lowerBound - 1) / 12,
                calendarYear - 100))
            let upper = min(9_999, min((bounds.upperBound - 1) / 12,
                calendarYear + 100))
            guard lower <= upper else { return }
            values = Array(lower...upper)
            labels = values.map(String.init)
        }
        let selector = PamCalendarSelectorController(
            title: month ? "Select month" : "Select year",
            values: values,
            labels: labels,
            selected: month ? calendarMonth : calendarYear
        ) { [weak self] value in
            _ = self?.selectCalendarMonthOrYear(month: month, value: value)
        }
        let navigation = UINavigationController(rootViewController: selector)
        navigation.modalPresentationStyle = .formSheet
        navigation.preferredContentSize = CGSize(width: 320, height: 320)
        presenter.present(navigation, animated: true)
    }

    private func calendarGridFrame() -> CGRect {
        guard let grid = descendant(tag: "pam:calendar-grid") else { return bounds }
        return convert(grid.bounds, from: grid)
    }

    private func calendarRowCount(first: Date, offset: Int, calendar: Calendar) -> Int {
        if properties["fixedWeeks"]?.pamFlag == true { return 6 }
        let days = calendar.range(of: .day, in: .month, for: first)?.count ?? 31
        return min(6, max(4, (offset + days + 6) / 7))
    }

    private func calendarMonthBounds() -> ClosedRange<Int> {
        func monthKey(_ raw: String?) -> Int? {
            guard let raw, raw.count >= 7,
                  let year = Int(raw.prefix(4)),
                  let month = Int(raw.dropFirst(5).prefix(2)),
                  (1...12).contains(month) else { return nil }
            return year * 12 + month
        }
        let minimum = max(
            (properties["minYear"]?.pamInteger ?? 1) * 12 + 1,
            monthKey(properties["minDate"]?.pamText
                ?? properties["minimumDate"]?.pamText) ?? 13
        )
        let maximum = min(
            (properties["maxYear"]?.pamInteger ?? 9_999) * 12 + 12,
            monthKey(properties["maxDate"]?.pamText
                ?? properties["maximumDate"]?.pamText) ?? 119_999
        )
        return minimum...max(minimum, maximum)
    }

    @discardableResult
    func navigateCalendar(months: Int) -> Bool {
        guard behavior == .calendar,
              isUserInteractionEnabled,
              !(properties["interactionDisabled"]?.pamFlag ?? false),
              !(properties["readOnly"]?.pamFlag ?? false),
              !(properties["disabled"]?.pamFlag ?? false),
              let requested = configuredCalendar().date(
                  byAdding: .month, value: months, to: calendarFirstDate()
              ) else { return false }
        let components = configuredCalendar().dateComponents([.year, .month], from: requested)
        guard let year = components.year, let month = components.month,
              calendarMonthBounds().contains(year * 12 + month) else { return false }
        calendarYear = year
        calendarMonth = month
        updateCalendarTitle()
        updateCalendarAccessibilityElements()
        setNeedsDisplay()
        emitMap([
            "action": .integer(PamHostAction.navigate.rawValue),
            "year": .integer(Int64(year)),
            "month": .integer(Int64(month)),
        ])
        UIAccessibility.post(notification: .layoutChanged, argument: self)
        return true
    }

    @discardableResult
    func selectCalendarMonthOrYear(month: Bool, value: Int) -> Bool {
        guard behavior == .calendar,
              isUserInteractionEnabled,
              !(properties["interactionDisabled"]?.pamFlag ?? false),
              !(properties["readOnly"]?.pamFlag ?? false),
              !(properties["disabled"]?.pamFlag ?? false) else { return false }
        let selectedYear = month ? calendarYear : value
        let selectedMonth = month ? value : calendarMonth
        guard (1...12).contains(selectedMonth) else { return false }
        let bounds = calendarMonthBounds()
        let key = min(bounds.upperBound, max(bounds.lowerBound,
            selectedYear * 12 + selectedMonth))
        calendarYear = (key - 1) / 12
        calendarMonth = (key - 1) % 12 + 1
        updateCalendarTitle()
        updateCalendarAccessibilityElements()
        setNeedsDisplay()
        emitMap([
            "action": .integer(PamHostAction.navigate.rawValue),
            "year": .integer(Int64(calendarYear)),
            "month": .integer(Int64(calendarMonth)),
        ])
        UIAccessibility.post(notification: .layoutChanged, argument: self)
        return true
    }

    private func updateCalendarTitle() {
        let first = calendarFirstDate()
        let formatter = DateFormatter()
        formatter.calendar = configuredCalendar()
        formatter.locale = configuredCalendar().locale
        formatter.dateFormat = "LLLL"
        let month = formatter.string(from: first)
        let year = String(calendarYear)
        for (tag, label) in [
            ("pam:calendar-title", "\(month) \(year)"),
            ("pam:calendar-month-select", month),
            ("pam:calendar-year-select", year),
        ] {
            guard let view = descendant(tag: tag) else { continue }
            setFirstCalendarLabel(in: view, text: label)
            view.accessibilityValue = label
        }
    }

    private func setFirstCalendarLabel(in view: UIView, text: String) {
        if let label = view as? UILabel {
            label.text = text
            return
        }
        for child in view.subviews {
            if findFirstText(in: child) != nil {
                setFirstCalendarLabel(in: child, text: text)
                return
            }
        }
    }

    private func updateCalendarAccessibilityElements() {
        guard behavior == .calendar else { return }
        let grid = calendarGridFrame()
        guard grid.width > 0, grid.height > 0 else { return }
        let calendar = configuredCalendar()
        let first = calendarFirstDate()
        let offset = (calendar.component(.weekday, from: first)
            - calendar.firstWeekday + 7) % 7
        guard let firstVisible = calendar.date(
            byAdding: .day, value: -offset, to: first
        ) else { return }
        let rows = calendarRowCount(first: first, offset: offset, calendar: calendar)
        let showWeek = properties["showWeek"]?.pamFlag == true
        let showOutside = properties["showOutsideDays"]?.pamFlag ?? true
        let columns = showWeek ? 8 : 7
        let rtl = effectiveUserInterfaceLayoutDirection == .rightToLeft
        let cellWidth = grid.width / CGFloat(columns)
        let cellHeight = grid.height / CGFloat(rows)
        let dateFormatter = DateFormatter()
        dateFormatter.calendar = calendar
        dateFormatter.locale = Locale(identifier: "en_US_POSIX")
        dateFormatter.dateFormat = "yyyy-MM-dd"
        let labelFormatter = DateFormatter()
        labelFormatter.calendar = calendar
        labelFormatter.locale = calendar.locale
        labelFormatter.dateStyle = .full
        let disabled = Set(
            (properties["disabledDates"]?.pamText ?? "")
                .split(whereSeparator: \.isNewline).map(String.init)
        )
        let minimumDate = properties["minDate"]?.pamText
            ?? properties["minimumDate"]?.pamText
        let maximumDate = properties["maxDate"]?.pamText
            ?? properties["maximumDate"]?.pamText
        var elements: [Any] = subviews
        elements.reserveCapacity(subviews.count + rows * 7)
        for index in 0..<(rows * 7) {
            guard let date = calendar.date(
                byAdding: .day, value: index, to: firstVisible
            ) else { continue }
            let outside = !calendar.isDate(date, equalTo: first, toGranularity: .month)
            if outside && !showOutside { continue }
            let key = dateFormatter.string(from: date)
            let year = calendar.component(.year, from: date)
            let unavailable = disabled.contains(key)
                || minimumDate.map({ key < String($0.prefix(10)) }) == true
                || maximumDate.map({ key > String($0.prefix(10)) }) == true
                || (properties["minYear"]?.pamInteger).map({ year < $0 }) == true
                || (properties["maxYear"]?.pamInteger).map({ year > $0 }) == true
                || !(properties["enabled"]?.pamFlag ?? true)
                || (properties["disabled"]?.pamFlag ?? false)
                || (properties["isDisabled"]?.pamFlag ?? false)
                || (properties["interactionDisabled"]?.pamFlag ?? false)
                || (properties["readOnly"]?.pamFlag ?? false)
            let dayColumn = index % 7
            let visualColumn = (rtl ? 6 - dayColumn : dayColumn)
                + (showWeek && !rtl ? 1 : 0)
            let row = index / 7
            let element = PamCalendarDayAccessibilityElement(
                accessibilityContainer: self, host: self, dateKey: key
            )
            element.accessibilityIdentifier = "pam:calendar-day:\(key)"
            element.accessibilityLabel = labelFormatter.string(from: date)
            element.accessibilityFrameInContainerSpace = CGRect(
                x: grid.minX + CGFloat(visualColumn) * cellWidth,
                y: grid.minY + CGFloat(row) * cellHeight,
                width: cellWidth,
                height: cellHeight
            )
            var traits: UIAccessibilityTraits = [.button]
            let withinRange = calendarRangeFrom.flatMap { from in
                calendarRangeTo.map { to in key > from && key < to }
            } ?? false
            if calendarSelectedDates.contains(key)
                || key == calendarRangeFrom || key == calendarRangeTo || withinRange {
                traits.insert(.selected)
            }
            if unavailable { traits.insert(.notEnabled) }
            element.accessibilityTraits = traits
            elements.append(element)
        }
        accessibilityElements = elements
    }

    func selectCalendarDate(_ key: String) -> Bool {
        let formatter = DateFormatter()
        formatter.calendar = configuredCalendar()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        formatter.isLenient = false
        guard formatter.date(from: key) != nil,
              !(properties["readOnly"]?.pamFlag ?? false),
              !(properties["disabled"]?.pamFlag ?? false),
              !(properties["isDisabled"]?.pamFlag ?? false),
              !(properties["interactionDisabled"]?.pamFlag ?? false),
              (properties["enabled"]?.pamFlag ?? true),
              isUserInteractionEnabled else { return false }
        let disabled = Set(
            (properties["disabledDates"]?.pamText ?? "")
                .split(whereSeparator: \.isNewline).map(String.init)
        )
        let minDate = properties["minDate"]?.pamText
            ?? properties["minimumDate"]?.pamText
        let maxDate = properties["maxDate"]?.pamText
            ?? properties["maximumDate"]?.pamText
        let year = Int(key.prefix(4)) ?? 0
        if disabled.contains(key)
            || minDate.map({ key < String($0.prefix(10)) }) == true
            || maxDate.map({ key > String($0.prefix(10)) }) == true
            || (properties["minYear"]?.pamInteger).map({ year < $0 }) == true
            || (properties["maxYear"]?.pamInteger).map({ year > $0 }) == true {
            return false
        }

        let payload: String
        switch calendarMode {
        case 2:
            if !calendarSelectedDates.insert(key).inserted {
                calendarSelectedDates.remove(key)
            }
            payload = "M\n" + calendarSelectedDates.sorted().joined(separator: "\n")
            accessibilityValue = "\(calendarSelectedDates.count) dates selected"
        case 3:
            if calendarRangeFrom == nil || calendarRangeTo != nil {
                calendarRangeFrom = key
                calendarRangeTo = nil
            } else {
                let start = calendarRangeFrom ?? key
                calendarRangeFrom = min(start, key)
                calendarRangeTo = max(start, key)
            }
            payload = "R\n\(calendarRangeFrom ?? "")\n\(calendarRangeTo ?? "")"
            accessibilityValue = calendarRangeTo == nil
                ? "Range starts \(calendarRangeFrom ?? "")"
                : "\(calendarRangeFrom ?? "") to \(calendarRangeTo ?? "")"
        default:
            calendarSelectedDates = [key]
            payload = key
            accessibilityValue = key
        }
        let selectedMonth = Int(key.dropFirst(5).prefix(2)) ?? calendarMonth
        if year != calendarYear || selectedMonth != calendarMonth {
            calendarYear = year
            calendarMonth = selectedMonth
            updateCalendarTitle()
        }
        updateCalendarAccessibilityElements()
        emit?(.change, Data(payload.utf8))
        setNeedsDisplay()
        UIAccessibility.post(notification: .layoutChanged, argument: self)
        return true
    }

    func calendarDate(at point: CGPoint) -> String? {
        let grid = calendarGridFrame()
        guard grid.width > 0, grid.height > 0,
              grid.contains(point) else { return nil }
        let calendar = configuredCalendar()
        let showWeek = properties["showWeek"]?.pamFlag == true
        let columns = showWeek ? 8 : 7
        let column = Int((point.x - grid.minX) / (grid.width / CGFloat(columns)))
        let rtl = effectiveUserInterfaceLayoutDirection == .rightToLeft
        let dayColumn = rtl ? 6 - column : column - (showWeek ? 1 : 0)
        guard (0..<7).contains(dayColumn) else { return nil }
        let first = calendarFirstDate()
        let offset = (calendar.component(.weekday, from: first)
            - calendar.firstWeekday + 7) % 7
        let rows = calendarRowCount(first: first, offset: offset, calendar: calendar)
        let hasGrid = descendant(tag: "pam:calendar-grid") != nil
        let row = Int((point.y - grid.minY) /
            (grid.height / CGFloat(rows + (hasGrid ? 0 : 1)))) - (hasGrid ? 0 : 1)
        guard (0..<rows).contains(row), (0..<columns).contains(column) else { return nil }
        guard let firstVisible = calendar.date(byAdding: .day, value: -offset, to: first),
              let date = calendar.date(
                byAdding: .day, value: row * 7 + dayColumn, to: firstVisible
              ) else { return nil }
        if !(properties["showOutsideDays"]?.pamFlag ?? true)
            && !calendar.isDate(date, equalTo: first, toGranularity: .month) {
            return nil
        }
        let formatter = DateFormatter()
        formatter.calendar = calendar
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"
        return formatter.string(from: date)
    }

    private func drawCalendar(_ context: CGContext) {
        let calendar = configuredCalendar()
        let first = calendarFirstDate()
        let offset = (
            calendar.component(.weekday, from: first)
            - calendar.firstWeekday
            + 7
        ) % 7
        let firstVisible = calendar.date(
            byAdding: .day,
            value: -offset,
            to: first
        ) ?? first
        let showWeek = properties["showWeek"]?.pamFlag == true
        let showOutside = properties["showOutsideDays"]?.pamFlag ?? true
        let grid = calendarGridFrame()
        guard grid.width > 0, grid.height > 0 else { return }
        let hasGrid = descendant(tag: "pam:calendar-grid") != nil
        let rows = calendarRowCount(first: first, offset: offset, calendar: calendar)
        let columns = showWeek ? 8 : 7
        let cellWidth = grid.width / CGFloat(columns)
        let cellHeight = grid.height / CGFloat(rows + (hasGrid ? 0 : 1))
        let font = UIFont.preferredFont(forTextStyle: .body)
        let mutedAttributes: [NSAttributedString.Key: Any] = [
            .font: UIFont.preferredFont(forTextStyle: .caption1),
            .foregroundColor: UIColor.secondaryLabel,
        ]
        let normalAttributes: [NSAttributedString.Key: Any] = [
            .font: font,
            .foregroundColor: stateLayerColor,
        ]
        let selected = calendarSelectedDates
        let disabled = Set(
            (properties["disabledDates"]?.pamText ?? "")
                .split(whereSeparator: \.isNewline).map(String.init)
        )
        let formatter = DateFormatter()
        formatter.calendar = calendar
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"

        if !hasGrid {
            for dayIndex in 0..<7 {
                let dayColumn = effectiveUserInterfaceLayoutDirection == .rightToLeft
                    ? 6 - dayIndex : dayIndex
                let visualColumn = dayColumn + (
                    showWeek && effectiveUserInterfaceLayoutDirection != .rightToLeft
                        ? 1 : 0
                )
                let symbolIndex = (dayIndex + calendar.firstWeekday - 1) % 7
                let symbol = calendar.veryShortWeekdaySymbols[symbolIndex] as NSString
                let frame = CGRect(
                    x: grid.minX + CGFloat(visualColumn) * cellWidth,
                    y: grid.minY,
                    width: cellWidth,
                    height: cellHeight
                )
                let size = symbol.size(withAttributes: mutedAttributes)
                symbol.draw(
                    at: CGPoint(
                        x: frame.midX - size.width / 2,
                        y: frame.midY - size.height / 2
                    ),
                    withAttributes: mutedAttributes
                )
            }
        }

        for index in 0..<(rows * 7) {
            guard let date = calendar.date(
                byAdding: .day,
                value: index,
                to: firstVisible
            ) else { continue }
            let dayColumn = index % 7
            let logicalColumn = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? 6 - dayColumn : dayColumn
            let visualColumn = logicalColumn + (
                showWeek && effectiveUserInterfaceLayoutDirection != .rightToLeft
                    ? 1 : 0
            )
            let row = index / 7 + (hasGrid ? 0 : 1)
            let frame = CGRect(
                x: grid.minX + CGFloat(visualColumn) * cellWidth,
                y: grid.minY + CGFloat(row) * cellHeight,
                width: cellWidth,
                height: cellHeight
            )
            let outside = !calendar.isDate(date, equalTo: first, toGranularity: .month)
            if outside && !showOutside { continue }
            let text = String(calendar.component(.day, from: date)) as NSString
            let dateKey = formatter.string(from: date)
            let withinRange = calendarRangeFrom.flatMap { from in
                calendarRangeTo.map { to in dateKey > from && dateKey < to }
            } ?? false
            let isSelected = selected.contains(dateKey)
                || dateKey == calendarRangeFrom
                || dateKey == calendarRangeTo
                || withinRange
            if isSelected {
                context.setFillColor(fillColor.cgColor)
                context.fillEllipse(in: frame.insetBy(
                    dx: cellWidth * 0.18,
                    dy: max(2, (cellHeight - cellWidth * 0.64) / 2)
                ))
            }
            let unavailable = disabled.contains(dateKey)
                || properties["minDate"]?.pamText.map({ dateKey < String($0.prefix(10)) }) == true
                || properties["maxDate"]?.pamText.map({ dateKey > String($0.prefix(10)) }) == true
            var attributes = outside || unavailable ? mutedAttributes : normalAttributes
            if isSelected {
                attributes[.foregroundColor] = selectedForegroundColor
            }
            let size = text.size(withAttributes: attributes)
            text.draw(
                at: CGPoint(x: frame.midX - size.width / 2, y: frame.midY - size.height / 2),
                withAttributes: attributes
            )
        }

        if showWeek {
            for row in 0..<rows {
                guard let date = calendar.date(
                    byAdding: .day,
                    value: row * 7,
                    to: firstVisible
                ) else { continue }
                let week = calendar.component(.weekOfYear, from: date)
                let column = effectiveUserInterfaceLayoutDirection == .rightToLeft
                    ? 7 : 0
                let frame = CGRect(
                    x: grid.minX + CGFloat(column) * cellWidth,
                    y: grid.minY + CGFloat(row + (hasGrid ? 0 : 1)) * cellHeight,
                    width: cellWidth,
                    height: cellHeight
                )
                let text = String(week) as NSString
                let size = text.size(withAttributes: mutedAttributes)
                text.draw(
                    at: CGPoint(
                        x: frame.midX - size.width / 2,
                        y: frame.midY - size.height / 2
                    ),
                    withAttributes: mutedAttributes
                )
            }
        }
    }

    private func drawSparkline(_ context: CGContext) {
        let source = properties["values"]?.pamText
            ?? properties["value"]?.pamText
            ?? ""
        let points = source
            .split(whereSeparator: { $0 == "," || $0 == "\n" || $0 == ";" || $0 == " " })
            .compactMap { Double($0).map { CGFloat($0) } }
        guard points.count > 1, bounds.width > 0, bounds.height > 0,
              let low = points.min(), let high = points.max() else { return }
        let spread = max(0.000_001, high - low)
        let lineWidth = properties["lineWidth"]?.pamDecimal ?? 2.5
        let inset = lineWidth / 2
        let drawableWidth = max(1, bounds.width - lineWidth)
        let drawableHeight = max(1, bounds.height - lineWidth)
        let coordinates = points.enumerated().map { index, point -> CGPoint in
            let logicalX = inset + drawableWidth * CGFloat(index) / CGFloat(points.count - 1)
            let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? bounds.width - logicalX : logicalX
            let y = inset + drawableHeight - (point - low) / spread * drawableHeight
            return CGPoint(x: x, y: y)
        }
        let type = properties["type"]?.pamText?.lowercased() ?? ""
        if type == "bar" || type == "bars" {
            let slot = drawableWidth / CGFloat(points.count)
            let barWidth = max(3, slot * 0.58)
            let radius = min(6, barWidth / 2)
            fillColor.setFill()
            for (index, point) in coordinates.enumerated() {
                let logicalX = inset + (CGFloat(index) + 0.5) * slot
                let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                    ? bounds.width - logicalX : logicalX
                UIBezierPath(
                    roundedRect: CGRect(
                        x: x - barWidth / 2,
                        y: point.y,
                        width: barWidth,
                        height: max(0, bounds.height - inset - point.y)
                    ),
                    cornerRadius: radius
                ).fill()
            }
            if points.indices.contains(sparklineSelectedIndex) {
                let logicalX = inset + (CGFloat(sparklineSelectedIndex) + 0.5) * slot
                let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                    ? bounds.width - logicalX : logicalX
                let point = coordinates[sparklineSelectedIndex]
                fillColor.withAlphaComponent(0.45).setStroke()
                let ring = UIBezierPath(
                    ovalIn: CGRect(x: x - 8, y: point.y - 8, width: 16, height: 16)
                )
                ring.lineWidth = 2
                ring.stroke()
            }
            return
        }
        let path = UIBezierPath()
        path.lineWidth = lineWidth
        path.lineCapStyle = .round
        path.lineJoinStyle = .round
        let smooth = properties["smooth"]?.pamFlag == true
        for (index, point) in coordinates.enumerated() {
            if index == 0 {
                path.move(to: point)
            } else if smooth {
                let previous = coordinates[index - 1]
                let controlX = (previous.x + point.x) / 2
                path.addCurve(
                    to: point,
                    controlPoint1: CGPoint(x: controlX, y: previous.y),
                    controlPoint2: CGPoint(x: controlX, y: point.y)
                )
            } else {
                path.addLine(to: point)
            }
        }
        if properties["fill"]?.pamFlag == true, let first = coordinates.first,
           let last = coordinates.last, let fillPath = path.copy() as? UIBezierPath {
            fillPath.addLine(to: CGPoint(x: last.x, y: bounds.height - inset))
            fillPath.addLine(to: CGPoint(x: first.x, y: bounds.height - inset))
            fillPath.close()
            fillColor.withAlphaComponent(0.16).setFill()
            fillPath.fill()
        }
        fillColor.setStroke()
        path.stroke()
        if properties["showPoints"]?.pamFlag == true {
            let selectedIndex = sparklineSelectedIndex >= 0
                ? sparklineSelectedIndex
                : (properties["selectedIndex"]?.pamInteger ?? -1)
            fillColor.setFill()
            for (index, point) in coordinates.enumerated() {
                let radius: CGFloat = index == selectedIndex ? 5 : 3
                UIBezierPath(
                    ovalIn: CGRect(
                        x: point.x - radius,
                        y: point.y - radius,
                        width: radius * 2,
                        height: radius * 2
                    )
                ).fill()
                if index == selectedIndex {
                    fillColor.withAlphaComponent(0.35).setStroke()
                    let ring = UIBezierPath(
                        ovalIn: CGRect(x: point.x - 8, y: point.y - 8, width: 16, height: 16)
                    )
                    ring.lineWidth = 2
                    ring.stroke()
                }
            }
        }
    }

    private var sparklineValues: [CGFloat] {
        let source = properties["values"]?.pamText
            ?? properties["value"]?.pamText
            ?? ""
        return source
            .split(whereSeparator: { $0 == "," || $0 == "\n" || $0 == ";" || $0 == " " })
            .compactMap { point -> CGFloat? in
                guard let value = Double(String(point)) else { return nil }
                return CGFloat(value)
            }
    }

    private var sparklineAccessibilityValue: String? {
        let points = sparklineValues
        guard points.indices.contains(sparklineSelectedIndex) else { return nil }
        return "Point \(sparklineSelectedIndex + 1) of \(points.count), \(formatted(points[sparklineSelectedIndex]))"
    }

    private func panSparkline(_ recognizer: UIPanGestureRecognizer) {
        guard properties["interactive"]?.pamFlag == true,
              properties["enabled"]?.pamFlag ?? true else { return }
        switch recognizer.state {
        case .began, .changed:
            updateSparklineSelection(at: recognizer.location(in: self).x, emitChange: false)
        case .ended:
            updateSparklineSelection(at: recognizer.location(in: self).x, emitChange: true)
        default:
            break
        }
    }

    private func updateSparklineSelection(at x: CGFloat, emitChange: Bool) {
        let points = sparklineValues
        guard !points.isEmpty, bounds.width > 0 else { return }
        let logicalX = effectiveUserInterfaceLayoutDirection == .rightToLeft
            ? bounds.width - x : x
        let ratio = min(1, max(0, logicalX / bounds.width))
        let index = min(points.count - 1, max(0, Int(round(ratio * CGFloat(points.count - 1)))))
        selectSparklineIndex(index, emitChange: emitChange)
    }

    private func selectSparklineIndex(_ requestedIndex: Int, emitChange: Bool) {
        let points = sparklineValues
        guard !points.isEmpty else { return }
        let index = min(points.count - 1, max(0, requestedIndex))
        if sparklineSelectedIndex != index {
            sparklineSelectedIndex = index
            accessibilityValue = sparklineAccessibilityValue
            setNeedsDisplay()
        }
        guard emitChange,
              let payload = try? JSONSerialization.data(withJSONObject: [
                "index": index,
                "value": Double(points[index]),
              ]) else { return }
        emit?(.change, payload)
        UIAccessibility.post(notification: .announcement, argument: sparklineAccessibilityValue)
    }

    private func applySparklineAutoDraw() {
        guard properties["autoDraw"]?.pamFlag == true else {
            sparklineAutoDrawApplied = false
            return
        }
        guard !sparklineAutoDrawApplied else { return }
        sparklineAutoDrawApplied = true
        guard animationsEnabled else { return }
        alpha = 0
        transform = CGAffineTransform(scaleX: 0.15, y: 1)
        UIView.animate(
            withDuration: min(
                4,
                max(0.12, (properties["autoDrawDuration"]?.pamDecimal ?? 800) / 1_000)
            ),
            delay: 0,
            options: [.curveEaseOut, .allowUserInteraction, .beginFromCurrentState]
        ) {
            self.alpha = 1
            self.transform = .identity
        }
    }

    private func animateStateLayer(to alpha: CGFloat, duration: TimeInterval) {
        pressAnimator?.stopAnimation(true)
        guard animationsEnabled else {
            self.alpha = alpha
            return
        }
        pressAnimator = UIViewPropertyAnimator(
            duration: duration,
            curve: .easeOut
        ) {
            self.alpha = alpha
        }
        pressAnimator?.startAnimation()
    }

    private func overlayContent() -> UIView? {
        anchoredPortalContent ?? descendant(tag: "pam:overlay-content")
    }

    private func menuItems() -> [PamMobileUiHost] {
        allDescendantHosts(in: overlayContent() ?? self).filter { $0.behavior == .menuItem }
    }

    private func allDescendantHosts(in root: UIView) -> [PamMobileUiHost] {
        var result: [PamMobileUiHost] = []
        func walk(_ view: UIView) {
            for child in view.subviews {
                if let host = child as? PamMobileUiHost { result.append(host) }
                walk(child)
            }
        }
        walk(root)
        return result
    }

    private func presentAnchoredPortalContent() {
        if anchoredPortalContent != nil { return }
        guard let window,
              let content = descendant(tag: "pam:overlay-content"),
              let trigger = descendant(tag: "pam:overlay-trigger"),
              let parent = content.superview else {
            return
        }
        let triggerFrame = trigger.convert(trigger.bounds, to: window)
        let sourceFrame = content.convert(content.bounds, to: window)
        var size = sourceFrame.size
        if size.width <= 0 || size.height <= 0 {
            size = content.sizeThatFits(CGSize(
                width: max(1, window.bounds.width - 16),
                height: max(1, window.bounds.height - 16)
            ))
        }
        size.width = min(max(1, size.width), max(1, window.bounds.width - 16))
        size.height = min(max(1, size.height), max(1, window.bounds.height - 16))

        anchoredPortalParent = parent
        anchoredPortalContent = content
        anchoredPortalIndex = parent.subviews.firstIndex(of: content) ?? parent.subviews.count
        anchoredPortalFrame = content.frame
        for host in allDescendantHosts(in: content) {
            host.anchoredOverlayOwner = self
        }

        let catcher = UIControl(frame: window.bounds)
        catcher.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        catcher.backgroundColor = .clear
        catcher.isAccessibilityElement = false
        catcher.accessibilityElementsHidden = true
        catcher.addTarget(
            self,
            action: #selector(onAnchoredPortalBackdrop),
            for: .touchUpInside
        )
        window.addSubview(catcher)
        anchoredPortalCatcher = catcher

        content.removeFromSuperview()
        window.addSubview(content)
        let safeFrame = window.safeAreaLayoutGuide.layoutFrame.insetBy(dx: 8, dy: 8)
        let gap = CGFloat(properties["offset"]?.pamDecimal ?? 8)
        let placement = Int(properties["placement"]?.pamInteger ?? 4)
        let rtl = effectiveUserInterfaceLayoutDirection == .rightToLeft
        let startX = rtl ? triggerFrame.maxX - size.width : triggerFrame.minX
        let endX = rtl ? triggerFrame.minX : triggerFrame.maxX - size.width
        let centeredX = triggerFrame.midX - size.width / 2
        let centeredY = triggerFrame.midY - size.height / 2
        var x: CGFloat
        var y: CGFloat
        switch placement {
        case 1:
            x = centeredX
            y = triggerFrame.minY - size.height - gap
        case 2:
            x = startX
            y = triggerFrame.minY - size.height - gap
        case 3:
            x = endX
            y = triggerFrame.minY - size.height - gap
        case 5:
            x = startX
            y = triggerFrame.maxY + gap
        case 6:
            x = endX
            y = triggerFrame.maxY + gap
        case 7:
            x = triggerFrame.minX - size.width - gap
            y = centeredY
        case 8:
            x = triggerFrame.minX - size.width - gap
            y = triggerFrame.minY
        case 9:
            x = triggerFrame.minX - size.width - gap
            y = triggerFrame.maxY - size.height
        case 10:
            x = triggerFrame.maxX + gap
            y = centeredY
        case 11:
            x = triggerFrame.maxX + gap
            y = triggerFrame.minY
        case 12:
            x = triggerFrame.maxX + gap
            y = triggerFrame.maxY - size.height
        case 13:
            x = safeFrame.midX - size.width / 2
            y = safeFrame.midY - size.height / 2
        default:
            x = centeredX
            y = triggerFrame.maxY + gap
        }
        x = min(max(safeFrame.minX, x), max(safeFrame.minX, safeFrame.maxX - size.width))
        y = min(max(safeFrame.minY, y), max(safeFrame.minY, safeFrame.maxY - size.height))
        content.frame = CGRect(origin: CGPoint(x: x, y: y), size: size)
        content.layer.zPosition = 1
        if animationsEnabled {
            content.alpha = 0
            content.transform = CGAffineTransform(scaleX: 0.96, y: 0.96)
            UIView.animate(
                withDuration: 0.18,
                delay: 0,
                options: [.beginFromCurrentState, .curveEaseOut]
            ) {
                content.alpha = 1
                content.transform = .identity
            }
        } else {
            content.alpha = 1
            content.transform = .identity
        }
    }

    @objc
    private func onAnchoredPortalBackdrop() {
        requestBackdropDismiss()
    }

    private func restoreAnchoredPortalContent() {
        anchoredPortalCatcher?.removeFromSuperview()
        guard let content = anchoredPortalContent else {
            anchoredPortalCatcher = nil
            return
        }
        content.removeFromSuperview()
        content.layer.zPosition = 0
        for host in allDescendantHosts(in: content) {
            if host.anchoredOverlayOwner === self { host.anchoredOverlayOwner = nil }
        }
        if let parent = anchoredPortalParent {
            parent.insertSubview(
                content,
                at: min(max(0, anchoredPortalIndex), parent.subviews.count)
            )
            content.frame = anchoredPortalFrame
        }
        anchoredPortalContent = nil
        anchoredPortalParent = nil
        anchoredPortalCatcher = nil
        anchoredPortalIndex = 0
        anchoredPortalFrame = .zero
    }

    private func overlayBackdrop() -> UIView? {
        descendant(prefix: "pam:overlay-backdrop")
    }

    private func descendant(tag: String) -> UIView? {
        descendant(prefix: tag).flatMap {
            $0.accessibilityIdentifier == tag ? $0 : nil
        }
    }

    private func descendant(prefix: String) -> UIView? {
        if accessibilityIdentifier?.hasPrefix(prefix) == true { return self }
        for child in subviews {
            if child.accessibilityIdentifier?.hasPrefix(prefix) == true { return child }
            if let host = child as? PamMobileUiHost,
               let match = host.descendant(prefix: prefix) {
                return match
            }
            if let match = find(in: child, prefix: prefix) { return match }
        }
        return nil
    }

    private func descendants(prefix: String) -> [UIView] {
        var result: [UIView] = []
        func walk(_ view: UIView) {
            if view.accessibilityIdentifier?.hasPrefix(prefix) == true {
                result.append(view)
            }
            view.subviews.forEach(walk)
        }
        walk(self)
        return result
    }

    private func allDescendants() -> [UIView] {
        var result: [UIView] = []
        func walk(_ view: UIView) {
            for child in view.subviews {
                result.append(child)
                walk(child)
            }
        }
        walk(self)
        return result
    }

    private func findFirstText(in view: UIView) -> String? {
        if let label = view as? UILabel, let text = label.text, !text.isEmpty {
            return text
        }
        for child in view.subviews {
            if let text = findFirstText(in: child) { return text }
        }
        return nil
    }

    private func nearestViewController() -> UIViewController? {
        var responder: UIResponder? = self
        while let current = responder {
            if let controller = current as? UIViewController { return controller }
            responder = current.next
        }
        return window?.rootViewController
    }

    private func find(in view: UIView, prefix: String) -> UIView? {
        for child in view.subviews {
            if child.accessibilityIdentifier?.hasPrefix(prefix) == true { return child }
            if let match = find(in: child, prefix: prefix) { return match }
        }
        return nil
    }

    private func ancestor(where predicate: (PamMobileUiHost) -> Bool) -> PamMobileUiHost? {
        var candidate = superview
        while let view = candidate {
            if let host = view as? PamMobileUiHost, predicate(host) { return host }
            candidate = view.superview
        }
        return nil
    }

    private func sheetAncestor() -> PamMobileUiHost? {
        ancestor { $0.behavior == .bottomSheet }
    }

    private func overlayAncestor() -> PamMobileUiHost? {
        ancestor { $0.behavior.isOverlay } ?? anchoredOverlayOwner
    }

    private func clamped(_ candidate: CGFloat) -> CGFloat {
        min(maximum, max(minimum, candidate))
    }

    private func parseNumbers(_ source: String?) -> [CGFloat] {
        source?
            .split(whereSeparator: { $0 == "," || $0 == "\n" || $0 == ";" || $0 == " " })
            .compactMap { Double($0).map { CGFloat($0) } }
            .map { min(100, max(1, $0)) } ?? []
    }

    private func formatted(_ number: CGFloat) -> String {
        number.rounded() == number
            ? String(Int(number))
            : String(format: "%.4g", Double(number))
    }

    private func color(_ value: Int?, fallback: UIColor) -> UIColor {
        guard let value else { return fallback }
        let bits = UInt32(truncatingIfNeeded: value)
        return UIColor(
            red: CGFloat((bits >> 16) & 0xff) / 255,
            green: CGFloat((bits >> 8) & 0xff) / 255,
            blue: CGFloat(bits & 0xff) / 255,
            alpha: CGFloat((bits >> 24) & 0xff) / 255
        )
    }

    private func emitMap(_ values: [String: WireValue]) {
        guard let payload = try? WireMap.encode(values) else { return }
        emit?(.native, payload)
    }

    func gestureRecognizer(
        _ gestureRecognizer: UIGestureRecognizer,
        shouldReceive touch: UITouch
    ) -> Bool {
        guard behavior.isOverlay,
              gestureRecognizer is UITapGestureRecognizer,
              let content = overlayContent() else { return true }
        return !content.convert(content.bounds, to: self).contains(touch.location(in: self))
    }

    func gestureRecognizer(
        _ gestureRecognizer: UIGestureRecognizer,
        shouldRecognizeSimultaneouslyWith otherGestureRecognizer: UIGestureRecognizer
    ) -> Bool {
        behavior == .bottomSheet || behavior == .slider || behavior == .sparkline
    }
}

private final class PamCalendarSelectorController: UIViewController,
    UIPickerViewDataSource, UIPickerViewDelegate {
    private let values: [Int]
    private let labels: [String]
    private let selected: Int
    private let onSelect: (Int) -> Void
    private let picker = UIPickerView()

    init(title: String, values: [Int], labels: [String], selected: Int,
         onSelect: @escaping (Int) -> Void) {
        self.values = values
        self.labels = labels
        self.selected = selected
        self.onSelect = onSelect
        super.init(nibName: nil, bundle: nil)
        self.title = title
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .systemBackground
        picker.dataSource = self
        picker.delegate = self
        picker.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(picker)
        NSLayoutConstraint.activate([
            picker.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor),
            picker.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor),
            picker.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            picker.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor),
        ])
        if let index = values.firstIndex(of: selected) {
            picker.selectRow(index, inComponent: 0, animated: false)
        }
        navigationItem.leftBarButtonItem = UIBarButtonItem(
            barButtonSystemItem: .cancel, target: self, action: #selector(cancel)
        )
        navigationItem.rightBarButtonItem = UIBarButtonItem(
            barButtonSystemItem: .done, target: self, action: #selector(confirm)
        )
    }

    func numberOfComponents(in pickerView: UIPickerView) -> Int { 1 }

    func pickerView(_ pickerView: UIPickerView,
                    numberOfRowsInComponent component: Int) -> Int {
        values.count
    }

    func pickerView(_ pickerView: UIPickerView, titleForRow row: Int,
                    forComponent component: Int) -> String? {
        labels[row]
    }

    @objc private func cancel() { dismiss(animated: true) }

    @objc private func confirm() {
        let row = picker.selectedRow(inComponent: 0)
        if values.indices.contains(row) { onSelect(values[row]) }
        dismiss(animated: true)
    }
}

private final class PamCalendarDayAccessibilityElement: UIAccessibilityElement {
    private weak var host: PamMobileUiHost?
    private let dateKey: String

    init(accessibilityContainer: Any, host: PamMobileUiHost, dateKey: String) {
        self.host = host
        self.dateKey = dateKey
        super.init(accessibilityContainer: accessibilityContainer)
    }

    override func accessibilityActivate() -> Bool {
        host?.selectCalendarDate(dateKey) ?? false
    }
}
private final class PamDateTimePickerController: UIViewController,
    UIAdaptivePresentationControllerDelegate {
    private let picker: UIDatePicker
    private let pickerTitle: String
    private let onDone: (Date) -> Void
    private let onDismiss: () -> Void
    private var resolved = false

    init(
        picker: UIDatePicker,
        title: String,
        onDone: @escaping (Date) -> Void,
        onDismiss: @escaping () -> Void
    ) {
        self.picker = picker
        pickerTitle = title
        self.onDone = onDone
        self.onDismiss = onDismiss
        super.init(nibName: nil, bundle: nil)
        modalPresentationStyle = .pageSheet
        preferredContentSize = CGSize(width: 360, height: 300)
        sheetPresentationController?.detents = [.medium()]
        sheetPresentationController?.prefersGrabberVisible = true
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .systemBackground
        let toolbar = UIToolbar()
        toolbar.translatesAutoresizingMaskIntoConstraints = false
        let titleLabel = UILabel()
        titleLabel.text = pickerTitle
        titleLabel.font = .preferredFont(forTextStyle: .headline)
        titleLabel.adjustsFontForContentSizeCategory = true
        titleLabel.lineBreakMode = .byTruncatingTail
        titleLabel.accessibilityTraits = .header
        toolbar.items = [
            UIBarButtonItem(barButtonSystemItem: .cancel, target: self,
                            action: #selector(cancelTapped)),
            UIBarButtonItem(barButtonSystemItem: .flexibleSpace,
                            target: nil, action: nil),
            UIBarButtonItem(customView: titleLabel),
            UIBarButtonItem(barButtonSystemItem: .flexibleSpace,
                            target: nil, action: nil),
            UIBarButtonItem(barButtonSystemItem: .done, target: self,
                            action: #selector(doneTapped)),
        ]
        picker.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(toolbar)
        view.addSubview(picker)
        NSLayoutConstraint.activate([
            toolbar.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            toolbar.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            toolbar.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            toolbar.heightAnchor.constraint(equalToConstant: 52),
            picker.topAnchor.constraint(equalTo: toolbar.bottomAnchor, constant: 8),
            picker.leadingAnchor.constraint(equalTo: view.leadingAnchor, constant: 8),
            picker.trailingAnchor.constraint(equalTo: view.trailingAnchor, constant: -8),
            picker.heightAnchor.constraint(equalToConstant: 216),
        ])
    }

    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        presentationController?.delegate = self
    }

    @objc private func cancelTapped() {
        guard !resolved else { return }
        resolved = true
        dismiss(animated: true) { [onDismiss] in onDismiss() }
    }

    @objc private func doneTapped() {
        guard !resolved else { return }
        resolved = true
        let selected = picker.date
        dismiss(animated: true) { [onDone] in onDone(selected) }
    }

    func presentationControllerDidDismiss(_ presentationController: UIPresentationController) {
        guard !resolved else { return }
        resolved = true
        onDismiss()
    }

    func dismissSilently() {
        resolved = true
        dismiss(animated: false)
    }
}
