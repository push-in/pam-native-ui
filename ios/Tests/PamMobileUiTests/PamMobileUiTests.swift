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

    func testSelectItemVoiceOverActivationUsesPressAndDismissPolicy() {
        var presses = 0
        var dismissals: [[String: WireValue]] = []
        let sheet = PamMobileUiHost { kind, payload in
            if kind == .native, let map = try? WireMap.decode(payload) {
                dismissals.append(map)
            }
        }
        sheet.update(["behavior": .integer(3), "open": .flag(true)])
        let content = UIView()
        content.accessibilityIdentifier = "pam:overlay-content"
        sheet.addSubview(content)
        let option = PamMobileUiHost { kind, _ in
            if kind == .press { presses += 1 }
        }
        option.update([
            "behavior": .integer(24),
            "component": .integer(Int64(GeneratedComponents.SELECT_ITEM)),
            "closeOnSelect": .flag(true),
        ])
        let label = UILabel()
        label.text = "Engineering"
        option.addSubview(label)
        content.addSubview(option)
        option.frame = CGRect(x: 0, y: 0, width: 200, height: 56)
        option.layoutIfNeeded()

        XCTAssertEqual(option.accessibilityLabel, "Engineering")
        XCTAssertTrue(option.keyCommands?.contains(where: { $0.input == "\r" }) == true)
        XCTAssertTrue(option.keyCommands?.contains(where: { $0.input == " " }) == true)
        XCTAssertTrue(option.canBecomeFirstResponder)
        XCTAssertTrue(option.accessibilityActivate())
        XCTAssertEqual(presses, 1)
        XCTAssertEqual(dismissals.last?["action"], .integer(1))

        sheet.releaseCallbacks()
        option.releaseCallbacks()
    }

    func testSelectItemRejectsDisabledActivationAndRefreshesPooledLabel() {
        var presses = 0
        let option = PamMobileUiHost { kind, _ in
            if kind == .press { presses += 1 }
        }
        option.update([
            "behavior": .integer(24),
            "component": .integer(Int64(GeneratedComponents.SELECT_ITEM)),
            "accessibilityLabel": .text("Old option"),
            "enabled": .flag(false),
        ])
        XCTAssertTrue(option.accessibilityTraits.contains(.notEnabled))
        XCTAssertFalse(option.accessibilityActivate())
        XCTAssertFalse(option.canBecomeFirstResponder)

        let label = UILabel()
        label.text = "New option"
        option.addSubview(label)
        option.frame = CGRect(x: 0, y: 0, width: 200, height: 56)
        option.update([
            "behavior": .integer(24),
            "component": .integer(Int64(GeneratedComponents.SELECT_ITEM)),
            "enabled": .flag(true),
            "closeOnSelect": .flag(false),
        ])
        option.layoutIfNeeded()
        XCTAssertEqual(option.accessibilityLabel, "New option")
        XCTAssertTrue(option.accessibilityActivate())
        XCTAssertEqual(presses, 1)

        option.update([
            "behavior": .integer(24),
            "component": .integer(Int64(GeneratedComponents.SELECT_ITEM)),
            "readOnly": .flag(true),
        ])
        XCTAssertFalse(option.accessibilityActivate())
        XCTAssertEqual(presses, 1)
        option.releaseCallbacks()
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

    func testCalendarNavigationAndHeaderKeepNativePayloadAndBounds() throws {
        var payloads: [[String: WireValue]] = []
        let host = PamMobileUiHost { kind, data in
            if kind == .native, let values = try? WireMap.decode(data) {
                payloads.append(values)
            }
        }
        host.frame = CGRect(x: 0, y: 0, width: 336, height: 336)
        host.update([
            "behavior": .integer(7),
            "year": .integer(2026),
            "month": .integer(10),
            "minDate": .text("2026-09-01"),
            "maxDate": .text("2026-11-30"),
        ])
        let previous = UIView(frame: CGRect(x: 0, y: 0, width: 48, height: 48))
        previous.accessibilityIdentifier = "pam:calendar-prev"
        host.addSubview(previous)
        let title = UILabel(frame: CGRect(x: 48, y: 0, width: 160, height: 48))
        title.accessibilityIdentifier = "pam:calendar-title"
        host.addSubview(title)

        XCTAssertTrue(host.handleCalendarHeaderTap(at: CGPoint(x: 24, y: 24)))
        XCTAssertEqual(payloads.last?["action"], .integer(3))
        XCTAssertEqual(payloads.last?["year"], .integer(2026))
        XCTAssertEqual(payloads.last?["month"], .integer(9))
        XCTAssertTrue(title.text?.localizedCaseInsensitiveContains("2026") == true)
        XCTAssertFalse(host.navigateCalendar(months: -1))
        XCTAssertEqual(payloads.count, 1)

        XCTAssertTrue(host.selectCalendarMonthOrYear(month: true, value: 12))
        XCTAssertEqual(payloads.last?["month"], .integer(11))
        XCTAssertTrue(host.selectCalendarMonthOrYear(month: false, value: 2025))
        XCTAssertEqual(payloads.last?["month"], .integer(9))
        XCTAssertEqual(payloads.last?["year"], .integer(2026))
        host.releaseCallbacks()
    }

    func testCalendarHitTestingUsesTaggedGridAndActualRowCount() {
        let host = PamMobileUiHost { _, _ in }
        host.frame = CGRect(x: 0, y: 0, width: 336, height: 336)
        host.update([
            "behavior": .integer(7),
            "year": .integer(2026),
            "month": .integer(10),
            "firstDayOfWeek": .integer(1),
            "showOutsideDays": .flag(false),
        ])
        let grid = UIView(frame: CGRect(x: 0, y: 96, width: 336, height: 240))
        grid.accessibilityIdentifier = "pam:calendar-grid"
        host.addSubview(grid)

        XCTAssertNil(host.calendarDate(at: CGPoint(x: 168, y: 48)))
        XCTAssertNil(host.calendarDate(at: CGPoint(x: 24, y: 120)))
        XCTAssertEqual(host.calendarDate(at: CGPoint(x: 168, y: 120)), "2026-10-01")
        XCTAssertEqual(host.calendarDate(at: CGPoint(x: 24, y: 168)), "2026-10-05")
        host.releaseCallbacks()
    }

    func testCalendarAdjacentMonthSelectionUpdatesVisibleTitle() {
        let host = PamMobileUiHost { _, _ in }
        host.update([
            "behavior": .integer(7),
            "year": .integer(2026),
            "month": .integer(10),
            "locale": .text("en_US"),
            "showOutsideDays": .flag(true),
        ])
        let title = UILabel()
        title.accessibilityIdentifier = "pam:calendar-title"
        host.addSubview(title)
        XCTAssertTrue(host.selectCalendarDate("2026-11-01"))
        XCTAssertEqual(title.text, "November 2026")
        XCTAssertTrue(host.navigateCalendar(months: -1))
        XCTAssertEqual(title.text, "October 2026")
        host.releaseCallbacks()
    }

    func testCalendarDaysAreAccessibleButtonsWithNativeActivation() {
        var changes: [String] = []
        let host = PamMobileUiHost { kind, payload in
            if kind == .change { changes.append(String(decoding: payload, as: UTF8.self)) }
        }
        host.frame = CGRect(x: 0, y: 0, width: 336, height: 336)
        host.update([
            "behavior": .integer(7),
            "year": .integer(2026),
            "month": .integer(10),
            "locale": .text("en_US"),
            "firstDayOfWeek": .integer(1),
            "showOutsideDays": .flag(false),
            "disabledDates": .text("2026-10-02"),
        ])
        let grid = UIView(frame: CGRect(x: 0, y: 96, width: 336, height: 240))
        grid.accessibilityIdentifier = "pam:calendar-grid"
        host.addSubview(grid)
        host.setNeedsLayout()
        host.layoutIfNeeded()

        let days = host.accessibilityElements?.compactMap { $0 as? UIAccessibilityElement } ?? []
        XCTAssertEqual(days.count, 31)
        XCTAssertFalse(days.contains {
            $0.accessibilityIdentifier == "pam:calendar-day:2026-09-30"
        })
        let first = days.first {
            $0.accessibilityIdentifier == "pam:calendar-day:2026-10-01"
        }
        XCTAssertTrue(first?.accessibilityTraits.contains(.button) == true)
        XCTAssertTrue(first?.accessibilityLabel?.contains("October") == true)
        XCTAssertEqual(first?.accessibilityFrameInContainerSpace.minY, 96)
        XCTAssertTrue(first?.accessibilityActivate() == true)
        XCTAssertEqual(changes, ["2026-10-01"])

        let disabled = days.first {
            $0.accessibilityIdentifier == "pam:calendar-day:2026-10-02"
        }
        XCTAssertTrue(disabled?.accessibilityTraits.contains(.notEnabled) == true)
        XCTAssertFalse(disabled?.accessibilityActivate() == true)
        XCTAssertEqual(changes.count, 1)
        host.releaseCallbacks()
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

    func testDateTimePickerUsesNativeValueFormatsAndInitialSelection() {
        let host = PamMobileUiHost { _, _ in }
        host.update([
            "behavior": .integer(17),
            "mode": .integer(4),
            "value": .text("2026-07-23"),
            "minimumDate": .text("2026-07-01"),
            "maximumDate": .text("2026-07-31"),
        ])
        let datePicker = host.configuredDatePicker()
        XCTAssertEqual(datePicker.datePickerMode, .date)
        XCTAssertEqual(host.pickerValue(for: datePicker.date), "2026-07-23")

        host.update([
            "behavior": .integer(17),
            "mode": .integer(5),
            "value": .text("14:35"),
            "timeZoneOffsetInMinutes": .integer(-180),
            "is24Hour": .flag(true),
        ])
        let timePicker = host.configuredDatePicker()
        XCTAssertEqual(timePicker.datePickerMode, .time)
        XCTAssertEqual(host.pickerValue(for: timePicker.date), "14:35")
        XCTAssertNil(timePicker.minimumDate)
        XCTAssertNil(timePicker.maximumDate)

        host.update([
            "behavior": .integer(17),
            "mode": .integer(6),
            "value": .text("2026-10-04T14:35-03:00"),
            "timeZoneOffsetInMinutes": .integer(-180),
            "minDate": .text("2026-10-01"),
            "maxDate": .text("2026-10-31"),
        ])
        let dateTimePicker = host.configuredDatePicker()
        XCTAssertEqual(dateTimePicker.datePickerMode, .dateAndTime)
        XCTAssertEqual(
            host.pickerValue(for: dateTimePicker.date),
            "2026-10-04T14:35-03:00"
        )
        XCTAssertEqual(
            host.pickerValue(for: dateTimePicker.maximumDate!),
            "2026-10-31T23:59-03:00"
        )
        host.releaseCallbacks()
    }

    func testDateTimePickerDefaultsToDateTimeAndClampsInitialValue() {
        let host = PamMobileUiHost { _, _ in }
        host.update([
            "behavior": .integer(17),
            "value": .text("2026-11-01T09:30"),
            "minDate": .text("2026-10-01"),
            "maxDate": .text("2026-10-31"),
        ])
        let picker = host.configuredDatePicker()
        XCTAssertEqual(picker.datePickerMode, .dateAndTime)
        XCTAssertEqual(host.pickerValue(for: picker.date), "2026-10-31T23:59")
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

    func testRangeSliderTapUpdatesNearestEndpointAndEmitsPair() {
        var changes: [String] = []
        var ends: [String] = []
        let slider = PamMobileUiHost { kind, payload in
            let value = String(decoding: payload, as: UTF8.self)
            if kind == .change { changes.append(value) }
            if kind == .native { ends.append(value) }
        }
        slider.frame = CGRect(x: 0, y: 0, width: 320, height: 48)
        slider.update([
            "behavior": .integer(5), "range": .flag(true),
            "min": .decimal(0), "max": .decimal(100), "step": .decimal(10),
            "lowerValue": .decimal(20), "upperValue": .decimal(80),
            "thumbWidth": .decimal(20),
        ])
        XCTAssertTrue(slider.activateSlider(at: CGPoint(x: 100, y: 24)))
        XCTAssertEqual(slider.accessibilityValue, "30 to 80")
        XCTAssertTrue(slider.activateSlider(at: CGPoint(x: 220, y: 24)))
        XCTAssertEqual(slider.accessibilityValue, "30 to 70")
        XCTAssertEqual(changes, ["[30,80]", "[30,70]"])
        XCTAssertEqual(ends, changes)

        slider.update([
            "behavior": .integer(5), "range": .flag(true),
            "lowerValue": .decimal(30), "upperValue": .decimal(70),
            "readOnly": .flag(true),
        ])
        XCTAssertFalse(slider.activateSlider(at: CGPoint(x: 160, y: 24)))
        slider.accessibilityIncrement()
        XCTAssertEqual(changes.count, 2)
        XCTAssertEqual(ends.count, 2)
        slider.releaseCallbacks()
    }

    func testRangeSliderAccessibilityAdjustsUpperEndpointAndResetsPooledStep() {
        var changes: [String] = []
        var ends: [String] = []
        let slider = PamMobileUiHost { kind, payload in
            let value = String(decoding: payload, as: UTF8.self)
            if kind == .change { changes.append(value) }
            if kind == .native { ends.append(value) }
        }
        slider.update([
            "behavior": .integer(5), "range": .flag(true),
            "min": .decimal(0), "max": .decimal(100), "step": .decimal(10),
            "lowerValue": .decimal(30), "upperValue": .decimal(70),
        ])
        slider.accessibilityIncrement()
        slider.accessibilityDecrement()
        XCTAssertEqual(changes, ["[30,80]", "[30,70]"])
        XCTAssertEqual(ends, changes)
        XCTAssertEqual(slider.accessibilityValue, "30 to 70")

        slider.update([
            "behavior": .integer(5), "range": .flag(false),
            "value": .decimal(15),
        ])
        slider.accessibilityIncrement()
        XCTAssertEqual(changes.last, "16")
        XCTAssertEqual(ends.last, "16")
        slider.releaseCallbacks()
    }

    func testSliderStopsDragWhenItBecomesReadOnlyMidGesture() {
        var changes: [String] = []
        var ends: [String] = []
        let slider = PamMobileUiHost { kind, payload in
            let value = String(decoding: payload, as: UTF8.self)
            if kind == .change { changes.append(value) }
            if kind == .native { ends.append(value) }
        }
        slider.frame = CGRect(x: 0, y: 0, width: 320, height: 48)
        let configuration: [String: WireValue] = [
            "behavior": .integer(5), "range": .flag(true),
            "lowerValue": .decimal(20), "upperValue": .decimal(80),
            "thumbWidth": .decimal(20),
        ]
        slider.update(configuration)
        slider.updateSliderGesture(state: .began, at: CGPoint(x: 100, y: 24))
        XCTAssertEqual(slider.accessibilityValue, "30 to 80")

        slider.update(configuration)
        XCTAssertEqual(slider.accessibilityValue, "30 to 80")

        slider.update(configuration.merging(["readOnly": .flag(true)]) { _, new in new })
        XCTAssertEqual(slider.accessibilityValue, "20 to 80")
        slider.updateSliderGesture(state: .ended, at: CGPoint(x: 220, y: 24))
        XCTAssertTrue(changes.isEmpty)
        XCTAssertTrue(ends.isEmpty)

        slider.update(configuration.merging([
            "lowerValue": .decimal(40), "upperValue": .decimal(60),
        ]) { _, new in new })
        XCTAssertEqual(slider.accessibilityValue, "40 to 60")
        XCTAssertTrue(slider.activateSlider(at: CGPoint(x: 160, y: 24)))
        XCTAssertEqual(changes, ["[50,60]"])
        XCTAssertEqual(ends, changes)
        slider.releaseCallbacks()
    }

    func testSliderInputUsesVisibleThumbInsetInBothOrientations() {
        let slider = PamMobileUiHost { _, _ in }
        slider.frame = CGRect(x: 0, y: 0, width: 100, height: 48)
        slider.update([
            "behavior": .integer(5), "min": .decimal(0), "max": .decimal(100),
            "thumbWidth": .decimal(20),
        ])
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 10, y: 24)), 0)
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 90, y: 24)), 100)
        slider.update([
            "behavior": .integer(5), "min": .decimal(0), "max": .decimal(100),
            "thumbWidth": .decimal(20), "reversed": .flag(true),
        ])
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 10, y: 24)), 100)

        slider.frame = CGRect(x: 0, y: 0, width: 48, height: 200)
        slider.update([
            "behavior": .integer(5), "orientation": .integer(2),
            "min": .decimal(0), "max": .decimal(100),
            "thumbHeight": .decimal(20), "reversed": .flag(false),
        ])
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 24, y: 10)), 100)
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 24, y: 190)), 0)

        slider.frame = CGRect(x: 0, y: 0, width: 100, height: 48)
        slider.update([
            "behavior": .integer(5), "min": .decimal(0), "max": .decimal(100),
            "thumbWidth": .decimal(20),
        ])
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 10, y: 24)), 0)
        XCTAssertEqual(slider.sliderValue(at: CGPoint(x: 90, y: 24)), 100)
        slider.releaseCallbacks()
    }

    func testProgressAnnouncesValueWithoutOfferingSliderAdjustment() {
        var events = 0
        let progress = PamMobileUiHost { _, _ in events += 1 }
        progress.update([
            "behavior": .integer(12), "value": .decimal(40),
            "min": .decimal(0), "max": .decimal(100),
        ])
        XCTAssertEqual(progress.accessibilityValue, "40%")
        XCTAssertFalse(progress.accessibilityTraits.contains(.adjustable))
        progress.accessibilityIncrement()
        progress.accessibilityDecrement()
        XCTAssertEqual(progress.accessibilityValue, "40%")
        XCTAssertEqual(events, 0)

        progress.update([
            "behavior": .integer(12), "value": .decimal(15),
            "min": .decimal(10), "max": .decimal(30),
        ])
        XCTAssertEqual(progress.accessibilityValue, "25%")
        progress.releaseCallbacks()
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

    func testFormAndInputGroupLabelsFocusTheirNativeFields() {
        for behavior in [27, 29] {
            let host = PamMobileUiHost { _, _ in }
            host.frame = CGRect(x: 0, y: 0, width: 320, height: 100)
            host.update(["behavior": .integer(Int64(behavior))])
            let label = UILabel(frame: CGRect(x: 8, y: 0, width: 200, height: 32))
            label.text = "Email"
            if behavior == 29 { label.accessibilityIdentifier = "pam:form-label" }
            host.addSubview(label)
            let field = FocusProbeField(frame: CGRect(x: 8, y: 40, width: 200, height: 44))
            host.addSubview(field)

            XCTAssertFalse(host.focusInputFromLabel(at: CGPoint(x: 20, y: 70)))
            XCTAssertFalse(field.didFocus)
            XCTAssertTrue(host.focusInputFromLabel(at: CGPoint(x: 20, y: 16)))
            XCTAssertTrue(field.didFocus)

            host.update([
                "behavior": .integer(Int64(behavior)),
                "readOnly": .flag(true),
            ])
            field.didFocus = false
            XCTAssertFalse(host.focusInputFromLabel(at: CGPoint(x: 20, y: 16)))
            XCTAssertFalse(field.didFocus)
            host.releaseCallbacks()
        }
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

    func testPortaledMenuItemsKeepSelectionAndDismissTheirOwner() {
        var menuEvents = 0
        var presses = 0
        let window = UIWindow(frame: CGRect(x: 0, y: 0, width: 320, height: 640))
        let menu = PamMobileUiHost { kind, _ in
            if kind == .native { menuEvents += 1 }
        }
        menu.frame = window.bounds
        menu.update([
            "behavior": .integer(15),
            "initiallyOpen": .flag(true),
            "selectionMode": .integer(1),
        ])
        let trigger = UIView(frame: CGRect(x: 20, y: 30, width: 80, height: 44))
        trigger.accessibilityIdentifier = "pam:overlay-trigger"
        let content = UIView(frame: CGRect(x: 20, y: 82, width: 160, height: 100))
        content.accessibilityIdentifier = "pam:overlay-content"
        let first = PamMobileUiHost { kind, _ in
            if kind == .press { presses += 1 }
        }
        first.update(["behavior": .integer(25), "closeOnSelect": .flag(false)])
        let second = PamMobileUiHost { kind, _ in
            if kind == .press { presses += 1 }
        }
        second.update(["behavior": .integer(25), "closeOnSelect": .flag(true)])
        content.addSubview(first)
        content.addSubview(second)
        menu.addSubview(trigger)
        menu.addSubview(content)
        window.addSubview(menu)
        menu.setNeedsLayout()
        menu.layoutIfNeeded()

        XCTAssertTrue(content.superview === window)
        first.activateMenuItem()
        XCTAssertTrue(first.accessibilityTraits.contains(.selected))
        XCTAssertFalse(second.accessibilityTraits.contains(.selected))
        XCTAssertEqual(presses, 1)
        XCTAssertEqual(menuEvents, 0)

        second.activateMenuItem()
        XCTAssertTrue(second.accessibilityTraits.contains(.selected))
        XCTAssertFalse(first.accessibilityTraits.contains(.selected))
        XCTAssertEqual(presses, 2)
        XCTAssertEqual(menuEvents, 1)
        XCTAssertTrue(content.superview === menu)
        first.releaseCallbacks()
        second.releaseCallbacks()
        menu.releaseCallbacks()
    }

    func testAnchoredBackdropAndEscapeRespectDismissalSettings() {
        var dismissals = 0
        let window = UIWindow(frame: CGRect(x: 0, y: 0, width: 320, height: 640))
        let menu = PamMobileUiHost { kind, _ in
            if kind == .native { dismissals += 1 }
        }
        menu.frame = window.bounds
        menu.update([
            "behavior": .integer(15),
            "initiallyOpen": .flag(true),
            "closeOnOverlayClick": .flag(false),
            "isKeyboardDismissable": .flag(false),
        ])
        let trigger = UIView(frame: CGRect(x: 20, y: 30, width: 80, height: 44))
        trigger.accessibilityIdentifier = "pam:overlay-trigger"
        let content = UIView(frame: CGRect(x: 20, y: 82, width: 160, height: 100))
        content.accessibilityIdentifier = "pam:overlay-content"
        menu.addSubview(trigger)
        menu.addSubview(content)
        window.addSubview(menu)
        menu.setNeedsLayout()
        menu.layoutIfNeeded()
        XCTAssertTrue(content.superview === window)

        let catcher = window.subviews.compactMap { $0 as? UIControl }.first
        XCTAssertNotNil(catcher)
        catcher?.sendActions(for: .touchUpInside)
        XCTAssertEqual(dismissals, 0)
        XCTAssertTrue(content.superview === window)
        XCTAssertFalse(menu.accessibilityPerformEscape())

        menu.update([
            "behavior": .integer(15),
            "initiallyOpen": .flag(true),
            "closeOnOverlayClick": .flag(true),
            "isKeyboardDismissable": .flag(true),
        ])
        XCTAssertTrue(menu.accessibilityPerformEscape())
        XCTAssertEqual(dismissals, 1)
        XCTAssertTrue(content.superview === menu)
        XCTAssertFalse(menu.accessibilityPerformEscape())
        XCTAssertEqual(dismissals, 1)
        menu.releaseCallbacks()
    }

    func testFileTreeFolderExpandsAndEmitsPathChange() {
        var changes: [String] = []
        let tree = PamMobileUiHost { kind, data in
            if kind == .change { changes.append(String(decoding: data, as: UTF8.self)) }
        }
        tree.update(["behavior": .integer(32), "defaultExpandedPaths": .text("")])
        let folder = PamMobileUiHost { _, _ in }
        folder.update(["behavior": .integer(33), "path": .text("docs")])
        let content = UIView()
        content.accessibilityIdentifier = "pam:file-tree-content"
        folder.addSubview(content)
        tree.addSubview(folder)
        tree.layoutIfNeeded()
        XCTAssertTrue(content.isHidden)

        tree.activateFileTreeItem(folder)
        XCTAssertFalse(content.isHidden)
        XCTAssertEqual(folder.accessibilityValue, "Expanded")
        XCTAssertTrue(folder.accessibilityTraits.contains(.selected))
        XCTAssertEqual(changes, ["docs"])

        tree.activateFileTreeItem(folder)
        XCTAssertTrue(content.isHidden)
        XCTAssertEqual(folder.accessibilityValue, "Collapsed")
        XCTAssertEqual(changes, ["docs", "docs"])
        folder.releaseCallbacks()
        tree.releaseCallbacks()
    }

    func testFileTreeSelectionAppliesAuthoredColorsAndNativeNames() {
        let tree = PamMobileUiHost { _, _ in }
        tree.update(["behavior": .integer(32)])
        let folder = PamMobileUiHost { _, _ in }
        folder.update([
            "behavior": .integer(33),
            "path": .text("docs"),
            "foregroundColor": .integer(Int64(0xFF102030)),
            "selectedForegroundColor": .integer(Int64(0xFFFFFFFF)),
            "selectedContainerColor": .integer(Int64(0xFF223344)),
        ])
        let header = UIView()
        header.accessibilityIdentifier = "pam:file-tree-header"
        let name = UILabel()
        name.text = "Documents"
        name.accessibilityIdentifier = "pam:file-tree-name"
        header.addSubview(name)
        folder.addSubview(header)
        tree.addSubview(folder)
        tree.layoutIfNeeded()

        XCTAssertEqual(folder.accessibilityLabel, "Documents")
        XCTAssertEqual(name.textColor, UIColor(red: 16.0 / 255, green: 32.0 / 255,
                                               blue: 48.0 / 255, alpha: 1))
        tree.activateFileTreeItem(folder)
        XCTAssertEqual(header.backgroundColor, UIColor(red: 34.0 / 255,
                                                       green: 51.0 / 255,
                                                       blue: 68.0 / 255, alpha: 1))
        XCTAssertEqual(name.textColor, UIColor(red: 1, green: 1, blue: 1, alpha: 1))

        let file = PamMobileUiHost { _, _ in }
        file.update([
            "behavior": .integer(34),
            "path": .text("docs/guide.md"),
            "selectedContainerColor": .integer(Int64(0xFF223344)),
        ])
        let fileName = UILabel()
        fileName.text = "Guide"
        file.addSubview(fileName)
        tree.addSubview(file)
        tree.activateFileTreeItem(file)
        XCTAssertEqual(file.accessibilityLabel, "Guide")
        XCTAssertEqual(header.backgroundColor, .clear)
        XCTAssertEqual(file.backgroundColor, UIColor(red: 34.0 / 255,
                                                     green: 51.0 / 255,
                                                     blue: 68.0 / 255, alpha: 1))
        let plainFile = PamMobileUiHost { _, _ in }
        plainFile.update([
            "behavior": .integer(34),
            "path": .text("docs/plain.md"),
        ])
        tree.addSubview(plainFile)
        tree.activateFileTreeItem(plainFile)
        XCTAssertEqual(plainFile.backgroundColor, .clear)
        XCTAssertEqual(file.backgroundColor, .clear)
        XCTAssertEqual(plainFile.accessibilityLabel, "plain.md")
        plainFile.releaseCallbacks()
        file.releaseCallbacks()
        folder.releaseCallbacks()
        tree.releaseCallbacks()
    }

    func testTableHeaderCellsExposeHeadingSemantics() {
        let table = PamMobileUiHost { _, _ in }
        table.update(["behavior": .integer(30)])
        let headerRow = UIView()
        headerRow.accessibilityIdentifier = "pam:table-row:header"
        let headerCell = UILabel()
        headerCell.text = "Name"
        headerRow.addSubview(headerCell)
        let bodyRow = UIView()
        bodyRow.accessibilityIdentifier = "pam:table-row"
        let bodyCell = UILabel()
        bodyCell.text = "PAM Native"
        bodyRow.addSubview(bodyCell)
        table.addSubview(headerRow)
        table.addSubview(bodyRow)
        table.layoutIfNeeded()

        XCTAssertTrue(headerCell.accessibilityTraits.contains(.header))
        XCTAssertFalse(bodyCell.accessibilityTraits.contains(.header))
        headerRow.accessibilityIdentifier = "pam:table-row"
        table.setNeedsLayout()
        table.layoutIfNeeded()
        XCTAssertFalse(headerCell.accessibilityTraits.contains(.header))
        table.releaseCallbacks()
    }

    func testDisabledSparklineRejectsVoiceOverAdjustment() {
        var changes: [Data] = []
        let chart = PamMobileUiHost { kind, payload in
            if kind == .change { changes.append(payload) }
        }
        chart.update([
            "behavior": .integer(35),
            "interactive": .flag(true),
            "enabled": .flag(false),
            "values": .text("2,4,6"),
        ])
        chart.accessibilityIncrement()
        chart.accessibilityDecrement()
        XCTAssertTrue(changes.isEmpty)
        XCTAssertTrue(chart.accessibilityTraits.contains(.notEnabled))

        chart.update([
            "behavior": .integer(35),
            "interactive": .flag(true),
            "enabled": .flag(true),
            "values": .text("2,4,6"),
        ])
        chart.accessibilityIncrement()
        XCTAssertEqual(changes.count, 1)
        let event = try? JSONSerialization.jsonObject(with: changes[0]) as? [String: Any]
        XCTAssertEqual(event?["index"] as? Int, 0)
        XCTAssertEqual(event?["value"] as? Double, 2)
        chart.releaseCallbacks()
    }

    func testTooltipRespectsConfiguredLongPressDelay() {
        let tooltip = PamMobileUiHost { _, _ in }
        tooltip.update([
            "behavior": .integer(16),
            "openDelay": .integer(750),
            "closeDelay": .integer(125),
        ])
        let durations = tooltip.gestureRecognizers?
            .compactMap { $0 as? UILongPressGestureRecognizer }
            .map(\.minimumPressDuration) ?? []
        XCTAssertTrue(durations.contains(0.75))
        tooltip.releaseCallbacks()
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

    func testTabsControlledValueOverridesTriggerStateAndKeepsContentWrapperVisible() {
        var changes: [String] = []
        let tabs = PamMobileUiHost { kind, payload in
            if kind == .change { changes.append(String(decoding: payload, as: UTF8.self)) }
        }
        tabs.frame = CGRect(x: 0, y: 0, width: 320, height: 240)
        tabs.update([
            "behavior": .integer(6),
            "value": .text("account"),
            "activationMode": .integer(2),
        ])
        let account = PamMobileUiHost { _, _ in }
        account.update(["behavior": .integer(23), "value": .text("account")])
        let security = PamMobileUiHost { _, _ in }
        security.update(["behavior": .integer(23), "value": .text("security"), "selected": .flag(true)])
        tabs.addSubview(account)
        tabs.addSubview(security)
        let wrapper = UIView()
        wrapper.accessibilityIdentifier = "pam:tabs-content-wrapper"
        let accountContent = UIView()
        accountContent.accessibilityIdentifier = "pam:tabs-content:account"
        let securityContent = UIView()
        securityContent.accessibilityIdentifier = "pam:tabs-content:security"
        let forcedContent = UIView()
        forcedContent.accessibilityIdentifier = "pam:tabs-content-force:help"
        wrapper.addSubview(accountContent)
        wrapper.addSubview(securityContent)
        wrapper.addSubview(forcedContent)
        tabs.addSubview(wrapper)
        tabs.setNeedsLayout()
        tabs.layoutIfNeeded()

        XCTAssertTrue(account.accessibilityTraits.contains(.selected))
        XCTAssertFalse(security.accessibilityTraits.contains(.selected))
        XCTAssertFalse(wrapper.isHidden)
        XCTAssertFalse(accountContent.isHidden)
        XCTAssertTrue(securityContent.isHidden)
        XCTAssertFalse(forcedContent.isHidden)

        XCTAssertTrue(tabs.selectTab(security, emitChange: true))
        XCTAssertEqual(changes, ["security"])
        XCTAssertFalse(account.accessibilityTraits.contains(.selected))
        XCTAssertTrue(security.accessibilityTraits.contains(.selected))
        tabs.layoutIfNeeded()
        XCTAssertTrue(accountContent.isHidden)
        XCTAssertFalse(securityContent.isHidden)

        tabs.update(["behavior": .integer(6), "value": .text("account")])
        tabs.layoutIfNeeded()
        XCTAssertTrue(account.accessibilityTraits.contains(.selected))
        XCTAssertFalse(security.accessibilityTraits.contains(.selected))
        XCTAssertFalse(accountContent.isHidden)
        XCTAssertTrue(securityContent.isHidden)
        XCTAssertEqual(changes, ["security"])
        account.releaseCallbacks()
        security.releaseCallbacks()
        tabs.releaseCallbacks()
    }

    func testTabsKeyboardFocusHonorsManualAndAutomaticActivation() {
        var changes: [String] = []
        let tabs = PamMobileUiHost { kind, payload in
            if kind == .change { changes.append(String(decoding: payload, as: UTF8.self)) }
        }
        tabs.frame = CGRect(x: 0, y: 0, width: 320, height: 120)
        tabs.update([
            "behavior": .integer(6),
            "defaultValue": .text("first"),
            "activationMode": .integer(2),
        ])
        let first = PamMobileUiHost { _, _ in }
        first.update(["behavior": .integer(23), "value": .text("first")])
        let second = PamMobileUiHost { _, _ in }
        second.update(["behavior": .integer(23), "value": .text("second")])
        let disabled = PamMobileUiHost { _, _ in }
        disabled.update([
            "behavior": .integer(23), "value": .text("disabled"), "enabled": .flag(false),
        ])
        tabs.addSubview(first)
        tabs.addSubview(second)
        tabs.addSubview(disabled)
        tabs.setNeedsLayout()
        tabs.layoutIfNeeded()

        XCTAssertTrue(first.canBecomeFirstResponder)
        XCTAssertFalse(disabled.canBecomeFirstResponder)
        XCTAssertTrue(first.keyCommands?.contains(where: {
            $0.input == UIKeyCommand.inputRightArrow
        }) == true)
        XCTAssertTrue(tabs.moveTabFocus(from: first, direction: 1))
        XCTAssertTrue(first.accessibilityTraits.contains(.selected))
        XCTAssertFalse(second.accessibilityTraits.contains(.selected))
        XCTAssertTrue(changes.isEmpty)
        XCTAssertTrue(tabs.selectTab(second, emitChange: true))
        XCTAssertEqual(changes, ["second"])

        tabs.update(["behavior": .integer(6), "activationMode": .integer(1)])
        tabs.layoutIfNeeded()
        XCTAssertTrue(tabs.moveTabFocus(from: second, direction: 1))
        XCTAssertTrue(first.accessibilityTraits.contains(.selected))
        XCTAssertEqual(changes, ["second", "first"])
        XCTAssertTrue(tabs.moveTabFocus(from: first, direction: Int.max))
        XCTAssertTrue(second.accessibilityTraits.contains(.selected))
        XCTAssertEqual(changes, ["second", "first", "second"])
        XCTAssertFalse(tabs.selectTab(disabled, emitChange: true))
        first.releaseCallbacks()
        second.releaseCallbacks()
        disabled.releaseCallbacks()
        tabs.releaseCallbacks()
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

        for behavior in [3, 5] {
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

private final class FocusProbeField: UITextField {
    var didFocus = false

    override func becomeFirstResponder() -> Bool {
        didFocus = true
        return true
    }
}
