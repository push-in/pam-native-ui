<?php

declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/examples/kitchen-sink/src/ComponentRoute.php';

(static function (): void {
    $route = new \App\ComponentRoute('p-swipe-actions', 'Swipe Actions', \Pam\MobileUi\Generated\MaterialComponentMap::TAGS['p-swipe-actions']);
    $method = new ReflectionMethod($route, 'swipeActionHint');
    foreach ([
        [[], 'Swipe horizontally or use the action buttons'],
        [['disabled' => true], 'Actions unavailable for this item'],
        [['isDisabled' => true], 'Actions unavailable for this item'],
        [['readonly' => true], 'Read only — this item cannot be changed'],
        [['readOnly' => true], 'Read only — this item cannot be changed'],
        [['isReadOnly' => true], 'Read only — this item cannot be changed'],
        [['loading' => true], 'Synchronizing — actions temporarily unavailable'],
        [['isLoading' => true], 'Synchronizing — actions temporarily unavailable'],
        [['disabled' => false, 'isDisabled' => true], 'Swipe horizontally or use the action buttons'],
        [['readonly' => false, 'isReadOnly' => true], 'Swipe horizontally or use the action buttons'],
        [['loading' => false, 'isLoading' => true], 'Swipe horizontally or use the action buttons'],
    ] as [$props, $expected]) {
        if ($method->invoke($route, $props) !== $expected) {
            throw new RuntimeException('Showcase instructions must match available swipe interactions.');
        }
    }
})();
