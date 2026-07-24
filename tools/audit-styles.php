<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Rendering\TailwindStyleCompiler;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;

require dirname(__DIR__).'/tests/bootstrap.php';

$contents = file_get_contents(dirname(__DIR__).'/resources/styles.json');
if ($contents === false) {
    throw new RuntimeException('The captured style catalog is missing.');
}

/** @var array{styles: list<array{classes: list<string>}>} $catalog */
$catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
$classGroups = [];
foreach ($catalog['styles'] as $style) {
    array_push($classGroups, ...$style['classes']);
}
$inlineContents = file_get_contents(dirname(__DIR__).'/resources/inline-classes.json');
if ($inlineContents === false) {
    throw new RuntimeException('The captured inline class catalog is missing.');
}
/** @var array{referenceCommit: string, sources: list<array{classes: list<string>}>} $inline */
$inline = json_decode($inlineContents, true, flags: JSON_THROW_ON_ERROR);
if ($inline['referenceCommit'] !== ($catalog['referenceCommit'] ?? null)) {
    throw new RuntimeException('Style captures do not reference the same upstream commit.');
}
foreach ($inline['sources'] as $source) {
    array_push($classGroups, ...$source['classes']);
}
$inlineRecipeContents = file_get_contents(dirname(__DIR__).'/resources/inline-recipes.json');
if ($inlineRecipeContents === false) {
    throw new RuntimeException('The generated inline recipe catalog is missing.');
}
/**
 * @var array{
 *     referenceCommit: string,
 *     recipes: list<array{
 *         base: string,
 *         variants: array<string, array<array-key, string>>,
 *         parentVariants: array<string, array<array-key, string>>,
 *         compoundVariants?: list<array<string, mixed>>,
 *         parentCompoundVariants?: list<array<string, mixed>>
 *     }>
 * } $inlineRecipes
 */
$inlineRecipes = json_decode($inlineRecipeContents, true, flags: JSON_THROW_ON_ERROR);
if ($inlineRecipes['referenceCommit'] !== ($catalog['referenceCommit'] ?? null)) {
    throw new RuntimeException('Generated inline recipes reference a different upstream commit.');
}
foreach ($inlineRecipes['recipes'] as $recipe) {
    $classGroups[] = $recipe['base'];
    foreach ([$recipe['variants'], $recipe['parentVariants']] as $axes) {
        foreach ($axes as $options) {
            array_push($classGroups, ...array_values($options));
        }
    }
    foreach ([
        $recipe['compoundVariants'] ?? [],
        $recipe['parentCompoundVariants'] ?? [],
    ] as $rules) {
        foreach ($rules as $rule) {
            if (is_string($rule['class'] ?? null)) {
                $classGroups[] = $rule['class'];
            }
        }
    }
}

$props = [
    'active' => true,
    'checked' => true,
    'disabled' => true,
    'focused' => true,
    'hovered' => true,
    'invalid' => true,
    'selected' => true,
];
$unsupported = [];

foreach ([ThemeMode::Light, ThemeMode::Dark] as $mode) {
    ThemeManager::mode($mode);
    foreach ([false, true] as $flip) {
        $props['flip'] = $flip;
        foreach (
            TailwindStyleCompiler::unsupportedUtilities(
                $classGroups,
                $props,
                $mode === ThemeMode::Dark ? Themes::dark() : Themes::light(),
            ) as $utility
        ) {
            $unsupported[$utility] = true;
        }
    }
}

$utilities = array_keys($unsupported);
sort($utilities, SORT_STRING);

fwrite(
    STDOUT,
    json_encode(
        [
            'capturedRecipes' => count($catalog['styles']),
            'capturedInlineSources' => count($inline['sources']),
            'generatedInlineRecipes' => count($inlineRecipes['recipes']),
            'unsupportedCount' => count($utilities),
            'unsupportedMobileUtilities' => $utilities,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    )."\n",
);

if (in_array('--strict', $argv, true) && $utilities !== []) {
    exit(1);
}
