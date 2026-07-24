<?php

declare(strict_types=1);

/**
 * Captures static className fragments that live outside tva() recipes.
 *
 * Usage:
 *   php tools/import-style-reference.php /path/to/reference-ui
 */

$checkout = $argv[1] ?? null;
if (!is_string($checkout) || $checkout === '') {
    fwrite(STDERR, "Pass the reference UI checkout path.\n");
    exit(2);
}

$root = realpath($checkout);
$components = $root === false ? false : realpath($root.'/src/components/ui');
if ($root === false || $components === false) {
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

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($components, FilesystemIterator::SKIP_DOTS),
);
$entries = [];

/** @param array<string, true> $classes */
$capture = static function (string $value, array &$classes): void {
    $value = trim((string) preg_replace('/\s+/', ' ', $value));
    $tokens = array_values(array_filter(
        preg_split('/\s+/', $value) ?: [],
        static fn (string $token): bool =>
            preg_match('/^-?[A-Za-z0-9_#.%:\/,\[\]-]+$/D', $token) === 1,
    ));
    $value = implode(' ', $tokens);
    if ($value !== '') {
        $classes[$value] = true;
    }
};

foreach ($iterator as $file) {
    if (
        !$file instanceof SplFileInfo
        || !$file->isFile()
        || $file->getExtension() !== 'tsx'
        || str_ends_with($file->getFilename(), '.web.tsx')
    ) {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if ($source === false || !str_contains($source, 'className')) {
        continue;
    }
    $classes = [];

    preg_match_all(
        '/className\s*=\s*(["\'])(.*?)\1/s',
        $source,
        $quoted,
    );
    foreach ($quoted[2] ?? [] as $value) {
        $capture($value, $classes);
    }

    preg_match_all(
        '/className\s*=\s*\{\s*(["\'])(.*?)\1\s*\}/s',
        $source,
        $expressionStrings,
    );
    foreach ($expressionStrings[2] ?? [] as $value) {
        $capture($value, $classes);
    }

    preg_match_all(
        '/className\s*(?:=|:)\s*\{\s*`(.*?)`\s*\}/s',
        $source,
        $templates,
    );
    foreach ($templates[1] ?? [] as $template) {
        $static = preg_replace('/\$\{.*?\}/s', ' ', $template);
        if (is_string($static)) {
            $capture($static, $classes);
        }
        preg_match_all('/(?:\?|:)\s*(["\'])(.*?)\1/s', $template, $conditionalStrings);
        foreach ($conditionalStrings[2] ?? [] as $value) {
            $capture($value, $classes);
        }
    }

    preg_match_all(
        '/className\s*=\s*\{([^{}\n]{1,500})\}/',
        $source,
        $expressions,
    );
    foreach ($expressions[1] ?? [] as $expression) {
        preg_match_all('/(["\'])(.*?)\1/s', $expression, $conditionalStrings);
        foreach ($conditionalStrings[2] ?? [] as $value) {
            $capture($value, $classes);
        }
    }

    if ($classes !== []) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $module = explode('/', substr($relative, strlen('src/components/ui/')))[0];
        $entries[] = [
            'module' => $module,
            'source' => $relative,
            'classes' => array_keys($classes),
        ];
    }
}

usort(
    $entries,
    static fn (array $left, array $right): int =>
        [$left['module'], $left['source']] <=> [$right['module'], $right['source']],
);

$target = dirname(__DIR__).'/resources/inline-classes.json';
$payload = json_encode(
    [
        'referenceCommit' => $commit,
        'sources' => $entries,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";

if (file_put_contents($target, $payload, LOCK_EX) === false) {
    throw new RuntimeException("Cannot write {$target}.");
}

fwrite(
    STDOUT,
    sprintf("Captured inline classes from %d mobile sources at %s.\n", count($entries), $commit),
);
