<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\MaterialShape;

final class MaterialTokens
{
    /** Material's 4 dp base grid. Layout spacing must be a multiple of this token. */
    public const float GRID = 4.0;

    public const float SPACE_EXTRA_SMALL = 4.0;
    public const float SPACE_SMALL = 8.0;
    public const float SPACE_MEDIUM = 12.0;
    public const float SPACE_LARGE = 16.0;
    public const float SPACE_EXTRA_LARGE = 24.0;
    public const float SPACE_DOUBLE_EXTRA_LARGE = 32.0;

    public const float COMPACT_WINDOW_GUTTER = 16.0;
    public const float MEDIUM_WINDOW_GUTTER = 24.0;
    public const float EXPANDED_WINDOW_GUTTER = 32.0;

    public const float MINIMUM_TOUCH_TARGET = 48.0;

    public const float OUTLINE_WIDTH = 1.0;
    public const float FOCUS_INDICATOR_WIDTH = 3.0;
    public const float DIVIDER_WIDTH = 1.0;

    public const float TOP_APP_BAR_SMALL_HEIGHT = 64.0;
    public const float TOP_APP_BAR_MEDIUM_HEIGHT = 112.0;
    public const float TOP_APP_BAR_LARGE_HEIGHT = 152.0;

    public const float BUTTON_SMALL_HEIGHT = 40.0;
    public const float BUTTON_SMALL_HORIZONTAL_SPACE = 16.0;
    public const float BUTTON_ICON_SIZE = 20.0;

    public const float FAB_SMALL_SIZE = 40.0;
    public const float FAB_SIZE = 56.0;
    public const float FAB_MEDIUM_SIZE = 80.0;
    public const float FAB_LARGE_SIZE = 96.0;

    public const float CHIP_HEIGHT = 32.0;
    public const float CHIP_RADIUS = 8.0;
    public const float CHIP_HORIZONTAL_SPACE = 16.0;
    public const float CHIP_ICON_SPACE = 8.0;
    public const float CHIP_ICON_SIZE = 18.0;

    public const float BADGE_DOT_SIZE = 6.0;
    public const float BADGE_LABEL_HEIGHT = 16.0;

    public const float CHECKBOX_ICON_SIZE = 18.0;
    public const float RADIO_ICON_SIZE = 20.0;
    public const float SELECTION_STATE_LAYER_SIZE = 40.0;

    public const float SLIDER_TRACK_HEIGHT = 16.0;
    public const float SLIDER_HANDLE_WIDTH = 4.0;
    public const float SLIDER_HANDLE_HEIGHT = 44.0;
    public const float SLIDER_HANDLE_TRACK_GAP = 6.0;
    public const float SLIDER_STATE_LAYER_SIZE = 40.0;
    public const float SLIDER_STOP_INDICATOR_SIZE = 4.0;
    public const float SLIDER_TICK_SIZE = 4.0;
    public const float SLIDER_TICK_LABEL_HEIGHT = 16.0;
    public const float SLIDER_TICK_LABEL_GAP = 8.0;
    public const float SLIDER_LABELLED_HEIGHT = 80.0;
    public const float SLIDER_TICK_LABELLED_HEIGHT = 72.0;
    public const float SLIDER_VALUE_AND_TICK_LABELLED_HEIGHT = 104.0;
    public const float SLIDER_VERTICAL_LABELLED_WIDTH = 112.0;

    public const float CAROUSEL_HEIGHT = 240.0;
    public const float CAROUSEL_RADIUS = 24.0;

    public const float CIRCULAR_PROGRESS_SIZE = 40.0;
    public const float PROGRESS_TRACK_THICKNESS = 4.0;

    public const float LIST_ONE_LINE_HEIGHT = 56.0;
    public const float LIST_TWO_LINE_HEIGHT = 72.0;
    public const float LIST_THREE_LINE_HEIGHT = 88.0;
    public const float LIST_HORIZONTAL_SPACE = 16.0;
    public const float LIST_VERTICAL_SPACE = 10.0;
    public const float LIST_CONTENT_GAP = 12.0;
    public const float LIST_ICON_SIZE = 24.0;

    public const float CARD_RADIUS = 12.0;
    public const float DIALOG_RADIUS = 28.0;
    public const float BOTTOM_SHEET_HANDLE_WIDTH = 32.0;
    public const float BOTTOM_SHEET_HANDLE_HEIGHT = 4.0;

    public const float SWITCH_TRACK_WIDTH = 52.0;
    public const float SWITCH_TRACK_HEIGHT = 32.0;
    public const float SWITCH_SELECTED_HANDLE_SIZE = 24.0;
    public const float SWITCH_UNSELECTED_HANDLE_SIZE = 16.0;

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
            MaterialDensity::Default => self::BUTTON_SMALL_HEIGHT,
            MaterialDensity::Comfortable => 36.0,
            MaterialDensity::Compact => 32.0,
        };
    }

    public static function iconButtonHeight(MaterialDensity $density): float
    {
        return self::buttonHeight($density);
    }

    public static function buttonHeightForSize(
        mixed $size,
        MaterialDensity $density,
    ): float {
        return match ($size) {
            'x-small', 'xs', ComponentSize::ExtraSmall->value => 32.0,
            'small', 'sm', ComponentSize::Small->value => 40.0,
            ComponentSize::Medium->value => 56.0,
            'large', 'lg', ComponentSize::Large->value => 96.0,
            'x-large', 'xl', ComponentSize::ExtraLarge->value => 136.0,
            default => self::buttonHeight($density),
        };
    }

    public static function buttonHorizontalSpace(mixed $size): float
    {
        return match ($size) {
            ComponentSize::Medium->value => 24.0,
            'large', 'lg', ComponentSize::Large->value => 48.0,
            'x-large', 'xl', ComponentSize::ExtraLarge->value => 64.0,
            default => self::BUTTON_SMALL_HORIZONTAL_SPACE,
        };
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
