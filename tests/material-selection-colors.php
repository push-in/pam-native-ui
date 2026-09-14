<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Rendering\ComponentRenderer;
use Pam\MobileUi\Theme\ThemeManager;

(static function (): void {
    $method = new ReflectionMethod(ComponentRenderer::class, 'nativeProperties');
    $originalMode = ThemeManager::configuredMode();
    try {
        foreach ([ThemeMode::Light, ThemeMode::Dark] as $mode) {
            ThemeManager::mode($mode);
            foreach ([NativeBehavior::Checkbox, NativeBehavior::Radio] as $behavior) {
                foreach ([null, 0xFF516279] as $override) {
                    $props = $override === null ? [] : ['trackColor' => $override];
                    $values = $method->invoke(null, $behavior === NativeBehavior::Checkbox ? 'Checkbox' : 'Radio', $behavior, $props, [], null);
                    if (!is_array($values)
                        || ($values['trackColor'] ?? null) !== ($override ?? ThemeManager::current()->color(ColorToken::Outline))) {
                        throw new RuntimeException('Selection outlines must use Outline while preserving explicit track colors.');
                    }
                }
            }
            $progress = $method->invoke(null, 'Progress', NativeBehavior::Progress, [], [], null);
            if (!is_array($progress)
                || ($progress['trackColor'] ?? null) !== ThemeManager::current()->color(ColorToken::Muted)) {
                throw new RuntimeException('Selection contrast must not recolor progress tracks.');
            }
        }
    } finally {
        ThemeManager::mode($originalMode);
    }
})();
