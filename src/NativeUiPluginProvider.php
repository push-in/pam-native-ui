<?php

declare(strict_types=1);

namespace Pam\NativeUi;

use Pam\Native\Plugin\PluginProvider;

/**
 * The components themselves are single-file `.pam` components registered by
 * PAM Native from the `components` directory declared in
 * pam-native.plugin.json; the provider only keeps the 1.x entry points alive.
 */
final class NativeUiPluginProvider implements PluginProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
