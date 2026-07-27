<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\MaterialShape;

final class MaterialTokens
{
    public const array BREAKPOINTS = [
        1 => 0,
        2 => 600,
        3 => 840,
        4 => 1145,
        5 => 1545,
        6 => 2138,
    ];

    public const array ELEVATIONS = [
        0 => 0.0,
        1 => 1.0,
        2 => 3.0,
        3 => 6.0,
        4 => 8.0,
        5 => 12.0,
    ];

    public const array TYPE_SCALE = [
        1 => [57.0, 64.0, 400.0, -0.25],
        2 => [45.0, 52.0, 400.0, 0.0],
        3 => [36.0, 44.0, 400.0, 0.0],
        4 => [32.0, 40.0, 400.0, 0.0],
        5 => [28.0, 36.0, 400.0, 0.0],
        6 => [24.0, 32.0, 400.0, 0.0],
        7 => [22.0, 28.0, 400.0, 0.0],
        8 => [16.0, 24.0, 500.0, 0.15],
        9 => [14.0, 20.0, 500.0, 0.1],
        10 => [12.0, 16.0, 500.0, 0.1],
        11 => [16.0, 24.0, 400.0, 0.5],
        12 => [14.0, 20.0, 400.0, 0.25],
        13 => [12.0, 16.0, 400.0, 0.4],
        14 => [14.0, 20.0, 500.0, 0.1],
        15 => [12.0, 16.0, 500.0, 0.5],
        16 => [11.0, 16.0, 500.0, 0.5],
        17 => [14.0, 20.0, 400.0, 0.1],
    ];

    public const array STATE_OPACITY = [
        1 => 0.08,
        2 => 0.10,
        3 => 0.10,
        4 => 0.12,
        5 => 0.12,
        6 => 0.38,
    ];

    public const array MOTION_DURATION = [
        1 => 50,
        2 => 100,
        3 => 150,
        4 => 200,
        5 => 250,
        6 => 300,
        7 => 350,
        8 => 400,
        9 => 450,
        10 => 500,
        11 => 550,
        12 => 600,
        13 => 700,
        14 => 800,
        15 => 900,
        16 => 1000,
    ];

    public const array EASING = [
        1 => [0.4, 0.0, 0.2, 1.0],
        2 => [0.0, 0.0, 0.2, 1.0],
        3 => [0.4, 0.0, 1.0, 1.0],
        4 => [0.2, 0.0, 1.0, 1.0],
        5 => [0.3, 0.0, 0.8, 0.15],
    ];

    private function __construct()
    {
    }

    public static function radius(MaterialShape $shape): float
    {
        return match ($shape) {
            MaterialShape::None => 0.0,
            MaterialShape::ExtraSmall => 4.0,
            MaterialShape::Small => 8.0,
            MaterialShape::Medium => 12.0,
            MaterialShape::Large => 16.0,
            MaterialShape::ExtraLarge => 28.0,
            MaterialShape::Full => 9999.0,
        };
    }

    public static function controlHeight(MaterialDensity $density): float
    {
        return self::fieldHeight($density);
    }

    public static function buttonHeight(MaterialDensity $density): float
    {
        return match ($density) {
            MaterialDensity::Default => 36.0,
            MaterialDensity::Comfortable => 32.0,
            MaterialDensity::Compact => 28.0,
        };
    }

    public static function iconButtonHeight(MaterialDensity $density): float
    {
        return self::buttonHeight($density);
    }

    public static function fieldHeight(MaterialDensity $density): float
    {
        return match ($density) {
            MaterialDensity::Default => 56.0,
            MaterialDensity::Comfortable => 48.0,
            MaterialDensity::Compact => 40.0,
        };
    }

    public static function componentSize(
        mixed $size,
        float $base,
        float $step = 8.0,
    ): float {
        $scale = match ($size) {
            'x-small', 'xs', ComponentSize::ExtraSmall->value => -2.0,
            'small', 'sm', ComponentSize::Small->value => -1.0,
            ComponentSize::Medium->value => 0.0,
            'large', 'lg', ComponentSize::Large->value => 1.0,
            'x-large', 'xl', ComponentSize::ExtraLarge->value => 2.0,
            default => 0.0,
        };

        return max($step, $base + ($scale * $step));
    }
}
