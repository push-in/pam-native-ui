import XCTest

final class FixtureUITests: XCTestCase {
    private let app = XCUIApplication()

    override func setUpWithError() throws {
        continueAfterFailure = false
        app.launch()
        XCTAssertTrue(app.staticTexts["fixture-title"].waitForExistence(timeout: 20))
    }

    func testAutocompleteSearchAndSelection() {
        let trigger = app.descendants(matching: .any)
            .matching(identifier: "fixture-autocomplete").firstMatch
        XCTAssertTrue(trigger.waitForExistence(timeout: 10))
        let labeledTrigger = app.descendants(matching: .any)
            .matching(NSPredicate(format: "label == %@", "Component")).firstMatch
        XCTAssertTrue(labeledTrigger.waitForExistence(timeout: 10))
        labeledTrigger.tap()

        let search = app.textFields["Find a component"]
        XCTAssertTrue(search.waitForExistence(timeout: 10))
        search.tap()
        search.typeText("Dia")
        let option = app.buttons["Dialog"]
        XCTAssertTrue(option.waitForExistence(timeout: 10))
        capture("autocomplete-filtered")
        option.tap()
        XCTAssertFalse(search.waitForExistence(timeout: 2))
        capture("autocomplete-selected")
    }

    func testComboboxSearchAndSelection() {
        let trigger = app.descendants(matching: .any)
            .matching(identifier: "fixture-combobox").firstMatch
        XCTAssertTrue(trigger.waitForExistence(timeout: 10))
        let labeledTrigger = app.descendants(matching: .any)
            .matching(NSPredicate(format: "label == %@", "Capability")).firstMatch
        XCTAssertTrue(labeledTrigger.waitForExistence(timeout: 10))
        labeledTrigger.tap()

        let search = app.textFields["Find a capability"]
        XCTAssertTrue(search.waitForExistence(timeout: 10))
        search.tap()
        search.typeText("Real")
        let option = app.buttons["Realtime"]
        XCTAssertTrue(option.waitForExistence(timeout: 10))
        capture("combobox-filtered")
        option.tap()
        XCTAssertFalse(search.waitForExistence(timeout: 2))
        capture("combobox-selected")
    }

    func testOtpAcceptsSixDigitsAndExposesInputSemantics() {
        let host = app.descendants(matching: .any)
            .matching(identifier: "fixture-otp").firstMatch
        XCTAssertTrue(host.waitForExistence(timeout: 10))
        let input = app.textFields["Verification code"]
        XCTAssertTrue(input.waitForExistence(timeout: 10))
        XCTAssertTrue(input.isEnabled)
        input.tap()
        input.typeText("416283")
        XCTAssertEqual(input.value as? String, "416283")
        capture("otp-complete")
    }

    private func capture(_ name: String) {
        let attachment = XCTAttachment(screenshot: app.screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
    }
}
