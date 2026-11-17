<?php

declare(strict_types=1);

namespace Pam\NativeUi;

use Pam\Native\Appearance;
use Pam\Native\AppearanceMode;

/**
 * Package-level entry points. Everything visual lives in the components;
 * this class only forwards to PAM Native primitives.
 */
final class NativeUi
{
    public const string VERSION = '2.0.0';

    private function __construct()
    {
    }

    /**
     * Light, dark or system appearance. PAM Native persists the choice
     * natively and restyles every component without remounting it.
     */
    public static function appearance(AppearanceMode $mode): void
    {
        if (Appearance::mode() !== $mode) {
            Appearance::set($mode);
        }
    }
}
