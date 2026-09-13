<?php

declare(strict_types=1);

use App\ComponentRoute;
use Pam\MobileUi\Generated\MaterialComponentMap;

$root = dirname(__DIR__);
require $root.'/examples/kitchen-sink/vendor/autoload.php';

$output = $argv[1] ?? null;
if (!is_string($output) || $output === '') {
    fwrite(STDERR, "Usage: php tools/export-component-docs.php <output.json>\n");
    exit(2);
}

/** @var list<array{type: int, module: string, components: list<string>}> $modules */
$modules = require $root.'/resources/pam-material-components.php';
$moduleByClass = [];
foreach ($modules as $module) {
    foreach ($module['components'] as $class) {
        $moduleByClass[$class] = $module['module'];
    }
}

$static = [
    'p-alert', 'p-avatar', 'p-badge', 'p-banner', 'p-card', 'p-data-table',
    'p-divider', 'p-empty-state', 'p-expansion-panel-text', 'p-icon', 'p-img',
    'p-progress-circular', 'p-progress-linear', 'p-sheet', 'p-skeleton-loader',
    'p-sparkline', 'p-stepper-header', 'p-stepper-window-item', 'p-timeline',
    'p-app-scaffold', 'p-chart', 'p-responsive-grid', 'p-result-state',
];

$families = [
    'Actions and identity' => ['p-btn', 'p-btn-group', 'p-btn-toggle', 'p-chip', 'p-chip-group', 'p-fab', 'p-icon-btn', 'p-speed-dial', 'p-avatar', 'p-badge', 'p-icon'],
    'Selection controls' => ['p-checkbox', 'p-radio', 'p-radio-group', 'p-switch', 'p-slider', 'p-range-slider', 'p-rating', 'p-item', 'p-item-group'],
    'Forms and editing' => ['p-form', 'p-text-field', 'p-textarea', 'p-number-input', 'p-otp-input', 'p-autocomplete', 'p-combobox', 'p-select', 'p-color-input', 'p-search-bar', 'p-password-field', 'p-masked-field', 'p-currency-field', 'p-tag-input', 'p-multi-select', 'p-file-input', 'p-filter-bar', 'p-segmented-button'],
    'Dates and time' => ['p-calendar', 'p-calendar-day', 'p-date-input', 'p-date-picker', 'p-time-picker', 'p-date-range-picker', 'p-time-range-picker'],
    'Data display' => ['p-data-table', 'p-data-table-virtual', 'p-data-grid', 'p-list', 'p-list-item', 'p-virtual-list', 'p-section-list', 'p-reorderable-list', 'p-swipe-actions', 'p-pull-to-refresh', 'p-infinite-scroll', 'p-timeline', 'p-timeline-item', 'p-treeview', 'p-treeview-item', 'p-tree-select', 'p-sparkline', 'p-chart'],
    'Navigation and progress' => ['p-app-bar', 'p-app-bar-nav-icon', 'p-app-scaffold', 'p-bottom-app-bar', 'p-navigation-bar', 'p-navigation-rail', 'p-navigation-drawer', 'p-pagination', 'p-toolbar', 'p-tabs', 'p-tab', 'p-stepper', 'p-stepper-header', 'p-stepper-item', 'p-stepper-actions', 'p-stepper-window', 'p-stepper-window-item', 'p-stepper-vertical', 'p-stepper-vertical-item', 'p-stepper-vertical-actions', 'p-slide-group', 'p-slide-group-item'],
    'Overlays and feedback' => ['p-alert', 'p-banner', 'p-banner-actions', 'p-bottom-sheet', 'p-command-palette', 'p-dialog', 'p-menu', 'p-overlay', 'p-popover', 'p-snackbar', 'p-tooltip', 'p-progress-button', 'p-progress-circular', 'p-progress-linear', 'p-skeleton-loader', 'p-empty-state', 'p-result-state'],
    'Surfaces and media' => ['p-card', 'p-card-actions', 'p-sheet', 'p-divider', 'p-carousel', 'p-carousel-item', 'p-img', 'p-responsive-grid', 'p-expansion-panels', 'p-expansion-panel', 'p-expansion-panel-title', 'p-expansion-panel-text'],
];
$familyByTag = [];
foreach ($families as $family => $tags) {
    foreach ($tags as $tag) {
        $familyByTag[$tag] = $family;
    }
}

// Some showcase routes delegate stateful rendering to focused preview
// components. Keep their real public props here so documentation never exposes
// the preview-only *Profile switches or produces an empty API table.
$delegatedProps = [
    'p-chip' => [
        'text' => 'View release', 'variant' => 'tonal', 'filter' => true,
        'selected' => true, 'closable' => true, 'closeLabel' => 'Remove filter',
        'disabled' => true, 'icon' => 'arrow-right',
    ],
    'p-chip-group' => [
        'value' => 'android', 'multiple' => true,
        'direction' => 'horizontal', 'disabled' => true,
    ],
    'p-color-input' => [
        'label' => 'HEX value', 'modelValue' => '#6750A4', 'mode' => 'hex',
        'variant' => 'outlined', 'disabled' => true, 'error' => true,
        'errorMessage' => 'Enter a valid HEX color', 'helper' => 'Six-digit hexadecimal color',
    ],
    'p-data-table' => [
        'headers' => [], 'items' => [], 'itemValue' => 'id', 'modelValue' => [],
        'showSelect' => true, 'density' => 'compact', 'striped' => true,
        'loading' => true, 'loadingText' => 'Syncing inventory',
        'noDataText' => 'No products found', 'mobile' => true,
    ],
    'p-data-table-virtual' => [
        'headers' => [], 'items' => [], 'itemValue' => 'id', 'modelValue' => [],
        'showSelect' => true, 'density' => 'compact', 'fixedHeader' => true,
        'striped' => true, 'loading' => true, 'loadingText' => 'Refreshing builds',
        'noDataText' => 'No builds found', 'mobile' => true, 'height' => 280,
        'prefetch' => 6, 'removeClippedSubviews' => true,
        'showsScrollIndicator' => true, 'endReachedThreshold' => 0.25,
    ],
];

$variationMethod = new ReflectionMethod(ComponentRoute::class, 'variations');
$samplePropsMethod = new ReflectionMethod(ComponentRoute::class, 'sampleProps');
$entries = [];
foreach (MaterialComponentMap::TAGS as $tag => $class) {
    $shortClass = substr($class, strrpos($class, '\\') + 1);
    $route = new ComponentRoute($tag, $shortClass, $class);
    /** @var list<array{label: string, props: array<string, mixed>}> $variations */
    $variations = $variationMethod->invoke($route);
    $props = [];
    foreach ($variations as $variation) {
        /** @var array<string, mixed> $effectiveProps */
        $effectiveProps = $samplePropsMethod->invoke($route, $variation['props']);
        foreach ($effectiveProps as $name => $value) {
            if (
                str_ends_with($name, 'Profile')
                || in_array($name, ['id', 'source'], true)
            ) {
                continue;
            }
            $type = match (true) {
                is_bool($value) => 'bool',
                is_int($value) => 'int',
                is_float($value) => 'float',
                is_array($value) => 'array',
                is_string($value) => 'string',
                $value === null => 'null',
                default => 'mixed',
            };
            $props[$name]['types'][$type] = true;
            if (is_scalar($value) || $value === null) {
                $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($encoded)) {
                    $props[$name]['examples'][$encoded] = true;
                }
            }
        }
    }
    foreach ($delegatedProps[$tag] ?? [] as $name => $value) {
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_array($value) => 'array',
            is_string($value) => 'string',
            default => 'mixed',
        };
        $props[$name]['types'][$type] = true;
        if (is_scalar($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($encoded)) {
                $props[$name]['examples'][$encoded] = true;
            }
        }
    }
    ksort($props);
    $documentedProps = [];
    foreach ($props as $name => $metadata) {
        $documentedProps[] = [
            'name' => $name,
            'types' => array_keys($metadata['types']),
            'examples' => array_slice(array_keys($metadata['examples'] ?? []), 0, 6),
        ];
    }
    $module = $moduleByClass[$shortClass] ?? str_replace('p-', '', $tag);
    $family = $familyByTag[$tag] ?? 'Native component';
    $entries[] = [
        'tag' => $tag,
        'class' => $shortClass,
        'namespace' => $class,
        'module' => $module,
        'family' => $family,
        'summary' => sprintf('%s component rendered through the retained-native Android and UIKit pipeline.', $family),
        'interactive' => !in_array($tag, $static, true),
        'profiles' => array_values(array_unique(array_column($variations, 'label'))),
        'props' => $documentedProps,
        'related' => array_values(array_filter(
            MaterialComponentMap::MODULES[$module] ?? [],
            static fn (string $related): bool => $related !== $tag,
        )),
    ];
}

usort($entries, static fn (array $left, array $right): int => $left['tag'] <=> $right['tag']);
$payload = json_encode([
    'schemaVersion' => 1,
    'componentCount' => count($entries),
    'source' => 'push-in/pam-native-ui',
    'components' => $entries,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($payload) || file_put_contents($output, $payload."\n", LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write {$output}\n");
    exit(1);
}

printf("Exported %d component documentation records to %s.\n", count($entries), $output);
