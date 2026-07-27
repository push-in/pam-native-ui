<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Rendering\MaterialStyleResolver;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\Style;

require __DIR__.'/bootstrap.php';

$tags = MaterialComponentMap::TAGS;
$ids = MaterialComponentMap::IDS;
$modules = MaterialComponentMap::MODULES;

$sequentialIds = array_values($ids);
sort($sequentialIds, SORT_NUMERIC);
if ($sequentialIds !== range(1, 163)) {
    throw new RuntimeException('Material component IDs must be sequential from 1 through 163.');
}

$moduleTags = [];
foreach ($modules as $module => $components) {
    foreach ($components as $tag) {
        $moduleTags[$tag] = true;
    }
}
if (array_keys($moduleTags) !== array_keys($tags)) {
    throw new RuntimeException('Material modules and public tags must match exactly.');
}

$themes = [
    ['mode' => ThemeMode::Light, 'theme' => Themes::light()],
    ['mode' => ThemeMode::Dark, 'theme' => Themes::dark()],
];
$variants = ['elevated', 'flat', 'tonal', 'outlined', 'text', 'plain'];
$densities = ['default', 'comfortable', 'compact'];
$states = [
    [],
    ['disabled' => true],
    ['selected' => true],
    ['loading' => true],
];

$styleCases = 0;
$renderCases = 0;
foreach ($tags as $tag => $class) {
    if (!str_starts_with($tag, 'p-') || preg_match('/^p-[a-z][a-z0-9-]*$/D', $tag) !== 1) {
        throw new RuntimeException("Invalid Material tag {$tag}.");
    }
    $part = $class::componentName();
    if (($ids[$part] ?? null) === null) {
        throw new RuntimeException("Missing Material ID for {$part}.");
    }

    foreach ($themes as ['mode' => $mode, 'theme' => $theme]) {
        PamUI::mode($mode);
        foreach ($variants as $variant) {
            foreach ($densities as $density) {
                foreach ($states as $state) {
                    $props = [
                        '__materialComponent' => $part,
                        'variant' => $variant,
                        'density' => $density,
                        'text' => $part,
                        ...$state,
                    ];
                    $style = MaterialStyleResolver::resolve($props, $theme);
                    if (!$style instanceof Style) {
                        throw new RuntimeException(
                            "Material style missing for {$part} ({$mode->name}/{$variant}/{$density}).",
                        );
                    }
                    $styleCases++;
                }
            }
        }

        $class::make([
            'text' => $part,
            'value' => 1,
            'accessibilityLabel' => $part,
        ])->toElement();
        $renderCases++;
    }
}

/**
 * @param array<string, mixed> $props
 * @param array<string, float|int|null> $expected
 */
$assertGeometry = static function (
    string $component,
    array $props,
    array $expected,
) use ($themes): void {
    $style = MaterialStyleResolver::resolve(
        ['__materialComponent' => $component, ...$props],
        $themes[0]['theme'],
    );

    foreach ($expected as $property => $value) {
        $actual = $style->{$property};
        if ($actual !== $value) {
            throw new RuntimeException(
                sprintf(
                    '%s.%s expected %s, received %s.',
                    $component,
                    $property,
                    var_export($value, true),
                    var_export($actual, true),
                ),
            );
        }
    }
};

$assertGeometry('PBtn', [], [
    'minWidth' => 64.0,
    'minHeight' => 36.0,
]);
$assertGeometry('PBtn', ['density' => 'comfortable'], [
    'minHeight' => 32.0,
]);
$assertGeometry('PBtn', ['density' => 'compact'], [
    'minHeight' => 28.0,
]);
$assertGeometry('PBtn', ['disabled' => true], [
    'opacity' => 0.26,
]);
$assertGeometry('PIconBtn', [], [
    'width' => 36.0,
    'height' => 36.0,
]);
$assertGeometry('PIconBtn', ['size' => 'x-small'], [
    'width' => 20.0,
    'height' => 20.0,
]);
$assertGeometry('PIconBtn', ['size' => 'small'], [
    'width' => 28.0,
    'height' => 28.0,
]);
$assertGeometry('PIconBtn', ['size' => 'large'], [
    'width' => 44.0,
    'height' => 44.0,
]);
$assertGeometry('PIconBtn', ['size' => 'x-large'], [
    'width' => 52.0,
    'height' => 52.0,
]);
$assertGeometry('PIconBtn', ['density' => 'comfortable'], [
    'width' => 32.0,
    'height' => 32.0,
]);
$assertGeometry('PIconBtn', ['density' => 'compact'], [
    'width' => 28.0,
    'height' => 28.0,
]);
$assertGeometry('PCard', [], [
    'padding' => 0.0,
    'borderRadius' => 4.0,
]);
$assertGeometry('PChip', [], [
    'minHeight' => 32.0,
    'paddingHorizontal' => 12.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PChip', ['density' => 'comfortable'], [
    'minHeight' => 28.0,
    'paddingHorizontal' => 10.0,
]);
$assertGeometry('PChip', ['density' => 'compact'], [
    'minHeight' => 24.0,
    'paddingHorizontal' => 8.0,
]);
$assertGeometry('PList', [], [
    'paddingVertical' => 8.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PListItem', [], [
    'minHeight' => 48.0,
]);
$assertGeometry('PListItem', ['density' => 'comfortable'], [
    'minHeight' => 44.0,
]);
$assertGeometry('PListItem', ['density' => 'compact'], [
    'minHeight' => 40.0,
]);
$assertGeometry('PListItem', ['lines' => 2], [
    'minHeight' => 64.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 12.0,
]);
$assertGeometry('PListItem', ['lines' => 3], [
    'minHeight' => 88.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 16.0,
]);
$assertGeometry('PTab', [], [
    'minWidth' => 90.0,
    'minHeight' => 48.0,
]);
$assertGeometry('PTab', ['density' => 'comfortable'], [
    'minHeight' => 44.0,
]);
$assertGeometry('PTab', ['density' => 'compact'], [
    'minHeight' => 36.0,
]);
$assertGeometry('PTextField', [], [
    'height' => 56.0,
    'minHeight' => 56.0,
    'paddingTop' => 8.0,
    'paddingBottom' => 4.0,
]);
$assertGeometry('PTextField', ['density' => 'comfortable'], [
    'height' => 48.0,
    'minHeight' => 48.0,
]);
$assertGeometry('PTextField', ['density' => 'compact'], [
    'height' => 40.0,
    'minHeight' => 40.0,
    'paddingTop' => 0.0,
    'paddingBottom' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'outlined'], [
    'borderWidth' => 1.0,
    'borderBottomWidth' => null,
    'elevation' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'underlined'], [
    'paddingHorizontal' => 0.0,
    'borderWidth' => 0.0,
    'borderBottomWidth' => 1.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'solo'], [
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'elevation' => 1.0,
]);
$assertGeometry('PTextField', ['variant' => 'solo-inverted'], [
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'elevation' => 1.0,
]);
$assertGeometry('PTextField', ['variant' => 'solo-filled'], [
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'elevation' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'plain'], [
    'paddingHorizontal' => 0.0,
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'borderRadius' => 0.0,
]);
$assertGeometry('PDialog', [], [
    'margin' => 24.0,
    'borderRadius' => 28.0,
    'elevation' => 6.0,
]);
$assertGeometry('PProgressCircular', [], [
    'width' => 32.0,
    'height' => 32.0,
    'animationDurationMs' => 1400,
]);
$assertGeometry('PProgressCircular', ['size' => 'x-small'], [
    'width' => 16.0,
    'height' => 16.0,
]);
$assertGeometry('PProgressCircular', ['size' => 'small'], [
    'width' => 24.0,
    'height' => 24.0,
]);
$assertGeometry('PProgressCircular', ['size' => 'large'], [
    'width' => 48.0,
    'height' => 48.0,
]);
$assertGeometry('PProgressCircular', ['size' => 'x-large'], [
    'width' => 64.0,
    'height' => 64.0,
]);
$assertGeometry('PProgressLinear', [], [
    'height' => 4.0,
    'animationDurationMs' => 2200,
]);
$assertGeometry('PSkeletonLoader', [], [
    'borderRadius' => 4.0,
    'animationDurationMs' => 1500,
]);
$assertGeometry('PAppBar', [], [
    'minHeight' => 64.0,
    'paddingHorizontal' => 4.0,
]);
$assertGeometry('PAppBar', ['density' => 'comfortable'], [
    'minHeight' => 56.0,
]);
$assertGeometry('PAppBar', ['density' => 'compact'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PToolbar', [], [
    'minHeight' => 64.0,
]);
$assertGeometry('PToolbar', ['density' => 'comfortable'], [
    'minHeight' => 56.0,
]);
$assertGeometry('PToolbar', ['density' => 'compact'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PToolbarTitle', [], [
    'fontSize' => 20.0,
    'lineHeight' => 28.0,
]);
$assertGeometry('PAppBarTitle', [], [
    'fontSize' => 20.0,
    'lineHeight' => 28.0,
]);
$assertGeometry('PBottomNavigation', [], [
    'minHeight' => 56.0,
    'paddingHorizontal' => 0.0,
    'elevation' => 2.0,
]);
$assertGeometry('PBottomNavigation', ['density' => 'comfortable'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PBottomNavigation', ['density' => 'compact'], [
    'minHeight' => 40.0,
]);
$assertGeometry('PSystemBar', [], [
    'minHeight' => 24.0,
    'paddingHorizontal' => 8.0,
]);
$assertGeometry('PFooter', [], [
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 8.0,
]);
$assertGeometry('PAlert', [], [
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 16.0,
    'gap' => 16.0,
    'borderRadius' => 4.0,
    'elevation' => 1.0,
]);
$assertGeometry('PAlertTitle', [], [
    'fontSize' => 24.0,
    'lineHeight' => 28.0,
]);
$assertGeometry('PAlert', ['border' => 'start'], [
    'paddingLeft' => 24.0,
    'borderLeftWidth' => 8.0,
]);
$assertGeometry('PAlert', ['border' => 'start', 'rtl' => true], [
    'paddingRight' => 24.0,
    'borderRightWidth' => 8.0,
]);
$assertGeometry('PAlert', ['border' => 'top'], [
    'paddingTop' => 24.0,
    'borderTopWidth' => 8.0,
]);
$assertGeometry('PBanner', [], [
    'paddingLeft' => 16.0,
    'paddingRight' => 8.0,
    'paddingTop' => 16.0,
    'paddingBottom' => 16.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PBannerActions', [], [
    'gap' => 8.0,
    'marginTop' => 20.0,
]);
$assertGeometry('PBadge', [], [
    'minWidth' => 20.0,
    'minHeight' => 20.0,
    'borderRadius' => 9999.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBadge', ['bordered' => true], [
    'borderWidth' => 2.0,
]);
$assertGeometry('PBottomSheet', [], [
    'widthPercent' => 100.0,
    'borderRadius' => 28.0,
    'elevation' => 6.0,
]);
$assertGeometry('PCardTitle', [], [
    'fontSize' => 20.0,
    'lineHeight' => 28.0,
]);
$assertGeometry('PCardTitle', ['density' => 'compact'], [
    'lineHeight' => 24.8,
]);
$assertGeometry('PCardSubtitle', [], [
    'paddingHorizontal' => 16.0,
    'fontSize' => 14.0,
    'lineHeight' => 20.0,
]);
$assertGeometry('PCardSubtitle', ['density' => 'comfortable'], [
    'lineHeight' => 18.0,
]);
$assertGeometry('PCardSubtitle', ['density' => 'compact'], [
    'lineHeight' => 16.0,
]);
$assertGeometry('PCardText', [], [
    'paddingHorizontal' => 16.0,
    'paddingTop' => 16.0,
    'paddingBottom' => 16.0,
    'fontSize' => 14.0,
    'lineHeight' => 20.0,
]);
$assertGeometry('PCardText', ['density' => 'comfortable'], [
    'lineHeight' => 19.2,
]);
$assertGeometry('PCardText', ['density' => 'compact'], [
    'lineHeight' => 18.4,
]);
$assertGeometry('PCardItem', [], [
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 10.0,
]);
$assertGeometry('PCardActions', [], [
    'minHeight' => 52.0,
    'padding' => 8.0,
    'gap' => 8.0,
]);
$assertGeometry('PExpansionPanelTitle', [], [
    'minHeight' => 48.0,
    'paddingHorizontal' => 24.0,
]);
$assertGeometry('PExpansionPanel', ['active' => true], [
    'marginVertical' => 16.0,
]);
$assertGeometry('PExpansionPanel', ['disabled' => true], [
    'opacity' => 0.26,
]);
$assertGeometry('PExpansionPanelTitle', ['active' => true], [
    'minHeight' => 64.0,
]);
$assertGeometry('PExpansionPanelText', [], [
    'paddingHorizontal' => 24.0,
    'paddingTop' => 8.0,
    'paddingBottom' => 16.0,
]);
$assertGeometry('PListItemTitle', [], [
    'fontSize' => 16.0,
    'lineHeight' => 24.0,
]);
$assertGeometry('PNavigationDrawer', [], [
    'borderRadius' => 0.0,
    'elevation' => 4.0,
]);
$assertGeometry('PSelectionControl', [], [
    'minHeight' => 40.0,
]);
$assertGeometry('PSelectionControl', ['density' => 'comfortable'], [
    'minHeight' => 36.0,
]);
$assertGeometry('PSelectionControl', ['density' => 'compact'], [
    'minHeight' => 28.0,
]);
$assertGeometry('PSnackbar', [], [
    'widthPercent' => 100.0,
    'minHeight' => 48.0,
    'maxWidth' => 672.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 12.0,
    'elevation' => 6.0,
]);
$assertGeometry('PStepper', [], [
    'borderRadius' => 4.0,
    'elevation' => 1.0,
]);
$assertGeometry('PStepperHeader', [], [
    'elevation' => 1.0,
]);
$assertGeometry('PStepperItem', [], [
    'padding' => 24.0,
    'opacity' => 0.6,
]);
$assertGeometry('PStepperItem', ['active' => true], [
    'opacity' => 1.0,
]);
$assertGeometry('PStepperWindow', [], [
    'margin' => 24.0,
]);
$assertGeometry('PStepperWindowItem', [], [
    'paddingTop' => 24.0,
]);
$assertGeometry('PStepperActions', [], [
    'padding' => 16.0,
]);
$assertGeometry('PStepperVerticalActions', [], [
    'paddingHorizontal' => 24.0,
    'paddingBottom' => 16.0,
]);
$assertGeometry('PTooltip', [], [
    'widthPercent' => 100.0,
    'backgroundColor' => 0x00000000,
    'elevation' => 0.0,
]);
$assertGeometry('POverlay', [], [
    'widthPercent' => 100.0,
    'heightPercent' => 100.0,
    'opacity' => 1.0,
]);
$assertGeometry('PMenu', [], [
    'borderRadius' => 4.0,
    'elevation' => 3.0,
]);
$assertGeometry('PAvatar', ['size' => 'x-small'], [
    'width' => 24.0,
    'height' => 24.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PAvatar', [], [
    'width' => 40.0,
    'height' => 40.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PAvatar', ['size' => 'x-large'], [
    'width' => 56.0,
    'height' => 56.0,
]);
$assertGeometry('PIcon', ['size' => 'x-small'], [
    'width' => 16.0,
    'height' => 16.0,
]);
$assertGeometry('PIcon', ['size' => 'x-large'], [
    'width' => 32.0,
    'height' => 32.0,
]);
$assertGeometry('PFab', ['size' => 'x-small'], [
    'width' => 40.0,
    'height' => 40.0,
    'borderRadius' => 9999.0,
    'elevation' => 3.0,
]);
$assertGeometry('PFab', ['size' => 'large'], [
    'width' => 72.0,
    'height' => 72.0,
]);
$assertGeometry('PFab', ['size' => 'x-large'], [
    'width' => 96.0,
    'height' => 96.0,
]);
$assertGeometry('PBadge', [], [
    'height' => 20.0,
    'minWidth' => 20.0,
    'borderRadius' => 9999.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBadge', ['dot' => true], [
    'width' => 9.0,
    'height' => 9.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBreadcrumbs', [], [
    'minHeight' => 56.0,
    'paddingHorizontal' => 12.0,
    'paddingVertical' => 16.0,
]);
$assertGeometry('PBreadcrumbsItem', [], [
    'minHeight' => 24.0,
    'paddingHorizontal' => 4.0,
]);
$assertGeometry('PBreadcrumbsDivider', [], [
    'paddingHorizontal' => 8.0,
    'fontSize' => 16.0,
    'lineHeight' => 24.0,
]);
$assertGeometry('PPagination', [], [
    'minHeight' => 36.0,
    'gap' => 9.6,
]);
$assertGeometry('PPagination', ['density' => 'compact'], [
    'minHeight' => 28.0,
]);
$assertGeometry('PCarousel', [], [
    'height' => 500.0,
    'minHeight' => 500.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PCarousel', ['height' => 320], [
    'height' => 320.0,
    'minHeight' => 320.0,
]);
$assertGeometry('PCarouselItem', [], [
    'widthPercent' => 100.0,
    'heightPercent' => 100.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PRating', [], [
    'height' => 40.0,
    'minHeight' => 40.0,
    'animationDurationMs' => 150,
]);
$assertGeometry('PTimeline', [], [
    'widthPercent' => 100.0,
    'gap' => 0.0,
]);
$assertGeometry('PTimelineItem', [], [
    'minHeight' => 86.0,
    'padding' => 24.0,
    'gap' => 24.0,
    'borderWidth' => 2.0,
]);
$assertGeometry('PWindow', [], [
    'widthPercent' => 100.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PPicker', [], [
    'borderRadius' => 4.0,
    'elevation' => 0.0,
]);
$assertGeometry('PDatePicker', [], [
    'width' => 328.0,
    'minWidth' => 328.0,
    'borderRadius' => 4.0,
]);
$assertGeometry('PDatePickerHeader', [], [
    'height' => 70.0,
    'paddingHorizontal' => 12.0,
    'paddingVertical' => 4.0,
]);
$assertGeometry('PDatePickerMonth', [], [
    'minHeight' => 288.0,
    'paddingHorizontal' => 12.0,
    'paddingBottom' => 8.0,
]);
$assertGeometry('PDatePickerMonths', [], [
    'height' => 288.0,
    'gap' => 24.0,
]);
$assertGeometry('PDatePickerYears', [], [
    'height' => 288.0,
    'paddingHorizontal' => 32.0,
]);
$assertGeometry('PTimePicker', [], [
    'width' => 328.0,
    'padding' => 24.0,
    'borderRadius' => 4.0,
]);
$assertGeometry('PTimePickerControls', [], [
    'height' => 80.0,
    'fontSize' => 56.0,
    'lineHeight' => 74.0,
]);
$assertGeometry('PTimePickerClock', [], [
    'width' => 290.0,
    'height' => 290.0,
    'padding' => 10.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PColorPicker', [], [
    'width' => 300.0,
    'borderRadius' => 4.0,
    'elevation' => 1.0,
]);
$assertGeometry('PColorPickerPreview', [], [
    'minHeight' => 46.0,
    'padding' => 16.0,
    'gap' => 8.0,
]);
$assertGeometry('PColorPickerEdit', [], [
    'minHeight' => 64.0,
    'marginTop' => 24.0,
    'marginBottom' => 8.0,
]);
$assertGeometry('PColorPickerSwatches', [], [
    'paddingBottom' => 10.0,
    'borderRadius' => 8.0,
]);
$assertGeometry('PSlider', [], [
    'widthPercent' => 100.0,
    'minHeight' => 32.0,
    'paddingHorizontal' => 8.0,
    'borderRadius' => 6.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PRangeSlider', ['vertical' => true], [
    'width' => 32.0,
    'minHeight' => 300.0,
    'marginTop' => 12.0,
    'marginBottom' => 12.0,
]);
$assertGeometry('PSelectionControl', [], [
    'minHeight' => 40.0,
]);
$assertGeometry('PSelectionControl', ['density' => 'comfortable'], [
    'minHeight' => 36.0,
]);
$assertGeometry('PSelectionControl', ['density' => 'compact'], [
    'minHeight' => 28.0,
]);
$assertGeometry('PSwitch', [], [
    'minHeight' => 40.0,
    'minWidth' => 40.0,
    'gap' => 10.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PCheckbox', ['disabled' => true], [
    'minHeight' => 40.0,
    'opacity' => 0.38,
]);
$assertGeometry('PRadioGroup', ['inline' => true], [
    'widthPercent' => 100.0,
    'gap' => 4.0,
]);
$assertGeometry('PTable', [], [
    'minHeight' => 52.0,
    'paddingHorizontal' => 0.0,
    'borderWidth' => 0.0,
    'fontSize' => 14.0,
    'lineHeight' => 21.0,
    'animationDurationMs' => 280,
]);
$assertGeometry('PDataTable', ['density' => 'comfortable'], [
    'minHeight' => 44.0,
]);
$assertGeometry('PDataTableVirtual', ['density' => 'compact'], [
    'minHeight' => 36.0,
]);
$assertGeometry('PDataIterator', [], [
    'widthPercent' => 100.0,
    'gap' => 16.0,
    'paddingVertical' => 8.0,
]);
$assertGeometry('PVirtualScroll', [], [
    'widthPercent' => 100.0,
    'minHeight' => 52.0,
]);
$assertGeometry('PTreeview', [], [
    'widthPercent' => 100.0,
    'paddingVertical' => 8.0,
]);
$assertGeometry('PTreeviewItem', ['level' => 2], [
    'minHeight' => 48.0,
    'paddingLeft' => 64.0,
    'paddingRight' => 16.0,
]);
$assertGeometry('PEmptyState', [], [
    'minHeight' => 240.0,
    'padding' => 16.0,
    'gap' => 8.0,
]);
$assertGeometry('POtpInput', [], [
    'width' => 320.0,
    'minHeight' => 64.0,
    'paddingHorizontal' => 8.0,
    'paddingVertical' => 8.0,
    'gap' => 8.0,
    'fontSize' => 20.0,
]);
$assertGeometry('POtpInput', ['divided' => true], [
    'width' => 360.0,
]);
$assertGeometry('PFileUpload', [], [
    'minHeight' => 184.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 64.0,
    'borderWidth' => 2.0,
    'borderRadius' => 4.0,
]);
$assertGeometry('PFileUpload', ['inset' => true], [
    'minHeight' => 96.0,
    'paddingVertical' => 16.0,
]);
$assertGeometry('PFileUploadItem', [], [
    'minHeight' => 64.0,
    'marginTop' => 16.0,
    'marginBottom' => 16.0,
    'padding' => 16.0,
]);
$assertGeometry('PAutocomplete', [], [
    'height' => 56.0,
    'minHeight' => 56.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PAutocomplete', ['chips' => true], [
    'height' => 64.0,
]);
$assertGeometry('PSelect', ['density' => 'compact'], [
    'height' => 40.0,
]);
$assertGeometry('PImg', ['cardMedia' => true], [
    'minHeight' => 200.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PParallax', [], [
    'height' => 500.0,
    'minHeight' => 500.0,
]);
$assertGeometry('PSparkline', [], [
    'height' => 100.0,
    'minHeight' => 100.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PCalendar', [], [
    'minHeight' => 344.0,
    'borderWidth' => 1.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PCalendarHeader', [], [
    'height' => 56.0,
    'minHeight' => 56.0,
    'paddingHorizontal' => 4.0,
    'borderWidth' => 1.0,
]);
$assertGeometry('PCalendarDay', [], [
    'minHeight' => 56.0,
    'paddingTop' => 4.0,
    'fontSize' => 12.0,
    'lineHeight' => 20.0,
]);
$assertGeometry('PCalendarInterval', [], [
    'minWidth' => 50.0,
    'minHeight' => 20.0,
    'paddingRight' => 8.0,
    'fontSize' => 10.0,
    'lineHeight' => 20.0,
]);

fwrite(
    STDOUT,
    json_encode(
        [
            'modules' => count($modules),
            'components' => count($tags),
            'styleCases' => $styleCases,
            'renderCases' => $renderCases,
            'themes' => count($themes),
            'variants' => count($variants),
            'densities' => count($densities),
            'states' => count($states),
        ],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
    )."\n",
);
