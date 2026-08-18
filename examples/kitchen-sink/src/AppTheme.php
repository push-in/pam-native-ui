<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\PamUI;
use Pam\MobileUi\Theme\Themes;

final class AppTheme
{
    private function __construct()
    {
    }

    public static function install(): void
    {
        PamUI::theme(Themes::pamLight(), Themes::pamDark());
    }
}
