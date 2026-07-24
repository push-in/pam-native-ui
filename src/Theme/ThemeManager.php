<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ThemeMode;

final class ThemeManager
{
    private static ThemeMode $mode = ThemeMode::System;
    private static ?Theme $customLight = null;
    private static ?Theme $customDark = null;
    private static ?bool $systemDark = null;
    private static ?Theme $registeredTheme = null;

    private function __construct()
    {
    }

    public static function mode(ThemeMode $mode): void
    {
        self::$mode = $mode;
    }

    public static function configuredMode(): ThemeMode
    {
        return self::$mode;
    }

    public static function systemDark(bool $dark): void
    {
        self::$systemDark = $dark;
    }

    public static function customize(?Theme $light = null, ?Theme $dark = null): void
    {
        self::$customLight = $light;
        self::$customDark = $dark;
        self::$registeredTheme = null;
    }

    public static function current(): Theme
    {
        $dark = match (self::$mode) {
            ThemeMode::Dark => true,
            ThemeMode::Light => false,
            ThemeMode::System => self::isSystemDark(),
        };

        $theme = $dark
            ? (self::$customDark ?? Themes::dark())
            : (self::$customLight ?? Themes::light());
        if (self::$registeredTheme !== $theme) {
            ThemeClassRegistry::apply($theme);
            self::$registeredTheme = $theme;
        }

        return $theme;
    }

    public static function resolvedMode(): ThemeMode
    {
        return match (self::$mode) {
            ThemeMode::Light => ThemeMode::Light,
            ThemeMode::Dark => ThemeMode::Dark,
            ThemeMode::System => self::isSystemDark() ? ThemeMode::Dark : ThemeMode::Light,
        };
    }

    public static function reset(): void
    {
        self::$mode = ThemeMode::System;
        self::$customLight = null;
        self::$customDark = null;
        self::$systemDark = null;
        self::$registeredTheme = null;
    }

    private static function isSystemDark(): bool
    {
        if (self::$systemDark !== null) {
            return self::$systemDark;
        }

        $value = getenv('PAM_SYSTEM_DARK');

        return $value !== false
            && in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
