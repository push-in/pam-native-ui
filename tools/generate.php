<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root.'/resources/upstream-components.json';
$contents = file_get_contents($source);

if ($contents === false) {
    throw new RuntimeException("Run tools/capture-upstream.php before {$argv[0]}.");
}

/** @var array{modules: list<array{name: string, exports: list<string>}>} $catalog */
$catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
$tags = [];
$modules = [];
$classNames = [
    'Switch' => 'SwitchControl',
];

foreach ($catalog['modules'] as $module) {
    $moduleTags = [];

    foreach ($module['exports'] as $export) {
        if (
            preg_match('/^[A-Z][A-Za-z0-9]*$/D', $export) !== 1
            || str_starts_with($export, 'UI')
            || str_starts_with($export, 'RN')
            || str_starts_with($export, 'Animated')
            || str_ends_with($export, 'Style')
        ) {
            continue;
        }

        $tags[$export] = $classNames[$export] ?? $export;
        $moduleTags[$export] = true;
    }

    $modules[$module['name']] = array_keys($moduleTags);
}

ksort($tags, SORT_STRING);
ksort($modules, SORT_STRING);
$ids = [];

foreach (array_keys($tags) as $index => $tag) {
    $ids[$tag] = $index + 1;
}

$categories = [
    'heading' => 1,
    'text' => 1,
    'box' => 2,
    'center' => 2,
    'divider' => 2,
    'grid' => 2,
    'hstack' => 2,
    'view' => 2,
    'vstack' => 2,
    'alert' => 3,
    'progress' => 3,
    'skeleton' => 3,
    'spinner' => 3,
    'toast' => 3,
    'badge' => 4,
    'card' => 4,
    'table' => 4,
    'tabs' => 4,
    'button' => 5,
    'calendar' => 5,
    'checkbox' => 5,
    'date-time-picker' => 5,
    'form-control' => 5,
    'input' => 5,
    'link' => 5,
    'pressable' => 5,
    'radio' => 5,
    'select' => 5,
    'slider' => 5,
    'switch' => 5,
    'textarea' => 5,
    'accordion' => 7,
    'avatar' => 8,
    'icon' => 8,
    'image' => 8,
    'image-background' => 8,
    'chat-ai' => 10,
];
$alphaModules = array_fill_keys([
    'bottomsheet',
    'calendar',
    'date-time-picker',
    'grid',
    'image-viewer',
    'liquid-glass',
    'skeleton',
    'table',
    'tabs',
], true);
$nativeModules = array_fill_keys([
    'accordion',
    'actionsheet',
    'alert-dialog',
    'bottomsheet',
    'calendar',
    'checkbox',
    'date-time-picker',
    'drawer',
    'image-viewer',
    'liquid-glass',
    'menu',
    'modal',
    'popover',
    'portal',
    'progress',
    'radio',
    'select',
    'skeleton',
    'slider',
    'tabs',
    'toast',
    'tooltip',
], true);
$coreModules = array_fill_keys([
    'flat-list',
    'image',
    'image-background',
    'input-accessory-view',
    'keyboard-avoiding-view',
    'refresh-control',
    'safe-area-view',
    'scroll-view',
    'section-list',
    'status-bar',
    'virtualized-list',
    'view',
], true);
$serviceModules = array_fill_keys([
    'all-components',
    'gluestack-ui-provider',
    'utils',
], true);
$parityModules = [];

foreach ($catalog['modules'] as $index => $module) {
    $name = $module['name'];
    $implementation = match (true) {
        isset($nativeModules[$name]) => 2,
        isset($coreModules[$name]) => 3,
        isset($serviceModules[$name]) => 4,
        default => 1,
    };
    $hasVariants = ($module['variants'] ?? []) !== [];
    $parityModules[] = [
        'id' => $index + 1,
        'name' => $name,
        'category' => $categories[$name]
            ?? ((isset($coreModules[$name]) || isset($serviceModules[$name])) ? 9 : 6),
        'maturity' => isset($alphaModules[$name]) ? 2 : 1,
        'implementation' => $implementation,
        'exports' => $module['exports'],
        'parts' => $modules[$name],
        'variantAxes' => array_values(array_map(
            static fn (string $variant, int $variantIndex): array => [
                'id' => $variantIndex + 1,
                'name' => $variant,
            ],
            $module['variants'] ?? [],
            array_keys($module['variants'] ?? []),
        )),
        'examples' => $module['examples'] ?? 0,
        'verification' => [
            2,
            2,
            $implementation === 4 ? 4 : 2,
            $hasVariants ? 2 : 4,
            $implementation === 4 ? 4 : 2,
            2,
            2,
            ($module['examples'] ?? 0) > 0 ? 2 : 1,
            2,
            1,
        ],
    ];
}

$parity = json_encode([
    '$schema' => './parity.schema.json',
    'reference' => [
        'repository' => $catalog['repository'] ?? 'https://github.com/gluestack/gluestack-ui',
        'commit' => $catalog['commit'] ?? '',
        'packageVersion' => $catalog['packageVersion'] ?? '',
        'capturedAt' => $catalog['capturedAt'] ?? '',
        'moduleCount' => count($catalog['modules']),
        'exportCount' => array_sum(array_map(
            static fn (array $module): int => count($module['exports']),
            $catalog['modules'],
        )),
        'facadeCount' => count($tags),
    ],
    'definitions' => [
        'categories' => [
            ['id' => 1, 'name' => 'typography'],
            ['id' => 2, 'name' => 'layout'],
            ['id' => 3, 'name' => 'feedback'],
            ['id' => 4, 'name' => 'data-display'],
            ['id' => 5, 'name' => 'forms'],
            ['id' => 6, 'name' => 'overlay'],
            ['id' => 7, 'name' => 'disclosure'],
            ['id' => 8, 'name' => 'media'],
            ['id' => 9, 'name' => 'system'],
            ['id' => 10, 'name' => 'ai'],
        ],
        'gates' => [
            ['id' => 1, 'name' => 'php'],
            ['id' => 2, 'name' => 'tags'],
            ['id' => 3, 'name' => 'android'],
            ['id' => 4, 'name' => 'variants'],
            ['id' => 5, 'name' => 'states'],
            ['id' => 6, 'name' => 'accessibility'],
            ['id' => 7, 'name' => 'themes'],
            ['id' => 8, 'name' => 'examples'],
            ['id' => 9, 'name' => 'tests'],
            ['id' => 10, 'name' => 'performance'],
        ],
        'verificationStatuses' => [
            ['id' => 1, 'name' => 'planned'],
            ['id' => 2, 'name' => 'implemented'],
            ['id' => 3, 'name' => 'verified'],
            ['id' => 4, 'name' => 'not-applicable'],
        ],
    ],
    'modules' => $parityModules,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

$categoryLabels = [
    1 => 'Typography',
    2 => 'Layout',
    3 => 'Feedback',
    4 => 'Data display',
    5 => 'Forms',
    6 => 'Overlay',
    7 => 'Disclosure',
    8 => 'Media',
    9 => 'System',
    10 => 'AI',
];
$implementationLabels = [
    1 => 'PHP composition',
    2 => 'Android native host',
    3 => 'PAM core primitive',
    4 => 'Service/provider',
];
$maturityLabels = [
    1 => 'Stable',
    2 => 'Alpha',
];
$catalogMarkdown = <<<'MARKDOWN'
# Component catalog

Generated from the pinned gluestack-ui reference. Do not edit this file by hand;
run `composer generate` after updating the upstream capture.

Names in **Tags** are available through both declarative `.pam` syntax and PHP
facades under `Pam\MobileUi\Component`.

MARKDOWN;

foreach ($parityModules as $module) {
    $variantNames = array_map(
        static fn (array $axis): string => "`{$axis['name']}`",
        $module['variantAxes'],
    );
    $tagNames = array_map(
        static fn (string $part): string => "`{$part}`",
        $module['parts'],
    );
    $catalogMarkdown .= "\n## {$module['name']}\n\n";
    $catalogMarkdown .= '- Category: '.$categoryLabels[$module['category']]."\n";
    $catalogMarkdown .= '- Maturity: '.$maturityLabels[$module['maturity']]."\n";
    $catalogMarkdown .= '- Runtime: '.$implementationLabels[$module['implementation']]."\n";
    $catalogMarkdown .= '- Variants: '.($variantNames === [] ? 'none' : implode(', ', $variantNames))."\n";
    $catalogMarkdown .= '- Upstream examples captured: '.$module['examples']."\n";
    $catalogMarkdown .= '- Tags: '.($tagNames === [] ? 'none' : implode(', ', $tagNames))."\n";
}

$catalogMarkdown .= "\n";

$facades = <<<'PHP'
<?php

declare(strict_types=1);

namespace Pam\MobileUi\Component;

/**
 * Generated from resources/upstream-components.json.
 *
 * @codeCoverageIgnore
 */

PHP;

foreach ($tags as $tag => $class) {
    $facades .= "final class {$class} extends UiComponent\n{\n";
    $facades .= "    protected const string COMPONENT = ".var_export($tag, true).";\n";
    $facades .= "}\n\n";
}

$map = <<<'PHP'
<?php

declare(strict_types=1);

namespace Pam\MobileUi\Generated;

final class ComponentMap
{
    /** @var array<string, class-string<\Pam\MobileUi\Component\UiComponent>> */
    public const array TAGS = [
PHP;

foreach ($tags as $tag => $class) {
    $map .= '        '.var_export($tag, true).' => \\Pam\\MobileUi\\Component\\'.$class."::class,\n";
}

$map .= "    ];\n\n";
$map .= "    /** @var array<string, int> */\n";
$map .= "    public const array IDS = [\n";

foreach ($ids as $tag => $id) {
    $map .= '        '.var_export($tag, true)." => {$id},\n";
}

$map .= "    ];\n\n";
$map .= "    /** @var array<string, list<string>> */\n";
$map .= "    public const array MODULES = [\n";

foreach ($modules as $module => $moduleTags) {
    $map .= '        '.var_export($module, true).' => '.var_export($moduleTags, true).",\n";
}

$map .= "    ];\n\n";
$map .= "    private function __construct()\n    {\n    }\n}\n";

$kotlin = <<<'KOTLIN'
package dev.pam.mobileui

/** Generated from resources/upstream-components.json. */
internal object GeneratedComponents {
KOTLIN;

foreach ($ids as $tag => $id) {
    $constant = strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $tag));
    $kotlin .= "\n    const val {$constant}: Int = {$id}";
}

$kotlin .= "\n}\n";

$iconContents = file_get_contents($root.'/resources/icons.json');

if ($iconContents === false) {
    throw new RuntimeException('Run tools/capture-upstream.php to generate resources/icons.json.');
}

/** @var array{icons: array<string, list<string>>} $iconCatalog */
$iconCatalog = json_decode($iconContents, true, flags: JSON_THROW_ON_ERROR);
$kotlinIcons = <<<'KOTLIN'
package dev.pam.mobileui

/** Generated from the MIT-licensed gluestack-ui icon paths. */
internal object GeneratedIcons {
    val paths: Map<Int, Array<String>> = mapOf(
KOTLIN;

foreach ($iconCatalog['icons'] as $icon => $paths) {
    if (!isset($ids[$icon])) {
        continue;
    }

    $encodedPaths = array_map(
        static fn (string $path): string => '"'.addcslashes($path, "\\\"\n\r\$").'"',
        $paths,
    );
    $kotlinIcons .= "\n        {$ids[$icon]} to arrayOf(".implode(', ', $encodedPaths).'),';
}

$kotlinIcons .= "\n    )\n}\n";

$styleContents = file_get_contents($root.'/resources/styles.json');

if ($styleContents === false) {
    throw new RuntimeException('Run tools/capture-upstream.php to generate resources/styles.json.');
}

/**
 * @var array{
 *     styles: list<array{
 *         module: string,
 *         source: string,
 *         name: string,
 *         base: string,
 *         variants: array<string, mixed>,
 *         parentVariants: array<string, mixed>,
 *         defaultVariants: array<string, mixed>,
 *         compoundVariants?: list<array<string, mixed>>,
 *         parentCompoundVariants?: list<array<string, mixed>>
 *     }>
 * } $styleCatalog
 */
$styleCatalog = json_decode($styleContents, true, flags: JSON_THROW_ON_ERROR);
$inlineRecipeContents = file_get_contents($root.'/resources/inline-recipes.json');

if ($inlineRecipeContents === false) {
    throw new RuntimeException('The generated inline recipe catalog is missing.');
}

/**
 * @var array{
 *     referenceCommit: string,
 *     recipes: list<array{
 *         module: string,
 *         source: string,
 *         name: string,
 *         base: string,
 *         variants: array<string, mixed>,
 *         parentVariants: array<string, mixed>,
 *         defaultVariants: array<string, mixed>
 *     }>
 * } $inlineRecipeCatalog
 */
$inlineRecipeCatalog = json_decode(
    $inlineRecipeContents,
    true,
    flags: JSON_THROW_ON_ERROR,
);

if (
    $inlineRecipeCatalog['referenceCommit']
    !== ($styleCatalog['referenceCommit'] ?? null)
) {
    throw new RuntimeException('Inline recipes and upstream styles must pin the same commit.');
}
$recipesByKey = [];

foreach ([...$styleCatalog['styles'], ...$inlineRecipeCatalog['recipes']] as $recipe) {
    $key = $recipe['module'].':'.$recipe['name'];
    $existing = $recipesByKey[$key] ?? null;
    $sourceIsWeb = str_contains($recipe['source'], '.web.');
    $existingIsWeb = is_array($existing)
        && str_contains((string) ($existing['source'] ?? ''), '.web.');

    if ($existing !== null && (!$existingIsWeb || $sourceIsWeb)) {
        continue;
    }

    $recipesByKey[$key] = [
        'source' => $recipe['source'],
        'base' => $recipe['base'],
        'variants' => $recipe['variants'],
        'parentVariants' => $recipe['parentVariants'],
        'defaultVariants' => $recipe['defaultVariants'],
        'compoundVariants' => $recipe['compoundVariants'] ?? [],
        'parentCompoundVariants' => $recipe['parentCompoundVariants'] ?? [],
    ];
}

ksort($recipesByKey, SORT_STRING);
$recipeIds = [];
$recipes = [];

foreach ($recipesByKey as $key => $recipe) {
    $id = count($recipeIds) + 1;
    $recipeIds[$key] = $id;
    $recipes[$id] = $recipe;
}

$styleAliases = [
    'avatar' => [
        'AvatarFallback' => 'avatarStyle',
    ],
    'bottomsheet' => [
        'BottomSheetDragIndicator' => 'bottomSheetHandleStyle',
    ],
    'calendar' => [
        'CalendarHeaderPrevButton' => 'calendarHeaderButtonStyle',
        'CalendarHeaderNextButton' => 'calendarHeaderButtonStyle',
        'CalendarHeaderMonthSelect' => 'calendarHeaderSelectStyle',
        'CalendarHeaderYearSelect' => 'calendarHeaderSelectStyle',
    ],
    'image-viewer' => [
        'ImageViewer' => 'imageViewerStyle',
    ],
    'select' => [
        'Select' => 'selectStyle',
        'SelectPortal' => 'actionsheetStyle',
        'SelectBackdrop' => 'actionsheetBackdropStyle',
        'SelectContent' => 'actionsheetContentStyle',
        'SelectDragIndicator' => 'actionsheetDragIndicatorStyle',
        'SelectDragIndicatorWrapper' => 'actionsheetDragIndicatorWrapperStyle',
        'SelectItem' => 'actionsheetItemStyle',
        'SelectScrollView' => 'actionsheetScrollViewStyle',
        'SelectVirtualizedList' => 'actionsheetVirtualizedListStyle',
        'SelectFlatList' => 'actionsheetFlatListStyle',
        'SelectSectionList' => 'actionsheetSectionListStyle',
        'SelectSectionHeaderText' => 'actionsheetSectionHeaderTextStyle',
    ],
    'table' => [
        'TableRow' => 'tableRowStyleStyle',
    ],
    'tabs' => [
        'TabsAnimatedIndicator' => 'tabsIndicatorStyle',
    ],
];
$componentRecipeIds = [];
$componentRecipeScores = [];

foreach ($modules as $module => $moduleTags) {
    foreach ($moduleTags as $tag) {
        $candidate = match ($tag) {
            'HStack' => 'hstackStyle',
            'VStack' => 'vstackStyle',
            default => lcfirst($tag).'Style',
        };
        $score = 100;

        if (!isset($recipeIds[$module.':'.$candidate])) {
            $candidate = $styleAliases[$module][$tag] ?? '';
            $score = 90;
        }
        if (
            $candidate === ''
            && $module === 'icon'
            && str_ends_with($tag, 'Icon')
        ) {
            $candidate = 'iconStyle';
            $score = 50;
        }

        $recipeId = $recipeIds[$module.':'.$candidate] ?? null;
        if (
            $recipeId !== null
            && $score > ($componentRecipeScores[$tag] ?? 0)
        ) {
            $componentRecipeIds[$tag] = $recipeId;
            $componentRecipeScores[$tag] = $score;
        }
    }
}

ksort($componentRecipeIds, SORT_STRING);
$styleRecipes = <<<'PHP'
<?php

declare(strict_types=1);

namespace Pam\MobileUi\Generated;

/**
 * Build-time compiled from the MIT-licensed gluestack-ui tva recipes.
 *
 * Recipe and component references use stable sequential integers so the hot
 * rendering path never parses upstream JavaScript or JSON.
 */
final class StyleRecipes
{
    /** @var array<int, array<string, mixed>> */
    public const array RECIPES =
PHP;
$styleRecipes .= ' '.var_export($recipes, true).";\n\n";
$styleRecipes .= "    /** @var array<string, int> */\n";
$styleRecipes .= "    public const array COMPONENTS = ".var_export($componentRecipeIds, true).";\n\n";
$styleRecipes .= "    private function __construct()\n    {\n    }\n}\n";

$targets = [
    $root.'/src/Generated/ComponentFacades.php' => $facades,
    $root.'/src/Generated/ComponentMap.php' => $map,
    $root.'/src/Generated/StyleRecipes.php' => $styleRecipes,
    $root.'/android/src/main/kotlin/dev/pam/mobileui/GeneratedComponents.kt' => $kotlin,
    $root.'/android/src/main/kotlin/dev/pam/mobileui/GeneratedIcons.kt' => $kotlinIcons,
    $root.'/resources/parity.json' => $parity,
    $root.'/docs/catalog.md' => $catalogMarkdown,
];

foreach ($targets as $path => $generated) {
    $directory = dirname($path);

    if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
        throw new RuntimeException("Cannot create {$directory}.");
    }

    $generated = rtrim($generated)."\n";
    $generated = preg_replace('/[ \t]+$/m', '', $generated) ?? $generated;

    if (file_put_contents($path, $generated, LOCK_EX) === false) {
        throw new RuntimeException("Cannot write {$path}.");
    }
}

fwrite(STDOUT, sprintf("Generated %d component facades.\n", count($tags)));
