import Foundation
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
    case glass = 9
    case checkbox = 10
    case radio = 11
    case toast = 12
    case imageViewer = 13
    case chat = 14
    case progress = 15
    case drawer = 16
    case modal = 17
    case alertDialog = 18
    case popover = 19
    case menu = 20
    case tooltip = 21
    case dateTimePicker = 22
    case portal = 23
    case accordionGroup = 24
    case checkboxGroup = 25
    case radioGroup = 26
    case switchControl = 27
    case tabTrigger = 28
    case sheetItem = 29
    case menuItem = 30
    case overlayDismiss = 31
    case inputGroup = 32
    case inputSlot = 33
    case formControl = 34
    case table = 35
    case tableRow = 36
    case imageViewerControl = 37
    case messageBranch = 38
    case messageBranchControl = 39
    case promptInput = 40
    case promptInputSubmit = 41
    case conversationScrollButton = 42
    case fileTree = 43
    case fileTreeFolder = 44
    case fileTreeFile = 45
    case transition = 46
    case parallax = 47
    case sparkline = 48
    case hotkey = 49
    case hover = 50

    var isOverlay: Bool {
        switch self {
        case .bottomSheet, .overlay, .drawer, .modal, .alertDialog,
             .popover, .menu, .tooltip, .imageViewer, .portal:
            true
        default:
            false
        }
    }
}

private enum PamHostAction: Int64 {
    case dismiss = 1
    case select = 2
    case open = 3
    case zoom = 4
    case navigate = 5
}

final class PamMobileUiHost: UIView, UIGestureRecognizerDelegate {
    typealias EventEmitter = (NativeViewEventKind, Data) -> Void

    private var emit: EventEmitter?
    private var behavior = PamMobileBehavior.container
    private var properties: [String: WireValue] = [:]
    private var isOpen = true
    private var isControlled = false
    private var isChecked = false
    private var isSelectedState = false
    private var isExpanded = false
    private var minimum: CGFloat = 0
    private var maximum: CGFloat = 100
    private var step: CGFloat = 1
    private var value: CGFloat = 0
    private var snapPoints: [CGFloat] = []
    private var snapIndex = 0
    private var dragOrigin: CGFloat = 0
    private var activeSheetHeight: CGFloat = 0
    private var stateLayerColor = UIColor.label
    private var fillColor = UIColor.tintColor
    private var trackColor = UIColor.secondarySystemFill
    private var pressAnimator: UIViewPropertyAnimator?
    private var imageScale: CGFloat = 1
    private var imageTranslation = CGPoint.zero
    private var shimmerLayer: CAGradientLayer?

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

        let pan = UIPanGestureRecognizer(target: self, action: #selector(onPan(_:)))
        pan.maximumNumberOfTouches = 1
        pan.delegate = self
        addGestureRecognizer(pan)

        let pinch = UIPinchGestureRecognizer(target: self, action: #selector(onPinch(_:)))
        pinch.delegate = self
        addGestureRecognizer(pinch)

        let doubleTap = UITapGestureRecognizer(target: self, action: #selector(onDoubleTap(_:)))
        doubleTap.numberOfTapsRequired = 2
        doubleTap.delegate = self
        addGestureRecognizer(doubleTap)
        tap.require(toFail: doubleTap)
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    func update(_ next: [String: WireValue]) {
        properties = next
        behavior = PamMobileBehavior(
            rawValue: next["behavior"]?.pamInteger ?? behavior.rawValue
        ) ?? .container
        isControlled = next["open"] != nil || next["isOpen"] != nil
        isOpen = next["open"]?.pamFlag
            ?? next["isOpen"]?.pamFlag
            ?? next["modelValue"]?.pamFlag
            ?? (behavior == .popover || behavior == .menu || behavior == .tooltip ? false : isOpen)
        isChecked = next["checked"]?.pamFlag
            ?? next["isChecked"]?.pamFlag
            ?? next["modelValue"]?.pamFlag
            ?? isChecked
        isSelectedState = next["selected"]?.pamFlag
            ?? next["isSelected"]?.pamFlag
            ?? isSelectedState
        isExpanded = next["expanded"]?.pamFlag
            ?? next["isExpanded"]?.pamFlag
            ?? isExpanded
        minimum = next["minimum"]?.pamDecimal ?? next["min"]?.pamDecimal ?? minimum
        maximum = max(minimum, next["maximum"]?.pamDecimal ?? next["max"]?.pamDecimal ?? maximum)
        step = max(0.000_001, next["step"]?.pamDecimal ?? step)
        value = clamped(next["value"]?.pamDecimal ?? next["modelValue"]?.pamDecimal ?? value)
        snapPoints = parseNumbers(next["snapPoints"]?.pamText)
        snapIndex = min(
            max(0, next["snapToIndex"]?.pamInteger
                ?? next["defaultSnapIndex"]?.pamInteger
                ?? snapIndex),
            max(0, snapPoints.count - 1)
        )
        fillColor = color(next["fillColor"]?.pamInteger, fallback: fillColor)
        trackColor = color(next["trackColor"]?.pamInteger, fallback: trackColor)
        stateLayerColor = color(
            next["foregroundColor"]?.pamInteger,
            fallback: stateLayerColor
        )

        applySemantics()
        applyVisibility()
        applyBehaviorState()
        setNeedsLayout()
        setNeedsDisplay()
    }

    func releaseCallbacks() {
        pressAnimator?.stopAnimation(true)
        pressAnimator = nil
        shimmerLayer?.removeAllAnimations()
        shimmerLayer?.removeFromSuperlayer()
        shimmerLayer = nil
        emit = nil
        gestureRecognizers?.forEach(removeGestureRecognizer)
    }

    override func didAddSubview(_ subview: UIView) {
        super.didAddSubview(subview)
        setNeedsLayout()
        applySemantics()
    }

    override func layoutSubviews() {
        super.layoutSubviews()
        switch behavior {
        case .bottomSheet:
            layoutBottomSheet()
        case .overlay, .drawer, .modal, .alertDialog, .portal, .imageViewer:
            layoutOverlay()
        case .popover, .menu, .tooltip:
            layoutAnchoredOverlay()
        case .tabs:
            layoutTabs()
        case .tableRow:
            layoutTableRow()
        case .messageBranch:
            layoutMessageBranch()
        case .fileTree:
            layoutFileTree()
        case .imageViewer:
            applyImageTransform()
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
            drawSelection(context)
        case .skeleton:
            drawSkeleton(context)
        case .calendar:
            drawCalendar(context)
        case .sparkline:
            drawSparkline(context)
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
        case .tabTrigger, .sheetItem, .menuItem, .inputSlot,
             .imageViewerControl, .messageBranchControl, .promptInputSubmit,
             .conversationScrollButton, .fileTreeFolder, .fileTreeFile:
            emit?(.press, Data())
            if behavior == .sheetItem,
               properties["closeOnPress"]?.pamFlag ?? true {
                sheetAncestor()?.requestDismiss()
            }
        case .popover, .menu, .tooltip:
            setOpen(!isOpen, shouldEmit: true)
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

    @objc private func onPan(_ recognizer: UIPanGestureRecognizer) {
        switch behavior {
        case .bottomSheet:
            panSheet(recognizer)
        case .slider:
            panSlider(recognizer)
        case .drawer:
            if recognizer.state == .ended,
               recognizer.velocity(in: self).x < -560 {
                requestDismiss()
            }
        case .imageViewer:
            let translation = recognizer.translation(in: self)
            imageTranslation.x += translation.x
            imageTranslation.y += translation.y
            recognizer.setTranslation(.zero, in: self)
            applyImageTransform()
        default:
            break
        }
    }

    @objc private func onPinch(_ recognizer: UIPinchGestureRecognizer) {
        guard behavior == .imageViewer else { return }
        imageScale = min(5, max(1, imageScale * recognizer.scale))
        recognizer.scale = 1
        applyImageTransform()
        if recognizer.state == .ended {
            emitMap([
                "action": .integer(PamHostAction.zoom.rawValue),
                "scale": .decimal(Double(imageScale)),
            ])
        }
    }

    @objc private func onDoubleTap(_ recognizer: UITapGestureRecognizer) {
        guard behavior == .imageViewer else { return }
        imageScale = imageScale > 1 ? 1 : 2
        if imageScale == 1 { imageTranslation = .zero }
        applyImageTransform(animated: true)
        emitMap([
            "action": .integer(PamHostAction.zoom.rawValue),
            "scale": .decimal(Double(imageScale)),
        ])
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
                : formatted(value)
        case .tabTrigger:
            isAccessibilityElement = true
            traits = [.button]
            if isSelectedState { traits.insert(.selected) }
        case .sheetItem, .menuItem, .overlayDismiss, .inputSlot,
             .imageViewerControl, .messageBranchControl, .promptInputSubmit,
             .conversationScrollButton, .fileTreeFolder, .fileTreeFile:
            isAccessibilityElement = true
            traits = [.button]
        case .alertDialog:
            accessibilityViewIsModal = isOpen
            isAccessibilityElement = false
        default:
            isAccessibilityElement = accessibilityLabel != nil
        }
        if !(properties["enabled"]?.pamFlag ?? true) {
            traits.insert(.notEnabled)
        }
        accessibilityTraits = traits
    }

    private func applyVisibility() {
        if behavior.isOverlay {
            isHidden = !isOpen
            accessibilityViewIsModal = isOpen
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
        case .messageBranch:
            setNeedsLayout()
        case .imageViewer:
            applyImageTransform()
        case .transition:
            applyTransition()
        case .parallax:
            applyParallax()
        case .hotkey:
            becomeFirstResponder()
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
        accessibilityValue = "Position \(snapIndex + 1) of \(points.count)"
    }

    private func layoutOverlay() {
        overlayBackdrop()?.frame = bounds
        guard let content = overlayContent() else { return }
        if behavior == .drawer {
            let width = min(bounds.width * 0.86, 360)
            content.frame = CGRect(
                x: effectiveUserInterfaceLayoutDirection == .rightToLeft
                    ? bounds.width - width : 0,
                y: 0,
                width: width,
                height: bounds.height
            )
        } else if behavior == .modal || behavior == .alertDialog {
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
        overlayBackdrop()?.frame = bounds
        guard isOpen, let content = overlayContent() else { return }
        let margin: CGFloat = 8
        var frame = content.frame
        frame.origin.x = min(max(margin, frame.origin.x), max(margin, bounds.width - frame.width - margin))
        frame.origin.y = min(max(margin, frame.origin.y), max(margin, bounds.height - frame.height - margin))
        if effectiveUserInterfaceLayoutDirection == .rightToLeft {
            frame.origin.x = bounds.width - frame.maxX
        }
        content.frame = frame
    }

    private func layoutTabs() {
        let selected = properties["value"]?.pamText
            ?? properties["modelValue"]?.pamText
        descendants(prefix: "pam:tabs-content").forEach { child in
            let value = child.accessibilityIdentifier?.split(separator: ":").last.map(String.init)
            let visible = selected == nil || value == selected
            child.isHidden = !visible
            child.accessibilityElementsHidden = !visible
        }
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

    private func layoutMessageBranch() {
        let selected = properties["activeBranch"]?.pamInteger
            ?? properties["branchIndex"]?.pamInteger
            ?? 0
        let pages = subviews.filter { !$0.isHidden || $0.accessibilityElementsHidden }
        for (index, page) in pages.enumerated() {
            let visible = index == min(max(0, selected), max(0, pages.count - 1))
            page.isHidden = !visible
            page.accessibilityElementsHidden = !visible
            if visible { page.frame = bounds }
        }
        accessibilityValue = "Branch \(selected + 1) of \(max(1, pages.count))"
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
        if isOpen {
            UIAccessibility.post(
                notification: .announcement,
                argument: accessibilityLabel ?? findFirstText(in: self) ?? "Notification"
            )
        }
    }

    private func applyShimmer() {
        guard !UIAccessibility.isReduceMotionEnabled,
              !(properties["isLoaded"]?.pamFlag ?? false) else {
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

    private func applyImageTransform(animated: Bool = false) {
        guard behavior == .imageViewer else { return }
        let content = overlayContent() ?? subviews.last
        guard let content else { return }
        let transform = CGAffineTransform(
            translationX: imageTranslation.x,
            y: imageTranslation.y
        ).scaledBy(x: imageScale, y: imageScale)
        if animated, !UIAccessibility.isReduceMotionEnabled {
            UIView.animate(
                withDuration: 0.24,
                delay: 0,
                options: [.beginFromCurrentState, .allowUserInteraction]
            ) {
                content.transform = transform
            }
        } else {
            content.transform = transform
        }
        accessibilityValue = "Zoom \(formatted(imageScale * 100)) percent"
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
        controller.present(alert, animated: !UIAccessibility.isReduceMotionEnabled)
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
        guard bounds.width > 0 else { return }
        let point = recognizer.location(in: self)
        let logicalX = effectiveUserInterfaceLayoutDirection == .rightToLeft
            ? bounds.width - point.x : point.x
        let fraction = min(1, max(0, logicalX / bounds.width))
        setRangeValue(minimum + fraction * (maximum - minimum), emitChange: true)
        if recognizer.state == .ended || recognizer.state == .cancelled {
            emit?(.native, Data(formatted(value).utf8))
        }
    }

    private func setRangeValue(_ requested: CGFloat, emitChange: Bool) {
        value = clamped(round((requested - minimum) / step) * step + minimum)
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
        let fraction = maximum > minimum ? (value - minimum) / (maximum - minimum) : 0
        let height = max(4, min(bounds.height, properties["thickness"]?.pamDecimal ?? 4))
        let track = CGRect(x: 0, y: (bounds.height - height) / 2, width: bounds.width, height: height)
        context.setFillColor(trackColor.cgColor)
        context.fillEllipse(in: track)
        context.setFillColor(fillColor.cgColor)
        var fill = track
        fill.size.width *= min(1, max(0, fraction))
        if effectiveUserInterfaceLayoutDirection == .rightToLeft {
            fill.origin.x = bounds.width - fill.width
        }
        context.fill(fill)
    }

    private func drawSlider(_ context: CGContext) {
        drawProgress(context)
        let fraction = maximum > minimum ? (value - minimum) / (maximum - minimum) : 0
        let logical = bounds.width * min(1, max(0, fraction))
        let x = effectiveUserInterfaceLayoutDirection == .rightToLeft
            ? bounds.width - logical : logical
        context.setFillColor(fillColor.cgColor)
        context.fillEllipse(in: CGRect(x: x - 10, y: bounds.midY - 10, width: 20, height: 20))
    }

    private func drawSwitch(_ context: CGContext) {
        let track = CGRect(
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
            y: bounds.midY - diameter / 2,
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
        let calendar = Calendar.autoupdatingCurrent
        let now = properties["visibleDate"]?.pamText
            .flatMap(ISO8601DateFormatter().date(from:)) ?? Date()
        let range = calendar.range(of: .day, in: .month, for: now) ?? 1..<31
        let first = calendar.date(from: calendar.dateComponents([.year, .month], from: now)) ?? now
        let offset = max(0, calendar.component(.weekday, from: first) - 1)
        let cellWidth = bounds.width / 7
        let cellHeight = bounds.height / 7
        let font = UIFont.preferredFont(forTextStyle: .body)
        let attributes: [NSAttributedString.Key: Any] = [
            .font: font,
            .foregroundColor: stateLayerColor,
        ]
        for day in range {
            let index = offset + day - 1
            let column = index % 7
            let logicalColumn = effectiveUserInterfaceLayoutDirection == .rightToLeft
                ? 6 - column : column
            let row = index / 7 + 1
            let frame = CGRect(
                x: CGFloat(logicalColumn) * cellWidth,
                y: CGFloat(row) * cellHeight,
                width: cellWidth,
                height: cellHeight
            )
            let text = String(day) as NSString
            let size = text.size(withAttributes: attributes)
            text.draw(
                at: CGPoint(x: frame.midX - size.width / 2, y: frame.midY - size.height / 2),
                withAttributes: attributes
            )
        }
    }

    private func drawSparkline(_ context: CGContext) {
        let source = properties["values"]?.pamText
            ?? properties["value"]?.pamText
            ?? ""
        let points = source
            .split(whereSeparator: { $0 == "," || $0 == "\n" || $0 == ";" || $0 == " " })
            .compactMap { Double($0).map(CGFloat.init) }
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

    private func applyTransition() {
        guard !UIAccessibility.isReduceMotionEnabled else {
            alpha = 1
            transform = .identity
            return
        }
        alpha = 0
        transform = CGAffineTransform(scaleX: 0.96, y: 0.96)
        UIView.animate(
            withDuration: properties["duration"]?.pamDecimal.map {
                TimeInterval($0 / 1_000)
            } ?? 0.22,
            delay: 0,
            options: [.beginFromCurrentState, .allowUserInteraction, .curveEaseOut]
        ) {
            self.alpha = 1
            self.transform = .identity
        }
    }

    private func applyParallax() {
        guard let window, bounds.height > 0 else { return }
        let frame = convert(bounds, to: window)
        let offset = frame.midY - window.bounds.midY
        let speed = min(1, max(-1, properties["speed"]?.pamDecimal ?? 0.28))
        let translation = min(bounds.height / 3, max(-bounds.height / 3, -offset * speed))
        subviews.forEach {
            $0.transform = CGAffineTransform(translationX: 0, y: translation)
        }
    }

    override var canBecomeFirstResponder: Bool {
        behavior == .hotkey
    }

    override var keyCommands: [UIKeyCommand]? {
        guard behavior == .hotkey else { return nil }
        let source = properties["keys"]?.pamText
            ?? properties["hotkey"]?.pamText
            ?? properties["value"]?.pamText
            ?? ""
        let pieces = source.lowercased().split(separator: "+").map(String.init)
        guard let input = pieces.last, !input.isEmpty else { return nil }
        var modifiers: UIKeyModifierFlags = []
        if pieces.contains("ctrl") || pieces.contains("control") { modifiers.insert(.control) }
        if pieces.contains("alt") || pieces.contains("option") { modifiers.insert(.alternate) }
        if pieces.contains("shift") { modifiers.insert(.shift) }
        if pieces.contains("meta") || pieces.contains("cmd") { modifiers.insert(.command) }
        return [UIKeyCommand(
            input: input,
            modifierFlags: modifiers,
            action: #selector(onHotkey)
        )]
    }

    @objc private func onHotkey() {
        emit?(.press, Data())
    }

    override func pressesBegan(_ presses: Set<UIPress>, with event: UIPressesEvent?) {
        if behavior == .hover {
            animateStateLayer(to: 0.92, duration: 0.09)
            emit?(.toggle, Data("1".utf8))
        }
        super.pressesBegan(presses, with: event)
    }

    override func pressesEnded(_ presses: Set<UIPress>, with event: UIPressesEvent?) {
        if behavior == .hover {
            animateStateLayer(to: 1, duration: 0.14)
            emit?(.toggle, Data("0".utf8))
        }
        super.pressesEnded(presses, with: event)
    }

    private func animateStateLayer(to alpha: CGFloat, duration: TimeInterval) {
        pressAnimator?.stopAnimation(true)
        guard !UIAccessibility.isReduceMotionEnabled else {
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
        descendant(tag: "pam:overlay-content")
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
            .compactMap { Double($0).map(CGFloat.init) }
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
