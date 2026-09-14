<?php

declare(strict_types=1);

use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\EventKind;
use Pam\Native\PropKey;

require_once __DIR__.'/bootstrap.php';

(static function (): void {
    foreach (['p-data-table', 'p-data-table-virtual', 'p-data-grid'] as $tag) {
        foreach ([['density' => 'compact'], ['rowHeight' => 24.0], ['itemHeight' => 32.0]] as $sizing) {
            $stack = [MaterialComponentMap::TAGS[$tag]::make($sizing + [
                'showSelect' => true,
                'headers' => [['key' => 'name', 'title' => 'Name']],
                'items' => [['id' => 1, 'name' => 'Ada']],
            ])->onChange(static function (): void {})->toElement()];
            $count = 0;
            while ($stack !== []) {
                $node = array_pop($stack);
                if (($node->properties()[PropKey::AccessibilityRole->value] ?? null)
                    === \Pam\Native\AccessibilityRole::Checkbox->value) {
                    $count++;
                    if (($node->properties()[PropKey::Height->value] ?? 0.0) < 48.0) {
                        throw new RuntimeException($tag.' selection targets must be at least 48 dp tall.');
                    }
                }
                array_push($stack, ...$node->children());
            }
            if ($count !== 2) throw new RuntimeException('Expected header and row checkboxes for '.$tag);
        }
    }
    foreach (['disabled', 'isDisabled', 'readonly', 'readOnly', 'isReadOnly', 'loading', 'isLoading'] as $lock) {
        foreach ([false, true] as $blocked) {
            $props = [$lock => $blocked, 'items' => ['First', 'Last']];
            $reorder = MaterialComponentMap::TAGS['p-reorderable-list']::make($props)
                ->onReorder(static function (array $items): void {})->toElement();
            $region = $reorder->children()[0]->children()[0];
            $move = $reorder->children()[0]->children()[2];
            foreach ([PropKey::Enabled, PropKey::Draggable, PropKey::DropEnabled] as $key) {
                if (($region->properties()[$key->value] ?? null) !== !$blocked) {
                    throw new RuntimeException('Reorder region ignored '.$lock);
                }
            }
            if (isset($region->events()[EventKind::Drop->value]) !== !$blocked
                || isset($move->events()[EventKind::Press->value]) !== !$blocked
                || ($move->properties()[PropKey::Enabled->value] ?? null) !== !$blocked) {
                throw new RuntimeException('Reorder mutation handlers ignored '.$lock);
            }
            $swipe = MaterialComponentMap::TAGS['p-swipe-actions']::make($props)
                ->onAction(static function (string $action): void {})->toElement();
            $gesture = $swipe->children()[1];
            foreach ([PropKey::Enabled, PropKey::GestureEnabled, PropKey::GestureNativeTransform] as $key) {
                if (($gesture->properties()[$key->value] ?? null) !== !$blocked) {
                    throw new RuntimeException('Swipe gesture ignored '.$lock);
                }
            }
            if (isset($gesture->events()[EventKind::GestureEnd->value]) !== !$blocked) {
                throw new RuntimeException('Swipe completion ignored '.$lock);
            }
            foreach ($swipe->children()[2]->children() as $control) {
                if (($control->properties()[PropKey::Enabled->value] ?? null) !== !$blocked
                    || isset($control->events()[EventKind::Press->value]) !== !$blocked) {
                    throw new RuntimeException('Swipe action button ignored '.$lock);
                }
            }
        }
    }
})();
