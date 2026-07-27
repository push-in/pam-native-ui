// swift-tools-version: 5.9

import Foundation
import PackageDescription

let pamNativePath = ProcessInfo.processInfo.environment["PAM_NATIVE_IOS_PATH"]
    ?? "../../pam-native/ios"

let package = Package(
    name: "PamMobileUi",
    platforms: [
        .iOS(.v15),
    ],
    products: [
        .library(name: "PamMobileUi", targets: ["PamMobileUi"]),
    ],
    dependencies: [
        .package(name: "PamNative", path: pamNativePath),
    ],
    targets: [
        .target(
            name: "PamMobileUi",
            dependencies: [
                .product(name: "PamNative", package: "PamNative"),
            ],
            path: "Sources/PamMobileUi"
        ),
        .testTarget(
            name: "PamMobileUiTests",
            dependencies: ["PamMobileUi", "PamNative"],
            path: "Tests/PamMobileUiTests"
        ),
    ],
    swiftLanguageVersions: [.v5]
)
