<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\Themes;

final class AppTheme
{
    private function __construct()
    {
    }

    public static function install(): void
    {
        PamUI::theme(
            Themes::light()->withColors([
                ColorToken::Primary->value => Color::rgb(43, 111, 246),
                ColorToken::PrimaryForeground->value => Color::rgb(255, 255, 255),
                ColorToken::Background->value => Color::rgb(244, 247, 251),
                ColorToken::Card->value => Color::rgb(255, 255, 255),
                ColorToken::Surface->value => Color::rgb(255, 255, 255),
                ColorToken::SurfaceElevated->value => Color::rgb(247, 250, 255),
                ColorToken::SurfaceSunken->value => Color::rgb(233, 239, 248),
                ColorToken::Border->value => Color::rgb(215, 224, 237),
                ColorToken::Input->value => Color::rgb(215, 224, 237),
                ColorToken::Muted->value => Color::rgb(233, 239, 248),
                ColorToken::MutedForeground->value => Color::rgb(83, 101, 126),
                ColorToken::Accent->value => Color::rgb(225, 235, 255),
                ColorToken::AccentForeground->value => Color::rgb(27, 78, 181),
                ColorToken::Success->value => Color::rgb(16, 147, 101),
                ColorToken::Focus->value => Color::rgb(43, 111, 246),
                ColorToken::Ring->value => Color::rgb(76, 141, 255),
            ]),
            Themes::dark()->withColors([
                ColorToken::Primary->value => Color::rgb(76, 141, 255),
                ColorToken::PrimaryForeground->value => Color::rgb(255, 255, 255),
                ColorToken::Background->value => Color::rgb(7, 17, 31),
                ColorToken::Card->value => Color::rgb(13, 27, 42),
                ColorToken::Surface->value => Color::rgb(13, 27, 42),
                ColorToken::SurfaceElevated->value => Color::rgb(19, 36, 58),
                ColorToken::SurfaceSunken->value => Color::rgb(5, 13, 24),
                ColorToken::Foreground->value => Color::rgb(244, 248, 255),
                ColorToken::OnSurface->value => Color::rgb(244, 248, 255),
                ColorToken::Secondary->value => Color::rgb(19, 36, 58),
                ColorToken::SecondaryForeground->value => Color::rgb(225, 234, 247),
                ColorToken::Muted->value => Color::rgb(19, 36, 58),
                ColorToken::MutedForeground->value => Color::rgb(143, 163, 190),
                ColorToken::Border->value => Color::rgb(37, 57, 82),
                ColorToken::Input->value => Color::rgb(37, 57, 82),
                ColorToken::Accent->value => Color::rgb(21, 54, 105),
                ColorToken::AccentForeground->value => Color::rgb(183, 209, 255),
                ColorToken::Success->value => Color::rgb(94, 230, 168),
                ColorToken::SuccessForeground->value => Color::rgb(4, 45, 31),
                ColorToken::Warning->value => Color::rgb(247, 185, 85),
                ColorToken::WarningForeground->value => Color::rgb(55, 32, 2),
                ColorToken::Info->value => Color::rgb(92, 190, 255),
                ColorToken::InfoForeground->value => Color::rgb(3, 39, 63),
                ColorToken::Focus->value => Color::rgb(76, 141, 255),
                ColorToken::Ring->value => Color::rgb(76, 141, 255),
            ]),
        );
    }
}
