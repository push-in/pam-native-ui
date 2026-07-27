// swift-tools-version: 5.9

import Foundation
import PackageDescription

let pamNativePath = ProcessInfo.processInfo.environment["PAM_NATIVE_IOS_PATH"]
    ?? "../pam-native/ios"

let package = Package(
    name: "PamMobileUi",
    platforms: [
        .iOS(.v15),
    ],
    products: [
        .library(name: "PamMobileUi", targets: ["PamMobileUi"]),
    ],
    dependencies: [
        .package(path: pamNativePath),
    ],
    targets: [
        .target(
            name: "PamMobileUi",
            dependencies: [
                .product(name: "PamNative", package: "ios"),
            ],
            path: "ios/Sources/PamMobileUi"
        ),
        .target(
            name: "PamMobileUiTestRuntimeShims",
            path: "ios/Tests/PamMobileUiTestRuntimeShims",
            publicHeadersPath: "include"
        ),
        .testTarget(
            name: "PamMobileUiTests",
            dependencies: [
                "PamMobileUi",
                "PamMobileUiTestRuntimeShims",
                .product(name: "PamNative", package: "ios"),
            ],
            path: "ios/Tests/PamMobileUiTests"
        ),
    ],
    swiftLanguageVersions: [.v5]
)
