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
            ColorToken::Primary->value => Color::rgb(24, 103, 192)->argb,
            ColorToken::PrimaryForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Card->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Secondary->value => Color::rgb(72, 169, 166)->argb,
            ColorToken::SecondaryForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Background->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Popover->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::PopoverForeground->value => Color::rgb(0, 0, 0, 222)->argb,
            ColorToken::Muted->value => Color::rgb(238, 238, 238)->argb,
            ColorToken::MutedForeground->value => Color::rgb(0, 0, 0, 153)->argb,
            ColorToken::Destructive->value => Color::rgb(176, 0, 32)->argb,
            ColorToken::Foreground->value => Color::rgb(0, 0, 0, 222)->argb,
            ColorToken::Border->value => Color::rgb(0, 0, 0, 31)->argb,
            ColorToken::Input->value => Color::rgb(0, 0, 0, 31)->argb,
            ColorToken::Ring->value => Color::rgb(24, 103, 192)->argb,
            ColorToken::Accent->value => Color::rgb(232, 241, 250)->argb,
            ColorToken::AccentForeground->value => Color::rgb(31, 85, 146)->argb,
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Surface->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::SurfaceElevated->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::SurfaceSunken->value => Color::rgb(238, 238, 238)->argb,
            ColorToken::Success->value => Color::rgb(76, 175, 80)->argb,
            ColorToken::SuccessForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Warning->value => Color::rgb(251, 140, 0)->argb,
            ColorToken::WarningForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Info->value => Color::rgb(33, 150, 243)->argb,
            ColorToken::InfoForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Focus->value => Color::rgb(24, 103, 192)->argb,
            ColorToken::Overlay->value => Color::rgb(0, 0, 0, 82)->argb,
            ColorToken::OnSurface->value => Color::rgb(0, 0, 0, 222)->argb,
        ]);
    }

    public static function dark(): Theme
    {
        return self::$dark ??= new Theme([
            ColorToken::Primary->value => Color::rgb(33, 150, 243)->argb,
            ColorToken::PrimaryForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Card->value => Color::rgb(33, 33, 33)->argb,
            ColorToken::Secondary->value => Color::rgb(84, 182, 178)->argb,
            ColorToken::SecondaryForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Background->value => Color::rgb(18, 18, 18)->argb,
            ColorToken::Popover->value => Color::rgb(33, 33, 33)->argb,
            ColorToken::PopoverForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Muted->value => Color::rgb(66, 66, 66)->argb,
            ColorToken::MutedForeground->value => Color::rgb(255, 255, 255, 179)->argb,
            ColorToken::Destructive->value => Color::rgb(207, 102, 121)->argb,
            ColorToken::Foreground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Border->value => Color::rgb(255, 255, 255, 31)->argb,
            ColorToken::Input->value => Color::rgb(255, 255, 255, 31)->argb,
            ColorToken::Ring->value => Color::rgb(33, 150, 243)->argb,
            ColorToken::Accent->value => Color::rgb(39, 55, 68)->argb,
            ColorToken::AccentForeground->value => Color::rgb(144, 202, 249)->argb,
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Surface->value => Color::rgb(33, 33, 33)->argb,
            ColorToken::SurfaceElevated->value => Color::rgb(42, 42, 42)->argb,
            ColorToken::SurfaceSunken->value => Color::rgb(18, 18, 18)->argb,
            ColorToken::Success->value => Color::rgb(76, 175, 80)->argb,
            ColorToken::SuccessForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Warning->value => Color::rgb(251, 140, 0)->argb,
            ColorToken::WarningForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Info->value => Color::rgb(33, 150, 243)->argb,
            ColorToken::InfoForeground->value => Color::rgb(0, 0, 0)->argb,
            ColorToken::Focus->value => Color::rgb(33, 150, 243)->argb,
            ColorToken::Overlay->value => Color::rgb(0, 0, 0, 128)->argb,
            ColorToken::OnSurface->value => Color::rgb(255, 255, 255)->argb,
        ]);
    }
}
