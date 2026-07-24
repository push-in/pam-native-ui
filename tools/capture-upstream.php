<?php

declare(strict_types=1);

require __DIR__.'/JavaScriptObjectParser.php';

/**
 * Capture the public gluestack-ui component surface into a deterministic file.
 *
 * Usage:
 *   php tools/capture-upstream.php /path/to/gluestack-ui
 */

$root = $argv[1] ?? null;

if (!is_string($root) || $root === '') {
    fwrite(STDERR, "Pass the gluestack-ui checkout path.\n");
    exit(2);
}

$root = realpath($root);
$componentsRoot = $root === false ? false : realpath($root.'/src/components/ui');

if ($root === false || $componentsRoot === false || !is_dir($componentsRoot)) {
    fwrite(STDERR, "The checkout does not contain src/components/ui.\n");
    exit(2);
}

$commit = trim((string) shell_exec(
    'git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null',
));

if (preg_match('/^[a-f0-9]{40}$/D', $commit) !== 1) {
    fwrite(STDERR, "Cannot resolve the upstream commit.\n");
    exit(2);
}

$modules = [];
$directories = glob($componentsRoot.'/*', GLOB_ONLYDIR);

if ($directories === false) {
    throw new RuntimeException('Cannot enumerate upstream component modules.');
}

sort($directories, SORT_STRING);

foreach ($directories as $directory) {
    $sources = glob($directory.'/*.tsx');
    $source = '';

    if ($sources !== false) {
        sort($sources, SORT_STRING);

        foreach ($sources as $path) {
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException("Cannot read {$path}.");
            }

            $source .= "\n".$contents;
        }
    }

    $exports = [];

    preg_match_all('/export\s*\{([^}]+)\}/s', $source, $exportBlocks);

    foreach ($exportBlocks[1] ?? [] as $block) {
        foreach (explode(',', $block) as $candidate) {
            $segments = preg_split('/\s+as\s+/', trim($candidate));
            $name = trim((string) end($segments));

            if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name) === 1) {
                $exports[$name] = true;
            }
        }
    }

    preg_match_all(
        '/export\s+(?:const|function|class)\s+([A-Za-z][A-Za-z0-9]*)/',
        $source,
        $namedExports,
    );

    foreach ($namedExports[1] ?? [] as $name) {
        $exports[$name] = true;
    }

    $variants = [];
    preg_match_all('/variants\s*:\s*\{([\s\S]*?)\n\s*\}\s*,?\n\s*\}/', $source, $variantBlocks);

    foreach ($variantBlocks[1] ?? [] as $block) {
        preg_match_all(
            '/^\s{4,8}([A-Za-z][A-Za-z0-9]*|[\'"][^\'"]+[\'"])\s*:\s*\{/m',
            $block,
            $variantNames,
        );

        foreach ($variantNames[1] ?? [] as $name) {
            $variants[trim($name, "'\"")] = true;
        }
    }

    $examples = glob($directory.'/examples/*/meta.json');

    $modules[] = [
        'name' => basename($directory),
        'exports' => array_keys($exports),
        'variants' => array_keys($variants),
        'examples' => $examples === false ? 0 : count($examples),
    ];
}

$license = $root.'/packages/gluestack-ui/LICENSE';
$payload = [
    'repository' => 'https://github.com/gluestack/gluestack-ui',
    'commit' => $commit,
    'packageVersion' => '5.0.3',
    'capturedAt' => '2026-07-23',
    'license' => [
        'spdx' => 'MIT',
        'path' => 'packages/gluestack-ui/LICENSE',
        'sha256' => is_file($license) ? hash_file('sha256', $license) : null,
    ],
    'modules' => $modules,
];
$encoded = json_encode(
    $payload,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";
$target = dirname(__DIR__).'/resources/upstream-components.json';

if (file_put_contents($target, $encoded, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write {$target}.");
}

$iconSource = file_get_contents($componentsRoot.'/icon/index.tsx');

if ($iconSource === false) {
    throw new RuntimeException('Cannot read the upstream icon source.');
}

preg_match_all(
    '/const\s+([A-Za-z][A-Za-z0-9]*Icon)\s*=\s*createIcon\(\{([\s\S]*?)\n\}\);/',
    $iconSource,
    $iconBlocks,
    PREG_SET_ORDER,
);
$icons = [];

foreach ($iconBlocks as $block) {
    preg_match_all('/\bd="([^"]+)"/', $block[2], $paths);

    if (($paths[1] ?? []) !== []) {
        $icons[$block[1]] = array_values($paths[1]);
    }
}

ksort($icons, SORT_STRING);
$iconTarget = dirname(__DIR__).'/resources/icons.json';
$iconPayload = json_encode(
    [
        'referenceCommit' => $commit,
        'viewBox' => [0, 0, 24, 24],
        'icons' => $icons,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";

if (file_put_contents($iconTarget, $iconPayload, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write {$iconTarget}.");
}

$styles = [];

foreach ($directories as $directory) {
    $sources = glob($directory.'/*.tsx');

    if ($sources === false) {
        continue;
    }

    sort($sources, SORT_STRING);

    foreach ($sources as $path) {
        $styleSource = file_get_contents($path);

        if ($styleSource === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        preg_match_all(
            '/(?:export\s+)?const\s+([A-Za-z][A-Za-z0-9]*Style)\s*=\s*tva\(\{/',
            $styleSource,
            $starts,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($starts[0] ?? [] as $index => $startMatch) {
            $name = $starts[1][$index][0];
            $opening = $startMatch[1] + strlen($startMatch[0]) - 1;
            $depth = 0;
            $quote = null;
            $escaped = false;
            $end = null;
            $length = strlen($styleSource);

            for ($position = $opening; $position < $length; $position++) {
                $character = $styleSource[$position];

                if ($quote !== null) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($character === '\\') {
                        $escaped = true;
                    } elseif ($character === $quote) {
                        $quote = null;
                    }

                    continue;
                }

                if ($character === "'" || $character === '"' || $character === '`') {
                    $quote = $character;
                    continue;
                }

                if ($character === '{') {
                    $depth++;
                } elseif ($character === '}' && --$depth === 0) {
                    $end = $position;
                    break;
                }
            }

            if ($end === null) {
                throw new RuntimeException("Cannot parse {$name} in {$path}.");
            }

            $block = substr($styleSource, $opening, $end - $opening + 1);
            $recipe = (new JavaScriptObjectParser($block))->parse();
            $base = is_string($recipe['base'] ?? null) ? $recipe['base'] : '';
            $variants = is_array($recipe['variants'] ?? null) ? $recipe['variants'] : [];
            $parentVariants = is_array($recipe['parentVariants'] ?? null)
                ? $recipe['parentVariants']
                : [];
            $defaultVariants = is_array($recipe['defaultVariants'] ?? null)
                ? $recipe['defaultVariants']
                : [];
            $compoundVariants = is_array($recipe['compoundVariants'] ?? null)
                ? $recipe['compoundVariants']
                : [];
            $parentCompoundVariants = is_array($recipe['parentCompoundVariants'] ?? null)
                ? $recipe['parentCompoundVariants']
                : [];
            $classes = [];
            $collectClasses = static function (mixed $value) use (&$classes, &$collectClasses): void {
                if (is_string($value)) {
                    $classes[] = $value;
                    return;
                }
                if (is_array($value)) {
                    foreach ($value as $child) {
                        $collectClasses($child);
                    }
                }
            };
            foreach ([$base, $variants, $parentVariants] as $classSource) {
                $collectClasses($classSource);
            }
            foreach ([$compoundVariants, $parentCompoundVariants] as $rules) {
                foreach ($rules as $rule) {
                    if (is_array($rule) && is_string($rule['class'] ?? null)) {
                        $classes[] = $rule['class'];
                    }
                }
            }
            $classes = array_values(array_unique($classes));
            $styles[] = [
                'module' => basename($directory),
                'source' => substr($path, strlen($root) + 1),
                'name' => $name,
                'base' => $base,
                'variants' => $variants,
                'parentVariants' => $parentVariants,
                'defaultVariants' => $defaultVariants,
                'compoundVariants' => $compoundVariants,
                'parentCompoundVariants' => $parentCompoundVariants,
                'classes' => $classes,
            ];
        }
    }
}

usort(
    $styles,
    static fn (array $left, array $right): int => [
        $left['module'],
        $left['name'],
        $left['source'],
    ] <=> [
        $right['module'],
        $right['name'],
        $right['source'],
    ],
);
$styleTarget = dirname(__DIR__).'/resources/styles.json';
$stylePayload = json_encode(
    [
        'referenceCommit' => $commit,
        'styles' => $styles,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";

if (file_put_contents($styleTarget, $stylePayload, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write {$styleTarget}.");
}

fwrite(
    STDOUT,
    sprintf(
        "Captured %d modules, %d icons, and %d style definitions at %s.\n",
        count($modules),
        count($icons),
        count($styles),
        $commit,
    ),
);
