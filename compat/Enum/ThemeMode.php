<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

use Pam\Native\AppearanceMode;

/**
 * @deprecated 2.0 Use {@see AppearanceMode} from PAM Native.
 */
enum ThemeMode: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function appearance(): AppearanceMode
    {
        return match ($this) {
            self::System => AppearanceMode::System,
            self::Light => AppearanceMode::Light,
            self::Dark => AppearanceMode::Dark,
        };
    }
}
