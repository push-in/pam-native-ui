<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\InterfaceDensity;

final class DesignTokens
{
    private function __construct()
    {
    }

    public const float RADIUS_SMALL = 8.0;
    public const float RADIUS_MEDIUM = 12.0;
    public const float RADIUS_LARGE = 16.0;
    public const float RADIUS_XLARGE = 24.0;
    public const float RADIUS_FULL = 999.0;

    public const int MOTION_FAST = 150;
    public const int MOTION_STANDARD = 240;
    public const int MOTION_EMPHASIZED = 360;

    public const float TEXT_LABEL = 13.0;
    public const float TEXT_BODY = 16.0;
    public const float TEXT_TITLE = 20.0;
    public const float TEXT_HEADLINE = 28.0;
    public const float TEXT_DISPLAY = 36.0;

    public static function spacing(InterfaceDensity $density): float
    {
        return match ($density) {
            InterfaceDensity::Compact => 12.0,
            InterfaceDensity::Comfortable => 16.0,
            InterfaceDensity::Spacious => 24.0,
        };
    }

    public static function controlHeight(InterfaceDensity $density): float
    {
        return match ($density) {
            InterfaceDensity::Compact => 48.0,
            InterfaceDensity::Comfortable => 52.0,
            InterfaceDensity::Spacious => 56.0,
        };
    }
}
