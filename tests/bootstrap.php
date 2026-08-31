<?php

declare(strict_types=1);

$configuredRoot = getenv('PAM_NATIVE_ROOT');
$candidateRoots = [];
if (is_string($configuredRoot) && $configuredRoot !== '') {
    $candidateRoots[] = $configuredRoot;
}
$candidateRoots[] = dirname(__DIR__).'/vendor/pushinbr/pam-native';
$candidateRoots[] = dirname(__DIR__, 2).'/pam-native';
$candidateRoots[] = dirname(__DIR__, 2).'/pam-native-candidate';

$pamNativeSource = null;
foreach ($candidateRoots as $candidateRoot) {
    foreach ([$candidateRoot.'/src/', $candidateRoot.'/packages/native/src/'] as $candidateSource) {
        if (is_file($candidateSource.'App.php')) {
            $pamNativeSource = $candidateSource;
            break 2;
        }
    }
}

if ($pamNativeSource === null) {
    throw new RuntimeException(
        'Cannot locate the PAM Native PHP SDK in any supported project or Composer layout.',
    );
}

$roots = [
    'Pam\\MobileUi\\' => dirname(__DIR__).'/src/',
    'Pam\\Native\\' => $pamNativeSource,
];

spl_autoload_register(
    static function (string $class) use ($roots): void {
        foreach ($roots as $prefix => $root) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $path = $root.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    },
);

require_once __DIR__.'/fixtures/InternalComponentFacades.php';
require_once dirname(__DIR__).'/src/Generated/MaterialComponentFacades.php';
