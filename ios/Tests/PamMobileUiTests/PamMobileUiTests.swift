import PamNative
import UIKit
import XCTest
@testable import PamMobileUi

final class PamMobileUiTests: XCTestCase {
    func testSelectionIndicatorUsesTaggedGeometryInBothDirections() {
        let host = PamMobileUiHost { _, _ in }
        defer { host.releaseCallbacks() }
        host.frame = CGRect(x: 0, y: 0, width: 320, height: 64)
        let container = UIView(frame: CGRect(x: 8, y: 4, width: 304, height: 56))
        let indicator = UIView(frame: CGRect(x: 12, y: 16, width: 24, height: 24))
        indicator.accessibilityIdentifier = "pam:selection-indicator"
        host.addSubview(container)
        container.addSubview(indicator)
        XCTAssertEqual(host.selectionIndicatorRect, CGRect(x: 20, y: 20, width: 24, height: 24))
        host.semanticContentAttribute = .forceRightToLeft
        indicator.frame.origin.x = 268
        XCTAssertEqual(host.selectionIndicatorRect, CGRect(x: 276, y: 20, width: 24, height: 24))
        container.removeFromSuperview()
        XCTAssertEqual(host.selectionIndicatorRect, CGRect(x: 150, y: 22, width: 20, height: 20))
    }

    func testCheckboxRendersDistinctCheckedAndIndeterminateMarks() throws {
        let host = PamMobileUiHost { _, _ in }
        defer { host.releaseCallbacks() }
        host.frame = CGRect(x: 0, y: 0, width: 48, height: 48)
        let format = UIGraphicsImageRendererFormat()
        format.scale = 1
        let renderer = UIGraphicsImageRenderer(size: host.bounds.size, format: format)
        func capture(checked: Bool, indeterminate: Bool) throws -> Data {
            host.update([
                "behavior": .integer(Int64(PamMobileBehavior.checkbox.rawValue)),
                "checked": .flag(checked),
                "indeterminate": .flag(indeterminate),
                "fillColor": .integer(0xFF077A50),
                "trackColor": .integer(0xFF5B657E),
                "selectedForegroundColor": .integer(0xFFFFFFFF),
            ])
            let image = renderer.image { _ in host.draw(host.bounds) }
            return try XCTUnwrap(image.pngData())
        }
        let unchecked = try capture(checked: false, indeterminate: false)
        let checked = try capture(checked: true, indeterminate: false)
        let mixed = try capture(checked: false, indeterminate: true)
        XCTAssertNotEqual(unchecked, checked)
        XCTAssertNotEqual(unchecked, mixed)
        XCTAssertNotEqual(checked, mixed)
    }

    func testFileTreeMultipleSelectionRetainsOtherPaths() {
        var changes: [String] = []
        let tree = PamMobileUiHost { kind, data in
            if kind == .change { changes.append(String(decoding: data, as: UTF8.self)) }
        }
        let alpha = PamMobileUiHost { _, _ in }
        let beta = PamMobileUiHost { _, _ in }
        alpha.update(["behavior": .integer(34), "path": .text("alpha")])
        beta.update(["behavior": .integer(34), "path": .text("beta")])
        let alphaMark = UIView()
        let betaMark = UIView()
        alphaMark.accessibilityIdentifier = "pam:file-tree-selection-mark"
        betaMark.accessibilityIdentifier = "pam:file-tree-selection-mark"
        alpha.addSubview(alphaMark)
        beta.addSubview(betaMark)
        tree.addSubview(alpha)
        tree.addSubview(beta)
        defer {
            tree.releaseCallbacks()
            alpha.releaseCallbacks()
            beta.releaseCallbacks()
        }
        tree.update(["behavior": .integer(32), "multiple": .flag(true), "selectedPaths": .text("alpha")])
        XCTAssertTrue(beta.accessibilityActivate())
        XCTAssertTrue(alpha.accessibilityTraits.contains(.selected))
        XCTAssertTrue(beta.accessibilityTraits.contains(.selected))
        XCTAssertEqual(alphaMark.alpha, 1)
        XCTAssertEqual(betaMark.alpha, 1)
        XCTAssertTrue(alpha.accessibilityActivate())
        XCTAssertFalse(alpha.accessibilityTraits.contains(.selected))
        XCTAssertTrue(beta.accessibilityTraits.contains(.selected))
        XCTAssertEqual(alphaMark.alpha, 0)
        XCTAssertEqual(betaMark.alpha, 1)
        XCTAssertEqual(changes, ["beta", "alpha"])
        tree.update(["behavior": .integer(32), "multiple": .flag(true), "selectedPaths": .text("")])
        tree.frame = CGRect(x: 0, y: 0, width: 390, height: 844)
        tree.setNeedsLayout()
        tree.layoutIfNeeded()
        XCTAssertFalse(alpha.accessibilityTraits.contains(.selected))
        XCTAssertFalse(beta.accessibilityTraits.contains(.selected))
        XCTAssertEqual(alphaMark.alpha, 0)
        XCTAssertEqual(betaMark.alpha, 0)
    }

    func testFileTreeActivationExpansionAndDisabledAncestry() {
        var events: [(NativeViewEventKind, Data)] = []
        let tree = PamMobileUiHost { events.append(($0, $1)) }
        let folder = PamMobileUiHost { _, _ in XCTFail("Tree owns emitted changes") }
        let file = PamMobileUiHost { _, _ in XCTFail("Tree owns emitted changes") }
        tree.update(["behavior": .integer(32), "expandedPaths": .text("/src")])
        folder.update(["behavior": .integer(33), "path": .text("/src")])
        file.update(["behavior": .integer(34), "path": .text("/src/app.php")])
        let content = UIView()
        content.accessibilityIdentifier = "pam:file-tree-content"
        content.addSubview(file)
        folder.addSubview(content)
        tree.addSubview(folder)
        defer {
            tree.releaseCallbacks()
            folder.releaseCallbacks()
            file.releaseCallbacks()
        }

        tree.isUserInteractionEnabled = false
        XCTAssertFalse(folder.accessibilityActivate())
        XCTAssertFalse(file.accessibilityActivate())
        tree.isUserInteractionEnabled = true
        folder.isUserInteractionEnabled = false
        XCTAssertFalse(file.accessibilityActivate())
        folder.isUserInteractionEnabled = true
        file.update(["behavior": .integer(34), "path": .text("/src/app.php"), "enabled": .flag(false)])
        XCTAssertFalse(file.accessibilityActivate())
        XCTAssertTrue(events.isEmpty)
        file.update(["behavior": .integer(34), "path": .text("/src/app.php"), "enabled": .flag(true)])

        XCTAssertTrue(folder.accessibilityActivate())
        XCTAssertTrue(content.isHidden)
        XCTAssertTrue(content.accessibilityElementsHidden)
        XCTAssertEqual(folder.accessibilityValue, "Collapsed")
        XCTAssertTrue(folder.accessibilityActivate())
        XCTAssertFalse(content.isHidden)
        XCTAssertEqual(folder.accessibilityValue, "Expanded")
        XCTAssertTrue(file.accessibilityActivate())
        XCTAssertTrue(file.accessibilityTraits.contains(.selected))
        XCTAssertFalse(folder.accessibilityTraits.contains(.selected))
        XCTAssertFalse(file.accessibilityActivate(), "Selecting the same path must not emit twice")
        XCTAssertEqual(events.filter { $0.0 == .change }.map { String(decoding: $0.1, as: UTF8.self) },
                       ["/src", "/src", "/src/app.php"])
        XCTAssertEqual(events.filter { $0.0 == .native }.count, 2)

        tree.update(["behavior": .integer(32), "expandedPaths": .text("")])
        tree.frame = CGRect(x: 0, y: 0, width: 390, height: 844)
        tree.setNeedsLayout()
        tree.layoutIfNeeded()
        XCTAssertTrue(content.isHidden, "An explicitly empty controlled set must collapse all folders")
    }

    func testSelectionDismissalHonorsCloseOnSelectBeforeLegacyCloseOnPress() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }
        guard let host = view as? PamMobileUiHost else {
            return XCTFail("Expected the selection host")
        }
        for behavior in [3, 24] {
            for close in [false, true] {
                factory.update(view: host, properties: [
                    "behavior": .integer(Int64(behavior)),
                    "closeOnSelect": .flag(close),
                    "closeOnPress": .flag(!close),
                ])
                XCTAssertEqual(host.closesSheetOnSelection, close)
            }
        }
        factory.release(view: host)
        factory.close()
    }

    func testEveryManifestFactoryCreatesAndUpdatesAUIKitView() {
        let factories: [NativeViewFactory] = [
            MobileUiHostFactory(),
            MobileUiIconFactory(),
            MobileUiMarkdownFactory(),
            MobileUiHorizontalScrollFactory(),
            MobileUiGridFactory(),
        ]

        for factory in factories {
            let view = factory.create(context: nil) { _ in }
            factory.update(
                view: view,
                properties: [
                    "accessibilityLabel": .text("PAM native component"),
                    "accessibilityHint": .text("UIKit parity gate"),
                    "backgroundColor": .integer(Int64(0xFFF7FAFF)),
                    "borderRadius": .decimal(12),
                    "enabled": .flag(true),
                    "behavior": .integer(1),
                    "component": .integer(1),
                    "icon": .integer(1),
                    "text": .text("PAM UI"),
                ]
            )

            XCTAssertEqual(view.accessibilityLabel, "PAM native component")
            XCTAssertEqual(view.accessibilityHint, "UIKit parity gate")
            XCTAssertTrue(view.isUserInteractionEnabled)
            XCTAssertEqual(view.layer.cornerRadius, 12)
            factory.release(view: view)
            factory.close()
        }
    }

    func testHostAppliesTabsAndReducedMotionProperties() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }

        factory.update(
            view: view,
            properties: [
                "behavior": .integer(6),
                "selectedValue": .text("details"),
                "reduceMotion": .flag(true),
                "animationDuration": .integer(0),
                "accessibilityLabel": .text("Details tabs"),
            ]
        )

        XCTAssertTrue(view is PamMobileUiHost)
        XCTAssertEqual(view.accessibilityLabel, "Details tabs")
        XCTAssertFalse(hasAnimations(view.layer))
        factory.release(view: view)
        factory.close()
    }

    func testEveryCuratedNativeBehaviorUpdatesLayoutsAndReleases() {
        let factory = MobileUiHostFactory()

        for behavior in 1...39 {
            let view = factory.create(context: nil) { _ in }
            view.frame = CGRect(x: 0, y: 0, width: 390, height: 844)
            factory.update(
                view: view,
                properties: [
                    "behavior": .integer(Int64(behavior)),
                    "accessibilityLabel": .text("Behavior \(behavior)"),
                    "enabled": .flag(true),
                    "open": .flag(true),
                    "reduceMotion": .flag(true),
                    "animationDuration": .integer(0),
                    "min": .decimal(0),
                    "max": .decimal(100),
                    "value": .decimal(50),
                    "snapPoints": .text("40,70,100"),
                    "points": .text("2,4,3,8,6"),
                ]
            )
            view.setNeedsLayout()
            view.layoutIfNeeded()
            view.drawHierarchy(in: view.bounds, afterScreenUpdates: true)

            XCTAssertEqual(view.accessibilityLabel, "Behavior \(behavior)")
            XCTAssertTrue(view.isUserInteractionEnabled)
            XCTAssertFalse(
                hasAnimations(view.layer),
                "Behavior \(behavior) animated with reduced motion enabled"
            )

            factory.release(view: view)
        }

        factory.close()
    }

    func testAdjustableBehaviorsExposeNativeAccessibilityActions() {
        let factory = MobileUiHostFactory()

        for behavior in [3, 5, 12] {
            let view = factory.create(context: nil) { _ in }
            factory.update(
                view: view,
                properties: [
                    "behavior": .integer(Int64(behavior)),
                    "accessibilityLabel": .text("Adjustable control"),
                    "min": .decimal(0),
                    "max": .decimal(100),
                    "value": .decimal(50),
                    "snapPoints": .text("40,70,100"),
                ]
            )

            XCTAssertTrue(view.isAccessibilityElement)
            XCTAssertTrue(view.accessibilityTraits.contains(.adjustable))
            XCTAssertNotNil(view.accessibilityValue)
            factory.release(view: view)
        }

        factory.close()
    }

    func testCompactControlsRetainA44PointTouchTarget() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }
        view.frame = CGRect(x: 0, y: 0, width: 20, height: 20)
        factory.update(
            view: view,
            properties: [
                "behavior": .integer(9),
                "accessibilityLabel": .text("Compact checkbox"),
            ]
        )

        XCTAssertTrue(view.point(inside: CGPoint(x: -11, y: 10), with: nil))
        XCTAssertTrue(view.point(inside: CGPoint(x: 31, y: 10), with: nil))
        XCTAssertFalse(view.point(inside: CGPoint(x: -13, y: 10), with: nil))
        XCTAssertFalse(view.point(inside: CGPoint(x: 33, y: 10), with: nil))

        factory.release(view: view)
        factory.close()
    }

    func testListItemKeepsMaterialLeadingBodyAndTrailingInsets() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }
        view.frame = CGRect(x: 0, y: 0, width: 390, height: 72)
        let leading = UIView(frame: CGRect(x: 0, y: 0, width: 24, height: 24))
        let title = UILabel(frame: CGRect(x: 0, y: 0, width: 180, height: 24))
        let subtitle = UILabel(frame: CGRect(x: 0, y: 0, width: 180, height: 20))
        let trailing = UIView(frame: CGRect(x: 0, y: 0, width: 24, height: 24))
        [leading, title, subtitle, trailing].forEach { view.addSubview($0) }

        factory.update(
            view: view,
            properties: ["behavior": .integer(37)]
        )
        view.setNeedsLayout()
        view.layoutIfNeeded()

        XCTAssertEqual(leading.frame.minX, 16)
        XCTAssertEqual(title.frame.minX, leading.frame.maxX + 12)
        XCTAssertEqual(subtitle.frame.minX, title.frame.minX)
        XCTAssertEqual(subtitle.frame.minY, title.frame.maxY)
        XCTAssertEqual(trailing.frame.maxX, 374)
        XCTAssertGreaterThanOrEqual(trailing.frame.minX, title.frame.maxX + 12)

        factory.release(view: view)
        factory.close()
    }

    func testAbstractSelectionItemUsesButtonSemanticsWithoutCheckboxValue() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }
        factory.update(
            view: view,
            properties: [
                "behavior": .integer(9),
                "abstractSelectionItem": .flag(true),
                "checked": .flag(true),
                "accessibilityLabel": .text("Grid view"),
            ]
        )

        XCTAssertTrue(view.isAccessibilityElement)
        XCTAssertTrue(view.accessibilityTraits.contains(.button))
        XCTAssertTrue(view.accessibilityTraits.contains(.selected))
        XCTAssertEqual(view.accessibilityValue, "Selected")

        factory.release(view: view)
        factory.close()
    }

    func testGeneratedVectorCatalogCoversEveryNativeIconId() {
        XCTAssertEqual(GeneratedIcons.paths.count, 55)
        XCTAssertTrue(GeneratedIcons.paths.keys.allSatisfy { $0 > 0 })
        XCTAssertTrue(GeneratedIcons.paths.values.allSatisfy { !$0.isEmpty })
    }

    private func hasAnimations(_ layer: CALayer) -> Bool {
        if !(layer.animationKeys() ?? []).isEmpty {
            return true
        }

        return (layer.sublayers ?? []).contains(where: hasAnimations)
    }
}
