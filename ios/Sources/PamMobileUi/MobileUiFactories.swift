import Foundation
import PamNative
import UIKit

private extension WireValue {
    var textValue: String? {
        guard case let .text(value) = self else { return nil }
        return value
    }

    var integerValue: Int? {
        guard case let .integer(value) = self else { return nil }
        return Int(value)
    }

    var decimalValue: CGFloat? {
        switch self {
        case let .decimal(value): return CGFloat(value)
        case let .integer(value): return CGFloat(value)
        default: return nil
        }
    }

    var flagValue: Bool? {
        guard case let .flag(value) = self else { return nil }
        return value
    }
}

private extension UIColor {
    convenience init(pamARGB value: Int) {
        let bits = UInt32(truncatingIfNeeded: value)
        self.init(
            red: CGFloat((bits >> 16) & 0xff) / 255,
            green: CGFloat((bits >> 8) & 0xff) / 255,
            blue: CGFloat(bits & 0xff) / 255,
            alpha: CGFloat((bits >> 24) & 0xff) / 255
        )
    }
}

private func applyCommonProperties(_ view: UIView, _ properties: [String: WireValue]) {
    if let color = properties["backgroundColor"]?.integerValue {
        view.backgroundColor = UIColor(pamARGB: color)
    }
    if let radius = properties["borderRadius"]?.decimalValue {
        view.layer.cornerRadius = radius
        view.layer.cornerCurve = .continuous
        view.clipsToBounds = radius > 0
    }
    if let hidden = properties["hidden"]?.flagValue {
        view.isHidden = hidden
    }
    if let enabled = properties["enabled"]?.flagValue {
        view.isUserInteractionEnabled = enabled
        view.alpha = enabled ? 1 : 0.38
    }
    if let label = properties["accessibilityLabel"]?.textValue {
        view.isAccessibilityElement = true
        view.accessibilityLabel = label
    }
    if let hint = properties["accessibilityHint"]?.textValue {
        view.accessibilityHint = hint
    }
}

final class MobileUiHostFactory: NativeViewFactory {
    func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        PamMobileUiHost { _, payload in emit(payload) }
    }

    func create(context: AnyObject?, emitter: NativeViewEmitter) -> UIView {
        PamMobileUiHost { kind, payload in
            emitter.emit(kind: kind, payload: payload)
        }
    }

    func update(view: UIView, properties: [String: WireValue]) {
        applyCommonProperties(view, properties)
        (view as? PamMobileUiHost)?.update(properties)
    }

    func release(view: UIView) {
        (view as? PamMobileUiHost)?.releaseCallbacks()
    }
}

final class MobileUiIconFactory: NativeViewFactory {
    func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        PamVectorIconView()
    }

    func update(view: UIView, properties: [String: WireValue]) {
        guard let iconView = view as? PamVectorIconView else { return }
        applyCommonProperties(iconView, properties)
        iconView.icon = properties["icon"]?.integerValue ?? 0
        if let color = properties["color"]?.integerValue {
            iconView.iconColor = UIColor(pamARGB: color)
        }
    }
}

private final class PamVectorIconView: UIView {
    var icon = 0 {
        didSet {
            guard icon != oldValue else { return }
            paths = Self.paths(for: icon)
            setNeedsDisplay()
        }
    }
    var iconColor = UIColor.label {
        didSet {
            guard iconColor != oldValue else { return }
            setNeedsDisplay()
        }
    }

    private var paths: [UIBezierPath] = []
    private static var cache: [Int: [UIBezierPath]] = [:]
    private static let cacheLock = NSLock()

    init() {
        super.init(frame: .zero)
        backgroundColor = .clear
        isOpaque = false
        isAccessibilityElement = false
        contentMode = .redraw
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    override var intrinsicContentSize: CGSize {
        CGSize(width: 20, height: 20)
    }

    override func draw(_ rect: CGRect) {
        guard !paths.isEmpty, rect.width > 0, rect.height > 0 else { return }
        let scale = min(rect.width, rect.height) / 24
        let offsetX = (rect.width - 24 * scale) / 2
        let offsetY = (rect.height - 24 * scale) / 2
        guard let context = UIGraphicsGetCurrentContext() else { return }
        context.saveGState()
        context.translateBy(x: offsetX, y: offsetY)
        context.scaleBy(x: scale, y: scale)
        iconColor.setStroke()
        for path in paths {
            path.lineWidth = 2
            path.lineCapStyle = .round
            path.lineJoinStyle = .round
            path.stroke()
        }
        context.restoreGState()
    }

    private static func paths(for icon: Int) -> [UIBezierPath] {
        cacheLock.lock()
        defer { cacheLock.unlock() }
        if let cached = cache[icon] {
            return cached
        }
        let parsed = (GeneratedIcons.paths[icon] ?? []).compactMap(parse)
        cache[icon] = parsed
        return parsed
    }

    private static func parse(_ source: String) -> UIBezierPath? {
        let pattern = #"[A-Za-z]|[-+]?(?:\d*\.?\d+(?:[eE][-+]?\d+)?)"#
        guard let expression = try? NSRegularExpression(pattern: pattern) else {
            return nil
        }
        let range = NSRange(source.startIndex..., in: source)
        let tokens = expression.matches(in: source, range: range).compactMap {
            Range($0.range, in: source).map { String(source[$0]) }
        }
        guard !tokens.isEmpty else { return nil }

        let path = UIBezierPath()
        var index = 0
        var command = ""
        var point = CGPoint.zero
        func number() -> CGFloat? {
            guard index < tokens.count, let value = Double(tokens[index]) else {
                return nil
            }
            index += 1
            return CGFloat(value)
        }
        while index < tokens.count {
            if tokens[index].first?.isLetter == true {
                command = tokens[index]
                index += 1
            }
            switch command {
            case "M":
                guard let x = number(), let y = number() else { return nil }
                point = CGPoint(x: x, y: y)
                path.move(to: point)
                command = "L"
            case "L":
                guard let x = number(), let y = number() else { return nil }
                point = CGPoint(x: x, y: y)
                path.addLine(to: point)
            case "H":
                guard let x = number() else { return nil }
                point.x = x
                path.addLine(to: point)
            case "V":
                guard let y = number() else { return nil }
                point.y = y
                path.addLine(to: point)
            case "C":
                guard
                    let x1 = number(), let y1 = number(),
                    let x2 = number(), let y2 = number(),
                    let x = number(), let y = number()
                else {
                    return nil
                }
                point = CGPoint(x: x, y: y)
                path.addCurve(
                    to: point,
                    controlPoint1: CGPoint(x: x1, y: y1),
                    controlPoint2: CGPoint(x: x2, y: y2)
                )
            case "Z", "z":
                path.close()
                command = ""
            default:
                return nil
            }
        }
        return path
    }
}

final class MobileUiMarkdownFactory: NativeViewFactory {
    func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        let view = PamMarkdownView()
        view.onLink = { link in emit(Data(link.utf8)) }
        return view
    }

    func update(view: UIView, properties: [String: WireValue]) {
        guard let markdown = view as? PamMarkdownView else { return }
        applyCommonProperties(markdown, properties)
        markdown.update(properties)
    }

    func release(view: UIView) {
        (view as? PamMarkdownView)?.releaseContent()
    }
}

private final class PamMarkdownView: UITextView, UITextViewDelegate {
    var onLink: ((String) -> Void)?
    private var source = ""

    init() {
        super.init(frame: .zero, textContainer: nil)
        backgroundColor = .clear
        isEditable = false
        isScrollEnabled = false
        textContainerInset = .zero
        textContainer.lineFragmentPadding = 0
        adjustsFontForContentSizeCategory = true
        dataDetectorTypes = [.link]
        delegate = self
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    func update(_ properties: [String: WireValue]) {
        let next = properties["source"]?.textValue ?? source
        let foreground = properties["foregroundColor"]?.integerValue
            .map(UIColor.init(pamARGB:)) ?? UIColor.label
        linkTextAttributes = [
            .foregroundColor: properties["linkColor"]?.integerValue
                .map(UIColor.init(pamARGB:)) ?? tintColor as Any,
            .underlineStyle: NSUnderlineStyle.single.rawValue,
        ]
        isSelectable = properties["selectable"]?.flagValue ?? true
        guard next != source || textColor != foreground else { return }
        source = next
        let attributed = try? AttributedString(
            markdown: next,
            options: .init(
                interpretedSyntax: .full,
                failurePolicy: .returnPartiallyParsedIfPossible
            )
        )
        let mutable = NSMutableAttributedString(
            attributedString: attributed.map(NSAttributedString.init)
                ?? NSAttributedString(string: next)
        )
        mutable.addAttributes(
            [.foregroundColor: foreground, .font: UIFont.preferredFont(forTextStyle: .body)],
            range: NSRange(location: 0, length: mutable.length)
        )
        attributedText = mutable
        invalidateIntrinsicContentSize()
    }

    func releaseContent() {
        attributedText = nil
        onLink = nil
    }

    func textView(
        _ textView: UITextView,
        shouldInteractWith URL: URL,
        in characterRange: NSRange,
        interaction: UITextItemInteraction
    ) -> Bool {
        onLink?(URL.absoluteString)
        return false
    }
}

final class MobileUiHorizontalScrollFactory: NativeViewFactory {
    func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        PamHorizontalScrollView(emit: emit)
    }

    func update(view: UIView, properties: [String: WireValue]) {
        guard let scroll = view as? PamHorizontalScrollView else { return }
        applyCommonProperties(scroll, properties)
        scroll.update(properties)
    }

    func release(view: UIView) {
        (view as? PamHorizontalScrollView)?.releaseCallbacks()
    }
}

private final class PamHorizontalScrollView: UIScrollView, UIScrollViewDelegate {
    private var emit: ((Data) -> Void)?

    init(emit: @escaping (Data) -> Void) {
        self.emit = emit
        super.init(frame: .zero)
        delegate = self
        alwaysBounceHorizontal = false
        alwaysBounceVertical = false
        showsVerticalScrollIndicator = false
        directionalLockEnabled = true
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) is unavailable")
    }

    func update(_ properties: [String: WireValue]) {
        isScrollEnabled = properties["scrollingEnabled"]?.flagValue ?? true
        showsHorizontalScrollIndicator =
            properties["showsHorizontalScrollIndicator"]?.flagValue ?? false
        isPagingEnabled = properties["pagingEnabled"]?.flagValue ?? false
        if let offset = properties["contentOffset"]?.decimalValue,
           abs(contentOffset.x - offset) > 0.5 {
            setContentOffset(CGPoint(x: max(0, offset), y: 0), animated: false)
        }
        switch properties["decelerationRate"]?.textValue {
        case "fast": decelerationRate = .fast
        default: decelerationRate = .normal
        }
    }

    func scrollViewDidScroll(_ scrollView: UIScrollView) {
        var value = Double(scrollView.contentOffset.x)
        emit?(Data(bytes: &value, count: MemoryLayout<Double>.size))
    }

    func releaseCallbacks() {
        delegate = nil
        emit = nil
    }
}

final class MobileUiGridFactory: NativeViewFactory {
    func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        PamGridView()
    }

    func update(view: UIView, properties: [String: WireValue]) {
        guard let grid = view as? PamGridView else { return }
        applyCommonProperties(grid, properties)
        grid.update(properties)
    }
}

private final class PamGridView: UIView {
    private var columns = [12, 12, 12, 12, 12, 12]
    private var columnGaps = Array(repeating: CGFloat.zero, count: 6)
    private var rowGaps = Array(repeating: CGFloat.zero, count: 6)

    func update(_ properties: [String: WireValue]) {
        columns = Self.integers(properties["columns"]?.textValue, fallback: columns)
        columnGaps = Self.decimals(properties["columnGaps"]?.textValue, fallback: columnGaps)
        rowGaps = Self.decimals(properties["rowGaps"]?.textValue, fallback: rowGaps)
        setNeedsLayout()
        invalidateIntrinsicContentSize()
    }

    override func layoutSubviews() {
        super.layoutSubviews()
        let index = breakpointIndex()
        let count = max(1, columns[index])
        let gap = columnGaps[index]
        let unit = max(0, (bounds.width - gap * CGFloat(count - 1)) / CGFloat(count))
        var row = 0
        var column = 0
        var rowTop = CGFloat.zero
        var rowHeight = CGFloat.zero
        var rows: [[(UIView, Int, Int)]] = [[]]

        for child in subviews where !child.isHidden {
            let span = min(count, max(1, Self.span(child.accessibilityIdentifier, index)))
            if column > 0, column + span > count {
                rowTop += rowHeight + rowGaps[index]
                row += 1
                column = 0
                rowHeight = 0
                rows.append([])
            }
            let width = unit * CGFloat(span) + gap * CGFloat(span - 1)
            let height = max(1, child.sizeThatFits(CGSize(width: width, height: .greatestFiniteMagnitude)).height)
            rows[row].append((child, column, span))
            rowHeight = max(rowHeight, height)
            column += span
            if column >= count {
                let currentHeight = rowHeight
                layoutRow(rows[row], top: rowTop, height: currentHeight, unit: unit, gap: gap)
                rowTop += currentHeight + rowGaps[index]
                row += 1
                column = 0
                rowHeight = 0
                rows.append([])
            }
        }
        if row < rows.count, !rows[row].isEmpty {
            layoutRow(rows[row], top: rowTop, height: rowHeight, unit: unit, gap: gap)
        }
    }

    private func layoutRow(
        _ row: [(UIView, Int, Int)],
        top: CGFloat,
        height: CGFloat,
        unit: CGFloat,
        gap: CGFloat
    ) {
        let rtl = effectiveUserInterfaceLayoutDirection == .rightToLeft
        for (view, column, span) in row {
            let width = unit * CGFloat(span) + gap * CGFloat(span - 1)
            let logicalX = CGFloat(column) * (unit + gap)
            let x = rtl ? bounds.width - logicalX - width : logicalX
            view.frame = CGRect(x: x, y: top, width: width, height: height)
        }
    }

    private func breakpointIndex() -> Int {
        let width = window?.screen.bounds.width ?? UIScreen.main.bounds.width
        return [600.0, 840.0, 1145.0, 1545.0, 2138.0].map(CGFloat.init)
            .reduce(0) { width >= $1 ? $0 + 1 : $0 }
    }

    private static func span(_ identifier: String?, _ index: Int) -> Int {
        guard let source = identifier?.split(separator: ":").last else { return 1 }
        return integers(String(source), fallback: Array(repeating: 1, count: 6))[index]
    }

    private static func integers(_ source: String?, fallback: [Int]) -> [Int] {
        let parsed = source?.split(separator: ",").compactMap { Int($0) } ?? []
        var result = Array(repeating: 1, count: 6)
        var current = max(1, fallback.first ?? 1)
        for index in result.indices {
            current = max(1, parsed[safe: index] ?? fallback[safe: index] ?? current)
            result[index] = current
        }
        return result
    }

    private static func decimals(_ source: String?, fallback: [CGFloat]) -> [CGFloat] {
        let parsed = source?.split(separator: ",").compactMap { Double($0).map(CGFloat.init) } ?? []
        var result = Array(repeating: CGFloat.zero, count: 6)
        var current = max(0, fallback.first ?? 0)
        for index in result.indices {
            current = max(0, parsed[safe: index] ?? fallback[safe: index] ?? current)
            result[index] = current
        }
        return result
    }
}

private extension Collection {
    subscript(safe index: Index) -> Element? {
        indices.contains(index) ? self[index] : nil
    }
}
