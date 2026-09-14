<?php

declare(strict_types=1);

use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\AccessibilityImportance;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\PropKey;

require_once __DIR__.'/bootstrap.php';

(static function (): void {
    $tags = MaterialComponentMap::TAGS;
    foreach (['p-chip', 'p-alert'] as $tag) {
        foreach ([null, 'disabled', 'isDisabled', 'readonly', 'readOnly', 'isReadOnly', 'loading', 'isLoading'] as $lock) {
            $closed = false;
            $props = ['text' => 'Protected content', 'closable' => true, 'closeLabel' => 'Close specimen'];
            if ($lock !== null) $props[$lock] = true;
            $stack = [$tags[$tag]::make($props)->onClose(static function () use (&$closed): void {
                $closed = true;
            })->toElement()];
            $found = false;
            while ($stack !== []) {
                $node = array_pop($stack);
                if (($node->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Close specimen') {
                    $found = true;
                    $press = $node->events()[\Pam\Native\EventKind::Press->value] ?? null;
                    if (($node->properties()[PropKey::Enabled->value] ?? true) !== ($lock === null)
                        || ($press instanceof Closure) !== ($lock === null)) {
                        throw new RuntimeException($tag.' close action must mirror its blocked state.');
                    }
                    $press?->__invoke();
                    if ($closed !== ($lock === null)) {
                        throw new RuntimeException($tag.' must preserve active close callbacks and reject blocked ones.');
                    }
                }
                array_push($stack, ...$node->children());
            }
            if (!$found) throw new RuntimeException('Missing close action for '.$tag);
        }
    }
    foreach ([false, true] as $revealed) {
        $localizedPassword = $tags['p-password-field']::make([
            'modelValue' => 'secret', 'revealed' => $revealed,
            'showLabel' => 'Mostrar senha', 'hideLabel' => 'Ocultar senha',
        ])->toElement();
        $stack = [$localizedPassword];
        $found = false;
        while ($stack !== []) {
            $node = array_pop($stack);
            if (($node->properties()[PropKey::AccessibilityLabel->value] ?? null)
                === ($revealed ? 'Ocultar senha' : 'Mostrar senha')) {
                $icon = $node->children()[0] ?? null;
                $iconProps = $icon?->properties() ?? [];
                $host = $iconProps[PropKey::HostProperties->value] ?? null;
                $expected = \Pam\MobileUi\Generated\ComponentMap::IDS[$revealed ? 'EyeOffIcon' : 'EyeIcon'];
                if (!$host instanceof BinaryValue
                    || (Wire::decodeMap($host->bytes)['icon'] ?? null) !== $expected
                    || ($iconProps[PropKey::Width->value] ?? null) !== 20.0
                    || ($iconProps[PropKey::Height->value] ?? null) !== 20.0
                    || ($iconProps[PropKey::AccessibilityImportance->value] ?? null)
                        !== AccessibilityImportance::NoHideDescendants->value) {
                    throw new RuntimeException('Password actions must use decorative state-specific vector icons and localized labels.');
                }
                $found = true;
            }
            array_push($stack, ...$node->children());
        }
        if (!$found) {
            throw new RuntimeException('Password visibility labels must be customizable in both states.');
        }
    }
    foreach (['p-text-field', 'p-textarea', 'p-password-field', 'p-masked-field', 'p-currency-field'] as $clearTag) {
        $stack = [$tags[$clearTag]::make([
            'modelValue' => '123', 'clearable' => true, 'clearLabel' => 'Limpar campo',
        ])->toElement()];
        $found = false;
        while ($stack !== []) {
            $node = array_pop($stack);
            if (($node->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Limpar campo') {
                $icon = $node->children()[0] ?? null;
                $host = $icon?->properties()[PropKey::HostProperties->value] ?? null;
                if (!$host instanceof BinaryValue || (Wire::decodeMap($host->bytes)['icon'] ?? null)
                    !== \Pam\MobileUi\Generated\ComponentMap::IDS['CloseIcon']) {
                    throw new RuntimeException($clearTag.' must use the shared vector clear icon, not a font glyph.');
                }
                $found = true;
            }
            array_push($stack, ...$node->children());
        }
        if (!$found) {
            throw new RuntimeException($clearTag.' must retain its localized clear action.');
        }
    }
})();
