<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

// Execute the repository-owned example itself, not a manually duplicated copy.
$document = dirname(__DIR__, 2).'/pam-docs/src/content/docs/packages/mobile-ui.mdx';
$source = file_get_contents($document);
if ($source === false) {
    throw new RuntimeException('Cannot read the sibling PAM documentation.');
}
preg_match_all('/```php\R(.*?)```/s', $source, $blocks);
$examples = array_values(array_filter(
    $blocks[1],
    static fn (string $block): bool => str_contains($block, '$content = Card::make'),
));
if (count($examples) !== 1) {
    throw new RuntimeException('Expected exactly one documented Card/Button composition.');
}
$shipped = false;
$handler = static function () use (&$shipped): void {
    $shipped = true;
};
// The docs place this expression inside an application component's render method.
$example = str_replace('$this->shipOrder(...)', '$handler', $examples[0], $replacements);
if ($replacements !== 1) {
    throw new RuntimeException('The documented handler must use a first-class callable.');
}
$content = eval($example."\nreturn \$content;");
if (!$content instanceof \Pam\MobileUi\Component\UiComponent) {
    throw new RuntimeException('The example did not produce a UI component.');
}
$tree = $content->toElement();
$button = $tree->children()[0] ?? null;
$press = $button?->events()[\Pam\Native\EventKind::Press->value] ?? null;
if (!$press instanceof Closure) {
    throw new RuntimeException('The example did not bind its press handler.');
}
$press();
if (!$shipped) {
    throw new RuntimeException('The documented press handler was not invoked.');
}
echo "Documented composition and press callback: PASS\n";
