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
