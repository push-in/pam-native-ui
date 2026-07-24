<?php

declare(strict_types=1);

namespace Pam\MobileUi;

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Theme\Theme;
use Pam\MobileUi\Theme\ThemeManager;

/**
 * Global PamUI theme and appearance configuration.
 */
final class PamUI
{
    private function __construct()
    {
    }

    public static function mode(ThemeMode $mode): void
    {
        ThemeManager::mode($mode);
    }

    public static function theme(
        ?Theme $light = null,
        ?Theme $dark = null,
    ): void {
        ThemeManager::customize($light, $dark);
    }

    public static function systemDark(bool $dark): void
    {
        ThemeManager::systemDark($dark);
    }

    public static function currentTheme(): Theme
    {
        return ThemeManager::current();
    }
}
