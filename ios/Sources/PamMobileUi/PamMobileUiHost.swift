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
}

final class PamMobileUiHost: UIView, UIGestureRecognizerDelegate {
    typealias EventEmitter = (NativeViewEventKind, Data) -> Void

    private var emit: EventEmitter?
    private var behavior = PamMobileBehavior.container
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
    private var sliderThumbSize: CGFloat = 20
    private var sliderTrackThickness: CGFloat = 4
    private var snapPoints: [CGFloat] = []
    private var snapIndex = 0
    private var dragOrigin: CGFloat = 0
    private var activeSheetHeight: CGFloat = 0
    private var sheetSearchable = false
    private var sheetAllowCustomValue = false
    private var sheetSearchPlaceholder = "Search options"
    private weak var sheetSearchField: UITextField?
    private weak var sheetCustomAction: UIButton?
    private weak var sheetEmptyState: UILabel?
    private var stateLayerColor = UIColor.label
    private var fillColor = UIColor.tintColor
    private var trackColor = UIColor.secondarySystemFill
    private var selectedForegroundColor = UIColor.white
    private var pressAnimator: UIViewPropertyAnimator?
    private var shimmerLayer: CAGradientLayer?
    private var progressTrackLayer: CAShapeLayer?
    private var progressFillLayer: CAShapeLayer?
    private var toastDismissWorkItem: DispatchWorkItem?
    private var toastScheduleSignature: String?
    private var toastAnnouncementSignature: String?
    private weak var anchoredPortalParent: UIView?
    private weak var anchoredPortalContent: UIView?
    private weak var anchoredPortalCatcher: UIControl?
    private var anchoredPortalIndex = 0
    private var anchoredPortalFrame = CGRect.zero
    private var navigationKind = 0
    private var carouselCycle = false
    private var carouselContinuous = true
    private var carouselInterval: TimeInterval = 6
    private var carouselWorkItem: DispatchWorkItem?
    private var sparklineAutoDrawApplied = false

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

    func update(_ next: [String: WireValue]) {
        let previousBehavior = behavior
        let wasControlled = isControlled
        properties = next
        behavior = PamMobileBehavior(
            rawValue: next["behavior"]?.pamInteger ?? behavior.rawValue
        ) ?? .container
        if previousBehavior != behavior {
            openDefaultInitialized = false
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
        isChecked = next["checked"]?.pamFlag
            ?? next["isChecked"]?.pamFlag
            ?? next["modelValue"]?.pamFlag
            ?? isChecked
        isSelectedState = next["selected"]?.pamFlag
            ?? next["isSelected"]?.pamFlag
            ?? isSelectedState
        buttonToggleItem = next["buttonToggleItem"]?.pamFlag ?? false
        isExpanded = next["expanded"]?.pamFlag
            ?? next["isExpanded"]?.pamFlag
            ?? isExpanded
        minimum = next["minimum"]?.pamDecimal ?? next["min"]?.pamDecimal ?? minimum
        maximum = max(minimum, next["maximum"]?.pamDecimal ?? next["max"]?.pamDecimal ?? maximum)
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
        showThumbLabel = next["showThumbLabel"]?.pamFlag == true
            || next["alwaysShowThumbLabel"]?.pamFlag == true
        sliderThumbSize = max(1, next["thumbSize"]?.pamDecimal ?? sliderThumbSize)
        sliderTrackThickness = max(
            1,
            next["trackThickness"]?.pamDecimal ?? sliderTrackThickness
        )
        navigationKind = next["navigationKind"]?.pamInteger ?? navigationKind
        carouselCycle = next["cycle"]?.pamFlag ?? false
        carouselContinuous = next["continuous"]?.pamFlag ?? true
        carouselInterval = min(
            60,
            max(0.75, (next["interval"]?.pamDecimal ?? 6_000) / 1_000)
        )
        snapPoints = parseNumbers(next["snapPoints"]?.pamText)
        snapIndex = min(
            max(0, next["snapToIndex"]?.pamInteger
                ?? next["defaultSnapIndex"]?.pamInteger
                ?? snapIndex),
            max(0, snapPoints.count - 1)
        )
        sheetSearchable = next["searchable"]?.pamFlag ?? false
        sheetAllowCustomValue = next["allowCustomValue"]?.pamFlag ?? false
        sheetSearchPlaceholder = next["searchPlaceholder"]?.pamText
            ?? "Search options"
        fillColor = color(next["fillColor"]?.pamInteger, fallback: fillColor)
        trackColor = color(next["trackColor"]?.pamInteger, fallback: trackColor)
        stateLayerColor = color(
            next["foregroundColor"]?.pamInteger,
            fallback: stateLayerColor
        )
        selectedForegroundColor = color(
            next["selectedForegroundColor"]?.pamInteger,
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
        restoreAnchoredPortalContent()
        pressAnimator?.stopAnimation(true)
        pressAnimator = nil
        shimmerLayer?.removeAllAnimations()
        shimmerLayer?.removeFromSuperlayer()
        shimmerLayer = nil
        toastDismissWorkItem?.cancel()
        toastDismissWorkItem = nil
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
        default:
            break
        }
    }

    override func accessibilityIncrement() {
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
        guard isUserInteractionEnabled else { return }
        let point = recognizer.location(in: self)
        if behavior.isOverlay, let backdrop = overlayBackdrop(), backdrop.frame.contains(point) {
            requestDismiss()
            return
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
            tabsAncestor()?.selectTab(self)
            emit?(.press, Data())
        case .sheetItem, .menuItem, .inputSlot,
             .fileTreeFolder, .fileTreeFile:
            emit?(.press, Data())
            if behavior == .sheetItem,
               properties["closeOnPress"]?.pamFlag ?? true {
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
            setOpen(true, shouldEmit: true)
        case .ended, .cancelled, .failed:
            setOpen(false, shouldEmit: true)
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

    private func selectTab(_ trigger: PamMobileUiHost) {
        let target = trigger.properties["value"]?.pamText
        let previous = tabTriggers(in: self)
            .first(where: \.isSelectedState)?
            .properties["value"]?
            .pamText
        tabTriggers(in: self).forEach { item in
            item.isSelectedState = item.properties["value"]?.pamText == target
            item.applyButtonToggleVisualState()
            item.applyTabTextVisualState()
            item.applySemantics()
        }
        if let target, target != previous {
            emit?(.change, Data(target.utf8))
        }
        setNeedsLayout()
        UIView.animate(
            withDuration: animationsEnabled ? 0.2 : 0,
            delay: 0,
            options: [.beginFromCurrentState, .curveEaseInOut, .allowUserInteraction]
        ) {
            self.layoutTabs()
        }
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
            self.selectTab(triggers[next % triggers.count])
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
        selectTab(triggers[target])
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
        backgroundColor = isSelectedState ? fillColor : .clear
        layer.cornerRadius = CGFloat(
            properties["selectionCornerRadius"]?.pamDecimal ?? 8
        )
        layer.masksToBounds = true
        applyTabTextVisualState()
    }

    private func applyTabTextVisualState() {
        guard behavior == .tabTrigger else { return }
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
        case .tabTrigger:
            isAccessibilityElement = true
            traits = [.button]
            if isSelectedState { traits.insert(.selected) }
        case .sheetItem, .menuItem, .overlayDismiss, .inputSlot,
             .fileTreeFolder, .fileTreeFile:
            isAccessibilityElement = true
            traits = [.button]
        default:
            isAccessibilityElement = accessibilityLabel != nil
        }
        if !(properties["enabled"]?.pamFlag ?? true) {
            traits.insert(.notEnabled)
        }
        accessibilityTraits = traits
    }

    private var requiresMinimumTouchTarget: Bool {
        switch behavior {
        case .accordion, .slider, .checkbox, .radio, .switchControl,
             .tabTrigger, .sheetItem, .menuItem, .overlayDismiss, .inputSlot,
             .fileTreeFolder, .fileTreeFile,
             .calendar, .dateTimePicker:
            true
        default:
            false
        }
    }

    private func applyVisibility() {
        if behavior.isOverlay {
            isHidden = !isOpen
            accessibilityViewIsModal = isOpen
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
        let search = ensureSheetSearchField(in: content)
        let rowHeight: CGFloat = 56
        let inset: CGFloat = 8
        if let search {
            search.frame = CGRect(
                x: inset,
                y: inset,
                width: max(0, content.bounds.width - inset * 2),
                height: rowHeight
            )
            if !search.isFirstResponder, window != nil {
                DispatchQueue.main.async { [weak search] in
                    search?.becomeFirstResponder()
                }
            }
        }
        let itemOffset = search == nil ? inset : rowHeight + inset * 2
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
            sheetEmptyState?.removeFromSuperview()
            sheetSearchField = nil
            sheetCustomAction = nil
            sheetEmptyState = nil
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
            button.titleLabel?.font = .preferredFont(forTextStyle: .body)
            button.titleLabel?.adjustsFontForContentSizeCategory = true
            button.addTarget(
                self,
                action: #selector(acceptCustomSheetValue),
                for: .touchUpInside
            )
            sheetCustomAction = button
            return button
        }()
        if customAction.superview !== content {
            customAction.removeFromSuperview()
            content.addSubview(customAction)
        }
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
        updateSheetSupplementary(query: field.text ?? "", content: content)
        return field
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
        sheetEmptyState?.isHidden = query.isEmpty || !visibleItems.isEmpty || showCustom
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
        let controlled = properties["value"]?.pamText
            ?? properties["modelValue"]?.pamText
        let selectedTrigger = triggers.first(where: { $0.isSelectedState })
            ?? triggers.first(where: {
                $0.properties["value"]?.pamText == controlled
            })
            ?? triggers.first
        let selected = selectedTrigger?.properties["value"]?.pamText
            ?? controlled
        if navigationKind == 1 {
            triggers.forEach { trigger in
                let visible = selected == nil
                    || trigger.properties["value"]?.pamText == selected
                trigger.isHidden = !visible
                trigger.accessibilityElementsHidden = !visible
            }
        }
        descendants(prefix: "pam:tabs-content").forEach { child in
            let value = child.accessibilityIdentifier?.split(separator: ":").last.map(String.init)
            let visible = selected == nil || value == selected
            child.isHidden = !visible
            child.accessibilityElementsHidden = !visible
        }
        guard
            let trigger = selectedTrigger,
            let indicator = descendant(tag: "pam:tabs-indicator")
        else {
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
        let totalHeight = visible.reduce(CGFloat.zero) { $0 + $1.bounds.height }
        var y = max(0, (bounds.height - totalHeight) / 2)
        let inset: CGFloat = 16
        for child in visible {
            let width = min(child.bounds.width, bounds.width - inset * 2)
            let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? bounds.width - inset - width : inset
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
        let expanded = Set(
            properties["expandedPaths"]?.pamText?
                .split(separator: "\n")
                .map(String.init) ?? []
        )
        for folder in descendants(prefix: "pam:file-tree-folder") {
            guard let identifier = folder.accessibilityIdentifier else { continue }
            let path = identifier.split(separator: ":", maxSplits: 2).last.map(String.init) ?? ""
            let open = expanded.isEmpty
                ? (folder.accessibilityValue == "Expanded")
                : expanded.contains(path)
            folder.accessibilityValue = open ? "Expanded" : "Collapsed"
            folder.subviews.dropFirst().forEach {
                $0.isHidden = !open
                $0.accessibilityElementsHidden = !open
            }
        }
    }

    private func applyInputState() {
        let readOnly = properties["readOnly"]?.pamFlag ?? false
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
        layer.borderWidth = invalid ? 2 : (properties["focused"]?.pamFlag == true ? 2 : 0)
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
        let announcementSignature = "\(identity)\u{0}\(announcement)"
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

    private func presentDateTimePicker() {
        guard let controller = nearestViewController() else {
            emit?(.press, Data())
            return
        }
        let alert = UIAlertController(
            title: properties["label"]?.pamText ?? "Select date",
            message: nil,
            preferredStyle: .actionSheet
        )
        let picker = UIDatePicker()
        picker.preferredDatePickerStyle = .wheels
        picker.datePickerMode = switch properties["mode"]?.pamInteger {
        case 5: .time
        case 6: .dateAndTime
        default: .date
        }
        alert.view.addSubview(picker)
        picker.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([
            picker.leadingAnchor.constraint(equalTo: alert.view.leadingAnchor, constant: 8),
            picker.trailingAnchor.constraint(equalTo: alert.view.trailingAnchor, constant: -8),
            picker.topAnchor.constraint(equalTo: alert.view.topAnchor, constant: 48),
            picker.heightAnchor.constraint(equalToConstant: 216),
        ])
        alert.addAction(UIAlertAction(title: "Cancel", style: .cancel))
        alert.addAction(UIAlertAction(title: "Done", style: .default) { [weak self, weak picker] _ in
            guard let self, let picker else { return }
            let formatter = ISO8601DateFormatter()
            self.emit?(.change, Data(formatter.string(from: picker.date).utf8))
        })
        controller.present(alert, animated: animationsEnabled)
    }

    private func panSheet(_ recognizer: UIPanGestureRecognizer) {
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
        guard properties["dismissible"]?.pamFlag ?? true else { return }
        setOpen(false, shouldEmit: false)
        emitMap([
            "action": .integer(PamHostAction.dismiss.rawValue),
            "dismissed": .flag(true),
        ])
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
        let trackInset = sliderThumbSize / 2
        let track = orientation == 2
            ? CGRect(
                x: bounds.midX - sliderTrackThickness / 2,
                y: trackInset,
                width: sliderTrackThickness,
                height: max(1, bounds.height - sliderThumbSize)
            )
            : CGRect(
                x: trackInset,
                y: bounds.midY - sliderTrackThickness / 2,
                width: max(1, bounds.width - sliderThumbSize),
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

        if showSliderTicks {
            let intervals = min(100, max(1, Int(round((maximum - minimum) / step))))
            context.setFillColor(trackColor.cgColor)
            for index in 0...intervals {
                let tickValue = minimum
                    + (maximum - minimum) * CGFloat(index) / CGFloat(intervals)
                let point = sliderPoint(tickValue, in: track)
                context.fillEllipse(in: CGRect(
                    x: point.x - 1.5,
                    y: point.y - 1.5,
                    width: 3,
                    height: 3
                ))
            }
        }

        context.setFillColor(fillColor.cgColor)
        let radius = sliderThumbSize / 2
        let thumbValues = rangeEnabled ? [lowerValue, upperValue] : [value]
        for current in thumbValues {
            let point = sliderPoint(current, in: track)
            context.fillEllipse(in: CGRect(
                x: point.x - radius,
                y: point.y - radius,
                width: sliderThumbSize,
                height: sliderThumbSize
            ))
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
            .foregroundColor: selectedForegroundColor,
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
        let diameter: CGFloat = isChecked ? 24 : 16
        let left = track.minX + 4
        let right = track.maxX - diameter - 4
        let checkedX = effectiveUserInterfaceLayoutDirection == .rightToLeft ? left : right
        let uncheckedX = effectiveUserInterfaceLayoutDirection == .rightToLeft ? right : left
        context.setFillColor((isChecked ? UIColor.white : UIColor.secondaryLabel).cgColor)
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

    private func drawCalendar(_ context: CGContext) {
        var calendar = Calendar.autoupdatingCurrent
        calendar.firstWeekday = 1
        let requestedYear = properties["year"]?.pamInteger
        let requestedMonth = properties["month"]?.pamInteger
        let now = calendar.date(from: DateComponents(
            year: requestedYear,
            month: requestedMonth,
            day: 1
        )) ?? Date()
        let first = calendar.date(
            from: calendar.dateComponents([.year, .month], from: now)
        ) ?? now
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
        let columns = showWeek ? 8 : 7
        let cellWidth = bounds.width / CGFloat(columns)
        let cellHeight = bounds.height / 7
        let font = UIFont.preferredFont(forTextStyle: .body)
        let mutedAttributes: [NSAttributedString.Key: Any] = [
            .font: UIFont.preferredFont(forTextStyle: .caption1),
            .foregroundColor: UIColor.secondaryLabel,
        ]
        let normalAttributes: [NSAttributedString.Key: Any] = [
            .font: font,
            .foregroundColor: stateLayerColor,
        ]
        let selected = Set(
            (properties["selectedValues"]?.pamText ?? "")
                .split(whereSeparator: \.isNewline)
                .map(String.init)
        )
        let formatter = DateFormatter()
        formatter.calendar = calendar
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.dateFormat = "yyyy-MM-dd"

        for dayIndex in 0..<7 {
            let dayColumn = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? 6 - dayIndex : dayIndex
            let visualColumn = dayColumn + (
                showWeek && effectiveUserInterfaceLayoutDirection != .rightToLeft
                    ? 1 : 0
            )
            let symbol = calendar.veryShortWeekdaySymbols[dayIndex] as NSString
            let frame = CGRect(
                x: CGFloat(visualColumn) * cellWidth,
                y: 0,
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

        for index in 0..<42 {
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
            let row = index / 7 + 1
            let frame = CGRect(
                x: CGFloat(visualColumn) * cellWidth,
                y: CGFloat(row) * cellHeight,
                width: cellWidth,
                height: cellHeight
            )
            let outside = !calendar.isDate(date, equalTo: first, toGranularity: .month)
            if outside && !showOutside { continue }
            let text = String(calendar.component(.day, from: date)) as NSString
            let dateKey = formatter.string(from: date)
            let isSelected = selected.contains(dateKey)
            if isSelected {
                context.setFillColor(fillColor.cgColor)
                context.fillEllipse(in: frame.insetBy(
                    dx: cellWidth * 0.18,
                    dy: max(2, (cellHeight - cellWidth * 0.64) / 2)
                ))
            }
            var attributes = outside ? mutedAttributes : normalAttributes
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
            for row in 0..<6 {
                guard let date = calendar.date(
                    byAdding: .day,
                    value: row * 7,
                    to: firstVisible
                ) else { continue }
                let week = calendar.component(.weekOfYear, from: date)
                let column = effectiveUserInterfaceLayoutDirection == .rightToLeft
                    ? 7 : 0
                let frame = CGRect(
                    x: CGFloat(column) * cellWidth,
                    y: CGFloat(row + 1) * cellHeight,
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
        let path = UIBezierPath()
        path.lineWidth = properties["lineWidth"]?.pamDecimal ?? 2.5
        path.lineCapStyle = .round
        path.lineJoinStyle = .round
        for (index, point) in points.enumerated() {
            let logicalX = bounds.width * CGFloat(index) / CGFloat(points.count - 1)
            let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? bounds.width - logicalX : logicalX
            let y = bounds.height - (point - low) / spread * bounds.height
            if index == 0 {
                path.move(to: CGPoint(x: x, y: y))
            } else {
                path.addLine(to: CGPoint(x: x, y: y))
            }
        }
        fillColor.setStroke()
        path.stroke()
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
        requestDismiss()
    }

    private func restoreAnchoredPortalContent() {
        anchoredPortalCatcher?.removeFromSuperview()
        guard let content = anchoredPortalContent else {
            anchoredPortalCatcher = nil
            return
        }
        content.removeFromSuperview()
        content.layer.zPosition = 0
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
        ancestor { $0.behavior.isOverlay }
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
        shouldRecognizeSimultaneouslyWith otherGestureRecognizer: UIGestureRecognizer
    ) -> Bool {
        behavior == .bottomSheet || behavior == .slider
    }
}
