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
                ColorToken::Primary->value => Color::rgb(29, 78, 216),
                ColorToken::Accent->value => Color::rgb(219, 234, 254),
                ColorToken::AccentForeground->value => Color::rgb(30, 64, 175),
                ColorToken::Focus->value => Color::rgb(37, 99, 235),
                ColorToken::Ring->value => Color::rgb(59, 130, 246),
            ]),
            Themes::dark()->withColors([
                ColorToken::Primary->value => Color::rgb(96, 165, 250),
                ColorToken::PrimaryForeground->value => Color::rgb(15, 23, 42),
                ColorToken::Accent->value => Color::rgb(30, 58, 138),
                ColorToken::AccentForeground->value => Color::rgb(219, 234, 254),
                ColorToken::Focus->value => Color::rgb(96, 165, 250),
                ColorToken::Ring->value => Color::rgb(96, 165, 250),
            ]),
        );
    }
}
