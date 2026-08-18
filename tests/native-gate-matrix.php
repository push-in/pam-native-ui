<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\Internal\TreeEncoder;
use Pam\Native\PropKey;

require __DIR__.'/bootstrap.php';

$expectedBehaviors = [];
foreach (NativeBehavior::cases() as $behavior) {
    $expectedBehaviors[strtolower($behavior->name)] = $behavior->value;
}
$nativeBehaviorSources = [
    dirname(__DIR__).'/android/src/main/kotlin/dev/pam/mobileui/MobileUiHost.kt'
        => [
            'pattern' => '/^        ([A-Z_]+)\((\d+)\)[,;]?$/m',
            'start' => 'private enum class Behavior',
            'end' => '        companion object',
            'aliases' => [
                'switch' => 'switchcontrol',
                'tabtrigger' => 'tabstrigger',
            ],
        ],
    dirname(__DIR__).'/ios/Sources/PamMobileUi/PamMobileUiHost.swift'
        => [
            'pattern' => '/^    case ([a-zA-Z]+) = (\d+)$/m',
            'start' => 'private enum PamMobileBehavior',
            'end' => '    var isOverlay',
            'aliases' => [
                'tabtrigger' => 'tabstrigger',
            ],
        ],
];
foreach ($nativeBehaviorSources as $path => $source) {
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException("Cannot inspect native behavior source {$path}.");
    }
    $start = strpos($contents, $source['start']);
    $end = $start === false
        ? false
        : strpos($contents, $source['end'], $start);
    if ($start === false || $end === false) {
        throw new RuntimeException("Cannot locate native behavior enum in {$path}.");
    }
    $behaviorSource = substr($contents, $start, $end - $start);
    preg_match_all($source['pattern'], $behaviorSource, $matches, PREG_SET_ORDER);
    $actualBehaviors = [];
    foreach ($matches as $match) {
        $name = strtolower(str_replace('_', '', $match[1]));
        $name = $source['aliases'][$name] ?? $name;
        $actualBehaviors[$name] = (int) $match[2];
    }
    if ($actualBehaviors !== $expectedBehaviors) {
        throw new RuntimeException(
            "Native behavior protocol in {$path} diverges from PHP.",
        );
    }
}

$catalogs = [
    'technical' => ComponentMap::TAGS,
    'material' => MaterialComponentMap::TAGS,
];
$themes = [
    [ThemeMode::Light, Themes::light()],
    [ThemeMode::Dark, Themes::dark()],
    [ThemeMode::Light, Themes::pamLight()],
    [ThemeMode::Dark, Themes::pamDark()],
];
$states = [
    [],
    ['active' => true],
    ['checked' => true],
    ['disabled' => true],
    ['focused' => true],
    ['loading' => true],
    ['selected' => true],
];
$renders = 0;
$frames = 0;
$accessibilityCases = 0;
$rtlCases = 0;
$motionCases = 0;
$largestFrameBytes = 0;
$started = hrtime(true);

foreach ($catalogs as $catalog => $components) {
    foreach ($themes as [$mode, $theme]) {
        PamUI::theme($theme, $theme);
        PamUI::mode($mode);
        foreach ($components as $name => $class) {
            foreach ($states as $state) {
                $label = "{$catalog}:{$name}";
                $element = $class::make([
                    'text' => $name,
                    'value' => 1,
                    'accessibilityLabel' => $label,
                    'accessibilityHint' => 'Native gate matrix',
                    'rtl' => true,
                    'reduceMotion' => false,
                    ...$state,
                ])->toElement();
                $properties = $element->properties();
                if (
                    ($properties[PropKey::AccessibilityLabel->value] ?? null)
                    !== $label
                ) {
                    throw new RuntimeException(
                        "{$name} did not preserve its native accessibility label.",
                    );
                }
                $encoded = (new TreeEncoder())->encode($element);
                $frame = $encoded['frame'] ?? null;
                if (!is_string($frame) || !str_starts_with($frame, 'PNT1')) {
                    throw new RuntimeException(
                        "{$name} did not produce a valid native binary frame.",
                    );
                }
                $largestFrameBytes = max($largestFrameBytes, strlen($frame));
                $renders++;
                $frames++;
                $accessibilityCases++;
                $rtlCases++;
                $motionCases++;
            }
        }
    }
}

$elapsedNanoseconds = hrtime(true) - $started;
$rendersPerSecond = $renders / ($elapsedNanoseconds / 1_000_000_000);
$peakMemoryBytes = memory_get_peak_usage(true);
if ($rendersPerSecond < 1_000) {
    throw new RuntimeException(sprintf(
        'Native gate throughput %.1f/s is below the 1000/s budget.',
        $rendersPerSecond,
    ));
}
if ($largestFrameBytes > 1_048_576) {
    throw new RuntimeException('A native gate frame exceeded one megabyte.');
}
if ($peakMemoryBytes > 67_108_864) {
    throw new RuntimeException('Native gate matrix exceeded 64 MB peak memory.');
}

PamUI::mode(ThemeMode::System);

echo json_encode(
    [
        'catalogs' => [
            'technical' => count(ComponentMap::TAGS),
            'material' => count(MaterialComponentMap::TAGS),
        ],
        'themes' => count($themes),
        'states' => count($states),
        'renders' => $renders,
        'nativeFrames' => $frames,
        'accessibilityCases' => $accessibilityCases,
        'rtlCases' => $rtlCases,
        'motionCases' => $motionCases,
        'rendersPerSecond' => round($rendersPerSecond, 1),
        'largestFrameBytes' => $largestFrameBytes,
        'peakMemoryBytes' => $peakMemoryBytes,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";
