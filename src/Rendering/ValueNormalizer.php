<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use BackedEnum;
use Pam\MobileUi\Enum\CalendarDayState;
use Pam\MobileUi\Enum\MaterialVariant;

final class ValueNormalizer
{
    /** @var array<string, array<string, int>> */
    private const array CODED_VALUES = [
        'size' => [
            'xs' => 1,
            'sm' => 2,
            'md' => 3,
            'default' => 3,
            'lg' => 4,
            'xl' => 5,
            '2xl' => 6,
            '3xl' => 7,
            '4xl' => 8,
            'icon' => 9,
            '2xs' => 10,
            '5xl' => 11,
            '6xl' => 12,
            'full' => 13,
            'none' => 14,
        ],
        'variant' => [
            'default' => MaterialVariant::Elevated->value,
            'elevated' => MaterialVariant::Elevated->value,
            'flat' => MaterialVariant::Flat->value,
            'tonal' => MaterialVariant::Tonal->value,
            'subtle' => MaterialVariant::Tonal->value,
            'outlined' => MaterialVariant::Outlined->value,
            'outline' => MaterialVariant::Outlined->value,
            'text' => MaterialVariant::Text->value,
            'plain' => MaterialVariant::Plain->value,
            'underlined' => MaterialVariant::Underlined->value,
            'filled' => MaterialVariant::Filled->value,
            'solid' => MaterialVariant::Filled->value,
            'solo' => MaterialVariant::Solo->value,
            'solo-inverted' => MaterialVariant::SoloInverted->value,
            'solo-filled' => MaterialVariant::SoloFilled->value,
        ],
        'role' => [
            'user' => 1,
            'assistant' => 2,
            'system' => 3,
        ],
        'orientation' => [
            'horizontal' => 1,
            'vertical' => 2,
        ],
        'activationMode' => [
            'automatic' => 1,
            'manual' => 2,
        ],
        'pressBehavior' => [
            'close' => 1,
            'collapse' => 2,
            'none' => 3,
        ],
        'selectionMode' => [
            'single' => 1,
            'multiple' => 2,
            'none' => 3,
        ],
        'slotAction' => [
            'focus' => 1,
            'clear' => 2,
            'toggle-password' => 3,
            'togglePassword' => 3,
            'none' => 4,
        ],
        'placement' => [
            'top' => 1,
            'top-start' => 2,
            'top-end' => 3,
            'bottom' => 4,
            'bottom-start' => 5,
            'bottom-end' => 6,
            'left' => 7,
            'left-top' => 8,
            'left-bottom' => 9,
            'right' => 10,
            'right-top' => 11,
            'right-bottom' => 12,
            'center' => 13,
            'top center' => 1,
            'top left' => 2,
            'top right' => 3,
            'bottom center' => 4,
            'bottom left' => 5,
            'bottom right' => 6,
            'left top' => 8,
            'left bottom' => 9,
            'right top' => 11,
            'right bottom' => 12,
        ],
        'anchor' => [
            'left' => 1,
            'right' => 2,
            'top' => 3,
            'bottom' => 4,
        ],
        'state' => [
            'default' => CalendarDayState::Default->value,
            'selected' => CalendarDayState::Selected->value,
            'disabled' => CalendarDayState::Disabled->value,
            'outside' => CalendarDayState::OutsideMonth->value,
            'today' => CalendarDayState::Today->value,
            'outside-month' => CalendarDayState::OutsideMonth->value,
            'range-start' => CalendarDayState::RangeStart->value,
            'range-middle' => CalendarDayState::RangeMiddle->value,
            'range-end' => CalendarDayState::RangeEnd->value,
        ],
        'type' => [
            'single' => 1,
            'multiple' => 2,
            'range' => 3,
            'date' => 4,
            'time' => 5,
            'datetime' => 6,
            'dot' => 7,
            'multi-dot' => 8,
            'period' => 9,
        ],
        'mode' => [
            'single' => 1,
            'multiple' => 2,
            'range' => 3,
            'date' => 4,
            'time' => 5,
            'datetime' => 6,
        ],
        'action' => [
            'muted' => 1,
            'success' => 2,
            'warning' => 3,
            'error' => 4,
            'info' => 5,
            'attention' => 6,
        ],
        'speed' => [
            'slow' => 1,
            'normal' => 2,
            'fast' => 3,
            'very-fast' => 4,
        ],
        'space' => [
            'xs' => 1,
            'sm' => 2,
            'md' => 3,
            'lg' => 4,
            'xl' => 5,
            '2xl' => 6,
            '3xl' => 7,
            '4xl' => 8,
        ],
        'flexDirection' => [
            'column' => 1,
            'row' => 2,
            'column-reverse' => 3,
            'row-reverse' => 4,
        ],
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public static function props(array $props): array
    {
        $normalized = [];

        foreach ($props as $name => $value) {
            $normalized[$name] = self::value($name, $value);
        }

        return $normalized;
    }

    public static function value(string $name, mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value) && isset(self::CODED_VALUES[$name][$value])) {
            return self::CODED_VALUES[$name][$value];
        }

        return $value;
    }
}
