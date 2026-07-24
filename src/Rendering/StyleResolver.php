<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Theme\Theme;
use Pam\Native\Align;
use Pam\Native\FlexDirection;
use Pam\Native\Justify;
use Pam\Native\Style;
use Pam\Native\TextAlignment;

final class StyleResolver
{
    private const array TEXT_PARTS = [
        'ActionsheetItemText',
        'ActionsheetSectionHeaderText',
        'AlertText',
        'AttachmentHoverCardText',
        'AvatarFallbackText',
        'BadgeText',
        'BottomSheetItemText',
        'ButtonText',
        'CalendarDayText',
        'CalendarHeaderTitle',
        'CalendarWeekDay',
        'CalendarWeekNumber',
        'FormControlErrorText',
        'FormControlHelperText',
        'FormControlLabelAstrick',
        'FormControlLabelText',
        'Heading',
        'LinkText',
        'MenuItemLabel',
        'MessageResponse',
        'ModelSelectorName',
        'RadioLabel',
        'SelectInput',
        'SelectSectionHeaderText',
        'TableCaption',
        'TableData',
        'TableHead',
        'Text',
        'ToastDescription',
        'ToastTitle',
        'TooltipText',
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $props
     */
    public static function resolve(string $part, array $props, Theme $theme): Style
    {
        $recipe = StyleRecipeResolver::classes($part, $props);

        if ($recipe !== null) {
            return TailwindStyleCompiler::compile($recipe, $props, $theme);
        }

        $size = is_int($props['size'] ?? null) ? $props['size'] : 3;
        $variant = is_int($props['variant'] ?? null) ? $props['variant'] : 1;
        $disabled = ($props['disabled'] ?? false) === true;

        if ($part === 'Icon' || str_ends_with($part, 'Icon')) {
            $iconSize = match ($props['size'] ?? 3) {
                '2xs' => 12.0,
                1, 'xs' => 14.0,
                2, 'sm' => 16.0,
                4, 'lg' => 20.0,
                5, 'xl' => 24.0,
                default => 18.0,
            };

            return new Style(
                width: $iconSize,
                height: $iconSize,
                opacity: $disabled ? 0.4 : 1.0,
            );
        }

        if (in_array($part, self::TEXT_PARTS, true) || str_ends_with($part, 'Text')) {
            $fontSize = match ($size) {
                1, 2 => 12.0,
                4 => 16.0,
                5 => 18.0,
                6 => 20.0,
                7 => 24.0,
                8 => 30.0,
                default => 14.0,
            };

            if ($part === 'Heading') {
                $fontSize = match ($size) {
                    1 => 12.0,
                    2 => 14.0,
                    3 => 16.0,
                    4 => 18.0,
                    5 => 20.0,
                    6 => 24.0,
                    7 => 30.0,
                    8 => 36.0,
                    default => 24.0,
                };
            }

            return new Style(
                textColor: self::textColor($part, $variant, $theme),
                fontSize: $fontSize,
                opacity: $disabled ? 0.4 : 1.0,
                textAlign: TextAlignment::Start,
                fontWeight: self::fontWeight($part, $props),
                lineHeight: max(16.0, $fontSize * 1.4),
            );
        }

        if ($part === 'Button') {
            return new Style(
                minHeight: match ($size) {
                    4 => 52.0,
                    default => 48.0,
                },
                minWidth: $size === 9 ? 48.0 : null,
                paddingHorizontal: match ($size) {
                    2 => 12.0,
                    4 => 32.0,
                    9 => 0.0,
                    default => 16.0,
                },
                paddingVertical: 8.0,
                gap: 8.0,
                backgroundColor: self::buttonBackground($variant, $theme),
                borderRadius: 6.0,
                borderWidth: $variant === 3 ? 1.0 : 0.0,
                borderColor: $theme->color(ColorToken::Border),
                opacity: $disabled ? 0.4 : 1.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                flexDirection: FlexDirection::Row,
            );
        }

        if (in_array($part, ['Input', 'Textarea', 'DateTimePicker'], true)) {
            return new Style(
                minHeight: $part === 'Textarea' ? 96.0 : 48.0,
                paddingHorizontal: 12.0,
                gap: 8.0,
                backgroundColor: $theme->color(ColorToken::Background),
                borderRadius: 6.0,
                borderWidth: 1.0,
                borderColor: $theme->color(ColorToken::Border),
                opacity: $disabled ? 0.5 : 1.0,
                alignItems: Align::Center,
                flexDirection: FlexDirection::Row,
            );
        }

        if ($part === 'Card') {
            return new Style(
                padding: match ($size) {
                    2 => 12.0,
                    4 => 24.0,
                    default => 16.0,
                },
                gap: 8.0,
                backgroundColor: $theme->color(ColorToken::Card),
                borderRadius: 12.0,
                borderWidth: 1.0,
                borderColor: $theme->color(ColorToken::Border),
                elevation: 1.0,
            );
        }

        if (in_array($part, ['Badge', 'Alert', 'Fab'], true)) {
            return new Style(
                minHeight: $part === 'Fab' ? 48.0 : 24.0,
                minWidth: $part === 'Fab' ? 48.0 : null,
                paddingHorizontal: $part === 'Alert' ? 16.0 : 8.0,
                paddingVertical: $part === 'Alert' ? 12.0 : 4.0,
                gap: 8.0,
                backgroundColor: match ($part) {
                    'Alert' => $theme->color(ColorToken::Muted),
                    'Fab' => $theme->color(ColorToken::Primary),
                    default => $variant === 2
                        ? $theme->color(ColorToken::Destructive)
                        : $theme->color(ColorToken::Secondary),
                },
                borderRadius: $part === 'Fab' ? 16.0 : 6.0,
                borderWidth: $part === 'Alert' ? 1.0 : 0.0,
                borderColor: $theme->color(ColorToken::Border),
                alignItems: Align::Center,
                flexDirection: FlexDirection::Row,
            );
        }

        if (in_array($part, ['Avatar', 'AvatarFallback', 'AvatarImage'], true)) {
            $diameter = match ($size) {
                1 => 24.0,
                2 => 32.0,
                4 => 48.0,
                5 => 64.0,
                default => 40.0,
            };

            return new Style(
                width: $diameter,
                height: $diameter,
                backgroundColor: $theme->color(ColorToken::Muted),
                borderRadius: $diameter / 2.0,
                overflow: \Pam\Native\Overflow::Hidden,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, ['Skeleton', 'SkeletonText'], true)) {
            return new Style(
                minHeight: $part === 'SkeletonText' ? 16.0 : 48.0,
                backgroundColor: $theme->color(ColorToken::Muted),
                borderRadius: 4.0,
            );
        }

        if ($part === 'Divider') {
            $horizontal = ($props['orientation'] ?? 1) === 1;

            return new Style(
                width: $horizontal ? null : 1.0,
                height: $horizontal ? 1.0 : null,
                minWidth: $horizontal ? 1.0 : null,
                minHeight: $horizontal ? null : 1.0,
                backgroundColor: $theme->color(ColorToken::Border),
            );
        }

        if ($part === 'HStack') {
            return new Style(
                gap: self::space($props),
                flexDirection: ($props['reversed'] ?? false)
                    ? FlexDirection::RowReverse
                    : FlexDirection::Row,
            );
        }

        if ($part === 'VStack') {
            return new Style(
                gap: self::space($props),
                flexDirection: ($props['reversed'] ?? false)
                    ? FlexDirection::ColumnReverse
                    : FlexDirection::Column,
            );
        }

        if ($part === 'Center') {
            return new Style(
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (
            str_contains($part, 'Content')
            || str_contains($part, 'Body')
            || str_contains($part, 'Header')
            || str_contains($part, 'Footer')
        ) {
            return new Style(
                padding: 16.0,
                gap: 12.0,
                backgroundColor: str_contains($part, 'Content')
                    ? $theme->color(ColorToken::Popover)
                    : null,
                borderRadius: str_contains($part, 'Content') ? 8.0 : null,
            );
        }

        return new Style(
            gap: isset($props['space']) ? self::space($props) : null,
            opacity: $disabled ? 0.4 : null,
        );
    }

    private static function buttonBackground(int $variant, Theme $theme): int
    {
        return match ($variant) {
            2 => $theme->color(ColorToken::Destructive),
            3, 5, 6 => 0x00000000,
            4 => $theme->color(ColorToken::Secondary),
            default => $theme->color(ColorToken::Primary),
        };
    }

    private static function textColor(string $part, int $variant, Theme $theme): int
    {
        if ($part === 'ButtonText') {
            return match ($variant) {
                2 => 0xffffffff,
                3, 5 => $theme->color(ColorToken::Foreground),
                4 => $theme->color(ColorToken::SecondaryForeground),
                6 => $theme->color(ColorToken::Primary),
                default => $theme->color(ColorToken::PrimaryForeground),
            };
        }

        if (str_contains($part, 'Description') || str_contains($part, 'Helper')) {
            return $theme->color(ColorToken::MutedForeground);
        }

        return $theme->color(ColorToken::Foreground);
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function fontWeight(string $part, array $props): int
    {
        if (($props['bold'] ?? false) === true || $part === 'Heading' || str_ends_with($part, 'Title')) {
            return 600;
        }

        return 400;
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function space(array $props): float
    {
        $space = $props['space'] ?? 3;

        return match ($space) {
            1, 'xs' => 4.0,
            2, 'sm' => 8.0,
            4, 'lg' => 16.0,
            5, 'xl' => 20.0,
            6, '2xl' => 24.0,
            7, '3xl' => 28.0,
            8, '4xl' => 32.0,
            default => 12.0,
        };
    }
}
