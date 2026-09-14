<?php

declare(strict_types=1);

use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\EventKind;
use Pam\Native\PropKey;

require_once __DIR__.'/bootstrap.php';

(static function (): void {
    $treeClass = MaterialComponentMap::TAGS['p-treeview'];
    $assertCustomSelection = static function (mixed $actual, mixed $expected, string $message): void {
        if ($actual !== $expected) {
            throw new RuntimeException($message);
        }
    };
    foreach ([
        [['alpha'], 'beta', ['alpha', 'beta']],
        [['alpha', 'beta'], 'alpha', ['beta']],
    ] as [$treeSelection, $treePressed, $expectedTreeSelection]) {
        $treeSelectionResult = null;
        $multipleTree = $treeClass::make([
            'multiple' => true, 'modelValue' => $treeSelection,
            'items' => [['title' => 'Alpha', 'value' => 'alpha'], ['title' => 'Beta', 'value' => 'beta']],
        ])->onChange(static function (array $next) use (&$treeSelectionResult): void {
            $treeSelectionResult = $next;
        })->toElement();
        $multipleTreePayload = $multipleTree->properties()[PropKey::HostProperties->value] ?? null;
        if (!$multipleTreePayload instanceof BinaryValue
            || (Wire::decodeMap($multipleTreePayload->bytes)['selectedPaths'] ?? null) !== implode("\n", $treeSelection)) {
            throw new RuntimeException('Multiple Treeview must transmit every selected path to its native host.');
        }
        $multipleTree->events()[EventKind::Change->value]($treePressed);
        $assertCustomSelection($treeSelectionResult, $expectedTreeSelection,
            'Multiple Treeview must emit additive/removal arrays, never replace selection with a scalar.');
    }
    foreach ([
        [[], true, ['mobile']],
        [['mobile', 'docs'], false, ['docs']],
        [['mobile', 'docs'], true, ['docs', 'mobile']],
    ] as [$openedTreePaths, $expandTreePath, $expectedOpenedPaths]) {
        $openedResult = null;
        $rawTreeEvents = [];
        $controlledTree = $treeClass::make(['opened' => $openedTreePaths])
            ->onToggle(static function (array $next) use (&$openedResult): void { $openedResult = $next; })
            ->on(EventKind::Native, static function (string $raw) use (&$rawTreeEvents): void { $rawTreeEvents[] = $raw; })
            ->toElement();
        $treeNative = $controlledTree->events()[EventKind::Native->value];
        $treeNative(Wire::map([
            'action' => \Pam\MobileUi\Enum\FileTreeAction::Expanded->value,
            'path' => 'mobile', 'expanded' => $expandTreePath,
        ]));
        $assertCustomSelection($openedResult, $expectedOpenedPaths, 'Treeview must forward expansion into its complete controlled opened set.');
        $treeNative('invalid map');
        $treeNative(Wire::map(['path' => 'mobile', 'expanded' => true]));
        $assertCustomSelection($openedResult, $expectedOpenedPaths, 'Malformed tree events must not mutate controlled expansion.');
        $assertCustomSelection(count($rawTreeEvents), 3, 'Treeview must retain the authored raw native event handler.');
    }
})();
