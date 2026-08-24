<?php

declare(strict_types=1);

$pamNativeRoot = getenv('PAM_NATIVE_ROOT');
if (!is_string($pamNativeRoot) || $pamNativeRoot === '') {
    $pamNativeRoot = dirname(__DIR__, 2).'/pam-native';
}
$pamNativeSource = is_dir($pamNativeRoot.'/src')
    ? $pamNativeRoot.'/src/'
    : $pamNativeRoot.'/packages/native/src/';

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
