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
];

$families = [
    'Actions and identity' => ['p-btn', 'p-btn-group', 'p-btn-toggle', 'p-chip', 'p-chip-group', 'p-fab', 'p-icon-btn', 'p-speed-dial', 'p-avatar', 'p-badge', 'p-icon'],
    'Selection controls' => ['p-checkbox', 'p-radio', 'p-radio-group', 'p-switch', 'p-slider', 'p-range-slider', 'p-rating', 'p-item', 'p-item-group'],
    'Forms and editing' => ['p-form', 'p-text-field', 'p-textarea', 'p-number-input', 'p-otp-input', 'p-autocomplete', 'p-combobox', 'p-select', 'p-color-input'],
    'Dates and time' => ['p-calendar', 'p-calendar-day', 'p-date-input', 'p-date-picker', 'p-time-picker'],
    'Data display' => ['p-data-table', 'p-data-table-virtual', 'p-list', 'p-list-item', 'p-infinite-scroll', 'p-timeline', 'p-timeline-item', 'p-treeview', 'p-treeview-item', 'p-sparkline'],
    'Navigation and progress' => ['p-app-bar', 'p-app-bar-nav-icon', 'p-toolbar', 'p-tabs', 'p-tab', 'p-stepper', 'p-stepper-header', 'p-stepper-item', 'p-stepper-actions', 'p-stepper-window', 'p-stepper-window-item', 'p-stepper-vertical', 'p-stepper-vertical-item', 'p-stepper-vertical-actions', 'p-slide-group', 'p-slide-group-item'],
    'Overlays and feedback' => ['p-alert', 'p-banner', 'p-banner-actions', 'p-bottom-sheet', 'p-dialog', 'p-menu', 'p-overlay', 'p-snackbar', 'p-tooltip', 'p-progress-circular', 'p-progress-linear', 'p-skeleton-loader', 'p-empty-state'],
    'Surfaces and media' => ['p-card', 'p-card-actions', 'p-sheet', 'p-divider', 'p-carousel', 'p-carousel-item', 'p-img', 'p-expansion-panels', 'p-expansion-panel', 'p-expansion-panel-title', 'p-expansion-panel-text'],
];
$familyByTag = [];
foreach ($families as $family => $tags) {
    foreach ($tags as $tag) {
        $familyByTag[$tag] = $family;
    }
}

$variationMethod = new ReflectionMethod(ComponentRoute::class, 'variations');
$entries = [];
foreach (MaterialComponentMap::TAGS as $tag => $class) {
    $shortClass = substr($class, strrpos($class, '\\') + 1);
    $route = new ComponentRoute($tag, $shortClass, $class);
    /** @var list<array{label: string, props: array<string, mixed>}> $variations */
    $variations = $variationMethod->invoke($route);
    $props = [];
    foreach ($variations as $variation) {
        foreach ($variation['props'] as $name => $value) {
            if (str_ends_with($name, 'Profile')) {
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
