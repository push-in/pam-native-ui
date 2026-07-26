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
        $opacity = $disabled ? MaterialTokens::STATE_OPACITY[6] : 1.0;
        $height = MaterialTokens::controlHeight($density);

        if (self::textPart($part)) {
            [$size, $line, $weight, $tracking] =
                MaterialTokens::TYPE_SCALE[self::typeScale($part, $props)];

            return new Style(
                textColor: self::textColor($props, $theme),
                fontSize: $size,
                lineHeight: $line,
                fontWeight: (int) $weight,
                letterSpacing: $tracking / $size,
                opacity: $opacity,
                textAlign: TextAlignment::Start,
            );
        }

        if (in_array($part, ['PBtn', 'PIconBtn', 'PFab'], true)) {
            $icon = $part !== 'PBtn' || ($props['icon'] ?? false) === true;
            $height = $part === 'PFab' ? match ($props['size'] ?? null) {
                'small', 1 => 40.0,
                'large', 3 => 96.0,
                default => 56.0,
            } : $height;

            return new Style(
                minWidth: $icon ? $height : 88.0,
                width: $icon ? $height : null,
                minHeight: $height,
                height: $icon ? $height : null,
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
                borderRadius: $part === 'PFab'
                    ? MaterialTokens::radius(MaterialShape::Large)
                    : MaterialTokens::radius(MaterialShape::Full),
                elevation: $variant === MaterialVariant::Elevated ? 1.0 : 0.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, ['PCard', 'PSheet', 'PEmptyState', 'PPicker'], true)) {
            return new Style(
                padding: match ($density) {
                    MaterialDensity::Default => 16.0,
                    MaterialDensity::Comfortable => 12.0,
                    MaterialDensity::Compact => 8.0,
                },
                gap: 12.0,
                backgroundColor: $variant === MaterialVariant::Tonal
                    ? $theme->color(ColorToken::Secondary)
                    : $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Medium),
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
                minHeight: $part === 'PTextarea' ? 112.0 : $height + 8.0,
                paddingHorizontal: 16.0,
                paddingVertical: 8.0,
                gap: 8.0,
                backgroundColor: $variant === MaterialVariant::Outlined
                    ? $theme->color(ColorToken::Background)
                    : $theme->color(ColorToken::SurfaceSunken),
                textColor: $theme->color(ColorToken::OnSurface),
                placeholderColor: $theme->color(ColorToken::MutedForeground),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: 1.0,
                borderRadius: MaterialTokens::radius(MaterialShape::ExtraSmall),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: $part === 'PTextarea' ? Align::Start : Align::Center,
            );
        }

        if (in_array($part, ['PChip', 'PBadge'], true)) {
            $semanticBackground = self::semanticColor($props, $theme, false);

            return new Style(
                minHeight: $part === 'PChip' ? 32.0 : 20.0,
                paddingHorizontal: $part === 'PChip' ? 12.0 : 8.0,
                gap: 6.0,
                backgroundColor: $variant === MaterialVariant::Outlined
                    ? $transparent
                    : ($semanticBackground
                        ?? $theme->color(ColorToken::Secondary)),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Small),
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
                alignSelf: Align::Start,
                justifyContent: Justify::Center,
            );
        }

        if (in_array($part, [
            'PAppBar', 'PToolbar', 'PBottomNavigation', 'PSystemBar', 'PFooter',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: $part === 'PSystemBar' ? 24.0 : 64.0,
                paddingHorizontal: 16.0,
                gap: 8.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                elevation: self::number($props['elevation'] ?? null),
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
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
                paddingHorizontal: 16.0,
                paddingVertical: 12.0,
                gap: 12.0,
                backgroundColor: $surfaceSemantic
                    ? $theme->color(ColorToken::SurfaceElevated)
                    : ($semanticColor ?? $theme->color(ColorToken::SurfaceElevated)),
                borderColor: $semanticColor ?? $theme->color(ColorToken::Border),
                borderWidth: $part === 'PAlert' || $variant === MaterialVariant::Outlined
                    ? 1.0
                    : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Medium),
                elevation: $part === 'PSnackbar' ? 3.0 : 0.0,
                opacity: $opacity,
                flexDirection: FlexDirection::Row,
                alignItems: Align::Center,
            );
        }

        if (in_array($part, [
            'PDialog', 'PMenu', 'PBottomSheet', 'POverlay', 'PTooltip',
            'PNavigationDrawer', 'PSnackbarQueue', 'PSpeedDial',
        ], true)) {
            return new Style(
                padding: $part === 'PTooltip' ? 8.0 : 16.0,
                gap: 8.0,
                backgroundColor: $part === 'POverlay'
                    ? $theme->color(ColorToken::Overlay)
                    : $theme->color(ColorToken::SurfaceElevated),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: in_array($part, ['PMenu', 'PNavigationDrawer'], true)
                    ? 1.0
                    : 0.0,
                borderRadius: match ($part) {
                    'PBottomSheet' => MaterialTokens::radius(MaterialShape::ExtraLarge),
                    'PTooltip' => MaterialTokens::radius(MaterialShape::ExtraSmall),
                    default => MaterialTokens::radius(MaterialShape::Medium),
                },
                elevation: $part === 'POverlay' ? 0.0 : 3.0,
                opacity: $part === 'POverlay' ? 0.72 : $opacity,
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
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Medium),
                overflow: Overflow::Hidden,
                opacity: $opacity,
            );
        }

        if (in_array($part, [
            'PListItem', 'PTreeviewItem', 'PFileUploadItem',
            'PDataTableRow', 'PItem', 'PSlideGroupItem',
        ], true)) {
            return new Style(
                widthPercent: 100.0,
                minHeight: $density === MaterialDensity::Compact ? 40.0 : 48.0,
                paddingHorizontal: 16.0,
                paddingVertical: 8.0,
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
            return new Style(
                minWidth: in_array($part, ['PCheckbox', 'PCheckboxBtn', 'PRadio'], true)
                    ? 48.0
                    : null,
                minHeight: 48.0,
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
                width: $part === 'PProgressCircular' ? 40.0 : null,
                height: 40.0,
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
                minHeight: 48.0,
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
                minHeight: 48.0,
                minWidth: 48.0,
                paddingHorizontal: 16.0,
                gap: 8.0,
                textColor: ($props['selected'] ?? false)
                    ? $theme->color(ColorToken::Primary)
                    : $theme->color(ColorToken::MutedForeground),
                backgroundColor: ($props['selected'] ?? false)
                    ? $theme->color(ColorToken::Secondary)
                    : $transparent,
                borderRadius: MaterialTokens::radius(MaterialShape::Full),
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
            return new Style(
                widthPercent: 100.0,
                padding: 16.0,
                gap: 12.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: $variant === MaterialVariant::Outlined ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Medium),
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
            return new Style(
                widthPercent: 100.0,
                minHeight: 48.0,
                padding: 12.0,
                gap: 8.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderColor: $theme->color(ColorToken::Border),
                borderBottomWidth: in_array(
                    $part,
                    ['PExpansionPanelTitle', 'PCalendarInterval'],
                    true,
                ) ? 1.0 : 0.0,
                borderRadius: MaterialTokens::radius(MaterialShape::Small),
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
        return match ($part) {
            'PCardTitle', 'PAppBarTitle', 'PToolbarTitle' => 8,
            'PAlertTitle', 'PBannerText', 'PListItemTitle' => 9,
            'PCardSubtitle', 'PListItemSubtitle', 'PLabel' => 12,
            'PCounter', 'PKbd', 'PCode' => 13,
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
                'xs' => 15,
                'body-small' => 13,
                'label-large' => 14,
                'label-small' => 15,
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
