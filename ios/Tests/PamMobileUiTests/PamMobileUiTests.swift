import PamNative
import UIKit
import XCTest
@testable import PamMobileUi

final class PamMobileUiTests: XCTestCase {
    func testGeneratedComponentIDsMatchTheAndroidContract() {
        XCTAssertEqual(GeneratedComponents.BUTTON, 20)
        XCTAssertEqual(GeneratedComponents.FAB, 48)
        XCTAssertEqual(GeneratedComponents.SELECT_ITEM, 87)
        XCTAssertEqual(GeneratedComponents.SELECT_PORTAL, 88)
        XCTAssertEqual(
            GeneratedComponents.allIds.sorted(),
            Array(1...GeneratedComponents.allIds.count)
        )
    }

    func testButtonAndFabKeepA44PointTouchTargetWhenPooled() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }
        view.frame = CGRect(x: 0, y: 0, width: 20, height: 20)

        for component in [GeneratedComponents.BUTTON, GeneratedComponents.FAB] {
            factory.update(view: view, properties: [
                "behavior": .integer(1),
                "component": .integer(Int64(component)),
            ])
            XCTAssertTrue(view.point(inside: CGPoint(x: -11, y: 10), with: nil))
            XCTAssertFalse(view.point(inside: CGPoint(x: -13, y: 10), with: nil))
        }

        factory.update(view: view, properties: [
            "behavior": .integer(1),
            "component": .integer(0),
        ])
        XCTAssertFalse(view.point(inside: CGPoint(x: -11, y: 10), with: nil))
        factory.release(view: view)
        factory.close()
    }

    func testSelectItemHasSelectionSemanticsAndGenericSheetItemDoesNot() {
        let factory = MobileUiHostFactory()
        let view = factory.create(context: nil) { _ in }
        factory.update(view: view, properties: [
            "behavior": .integer(24),
            "component": .integer(Int64(GeneratedComponents.SELECT_ITEM)),
            "checked": .flag(true),
            "accessibilityLabel": .text("First option"),
        ])
        XCTAssertTrue(view.accessibilityTraits.contains(.button))
        XCTAssertTrue(view.accessibilityTraits.contains(.selected))
        XCTAssertEqual(view.accessibilityValue, "Selected")

        factory.update(view: view, properties: [
            "behavior": .integer(24),
            "component": .integer(0),
            "checked": .flag(false),
            "accessibilityLabel": .text("Generic action"),
        ])
        XCTAssertTrue(view.accessibilityTraits.contains(.button))
        XCTAssertFalse(view.accessibilityTraits.contains(.selected))
        XCTAssertNil(view.accessibilityValue)
        factory.release(view: view)
        factory.close()
    }

    func testDefaultCheckedAndSelectedContainerColorReachUIKit() {
        let factory = MobileUiHostFactory()
        let checkbox = factory.create(context: nil) { _ in }
        factory.update(view: checkbox, properties: [
            "behavior": .integer(9),
            "defaultIsChecked": .flag(true),
            "accessibilityLabel": .text("Remember me"),
        ])
        XCTAssertTrue(checkbox.accessibilityTraits.contains(.selected))
        XCTAssertEqual(checkbox.accessibilityValue, "On")

        let toggle = factory.create(context: nil) { _ in }
        factory.update(view: toggle, properties: [
            "behavior": .integer(23),
            "buttonToggleItem": .flag(true),
            "selected": .flag(true),
            "selectedContainerColor": .integer(Int64(0xFFFF0000)),
        ])
        XCTAssertEqual(
            toggle.backgroundColor,
            UIColor(red: 1, green: 0, blue: 0, alpha: 1)
        )
        factory.release(view: checkbox)
        factory.release(view: toggle)
        factory.close()
    }

    func testCalendarSelectionHonorsDisabledDatesAndNativePayloadModes() {
        var payloads: [String] = []
        let calendar = PamMobileUiHost { _, payload in
            payloads.append(String(decoding: payload, as: UTF8.self))
        }
        calendar.update([
            "behavior": .integer(7),
            "mode": .integer(2),
            "disabledDates": .text("2026-10-04"),
            "minDate": .text("2026-10-01"),
            "maxDate": .text("2026-10-31"),
            "firstDayOfWeek": .integer(1),
            "locale": .text("pt_BR"),
        ])
        XCTAssertEqual(calendar.configuredCalendar().firstWeekday, 2)
        XCTAssertEqual(calendar.configuredCalendar().locale?.identifier, "pt_BR")
        XCTAssertFalse(calendar.selectCalendarDate("2026-09-30"))
        XCTAssertFalse(calendar.selectCalendarDate("2026-10-04"))
        XCTAssertTrue(calendar.selectCalendarDate("2026-10-05"))
        XCTAssertTrue(calendar.selectCalendarDate("2026-10-06"))
        XCTAssertEqual(payloads, ["M\n2026-10-05", "M\n2026-10-05\n2026-10-06"])

        calendar.update([
            "behavior": .integer(7),
            "mode": .integer(3),
        ])
        XCTAssertTrue(calendar.selectCalendarDate("2026-10-10"))
        XCTAssertTrue(calendar.selectCalendarDate("2026-10-08"))
        XCTAssertEqual(Array(payloads.suffix(2)), [
            "R\n2026-10-10\n",
            "R\n2026-10-08\n2026-10-10",
        ])
        calendar.releaseCallbacks()
    }

    func testDatePickerAppliesBoundsAndClockMode() {
        let host = PamMobileUiHost { _, _ in }
        host.update([
            "behavior": .integer(17),
            "mode": .integer(6),
            "minDate": .text("2026-10-01"),
            "maxDate": .text("2026-10-31"),
            "is24Hour": .flag(true),
        ])
        let picker = host.configuredDatePicker()
        XCTAssertEqual(picker.datePickerMode, .dateAndTime)
        guard let minimum = picker.minimumDate, let maximum = picker.maximumDate else {
            XCTFail("The picker must retain both requested date bounds")
            return
        }
        XCTAssertLessThan(minimum, maximum)
        host.releaseCallbacks()
    }

    func testNativePropertyAliasesReachUIKitBehavior() {
        let host = PamMobileUiHost { _, _ in }
        host.update([
            "behavior": .integer(5),
            "minValue": .decimal(10),
            "maxValue": .decimal(20),
            "value": .decimal(15),
            "step": .decimal(10),
            "sliderTrackHeight": .decimal(8),
        ])
        host.accessibilityIncrement()
        XCTAssertEqual(host.accessibilityValue, "20")

        host.update([
            "behavior": .integer(31),
            "isHeaderRow": .flag(true),
        ])
        XCTAssertTrue(host.accessibilityTraits.contains(.header))

        let input = UITextField()
        host.addSubview(input)
        host.update([
            "behavior": .integer(27),
            "interactionDisabled": .flag(true),
        ])
        XCTAssertFalse(input.isUserInteractionEnabled)

        host.update([
            "behavior": .integer(3),
            "focusScope": .flag(false),
        ])
        XCTAssertFalse(host.accessibilityViewIsModal)
        host.releaseCallbacks()
    }

    func testInputSlotClearActionUpdatesItsNativeField() {
        let group = PamMobileUiHost { _, _ in }
        group.update(["behavior": .integer(27)])
        let field = UITextField()
        field.text = "Draft"
        group.addSubview(field)
        let slot = PamMobileUiHost { _, _ in }
        slot.update([
            "behavior": .integer(28),
            "slotAction": .integer(2),
            "focusOnPress": .flag(false),
        ])
        group.addSubview(slot)
        slot.activateInputSlot()
        XCTAssertEqual(field.text, "")
        group.releaseCallbacks()
        slot.releaseCallbacks()
    }

    func testMenuSingleSelectionUpdatesUIKitSemantics() {
        let menu = PamMobileUiHost { _, _ in }
        menu.update([
            "behavior": .integer(15),
            "open": .flag(true),
            "selectionMode": .integer(1),
        ])
        let first = PamMobileUiHost { _, _ in }
        first.update([
            "behavior": .integer(25),
            "closeOnSelect": .flag(false),
        ])
        let second = PamMobileUiHost { _, _ in }
        second.update([
            "behavior": .integer(25),
            "closeOnSelect": .flag(false),
        ])
        menu.addSubview(first)
        menu.addSubview(second)
        second.activateMenuItem()
        XCTAssertFalse(first.accessibilityTraits.contains(.selected))
        XCTAssertTrue(second.accessibilityTraits.contains(.selected))
        first.activateMenuItem()
        XCTAssertTrue(first.accessibilityTraits.contains(.selected))
        XCTAssertFalse(second.accessibilityTraits.contains(.selected))
        first.releaseCallbacks()
        second.releaseCallbacks()
        menu.releaseCallbacks()
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
