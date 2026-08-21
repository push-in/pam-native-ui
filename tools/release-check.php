<?php

declare(strict_types=1);

use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\MaterialComponentMap;

require dirname(__DIR__).'/tests/bootstrap.php';

$root = dirname(__DIR__);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

/**
 * @return array<string, mixed>
 */
$json = static function (string $path): array {
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Cannot read {$path}.");
    }
    $value = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($value)) {
        throw new RuntimeException("Expected a JSON object in {$path}.");
    }

    return $value;
};

$composer = $json($root.'/composer.json');
$plugin = $json($root.'/pam-native.plugin.json');
$exampleComposer = $json($root.'/examples/kitchen-sink/composer.json');
$parity = $json($root.'/resources/parity.json');
$materialParity = $json($root.'/resources/material-parity.json');
$ciVerifiedGates = [];
if (getenv('CI') === 'true') {
    $rawVerifiedGates = getenv('PAM_CI_VERIFIED_GATES');
    if (is_string($rawVerifiedGates)) {
        foreach (explode(',', $rawVerifiedGates) as $gate) {
            $gate = trim($gate);
            if ($gate !== '') {
                $ciVerifiedGates[$gate] = true;
            }
        }
    }
}

$assert(
    ($composer['name'] ?? null) === 'pushinbr/pam-native-ui',
    'The package must be named pushinbr/pam-native-ui.',
);
$assert(
    ($composer['require']['pushinbr/pam-native'] ?? null) === '^0.6.1',
    'The package must require pushinbr/pam-native:^0.6.1.',
);
$assert(
    ($composer['type'] ?? null) === 'pam-native-plugin',
    'Composer type must remain pam-native-plugin for autolinking.',
);
$assert(
    ($plugin['$schema'] ?? null)
        === 'vendor/pushinbr/pam-native/resources/pam-native.plugin.schema.json',
    'The plugin schema must resolve through the pushinbr/pam-native package.',
);
$assert(
    ($plugin['pamNative']['minimum'] ?? null) === '0.6.1'
        && ($plugin['pamNative']['maximumExclusive'] ?? null) === '1.0.0',
    'The plugin must support the PAM Native 0.6.x line.',
);
$assert(
    ($exampleComposer['require']['pushinbr/pam-native-ui'] ?? null) === '0.6.x-dev'
        && ($exampleComposer['require']['pushinbr/pam-native'] ?? null) === '0.6.x-dev',
    'The kitchen sink must exercise both public 0.6.x package lines.',
);

$reference = $parity['reference'] ?? null;
$modules = $parity['modules'] ?? null;
$assert(is_array($reference), 'Parity reference is missing.');
$assert(is_array($modules), 'Parity modules are missing.');

/** @var list<array<string, mixed>> $moduleList */
$moduleList = is_array($modules) ? array_values($modules) : [];
$referenceModuleCount = is_array($reference)
    ? ($reference['moduleCount'] ?? null)
    : null;
$referenceFacadeCount = is_array($reference)
    ? ($reference['facadeCount'] ?? null)
    : null;

$assert(
    $referenceModuleCount === count($moduleList),
    'The pinned module count does not match the parity inventory.',
);
$assert(
    $referenceFacadeCount === count(ComponentMap::TAGS),
    'The pinned facade count does not match the generated PHP API.',
);
$materialReference = $materialParity['reference'] ?? [];
$materialModules = $materialParity['modules'] ?? [];
$assert(
    is_array($materialReference)
        && ($materialReference['metadataImport'] ?? null) === false
        && ($materialReference['namespace'] ?? null) === 'p-*',
    'Material parity must be authored manually and expose only p-* tags.',
);
$assert(
    is_array($materialModules)
        && ($materialReference['moduleCount'] ?? null) === count($materialModules)
        && count($materialModules) === count(MaterialComponentMap::MODULES),
    'Material parity module count must match the generated component map.',
);
$materialTags = [];
foreach (is_array($materialModules) ? $materialModules : [] as $module) {
    if (!is_array($module)) {
        $assert(false, 'Every Material parity module must be an object.');
        continue;
    }
    foreach (is_array($module['components'] ?? null) ? $module['components'] : [] as $tag) {
        if (is_string($tag)) {
            $materialTags[$tag] = true;
        }
    }
}
$assert(
    ($materialReference['componentCount'] ?? null) === count($materialTags)
        && array_keys($materialTags) === array_keys(MaterialComponentMap::TAGS),
    'Material parity components must exactly match the generated p-* API.',
);
$materialGateNames = [];
foreach ($materialParity['gateDefinitions'] ?? [] as $gate) {
    if (
        is_array($gate)
        && is_int($gate['id'] ?? null)
        && is_string($gate['name'] ?? null)
    ) {
        $materialGateNames[$gate['id']] = $gate['name'];
    }
}
$materialPendingByGate = [];
foreach (is_array($materialModules) ? $materialModules : [] as $module) {
    if (!is_array($module)) {
        $assert(false, 'Every Material parity module must be an object.');
        continue;
    }
    $verification = $module['verification'] ?? null;
    $assert(
        is_array($verification)
            && count($verification) === count($materialGateNames),
        'Every Material module must cover every verification gate.',
    );
    foreach (is_array($verification) ? $verification : [] as $index => $status) {
        if ($status === 1 || $status === 2) {
            $gateName = $materialGateNames[$index + 1] ?? 'unknown';
            $materialPendingByGate[$gateName] =
                ($materialPendingByGate[$gateName] ?? 0) + 1;
        }
    }
}
foreach ($materialPendingByGate as $pendingGate => $pendingCount) {
    if (isset($ciVerifiedGates[$pendingGate])) {
        unset($materialPendingByGate[$pendingGate]);
        continue;
    }
    $assert(
        false,
        "Material release gate {$pendingGate} is not verified for "
            .$pendingCount.' modules.',
    );
}
$componentIds = array_values(ComponentMap::IDS);
sort($componentIds, SORT_NUMERIC);
$assert(
    $componentIds === range(1, count(ComponentMap::IDS)),
    'Generated component IDs must be sequential integers.',
);

$moduleIds = [];
$moduleNames = [];
$statusCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$pendingByGate = [];
$gateNames = [];

foreach (($parity['definitions']['gates'] ?? []) as $gate) {
    if (
        is_array($gate)
        && is_int($gate['id'] ?? null)
        && is_string($gate['name'] ?? null)
    ) {
        $gateNames[$gate['id']] = $gate['name'];
    }
}

foreach ($moduleList as $module) {
    $id = $module['id'] ?? null;
    $name = $module['name'] ?? null;
    $moduleLabel = is_string($name) ? $name : '<invalid>';
    $verification = $module['verification'] ?? null;
    $assert(is_int($id), 'Every parity module must have an integer ID.');
    $assert(is_string($name), 'Every parity module must have a string name.');
    if (is_int($id)) {
        $moduleIds[] = $id;
    }
    if (is_string($name)) {
        $moduleNames[] = $name;
        $assert(
            array_key_exists($name, ComponentMap::MODULES),
            "Parity module {$name} is absent from the generated module map.",
        );
    }
    $assert(
        is_array($verification) && count($verification) === count($gateNames),
        "Parity module {$moduleLabel} must contain one status for every release gate.",
    );
    if (!is_array($verification)) {
        continue;
    }
    foreach (array_values($verification) as $index => $status) {
        $assert(
            is_int($status) && $status >= 1 && $status <= 4,
            "Parity module {$moduleLabel} contains an invalid verification status.",
        );
        if (!is_int($status) || !isset($statusCounts[$status])) {
            continue;
        }
        $statusCounts[$status]++;
        if ($status === 1 || $status === 2) {
            $gate = $gateNames[$index + 1] ?? 'gate-'.($index + 1);
            $pendingByGate[$gate] = ($pendingByGate[$gate] ?? 0) + 1;
        }
    }
}

$assert(
    $moduleIds === range(1, count($moduleList)),
    'Parity module IDs must be sequential integers.',
);
$assert(
    count(array_unique($moduleNames)) === count($moduleNames),
    'Parity module names must be unique.',
);

foreach (array_keys($pendingByGate) as $pendingGate) {
    if (isset($ciVerifiedGates[$pendingGate])) {
        unset($pendingByGate[$pendingGate]);
        continue;
    }
    $assert(
        false,
        "Release gate {$pendingGate} is not verified for "
            .$pendingByGate[$pendingGate].' material modules.',
    );
}

$catalog = file_get_contents($root.'/docs/catalog.md');
$readme = file_get_contents($root.'/README.md');
$exampleReadme = file_get_contents($root.'/examples/kitchen-sink/README.md');
$assert(is_string($catalog), 'Generated component catalog is missing.');
$assert(is_string($readme), 'Package README is missing.');
$assert(is_string($exampleReadme), 'Kitchen-sink README is missing.');

if (is_string($catalog)) {
    foreach (MaterialComponentMap::MODULES as $moduleName => $moduleTags) {
        $assert(
            str_contains($catalog, "\n## `{$moduleName}`\n"),
            "Material catalog is missing module {$moduleName}.",
        );
        foreach ($moduleTags as $tag) {
            $assert(
                str_contains($catalog, "`<{$tag} />`"),
                "Material catalog is missing tag {$tag}.",
            );
        }
    }
}

foreach ([
    $root.'/composer.json',
    $root.'/pam-native.plugin.json',
    $root.'/README.md',
    $root.'/CONTRIBUTING.md',
    $root.'/docs/authoring.md',
    $root.'/docs/product-foundations.md',
    $root.'/docs/performance.md',
    $root.'/examples/kitchen-sink/composer.json',
    $root.'/examples/kitchen-sink/README.md',
] as $publicFile) {
    $contents = file_get_contents($publicFile);
    $assert(is_string($contents), "Cannot inspect {$publicFile}.");
    if (!is_string($contents)) {
        continue;
    }
    $assert(
        !str_contains($contents, 'pam/pam-native')
            && !str_contains($contents, 'pam/pam-native-ui')
            && !str_contains($contents, 'composer require pam/'),
        "Legacy Composer package name found in {$publicFile}.",
    );
}

$ecosystemGate = 'push-in/pam/.github/workflows/ecosystem-compatibility.yml@main';
$publicationWorkflow = file_get_contents(
    $root.'/.github/workflows/publication-compatibility.yml',
);
$releaseWorkflow = file_get_contents($root.'/.github/workflows/release.yml');
$composerWorkflow = file_get_contents($root.'/.github/workflows/composer-package.yml');
foreach ([
    'publication compatibility' => $publicationWorkflow,
    'native release' => $releaseWorkflow,
    'Composer distribution' => $composerWorkflow,
] as $workflowName => $workflow) {
    $assert(is_string($workflow), "Cannot inspect {$workflowName} workflow.");
    if (!is_string($workflow)) {
        continue;
    }
    $assert(
        str_contains($workflow, $ecosystemGate),
        "{$workflowName} must invoke the central ecosystem gate.",
    );
    $assert(
        str_contains($workflow, "tags:\n")
            && (
                str_contains($workflow, "- \"v*\"")
                || str_contains($workflow, "- 'v*'")
            ),
        "{$workflowName} must certify version tags.",
    );
}
$assert(
    is_string($releaseWorkflow)
        && substr_count($releaseWorkflow, 'needs: ecosystem-compatibility') >= 1,
    'Native release jobs must wait for ecosystem compatibility.',
);
$assert(
    is_string($composerWorkflow)
        && str_contains($composerWorkflow, 'needs: ecosystem-compatibility'),
    'Composer publication must wait for ecosystem compatibility.',
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "release-check: {$failure}\n");
    }
    exit(1);
}

ksort($pendingByGate, SORT_STRING);
ksort($materialPendingByGate, SORT_STRING);

echo json_encode(
    [
        'package' => $composer['name'],
        'upstreamCommit' => is_array($reference)
            ? ($reference['commit'] ?? null)
            : null,
        'modules' => count($moduleList),
        'facades' => count(ComponentMap::TAGS),
        'material' => [
            'modules' => count($materialModules),
            'components' => count(MaterialComponentMap::TAGS),
            'namespace' => $materialReference['namespace'] ?? null,
            'metadataImport' => $materialReference['metadataImport'] ?? null,
            'targets' => $materialReference['targets'] ?? [],
            'pendingByGate' => $materialPendingByGate,
            'ciVerifiedGates' => array_keys($ciVerifiedGates),
            'releaseReady' => $materialPendingByGate === [],
        ],
        'verification' => [
            'planned' => $statusCounts[1],
            'implemented' => $statusCounts[2],
            'verified' => $statusCounts[3],
            'notApplicable' => $statusCounts[4],
        ],
        'pendingByGate' => $pendingByGate,
        'communityApiReady' => true,
        'comparativePerformanceReady' => $pendingByGate === [],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";
