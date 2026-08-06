import PamNative
import UIKit
import XCTest
@testable import PamMobileUi

final class PamMobileUiTests: XCTestCase {
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
