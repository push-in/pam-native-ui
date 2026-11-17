<?php

declare(strict_types=1);

namespace Pam\MobileUi;

use Pam\MobileUi\Enum\ThemeMode;
use Pam\NativeUi\NativeUi;

/**
 * @deprecated 2.0 Use {@see NativeUi::appearance()}. Kept so 1.x entry points
 *             (`PamUI::mode(ThemeMode::System)`) keep booting.
 */
final class PamUI
{
    private function __construct()
    {
    }

    public static function mode(ThemeMode $mode): void
    {
        NativeUi::appearance($mode->appearance());
    }
}
