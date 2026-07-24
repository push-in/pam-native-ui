<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Theme\Theme;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\Align;
use Pam\Native\AnimationKind;
use Pam\Native\FlexDirection;
use Pam\Native\FontStyle;
use Pam\Native\ImageFit;
use Pam\Native\Justify;
use Pam\Native\Overflow;
use Pam\Native\PointerEvents;
use Pam\Native\PositionType;
use Pam\Native\Style;
use Pam\Native\TextAlignment;
use Pam\Native\TextDecoration;
use Pam\Native\TextTransform;

/**
 * Compiles the mobile subset of gluestack's Tailwind recipes to packed PAM
 * style properties. This executes in PHP only when a declarative tree changes;
 * Android receives numeric properties and performs frame work on the UI thread.
 */
final class TailwindStyleCompiler
{
    private const array FONT_SIZES = [
        '2xs' => 10.0,
        'xs' => 12.0,
        'sm' => 14.0,
        'base' => 16.0,
        'lg' => 18.0,
        'xl' => 20.0,
        '2xl' => 24.0,
        '3xl' => 30.0,
        '4xl' => 36.0,
        '5xl' => 48.0,
        '6xl' => 60.0,
    ];

    private function __construct()
    {
    }

    /**
     * @param list<string> $classGroups
     * @param array<string, mixed> $props
     */
    public static function compile(
        array $classGroups,
        array $props,
        Theme $theme,
    ): Style {
        /** @var array<string, mixed> $values */
        $values = [];

        foreach ($classGroups as $group) {
            foreach (preg_split('/\s+/', trim($group)) ?: [] as $token) {
                $utility = '';
                if ($token !== '' && self::conditionApplies($token, $props, $theme, $utility)) {
                    self::apply($utility, $values, $theme);
                }
            }
        }

        return self::style($values);
    }

    /**
     * Returns mobile utilities that currently have no native compilation path.
     *
     * @param list<string> $classGroups
     * @param array<string, mixed> $props
     * @return list<string>
     */
    public static function unsupportedUtilities(
        array $classGroups,
        array $props,
        Theme $theme,
    ): array {
        $unsupported = [];
        $scratch = [];

        foreach ($classGroups as $group) {
            foreach (preg_split('/\s+/', trim($group)) ?: [] as $token) {
                $utility = '';
                if (
                    $token !== ''
                    && self::conditionApplies($token, $props, $theme, $utility)
                    && !self::apply($utility, $scratch, $theme)
                ) {
                    $unsupported[$utility] = true;
                }
            }
        }

        return array_keys($unsupported);
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function conditionApplies(
        string $token,
        array $props,
        Theme $theme,
        string &$utility,
    ): bool {
        $segments = self::segments($token);
        $last = array_pop($segments);
        $utility = is_string($last) ? $last : '';

        foreach ($segments as $condition) {
            if ($condition === 'dark') {
                if (ThemeManager::resolvedMode() !== ThemeMode::Dark) {
                    return false;
                }
                continue;
            }
            if ($condition === 'android') {
                continue;
            }
            if ($condition === 'placeholder') {
                $utility = 'placeholder-'.$utility;
                continue;
            }
            if (
                $condition === 'web'
                || $condition === 'ios'
                || in_array($condition, ['sm', 'md', 'lg', 'xl', '2xl'], true)
                || str_starts_with($condition, 'group-')
            ) {
                return false;
            }

            $state = match ($condition) {
                'disabled', 'data-[disabled=true]' => self::flag($props, 'disabled')
                    || self::flag($props, 'isDisabled'),
                'data-[checked=true]' => self::flag($props, 'checked')
                    || self::flag($props, 'isChecked'),
                'data-[selected=true]' => self::flag($props, 'selected')
                    || self::flag($props, 'isSelected'),
                'data-[invalid=true]' => self::flag($props, 'invalid')
                    || self::flag($props, 'isInvalid'),
                'data-[active=true]', 'active' => self::flag($props, 'active')
                    || self::flag($props, 'isActive'),
                'data-[flip=true]' => self::flag($props, 'flip')
                    || self::flag($props, 'isFlipped'),
                'data-[flip=false]' => !self::flag($props, 'flip')
                    && !self::flag($props, 'isFlipped'),
                'data-[focus=true]', 'data-[focus-visible=true]', 'focus', 'focus-visible' =>
                    self::flag($props, 'focused') || self::flag($props, 'isFocused'),
                'data-[hover=true]', 'hover' => self::flag($props, 'hovered')
                    || self::flag($props, 'isHovered'),
                default => true,
            };

            if (!$state) {
                return false;
            }
        }

        return $utility !== '';
    }

    /** @return list<string> */
    private static function segments(string $token): array
    {
        $segments = [];
        $start = 0;
        $depth = 0;
        $length = strlen($token);

        for ($position = 0; $position < $length; $position++) {
            $character = $token[$position];
            if ($character === '[') {
                $depth++;
            } elseif ($character === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($character === ':' && $depth === 0) {
                $segments[] = substr($token, $start, $position - $start);
                $start = $position + 1;
            }
        }

        $segments[] = substr($token, $start);

        return $segments;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function apply(string $utility, array &$values, Theme $theme): bool
    {
        if (self::applyDimensions($utility, $values)) {
            return true;
        }
        if (self::applySpacing($utility, $values)) {
            return true;
        }
        if (self::applyColor($utility, $values, $theme)) {
            return true;
        }
        if (self::applyTypography($utility, $values)) {
            return true;
        }

        if ($utility === 'flex-1') {
            $values['flexGrow'] = 1.0;
        } elseif ($utility === 'shrink-0') {
            $values['flexShrink'] = 0.0;
        } elseif ($utility === 'shrink') {
            $values['flexShrink'] = 1.0;
        } elseif ($utility === 'flex-row') {
            $values['flexDirection'] = FlexDirection::Row;
        } elseif ($utility === 'flex-row-reverse') {
            $values['flexDirection'] = FlexDirection::RowReverse;
        } elseif ($utility === 'flex-col') {
            $values['flexDirection'] = FlexDirection::Column;
        } elseif ($utility === 'flex-col-reverse') {
            $values['flexDirection'] = FlexDirection::ColumnReverse;
        } elseif (str_starts_with($utility, 'items-')) {
            $values['alignItems'] = self::align(substr($utility, 6));
        } elseif (str_starts_with($utility, 'self-')) {
            $values['alignSelf'] = self::align(substr($utility, 5));
        } elseif (str_starts_with($utility, 'justify-')) {
            $values['justifyContent'] = self::justify(substr($utility, 8));
        } elseif ($utility === 'overflow-hidden') {
            $values['overflow'] = Overflow::Hidden;
        } elseif ($utility === 'overflow-visible') {
            $values['overflow'] = Overflow::Visible;
        } elseif ($utility === 'absolute' || $utility === 'relative') {
            $values['positionType'] = $utility === 'absolute'
                ? PositionType::Absolute
                : PositionType::Relative;
        } elseif (self::applyPosition($utility, $values)) {
            return true;
        } elseif ($utility === 'aspect-square') {
            $values['aspectRatio'] = 1.0;
        } elseif ($utility === 'pointer-events-none') {
            $values['pointerEvents'] = PointerEvents::None;
        } elseif ($utility === 'pointer-events-auto') {
            $values['pointerEvents'] = PointerEvents::Auto;
        } elseif ($utility === 'pointer-events-all') {
            $values['pointerEvents'] = PointerEvents::Auto;
        } elseif ($utility === 'pb-safe') {
            $values['safeAreaBottom'] = true;
        } elseif ($utility === 'backdrop-blur-sm') {
            $values['blurRadius'] = 4.0;
        } elseif ($utility === 'ml-auto') {
            $values['marginLeftAuto'] = true;
        } elseif ($utility === '-translate-x-1/2') {
            $values['translationXPercent'] = -50.0;
        } elseif (preg_match('/^-space-x-(.+)$/D', $utility, $match) === 1) {
            $space = self::dimension($match[1]);
            if ($space === null) {
                return false;
            }
            $values['gap'] = -$space;
            $values['flexDirection'] = FlexDirection::Row;
        } elseif ($utility === 'animate-pulse') {
            $values['animationKind'] = AnimationKind::Pulse;
            $values['animationDurationMs'] ??= 1_500;
        } elseif ($utility === 'hidden') {
            $values['visible'] = false;
        } elseif ($utility === 'object-cover') {
            $values['imageFit'] = ImageFit::Cover;
        } elseif ($utility === 'sr-only') {
            $values['positionType'] = PositionType::Absolute;
            $values['width'] = 1.0;
            $values['height'] = 1.0;
            $values['overflow'] = Overflow::Hidden;
        } elseif (preg_match('/^line-clamp-(\d+)$/D', $utility, $match) === 1) {
            $values['numberOfLines'] = max(1, (int) $match[1]);
            $values['overflow'] = Overflow::Hidden;
        } elseif ($utility === 'truncate') {
            $values['numberOfLines'] = 1;
            $values['overflow'] = Overflow::Hidden;
        } elseif (preg_match('/^scale-\\[(-?\\d+(?:\\.\\d+)?)\\]$/D', $utility, $match) === 1) {
            $values['scaleX'] = (float) $match[1];
            $values['scaleY'] = (float) $match[1];
        } elseif (preg_match('/^duration-(\\d+)$/D', $utility, $match) === 1) {
            $values['animationDurationMs'] = (int) $match[1];
            $values['animateChanges'] = true;
        } elseif (str_starts_with($utility, 'transition-')) {
            $values['animateChanges'] = true;
        } elseif (preg_match('/^ring-(\\d+)$/D', $utility, $match) === 1) {
            $values['borderWidth'] = (float) $match[1];
        } elseif (str_starts_with($utility, 'ring-')) {
            $ringColor = self::color(substr($utility, 5), $theme);
            if ($ringColor !== null) {
                $values['borderColor'] = $ringColor;
            } else {
                return false;
            }
        } elseif ($utility === 'border') {
            $values['borderWidth'] = 1.0;
        } elseif (preg_match('/^border-([trbl])(?:-(\d+))?$/D', $utility, $match) === 1) {
            $key = match ($match[1]) {
                't' => 'borderTopWidth',
                'r' => 'borderRightWidth',
                'b' => 'borderBottomWidth',
                'l' => 'borderLeftWidth',
            };
            $values[$key] = isset($match[2]) ? (float) $match[2] : 1.0;
        } elseif (preg_match('/^border-(\d+)$/D', $utility, $match) === 1) {
            $values['borderWidth'] = (float) $match[1];
        } elseif ($utility === 'border-0') {
            $values['borderWidth'] = 0.0;
        } elseif (str_starts_with($utility, 'rounded')) {
            self::applyRadius($utility, $values);
        } elseif (preg_match('/^opacity-(\d+)$/D', $utility, $match) === 1) {
            $values['opacity'] = max(0.0, min(1.0, (int) $match[1] / 100));
        } elseif (str_starts_with($utility, 'shadow-')) {
            $values['elevation'] = self::elevation(substr($utility, 7));
        } elseif (preg_match('/^z-\[?(-?\d+)\]?$/D', $utility, $match) === 1) {
            $values['zIndex'] = (int) $match[1];
        } else {
            return self::isNativeNoOp($utility);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function applyDimensions(string $utility, array &$values): bool
    {
        if (preg_match('/^size-(.+)$/D', $utility, $sizeMatch) === 1) {
            $percent = self::percentage($sizeMatch[1]);
            if ($percent !== null) {
                $values['widthPercent'] = $percent;
                $values['heightPercent'] = $percent;
                return true;
            }
            $size = self::dimension($sizeMatch[1]);
            if ($size === null) {
                return false;
            }
            $values['width'] = $size;
            $values['height'] = $size;
            return true;
        }
        if (
            preg_match('/^(w|h|min-w|min-h|max-w|max-h)-(.+)$/D', $utility, $match) !== 1
        ) {
            return false;
        }

        $raw = $match[2];
        $percent = self::percentage($raw);
        if ($percent !== null) {
            $key = match ($match[1]) {
                'w' => 'widthPercent',
                'h' => 'heightPercent',
                'max-w' => 'maxWidthPercent',
                'max-h' => 'maxHeightPercent',
                default => null,
            };
            if ($key !== null) {
                $values[$key] = $percent;
            }

            return true;
        }

        $value = self::dimension($raw);
        if ($value === null) {
            return in_array($raw, ['auto', 'fit'], true);
        }

        $key = match ($match[1]) {
            'w' => 'width',
            'h' => 'height',
            'min-w' => 'minWidth',
            'min-h' => 'minHeight',
            'max-w' => 'maxWidth',
            'max-h' => 'maxHeight',
        };
        $values[$key] = $value;

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function applySpacing(string $utility, array &$values): bool
    {
        if (
            preg_match('/^(-?)(p|px|py|pl|pt|pr|pb|m|mx|my|ml|mt|mr|mb|gap)-(.+)$/D', $utility, $match)
            !== 1
        ) {
            return false;
        }
        $value = self::dimension($match[3]);
        if ($value === null) {
            return false;
        }
        if ($match[1] === '-') {
            if (!str_starts_with($match[2], 'm')) {
                return true;
            }
            $value *= -1.0;
        }
        $key = match ($match[2]) {
            'p' => 'padding',
            'px' => 'paddingHorizontal',
            'py' => 'paddingVertical',
            'pl' => 'paddingLeft',
            'pt' => 'paddingTop',
            'pr' => 'paddingRight',
            'pb' => 'paddingBottom',
            'm' => 'margin',
            'mx' => 'marginHorizontal',
            'my' => 'marginVertical',
            'ml' => 'marginLeft',
            'mt' => 'marginTop',
            'mr' => 'marginRight',
            'mb' => 'marginBottom',
            'gap' => 'gap',
        };
        $values[$key] = $value;

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function applyColor(
        string $utility,
        array &$values,
        Theme $theme,
    ): bool {
        if (
            preg_match('/^(bg|text|border|fill|placeholder-text)-(.+)$/D', $utility, $match)
            !== 1
        ) {
            return false;
        }
        if ($match[1] === 'text' && isset(self::FONT_SIZES[$match[2]])) {
            return false;
        }

        $color = self::color($match[2], $theme);
        if ($color === null) {
            return false;
        }
        $values[match ($match[1]) {
            'bg' => 'backgroundColor',
            'text' => 'textColor',
            'border' => 'borderColor',
            'fill' => 'tintColor',
            'placeholder-text' => 'placeholderColor',
        }] = $color;

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function applyTypography(string $utility, array &$values): bool
    {
        if (preg_match('/^text-(.+)$/D', $utility, $match) === 1) {
            $size = self::FONT_SIZES[$match[1]] ?? self::dimension($match[1]);
            if ($size !== null) {
                $values['fontSize'] = $size;
                return true;
            }
        }
        if (preg_match('/^font-(thin|light|normal|medium|semibold|bold|black)$/D', $utility, $match) === 1) {
            $values['fontWeight'] = match ($match[1]) {
                'thin' => 100,
                'light' => 300,
                'medium' => 500,
                'semibold' => 600,
                'bold' => 700,
                'black' => 900,
                default => 400,
            };
            return true;
        }
        if (preg_match('/^font-(body|heading|mono|roboto|sans)$/D', $utility, $match) === 1) {
            $values['fontFamily'] = match ($match[1]) {
                'mono' => 'monospace',
                'roboto' => 'Roboto',
                default => 'sans-serif',
            };
            return true;
        }
        if (preg_match('/^leading-(.+)$/D', $utility, $match) === 1) {
            $values['lineHeight'] = self::dimension($match[1]) ?? 0.0;
            return true;
        }
        if (str_starts_with($utility, 'tracking-')) {
            $values['letterSpacing'] = match (substr($utility, 9)) {
                'tighter' => -0.05,
                'tight' => -0.025,
                'normal' => 0.0,
                'sm' => -0.0125,
                'md' => 0.0,
                'wide' => 0.025,
                'wider' => 0.05,
                'widest' => 0.1,
                default => 0.0,
            };
            return true;
        }
        if ($utility === 'text-left' || $utility === 'text-start') {
            $values['textAlign'] = TextAlignment::Start;
            return true;
        }
        if ($utility === 'text-center') {
            $values['textAlign'] = TextAlignment::Center;
            return true;
        }
        if ($utility === 'text-right' || $utility === 'text-end') {
            $values['textAlign'] = TextAlignment::End;
            return true;
        }
        if ($utility === 'underline' || $utility === 'line-through') {
            $current = $values['textDecoration'] ?? TextDecoration::None;
            $hasUnderline = $utility === 'underline'
                || $current === TextDecoration::Underline
                || $current === TextDecoration::UnderlineLineThrough;
            $hasLineThrough = $utility === 'line-through'
                || $current === TextDecoration::LineThrough
                || $current === TextDecoration::UnderlineLineThrough;
            $values['textDecoration'] = match (true) {
                $hasUnderline && $hasLineThrough => TextDecoration::UnderlineLineThrough,
                $hasUnderline => TextDecoration::Underline,
                default => TextDecoration::LineThrough,
            };
            return true;
        }
        if ($utility === 'no-underline') {
            $values['textDecoration'] = TextDecoration::None;
            return true;
        }
        if ($utility === 'italic' || $utility === 'not-italic') {
            $values['fontStyle'] = $utility === 'italic' ? FontStyle::Italic : FontStyle::Normal;
            return true;
        }
        if (in_array($utility, ['uppercase', 'lowercase', 'capitalize', 'normal-case'], true)) {
            $values['textTransform'] = match ($utility) {
                'uppercase' => TextTransform::Uppercase,
                'lowercase' => TextTransform::Lowercase,
                'capitalize' => TextTransform::Capitalize,
                default => TextTransform::None,
            };
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $values */
    private static function applyPosition(string $utility, array &$values): bool
    {
        if (preg_match('/^(inset|left|top|right|bottom)-(.+)$/D', $utility, $match) !== 1) {
            return false;
        }
        $value = self::dimension($match[2]);
        if ($value === null) {
            return true;
        }
        if ($match[1] === 'inset') {
            foreach (['left', 'top', 'right', 'bottom'] as $key) {
                $values[$key] = $value;
            }
        } else {
            $values[$match[1]] = $value;
        }

        return true;
    }

    private static function dimension(string $value): ?float
    {
        if (in_array($value, ['full', 'fit', 'auto', 'screen'], true)) {
            return null;
        }
        if ($value === 'px') {
            return 1.0;
        }
        if (preg_match('/^\[(-?\d+(?:\.\d+)?)(px|rem)?\]$/D', $value, $match) === 1) {
            $number = (float) $match[1];
            return ($match[2] ?? '') === 'rem' ? $number * 16.0 : $number;
        }
        if (is_numeric($value)) {
            return (float) $value * 4.0;
        }

        return match ($value) {
            'xs' => 320.0,
            'sm' => 384.0,
            'md' => 448.0,
            'lg' => 512.0,
            'xl' => 576.0,
            default => null,
        };
    }

    private static function percentage(string $value): ?float
    {
        if ($value === 'full' || $value === 'screen') {
            return 100.0;
        }
        if (preg_match('/^(\d+)\/(\d+)$/D', $value, $fraction) === 1) {
            $denominator = (int) $fraction[2];
            if ($denominator === 0) {
                return null;
            }

            return max(
                0.0,
                min(100.0, (int) $fraction[1] / $denominator * 100.0),
            );
        }
        if (preg_match('/^\[(\d+(?:\.\d+)?)(%|vh|vw)\]$/D', $value, $match) === 1) {
            return max(0.0, min(100.0, (float) $match[1]));
        }

        return null;
    }

    private static function isNativeNoOp(string $utility): bool
    {
        if (in_array($utility, [
            'avatar-group',
            'border-collapse',
            'border-solid',
            'default',
            'fill-none',
            'flex',
            'group',
            'md',
            'overflow-y-auto',
            'stroke-none',
            'table',
            'xs',
        ], true)) {
            return true;
        }

        return str_starts_with($utility, 'cursor-')
            || str_starts_with($utility, 'group/')
            || str_starts_with($utility, 'group-[')
            || str_starts_with($utility, 'outline-')
            || str_starts_with($utility, 'underline-offset-');
    }

    private static function color(string $value, Theme $theme): ?int
    {
        $segments = explode('/', $value, 2);
        $name = $segments[0];
        $opacity = $segments[1] ?? null;
        $color = match ($name) {
            'primary' => $theme->color(ColorToken::Primary),
            'primary-foreground' => $theme->color(ColorToken::PrimaryForeground),
            'card' => $theme->color(ColorToken::Card),
            'card-foreground', 'foreground' => $theme->color(ColorToken::Foreground),
            'secondary' => $theme->color(ColorToken::Secondary),
            'secondary-foreground' => $theme->color(ColorToken::SecondaryForeground),
            'background' => $theme->color(ColorToken::Background),
            'popover' => $theme->color(ColorToken::Popover),
            'popover-foreground' => $theme->color(ColorToken::PopoverForeground),
            'muted' => $theme->color(ColorToken::Muted),
            'muted-foreground' => $theme->color(ColorToken::MutedForeground),
            'destructive' => $theme->color(ColorToken::Destructive),
            'destructive-foreground' => $theme->color(ColorToken::DestructiveForeground),
            'border' => $theme->color(ColorToken::Border),
            'input' => $theme->color(ColorToken::Input),
            'ring', 'indicator-primary', 'indicator-info' => $theme->color(ColorToken::Ring),
            'indicator-error' => $theme->color(ColorToken::Destructive),
            'accent' => $theme->color(ColorToken::Accent),
            'accent-foreground' => $theme->color(ColorToken::AccentForeground),
            'transparent' => 0x00000000,
            'black', '[#000]' => 0xff000000,
            'white' => 0xffffffff,
            'green-500' => 0xff22c55e,
            'yellow-500', 'yellow500' => 0xffeab308,
            'slate-800' => 0xff1e293b,
            'slate-900' => 0xff0f172a,
            default => self::hexColor($name),
        };

        if ($color === null || $opacity === null) {
            return $color;
        }
        $alpha = str_starts_with($opacity, '[')
            ? (float) trim($opacity, '[]')
            : (float) $opacity / 100.0;

        return ((int) round(max(0.0, min(1.0, $alpha)) * 255) << 24)
            | ($color & 0x00ffffff);
    }

    private static function hexColor(string $value): ?int
    {
        if (preg_match('/^\[#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\]$/D', $value, $match) !== 1) {
            return null;
        }
        $hex = strlen($match[1]) === 3
            ? implode('', array_map(
                static fn (string $digit): string => $digit.$digit,
                str_split($match[1]),
            ))
            : $match[1];

        return 0xff000000 | hexdec($hex);
    }

    private static function align(string $value): Align
    {
        return match ($value) {
            'center' => Align::Center,
            'end' => Align::End,
            'stretch' => Align::Stretch,
            default => Align::Start,
        };
    }

    private static function justify(string $value): Justify
    {
        return match ($value) {
            'center' => Justify::Center,
            'end' => Justify::End,
            'between' => Justify::SpaceBetween,
            'around' => Justify::SpaceAround,
            'evenly' => Justify::SpaceEvenly,
            default => Justify::Start,
        };
    }

    private static function radius(string $utility): float
    {
        $value = ltrim(substr($utility, strlen('rounded')), '-');

        return match ($value) {
            'none' => 0.0,
            'sm' => 2.0,
            'md' => 6.0,
            'lg' => 8.0,
            'xl' => 12.0,
            '2xl' => 16.0,
            '3xl' => 24.0,
            'full' => 9999.0,
            default => 4.0,
        };
    }

    /** @param array<string, mixed> $values */
    private static function applyRadius(string $utility, array &$values): void
    {
        if (
            preg_match('/^rounded(?:-(tl|tr|br|bl|t|r|b|l))?-(.+)$/D', $utility, $match) === 1
        ) {
            $radius = self::radius('rounded-'.$match[2]);
            $keys = match ($match[1]) {
                'tl' => ['borderTopLeftRadius'],
                'tr' => ['borderTopRightRadius'],
                'br' => ['borderBottomRightRadius'],
                'bl' => ['borderBottomLeftRadius'],
                't' => ['borderTopLeftRadius', 'borderTopRightRadius'],
                'r' => ['borderTopRightRadius', 'borderBottomRightRadius'],
                'b' => ['borderBottomLeftRadius', 'borderBottomRightRadius'],
                'l' => ['borderTopLeftRadius', 'borderBottomLeftRadius'],
                default => ['borderRadius'],
            };
            foreach ($keys as $key) {
                $values[$key] = $radius;
            }

            return;
        }

        $values['borderRadius'] = self::radius($utility);
    }

    private static function elevation(string $value): float
    {
        return match ($value) {
            'xs' => 1.0,
            'sm' => 2.0,
            'md' => 4.0,
            'lg' => 6.0,
            default => str_starts_with($value, 'hard-') ? 8.0 : 3.0,
        };
    }

    /** @param array<string, mixed> $props */
    private static function flag(array $props, string $name): bool
    {
        $value = $props[$name] ?? false;

        return $value === true || $value === 1 || $value === 'true' || $value === '1';
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function style(array $values): Style
    {
        return new Style(
            width: self::float($values, 'width'),
            height: self::float($values, 'height'),
            flexGrow: self::float($values, 'flexGrow'),
            padding: self::float($values, 'padding'),
            paddingHorizontal: self::float($values, 'paddingHorizontal'),
            paddingVertical: self::float($values, 'paddingVertical'),
            gap: self::float($values, 'gap'),
            margin: self::float($values, 'margin'),
            marginHorizontal: self::float($values, 'marginHorizontal'),
            marginVertical: self::float($values, 'marginVertical'),
            minWidth: self::float($values, 'minWidth'),
            minHeight: self::float($values, 'minHeight'),
            maxWidth: self::float($values, 'maxWidth'),
            maxHeight: self::float($values, 'maxHeight'),
            backgroundColor: self::int($values, 'backgroundColor'),
            textColor: self::int($values, 'textColor'),
            fontSize: self::float($values, 'fontSize'),
            borderRadius: self::float($values, 'borderRadius'),
            borderWidth: self::float($values, 'borderWidth'),
            borderColor: self::int($values, 'borderColor'),
            opacity: self::float($values, 'opacity'),
            alignItems: self::instance($values, 'alignItems', Align::class),
            alignSelf: self::instance($values, 'alignSelf', Align::class),
            justifyContent: self::instance($values, 'justifyContent', Justify::class),
            textAlign: self::instance($values, 'textAlign', TextAlignment::class),
            fontWeight: self::int($values, 'fontWeight'),
            elevation: self::float($values, 'elevation'),
            letterSpacing: self::float($values, 'letterSpacing'),
            lineHeight: self::float($values, 'lineHeight'),
            zIndex: self::int($values, 'zIndex'),
            overflow: self::instance($values, 'overflow', Overflow::class),
            flexDirection: self::instance($values, 'flexDirection', FlexDirection::class),
            scaleX: self::float($values, 'scaleX'),
            scaleY: self::float($values, 'scaleY'),
            flexShrink: self::float($values, 'flexShrink'),
            paddingLeft: self::float($values, 'paddingLeft'),
            paddingTop: self::float($values, 'paddingTop'),
            paddingRight: self::float($values, 'paddingRight'),
            paddingBottom: self::float($values, 'paddingBottom'),
            marginLeft: self::float($values, 'marginLeft'),
            marginTop: self::float($values, 'marginTop'),
            marginRight: self::float($values, 'marginRight'),
            marginBottom: self::float($values, 'marginBottom'),
            positionType: self::instance($values, 'positionType', PositionType::class),
            left: self::float($values, 'left'),
            top: self::float($values, 'top'),
            right: self::float($values, 'right'),
            bottom: self::float($values, 'bottom'),
            aspectRatio: self::float($values, 'aspectRatio'),
            borderTopLeftRadius: self::float($values, 'borderTopLeftRadius'),
            borderTopRightRadius: self::float($values, 'borderTopRightRadius'),
            borderBottomRightRadius: self::float($values, 'borderBottomRightRadius'),
            borderBottomLeftRadius: self::float($values, 'borderBottomLeftRadius'),
            borderLeftWidth: self::float($values, 'borderLeftWidth'),
            borderTopWidth: self::float($values, 'borderTopWidth'),
            borderRightWidth: self::float($values, 'borderRightWidth'),
            borderBottomWidth: self::float($values, 'borderBottomWidth'),
            textDecoration: self::instance($values, 'textDecoration', TextDecoration::class),
            textTransform: self::instance($values, 'textTransform', TextTransform::class),
            fontStyle: self::instance($values, 'fontStyle', FontStyle::class),
            widthPercent: self::float($values, 'widthPercent'),
            heightPercent: self::float($values, 'heightPercent'),
            maxWidthPercent: self::float($values, 'maxWidthPercent'),
            maxHeightPercent: self::float($values, 'maxHeightPercent'),
            pointerEvents: self::instance($values, 'pointerEvents', PointerEvents::class),
            numberOfLines: self::int($values, 'numberOfLines'),
            tintColor: self::int($values, 'tintColor'),
            placeholderColor: self::int($values, 'placeholderColor'),
            animationDurationMs: self::int($values, 'animationDurationMs'),
            animateChanges: self::bool($values, 'animateChanges'),
            safeAreaBottom: self::bool($values, 'safeAreaBottom'),
            blurRadius: self::float($values, 'blurRadius'),
            fontFamily: self::string($values, 'fontFamily'),
            visible: self::bool($values, 'visible'),
            imageFit: self::instance($values, 'imageFit', ImageFit::class),
            marginLeftAuto: self::bool($values, 'marginLeftAuto'),
            translationXPercent: self::float($values, 'translationXPercent'),
            animationKind: self::instance($values, 'animationKind', AnimationKind::class),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function float(array $values, string $key): ?float
    {
        $value = $values[$key] ?? null;

        return is_float($value) ? $value : (is_int($value) ? (float) $value : null);
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function int(array $values, string $key): ?int
    {
        return is_int($values[$key] ?? null) ? $values[$key] : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function bool(array $values, string $key): ?bool
    {
        return is_bool($values[$key] ?? null) ? $values[$key] : null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function string(array $values, string $key): ?string
    {
        return is_string($values[$key] ?? null) ? $values[$key] : null;
    }

    /**
     * @template T of object
     * @param array<string, mixed> $values
     * @param class-string<T> $class
     * @return T|null
     */
    private static function instance(array $values, string $key, string $class): ?object
    {
        $value = $values[$key] ?? null;

        return $value instanceof $class ? $value : null;
    }
}
