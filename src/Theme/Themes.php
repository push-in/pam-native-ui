<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ColorToken;

final class Themes
{
    private static ?Theme $light = null;
    private static ?Theme $dark = null;

    private function __construct()
    {
    }

    public static function light(): Theme
    {
        return self::$light ??= new Theme([
            ColorToken::Primary->value => Color::rgb(21, 128, 61)->argb,
            ColorToken::PrimaryForeground->value => Color::rgb(248, 250, 252)->argb,
            ColorToken::Card->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Secondary->value => Color::rgb(241, 245, 249)->argb,
            ColorToken::SecondaryForeground->value => Color::rgb(15, 23, 42)->argb,
            ColorToken::Background->value => Color::rgb(248, 250, 252)->argb,
            ColorToken::Popover->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::PopoverForeground->value => Color::rgb(15, 23, 42)->argb,
            ColorToken::Muted->value => Color::rgb(241, 245, 249)->argb,
            ColorToken::MutedForeground->value => Color::rgb(71, 85, 105)->argb,
            ColorToken::Destructive->value => Color::rgb(220, 38, 38)->argb,
            ColorToken::Foreground->value => Color::rgb(15, 23, 42)->argb,
            ColorToken::Border->value => Color::rgb(203, 213, 225)->argb,
            ColorToken::Input->value => Color::rgb(203, 213, 225)->argb,
            ColorToken::Ring->value => Color::rgb(34, 197, 94)->argb,
            ColorToken::Accent->value => Color::rgb(220, 252, 231)->argb,
            ColorToken::AccentForeground->value => Color::rgb(22, 101, 52)->argb,
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Surface->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::SurfaceElevated->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::SurfaceSunken->value => Color::rgb(241, 245, 249)->argb,
            ColorToken::Success->value => Color::rgb(21, 128, 61)->argb,
            ColorToken::SuccessForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Warning->value => Color::rgb(180, 83, 9)->argb,
            ColorToken::WarningForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Info->value => Color::rgb(3, 105, 161)->argb,
            ColorToken::InfoForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Focus->value => Color::rgb(22, 163, 74)->argb,
            ColorToken::Overlay->value => Color::rgb(15, 23, 42, 153)->argb,
            ColorToken::OnSurface->value => Color::rgb(15, 23, 42)->argb,
        ]);
    }

    public static function dark(): Theme
    {
        return self::$dark ??= new Theme([
            ColorToken::Primary->value => Color::rgb(34, 197, 94)->argb,
            ColorToken::PrimaryForeground->value => Color::rgb(5, 46, 22)->argb,
            ColorToken::Card->value => Color::rgb(23, 32, 51)->argb,
            ColorToken::Secondary->value => Color::rgb(30, 41, 59)->argb,
            ColorToken::SecondaryForeground->value => Color::rgb(248, 250, 252)->argb,
            ColorToken::Background->value => Color::rgb(15, 23, 42)->argb,
            ColorToken::Popover->value => Color::rgb(23, 32, 51)->argb,
            ColorToken::PopoverForeground->value => Color::rgb(248, 250, 252)->argb,
            ColorToken::Muted->value => Color::rgb(39, 47, 66)->argb,
            ColorToken::MutedForeground->value => Color::rgb(148, 163, 184)->argb,
            ColorToken::Destructive->value => Color::rgb(248, 113, 113)->argb,
            ColorToken::Foreground->value => Color::rgb(248, 250, 252)->argb,
            ColorToken::Border->value => Color::rgb(71, 85, 105)->argb,
            ColorToken::Input->value => Color::rgb(51, 65, 85)->argb,
            ColorToken::Ring->value => Color::rgb(74, 222, 128)->argb,
            ColorToken::Accent->value => Color::rgb(30, 64, 42)->argb,
            ColorToken::AccentForeground->value => Color::rgb(187, 247, 208)->argb,
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Surface->value => Color::rgb(23, 32, 51)->argb,
            ColorToken::SurfaceElevated->value => Color::rgb(30, 41, 59)->argb,
            ColorToken::SurfaceSunken->value => Color::rgb(9, 15, 28)->argb,
            ColorToken::Success->value => Color::rgb(74, 222, 128)->argb,
            ColorToken::SuccessForeground->value => Color::rgb(5, 46, 22)->argb,
            ColorToken::Warning->value => Color::rgb(251, 191, 36)->argb,
            ColorToken::WarningForeground->value => Color::rgb(69, 26, 3)->argb,
            ColorToken::Info->value => Color::rgb(56, 189, 248)->argb,
            ColorToken::InfoForeground->value => Color::rgb(8, 47, 73)->argb,
            ColorToken::Focus->value => Color::rgb(74, 222, 128)->argb,
            ColorToken::Overlay->value => Color::rgb(2, 6, 23, 184)->argb,
            ColorToken::OnSurface->value => Color::rgb(248, 250, 252)->argb,
        ]);
    }
}
