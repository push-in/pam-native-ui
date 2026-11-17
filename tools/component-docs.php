<?php

declare(strict_types=1);

/*
 * Writes docs/components.md from the component classes: tag, summary,
 * typed props with defaults, events and slots. Run after changing a
 * component: php tools/component-docs.php
 */

use Pam\Native\Attributes\Event;
use Pam\Native\Attributes\Slot;
use Pam\Native\Attributes\Tag;
use Pam\Native\Internal\PamPhpCompiler;

require getenv('PAM_NATIVE_UI_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$cache = sys_get_temp_dir().'/pam-native-ui-docs-'.getmypid();
$components = PamPhpCompiler::compileDirectory($root.'/components', $cache);
$order = ['inputs' => [], 'actions' => [], 'layout' => [], 'feedback' => [], 'overlays' => []];
$group = static fn (string $tag): string => match (true) {
    in_array($tag, ['p-text-field', 'p-textarea', 'p-checkbox', 'p-switch', 'p-radio-group', 'p-select', 'p-date-input', 'p-slider', 'p-stepper', 'p-segmented', 'p-chip', 'p-tabs'], true) => 'inputs',
    in_array($tag, ['p-btn', 'p-icon-btn'], true) => 'actions',
    in_array($tag, ['p-screen', 'AppScreen', 'p-card', 'p-section', 'p-list-item', 'p-divider', 'p-form'], true) => 'layout',
    in_array($tag, ['p-sheet', 'p-dialog', 'p-snackbar'], true) => 'overlays',
    default => 'feedback',
};
foreach ($components as $component) {
    require_once $component->classFile;
    $class = new ReflectionClass($component->className);
    $tag = ($class->getAttributes(Tag::class)[0] ?? null)?->newInstance()->name ?? $class->getShortName();
    $doc = (string) $class->getDocComment();
    $lines = array_map(static fn (string $line): string => trim(preg_replace('/^\s*\*\s?/', '', $line) ?? ''), explode("\n", trim($doc, "/* \n")));
    $summary = [];
    $example = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, '@')) {
            continue;
        }
        if (str_starts_with($line, '<')) {
            $example[] = $line;
        } elseif ($line !== '' || $summary !== []) {
            $summary[] = $line;
        }
    }
    $props = [];
    foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
        $default = $parameter->isDefaultValueAvailable() ? var_export($parameter->getDefaultValue(), true) : 'required';
        $default = str_replace(['NULL', "array (\n)"], ['null', '[]'], $default);
        $props[] = '| `'.$parameter->getName().'` | `'.(string) $parameter->getType().'` | `'.$default.'` |';
    }
    $events = array_map(static fn ($a): string => '`'.$a->newInstance()->name.'` ('.$a->newInstance()->payload.')', $class->getAttributes(Event::class));
    $slots = array_map(static fn ($a): string => '`'.$a->newInstance()->name.'`', $class->getAttributes(Slot::class));
    $section = '### `'.$tag."`\n\n".trim(implode("\n", $summary))."\n\n";
    if ($example !== []) {
        $section .= "```xml\n".implode("\n", $example)."\n```\n\n";
    }
    if ($props !== []) {
        $section .= "| Prop | Type | Default |\n| --- | --- | --- |\n".implode("\n", $props)."\n\n";
    }
    if ($events !== []) {
        $section .= 'Events: '.implode(', ', $events).($class->getAttributes(Event::class) !== [] && in_array('model', array_map(static fn ($a) => $a->newInstance()->name, $class->getAttributes(Event::class)), true) ? '. Bind with `p-model`.' : '.')."\n\n";
    }
    if ($slots !== []) {
        $section .= 'Slots: '.implode(', ', $slots).".\n\n";
    }
    $order[$group($tag)][$tag] = $section;
}
$titles = ['inputs' => 'Inputs', 'actions' => 'Actions', 'layout' => 'Layout', 'feedback' => 'Feedback', 'overlays' => 'Overlays'];
$out = "# Component reference\n\nGenerated from the component classes by `tools/component-docs.php`. Every prop is a typed constructor parameter: a static attribute (`label=\"Name\"`) or a bound expression (`:options=\"\$countries\"`). Inputs and overlays bind with `p-model`; their value is the `modelValue` prop.\n\n";
foreach ($order as $key => $sections) {
    ksort($sections);
    $out .= '## '.$titles[$key]."\n\n".implode('', $sections);
}
file_put_contents($root.'/docs/components.md', rtrim($out)."\n");
foreach (glob($cache.'/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($cache);
echo 'Wrote docs/components.md ('.count($components)." components).\n";
