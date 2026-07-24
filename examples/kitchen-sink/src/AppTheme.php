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
                ColorToken::Primary->value => Color::rgb(37, 99, 235),
                ColorToken::Ring->value => Color::rgb(96, 165, 250),
            ]),
            Themes::dark()->withColors([
                ColorToken::Primary->value => Color::rgb(147, 197, 253),
                ColorToken::PrimaryForeground->value => Color::rgb(23, 37, 84),
                ColorToken::Ring->value => Color::rgb(96, 165, 250),
            ]),
        );
    }
}
