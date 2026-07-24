<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ThemeMode;

final class ThemeManager
{
    private static ThemeMode $mode = ThemeMode::System;
    private static ?Theme $customLight = null;
    private static ?Theme $customDark = null;
    private static bool $systemDark = false;

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
    }

    public static function current(): Theme
    {
        $dark = match (self::$mode) {
            ThemeMode::Dark => true,
            ThemeMode::Light => false,
            ThemeMode::System => self::$systemDark,
        };

        return $dark
            ? (self::$customDark ?? Themes::dark())
            : (self::$customLight ?? Themes::light());
    }

    public static function resolvedMode(): ThemeMode
    {
        return match (self::$mode) {
            ThemeMode::Light => ThemeMode::Light,
            ThemeMode::Dark => ThemeMode::Dark,
            ThemeMode::System => self::$systemDark ? ThemeMode::Dark : ThemeMode::Light,
        };
    }

    public static function reset(): void
    {
        self::$mode = ThemeMode::System;
        self::$customLight = null;
        self::$customDark = null;
        self::$systemDark = false;
    }
}
