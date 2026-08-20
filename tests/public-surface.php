<?php

declare(strict_types=1);

use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\MobileUiPluginProvider;
use Pam\MobileUi\Enum\MetricTrend;
use Pam\MobileUi\Enum\StatusTone;
use Pam\MobileUi\Product\MetricCard;
use Pam\MobileUi\Product\StatusBanner;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\AccessibilityRole;
use Pam\Native\PropKey;
use Pam\Native\TemplateRegistry;

$vendorAutoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    $localRoots = [
        'Pam\\MobileUi\\' => dirname(__DIR__).'/src/',
        'Pam\\Native\\' => dirname(__DIR__, 2).'/pam-native/packages/native/src/',
    ];
    spl_autoload_register(static function (string $class) use ($localRoots): void {
        foreach ($localRoots as $prefix => $root) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $path = $root.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    });
    require_once dirname(__DIR__).'/src/Generated/MaterialComponentFacades.php';
}

TemplateRegistry::reset();
(new MobileUiPluginProvider())->register();

if (TemplateRegistry::factory('StatusBanner') === null) {
    throw new RuntimeException('StatusBanner is not registered for templates.');
}
if (TemplateRegistry::factory('MetricCard') === null) {
    throw new RuntimeException('MetricCard is not registered for templates.');
}

$metric = MetricCard::make('Revenue', 'R$ 128,450')
    ->delta('+12.4%', MetricTrend::Positive)
    ->supporting('Versus last month')
    ->toElement();
if (($metric->properties()[PropKey::AccessibilityRole->value] ?? null)
    !== AccessibilityRole::Summary->value
) {
    throw new RuntimeException('Metric cards must expose a summary role.');
}
$metricLabel = $metric->properties()[PropKey::AccessibilityLabel->value] ?? null;
if (!is_string($metricLabel) || !str_contains($metricLabel, 'Positive trend: +12.4%')) {
    throw new RuntimeException('Metric cards must announce semantic trend context.');
}

$errorBanner = StatusBanner::make('Payment failed', StatusTone::Error)
    ->message('Review the card details.')
    ->toElement();
if (($errorBanner->properties()[PropKey::AccessibilityRole->value] ?? null)
    !== AccessibilityRole::Alert->value
) {
    throw new RuntimeException('Error banners must expose the alert role.');
}
if (($errorBanner->properties()[PropKey::AccessibilityLiveRegion->value] ?? null)
    !== AccessibilityLiveRegion::Assertive->value
) {
    throw new RuntimeException('Error banners must be announced assertively.');
}

$progressBanner = StatusBanner::make('Publishing', StatusTone::Progress)->toElement();
if (($progressBanner->properties()[PropKey::AccessibilityBusy->value] ?? null) !== true) {
    throw new RuntimeException('Progress banners must expose their busy state.');
}

$iconClass = MaterialComponentMap::TAGS['p-icon'];
$decorativeIcon = $iconClass::make(['icon' => 'check'])->toElement();
if (array_key_exists(PropKey::AccessibilityRole->value, $decorativeIcon->properties())) {
    throw new RuntimeException('Unlabeled icons must remain outside semantic image output.');
}
$labeledIcon = $iconClass::make([
    'icon' => 'check',
    'accessibilityLabel' => 'Completed',
])->toElement();
if (($labeledIcon->properties()[PropKey::AccessibilityRole->value] ?? null)
    !== AccessibilityRole::Image->value
) {
    throw new RuntimeException('Labeled icons must expose the native image role.');
}

foreach ([
    'p-alert' => AccessibilityLiveRegion::Assertive,
    'p-banner' => AccessibilityLiveRegion::Polite,
    'p-snackbar' => AccessibilityLiveRegion::Polite,
] as $tag => $expectedRegion) {
    $class = MaterialComponentMap::TAGS[$tag];
    $alert = $class::make([
        'text' => 'Updated',
        'accessibilityLabel' => 'Updated',
    ])->toElement();
    if (($alert->properties()[PropKey::AccessibilityLiveRegion->value] ?? null)
        !== $expectedRegion->value
    ) {
        throw new RuntimeException("{$tag} must expose its default live region.");
    }
}

$ratingClass = MaterialComponentMap::TAGS['p-rating'];
$rating = $ratingClass::make(['value' => 3])->toElement()->properties();
if (($rating[PropKey::AccessibilityValueMin->value] ?? null) !== 0.0
    || ($rating[PropKey::AccessibilityValueMax->value] ?? null) !== 5.0
    || ($rating[PropKey::AccessibilityValueNow->value] ?? null) !== 3.0
) {
    throw new RuntimeException('Rating accessibility range must match its five-star native range.');
}

$rangeClass = MaterialComponentMap::TAGS['p-range-slider'];
$range = $rangeClass::make(['value' => [20, 80]])->toElement()->properties();
if (($range[PropKey::AccessibilityValueMin->value] ?? null) !== 0.0
    || ($range[PropKey::AccessibilityValueMax->value] ?? null) !== 100.0
    || ($range[PropKey::AccessibilityValueNow->value] ?? null) !== 80.0
    || ($range[PropKey::AccessibilityValueText->value] ?? null) !== '20 to 80'
) {
    throw new RuntimeException('Range slider must announce both native endpoints.');
}

$progressClass = MaterialComponentMap::TAGS['p-progress-linear'];
$progress = $progressClass::make(['indeterminate' => true])->toElement()->properties();
if (($progress[PropKey::AccessibilityBusy->value] ?? null) !== true
    || ($progress[PropKey::AccessibilityValueText->value] ?? null) !== 'In progress'
) {
    throw new RuntimeException('Indeterminate progress must expose busy text semantics.');
}

foreach (MaterialComponentMap::TAGS as $tag => $class) {
    if (TemplateRegistry::factory($tag) === null) {
        throw new RuntimeException("Public Material tag {$tag} is not registered.");
    }
    if (!class_exists($class)) {
        throw new RuntimeException("Public Material facade {$class} is not autoloadable.");
    }
}

foreach ([
    'Text',
    'View',
    'Box',
    'Grid',
    'GridItem',
    'HStack',
    'VStack',
    'FileTree',
] as $removedTag) {
    if (TemplateRegistry::factory($removedTag) !== null) {
        throw new RuntimeException("Removed legacy tag {$removedTag} is still registered.");
    }
    if (class_exists("Pam\\MobileUi\\Component\\{$removedTag}")) {
        throw new RuntimeException("Removed legacy facade {$removedTag} is still autoloadable.");
    }
}

$removedMaterialTags = [
    'p-action-sheet',
    'p-alert-dialog',
    'p-alert-title',
    'p-app',
    'p-app-bar-title',
    'p-banner-text',
    'p-bottom-navigation',
    'p-breadcrumbs',
    'p-calendar-header',
    'p-calendar-interval',
    'p-card-subtitle',
    'p-card-text',
    'p-card-title',
    'p-card-item',
    'p-code',
    'p-col',
    'p-container',
    'p-data-iterator',
    'p-data-table-server',
    'p-date-picker-controls',
    'p-date-picker-header',
    'p-date-picker-month',
    'p-date-picker-months',
    'p-date-picker-years',
    'p-file-input',
    'p-file-upload',
    'p-file-upload-item',
    'p-field',
    'p-footer',
    'p-hotkey',
    'p-hover',
    'p-input',
    'p-kbd',
    'p-layout',
    'p-layout-item',
    'p-lazy',
    'p-list-item-subtitle',
    'p-list-item-title',
    'p-list-group',
    'p-list-subheader',
    'p-main',
    'p-navigation-drawer',
    'p-pagination',
    'p-parallax',
    'p-pull-to-refresh',
    'p-responsive',
    'p-row',
    'p-skeleton-text',
    'p-system-bar',
    'p-table',
    'p-text',
    'p-time-picker-clock',
    'p-time-picker-controls',
    'p-toolbar-items',
    'p-toolbar-title',
    'p-transition',
    'p-virtual-scroll',
    'p-window',
];
foreach ($removedMaterialTags as $removedTag) {
    if (TemplateRegistry::factory($removedTag) !== null) {
        throw new RuntimeException("Removed mobile tag {$removedTag} is still registered.");
    }
    if (array_key_exists($removedTag, MaterialComponentMap::TAGS)) {
        throw new RuntimeException("Removed mobile tag {$removedTag} is still catalogued.");
    }
}

$publicDocumentation = [
    dirname(__DIR__).'/README.md',
    dirname(__DIR__).'/docs/authoring.md',
    dirname(__DIR__).'/examples/kitchen-sink/README.md',
];
$removedPublicTags = [
    'p-text',
    'p-col',
    'p-row',
    'p-container',
    'p-layout',
    'p-main',
    ...$removedMaterialTags,
];
foreach ($publicDocumentation as $path) {
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException("Cannot inspect public documentation {$path}.");
    }
    foreach ($removedPublicTags as $removedTag) {
        if (preg_match('/<'.preg_quote($removedTag, '/').'(?:\\s|>|\\/)/', $contents) === 1) {
            throw new RuntimeException(
                "Public documentation {$path} still uses removed tag {$removedTag}.",
            );
        }
    }
}

$componentIds = json_decode(
    file_get_contents(dirname(__DIR__).'/resources/component-ids.json')
        ?: throw new RuntimeException('Cannot read component IDs.'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$iconCatalog = json_decode(
    file_get_contents(dirname(__DIR__).'/resources/icons.json')
        ?: throw new RuntimeException('Cannot read the icon catalog.'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($componentIds) || !is_array($iconCatalog)) {
    throw new RuntimeException('Generated ID resources must decode to objects.');
}
$icons = $iconCatalog['icons'] ?? null;
if (!is_array($icons)) {
    throw new RuntimeException('The icon catalog is missing its icon map.');
}
$expectedIconIds = [];
foreach (array_keys($icons) as $icon) {
    if (is_string($icon) && is_int($componentIds[$icon] ?? null)) {
        $expectedIconIds[] = $componentIds[$icon];
    }
}
sort($expectedIconIds, SORT_NUMERIC);
$nativeIconMaps = [
    dirname(__DIR__).'/android/src/main/kotlin/dev/pam/mobileui/GeneratedIcons.kt'
        => '/^        (\d+) to arrayOf\(/m',
    dirname(__DIR__).'/ios/Sources/PamMobileUi/GeneratedIcons.swift'
        => '/^        (\d+): \[/m',
];
foreach ($nativeIconMaps as $path => $pattern) {
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException("Cannot inspect generated icon map {$path}.");
    }
    preg_match_all($pattern, $contents, $matches);
    $actualIds = array_map('intval', $matches[1]);
    sort($actualIds, SORT_NUMERIC);
    if ($actualIds !== $expectedIconIds) {
        throw new RuntimeException(
            "Generated icon IDs in {$path} diverge from the component protocol.",
        );
    }
}

echo sprintf(
    "Validated the curated public surface: %d p-* components; no removed mobile or legacy aliases.\n",
    count(MaterialComponentMap::TAGS),
);
