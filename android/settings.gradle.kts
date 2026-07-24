pluginManagement {
    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        google()
        mavenCentral()
    }
}

rootProject.name = "pam-mobile-ui"

val pamNativeRoot = providers.gradleProperty("pamNativeRoot")
    .orElse(providers.environmentVariable("PAM_NATIVE_ROOT"))
    .orElse("../../pam-native")
    .get()

include(":plugin-api")
project(":plugin-api").projectDir = file("$pamNativeRoot/android/plugin-api")
