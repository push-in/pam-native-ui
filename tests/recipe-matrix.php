<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\StyleRecipes;
use Pam\MobileUi\Rendering\StyleRecipeResolver;
use Pam\MobileUi\Rendering\TailwindStyleCompiler;
use Pam\MobileUi\Rendering\ValueNormalizer;
use Pam\MobileUi\Theme\Theme;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;

require __DIR__.'/bootstrap.php';

/**
 * @param array<string, mixed> $props
 */
$assertRecipeSupported = static function (
    string $part,
    array $props,
    Theme $theme,
): void {
    $classes = StyleRecipeResolver::classes($part, $props);
    if ($classes === null) {
        return;
    }

    $unsupported = TailwindStyleCompiler::unsupportedUtilities(
        $classes,
        $props,
        $theme,
    );
    if ($unsupported !== []) {
        throw new RuntimeException(sprintf(
            '%s has unsupported recipe utilities for %s: %s',
            $part,
            json_encode($props, JSON_THROW_ON_ERROR),
            implode(', ', $unsupported),
        ));
    }
};

$themes = [
    [ThemeMode::Light, Themes::light()],
    [ThemeMode::Dark, Themes::dark()],
    [ThemeMode::Light, Themes::pamLight()],
    [ThemeMode::Dark, Themes::pamDark()],
];
$stateCases = [
    ['active' => true],
    ['checked' => true],
    ['disabled' => true],
    ['focused' => true],
    ['hovered' => true],
    ['invalid' => true],
    ['selected' => true],
    ['flip' => false],
    [
        'active' => true,
        'checked' => true,
        'disabled' => true,
        'focused' => true,
        'hovered' => true,
        'invalid' => true,
        'selected' => true,
        'flip' => true,
    ],
];
$defaultCases = 0;
$variantCases = 0;
$compoundCases = 0;
$stateMatrixCases = 0;

foreach ($themes as [$mode, $theme]) {
    ThemeManager::customize(light: $theme, dark: $theme);
    ThemeManager::mode($mode);

    foreach (ComponentMap::TAGS as $part => $componentClass) {
        $componentClass::make()->toElement();
        $defaultProps = [];
        $assertRecipeSupported($part, $defaultProps, $theme);
        $defaultCases++;

        foreach ($stateCases as $stateProps) {
            $normalizedStateProps = ValueNormalizer::props($stateProps);
            $componentClass::make($stateProps)->toElement();
            $assertRecipeSupported($part, $normalizedStateProps, $theme);
            $stateMatrixCases++;
        }

        if (!array_key_exists($part, StyleRecipes::COMPONENTS)) {
            continue;
        }
        $recipeId = StyleRecipes::COMPONENTS[$part];
        $recipe = StyleRecipes::RECIPES[$recipeId];

        foreach (['variants', 'parentVariants'] as $variantGroup) {
            $axes = $recipe[$variantGroup];
            foreach ($axes as $axis => $options) {
                foreach (array_keys($options) as $option) {
                    $props = ValueNormalizer::props([$axis => $option]);
                    $componentClass::make([$axis => $option])->toElement();
                    $assertRecipeSupported($part, $props, $theme);
                    $variantCases++;
                }
            }
        }

        foreach (['compoundVariants', 'parentCompoundVariants'] as $compoundGroup) {
            $rules = $recipe[$compoundGroup];
            foreach ($rules as $rule) {
                $props = [];
                foreach ($rule as $axis => $option) {
                    if ($axis !== 'class') {
                        $props[$axis] = $option;
                    }
                }
                $normalizedProps = ValueNormalizer::props($props);
                $componentClass::make($props)->toElement();
                $assertRecipeSupported($part, $normalizedProps, $theme);
                $compoundCases++;
            }
        }
    }
}

ThemeManager::reset();

echo json_encode(
    [
        'facades' => count(ComponentMap::TAGS),
        'themes' => count($themes),
        'defaultCases' => $defaultCases,
        'variantCases' => $variantCases,
        'compoundCases' => $compoundCases,
        'stateCases' => $stateMatrixCases,
        'unsupportedCount' => 0,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";
