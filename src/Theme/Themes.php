<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ColorToken;

final class Themes
{
    private static ?Theme $light = null;
    private static ?Theme $dark = null;
    private static ?Theme $pamLight = null;
    private static ?Theme $pamDark = null;

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
            ColorToken::PrimaryForeground->value => Color::rgb(255, 255, 255)->argb,
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
            ColorToken::InfoForeground->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Focus->value => Color::rgb(33, 150, 243)->argb,
            ColorToken::Overlay->value => Color::rgb(0, 0, 0, 128)->argb,
            ColorToken::OnSurface->value => Color::rgb(255, 255, 255)->argb,
        ]);
    }

    public static function pamLight(): Theme
    {
        return self::$pamLight ??= self::light()->withColors([
            ColorToken::Primary->value => Color::rgb(22, 101, 52),
            ColorToken::PrimaryForeground->value => Color::rgb(255, 255, 255),
            ColorToken::Card->value => Color::rgb(255, 255, 255),
            ColorToken::Secondary->value => Color::rgb(51, 65, 85),
            ColorToken::SecondaryForeground->value => Color::rgb(255, 255, 255),
            ColorToken::Background->value => Color::rgb(248, 250, 252),
            ColorToken::Popover->value => Color::rgb(255, 255, 255),
            ColorToken::PopoverForeground->value => Color::rgb(15, 23, 42),
            ColorToken::Muted->value => Color::rgb(226, 232, 240),
            ColorToken::MutedForeground->value => Color::rgb(71, 85, 105),
            ColorToken::Destructive->value => Color::rgb(185, 28, 28),
            ColorToken::Foreground->value => Color::rgb(15, 23, 42),
            ColorToken::Border->value => Color::rgb(203, 213, 225),
            ColorToken::Input->value => Color::rgb(203, 213, 225),
            ColorToken::Ring->value => Color::rgb(22, 101, 52),
            ColorToken::Accent->value => Color::rgb(220, 252, 231),
            ColorToken::AccentForeground->value => Color::rgb(20, 83, 45),
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255),
            ColorToken::Surface->value => Color::rgb(248, 250, 252),
            ColorToken::SurfaceElevated->value => Color::rgb(255, 255, 255),
            ColorToken::SurfaceSunken->value => Color::rgb(241, 245, 249),
            ColorToken::Success->value => Color::rgb(21, 128, 61),
            ColorToken::SuccessForeground->value => Color::rgb(255, 255, 255),
            ColorToken::Warning->value => Color::rgb(133, 77, 14),
            ColorToken::WarningForeground->value => Color::rgb(255, 255, 255),
            ColorToken::Info->value => Color::rgb(29, 78, 216),
            ColorToken::InfoForeground->value => Color::rgb(255, 255, 255),
            ColorToken::Focus->value => Color::rgb(22, 101, 52),
            ColorToken::Overlay->value => Color::rgb(15, 23, 42, 140),
            ColorToken::OnSurface->value => Color::rgb(15, 23, 42),
        ]);
    }

    public static function pamDark(): Theme
    {
        return self::$pamDark ??= self::dark()->withColors([
            ColorToken::Primary->value => Color::rgb(74, 222, 128),
            ColorToken::PrimaryForeground->value => Color::rgb(5, 46, 22),
            ColorToken::Card->value => Color::rgb(17, 24, 39),
            ColorToken::Secondary->value => Color::rgb(148, 163, 184),
            ColorToken::SecondaryForeground->value => Color::rgb(15, 23, 42),
            ColorToken::Background->value => Color::rgb(11, 17, 32),
            ColorToken::Popover->value => Color::rgb(24, 34, 53),
            ColorToken::PopoverForeground->value => Color::rgb(248, 250, 252),
            ColorToken::Muted->value => Color::rgb(30, 41, 59),
            ColorToken::MutedForeground->value => Color::rgb(203, 213, 225),
            ColorToken::Destructive->value => Color::rgb(248, 113, 113),
            ColorToken::Foreground->value => Color::rgb(248, 250, 252),
            ColorToken::Border->value => Color::rgb(71, 85, 105),
            ColorToken::Input->value => Color::rgb(51, 65, 85),
            ColorToken::Ring->value => Color::rgb(134, 239, 172),
            ColorToken::Accent->value => Color::rgb(20, 83, 45),
            ColorToken::AccentForeground->value => Color::rgb(220, 252, 231),
            ColorToken::DestructiveForeground->value => Color::rgb(69, 10, 10),
            ColorToken::Surface->value => Color::rgb(17, 24, 39),
            ColorToken::SurfaceElevated->value => Color::rgb(24, 34, 53),
            ColorToken::SurfaceSunken->value => Color::rgb(11, 17, 32),
            ColorToken::Success->value => Color::rgb(74, 222, 128),
            ColorToken::SuccessForeground->value => Color::rgb(5, 46, 22),
            ColorToken::Warning->value => Color::rgb(251, 191, 36),
            ColorToken::WarningForeground->value => Color::rgb(66, 32, 6),
            ColorToken::Info->value => Color::rgb(96, 165, 250),
            ColorToken::InfoForeground->value => Color::rgb(8, 47, 73),
            ColorToken::Focus->value => Color::rgb(134, 239, 172),
            ColorToken::Overlay->value => Color::rgb(0, 0, 0, 153),
            ColorToken::OnSurface->value => Color::rgb(248, 250, 252),
        ]);
    }
}
