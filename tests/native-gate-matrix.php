<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\AccessibilityCheckedState;
use Pam\Native\AccessibilityRole;
use Pam\Native\Element;
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
$materialRoleContract = [
    'p-app-bar' => AccessibilityRole::Toolbar,
    'p-app-bar-nav-icon' => AccessibilityRole::Button,
    'p-alert' => AccessibilityRole::Alert,
    'p-autocomplete' => AccessibilityRole::ComboBox,
    'p-avatar' => AccessibilityRole::Image,
    'p-banner' => AccessibilityRole::Alert,
    'p-btn' => AccessibilityRole::Button,
    'p-calendar' => AccessibilityRole::Grid,
    'p-calendar-day' => AccessibilityRole::Button,
    'p-carousel-item' => AccessibilityRole::Tab,
    'p-checkbox' => AccessibilityRole::Checkbox,
    'p-chip' => AccessibilityRole::Button,
    'p-color-input' => AccessibilityRole::Input,
    'p-combobox' => AccessibilityRole::ComboBox,
    'p-data-table' => AccessibilityRole::Grid,
    'p-data-table-virtual' => AccessibilityRole::Grid,
    'p-date-input' => AccessibilityRole::Input,
    'p-date-picker' => AccessibilityRole::Grid,
    'p-empty-state' => AccessibilityRole::Summary,
    'p-expansion-panel' => AccessibilityRole::Button,
    'p-expansion-panel-title' => AccessibilityRole::ToggleButton,
    'p-fab' => AccessibilityRole::Button,
    'p-icon' => AccessibilityRole::Image,
    'p-icon-btn' => AccessibilityRole::Button,
    'p-img' => AccessibilityRole::Image,
    'p-infinite-scroll' => AccessibilityRole::List,
    'p-item' => AccessibilityRole::Button,
    'p-list' => AccessibilityRole::List,
    'p-list-item' => AccessibilityRole::ListItem,
    'p-menu' => AccessibilityRole::Menu,
    'p-number-input' => AccessibilityRole::SpinButton,
    'p-otp-input' => AccessibilityRole::Input,
    'p-progress-circular' => AccessibilityRole::ProgressBar,
    'p-progress-linear' => AccessibilityRole::ProgressBar,
    'p-radio' => AccessibilityRole::Radio,
    'p-radio-group' => AccessibilityRole::RadioGroup,
    'p-range-slider' => AccessibilityRole::Adjustable,
    'p-rating' => AccessibilityRole::Adjustable,
    'p-select' => AccessibilityRole::ComboBox,
    'p-slide-group' => AccessibilityRole::TabList,
    'p-slide-group-item' => AccessibilityRole::Tab,
    'p-slider' => AccessibilityRole::Adjustable,
    'p-snackbar' => AccessibilityRole::Alert,
    'p-sparkline' => AccessibilityRole::Image,
    'p-speed-dial' => AccessibilityRole::Button,
    'p-stepper' => AccessibilityRole::List,
    'p-stepper-header' => AccessibilityRole::TabList,
    'p-stepper-item' => AccessibilityRole::Tab,
    'p-stepper-vertical' => AccessibilityRole::List,
    'p-stepper-vertical-item' => AccessibilityRole::ListItem,
    'p-switch' => AccessibilityRole::Switch,
    'p-tabs' => AccessibilityRole::TabList,
    'p-tab' => AccessibilityRole::Tab,
    'p-text-field' => AccessibilityRole::Input,
    'p-textarea' => AccessibilityRole::Input,
    'p-timeline' => AccessibilityRole::List,
    'p-timeline-item' => AccessibilityRole::ListItem,
    'p-toolbar' => AccessibilityRole::Toolbar,
    'p-tooltip' => AccessibilityRole::Presentation,
    'p-treeview' => AccessibilityRole::List,
    'p-treeview-item' => AccessibilityRole::ListItem,
];
$renders = 0;
$frames = 0;
$accessibilityScenarios = 0;
$accessibilityAssertions = 0;
$semanticRoleAssertions = 0;
$rtlAssertions = 0;
$reducedMotionAssertions = 0;
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
                    'reduceMotion' => true,
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
                $accessibilityAssertions++;
                if (
                    ($properties[PropKey::AccessibilityHint->value] ?? null)
                    !== 'Native gate matrix'
                ) {
                    throw new RuntimeException(
                        "{$name} did not preserve its native accessibility hint.",
                    );
                }
                $accessibilityAssertions++;
                $role = $properties[PropKey::AccessibilityRole->value] ?? null;
                if (!is_int($role) || AccessibilityRole::tryFrom($role) === null) {
                    throw new RuntimeException(
                        "{$name} did not emit an integer-backed native accessibility role.",
                    );
                }
                $accessibilityAssertions++;
                $expectedRole = $catalog === 'material'
                    ? ($materialRoleContract[$name] ?? null)
                    : null;
                if ($expectedRole !== null) {
                    if ($role !== $expectedRole->value) {
                        throw new RuntimeException(sprintf(
                            '%s emitted role %s; expected %s.',
                            $name,
                            AccessibilityRole::from($role)->name,
                            $expectedRole->name,
                        ));
                    }
                    $semanticRoleAssertions++;
                }
                if (!treeContainsProperty($element, PropKey::LayoutDirection, 2)) {
                    throw new RuntimeException(
                        "{$name} did not preserve right-to-left layout direction.",
                    );
                }
                $rtlAssertions++;
                if (($state['disabled'] ?? false) === true) {
                    if (($properties[PropKey::Enabled->value] ?? null) !== false) {
                        throw new RuntimeException(
                            "{$name} did not expose its disabled native state.",
                        );
                    }
                    $accessibilityAssertions++;
                }
                if (($state['selected'] ?? false) === true) {
                    if (($properties[PropKey::Selected->value] ?? null) !== true) {
                        throw new RuntimeException(
                            "{$name} did not expose its selected native state.",
                        );
                    }
                    $accessibilityAssertions++;
                }
                if (($state['checked'] ?? false) === true) {
                    if (
                        ($properties[PropKey::AccessibilityCheckedState->value] ?? null)
                        !== AccessibilityCheckedState::Checked->value
                        || ($properties[PropKey::Checked->value] ?? null) !== true
                    ) {
                        throw new RuntimeException(
                            "{$name} did not expose its checked native state.",
                        );
                    }
                    $accessibilityAssertions += 2;
                }
                if (($state['loading'] ?? false) === true) {
                    if (
                        ($properties[PropKey::AccessibilityBusy->value] ?? null) !== true
                        || ($properties[PropKey::Enabled->value] ?? null) !== false
                    ) {
                        throw new RuntimeException(
                            "{$name} did not expose a busy, non-interactive loading state.",
                        );
                    }
                    $accessibilityAssertions += 2;
                }
                assertReducedMotion($element, $name, $reducedMotionAssertions);
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
                $accessibilityScenarios++;
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
        'accessibilityScenarios' => $accessibilityScenarios,
        'accessibilityAssertions' => $accessibilityAssertions,
        'semanticRoleAssertions' => $semanticRoleAssertions,
        'rtlAssertions' => $rtlAssertions,
        'reducedMotionAssertions' => $reducedMotionAssertions,
        'rendersPerSecond' => round($rendersPerSecond, 1),
        'largestFrameBytes' => $largestFrameBytes,
        'peakMemoryBytes' => $peakMemoryBytes,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";

function assertReducedMotion(
    Element $element,
    string $name,
    int &$assertions,
): void {
    $properties = $element->properties();
    if (array_key_exists(PropKey::AnimateChanges->value, $properties)) {
        if ($properties[PropKey::AnimateChanges->value] !== false) {
            throw new RuntimeException(
                "{$name} retained native animation while reduced motion was requested.",
            );
        }
        $assertions++;
    }
    if (array_key_exists(PropKey::AnimationDurationMs->value, $properties)) {
        if ($properties[PropKey::AnimationDurationMs->value] !== 0) {
            throw new RuntimeException(
                "{$name} retained a non-zero duration while reduced motion was requested.",
            );
        }
        $assertions++;
    }
    foreach ($element->children() as $child) {
        assertReducedMotion($child, $name, $assertions);
    }
}

function treeContainsProperty(
    Element $element,
    PropKey $property,
    mixed $expected,
): bool {
    if (($element->properties()[$property->value] ?? null) === $expected) {
        return true;
    }
    foreach ($element->children() as $child) {
        if (treeContainsProperty($child, $property, $expected)) {
            return true;
        }
    }

    return false;
}
