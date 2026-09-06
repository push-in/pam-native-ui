<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Pam\MobileUi\Enum\CalendarDayState;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\MaterialShape;
use Pam\MobileUi\Enum\MaterialVariant;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\MaterialTokens;
use Pam\MobileUi\Theme\Theme;
use Pam\Native\Align;
use Pam\Native\FlexDirection;
use Pam\Native\FlexWrap;
use Pam\Native\Justify;
use Pam\Native\Overflow;
use Pam\Native\PositionType;
use Pam\Native\Style;
use Pam\Native\TextAlignment;

final class MaterialStyleResolver
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $props */
    public static function resolve(array $props, Theme $theme): ?Style
    {
        $part = $props['__materialComponent'] ?? null;
        if (!is_string($part)) {
            return null;
        }

        $density = match ($props['density'] ?? null) {
            MaterialDensity::Comfortable->value, 'comfortable' => MaterialDensity::Comfortable,
            MaterialDensity::Compact->value, 'compact' => MaterialDensity::Compact,
            default => MaterialDensity::Default,
        };
        $variant = match ($props['variant'] ?? null) {
            MaterialVariant::Flat->value, 'flat' => MaterialVariant::Flat,
            MaterialVariant::Tonal->value, 'tonal', 'subtle' => MaterialVariant::Tonal,
            MaterialVariant::Outlined->value, 'outlined', 'outline' => MaterialVariant::Outlined,
            MaterialVariant::Text->value, 'text' => MaterialVariant::Text,
            MaterialVariant::Plain->value, 'plain' => MaterialVariant::Plain,
            MaterialVariant::Underlined->value, 'underlined' =>
                MaterialVariant::Underlined,
            MaterialVariant::Filled->value, 'filled' => MaterialVariant::Filled,
            MaterialVariant::Solo->value, 'solo' => MaterialVariant::Solo,
            MaterialVariant::SoloInverted->value, 'solo-inverted' =>
                MaterialVariant::SoloInverted,
            MaterialVariant::SoloFilled->value, 'solo-filled' =>
                MaterialVariant::SoloFilled,
            default => MaterialVariant::Elevated,
        };
        $transparent = Color::rgb(0, 0, 0, 0)->argb;
        $disabled = ($props['disabled'] ?? false) === true;
        $buttonPart = in_array(
            $part,
            ['PBtn', 'PIconBtn', 'PFab', 'PAppBarNavIcon'],
            true,
        );
        $opacity = $disabled
            ? ($buttonPart ? 0.26 : MaterialTokens::STATE_OPACITY[6])
            : 1.0;
        $height = MaterialTokens::fieldHeight($density);

        if ($part === 'PAlert') {
            $semanticBackground = self::semanticColor($props, $theme, false);
            $semanticForeground = self::semanticColor($props, $theme, true);
            $filled = $variant === MaterialVariant::Filled;
            $tonal = $variant === MaterialVariant::Tonal;
            $plain = $variant === MaterialVariant::Plain;
            $transparentVariant = in_array(
                $variant,
                [MaterialVariant::Outlined, MaterialVariant::Text, MaterialVariant::Plain],
                true,
            );
            $alertBackground = match (true) {
                $semanticBackground !== null && !$filled => self::blendArgb(
                    $semanticBackground,
                    $theme->color(ColorToken::Surface),
                    0.12,
                ),
                $semanticBackground !== null => $semanticBackground,
                $tonal => self::blendArgb(
                    $theme->color(ColorToken::Secondary),
                    $theme->color(ColorToken::Surface),
                    0.12,
                ),
                default => $theme->color(ColorToken::SurfaceElevated),
            };
            $alertForeground = match (true) {
                $filled => $semanticForeground
                    ?? $theme->color(ColorToken::Foreground),
                default => $theme->color(ColorToken::Foreground),
            };
            $alertBorder = $props['border'] ?? null;
            $rtl = ($props['rtl'] ?? false) === true;
            $startBorder = $alertBorder === 'start';
            $endBorder = $alertBorder === 'end';
            $topBorder = $alertBorder === 'top';
            $bottomBorder = $alertBorder === 'bottom';
            $verticalPadding = match ($density) {
                MaterialDensity::Comfortable => 16.0,
                MaterialDensity::Compact => 12.0,
                default => 12.0,
            };

            return new Style(
                widthPercent: 100.0,
                minHeight: 80.0,
                paddingHorizontal: $plain ? 0.0 : 16.0,
                paddingVertical: $plain ? 0.0 : $verticalPadding,
                paddingLeft: ($startBorder && !$rtl) || ($endBorder && $rtl)
                    ? 20.0
                    : null,
                paddingRight: ($endBorder && !$rtl) || ($startBorder && $rtl)
                    ? 20.0
                    : null,
                paddingTop: $topBorder ? $verticalPadding + 4.0 : null,
                paddingBottom: $bottomBorder ? $verticalPadding + 4.0 : null,
                gap: 12.0,
                backgroundColor: $transparentVariant
                    ? $transparent
                    : $alertBackground,
                borderColor: $semanticBackground
                    ?? $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderLeftWidth: ($startBorder && !$rtl) || ($endBorder && $rtl)
                    ? 4.0
                    : null,
                borderTopWidth: $topBorder ? 4.0 : null,
                borderRightWidth: ($endBorder && !$rtl) || ($startBorder && $rtl)
                    ? 4.0
                    : null,
                borderBottomWidth: $bottomBorder ? 4.0 : null,
                borderRadius: MaterialTokens::radius(MaterialShape::Medium),
                textColor: $alertForeground,
                tintColor: $alertForeground,
                opacity: $opacity,
                elevation: $variant === MaterialVariant::Elevated
                    ? self::resolvedElevation($props, 1.0)
                    : 0.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Start,
            );
        }

        if ($part === 'PBanner') {
            $semanticBackground = self::semanticColor($props, $theme, false);
            $bannerBackground = $semanticBackground === null
                ? $theme->color(ColorToken::Surface)
                : self::blendArgb(
                    $semanticBackground,
                    $theme->color(ColorToken::Surface),
                    MaterialTokens::STATE_OPACITY[4],
                );
            [$paddingTop, $paddingBottom] = match ($density) {
                MaterialDensity::Comfortable => [12.0, 8.0],
                MaterialDensity::Compact => [8.0, 4.0],
                default => [16.0, 8.0],
            };
            $reduceMotion = ($props['reduceMotion'] ?? false) === true;
            $flat = ($props['flat'] ?? false) === true
                || $variant === MaterialVariant::Flat;

            return new Style(
                widthPercent: 100.0,
                paddingLeft: 16.0,
                paddingRight: 8.0,
                paddingTop: $paddingTop,
                paddingBottom: $paddingBottom,
                gap: 0.0,
                backgroundColor: $bannerBackground,
                borderBottomWidth: 0.0,
                borderColor: $theme->color(ColorToken::Border),
                borderRadius: 0.0,
                elevation: $flat
                    ? 0.0
                    : self::resolvedElevation($props, 1.0),
                opacity: $opacity,
                flexDirection: FlexDirection::Column,
                alignItems: Align::Stretch,
                animationDurationMs: $reduceMotion ? null : 250,
                animateChanges: !$reduceMotion,
            );
        }

        if ($part === 'PBannerActions') {
            return new Style(
                widthPercent: 100.0,
                minHeight: MaterialTokens::MINIMUM_TOUCH_TARGET,
                gap: 8.0,
                marginTop: 4.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::End,
            );
        }

        if ($part === 'PExpansionPanels') {
            return new Style(
                widthPercent: 100.0,
                padding: 0.0,
                gap: 0.0,
            );
        }

        if ($part === 'PExpansionPanel') {
            return new Style(
                widthPercent: 100.0,
                padding: 0.0,
                marginVertical: 0.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderRadius: 0.0,
                opacity: $disabled ? 0.26 : 1.0,
            );
        }

        if ($part === 'PExpansionPanelTitle') {
            $active = ($props['active'] ?? $props['expanded'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                minHeight: $active ? 64.0 : 56.0,
                paddingHorizontal: 16.0,
                paddingVertical: 12.0,
                gap: 16.0,
                textColor: $theme->color(ColorToken::OnSurface),
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderBottomWidth: 1.0,
                opacity: $disabled ? 0.26 : 1.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PExpansionPanelText') {
            return new Style(
                widthPercent: 100.0,
                paddingHorizontal: 16.0,
                paddingTop: 12.0,
                paddingBottom: 20.0,
                textColor: $theme->color(ColorToken::MutedForeground),
                opacity: $disabled ? 0.26 : 1.0,
            );
        }

        if (in_array($part, ['PStepper', 'PStepperVertical'], true)) {
            return new Style(
                widthPercent: 100.0,
                padding: 0.0,
                gap: 0.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderRadius: 4.0,
                elevation: self::resolvedElevation($props, 1.0),
                opacity: $opacity,
            );
        }

        if ($part === 'PStepperHeader') {
            return new Style(
                widthPercent: 100.0,
                elevation: self::resolvedElevation($props, 1.0),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, ['PStepperItem', 'PStepperVerticalItem'], true)) {
            $active = ($props['active'] ?? $props['selected'] ?? false) === true;
            $horizontal = $part === 'PStepperItem';

            return new Style(
                widthPercent: 100.0,
                padding: $horizontal ? 24.0 : null,
                paddingHorizontal: $horizontal ? null : 16.0,
                paddingVertical: $horizontal ? null : 12.0,
                gap: 8.0,
                opacity: $disabled ? 0.38 : ($active ? 1.0 : 0.6),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PStepperWindow') {
            return new Style(
                widthPercent: 100.0,
                margin: 24.0,
            );
        }

        if ($part === 'PStepperWindowItem') {
            return new Style(
                widthPercent: 100.0,
                paddingTop: 24.0,
            );
        }

        if ($part === 'PStepperActions') {
            return new Style(
                widthPercent: 100.0,
                padding: 16.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::End,
            );
        }

        if ($part === 'PStepperVerticalActions') {
            return new Style(
                widthPercent: 100.0,
                paddingHorizontal: 24.0,
                paddingBottom: 16.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::End,
            );
        }

        if ($part === 'PCalendar') {
            $visible = $props['visibleDate']
                ?? $props['modelValue']
                ?? $props['value']
                ?? date('Y-m-d');
            if (is_array($visible)) {
                $visible = reset($visible);
            }
            $date = is_scalar($visible)
                ? \DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    substr((string) $visible, 0, 10),
                )
                : false;
            $date = $date ?: new \DateTimeImmutable('first day of this month');
            $firstDayOfWeek = max(
                0,
                min(6, (int) ($props['firstDayOfWeek'] ?? 0)),
            );
            $firstDay = ((int) $date->format('N')) % 7;
            $startOffset = ($firstDay - $firstDayOfWeek + 7) % 7;
            $occupiedCells = $startOffset + (int) $date->format('t');
            $rows = in_array(
                $props['fixedWeeks'] ?? false,
                [true, 1, '1'],
                true,
            )
                ? 6
                : max(4, min(6, (int) ceil($occupiedCells / 7)));
            $height = 88.0 + ($rows * 48.0);

            return new Style(
                widthPercent: 100.0,
                minHeight: $height,
                borderWidth: 0.0,
                borderRadius: 0.0,
                backgroundColor: 0x00000000,
                textColor: $theme->color(ColorToken::OnSurface),
                opacity: $opacity,
            );
        }

        if ($part === 'PCalendarDay') {
            $state = match ($props['state'] ?? CalendarDayState::Default->value) {
                CalendarDayState::Selected->value, 'selected' => CalendarDayState::Selected,
                CalendarDayState::Disabled->value, 'disabled' => CalendarDayState::Disabled,
                CalendarDayState::OutsideMonth->value,
                'outside', 'outside-month' => CalendarDayState::OutsideMonth,
                CalendarDayState::Today->value, 'today' => CalendarDayState::Today,
                CalendarDayState::RangeStart->value,
                'range-start' => CalendarDayState::RangeStart,
                CalendarDayState::RangeMiddle->value,
                'range-middle' => CalendarDayState::RangeMiddle,
                CalendarDayState::RangeEnd->value,
                'range-end' => CalendarDayState::RangeEnd,
                default => CalendarDayState::Default,
            };
            $selected = in_array(
                $props['selected'] ?? $props['isSelected'] ?? false,
                [true, 1, '1'],
                true,
            ) || in_array($state, [
                CalendarDayState::Selected,
                CalendarDayState::RangeStart,
                CalendarDayState::RangeEnd,
            ], true);
            $outside = in_array(
                $props['outside'] ?? false,
                [true, 1, '1'],
                true,
            ) || $state === CalendarDayState::OutsideMonth;
            $dayDisabled = $disabled || $state === CalendarDayState::Disabled;
            $semanticForeground = self::semanticForeground($props, $theme)
                ?? $theme->color(ColorToken::PrimaryForeground);
            return new Style(
                width: 48.0,
                height: 48.0,
                minWidth: 48.0,
                minHeight: 48.0,
                borderWidth: 0.0,
                borderRadius: 0.0,
                backgroundColor: $transparent,
                textColor: $selected
                    ? $semanticForeground
                    : ($outside
                        ? $theme->color(ColorToken::MutedForeground)
                        : $theme->color(ColorToken::OnSurface)),
                fontSize: 14.0,
                lineHeight: 20.0,
                fontWeight: 400,
                opacity: $dayDisabled || $outside ? 0.38 : 1.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if ($part === 'POtpInput') {
            $divider = $props['divider'] ?? null;
            $divided = ($props['divided'] ?? false) === true
                || $divider === true
                || (is_string($divider) && $divider !== '');

            return new Style(
                width: $divided ? 360.0 : 320.0,
                minHeight: 64.0,
                paddingHorizontal: 8.0,
                paddingVertical: 8.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                fontSize: 20.0,
                lineHeight: 28.0,
                backgroundColor: 0x00000000,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if (in_array($part, [
            'PAutocomplete',
            'PCombobox',
            'PSelect',
            'PNumberInput',
            'PDateInput',
            'PColorInput',
        ], true)) {
            $selectionField = in_array($part, [
                'PSelect', 'PAutocomplete', 'PCombobox',
            ], true);
            $fieldHeight = match (true) {
                $selectionField => 64.0,
                $part === 'PNumberInput'
                    && ($props['controlVariant'] ?? null) === 'stacked' => 112.0,
                $part === 'PNumberInput' => 64.0,
                default => max(56.0, MaterialTokens::fieldHeight($density)),
            };

            return new Style(
                widthPercent: 100.0,
                height: $fieldHeight,
                minHeight: $fieldHeight,
                paddingHorizontal: 16.0,
                borderWidth: ($props['variant'] ?? null) === 'outlined' ? 1.0 : 0.0,
                borderRadius: 4.0,
                borderColor: $theme->color(ColorToken::Border),
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if ($part === 'PImg') {
            $height = self::number($props['height'] ?? null);
            $aspectRatio = self::number($props['aspectRatio'] ?? null);

            return new Style(
                widthPercent: 100.0,
                height: $height > 0.0 ? $height : null,
                aspectRatio: $aspectRatio > 0.0 ? $aspectRatio : null,
                minHeight: ($props['cardMedia'] ?? false) ? 200.0 : 1.0,
                borderRadius: ($props['rounded'] ?? false) ? 4.0 : 0.0,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                opacity: ($props['loading'] ?? false) ? 0.60 : 1.0,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }
        if ($part === 'PSparkline') {
            $height = self::number($props['height'] ?? null);

            return new Style(
                widthPercent: 100.0,
                height: $height > 0.0 ? $height : 100.0,
                minHeight: $height > 0.0 ? $height : 100.0,
                padding: self::number($props['padding'] ?? null),
                backgroundColor: 0x00000000,
                opacity: $opacity,
                animationDurationMs: 300,
                animateChanges: true,
            );
        }

        if (in_array($part, [
            
            'PDataTable',
            
            'PDataTableVirtual',
        ], true)) {
            $rowHeight = match ($density) {
                MaterialDensity::Comfortable => 44.0,
                MaterialDensity::Compact => 36.0,
                default => 52.0,
            };
            $configuredHeight = self::number($props['height'] ?? null);
            $virtualViewportHeight = $part === 'PDataTableVirtual'
                ? ($configuredHeight > 0.0 ? $configuredHeight : $rowHeight * 6.0)
                : null;

            return new Style(
                widthPercent: 100.0,
                height: $virtualViewportHeight,
                minHeight: $virtualViewportHeight ?? $rowHeight,
                paddingHorizontal: 0.0,
                borderWidth: 0.0,
                borderRadius: 8.0,
                borderColor: $theme->color(ColorToken::Border),
                backgroundColor: $theme->color(ColorToken::Surface),
                fontSize: 14.0,
                lineHeight: 21.0,
                // Keep stale rows legible while the inline progress row
                // communicates refresh activity. Dimming the entire table to
                // disabled-state opacity made headers and values fail visual
                // hierarchy without preventing interaction.
                opacity: $opacity,
                overflow: Overflow::Hidden,
                animationDurationMs: 280,
                animateChanges: true,
            );
        }



        if (in_array($part, [
            
            'PInfiniteScroll',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: 52.0,
                backgroundColor: 0x00000000,
                opacity: ($props['loading'] ?? false) ? 0.38 : $opacity,
                animationDurationMs: 280,
                animateChanges: true,
            );
        }

        if ($part === 'PTreeview') {
            return new Style(
                widthPercent: 100.0,
                gap: 0.0,
                paddingVertical: 8.0,
                backgroundColor: 0x00000000,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PTreeviewItem') {
            $level = max(0.0, self::number($props['level'] ?? null));
            $selected = ($props['active'] ?? false) === true
                || ($props['selected'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                minHeight: match ($density) {
                    MaterialDensity::Comfortable => 44.0,
                    MaterialDensity::Compact => 40.0,
                    default => 48.0,
                },
                paddingLeft: 16.0 + ($level * 24.0),
                paddingRight: 16.0,
                gap: 8.0,
                borderRadius: 0.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                textColor: $selected
                    ? $theme->color(ColorToken::AccentForeground)
                    : $theme->color(ColorToken::OnSurface),
                backgroundColor: $selected
                    ? $theme->color(ColorToken::Accent)
                    : 0x00000000,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PEmptyState') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 240.0,
                padding: 16.0,
                gap: 8.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }

        if ($part === 'PSlider' || $part === 'PRangeSlider') {
            $vertical = ($props['direction'] ?? null) === 'vertical'
                || ($props['vertical'] ?? false)
                || ($props['orientation'] ?? null) === 2
                || ($props['orientation'] ?? null) === 'vertical';
            $thumbLabel = $props['thumbLabel']
                ?? $props['showThumbLabel']
                ?? false;
            $showsThumbLabel = $thumbLabel === true
                || $thumbLabel === 'always'
                || ($props['alwaysShowThumbLabel'] ?? false) === true;
            $tickLabels = $props['tickLabels'] ?? null;
            $showsTickLabels = is_array($tickLabels) && $tickLabels !== [];
            $horizontalHeight = match (true) {
                $showsThumbLabel && $showsTickLabels =>
                    MaterialTokens::SLIDER_VALUE_AND_TICK_LABELLED_HEIGHT,
                $showsThumbLabel => MaterialTokens::SLIDER_LABELLED_HEIGHT,
                $showsTickLabels => MaterialTokens::SLIDER_TICK_LABELLED_HEIGHT,
                default => MaterialTokens::MINIMUM_TOUCH_TARGET,
            };
            $verticalWidth = ($showsThumbLabel || $showsTickLabels)
                ? MaterialTokens::SLIDER_VERTICAL_LABELLED_WIDTH
                : MaterialTokens::MINIMUM_TOUCH_TARGET;

            return new Style(
                widthPercent: $vertical ? null : 100.0,
                width: $vertical ? $verticalWidth : null,
                minWidth: $vertical ? $verticalWidth : null,
                minHeight: $vertical
                    ? 300.0
                    : $horizontalHeight,
                paddingHorizontal: $vertical ? 0.0 : 8.0,
                marginTop: $vertical ? 12.0 : 0.0,
                marginBottom: $vertical ? 12.0 : 0.0,
                borderRadius: 6.0,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
                animationDurationMs: 300,
                animateChanges: true,
            );
        }

        if (in_array($part, [
            'PCheckbox',
            'PRadio',
            'PSwitch',
        ], true)) {
            $controlSize = max(
                MaterialTokens::MINIMUM_TOUCH_TARGET,
                MaterialTokens::componentSize(
                    $props['size'] ?? null,
                    MaterialTokens::MINIMUM_TOUCH_TARGET,
                ),
            );

            return new Style(
                minHeight: $controlSize,
                minWidth: $part === 'PSwitch'
                    ? max(MaterialTokens::SWITCH_TRACK_WIDTH, $controlSize)
                    : $controlSize,
                gap: $part === 'PSwitch' ? 10.0 : 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                backgroundColor: 0x00000000,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
                animationDurationMs: $part === 'PSwitch' ? 200 : 150,
                animateChanges: true,
            );
        }

        if (in_array($part, [
            'PCheckboxGroup',
            'PRadioGroup',
        ], true)) {
            $inline = ($props['inline'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                gap: 4.0,
                flexDirection: $inline ? FlexDirection::Row : FlexDirection::Column,
                alignItems: $inline ? Align::Center : Align::Stretch,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PDatePicker') {
            $width = ($props['showAdjacentMonths'] ?? false) && ($props['showWeek'] ?? false)
                ? 368.0
                : 328.0;

            return new Style(
                width: $width,
                minWidth: $width,
                borderRadius: 4.0,
                elevation: self::resolvedElevation($props, 0.0),
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'PTimePicker') {
            return new Style(
                width: 328.0,
                minWidth: 328.0,
                height: 56.0,
                minHeight: 56.0,
                paddingHorizontal: 16.0,
                borderRadius: 4.0,
                elevation: self::resolvedElevation($props, 0.0),
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: 1.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }









        if ($part === 'PCarousel') {
            $requestedHeight = $props['height'] ?? null;
            $height = is_numeric($requestedHeight)
                ? (float) $requestedHeight
                : MaterialTokens::CAROUSEL_HEIGHT;
            $requestedRadius = $props['borderRadius'] ?? null;
            $radius = is_numeric($requestedRadius)
                ? (float) $requestedRadius
                : MaterialTokens::CAROUSEL_RADIUS;

            return new Style(
                widthPercent: 100.0,
                height: $height,
                minHeight: $height,
                borderRadius: $radius,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                overflow: Overflow::Hidden,
                opacity: $opacity,
                animationDurationMs: 300,
                animateChanges: true,
            );
        }

        if ($part === 'PCarouselItem') {
            return new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                minHeight: 1.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                backgroundColor: 0x00000000,
                opacity: ($props['active'] ?? true) ? 1.0 : 0.0,
                animationDurationMs: 300,
                animateChanges: true,
            );
        }

        if ($part === 'PRating') {
            $itemSize = match ($props['size'] ?? null) {
                'x-small' => 16.0,
                'small' => 20.0,
                'large' => 28.0,
                'x-large' => 32.0,
                default => is_numeric($props['size'] ?? null) ? (float) $props['size'] : 24.0,
            };

            return new Style(
                height: max(MaterialTokens::MINIMUM_TOUCH_TARGET, $itemSize),
                minHeight: max(MaterialTokens::MINIMUM_TOUCH_TARGET, $itemSize),
                gap: self::number($props['itemGap'] ?? null),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                backgroundColor: 0x00000000,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
                animationDurationMs: 150,
                animateChanges: true,
            );
        }

        if ($part === 'PTimeline') {
            $horizontal = ($props['direction'] ?? 'vertical') === 'horizontal';

            return new Style(
                widthPercent: 100.0,
                gap: 0.0,
                padding: 0.0,
                flexDirection: $horizontal ? FlexDirection::Row : FlexDirection::Column,
                alignItems: $horizontal ? Align::Start : Align::Stretch,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }

        if ($part === 'PTimelineItem') {
            $dotSize = match ($props['size'] ?? null) {
                'x-small' => 24.0,
                'small' => 32.0,
                'large' => 48.0,
                'x-large' => 56.0,
                default => is_numeric($props['size'] ?? null) ? (float) $props['size'] : 38.0,
            };

            return new Style(
                widthPercent: 100.0,
                minHeight: max(72.0, $dotSize + 32.0),
                paddingHorizontal: 0.0,
                paddingVertical: 8.0,
                gap: 12.0,
                borderWidth: 0.0,
                borderRadius: 0.0,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }



        if ($part === 'PAvatar') {
            $size = match ($props['size'] ?? null) {
                'x-small' => 24.0,
                'small' => 32.0,
                'large' => 48.0,
                'x-large' => 56.0,
                default => is_numeric($props['size'] ?? null) ? (float) $props['size'] : 40.0,
            };
            $semanticBackground = self::semanticColor($props, $theme, false);
            $semanticForeground = self::semanticColor($props, $theme, true);
            $outlined = $variant === MaterialVariant::Outlined;
            $tonal = $variant === MaterialVariant::Tonal;
            $bordered = ($props['border'] ?? false) === true;
            $transparentAvatar = in_array(
                $variant,
                [
                    MaterialVariant::Text,
                    MaterialVariant::Plain,
                ],
                true,
            ) || $outlined;
            $tile = ($props['tile'] ?? false) === true;

            return new Style(
                width: $size,
                height: $size,
                minWidth: $size,
                minHeight: $size,
                textColor: $transparentAvatar
                    ? ($semanticBackground ?? $theme->color(ColorToken::Primary))
                    : ($tonal
                        ? ($semanticBackground ?? $theme->color(ColorToken::Secondary))
                    : ($semanticForeground
                        ?? $theme->color(ColorToken::SecondaryForeground))),
                backgroundColor: $transparentAvatar
                    ? 0x00000000
                    : ($tonal
                        ? self::blendArgb(
                            $semanticBackground
                                ?? $theme->color(ColorToken::Secondary),
                            $theme->color(ColorToken::Surface),
                            0.12,
                        )
                        : ($semanticBackground
                            ?? $theme->color(ColorToken::Secondary))),
                borderColor: $bordered && !$outlined
                    ? $theme->color(ColorToken::Surface)
                    : ($semanticBackground ?? $theme->color(ColorToken::Border)),
                borderWidth: $bordered ? 2.0 : ($outlined ? 1.0 : 0.0),
                borderRadius: $tile ? 0.0 : 9999.0,
                overflow: Overflow::Hidden,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
                elevation: $variant === MaterialVariant::Elevated
                    ? self::resolvedElevation($props, 1.0)
                    : 0.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if ($part === 'PIcon') {
            $size = match ($props['size'] ?? null) {
                'x-small' => 16.0,
                'small' => 20.0,
                'large' => 28.0,
                'x-large' => 32.0,
                default => is_numeric($props['size'] ?? null) ? (float) $props['size'] : 24.0,
            };
            $requestedColor = is_int($props['color'] ?? null)
                ? $props['color']
                : null;

            return new Style(
                width: $size,
                height: $size,
                minWidth: $size,
                minHeight: $size,
                textColor: $requestedColor
                    ?? self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::OnSurface),
                backgroundColor: $transparent,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if ($part === 'PBadge') {
            $isDot = (bool) ($props['dot'] ?? false);
            $badgeSize = $isDot
                ? MaterialTokens::BADGE_DOT_SIZE
                : MaterialTokens::BADGE_LABEL_HEIGHT;

            return new Style(
                width: $isDot ? $badgeSize : null,
                height: $badgeSize,
                minWidth: $badgeSize,
                minHeight: $badgeSize,
                paddingHorizontal: $isDot ? 0.0 : 4.0,
                paddingVertical: 0.0,
                borderWidth: (
                    ($props['border'] ?? false)
                    || ($props['bordered'] ?? false)
                ) ? 2.0 : 0.0,
                borderRadius: ($props['tile'] ?? false)
                    ? 0.0
                    : (($props['rounded'] ?? true) ? 9999.0 : 4.0),
                backgroundColor: self::semanticColor(
                    $props + ['color' => 'error'],
                    $theme,
                    false,
                ) ?? $theme->color(ColorToken::Destructive),
                textColor: self::semanticColor(
                    $props + ['color' => 'error'],
                    $theme,
                    true,
                ) ?? $theme->color(ColorToken::DestructiveForeground),
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        $parentVariants = self::stringKeyedArray(
            $props['__parentVariants'] ?? null,
        );
        if (
            $part === 'PText'
            && ($parentVariants['__pamParentComponent'] ?? null) === 'PBadge'
        ) {
            return new Style(
                fontSize: 11.0,
                lineHeight: 16.0,
                fontWeight: 500,
                textColor: self::semanticColor(
                    $parentVariants + ['color' => 'error'],
                    $theme,
                    true,
                ) ?? $theme->color(ColorToken::DestructiveForeground),
            );
        }

        if ($part === 'PProgressCircular') {
            $requestedSize = $props['size'] ?? null;
            $progressSize = is_numeric($requestedSize)
                ? (float) $requestedSize
                : match ($requestedSize) {
                    'x-small' => 16.0,
                    'small' => 24.0,
                    'large' => 48.0,
                    'x-large' => 64.0,
                    default => MaterialTokens::CIRCULAR_PROGRESS_SIZE,
                };

            return new Style(
                width: $progressSize,
                height: $progressSize,
                textColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::Primary),
                opacity: $opacity,
                animationDurationMs: 1400,
                animateChanges: true,
            );
        }

        if ($part === 'PProgressLinear') {
            $requestedHeight = $props['height'] ?? null;
            $progressHeight = is_numeric($requestedHeight)
                ? (float) $requestedHeight
                : MaterialTokens::PROGRESS_TRACK_THICKNESS;

            return new Style(
                widthPercent: 100.0,
                height: $progressHeight,
                minHeight: $progressHeight,
                backgroundColor: $theme->color(ColorToken::Secondary),
                textColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::Primary),
                borderRadius: 9999.0,
                opacity: $opacity,
                animationDurationMs: 2200,
                animateChanges: true,
            );
        }

        if ($part === 'PSkeletonLoader') {
            $skeletonType = $props['type'] ?? 'text';
            $boilerplate = ($props['boilerplate'] ?? false) === true;
            $reduceMotion = ($props['reduceMotion'] ?? false) === true;
            $skeletonHeight = match ($skeletonType) {
                'avatar', 'button' => 40.0,
                'chip' => 32.0,
                'heading' => 28.0,
                'image' => 160.0,
                'card', 'article' => 220.0,
                default => 16.0,
            };

            return new Style(
                widthPercent: 100.0,
                height: $skeletonHeight,
                minHeight: $skeletonHeight,
                maxWidth: match ($skeletonType) {
                    'avatar' => 40.0,
                    'button' => 112.0,
                    'chip' => 80.0,
                    'heading' => 224.0,
                    default => null,
                },
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                borderRadius: match ($skeletonType) {
                    'avatar', 'chip' => MaterialTokens::radius(MaterialShape::Full),
                    'card', 'article', 'image' => MaterialTokens::radius(MaterialShape::Medium),
                    default => MaterialTokens::radius(MaterialShape::ExtraSmall),
                },
                elevation: self::resolvedElevation($props, 0.0),
                opacity: $boilerplate ? $opacity * 0.72 : $opacity,
                animationDurationMs: $boilerplate || $reduceMotion
                    ? null
                    : 1500,
                animateChanges: !$boilerplate && !$reduceMotion,
            );
        }

        if ($part === 'POverlay') {
            return new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                backgroundColor: $transparent,
                opacity: 1.0,
            );
        }

        if ($part === 'PDialog') {
            $fullscreen = ($props['fullscreen'] ?? false) === true;
            $requestedWidth = is_numeric($props['width'] ?? null)
                ? max(280.0, (float) $props['width'])
                : null;
            $requestedMaxWidth = is_numeric($props['maxWidth'] ?? null)
                ? max(280.0, (float) $props['maxWidth'])
                : 560.0;

            return new Style(
                margin: $fullscreen ? 0.0 : 24.0,
                width: $fullscreen ? null : $requestedWidth,
                widthPercent: $requestedWidth === null ? 100.0 : null,
                heightPercent: $fullscreen ? 100.0 : null,
                maxWidth: $fullscreen ? null : $requestedMaxWidth,
                minHeight: 140.0,
                paddingHorizontal: 24.0,
                paddingVertical: 24.0,
                gap: 16.0,
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderRadius: $fullscreen
                    ? 0.0
                    : MaterialTokens::radius(MaterialShape::ExtraLarge),
                elevation: self::resolvedElevation($props, 6.0),
                opacity: $opacity,
            );
        }

        if ($part === 'PMenu') {
            return new Style(
                minWidth: 112.0,
                maxWidth: 280.0,
                paddingVertical: 8.0,
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: self::resolvedElevation($props, 3.0),
                opacity: $opacity,
            );
        }

        if ($part === 'PSnackbar') {
            $location = strtolower(trim(
                is_string($props['location'] ?? null)
                    ? $props['location']
                    : 'bottom',
            ));
            $atTop = str_contains($location, 'top');
            $atEnd = str_contains($location, 'right')
                || str_contains($location, 'end');
            $atStart = str_contains($location, 'left')
                || str_contains($location, 'start');
            $vertical = ($props['vertical'] ?? false) === true;

            return new Style(
                minHeight: $vertical ? 68.0 : 48.0,
                maxWidth: 672.0,
                paddingHorizontal: 16.0,
                paddingVertical: 12.0,
                gap: 12.0,
                backgroundColor: 0xFF1B2A3C,
                textColor: 0xFFF7FAFF,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: self::resolvedElevation($props, 6.0),
                opacity: $opacity,
                flexDirection: $vertical
                    ? FlexDirection::Column
                    : FlexDirection::Row,
                alignItems: $vertical ? Align::Start : Align::Center,
                zIndex: 1000,
                positionType: PositionType::Absolute,
                left: $atEnd ? null : 8.0,
                top: $atTop ? 8.0 : null,
                right: $atStart ? null : 8.0,
                bottom: $atTop ? null : 8.0,
                safeAreaBottom: !$atTop,
            );
        }

        if ($part === 'PBottomSheet') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 220.0,
                paddingHorizontal: 24.0,
                paddingTop: 12.0,
                paddingBottom: 32.0,
                gap: 16.0,
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderRadius: 0.0,
                borderTopLeftRadius: MaterialTokens::radius(MaterialShape::ExtraLarge),
                borderTopRightRadius: MaterialTokens::radius(MaterialShape::ExtraLarge),
                borderBottomRightRadius: 0.0,
                borderBottomLeftRadius: 0.0,
                elevation: self::resolvedElevation($props, 6.0),
                opacity: $opacity,
            );
        }

        if ($part === 'PTooltip') {
            return new Style(
                widthPercent: 100.0,
                backgroundColor: 0x00000000,
                elevation: 0.0,
                opacity: $opacity,
            );
        }

        if (in_array($part, ['PBtn', 'PIconBtn', 'PFab'], true)) {
            $icon = $part === 'PFab'
                ? (($props['extended'] ?? false) !== true)
                : ($part !== 'PBtn' || ($props['icon'] ?? false) === true);
            $buttonGroupItem = $part === 'PBtn'
                && ($props['__pamButtonGroupItem'] ?? false) === true;
            $buttonToggleItem = $part === 'PBtn'
                && ($props['__pamButtonToggleItem'] ?? false) === true;
            $segmentedItem = $buttonGroupItem || $buttonToggleItem;
            $selected = ($props['selected'] ?? false) === true;
            $block = ($props['block'] ?? false) === true;
            $stacked = ($props['stacked'] ?? false) === true;
            $slim = ($props['slim'] ?? false) === true;
            $semanticBackground = self::semanticColor($props, $theme, false);
            $semanticForeground = self::semanticColor($props, $theme, true);
            $disabledForeground = self::withOpacity(
                $theme->color(ColorToken::OnSurface),
                MaterialTokens::STATE_OPACITY[6],
            );
            $disabledContainer = self::withOpacity(
                $theme->color(ColorToken::OnSurface),
                0.12,
            );
            $height = match ($part) {
                'PFab' => match ($props['size'] ?? null) {
                    'xs', 'x-small', 'sm', 'small',
                    ComponentSize::ExtraSmall->value,
                    ComponentSize::Small->value => MaterialTokens::FAB_SMALL_SIZE,
                    'md', 'medium', ComponentSize::Medium->value =>
                        MaterialTokens::FAB_MEDIUM_SIZE,
                    'lg', 'large', 'xl', 'x-large',
                    ComponentSize::Large->value,
                    ComponentSize::ExtraLarge->value => MaterialTokens::FAB_LARGE_SIZE,
                    default => MaterialTokens::componentSize(
                        $props['size'] ?? null,
                        MaterialTokens::FAB_SIZE,
                    ),
                },
                'PIconBtn' => MaterialTokens::buttonHeightForSize(
                    $props['size'] ?? null,
                    $density,
                ),
                default => MaterialTokens::buttonHeightForSize(
                    $props['size'] ?? null,
                    $density,
                ),
            };
            $effectiveHeight = $stacked ? max(56.0, $height) : $height;
            $rounded = $props['rounded'] ?? null;
            $radius = match (true) {
                ($props['tile'] ?? false) === true, $rounded === false,
                    $rounded === 0, $rounded === '0' => 0.0,
                $part === 'PFab' => $effectiveHeight >= MaterialTokens::FAB_LARGE_SIZE
                    ? MaterialTokens::radius(MaterialShape::ExtraLarge)
                    : MaterialTokens::radius(MaterialShape::Large),
                $icon, $rounded === 'pill',
                    $rounded === 'full' => $effectiveHeight / 2.0,
                $rounded === true, $rounded === 'xl' =>
                    MaterialTokens::radius(MaterialShape::ExtraLarge),
                $rounded === 'lg' => MaterialTokens::radius(MaterialShape::Large),
                $rounded === 'sm' => MaterialTokens::radius(MaterialShape::Small),
                default => MaterialTokens::radius(MaterialShape::Full),
            };

            return new Style(
                widthPercent: $block ? 100.0 : null,
                minWidth: $block
                    ? null
                    : ($icon
                        ? $effectiveHeight
                        : 64.0),
                width: !$block && $icon ? $effectiveHeight : null,
                minHeight: $effectiveHeight,
                height: $effectiveHeight,
                paddingHorizontal: $icon
                    ? 0.0
                    : ($slim
                        ? 8.0
                        : MaterialTokens::buttonHorizontalSpace(
                            $props['size'] ?? null,
                        )),
                paddingVertical: $stacked ? 8.0 : 0.0,
                gap: $stacked ? 4.0 : 8.0,
                textColor: match (true) {
                    $disabled => $disabledForeground,
                    $segmentedItem && $selected => $semanticForeground
                        ?? $theme->color(
                            $buttonToggleItem
                                ? ColorToken::SecondaryForeground
                                : ColorToken::PrimaryForeground,
                        ),
                    $segmentedItem => $theme->color(ColorToken::OnSurface),
                    $variant === MaterialVariant::Tonal => $semanticForeground
                        ?? $theme->color(ColorToken::SecondaryForeground),
                    in_array($variant, [
                        MaterialVariant::Outlined,
                        MaterialVariant::Text,
                        MaterialVariant::Plain,
                    ], true) => $semanticBackground
                        ?? $theme->color(ColorToken::Primary),
                    default => $semanticForeground
                        ?? $theme->color(ColorToken::PrimaryForeground),
                },
                backgroundColor: match (true) {
                    $disabled && $segmentedItem && $selected => $disabledContainer,
                    $disabled && $segmentedItem => $transparent,
                    $disabled && in_array($variant, [
                        MaterialVariant::Outlined,
                        MaterialVariant::Text,
                        MaterialVariant::Plain,
                    ], true) => $transparent,
                    $disabled => $disabledContainer,
                    $buttonGroupItem && $selected => $semanticBackground
                        ?? $theme->color(ColorToken::Primary),
                    $buttonToggleItem && $selected => $semanticBackground
                        ?? $theme->color(ColorToken::Secondary),
                    $segmentedItem => $transparent,
                    $variant === MaterialVariant::Tonal => $semanticBackground
                        ?? $theme->color(ColorToken::Secondary),
                    in_array($variant, [
                        MaterialVariant::Outlined,
                        MaterialVariant::Text,
                        MaterialVariant::Plain,
                    ], true) => $transparent,
                    default => $semanticBackground
                        ?? $theme->color(ColorToken::Primary),
                },
                borderColor: match (true) {
                    $disabled => $disabledContainer,
                    $segmentedItem => $theme->color(ColorToken::Outline),
                    $variant === MaterialVariant::Outlined => $semanticBackground
                        ?? $theme->color(ColorToken::Outline),
                    default => $theme->color(ColorToken::OutlineVariant),
                },
                borderWidth: $segmentedItem
                    || $variant === MaterialVariant::Outlined
                        ? 1.0
                        : 0.0,
                borderRadius: $radius,
                elevation: $disabled || $buttonGroupItem
                    ? 0.0
                    : (($props['flat'] ?? false) === true
                    || $variant !== MaterialVariant::Elevated
                        ? 0.0
                        : self::resolvedElevation(
                            $props,
                            $part === 'PFab' ? 3.0 : 1.0,
                        )),
                opacity: 1.0,
                flexDirection: $stacked ? FlexDirection::Column : FlexDirection::Row,
                alignItems: Align::Center,
                alignSelf: $block ? Align::Stretch : null,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, ['PCard', 'PSheet', 'PEmptyState'], true)) {
            $contentContainer = $part === 'PEmptyState';
            $semanticBackground = self::semanticColor($props, $theme, false);
            $semanticForeground = self::semanticColor($props, $theme, true);
            $horizontal = $part === 'PCard' && (
                ($props['horizontal'] ?? false) === true
                || ($props['direction'] ?? null) === 'horizontal'
            );
            $card = $part === 'PCard';
            $outlinedCard = $card && (
                $variant === MaterialVariant::Outlined
                || ($props['border'] ?? false) === true
            );
            $filledCard = $card && in_array(
                $variant,
                [
                    MaterialVariant::Flat,
                    MaterialVariant::Tonal,
                    MaterialVariant::Filled,
                ],
                true,
            );
            $surfaceBackground = match (true) {
                $semanticBackground !== null => $semanticBackground,
                $outlinedCard => $theme->color(ColorToken::Surface),
                $filledCard => $theme->color(ColorToken::SurfaceContainerHighest),
                $card => $theme->color(ColorToken::SurfaceContainerLow),
                $variant === MaterialVariant::Tonal => $theme->color(ColorToken::Secondary),
                default => $theme->color(ColorToken::Surface),
            };

            return new Style(
                widthPercent: 100.0,
                padding: $contentContainer ? match ($density) {
                    MaterialDensity::Default => 16.0,
                    MaterialDensity::Comfortable => 12.0,
                    MaterialDensity::Compact => 8.0,
                } : 0.0,
                gap: $contentContainer ? 12.0 : 0.0,
                backgroundColor: $surfaceBackground,
                textColor: $semanticForeground
                    ?? $theme->color(
                        !$card && $variant === MaterialVariant::Tonal
                            ? ColorToken::SecondaryForeground
                            : ColorToken::OnSurface,
                    ),
                borderColor: $semanticBackground ?? $theme->color(ColorToken::OutlineVariant),
                borderWidth: $outlinedCard
                    || (!$card && $variant === MaterialVariant::Outlined)
                        ? MaterialTokens::OUTLINE_WIDTH
                        : 0.0,
                borderRadius: ($props['tile'] ?? false) === true
                    ? 0.0
                    : (($props['rounded'] ?? null) === 'xl'
                        ? MaterialTokens::radius(MaterialShape::ExtraLarge)
                        : MaterialTokens::CARD_RADIUS),
                elevation: !$disabled && $variant === MaterialVariant::Elevated
                    ? self::resolvedElevation($props, 1.0)
                    : 0.0,
                opacity: $opacity,
                overflow: Overflow::Hidden,
                flexDirection: $horizontal ? FlexDirection::Row : null,
            );
        }

        if (in_array($part, [
            'PTextField', 'PTextarea', 'PNumberInput', 'POtpInput',
            'PColorInput', 'PDateInput', 'PSelect',
            'PAutocomplete', 'PCombobox',
        ], true)) {
            $selectionField = in_array($part, [
                'PSelect', 'PAutocomplete', 'PCombobox',
            ], true);
            $controlHeight = match (true) {
                $selectionField => 64.0,
                $part === 'PNumberInput'
                    && ($props['controlVariant'] ?? null) === 'stacked' => 112.0,
                $part === 'PNumberInput' => 64.0,
                default => $height,
            };
            $focused = ($props['focused'] ?? $props['active'] ?? false) === true;
            $error = ($props['error'] ?? false) === true
                || ($props['errorMessages'] ?? []) !== [];
            $outlined = $variant === MaterialVariant::Outlined;
            $underlined = $variant === MaterialVariant::Underlined;
            $plain = $variant === MaterialVariant::Plain;
            $solo = in_array($variant, [
                MaterialVariant::Solo,
                MaterialVariant::SoloInverted,
                MaterialVariant::SoloFilled,
            ], true);
            $fieldBorder = $error
                ? $theme->color(ColorToken::Destructive)
                : ($focused
                    ? $theme->color(ColorToken::Primary)
                    : $theme->color(ColorToken::Outline));
            $textareaBaseHeight = match ($density) {
                MaterialDensity::Comfortable => 104.0,
                MaterialDensity::Compact => 96.0,
                default => 112.0,
            };
            $textareaRows = max(1, (int) ($props['rows'] ?? 3));
            if (
                $part === 'PTextarea'
                && ($props['autoGrow'] ?? false) === true
            ) {
                $textareaValue = (string) (
                    $props['modelValue'] ?? $props['value'] ?? ''
                );
                $contentRows = substr_count(
                    str_replace(["\r\n", "\r"], "\n", $textareaValue),
                    "\n",
                ) + 1;
                $maximumRows = max(
                    $textareaRows,
                    (int) ($props['maxRows'] ?? PHP_INT_MAX),
                );
                $textareaRows = min(
                    max($textareaRows, $contentRows),
                    $maximumRows,
                );
            }
            $textareaHeight = $textareaBaseHeight
                + (24.0 * max(0, $textareaRows - 3));

            return new Style(
                height: $part === 'PTextarea' ? $textareaHeight : $controlHeight,
                minHeight: $part === 'PTextarea' ? $textareaHeight : $controlHeight,
                paddingHorizontal: $underlined || $plain ? 0.0 : 16.0,
                paddingTop: $selectionField ? 0.0 : match ($density) {
                    MaterialDensity::Comfortable => 4.0,
                    MaterialDensity::Compact => 0.0,
                    default => 8.0,
                },
                paddingBottom: $selectionField ? 0.0 : match ($density) {
                    MaterialDensity::Comfortable => 2.0,
                    MaterialDensity::Compact => 0.0,
                    default => 4.0,
                },
                gap: 0.0,
                backgroundColor: match ($variant) {
                    MaterialVariant::Outlined,
                    MaterialVariant::Underlined,
                    MaterialVariant::Plain => $transparent,
                    MaterialVariant::Solo => $theme->color(ColorToken::Surface),
                    MaterialVariant::SoloInverted => $focused
                        ? $theme->color(ColorToken::Surface)
                        : $theme->color(ColorToken::Muted),
                    default => $theme->color(ColorToken::SurfaceSunken),
                },
                textColor: $theme->color(ColorToken::OnSurface),
                placeholderColor: $theme->color(ColorToken::MutedForeground),
                borderColor: $fieldBorder,
                borderWidth: $outlined ? ($focused || $error ? 2.0 : 1.0) : 0.0,
                borderBottomWidth: $outlined || $solo || $plain
                    ? null
                    : ($focused || $error ? 2.0 : 1.0),
                borderRadius: $underlined || $plain
                    ? 0.0
                    : MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: in_array($variant, [
                    MaterialVariant::Solo,
                    MaterialVariant::SoloInverted,
                ], true) ? 1.0 : 0.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Column,
                animationDurationMs: 150,
                animateChanges: true,
            );
        }

        if ($part === 'PChip') {
            $semanticBackground = self::semanticColor($props, $theme, false);
            $selected = ($props['selected'] ?? $props['active'] ?? false) === true;
            $chipBaseHeight = ($props['__pamChipGroupItem'] ?? false) === true
                ? 42.0
                : match ($density) {
                MaterialDensity::Comfortable => 28.0,
                MaterialDensity::Compact => 24.0,
                default => MaterialTokens::CHIP_HEIGHT,
                };
            $chipHeight = MaterialTokens::componentSize(
                $props['size'] ?? null,
                $chipBaseHeight,
            );

            return new Style(
                minHeight: $chipHeight,
                height: $chipHeight,
                paddingHorizontal: ($props['prependIcon'] ?? $props['icon'] ?? null) !== null
                    ? MaterialTokens::CHIP_ICON_SPACE
                    : MaterialTokens::CHIP_HORIZONTAL_SPACE,
                gap: MaterialTokens::CHIP_ICON_SPACE,
                textColor: $selected
                    ? $theme->color(ColorToken::SecondaryForeground)
                    : ($variant === MaterialVariant::Outlined
                    ? ($semanticBackground
                        ?? $theme->color(ColorToken::Primary))
                    : (self::semanticColor($props, $theme, true)
                        ?? $theme->color(ColorToken::SecondaryForeground))),
                backgroundColor: $selected
                    ? $theme->color(ColorToken::Secondary)
                    : ($variant === MaterialVariant::Outlined
                    ? $transparent
                    : ($semanticBackground
                        ?? $theme->color(ColorToken::Secondary))),
                borderColor: $semanticBackground ?? $theme->color(ColorToken::Outline),
                borderWidth: !$selected && $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::CHIP_RADIUS,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                alignSelf: Align::Start,
                justifyContent: Justify::Center,
                animationDurationMs: 150,
                animateChanges: true,
            );
        }

        if ($part === 'PCardActions') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 56.0,
                padding: 8.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                flexWrap: FlexWrap::Wrap,
                alignItems: Align::Center,
                justifyContent: Justify::End,
            );
        }

        if (in_array($part, [
            'PAppBar', 'PToolbar', 
        ], true)) {
            $semanticBackground = self::semanticColor($props, $theme, false);
            $semanticForeground = self::semanticColor($props, $theme, true);
            $barHeight = match ($density) {
                MaterialDensity::Comfortable => 56.0,
                MaterialDensity::Compact => 48.0,
                default => 64.0,
            };
            if (($props['prominent'] ?? false) === true) {
                $barHeight = 128.0;
            }

            return new Style(
                widthPercent: 100.0,
                minHeight: $barHeight,
                paddingHorizontal: match ($part) {
                    'PAppBar' => 4.0,
                    default => 16.0,
                },
                paddingVertical: null,
                gap: 8.0,
                backgroundColor: $semanticBackground
                    ?? $theme->color(ColorToken::Surface),
                textColor: $semanticForeground
                    ?? $theme->color(ColorToken::OnSurface),
                tintColor: $semanticForeground
                    ?? $theme->color(ColorToken::OnSurface),
                elevation: self::resolvedElevation(
                    $props,
                    0.0,
                ),
                positionType: ($props['prominent'] ?? false) === true
                    ? PositionType::Relative
                    : null,
                flexDirection: FlexDirection::Row,
                alignItems: ($props['prominent'] ?? false) === true
                    ? Align::Start
                    : Align::Center,
                justifyContent: null,
            );
        }

        if (in_array($part, [
            'PBannerActions',
            'PStepperActions', 'PStepperVerticalActions',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: in_array(
                    $part,
                    ['PBannerActions'],
                    true,
                ) ? Justify::End : null,
            );
        }

        if ($part === 'PRow') {
            return new Style(
                widthPercent: 100.0,
                minWidth: 0.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Stretch,
            );
        }

        if ($part === 'PCol') {
            return new Style(
                minWidth: 0.0,
                flexDirection: FlexDirection::Column,
                alignItems: Align::Stretch,
            );
        }

        if ($part === 'PSpacer') {
            return new Style(
                minWidth: 0.0,
                minHeight: 0.0,
                flexGrow: 1.0,
            );
        }





        if (in_array($part, [
            'PAlert', 'PBanner', 'PSnackbar',
        ], true)) {
            $semanticColor = self::semanticColor($props, $theme, false);

            return new Style(
                widthPercent: 100.0,
                minHeight: $part === 'PSnackbar' ? 48.0 : 64.0,
                minWidth: null,
                paddingHorizontal: 16.0,
                paddingVertical: match ($part) {
                    'PSnackbar' => 14.0,
                    'PAlert' => 16.0,
                    'PBanner' => 8.0,
                },
                gap: 12.0,
                backgroundColor: $semanticColor
                    ?? $theme->color(ColorToken::SurfaceElevated),
                borderColor: $semanticColor ?? $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined
                    ? 1.0
                    : 0.0,
                borderBottomWidth: $part === 'PBanner' ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: self::resolvedElevation(
                    $props,
                    $part === 'PSnackbar' ? 6.0 : 0.0,
                ),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PSpeedDial') {
            $horizontal = in_array(
                strtolower((string) ($props['direction'] ?? 'top')),
                ['start', 'end', 'left', 'right', 'horizontal'],
                true,
            );

            return new Style(
                minWidth: 56.0,
                minHeight: 56.0,
                padding: 0.0,
                gap: 8.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Large),
                flexDirection: $horizontal
                    ? FlexDirection::Row
                    : FlexDirection::Column,
                alignItems: Align::Start,
            );
        }

        if (in_array($part, [
            'PDialog', 'PMenu', 'PBottomSheet', 'POverlay', 'PTooltip',
        ], true)) {
            return new Style(
                paddingHorizontal: match ($part) {
                    'PTooltip' => 16.0,
                    'PDialog' => 24.0,
                    default => 16.0,
                },
                paddingVertical: match ($part) {
                    'PTooltip' => 5.0,
                    'PDialog' => 16.0,
                    default => 16.0,
                },
                gap: 8.0,
                backgroundColor: $part === 'POverlay'
                    ? $theme->color(ColorToken::Overlay)
                    : $theme->color(ColorToken::SurfaceElevated),
                borderColor: $theme->color(ColorToken::OutlineVariant),
                borderWidth: in_array($part, ['PMenu'], true)
                    ? 1.0
                    : 0.0,
                borderRadius: match ($part) {
                    'PBottomSheet' => 0.0,
                    'PTooltip' => MaterialTokens::radius(MaterialShape::ExtraSmall),
                    default => MaterialTokens::radius(MaterialShape::ExtraSmall),
                },
                elevation: self::resolvedElevation(
                    $props,
                    match ($part) {
                        'POverlay' => 0.0,
                        'PDialog' => 5.0,
                        'PBottomSheet' => 4.0,
                        default => 3.0,
                    },
                ),
                opacity: $part === 'POverlay' ? 0.20 : $opacity,
                overflow: Overflow::Hidden,
            );
        }

        if (in_array($part, [
            'PList', 'PDataTable',
            'PDataTableVirtual', 'PTreeview',
            'PInfiniteScroll',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                gap: 0.0,
                paddingVertical: in_array($part, [
                    'PList', 'PTreeview',
                ], true) ? 8.0 : null,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::OutlineVariant),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: 0.0,
                overflow: Overflow::Hidden,
                opacity: $opacity,
            );
        }

        if (in_array($part, [
            'PListItem', 'PTreeviewItem',
            'PDataTableRow', 'PItem', 'PSlideGroupItem',
        ], true)) {
            $requestedLines = $props['lines'] ?? 1;
            $lines = match (is_string($requestedLines)
                ? strtolower(trim($requestedLines))
                : $requestedLines) {
                2, '2', 'two' => 2,
                3, '3', 'three' => 3,
                default => 1,
            };
            $slideGroupItem = $part === 'PSlideGroupItem';

            return new Style(
                widthPercent: $slideGroupItem ? null : 100.0,
                // Let labels size naturally while preserving Material's 48dp
                // minimum touch target. A larger content minimum is additive to
                // horizontal padding and can push the last item off compact panes.
                minWidth: $slideGroupItem ? 48.0 : null,
                minHeight: match ($lines) {
                    2 => MaterialTokens::LIST_TWO_LINE_HEIGHT,
                    3 => MaterialTokens::LIST_THREE_LINE_HEIGHT,
                    default => MaterialTokens::LIST_ONE_LINE_HEIGHT,
                } + match ($density) {
                    MaterialDensity::Comfortable => -4.0,
                    MaterialDensity::Compact => -8.0,
                    default => 0.0,
                },
                paddingHorizontal: MaterialTokens::LIST_HORIZONTAL_SPACE,
                paddingVertical: MaterialTokens::LIST_VERTICAL_SPACE,
                gap: MaterialTokens::LIST_CONTENT_GAP,
                textColor: ($props['selected'] ?? false)
                    ? $theme->color(
                        $slideGroupItem
                            ? ColorToken::SecondaryForeground
                            : ColorToken::AccentForeground,
                    )
                    : $theme->color(
                        $slideGroupItem
                            ? ColorToken::MutedForeground
                            : ColorToken::OnSurface,
                    ),
                backgroundColor: ($props['selected'] ?? false)
                    ? $theme->color(
                        $slideGroupItem
                            ? ColorToken::Secondary
                            : ColorToken::Accent,
                    )
                    : ($slideGroupItem
                        ? $theme->color(ColorToken::Muted)
                        : $transparent),
                borderRadius: $slideGroupItem
                    ? MaterialTokens::radius(MaterialShape::Full)
                    : ($part === 'PItem'
                        ? MaterialTokens::radius(MaterialShape::Medium)
                        : 0.0),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PBtnGroup') {
            $connected = ($props['connected'] ?? $props['divided'] ?? false) === true;
            $fill = ($props['block'] ?? $props['fullWidth'] ?? false) === true;
            $rtl = ($props['rtl'] ?? false) === true;

            return new Style(
                widthPercent: $fill ? 100.0 : null,
                minHeight: MaterialTokens::MINIMUM_TOUCH_TARGET,
                gap: $connected
                    ? 2.0
                    : MaterialTokens::SPACE_SMALL,
                // Disabled state is propagated to every child button, whose
                // Material colors already include the required state alpha.
                // Applying opacity again at the group level would compound it.
                opacity: 1.0,
                flexDirection: $rtl
                    ? FlexDirection::RowReverse
                    : FlexDirection::Row,
                alignItems: Align::Center,
                alignSelf: $fill ? Align::Stretch : null,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if ($part === 'PBtnToggle') {
            $fill = ($props['block'] ?? $props['fullWidth'] ?? false) === true;
            $rtl = ($props['rtl'] ?? false) === true;

            return new Style(
                widthPercent: $fill ? 100.0 : null,
                minHeight: MaterialTokens::MINIMUM_TOUCH_TARGET,
                gap: 0.0,
                opacity: 1.0,
                flexDirection: $rtl
                    ? FlexDirection::RowReverse
                    : FlexDirection::Row,
                alignItems: Align::Center,
                alignSelf: $fill ? Align::Stretch : null,
            );
        }

        if (in_array($part, [
            'PCheckbox', 'PRadio', 'PSwitch', 'PRadioGroup',
            'PItemGroup', 'PChipGroup',
        ], true)) {
            $selectionControl = in_array($part, [
                'PCheckbox', 'PRadio', 'PSwitch',
            ], true);
            $selectionBaseSize = match ($density) {
                MaterialDensity::Comfortable => 36.0,
                MaterialDensity::Compact => 28.0,
                default => 40.0,
            };
            $selectionSize = MaterialTokens::componentSize(
                $props['size'] ?? null,
                $selectionBaseSize,
            );

            $groupVertical = $part === 'PItemGroup'
                ? (($props['direction'] ?? null) !== 'horizontal'
                    && ($props['inline'] ?? false) !== true)
                : ($part === 'PChipGroup'
                    && (($props['direction'] ?? null) === 'vertical'
                        || ($props['column'] ?? false) === true));

            return new Style(
                widthPercent: $part === 'PItemGroup' ? 100.0 : null,
                minWidth: in_array($part, ['PCheckbox', 'PRadio'], true)
                    ? $selectionSize
                    : null,
                minHeight: $selectionControl
                    ? $selectionSize
                    : ($groupVertical
                        ? max(50.0, ((int) ($props['__pamChildCount'] ?? 1)) * 50.0)
                        : 40.0),
                gap: 8.0,
                opacity: $opacity,
                flexDirection: $groupVertical
                    ? FlexDirection::Column
                    : FlexDirection::Row,
                alignItems: $groupVertical ? Align::Stretch : Align::Center,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if (in_array($part, [
            'PSlider', 'PRangeSlider', 'PProgressLinear',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                height: $part === 'PProgressLinear' ? 4.0 : null,
                minHeight: $part === 'PProgressLinear' ? 4.0 : 40.0,
                backgroundColor: $theme->color(ColorToken::Secondary),
                borderRadius: MaterialTokens::radius(MaterialShape::Full),
                opacity: $opacity,
            );
        }

        if (in_array($part, ['PProgressCircular', 'PRating'], true)) {
            $progressSize = MaterialTokens::componentSize(
                $props['size'] ?? null,
                $part === 'PProgressCircular' ? 32.0 : 40.0,
            );

            return new Style(
                width: $part === 'PProgressCircular' ? $progressSize : null,
                height: $progressSize,
                textColor: $theme->color(ColorToken::Primary),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if (in_array($part, [
            'PTabs', 'PSlideGroup', 
            'PStepperHeader',
        ], true)) {
            $verticalTabs = in_array($part, ['PTabs', 'PSlideGroup'], true)
                && (
                    ($props['direction'] ?? null) === 'vertical'
                    || ($props['vertical'] ?? false) === true
                );
            $tabAlignment = $props['alignTabs'] ?? null;

            return new Style(
                widthPercent: $verticalTabs ? null : 100.0,
                width: $verticalTabs ? 160.0 : null,
                minHeight: $part === 'PTabs'
                    ? MaterialTokens::MINIMUM_TOUCH_TARGET
                    : 48.0,
                paddingHorizontal: 8.0,
                gap: 4.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                flexDirection: $verticalTabs
                    ? FlexDirection::Column
                    : FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: match ($tabAlignment) {
                    'center' => Justify::Center,
                    'end' => Justify::End,
                    default => null,
                },
            );
        }

        if (in_array($part, [
            'PTab', 'PAppBarNavIcon', 
        ], true)) {
            $navigationIcon = $part === 'PAppBarNavIcon';

            return new Style(
                width: $navigationIcon ? 48.0 : null,
                height: $navigationIcon ? 48.0 : null,
                minHeight: $part === 'PTab'
                    ? MaterialTokens::MINIMUM_TOUCH_TARGET
                    : 48.0,
                minWidth: $part === 'PTab'
                    ? (($props['fixedTabs'] ?? false) ? 160.0 : 90.0)
                    : 48.0,
                flexGrow: $part === 'PTab'
                    && (
                        ($props['grow'] ?? false)
                        || ($props['fixedTabs'] ?? false)
                    ) ? 1.0 : null,
                paddingHorizontal: $navigationIcon ? 0.0 : 16.0,
                gap: 8.0,
                textColor: ($props['selected'] ?? false)
                    ? $theme->color(ColorToken::Primary)
                    : $theme->color(ColorToken::MutedForeground),
                backgroundColor: $transparent,
                borderRadius: $part === 'PTab'
                    ? 0.0
                    : MaterialTokens::radius(MaterialShape::Full),
                opacity: $part === 'PAppBarNavIcon' && $disabled
                    ? MaterialTokens::STATE_OPACITY[6]
                    : $opacity,
                flexDirection: ($props['stacked'] ?? false)
                    ? FlexDirection::Column
                    : FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, [
            'PStepper', 'PStepperVertical', 'PExpansionPanels',
            'PTimeline', 'PCarousel', 'PDatePicker', 'PTimePicker',
            'PCalendar',
        ], true)) {
            $bareContainer = in_array($part, [
                'PStepper', 'PStepperVertical', 'PExpansionPanels',
            ], true);

            return new Style(
                widthPercent: 100.0,
                padding: $bareContainer ? 0.0 : 16.0,
                gap: $bareContainer ? 0.0 : 12.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: self::resolvedElevation(
                    $props,
                    in_array($part, [
                        'PStepper', 'PStepperVertical',
                    ], true) ? 1.0 : 0.0,
                ),
                opacity: $opacity,
                overflow: Overflow::Hidden,
            );
        }

        if (in_array($part, [
            'PStepperItem', 'PStepperVerticalItem', 'PExpansionPanel',
            'PExpansionPanelTitle', 'PExpansionPanelText', 'PTimelineItem',
            'PCarouselItem',
            'PCalendarDay',
        ], true)) {
            $expansionTitle = $part === 'PExpansionPanelTitle';
            $expansionText = $part === 'PExpansionPanelText';
            $expansionPanel = $part === 'PExpansionPanel';
            $horizontalStep = $part === 'PStepperItem';
            $activeStep = $horizontalStep && (
                ($props['active'] ?? false) === true
                || ($props['selected'] ?? false) === true
            );

            return new Style(
                widthPercent: $horizontalStep ? null : 100.0,
                minWidth: $horizontalStep ? 88.0 : null,
                flexGrow: $horizontalStep ? 1.0 : null,
                minHeight: $expansionTitle ? 48.0 : null,
                padding: $horizontalStep ? 24.0 : null,
                paddingHorizontal: $expansionPanel
                    ? 0.0
                    : ($horizontalStep
                        ? null
                        : ($expansionTitle || $expansionText ? 24.0 : 12.0)),
                paddingVertical: $expansionPanel
                    ? 0.0
                    : ($horizontalStep
                        ? null
                        : ($expansionTitle ? 16.0 : 12.0)),
                gap: $expansionPanel ? 0.0 : 8.0,
                textColor: $expansionTitle
                    ? $theme->color(ColorToken::OnSurface)
                    : ($expansionText
                        ? $theme->color(ColorToken::MutedForeground)
                        : ($activeStep
                            ? $theme->color(ColorToken::Primary)
                            : $theme->color(ColorToken::MutedForeground))),
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderBottomWidth: $activeStep
                    ? 2.0
                    : (in_array(
                        $part,
                        ['PExpansionPanelTitle'],
                        true,
                    ) ? 1.0 : 0.0),
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                opacity: $horizontalStep
                    ? ($activeStep ? 1.0 : 0.6)
                    : $opacity,
            );
        }

        if ($part === 'PIcon') {
            $diameter = MaterialTokens::componentSize(
                $props['size'] ?? null,
                24.0,
            );

            return new Style(
                width: $diameter,
                height: $diameter,
                textColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::OnSurface),
                backgroundColor: $transparent,
                borderRadius: MaterialTokens::radius(MaterialShape::Full),
                overflow: Overflow::Hidden,
                opacity: $opacity,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if (in_array($part, ['PImg'], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: 120.0,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                borderRadius: MaterialTokens::radius(MaterialShape::Medium),
                overflow: Overflow::Hidden,
                opacity: $opacity,
            );
        }

        if ($part === 'PDivider') {
            $vertical = ($props['vertical'] ?? false) === true
                || ($props['orientation'] ?? null) === 2
                || ($props['orientation'] ?? null) === 'vertical';
            $inset = ($props['inset'] ?? false) === true && !$vertical;
            $thickness = is_numeric($props['thickness'] ?? null)
                ? max(1.0, (float) $props['thickness'])
                : 1.0;

            return new Style(
                // A percentage width plus a leading margin overflows its
                // parent. Let flex stretch resolve the remaining inline size
                // for inset dividers so both edges stay on the layout grid.
                widthPercent: $vertical || $inset ? null : 100.0,
                width: $vertical ? $thickness : null,
                height: $vertical ? 48.0 : $thickness,
                minWidth: $vertical ? $thickness : null,
                minHeight: $vertical ? 48.0 : $thickness,
                marginLeft: $inset ? 72.0 : null,
                backgroundColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::Border),
                opacity: $opacity,
            );
        }

        if ($part === 'PSkeletonLoader') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                borderRadius: MaterialTokens::radius(MaterialShape::Small),
                opacity: 0.72,
            );
        }

        if ($part === 'PBadge') {
            $badgeSize = MaterialTokens::componentSize(
                $props['size'] ?? null,
                20.0,
                4.0,
            );

            return new Style(
                minWidth: $badgeSize,
                minHeight: $badgeSize,
                paddingHorizontal: max(4.0, $badgeSize * 0.3),
                paddingVertical: 2.0,
                backgroundColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::Secondary),
                borderColor: $theme->color(ColorToken::Background),
                borderWidth: 2.0,
                borderRadius: $badgeSize / 2.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                animationDurationMs: 150,
                animateChanges: true,
            );
        }

        if (in_array($part, ['PForm'], true)) {
            return new Style(widthPercent: 100.0, opacity: $opacity);
        }

        if ($part === 'PSparkline') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 64.0,
                textColor: $theme->color(ColorToken::Primary),
                opacity: $opacity,
            );
        }

        return new Style(opacity: $opacity);
    }

    private static function number(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        return is_int($value) ? (float) $value : 0.0;
    }

    private static function withOpacity(int $argb, float $opacity): int
    {
        $alpha = (int) round(255 * max(0.0, min(1.0, $opacity)));

        return (($alpha & 0xff) << 24) | ($argb & 0x00ffffff);
    }

    public static function blendArgb(
        int $foreground,
        int $background,
        float $amount,
    ): int {
        $amount = max(0.0, min(1.0, $amount));
        $channel = static fn (int $color, int $shift): int =>
            ($color >> $shift) & 0xff;
        $blend = static fn (int $from, int $to): int =>
            (int) round(($from * $amount) + ($to * (1.0 - $amount)));

        return (0xff << 24)
            | ($blend($channel($foreground, 16), $channel($background, 16)) << 16)
            | ($blend($channel($foreground, 8), $channel($background, 8)) << 8)
            | $blend($channel($foreground, 0), $channel($background, 0));
    }

    /** @param array<string, mixed> $props */
    private static function semanticColor(
        array $props,
        Theme $theme,
        bool $foreground,
    ): ?int {
        $requested = $props['action'] ?? $props['color'] ?? null;
        if (is_int($requested) && ($requested > 0xFF || $requested < 0)) {
            return $requested;
        }
        $token = match ($requested) {
            'primary' => $foreground
                ? ColorToken::PrimaryForeground
                : ColorToken::Primary,
            'secondary' => $foreground
                ? ColorToken::SecondaryForeground
                : ColorToken::Secondary,
            2, 'success' => $foreground
                ? ColorToken::SuccessForeground
                : ColorToken::Success,
            3, 'warning' => $foreground
                ? ColorToken::WarningForeground
                : ColorToken::Warning,
            5, 'info' => $foreground
                ? ColorToken::InfoForeground
                : ColorToken::Info,
            4, 'error', 'danger', 'destructive' => $foreground
                ? ColorToken::DestructiveForeground
                : ColorToken::Destructive,
            default => null,
        };

        return $token === null ? null : $theme->color($token);
    }

    /** @param array<string, mixed> $props */
    public static function semanticForeground(
        array $props,
        Theme $theme,
    ): ?int {
        return self::semanticColor($props, $theme, true);
    }

    /** @param array<string, mixed> $props */
    public static function semanticBackground(
        array $props,
        Theme $theme,
    ): ?int {
        return self::semanticColor($props, $theme, false);
    }

    /** @param array<string, mixed> $props */
    private static function resolvedElevation(
        array $props,
        float $fallback,
    ): float {
        if (!is_numeric($props['elevation'] ?? null)) {
            return $fallback;
        }

        return max(0.0, min(24.0, (float) $props['elevation']));
    }

    /** @return array<string, mixed> */
    private static function stringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
