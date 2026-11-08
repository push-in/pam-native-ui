<?php

declare(strict_types=1);

namespace Pam\MobileUi\Support;

use LogicException;

/**
 * Components backed by an optional PAM Native plugin fail with the exact
 * Composer command instead of an unknown-class error when it is missing.
 */
final class OptionalPlugin
{
    private function __construct()
    {
    }

    /** @param class-string $class */
    public static function require(string $class, string $package, string $feature): void
    {
        if (!class_exists($class)) {
            throw new LogicException(sprintf(
                '%s needs the optional %s plugin: composer require %s',
                $feature,
                $package,
                $package,
            ));
        }
    }
}
