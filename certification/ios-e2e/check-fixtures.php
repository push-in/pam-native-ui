<?php

declare(strict_types=1);

use Pam\MobileUi\MobileUiPluginProvider;
use Pam\Native\Element;
use Pam\Native\PropKey;
use Pam\Native\View;

require dirname(__DIR__, 2).'/tests/bootstrap.php';

(new MobileUiPluginProvider())->register();
View::configure(__DIR__.'/app/resources/native');
$root = View::make('fixtures')->toElement();
$found = [];
$visit = static function (Element $node) use (&$visit, &$found): void {
    $id = $node->properties()[PropKey::TestId->value] ?? null;
    if (is_string($id) && str_starts_with($id, 'fixture-')) {
        $found[$id] = true;
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
echo "Public .pam fixtures compile with stable identifiers.\n";
