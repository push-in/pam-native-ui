<?php

declare(strict_types=1);

use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\Element;
use Pam\Native\PropKey;

(static function (): void {
    foreach ([false, true] as $expanded) {
        $tree = MaterialComponentMap::TAGS['p-treeview']::make([
            'items' => [['title' => 'Folder', 'value' => 'folder', 'children' => [
                ['title' => 'Child', 'value' => 'child'],
            ]]],
            'opened' => $expanded ? ['folder'] : [],
        ])->onToggle(static function (mixed $paths): void {})->toElement();
        $contents = [];
        $visit = static function (Element $element) use (&$visit, &$contents): void {
            if (($element->properties()[PropKey::Value->value] ?? null) === 'pam:file-tree-content') {
                $contents[] = $element;
            }
            foreach ($element->children() as $child) {
                $visit($child);
            }
        };
        $visit($tree);
        if (count($contents) !== 1
            || ($contents[0]->properties()[PropKey::Visible->value] ?? true) !== $expanded) {
            throw new RuntimeException('Controlled tree expansion must update engine layout visibility.');
        }
    }
})();
