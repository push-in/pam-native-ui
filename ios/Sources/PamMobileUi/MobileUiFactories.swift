import Foundation
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
        let view = UIView()
        view.clipsToBounds = false
        return view
    }

    func update(view: UIView, properties: [String: WireValue]) {
        applyCommonProperties(view, properties)
    }
}

final class MobileUiIconFactory: NativeViewFactory {
    func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        let image = UIImageView()
        image.contentMode = .scaleAspectFit
        image.isAccessibilityElement = false
        return image
    }

    func update(view: UIView, properties: [String: WireValue]) {
        guard let image = view as? UIImageView else { return }
        applyCommonProperties(image, properties)
        if let color = properties["color"]?.integerValue {
            image.tintColor = UIColor(pamARGB: color)
        }
        let icon = properties["icon"]?.integerValue ?? 0
        image.image = UIImage(systemName: Self.symbols[icon] ?? "circle")
    }

    private static let symbols: [Int: String] = [
        1: "house", 2: "magnifyingglass", 3: "person", 4: "gearshape",
        5: "bell", 6: "star", 7: "clock", 8: "message", 9: "checkmark",
        10: "xmark", 11: "chevron.left", 12: "chevron.right",
        13: "chevron.up", 14: "chevron.down", 15: "plus", 16: "minus",
    ]
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
