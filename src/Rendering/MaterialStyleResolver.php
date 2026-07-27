<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\MaterialShape;
use Pam\MobileUi\Enum\MaterialVariant;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\MaterialTokens;
use Pam\MobileUi\Theme\Theme;
use Pam\Native\Align;
use Pam\Native\FlexDirection;
use Pam\Native\Justify;
use Pam\Native\Overflow;
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
            MaterialVariant::Tonal->value, 'tonal' => MaterialVariant::Tonal,
            MaterialVariant::Outlined->value, 'outlined', 'outline' => MaterialVariant::Outlined,
            MaterialVariant::Text->value, 'text' => MaterialVariant::Text,
            MaterialVariant::Plain->value, 'plain' => MaterialVariant::Plain,
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

        if (in_array($part, [
            'PCardTitle', 'PCardSubtitle', 'PCardText',
        ], true)) {
            $title = $part === 'PCardTitle';
            $subtitle = $part === 'PCardSubtitle';

            return new Style(
                paddingHorizontal: 16.0,
                paddingTop: $title ? 16.0 : ($subtitle ? 0.0 : 16.0),
                paddingBottom: $title ? 4.0 : ($subtitle ? 16.0 : 16.0),
                textColor: $subtitle
                    ? $theme->color(ColorToken::MutedForeground)
                    : $theme->color(ColorToken::OnSurface),
                fontSize: $title ? 20.0 : 14.0,
                opacity: $opacity,
                textAlign: TextAlignment::Start,
                fontWeight: $title ? 400 : 400,
                letterSpacing: $title ? 0.0 : (0.25 / 14.0),
                lineHeight: match ($part) {
                    'PCardTitle' => match ($density) {
                        MaterialDensity::Compact => 24.8,
                        default => 28.0,
                    },
                    'PCardSubtitle' => match ($density) {
                        MaterialDensity::Comfortable => 18.0,
                        MaterialDensity::Compact => 16.0,
                        default => 20.0,
                    },
                    default => match ($density) {
                        MaterialDensity::Comfortable => 19.2,
                        MaterialDensity::Compact => 18.4,
                        default => 20.0,
                    },
                },
            );
        }

        if (in_array($part, ['PAppBarTitle', 'PToolbarTitle'], true)) {
            return new Style(
                textColor: $theme->color(ColorToken::OnSurface),
                fontSize: 20.0,
                opacity: $opacity,
                textAlign: TextAlignment::Start,
                fontWeight: 400,
                letterSpacing: 0.0,
                lineHeight: 28.0,
                flexShrink: 1.0,
            );
        }

        if ($part === 'PAlertTitle') {
            return new Style(
                textColor: $theme->color(ColorToken::OnSurface),
                fontSize: 24.0,
                opacity: $opacity,
                textAlign: TextAlignment::Start,
                fontWeight: 400,
                letterSpacing: 0.0,
                lineHeight: 28.0,
            );
        }

        if ($part === 'PBannerText') {
            $requestedLines = $props['lines'] ?? 3;
            $lines = is_numeric($requestedLines) ? (int) $requestedLines : 3;

            return new Style(
                paddingRight: 90.0,
                textColor: $theme->color(ColorToken::OnSurface),
                fontSize: 14.0,
                opacity: $opacity,
                textAlign: TextAlignment::Start,
                fontWeight: 400,
                letterSpacing: 0.25 / 14.0,
                lineHeight: 20.0,
                numberOfLines: max(1, min(3, $lines)),
            );
        }

        if ($part === 'PField') {
            $focused = ($props['focused'] ?? $props['active'] ?? false) === true;
            $error = ($props['error'] ?? false) === true
                || ($props['errorMessages'] ?? []) !== [];
            $disabled = ($props['disabled'] ?? false) === true;
            $outlined = $variant === MaterialVariant::Outlined;
            $borderColor = $error
                ? $theme->color(ColorToken::Destructive)
                : ($focused
                    ? $theme->color(ColorToken::Primary)
                    : $theme->color(ColorToken::Border));

            return new Style(
                widthPercent: 100.0,
                minHeight: MaterialTokens::fieldHeight($density),
                paddingHorizontal: 16.0,
                gap: 8.0,
                alignItems: Align::Center,
                backgroundColor: $outlined
                    ? $transparent
                    : $theme->color(ColorToken::SurfaceSunken),
                borderColor: $borderColor,
                borderWidth: $outlined ? ($focused || $error ? 2.0 : 1.0) : 0.0,
                borderBottomWidth: $outlined ? null : ($focused || $error ? 2.0 : 1.0),
                borderRadius: 4.0,
                opacity: $disabled ? 0.38 : 1.0,
                animationDurationMs: 150,
            );
        }

        if ($part === 'PAlert') {
            $semanticBackground = self::semanticColor($props, $theme, false);
            $alertBorder = $props['border'] ?? null;
            $rtl = ($props['rtl'] ?? false) === true;
            $startBorder = $alertBorder === 'start';
            $endBorder = $alertBorder === 'end';
            $topBorder = $alertBorder === 'top';
            $bottomBorder = $alertBorder === 'bottom';
            $verticalPadding = match ($density) {
                MaterialDensity::Comfortable => 12.0,
                MaterialDensity::Compact => 8.0,
                default => 16.0,
            };

            return new Style(
                widthPercent: 100.0,
                paddingHorizontal: 16.0,
                paddingVertical: $verticalPadding,
                paddingLeft: ($startBorder && !$rtl) || ($endBorder && $rtl)
                    ? 24.0
                    : null,
                paddingRight: ($endBorder && !$rtl) || ($startBorder && $rtl)
                    ? 24.0
                    : null,
                paddingTop: $topBorder ? $verticalPadding + 8.0 : null,
                paddingBottom: $bottomBorder ? $verticalPadding + 8.0 : null,
                gap: 16.0,
                backgroundColor: in_array(
                    $variant,
                    [MaterialVariant::Outlined, MaterialVariant::Text, MaterialVariant::Plain],
                    true,
                ) ? $transparent : ($semanticBackground
                    ?? $theme->color(ColorToken::Surface)),
                borderColor: $semanticBackground
                    ?? $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderLeftWidth: ($startBorder && !$rtl) || ($endBorder && $rtl)
                    ? 8.0
                    : null,
                borderTopWidth: $topBorder ? 8.0 : null,
                borderRightWidth: ($endBorder && !$rtl) || ($startBorder && $rtl)
                    ? 8.0
                    : null,
                borderBottomWidth: $bottomBorder ? 8.0 : null,
                borderRadius: 4.0,
                opacity: $opacity,
                elevation: $variant === MaterialVariant::Elevated ? 1.0 : 0.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PBanner') {
            $requestedLines = $props['lines'] ?? 2;
            $lines = max(
                1,
                min(3, is_numeric($requestedLines) ? (int) $requestedLines : 2),
            );
            $densityOffset = match ($density) {
                MaterialDensity::Comfortable => -4.0,
                MaterialDensity::Compact => -8.0,
                default => 0.0,
            };

            return new Style(
                widthPercent: 100.0,
                paddingLeft: 16.0,
                paddingRight: 8.0,
                paddingTop: max(0.0, match ($lines) {
                    1 => 10.0,
                    3 => 24.0,
                    default => 16.0,
                } + $densityOffset),
                paddingBottom: max(0.0, match ($lines) {
                    1 => 8.0,
                    default => 16.0,
                } + $densityOffset),
                gap: 24.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderBottomWidth: 1.0,
                borderColor: $theme->color(ColorToken::Border),
                borderRadius: 0.0,
                elevation: 0.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PBannerActions') {
            return new Style(
                gap: 8.0,
                marginTop: 20.0,
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
            $active = ($props['active'] ?? $props['expanded'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                padding: 0.0,
                marginVertical: $active ? 16.0 : 0.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderRadius: 4.0,
                opacity: $disabled ? 0.26 : 1.0,
            );
        }

        if ($part === 'PExpansionPanelTitle') {
            $active = ($props['active'] ?? $props['expanded'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                minHeight: $active ? 64.0 : 48.0,
                paddingHorizontal: 24.0,
                paddingVertical: 16.0,
                gap: 16.0,
                opacity: $disabled ? 0.26 : 1.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PExpansionPanelText') {
            return new Style(
                widthPercent: 100.0,
                paddingHorizontal: 24.0,
                paddingTop: 8.0,
                paddingBottom: 16.0,
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
                elevation: 1.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PStepperHeader') {
            return new Style(
                widthPercent: 100.0,
                elevation: 1.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, ['PStepperItem', 'PStepperVerticalItem'], true)) {
            $active = ($props['active'] ?? $props['selected'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                padding: 24.0,
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
            return new Style(
                widthPercent: 100.0,
                minHeight: 344.0,
                borderWidth: 1.0,
                borderRadius: 0.0,
                borderColor: $theme->color(ColorToken::Border),
                backgroundColor: $theme->color(ColorToken::Surface),
                textColor: $theme->color(ColorToken::OnSurface),
                opacity: $opacity,
            );
        }

        if ($part === 'PCalendarHeader') {
            return new Style(
                widthPercent: 100.0,
                height: 56.0,
                minHeight: 56.0,
                paddingHorizontal: 4.0,
                borderWidth: 1.0,
                borderRadius: 0.0,
                borderColor: $theme->color(ColorToken::Border),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'PCalendarDay') {
            $outside = ($props['outside'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                minHeight: 56.0,
                paddingTop: 4.0,
                borderWidth: 1.0,
                borderRadius: 0.0,
                borderColor: $theme->color(ColorToken::Border),
                backgroundColor: $outside
                    ? $theme->color(ColorToken::SurfaceSunken)
                    : $theme->color(ColorToken::Surface),
                fontSize: 12.0,
                lineHeight: 20.0,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PCalendarInterval') {
            return new Style(
                minWidth: 50.0,
                minHeight: 20.0,
                paddingRight: 8.0,
                borderWidth: 1.0,
                borderRadius: 0.0,
                borderColor: $theme->color(ColorToken::Border),
                fontSize: 10.0,
                lineHeight: 20.0,
                textAlign: TextAlignment::End,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'POtpInput') {
            $divided = ($props['divided'] ?? false) === true;

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

        if ($part === 'PFileUpload') {
            $inset = ($props['inset'] ?? false) === true;

            return new Style(
                widthPercent: 100.0,
                minHeight: $inset ? 96.0 : 184.0,
                paddingHorizontal: 16.0,
                paddingVertical: $inset ? 16.0 : 64.0,
                gap: 16.0,
                borderWidth: 2.0,
                borderRadius: 4.0,
                borderColor: $theme->color(ColorToken::Border),
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PFileUploadItem') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 64.0,
                marginTop: 16.0,
                marginBottom: 16.0,
                padding: 16.0,
                gap: 12.0,
                borderWidth: 1.0,
                borderRadius: 4.0,
                borderColor: $theme->color(ColorToken::Border),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if (in_array($part, [
            'PAutocomplete',
            'PCombobox',
            'PSelect',
            'PFileInput',
            'PNumberInput',
            'PDateInput',
            'PColorInput',
        ], true)) {
            $fieldHeight = (
                in_array($part, ['PAutocomplete', 'PCombobox'], true)
                && ($props['chips'] ?? false)
            ) ? 64.0 : MaterialTokens::fieldHeight($density);

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

            return new Style(
                widthPercent: 100.0,
                height: $height > 0.0 ? $height : null,
                minHeight: ($props['cardMedia'] ?? false) ? 200.0 : 1.0,
                borderRadius: ($props['rounded'] ?? false) ? 4.0 : 0.0,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                opacity: ($props['loading'] ?? false) ? 0.60 : 1.0,
                animationDurationMs: 200,
                animateChanges: true,
            );
        }

        if ($part === 'PResponsive') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 1.0,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }

        if ($part === 'PParallax') {
            $height = self::number($props['height'] ?? null);

            return new Style(
                widthPercent: 100.0,
                height: $height > 0.0 ? $height : 500.0,
                minHeight: $height > 0.0 ? $height : 500.0,
                overflow: Overflow::Hidden,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                opacity: $opacity,
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
            'PTable',
            'PDataTable',
            'PDataTableServer',
            'PDataTableVirtual',
        ], true)) {
            $rowHeight = match ($density) {
                MaterialDensity::Comfortable => 44.0,
                MaterialDensity::Compact => 36.0,
                default => 52.0,
            };

            return new Style(
                widthPercent: 100.0,
                minHeight: $rowHeight,
                paddingHorizontal: 0.0,
                borderWidth: 0.0,
                borderRadius: 8.0,
                borderColor: $theme->color(ColorToken::Border),
                backgroundColor: $theme->color(ColorToken::Surface),
                fontSize: 14.0,
                lineHeight: 21.0,
                opacity: ($props['loading'] ?? false) ? 0.38 : $opacity,
                overflow: Overflow::Hidden,
                animationDurationMs: 280,
                animateChanges: true,
            );
        }

        if ($part === 'PDataIterator') {
            return new Style(
                widthPercent: 100.0,
                gap: 16.0,
                paddingVertical: 8.0,
                backgroundColor: 0x00000000,
                opacity: ($props['loading'] ?? false) ? 0.38 : $opacity,
                animationDurationMs: 280,
                animateChanges: true,
            );
        }

        if (in_array($part, [
            'PVirtualScroll',
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
                backgroundColor: ($props['active'] ?? false)
                    ? $theme->color(ColorToken::Secondary)
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
                || ($props['vertical'] ?? false);

            return new Style(
                widthPercent: $vertical ? null : 100.0,
                width: $vertical ? 32.0 : null,
                minWidth: $vertical ? 32.0 : null,
                minHeight: $vertical ? 300.0 : 32.0,
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
            'PSelectionControl',
            'PCheckbox',
            'PCheckboxBtn',
            'PRadio',
            'PSwitch',
        ], true)) {
            $controlSize = match ($density) {
                MaterialDensity::Comfortable => 36.0,
                MaterialDensity::Compact => 28.0,
                default => 40.0,
            };

            return new Style(
                minHeight: $controlSize,
                minWidth: $part === 'PSwitch' ? 40.0 : $controlSize,
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
            'PSelectionControlGroup',
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

        if ($part === 'PPicker') {
            return new Style(
                borderRadius: 4.0,
                elevation: 0,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'PPickerTitle') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                paddingHorizontal: 12.0,
                paddingVertical: 8.0,
                fontWeight: 400,
                opacity: $opacity,
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
                elevation: self::number($props['elevation'] ?? null),
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'PDatePickerHeader') {
            return new Style(
                widthPercent: 100.0,
                height: 70.0,
                minHeight: 70.0,
                paddingHorizontal: 12.0,
                paddingVertical: 4.0,
                alignItems: Align::Center,
                opacity: $opacity,
            );
        }

        if ($part === 'PDatePickerMonth') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 288.0,
                paddingHorizontal: 12.0,
                paddingBottom: 8.0,
                gap: 4.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PDatePickerMonths') {
            return new Style(
                widthPercent: 100.0,
                height: 288.0,
                minHeight: 288.0,
                paddingHorizontal: 12.0,
                gap: 24.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PDatePickerYears') {
            return new Style(
                widthPercent: 100.0,
                height: 288.0,
                minHeight: 288.0,
                paddingHorizontal: 32.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PTimePicker') {
            return new Style(
                width: 328.0,
                minWidth: 328.0,
                padding: 24.0,
                borderRadius: 4.0,
                elevation: self::number($props['elevation'] ?? null),
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'PTimePickerControls') {
            return new Style(
                widthPercent: 100.0,
                height: 80.0,
                minHeight: 80.0,
                gap: 0.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                fontSize: 56.0,
                lineHeight: 74.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PTimePickerClock') {
            return new Style(
                width: 290.0,
                height: 290.0,
                minWidth: 290.0,
                minHeight: 290.0,
                padding: 10.0,
                borderRadius: 9999.0,
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
                opacity: $opacity,
            );
        }

        if ($part === 'PColorPicker') {
            return new Style(
                width: 300.0,
                minWidth: 300.0,
                borderRadius: 4.0,
                elevation: is_numeric($props['elevation'] ?? null)
                    ? (float) $props['elevation']
                    : 1.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: $opacity,
            );
        }

        if ($part === 'PColorPickerCanvas') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 150.0,
                borderRadius: 0.0,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PColorPickerPreview') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 46.0,
                padding: 16.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                opacity: $opacity,
            );
        }

        if ($part === 'PColorPickerEdit') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 64.0,
                marginTop: 24.0,
                marginBottom: 8.0,
                paddingHorizontal: 16.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                opacity: $opacity,
            );
        }

        if ($part === 'PColorPickerSwatches') {
            return new Style(
                widthPercent: 100.0,
                paddingHorizontal: 16.0,
                paddingBottom: 10.0,
                gap: 4.0,
                borderRadius: 8.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PBreadcrumbs') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 56.0,
                paddingHorizontal: 12.0,
                paddingVertical: 16.0,
                gap: 0.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }

        if ($part === 'PBreadcrumbsItem') {
            return new Style(
                minHeight: 24.0,
                paddingHorizontal: 4.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                borderRadius: 4.0,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PBreadcrumbsDivider') {
            return new Style(
                minHeight: 24.0,
                paddingHorizontal: 8.0,
                fontSize: 16.0,
                lineHeight: 24.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PPagination') {
            $buttonSize = MaterialTokens::iconButtonHeight($density);

            return new Style(
                widthPercent: 100.0,
                minHeight: $buttonSize,
                gap: 9.6,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }

        if ($part === 'PCarousel') {
            $requestedHeight = $props['height'] ?? null;
            $height = is_numeric($requestedHeight) ? (float) $requestedHeight : 500.0;

            return new Style(
                widthPercent: 100.0,
                height: $height,
                minHeight: $height,
                borderRadius: self::number($props['borderRadius'] ?? null),
                backgroundColor: $theme->color(ColorToken::SurfaceSunken),
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
                height: max(40.0, $itemSize),
                minHeight: max(40.0, $itemSize),
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
                minHeight: $dotSize + 48.0,
                padding: 24.0,
                gap: 24.0,
                borderWidth: is_numeric($props['lineThickness'] ?? null)
                    ? (float) $props['lineThickness']
                    : 2.0,
                borderRadius: 0.0,
                backgroundColor: 0x00000000,
                opacity: $opacity,
            );
        }

        if ($part === 'PWindow' || $part === 'PWindowItem') {
            return new Style(
                widthPercent: 100.0,
                minHeight: $part === 'PWindowItem' ? 1.0 : null,
                backgroundColor: 0x00000000,
                opacity: $opacity,
                animationDurationMs: 300,
                animateChanges: true,
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
            $size -= match ($density) {
                MaterialDensity::Comfortable => 4.0,
                MaterialDensity::Compact => 8.0,
                default => 0.0,
            };

            return new Style(
                width: $size,
                height: $size,
                minWidth: $size,
                minHeight: $size,
                borderRadius: 9999.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
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

            return new Style(
                width: $size,
                height: $size,
                minWidth: $size,
                minHeight: $size,
                opacity: ($props['disabled'] ?? false) ? 0.38 : 1.0,
            );
        }

        if ($part === 'PFab') {
            $size = match ($props['size'] ?? null) {
                'x-small' => 40.0,
                'small' => 48.0,
                'large' => 72.0,
                'x-large' => 96.0,
                default => is_numeric($props['size'] ?? null) ? (float) $props['size'] : 56.0,
            };
            $isFlat = in_array($props['variant'] ?? null, ['flat', 'text', 'plain'], true);

            return new Style(
                width: $size,
                height: $size,
                minWidth: $size,
                minHeight: $size,
                borderRadius: 9999.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
                backgroundColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::Primary),
                elevation: $isFlat ? 0 : 3,
                opacity: ($props['disabled'] ?? false) ? 0.26 : 1.0,
            );
        }

        if ($part === 'PBadge') {
            $isDot = (bool) ($props['dot'] ?? false);
            $badgeSize = $isDot ? 9.0 : match ($props['size'] ?? 'default') {
                'x-small' => 16.0,
                'small' => 18.0,
                'large' => 24.0,
                'x-large' => 28.0,
                default => is_numeric($props['size'] ?? null)
                    ? max(12.0, (float) $props['size'])
                    : 20.0,
            };

            return new Style(
                width: $badgeSize,
                height: $badgeSize,
                minWidth: $badgeSize,
                minHeight: $badgeSize,
                paddingHorizontal: 0.0,
                paddingVertical: 0.0,
                borderWidth: ($props['bordered'] ?? false) ? 2.0 : 0.0,
                borderRadius: 9999.0,
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
                fontSize: 12.0,
                lineHeight: 12.0,
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
                    default => 32.0,
                };

            return new Style(
                width: $progressSize,
                height: $progressSize,
                opacity: $opacity,
                animationDurationMs: 1400,
                animateChanges: true,
            );
        }

        if ($part === 'PProgressLinear') {
            $requestedHeight = $props['height'] ?? null;
            $progressHeight = is_numeric($requestedHeight) ? (float) $requestedHeight : 4.0;

            return new Style(
                widthPercent: 100.0,
                height: $progressHeight,
                minHeight: $progressHeight,
                backgroundColor: $theme->color(ColorToken::Secondary),
                borderRadius: 9999.0,
                opacity: $opacity,
                animationDurationMs: 2200,
                animateChanges: true,
            );
        }

        if ($part === 'PSkeletonLoader') {
            $skeletonType = $props['type'] ?? 'text';

            return new Style(
                widthPercent: 100.0,
                minHeight: match ($skeletonType) {
                    'avatar', 'button' => 40.0,
                    'chip' => 32.0,
                    'heading' => 28.0,
                    'image' => 160.0,
                    'card', 'article' => 220.0,
                    default => 16.0,
                },
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
                opacity: $opacity,
                animationDurationMs: 1500,
                animateChanges: true,
            );
        }

        if ($part === 'POverlay') {
            return new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                backgroundColor: Color::rgb(0, 0, 0, 82)->argb,
                opacity: 1.0,
            );
        }

        if ($part === 'PDialog') {
            return new Style(
                margin: 24.0,
                widthPercent: 100.0,
                maxWidth: 560.0,
                minHeight: 140.0,
                paddingHorizontal: 24.0,
                paddingVertical: 24.0,
                gap: 16.0,
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraLarge),
                elevation: 6.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PMenu') {
            return new Style(
                minWidth: 220.0,
                paddingVertical: 8.0,
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: 3.0,
                opacity: $opacity,
            );
        }

        if ($part === 'PSnackbar') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                maxWidth: 672.0,
                paddingHorizontal: 16.0,
                paddingVertical: 12.0,
                gap: 12.0,
                backgroundColor: 0xFF1B2A3C,
                textColor: 0xFFF7FAFF,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: 6.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PSnackbarQueue') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                maxWidth: 672.0,
                paddingHorizontal: 16.0,
                paddingVertical: 8.0,
                gap: 12.0,
                backgroundColor: 0xFF1B2A3C,
                textColor: 0xFFF7FAFF,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: 6.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PBottomSheet') {
            return new Style(
                widthPercent: 100.0,
                minHeight: 220.0,
                paddingHorizontal: 24.0,
                paddingTop: 24.0,
                paddingBottom: 32.0,
                gap: 16.0,
                backgroundColor: $theme->color(ColorToken::SurfaceElevated),
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraLarge),
                elevation: 6.0,
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

        if (self::textPart($part)) {
            [$size, $line, $weight, $tracking] =
                MaterialTokens::TYPE_SCALE[self::typeScale($part, $props)];

            return new Style(
                textColor: self::textColor($props, $theme),
                fontSize: $size,
                lineHeight: $line,
                fontWeight: (int) $weight,
                letterSpacing: $tracking / $size,
                paddingHorizontal: in_array($part, [
                    'PCardTitle', 'PCardSubtitle', 'PCardText',
                ], true) ? 16.0 : null,
                paddingVertical: match ($part) {
                    'PCardTitle' => 8.0,
                    'PCardText' => 16.0,
                    default => null,
                },
                opacity: $opacity,
                textAlign: TextAlignment::Start,
            );
        }

        if (in_array($part, ['PBtn', 'PIconBtn', 'PFab'], true)) {
            $icon = $part !== 'PBtn' || ($props['icon'] ?? false) === true;
            $height = match ($part) {
                'PFab' => match ($props['size'] ?? null) {
                    'small', 1 => 40.0,
                    'large', 3 => 96.0,
                    default => 56.0,
                },
                'PIconBtn' => MaterialTokens::iconButtonHeight($density),
                default => match ($props['size'] ?? null) {
                    'x-small', 'xs' => 20.0,
                    'small', 'sm' => 28.0,
                    'large', 'lg' => 44.0,
                    'x-large', 'xl' => 52.0,
                    default => MaterialTokens::buttonHeight($density),
                },
            };

            return new Style(
                minWidth: $icon ? $height : 64.0,
                width: $icon ? $height : null,
                minHeight: $height,
                height: $height,
                paddingHorizontal: $icon ? 0.0 : 16.0,
                gap: 8.0,
                textColor: match ($variant) {
                    MaterialVariant::Tonal =>
                        $theme->color(ColorToken::SecondaryForeground),
                    MaterialVariant::Outlined, MaterialVariant::Text,
                    MaterialVariant::Plain =>
                        $theme->color(ColorToken::Primary),
                    default => $theme->color(ColorToken::PrimaryForeground),
                },
                backgroundColor: match ($variant) {
                    MaterialVariant::Tonal => $theme->color(ColorToken::Secondary),
                    MaterialVariant::Outlined, MaterialVariant::Text,
                    MaterialVariant::Plain => $transparent,
                    default => $theme->color(ColorToken::Primary),
                },
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: match (true) {
                    $part === 'PFab' => MaterialTokens::radius(MaterialShape::Large),
                    $icon => MaterialTokens::radius(MaterialShape::Full),
                    ($props['rounded'] ?? false) === true =>
                        MaterialTokens::radius(MaterialShape::ExtraLarge),
                    default => MaterialTokens::radius(MaterialShape::ExtraSmall),
                },
                elevation: $variant === MaterialVariant::Elevated ? 1.0 : 0.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, ['PCard', 'PSheet', 'PEmptyState', 'PPicker'], true)) {
            $contentContainer = in_array($part, ['PEmptyState', 'PPicker'], true);

            return new Style(
                padding: $contentContainer ? match ($density) {
                    MaterialDensity::Default => 16.0,
                    MaterialDensity::Comfortable => 12.0,
                    MaterialDensity::Compact => 8.0,
                } : 0.0,
                gap: $contentContainer ? 12.0 : 0.0,
                backgroundColor: $variant === MaterialVariant::Tonal
                    ? $theme->color(ColorToken::Secondary)
                    : $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: $variant === MaterialVariant::Elevated ? 1.0 : 0.0,
                opacity: $opacity,
            );
        }

        if (in_array($part, [
            'PInput', 'PTextField', 'PTextarea', 'PNumberInput', 'POtpInput',
            'PColorInput', 'PDateInput', 'PFileInput', 'PSelect',
            'PAutocomplete', 'PCombobox',
        ], true)) {
            return new Style(
                height: $part === 'PTextarea' ? match ($density) {
                    MaterialDensity::Comfortable => 104.0,
                    MaterialDensity::Compact => 96.0,
                    default => 112.0,
                } : $height,
                minHeight: $part === 'PTextarea' ? match ($density) {
                    MaterialDensity::Comfortable => 104.0,
                    MaterialDensity::Compact => 96.0,
                    default => 112.0,
                } : $height,
                paddingHorizontal: 16.0,
                paddingVertical: 8.0,
                gap: 0.0,
                backgroundColor: $variant === MaterialVariant::Outlined
                    ? $theme->color(ColorToken::Background)
                    : $theme->color(ColorToken::SurfaceSunken),
                textColor: $theme->color(ColorToken::OnSurface),
                placeholderColor: $theme->color(ColorToken::MutedForeground),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: 1.0,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                opacity: $opacity,
                flexDirection: FlexDirection::Column,
            );
        }

        if ($part === 'PChip') {
            $semanticBackground = self::semanticColor($props, $theme, false);
            $chipHeight = match ($density) {
                MaterialDensity::Comfortable => 28.0,
                MaterialDensity::Compact => 24.0,
                default => 32.0,
            };

            return new Style(
                minHeight: $chipHeight,
                paddingHorizontal: match ($density) {
                    MaterialDensity::Comfortable => 10.0,
                    MaterialDensity::Compact => 8.0,
                    default => 12.0,
                },
                gap: 6.0,
                backgroundColor: $variant === MaterialVariant::Outlined
                    ? $transparent
                    : ($semanticBackground
                        ?? $theme->color(ColorToken::Secondary)),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Full),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                alignSelf: Align::Start,
                justifyContent: Justify::Center,
            );
        }

        if ($part === 'PCardItem') {
            return new Style(
                paddingHorizontal: 16.0,
                paddingVertical: 10.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PCardActions') {
            return new Style(
                minHeight: 52.0,
                padding: 8.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, [
            'PAppBar', 'PToolbar', 'PBottomNavigation', 'PSystemBar', 'PFooter',
        ], true)) {
            $barHeight = match ($part) {
                'PSystemBar' => 24.0,
                'PBottomNavigation' => match ($density) {
                    MaterialDensity::Comfortable => 48.0,
                    MaterialDensity::Compact => 40.0,
                    default => 56.0,
                },
                'PAppBar', 'PToolbar' => match ($density) {
                    MaterialDensity::Comfortable => 56.0,
                    MaterialDensity::Compact => 48.0,
                    default => 64.0,
                },
                default => null,
            };

            return new Style(
                widthPercent: 100.0,
                minHeight: $barHeight,
                paddingHorizontal: match ($part) {
                    'PAppBar' => 4.0,
                    'PSystemBar' => 8.0,
                    'PBottomNavigation' => 0.0,
                    default => 16.0,
                },
                paddingVertical: $part === 'PFooter' ? 8.0 : null,
                gap: 8.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                elevation: is_numeric($props['elevation'] ?? null)
                    ? (float) $props['elevation']
                    : ($part === 'PBottomNavigation' ? 2.0 : 0.0),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: $part === 'PSystemBar' ? Justify::End : null,
            );
        }

        if (in_array($part, [
            'PApp', 'PMain', 'PLayout', 'PLayoutItem', 'PContainer',
            'PResponsive', 'PDefaultsProvider', 'PThemeProvider',
            'PLocaleProvider', 'PLazy', 'PWindow', 'PWindowItem',
            'PTransition',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                backgroundColor: in_array($part, ['PApp', 'PMain'], true)
                    ? $theme->color(ColorToken::Background)
                    : null,
                opacity: $opacity,
            );
        }

        if (in_array($part, [
            'PRow', 'PCardItem', 'PCardActions', 'PBannerActions',
            'PToolbarItems', 'PStepperActions', 'PStepperVerticalActions',
            'PDatePickerControls', 'PTimePickerControls',
            'PColorPickerPreview', 'PColorPickerEdit',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                gap: 8.0,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PCol') {
            return new Style();
        }

        if ($part === 'PSpacer') {
            return new Style(flexGrow: 1.0);
        }

        if (in_array($part, [
            'PAlert', 'PBanner', 'PSnackbar', 'PFileUpload',
            'PConfirmEdit',
        ], true)) {
            $semanticColor = self::semanticColor($props, $theme, false);
            $surfaceSemantic = in_array($part, [
                'PAlert', 'PBanner', 'PConfirmEdit',
            ], true);

            return new Style(
                widthPercent: 100.0,
                minHeight: $part === 'PSnackbar' ? 48.0 : 64.0,
                minWidth: null,
                paddingHorizontal: 16.0,
                paddingVertical: match ($part) {
                    'PSnackbar' => 14.0,
                    'PAlert' => 16.0,
                    'PBanner' => 8.0,
                    default => 12.0,
                },
                gap: 12.0,
                backgroundColor: $surfaceSemantic
                    ? $theme->color(ColorToken::SurfaceElevated)
                    : ($semanticColor ?? $theme->color(ColorToken::SurfaceElevated)),
                borderColor: $semanticColor ?? $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined
                    ? 1.0
                    : 0.0,
                borderBottomWidth: $part === 'PBanner' ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                elevation: $part === 'PSnackbar' ? 6.0 : 0.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if ($part === 'PSpeedDial') {
            return new Style(
                width: 56.0,
                height: 56.0,
                minHeight: 56.0,
                padding: 0.0,
                gap: 8.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Large),
            );
        }

        if (in_array($part, [
            'PDialog', 'PMenu', 'PBottomSheet', 'POverlay', 'PTooltip',
            'PNavigationDrawer', 'PSnackbarQueue',
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
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: in_array($part, ['PMenu', 'PNavigationDrawer'], true)
                    ? 1.0
                    : 0.0,
                borderRadius: match ($part) {
                    'PBottomSheet' => 0.0,
                    'PTooltip' => MaterialTokens::radius(MaterialShape::ExtraSmall),
                    'PNavigationDrawer' => 0.0,
                    default => MaterialTokens::radius(MaterialShape::ExtraSmall),
                },
                elevation: match ($part) {
                    'POverlay' => 0.0,
                    'PDialog' => 5.0,
                    'PBottomSheet' => 4.0,
                    'PNavigationDrawer' => 4.0,
                    default => 3.0,
                },
                opacity: $part === 'POverlay' ? 0.20 : $opacity,
                overflow: Overflow::Hidden,
            );
        }

        if (in_array($part, [
            'PList', 'PListGroup', 'PDataIterator', 'PDataTable',
            'PDataTableServer', 'PDataTableVirtual', 'PTable', 'PTreeview',
            'PVirtualScroll', 'PInfiniteScroll',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                gap: 0.0,
                paddingVertical: in_array($part, [
                    'PList', 'PListGroup', 'PTreeview',
                ], true) ? 8.0 : null,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: 0.0,
                overflow: Overflow::Hidden,
                opacity: $opacity,
            );
        }

        if (in_array($part, [
            'PListItem', 'PTreeviewItem', 'PFileUploadItem',
            'PDataTableRow', 'PItem', 'PSlideGroupItem',
        ], true)) {
            $itemHeight = $part === 'PDataTableRow'
                ? match ($density) {
                    MaterialDensity::Default => 52.0,
                    MaterialDensity::Comfortable => 48.0,
                    MaterialDensity::Compact => 44.0,
                }
                : match ($density) {
                    MaterialDensity::Default => 48.0,
                    MaterialDensity::Comfortable => 46.0,
                    MaterialDensity::Compact => 44.0,
                };
            $requestedLines = $props['lines'] ?? 1;
            $lines = is_numeric($requestedLines) ? (int) $requestedLines : 1;

            return new Style(
                widthPercent: 100.0,
                minHeight: match ($lines) {
                    2 => 64.0,
                    3 => 88.0,
                    default => 48.0,
                } + match ($density) {
                    MaterialDensity::Comfortable => -4.0,
                    MaterialDensity::Compact => -8.0,
                    default => 0.0,
                },
                paddingHorizontal: 16.0,
                paddingVertical: match ($lines) {
                    2 => 12.0,
                    3 => 16.0,
                    default => 4.0,
                },
                gap: 12.0,
                backgroundColor: ($props['selected'] ?? false)
                    ? $theme->color(ColorToken::Secondary)
                    : $transparent,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, [
            'PCheckbox', 'PCheckboxBtn', 'PRadio', 'PSwitch',
            'PSelectionControl', 'PSelectionControlGroup', 'PRadioGroup',
            'PItemGroup', 'PChipGroup', 'PBtnToggle', 'PBtnGroup',
        ], true)) {
            $selectionControl = in_array($part, [
                'PCheckbox', 'PCheckboxBtn', 'PRadio', 'PSwitch',
                'PSelectionControl',
            ], true);
            $selectionSize = match ($density) {
                MaterialDensity::Comfortable => 36.0,
                MaterialDensity::Compact => 28.0,
                default => 40.0,
            };

            return new Style(
                minWidth: in_array($part, ['PCheckbox', 'PCheckboxBtn', 'PRadio'], true)
                    ? $selectionSize
                    : null,
                minHeight: $selectionControl ? $selectionSize : 40.0,
                gap: 8.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
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
            return new Style(
                width: $part === 'PProgressCircular' ? 32.0 : null,
                height: $part === 'PProgressCircular' ? 32.0 : 40.0,
                textColor: $theme->color(ColorToken::Primary),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, [
            'PTabs', 'PSlideGroup', 'PBreadcrumbs', 'PPagination',
            'PStepperHeader', 'PCalendarHeader',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: $part === 'PTabs'
                    ? match ($density) {
                        MaterialDensity::Comfortable => 44.0,
                        MaterialDensity::Compact => 36.0,
                        default => 48.0,
                    }
                    : 48.0,
                paddingHorizontal: 8.0,
                gap: 4.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, [
            'PTab', 'PAppBarNavIcon', 'PBreadcrumbsItem',
        ], true)) {
            return new Style(
                minHeight: $part === 'PTab'
                    ? match ($density) {
                        MaterialDensity::Comfortable => 44.0,
                        MaterialDensity::Compact => 36.0,
                        default => 48.0,
                    }
                    : 48.0,
                minWidth: $part === 'PTab' ? 90.0 : 48.0,
                paddingHorizontal: 16.0,
                gap: 8.0,
                textColor: ($props['selected'] ?? false)
                    ? $theme->color(ColorToken::Primary)
                    : $theme->color(ColorToken::MutedForeground),
                backgroundColor: $transparent,
                borderRadius: $part === 'PTab'
                    ? 0.0
                    : MaterialTokens::radius(MaterialShape::ExtraSmall),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, [
            'PStepper', 'PStepperVertical', 'PExpansionPanels',
            'PTimeline', 'PCarousel', 'PDatePicker', 'PTimePicker',
            'PColorPicker', 'PCalendar',
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
                elevation: in_array($part, [
                    'PStepper', 'PStepperVertical',
                ], true) ? 1.0 : 0.0,
                opacity: $opacity,
                overflow: Overflow::Hidden,
            );
        }

        if (in_array($part, [
            'PStepperItem', 'PStepperVerticalItem', 'PExpansionPanel',
            'PExpansionPanelTitle', 'PExpansionPanelText', 'PTimelineItem',
            'PCarouselItem', 'PDatePickerHeader', 'PDatePickerMonth',
            'PDatePickerMonths', 'PDatePickerYears', 'PTimePickerClock',
            'PColorPickerCanvas', 'PColorPickerSwatches', 'PCalendarDay',
            'PCalendarInterval',
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
                paddingHorizontal: $expansionPanel
                    ? 0.0
                    : ($expansionTitle || $expansionText ? 24.0 : 12.0),
                paddingVertical: $expansionPanel
                    ? 0.0
                    : ($expansionTitle ? 16.0 : 12.0),
                gap: $expansionPanel ? 0.0 : 8.0,
                textColor: $activeStep
                    ? $theme->color(ColorToken::Primary)
                    : $theme->color(ColorToken::MutedForeground),
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderBottomWidth: $activeStep
                    ? 2.0
                    : (in_array(
                        $part,
                        ['PExpansionPanelTitle', 'PCalendarInterval'],
                        true,
                    ) ? 1.0 : 0.0),
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                opacity: $opacity,
            );
        }

        if (in_array($part, ['PAvatar', 'PIcon'], true)) {
            $diameter = match ($props['size'] ?? null) {
                'xs' => 24.0,
                'sm' => 32.0,
                'lg' => 48.0,
                'xl' => 64.0,
                default => $part === 'PIcon' ? 24.0 : 40.0,
            };

            return new Style(
                width: $diameter,
                height: $diameter,
                textColor: $theme->color(ColorToken::OnSurface),
                backgroundColor: $part === 'PAvatar'
                    ? $theme->color(ColorToken::Secondary)
                    : $transparent,
                borderRadius: MaterialTokens::radius(MaterialShape::Full),
                overflow: Overflow::Hidden,
                opacity: $opacity,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, ['PImg', 'PParallax'], true)) {
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
            return new Style(
                widthPercent: 100.0,
                height: 1.0,
                backgroundColor: $theme->color(ColorToken::Border),
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
            return new Style(
                minWidth: 20.0,
                minHeight: 20.0,
                paddingHorizontal: 6.0,
                paddingVertical: 4.0,
                backgroundColor: self::semanticColor($props, $theme, false)
                    ?? $theme->color(ColorToken::Secondary),
                borderColor: $theme->color(ColorToken::Background),
                borderWidth: 2.0,
                borderRadius: 10.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, ['PForm', 'PValidation', 'PHotkey', 'PHover'], true)) {
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

    private static function textPart(string $part): bool
    {
        return in_array($part, [
            'PText', 'PCardTitle', 'PCardSubtitle', 'PCardText',
            'PListItemTitle', 'PListItemSubtitle', 'PLabel', 'PMessages',
            'PCounter', 'PCode', 'PKbd', 'PAlertTitle', 'PAppBarTitle',
            'PBannerText', 'PBreadcrumbsItem', 'PBreadcrumbsDivider',
            'PFieldLabel', 'PListSubheader', 'PPickerTitle', 'PToolbarTitle',
        ], true);
    }

    /** @param array<string, mixed> $props */
    private static function typeScale(string $part, array $props): int
    {
        $parent = self::stringKeyedArray($props['__parentVariants'] ?? null);
        $parentComponent = $parent['__pamParentComponent'] ?? null;
        if ($part === 'PText') {
            $parentScale = match ($parentComponent) {
                'PBtn', 'PFab' => 14,
                'PChip' => 17,
                'PBadge', 'PSystemBar', 'PBottomNavigation' => 15,
                'PSnackbar', 'PBanner' => 14,
                default => null,
            };
            if ($parentScale !== null) {
                return $parentScale;
            }
        }

        return match ($part) {
            'PCardTitle', 'PAppBarTitle', 'PToolbarTitle', 'PPickerTitle' => 7,
            'PAlertTitle' => 6,
            'PCardText', 'PCardSubtitle', 'PListItemSubtitle',
            'PBannerText', 'PListSubheader' => 12,
            'PListItemTitle' => 11,
            'PLabel', 'PFieldLabel' => 14,
            'PMessages', 'PCounter', 'PKbd', 'PCode' => 13,
            default => match ($props['size'] ?? null) {
                'display-large' => 1,
                'display-medium' => 2,
                'display-small' => 3,
                'headline-large' => 4,
                'headline-medium' => 5,
                'headline-small' => 6,
                'title-large' => 7,
                'title-medium' => 8,
                'title-small' => 9,
                '3xl' => 3,
                '2xl' => 4,
                'xl' => 6,
                'lg' => 7,
                'base' => 11,
                'sm' => 12,
                'xs' => 16,
                'body-small' => 13,
                'label-large' => 14,
                'label-small' => 16,
                default => 11,
            },
        };
    }

    private static function number(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        return is_int($value) ? (float) $value : 0.0;
    }

    /** @param array<string, mixed> $props */
    private static function textColor(array $props, Theme $theme): int
    {
        if (is_int($props['color'] ?? null)) {
            return $props['color'];
        }

        $parentValue = $props['__parentVariants'] ?? null;
        $parent = self::stringKeyedArray($parentValue);
        $parentComponent = $parent['__pamParentComponent'] ?? null;
        $parentVariant = $parent['variant'] ?? null;

        if (in_array($parentComponent, ['PBtn', 'PFab'], true)) {
            return match ($parentVariant) {
                MaterialVariant::Tonal->value, 'tonal' =>
                    $theme->color(ColorToken::SecondaryForeground),
                MaterialVariant::Outlined->value, 'outlined', 'outline',
                MaterialVariant::Text->value, 'text',
                MaterialVariant::Plain->value, 'plain' =>
                    $theme->color(ColorToken::Primary),
                default => $theme->color(ColorToken::PrimaryForeground),
            };
        }

        if (in_array($parentComponent, ['PChip', 'PBadge'], true)) {
            return self::semanticColor(
                $parent,
                $theme,
                true,
            ) ?? $theme->color(ColorToken::SecondaryForeground);
        }

        return $theme->color(ColorToken::OnSurface);
    }

    /** @param array<string, mixed> $props */
    private static function semanticColor(
        array $props,
        Theme $theme,
        bool $foreground,
    ): ?int {
        $token = match ($props['action'] ?? $props['color'] ?? null) {
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
