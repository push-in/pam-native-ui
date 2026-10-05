<?php

declare(strict_types=1);

use Pam\MobileUi\MobileUiPluginProvider;
use Certification\FixtureScreen;
use Pam\Native\Element;
use Pam\Native\PropKey;
use Pam\Native\View;

require dirname(__DIR__, 2).'/tests/bootstrap.php';
require __DIR__.'/app/src/FixtureScreen.php';

(new MobileUiPluginProvider())->register();
View::configure(__DIR__.'/app/resources/native');
$root = (new FixtureScreen())->render()->toElement();
$found = [];
$labels = [];
$visit = static function (Element $node) use (&$visit, &$found, &$labels): void {
    $id = $node->properties()[PropKey::TestId->value] ?? null;
    if (is_string($id) && str_starts_with($id, 'fixture-')) {
        $found[$id] = true;
    }
    $label = $node->properties()[PropKey::AccessibilityLabel->value] ?? null;
    if (is_string($label)) {
        $labels[$label] = true;
    }
    foreach ($node->children() as $child) {
        $visit($child);
    }
};
$visit($root);

foreach (['fixture-title', 'fixture-autocomplete', 'fixture-combobox', 'fixture-otp'] as $id) {
    if (!isset($found[$id])) {
        throw new RuntimeException("Missing compiled public fixture {$id}.");
    }
}
foreach (['Component', 'Dialog', 'Capability', 'Realtime', 'Verification code'] as $label) {
    if (!isset($labels[$label])) {
        throw new RuntimeException("Missing compiled accessible fixture {$label}.");
    }
}
echo "Public .pam fixtures compile with stable identifiers.\n";
