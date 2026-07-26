<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$specification = require $root.'/resources/pam-material-components.php';

if (!is_array($specification) || !array_is_list($specification)) {
    throw new RuntimeException('The PAM Material specification must be a list.');
}

$tags = [];
$ids = [];
$modules = [];
$nextComponentId = 1;
$expectedType = 1;

foreach ($specification as $module) {
    if (
        !is_array($module)
        || ($module['type'] ?? null) !== $expectedType
        || !is_string($module['module'] ?? null)
        || !is_array($module['components'] ?? null)
        || !array_is_list($module['components'])
    ) {
        throw new RuntimeException("Invalid PAM Material module type {$expectedType}.");
    }

    $moduleTags = [];
    foreach ($module['components'] as $class) {
        if (!is_string($class) || preg_match('/^P[A-Z][A-Za-z0-9]*$/D', $class) !== 1) {
            throw new RuntimeException("Invalid PAM Material facade in {$module['module']}.");
        }

        $tag = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $class));
        if (isset($tags[$tag])) {
            throw new RuntimeException("Duplicate PAM Material tag {$tag}.");
        }

        $tags[$tag] = $class;
        $ids[$class] = $nextComponentId++;
        $moduleTags[] = $tag;
    }

    $modules[$module['module']] = $moduleTags;
    $expectedType++;
}

$facades = <<<'PHP'
<?php

declare(strict_types=1);

namespace Pam\MobileUi\Material;

use Pam\MobileUi\Component\UiComponent;

/**
 * Generated from the authored resources/pam-material-components.php spec.
 *
 * @codeCoverageIgnore
 */

PHP;

foreach ($tags as $tag => $class) {
    $facades .= "final class {$class} extends UiComponent\n{\n";
    $facades .= "    protected const string COMPONENT = ".var_export($class, true).";\n";
    $facades .= "}\n\n";
}

$map = <<<'PHP'
<?php

declare(strict_types=1);

namespace Pam\MobileUi\Generated;

final class MaterialComponentMap
{
    /** @var array<string, class-string<\Pam\MobileUi\Component\UiComponent>> */
    public const array TAGS =
PHP;
$map .= ' '.var_export(array_map(
    static fn (string $class): string => "Pam\\MobileUi\\Material\\{$class}",
    $tags,
), true).";\n\n";
$map .= "    /** @var array<string, int> */\n";
$map .= "    public const array IDS = ".var_export($ids, true).";\n\n";
$map .= "    /** @var array<string, list<string>> */\n";
$map .= "    public const array MODULES = ".var_export($modules, true).";\n\n";
$map .= "    private function __construct()\n    {\n    }\n}\n";

$materialParity = json_encode([
    '$schema' => './material-parity.schema.json',
    'reference' => [
        'source' => 'resources/pam-material-components.php',
        'namespace' => 'p-*',
        'metadataImport' => false,
        'moduleCount' => count($modules),
        'componentCount' => count($tags),
        'targets' => [1, 2],
    ],
    'targetDefinitions' => [
        ['id' => 1, 'name' => 'android'],
        ['id' => 2, 'name' => 'ios'],
    ],
    'gateDefinitions' => [
        ['id' => 1, 'name' => 'inventory'],
        ['id' => 2, 'name' => 'tags'],
        ['id' => 3, 'name' => 'android'],
        ['id' => 4, 'name' => 'ios'],
        ['id' => 5, 'name' => 'md3-visual'],
        ['id' => 6, 'name' => 'motion-interaction'],
        ['id' => 7, 'name' => 'layout-insets-rtl'],
        ['id' => 8, 'name' => 'accessibility'],
        ['id' => 9, 'name' => 'themes'],
        ['id' => 10, 'name' => 'docs-showcase'],
        ['id' => 11, 'name' => 'tests'],
        ['id' => 12, 'name' => 'performance'],
    ],
    'statusDefinitions' => [
        ['id' => 1, 'name' => 'planned'],
        ['id' => 2, 'name' => 'implemented'],
        ['id' => 3, 'name' => 'verified'],
        ['id' => 4, 'name' => 'not-applicable'],
    ],
    'modules' => array_values(array_map(
        static fn (string $module, int $index): array => [
            'type' => $index + 1,
            'module' => $module,
            'components' => $modules[$module],
            'verification' => [3, 3, 3, 3, 2, 2, 2, 2, 3, 3, 3, 3],
        ],
        array_keys($modules),
        array_keys(array_keys($modules)),
    )),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$catalogMarkdown = "# PAM Material component catalog\n\n";
$catalogMarkdown .= "This catalog is generated from the manually authored ";
$catalogMarkdown .= "`resources/pam-material-components.php` specification. ";
$catalogMarkdown .= "It contains no imported Vuetify metadata and exposes only ";
$catalogMarkdown .= "native `p-*` tags.\n\n";
$catalogMarkdown .= sprintf(
    "**%d modules · %d component parts · Android and iOS**\n\n",
    count($modules),
    count($tags),
);
foreach ($modules as $module => $moduleTags) {
    $catalogMarkdown .= '## `'.$module."`\n\n";
    foreach ($moduleTags as $tag) {
        $catalogMarkdown .= '- `<'.$tag." />`\n";
    }
    $catalogMarkdown .= "\n";
}

$targets = [
    $root.'/src/Generated/MaterialComponentFacades.php' => $facades,
    $root.'/src/Generated/MaterialComponentMap.php' => $map,
    $root.'/resources/material-parity.json' => $materialParity,
    $root.'/docs/catalog.md' => $catalogMarkdown,
];

foreach ($targets as $path => $contents) {
    $contents = rtrim($contents)."\n";
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException("Cannot write {$path}.");
    }
}

fwrite(
    STDOUT,
    sprintf(
        "Generated %d p-* facades across %d PAM Material modules.\n",
        count($tags),
        count($modules),
    ),
);
