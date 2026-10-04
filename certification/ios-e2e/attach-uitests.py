#!/usr/bin/env python3
"""Attach the certification UI test target to a CLI-generated PAM Xcode host."""

from __future__ import annotations

import argparse
import shutil
from pathlib import Path


APP_TARGET = "500000000000000000000001"
TEST_TARGET = "500000000000000000000003"
TEST_GROUP = "400000000000000000000006"
TEST_SOURCE = "200000000000000000000007"
TEST_PRODUCT = "200000000000000000000008"
TEST_BUILD_FILE = "100000000000000000000008"
TEST_SOURCE_PHASE = "300000000000000000000004"
TEST_CONFIG_LIST = "700000000000000000000003"
TEST_DEBUG_CONFIG = "900000000000000000000005"
TEST_RELEASE_CONFIG = "900000000000000000000006"
TEST_DEPENDENCY = "A00000000000000000000001"
TEST_PROXY = "A00000000000000000000002"


def insert_before(source: str, marker: str, addition: str) -> str:
    count = source.count(marker)
    if count != 1:
        raise ValueError(f"Expected one {marker!r}, found {count}")
    return source.replace(marker, addition + marker, 1)


def replace_once(source: str, before: str, after: str) -> str:
    count = source.count(before)
    if count != 1:
        raise ValueError(f"Expected one {before!r}, found {count}")
    return source.replace(before, after, 1)


def attach(project: Path, tests: Path) -> None:
    pbx = project / "PamNativeApp.xcodeproj/project.pbxproj"
    source = pbx.read_text()
    if TEST_TARGET in source:
        raise ValueError("UI tests are already attached")

    source = insert_before(source, "/* End PBXBuildFile section */", f"\t\t{TEST_BUILD_FILE} /* FixtureUITests.swift in Sources */ = {{isa = PBXBuildFile; fileRef = {TEST_SOURCE} /* FixtureUITests.swift */; }};\n")
    source = insert_before(source, "/* End PBXFileReference section */", f"\t\t{TEST_SOURCE} /* FixtureUITests.swift */ = {{isa = PBXFileReference; lastKnownFileType = sourcecode.swift; path = FixtureUITests.swift; sourceTree = \"<group>\"; }};\n\t\t{TEST_PRODUCT} /* PamUiFixtureUITests.xctest */ = {{isa = PBXFileReference; explicitFileType = wrapper.cfbundle; path = PamUiFixtureUITests.xctest; sourceTree = BUILT_PRODUCTS_DIR; }};\n")
    source = insert_before(source, "/* End PBXGroup section */", f"\t\t{TEST_GROUP} /* CertificationUITests */ = {{isa = PBXGroup; children = ({TEST_SOURCE} /* FixtureUITests.swift */); path = CertificationUITests; sourceTree = \"<group>\"; }};\n")
    source = replace_once(source, "400000000000000000000004, 400000000000000000000005);", f"400000000000000000000004, {TEST_GROUP} /* CertificationUITests */, 400000000000000000000005);")
    source = replace_once(source, "200000000000000000000006); name = Products;", f"200000000000000000000006, {TEST_PRODUCT} /* PamUiFixtureUITests.xctest */); name = Products;")
    source = insert_before(source, "/* End PBXNativeTarget section */", f"\t\t{TEST_TARGET} /* PamUiFixtureUITests */ = {{isa = PBXNativeTarget; buildConfigurationList = {TEST_CONFIG_LIST}; buildPhases = ({TEST_SOURCE_PHASE}); buildRules = (); dependencies = ({TEST_DEPENDENCY}); name = PamUiFixtureUITests; productName = PamUiFixtureUITests; productReference = {TEST_PRODUCT}; productType = \"com.apple.product-type.bundle.ui-testing\"; }};\n")
    source = replace_once(source, f"targets = ({APP_TARGET});", f"targets = ({APP_TARGET}, {TEST_TARGET});")
    source = insert_before(source, "/* End PBXSourcesBuildPhase section */", f"\t\t{TEST_SOURCE_PHASE} = {{isa = PBXSourcesBuildPhase; buildActionMask = 2147483647; files = ({TEST_BUILD_FILE} /* FixtureUITests.swift in Sources */); runOnlyForDeploymentPostprocessing = 0; }};\n")
    source = insert_before(source, "/* Begin PBXFileReference section */", f"/* Begin PBXContainerItemProxy section */\n\t\t{TEST_PROXY} = {{isa = PBXContainerItemProxy; containerPortal = 500000000000000000000002; proxyType = 1; remoteGlobalIDString = {APP_TARGET}; remoteInfo = PamNativeApp; }};\n/* End PBXContainerItemProxy section */\n\n")
    source = insert_before(source, "/* Begin XCBuildConfiguration section */", f"/* Begin PBXTargetDependency section */\n\t\t{TEST_DEPENDENCY} = {{isa = PBXTargetDependency; target = {APP_TARGET}; targetProxy = {TEST_PROXY}; }};\n/* End PBXTargetDependency section */\n\n")
    source = insert_before(source, "/* End XCBuildConfiguration section */", f"\t\t{TEST_DEBUG_CONFIG} = {{isa = XCBuildConfiguration; buildSettings = {{CODE_SIGNING_ALLOWED = NO; GENERATE_INFOPLIST_FILE = YES; IPHONEOS_DEPLOYMENT_TARGET = 15.0; PRODUCT_BUNDLE_IDENTIFIER = dev.pam.mobileui.ioscertification.uitests; PRODUCT_NAME = \"$(TARGET_NAME)\"; SWIFT_VERSION = 5.0; TARGETED_DEVICE_FAMILY = \"1,2\"; TEST_TARGET_NAME = PamNativeApp; }}; name = Debug; }};\n\t\t{TEST_RELEASE_CONFIG} = {{isa = XCBuildConfiguration; buildSettings = {{CODE_SIGNING_ALLOWED = NO; GENERATE_INFOPLIST_FILE = YES; IPHONEOS_DEPLOYMENT_TARGET = 15.0; PRODUCT_BUNDLE_IDENTIFIER = dev.pam.mobileui.ioscertification.uitests; PRODUCT_NAME = \"$(TARGET_NAME)\"; SWIFT_VERSION = 5.0; TARGETED_DEVICE_FAMILY = \"1,2\"; TEST_TARGET_NAME = PamNativeApp; }}; name = Release; }};\n")
    source = insert_before(source, "/* End XCConfigurationList section */", f"\t\t{TEST_CONFIG_LIST} = {{isa = XCConfigurationList; buildConfigurations = ({TEST_DEBUG_CONFIG}, {TEST_RELEASE_CONFIG}); defaultConfigurationIsVisible = 0; defaultConfigurationName = Debug; }};\n")

    target_dir = project / "CertificationUITests"
    target_dir.mkdir(exist_ok=True)
    shutil.copy2(tests / "FixtureUITests.swift", target_dir / "FixtureUITests.swift")
    scheme_dir = project / "PamNativeApp.xcodeproj/xcshareddata/xcschemes"
    scheme_dir.mkdir(parents=True, exist_ok=True)
    scheme_dir.joinpath("PamNativeApp.xcscheme").write_text(f"""<?xml version="1.0" encoding="UTF-8"?>
<Scheme LastUpgradeVersion="1600" version="1.3">
  <BuildAction parallelizeBuildables="YES" buildImplicitDependencies="YES">
    <BuildActionEntries>
      <BuildActionEntry buildForTesting="YES" buildForRunning="YES" buildForProfiling="NO" buildForArchiving="NO" buildForAnalyzing="YES">
        <BuildableReference BuildableIdentifier="primary" BlueprintIdentifier="{APP_TARGET}" BuildableName="PamNativeApp.app" BlueprintName="PamNativeApp" ReferencedContainer="container:PamNativeApp.xcodeproj" />
      </BuildActionEntry>
    </BuildActionEntries>
  </BuildAction>
  <TestAction buildConfiguration="Debug" selectedDebuggerIdentifier="Xcode.DebuggerFoundation.Debugger.LLDB" selectedLauncherIdentifier="Xcode.IDEFoundation.Launcher.Posix" shouldUseLaunchSchemeArgsEnv="YES">
    <Testables>
      <TestableReference skipped="NO">
        <BuildableReference BuildableIdentifier="primary" BlueprintIdentifier="{TEST_TARGET}" BuildableName="PamUiFixtureUITests.xctest" BlueprintName="PamUiFixtureUITests" ReferencedContainer="container:PamNativeApp.xcodeproj" />
      </TestableReference>
    </Testables>
  </TestAction>
  <LaunchAction buildConfiguration="Debug" selectedDebuggerIdentifier="Xcode.DebuggerFoundation.Debugger.LLDB" selectedLauncherIdentifier="Xcode.IDEFoundation.Launcher.Posix" launchStyle="0" useCustomWorkingDirectory="NO" ignoresPersistentStateOnLaunch="NO" debugDocumentVersioning="YES" debugServiceExtension="internal" allowLocationSimulation="YES">
    <BuildableProductRunnable runnableDebuggingMode="0">
      <BuildableReference BuildableIdentifier="primary" BlueprintIdentifier="{APP_TARGET}" BuildableName="PamNativeApp.app" BlueprintName="PamNativeApp" ReferencedContainer="container:PamNativeApp.xcodeproj" />
    </BuildableProductRunnable>
  </LaunchAction>
</Scheme>
""")
    pbx.write_text(source)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("project", type=Path)
    args = parser.parse_args()
    attach(args.project, Path(__file__).with_name("UITests"))
