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
            ColorToken::Primary->value => Color::rgb(23, 23, 23)->argb,
            ColorToken::PrimaryForeground->value => Color::rgb(250, 250, 250)->argb,
            ColorToken::Card->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Secondary->value => Color::rgb(245, 245, 245)->argb,
            ColorToken::SecondaryForeground->value => Color::rgb(23, 23, 23)->argb,
            ColorToken::Background->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::Popover->value => Color::rgb(255, 255, 255)->argb,
            ColorToken::PopoverForeground->value => Color::rgb(10, 10, 10)->argb,
            ColorToken::Muted->value => Color::rgb(245, 245, 245)->argb,
            ColorToken::MutedForeground->value => Color::rgb(115, 115, 115)->argb,
            ColorToken::Destructive->value => Color::rgb(231, 0, 11)->argb,
            ColorToken::Foreground->value => Color::rgb(10, 10, 10)->argb,
            ColorToken::Border->value => Color::rgb(229, 229, 229)->argb,
            ColorToken::Input->value => Color::rgb(229, 229, 229)->argb,
            ColorToken::Ring->value => Color::rgb(212, 212, 212)->argb,
            ColorToken::Accent->value => Color::rgb(247, 247, 247)->argb,
            ColorToken::AccentForeground->value => Color::rgb(52, 52, 52)->argb,
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255)->argb,
        ]);
    }

    public static function dark(): Theme
    {
        return self::$dark ??= new Theme([
            ColorToken::Primary->value => Color::rgb(255, 245, 245)->argb,
            ColorToken::PrimaryForeground->value => Color::rgb(23, 23, 23)->argb,
            ColorToken::Card->value => Color::rgb(23, 23, 23)->argb,
            ColorToken::Secondary->value => Color::rgb(38, 38, 38)->argb,
            ColorToken::SecondaryForeground->value => Color::rgb(250, 250, 250)->argb,
            ColorToken::Background->value => Color::rgb(10, 10, 10)->argb,
            ColorToken::Popover->value => Color::rgb(23, 23, 23)->argb,
            ColorToken::PopoverForeground->value => Color::rgb(250, 250, 250)->argb,
            ColorToken::Muted->value => Color::rgb(38, 38, 38)->argb,
            ColorToken::MutedForeground->value => Color::rgb(161, 161, 161)->argb,
            ColorToken::Destructive->value => Color::rgb(255, 100, 103)->argb,
            ColorToken::Foreground->value => Color::rgb(250, 250, 250)->argb,
            ColorToken::Border->value => Color::rgb(46, 46, 46)->argb,
            ColorToken::Input->value => Color::rgb(46, 46, 46)->argb,
            ColorToken::Ring->value => Color::rgb(115, 115, 115)->argb,
            ColorToken::Accent->value => Color::rgb(38, 38, 38)->argb,
            ColorToken::AccentForeground->value => Color::rgb(250, 250, 250)->argb,
            ColorToken::DestructiveForeground->value => Color::rgb(255, 255, 255)->argb,
        ]);
    }
}
