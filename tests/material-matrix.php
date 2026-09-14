<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentMode;
use Pam\MobileUi\Enum\MaterialVariant;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Material\PAlert;
use Pam\MobileUi\Material\PBtn;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Rendering\MaterialStyleResolver;
use Pam\MobileUi\Rendering\StyleResolver;
use Pam\MobileUi\Theme\MaterialTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\AccessibilityCheckedState;
use Pam\Native\AccessibilityImportance;
use Pam\Native\AccessibilityRole;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\EventKind;
use Pam\Native\ImageErrorEvent;
use Pam\Native\Align;
use Pam\Native\FlexDirection;
use Pam\Native\FlexWrap;
use Pam\Native\Justify;
use Pam\Native\KeyboardType;
use Pam\Native\ModalAnimationType;
use Pam\Native\ModalPresentation;
use Pam\Native\NodeKind;
use Pam\Native\Overflow;
use Pam\Native\PositionType;
use Pam\Native\PropKey;
use Pam\Native\Style;
use Pam\Native\ScrollIndicatorStyle;
use Pam\Native\UI\Text;

require __DIR__.'/bootstrap.php';
require __DIR__.'/material-field-state.php';
require __DIR__.'/material-list-mutation.php';
require_once dirname(__DIR__).'/examples/kitchen-sink/src/ComponentRoute.php';

$samplePropsMethod = new ReflectionMethod(\App\ComponentRoute::class, 'sampleProps');
$catalogMethod = new ReflectionMethod(\App\ComponentRoute::class, 'catalogVariations');
$auditMethod = new ReflectionMethod(\App\ComponentRoute::class, 'auditVariations');
foreach (['p-menu', 'p-tooltip'] as $overlayTag) {
    $route = new \App\ComponentRoute($overlayTag, 'Overlay', MaterialComponentMap::TAGS[$overlayTag]);
    $placements = [];
    $specimens = $catalogMethod->invoke($route);
    if (!is_array($specimens)) {
        throw new RuntimeException('Overlay catalog must return an array.');
    }
    foreach ($specimens as $specimen) {
        if (!is_array($specimen) || !is_string($specimen['label'] ?? null)
            || !is_array($specimen['props'] ?? null)) {
            throw new RuntimeException('Overlay specimens require a label and properties.');
        }
        if (!str_starts_with($specimen['label'], 'Location ')) {
            continue;
        }
        $expected = $specimen['props']['placement'] ?? null;
        if (!is_int($expected) || Placement::tryFrom($expected) === null) {
            throw new RuntimeException($overlayTag.' placement specimens must use supported enum codes.');
        }
        $resolved = $samplePropsMethod->invoke($route, $specimen['props']);
        if (!is_array($resolved) || ($resolved['placement'] ?? null) !== $expected) {
            throw new RuntimeException($overlayTag.' showcase must preserve each requested placement.');
        }
        $placements[] = $expected;
    }
    if ($placements !== array_column(Placement::cases(), 'value')) {
        throw new RuntimeException($overlayTag.' showcase must demonstrate every supported placement.');
    }
}
foreach (MaterialComponentMap::TAGS as $catalogTag => $catalogClass) {
    $catalogRoute = new \App\ComponentRoute($catalogTag, $catalogTag, $catalogClass);
    $specimens = $catalogMethod->invoke($catalogRoute);
    if (!is_array($specimens) || count($specimens) < 4) {
        throw new RuntimeException($catalogTag.' must expose at least four catalog specimens.');
    }
    $configurations = [];
    foreach ($specimens as $specimen) {
        if (!is_array($specimen) || !is_string($specimen['label'] ?? null)
            || trim($specimen['label']) === '' || !is_array($specimen['props'] ?? null)) {
            throw new RuntimeException($catalogTag.' catalog specimens need a label and props.');
        }
        $configuration = $specimen['props'];
        ksort($configuration);
        $configurations[] = json_encode($configuration, JSON_THROW_ON_ERROR);
    }
    if (count(array_unique($configurations)) < 4) {
        throw new RuntimeException($catalogTag.' must not count renamed identical props as distinct specimens.');
    }
}
$assertAuditScenario = static function (?string $scenario): void {
    if ($scenario !== 'default') {
        throw new RuntimeException('Catalog lookup must restore the active audit scenario.');
    }
};
foreach (['p-reorderable-list', 'p-swipe-actions', 'p-tree-select', 'p-chart', 'p-result-state', 'p-data-grid'] as $auditTag) {
    $route = new \App\ComponentRoute($auditTag, 'Sample', MaterialComponentMap::TAGS[$auditTag]);
    $route->state->auditScenario = 'default';
    $normal = $catalogMethod->invoke($route);
    $audited = $auditMethod->invoke($route, 'default');
    if (!is_array($normal) || count($normal) < 2 || $audited !== $normal) {
        throw new RuntimeException($auditTag.' audit route must retain the complete catalog variations.');
    }
    $assertAuditScenario($route->state->auditScenario);
}
foreach (['p-password-field', 'p-masked-field', 'p-currency-field', 'p-text-field', 'p-search-bar'] as $sampleTag) {
    $route = new \App\ComponentRoute($sampleTag, 'Sample', MaterialComponentMap::TAGS[$sampleTag]);
    foreach ([null, 'Custom accessible name'] as $explicitLabel) {
        $props = ['label' => 'Account field'];
        if ($explicitLabel !== null) {
            $props['accessibilityLabel'] = $explicitLabel;
        }
        $resolved = $samplePropsMethod->invoke($route, $props);
        $expectedLabel = $explicitLabel ?? ($sampleTag === 'p-search-bar' ? 'Search components' : 'Account field');
        if (!is_array($resolved) || ($resolved['accessibilityLabel'] ?? null) !== $expectedLabel) {
            throw new RuntimeException('Showcase must preserve field labels and explicit accessible names.');
        }
    }
}

foreach (['p-btn', 'p-progress-button'] as $actionTag) {
    $route = new \App\ComponentRoute($actionTag, 'Sample', MaterialComponentMap::TAGS[$actionTag]);
    foreach ([null, 'Explicit action name'] as $explicitName) {
        $actionProps = ['text' => 'Publish changes'];
        if ($explicitName !== null) {
            $actionProps['accessibilityLabel'] = $explicitName;
        }
        $resolved = $samplePropsMethod->invoke($route, $actionProps);
        if (!is_array($resolved) || ($resolved['accessibilityLabel'] ?? null) !== ($explicitName ?? 'Publish changes')) {
            throw new RuntimeException('Showcase actions must use their visible text unless explicitly named.');
        }
    }
}
$progressRoute = new \App\ComponentRoute('p-progress-button', 'Progress Button', MaterialComponentMap::TAGS['p-progress-button']);
$progressSpecimens = $catalogMethod->invoke($progressRoute);
if (!is_array($progressSpecimens)) {
    throw new RuntimeException('Progress showcase requires catalog specimens.');
}
foreach ([MaterialVariant::Text, MaterialVariant::Outlined, MaterialVariant::Tonal] as $requiredVariant) {
    $hasVariant = false;
    foreach ($progressSpecimens as $specimen) {
        if (is_array($specimen) && is_array($specimen['props'] ?? null)
            && ($specimen['props']['variant'] ?? null) === $requiredVariant->value
            && ($specimen['props']['progress'] ?? null) === 42) {
            $hasVariant = true;
        }
    }
    if (!$hasVariant) {
        throw new RuntimeException('Progress showcase must demonstrate light variants with visible partial progress.');
    }
}
$layoutTokens = [
    MaterialTokens::SPACE_EXTRA_SMALL,
    MaterialTokens::SPACE_SMALL,
    MaterialTokens::SPACE_MEDIUM,
    MaterialTokens::SPACE_LARGE,
    MaterialTokens::SPACE_EXTRA_LARGE,
    MaterialTokens::SPACE_DOUBLE_EXTRA_LARGE,
    MaterialTokens::COMPACT_WINDOW_GUTTER,
    MaterialTokens::MEDIUM_WINDOW_GUTTER,
    MaterialTokens::EXPANDED_WINDOW_GUTTER,
];
foreach ($layoutTokens as $spacing) {
    if (fmod($spacing, MaterialTokens::GRID) !== 0.0) {
        throw new RuntimeException('Material layout spacing must stay on the 4 dp grid.');
    }
}
$displayTextStyle = StyleResolver::resolve(
    'Text',
    ['size' => '2xl'],
    Themes::pamLight(),
);
if ($displayTextStyle->fontSize !== 24.0 || $displayTextStyle->lineHeight !== 32.0) {
    throw new RuntimeException(
        'String typography sizes must resolve to the intended Material type metrics.',
    );
}
$defaultTextareaStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PTextarea',
    'rows' => 3,
], Themes::pamLight());
$fourRowTextareaStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PTextarea',
    'rows' => 4,
], Themes::pamLight());
$grownTextareaStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PTextarea',
    'autoGrow' => true,
    'rows' => 2,
    'modelValue' => "One\nTwo\nThree\nFour\nFive",
], Themes::pamLight());
if (
    !$defaultTextareaStyle instanceof Style
    || $defaultTextareaStyle->height !== 112.0
    || !$fourRowTextareaStyle instanceof Style
    || $fourRowTextareaStyle->height !== 136.0
    || !$grownTextareaStyle instanceof Style
    || $grownTextareaStyle->height !== 160.0
) {
    throw new RuntimeException(
        'p-textarea rows and auto-grow must produce deterministic 24 dp line growth.',
    );
}
$tonalAlert = PAlert::make(
    ['action' => 'info', 'variant' => 'subtle'],
    Text::make('Tonal alert'),
)->toElement();
$tonalAlertProperties = $tonalAlert->properties();
$tonalAlertTextProperties = $tonalAlert->children()[1]->properties();
$activeTheme = ThemeManager::current();
if (
    ($tonalAlertProperties[PropKey::BackgroundColor->value] ?? null)
        === $activeTheme->color(ColorToken::Info)
) {
    throw new RuntimeException(
        'Material subtle variants must retain their canonical name and render as tonal surfaces.',
    );
}
if (
    ($tonalAlertTextProperties[PropKey::TextColor->value] ?? null)
        !== $activeTheme->color(ColorToken::Foreground)
) {
    throw new RuntimeException(
        'Tonal alert descendants must inherit an accessible foreground color.',
    );
}
$alertClosed = false;
$closableAlert = PAlert::make(
    ['type' => 'success', 'color' => 'success', 'closable' => true],
    Text::make('Changes saved'),
)->onClose(static function () use (&$alertClosed): void {
    $alertClosed = true;
})->toElement();
$alertStatus = $closableAlert->children()[0] ?? null;
$alertClose = $closableAlert->children()[2] ?? null;
if (
    !$alertStatus instanceof \Pam\Native\Element
    || ($alertStatus->properties()[PropKey::Value->value] ?? null)
        !== 'pam:preserve-foreground:alert-status'
    || !$alertClose instanceof \Pam\Native\Element
    || $alertClose->kind() !== NodeKind::Pressable
    || ($alertClose->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Close alert'
    || ($alertClose->properties()[PropKey::MinWidth->value] ?? null) !== 40.0
    || ($alertClose->properties()[PropKey::MinHeight->value] ?? null) !== 40.0
    || ($alertClose->properties()[PropKey::HitSlopLeft->value] ?? null) !== 4.0
    || !isset($alertClose->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'Closable alerts must expose a semantic status icon and labelled 48 dp close target.',
    );
}
$alertClose->events()[EventKind::Press->value]();
if (!$alertClosed) {
    throw new RuntimeException('p-alert close must emit its close callback.');
}
$plainAlertStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PAlert',
    'variant' => 'plain',
], Themes::pamLight());
$neutralTonalAlertStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PAlert',
    'variant' => 'tonal',
], Themes::pamLight());
if (
    !$plainAlertStyle instanceof Style
    || $plainAlertStyle->paddingHorizontal !== 0.0
    || $plainAlertStyle->paddingVertical !== 0.0
    || !$neutralTonalAlertStyle instanceof Style
    || $neutralTonalAlertStyle->backgroundColor
        === Themes::pamLight()->color(ColorToken::Secondary)
    || $neutralTonalAlertStyle->backgroundColor
        === Themes::pamLight()->color(ColorToken::Surface)
    || $neutralTonalAlertStyle->textColor
        !== Themes::pamLight()->color(ColorToken::Foreground)
) {
    throw new RuntimeException(
        'Plain and tonal alerts must preserve distinct, meaningful visual contracts.',
    );
}
$hiddenBanner = \Pam\MobileUi\Material\PBanner::make(
    ['visible' => false],
    Text::make('Hidden update'),
)->toElement();
$visibleBanner = \Pam\MobileUi\Material\PBanner::make(
    [],
    Text::make('Available update'),
)->toElement();
if (
    ($hiddenBanner->properties()[PropKey::Visible->value] ?? null) !== false
    || array_key_exists(PropKey::Visible->value, $visibleBanner->properties())
    || ($visibleBanner->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::Alert->value
    || ($visibleBanner->properties()[PropKey::AccessibilityLiveRegion->value] ?? null)
        !== \Pam\Native\AccessibilityLiveRegion::Polite->value
) {
    throw new RuntimeException(
        'p-banner must honor visibility while preserving alert and polite-live semantics.',
    );
}
$successBannerStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PBanner',
    'color' => 'success',
], Themes::pamLight());
if (
    !$successBannerStyle instanceof Style
    || $successBannerStyle->backgroundColor
        === Themes::pamLight()->color(ColorToken::Surface)
    || $successBannerStyle->backgroundColor
        === Themes::pamLight()->color(ColorToken::Success)
) {
    throw new RuntimeException(
        'Semantic banners must use a readable tonal surface instead of ignoring or flooding the requested color.',
    );
}
$textButton = PBtn::make(
    ['variant' => 'text'],
    Text::make('Text action'),
)->toElement();
$textButtonProperties = $textButton->properties();
if (($textButton->children()[0]->properties()[PropKey::FlexShrink->value] ?? null) !== 1.0) {
    throw new RuntimeException('Button labels must shrink into their allocated width for multiline measurement.');
}
$fixedLabelButton = PBtn::make([], Text::make('Fixed label')->style(new Style(flexShrink: 0.0)))->toElement();
if (($fixedLabelButton->children()[0]->properties()[PropKey::FlexShrink->value] ?? null) !== 0.0) {
    throw new RuntimeException('Explicit application label shrink behavior must remain customizable.');
}
$extraSmallButtonProperties = PBtn::make(
    ['variant' => 'text', 'size' => 'x-small'],
    Text::make('Compact action'),
)->toElement()->properties();
$largeButtonProperties = PBtn::make(
    ['variant' => 'text', 'size' => 'large'],
    Text::make('Large action'),
)->toElement()->properties();
if (
    ($textButtonProperties[PropKey::BackgroundColor->value] ?? null) !== 0x00000000
    || ($textButtonProperties[PropKey::Elevation->value] ?? null) !== 0.0
    || ($textButtonProperties[PropKey::BorderWidth->value] ?? null) !== 0.0
    || ($textButtonProperties[PropKey::HitSlopLeft->value] ?? null) !== 4.0
    || ($textButtonProperties[PropKey::HitSlopTop->value] ?? null) !== 4.0
    || ($extraSmallButtonProperties[PropKey::HitSlopLeft->value] ?? null) !== 8.0
    || ($largeButtonProperties[PropKey::HitSlopLeft->value] ?? null) !== 0.0
) {
    throw new RuntimeException(
        'Text buttons must stay containerless while expanding only to an exact 48 dp target.',
    );
}

$tags = MaterialComponentMap::TAGS;
$ids = MaterialComponentMap::IDS;
$modules = MaterialComponentMap::MODULES;
$buttonGroupValue = 'unchanged';
$buttonGroup = $tags['p-btn-group']::make(
    ['modelValue' => 'week'],
    $tags['p-btn']::make(['value' => 'day'], Text::make('Day')),
    $tags['p-btn']::make(['value' => 'week'], Text::make('Week')),
    $tags['p-btn']::make(['value' => 'month'], Text::make('Month')),
)->onChange(static function (mixed $value) use (&$buttonGroupValue): void {
    $buttonGroupValue = $value;
})->toElement();
$buttonGroupProperties = $buttonGroup->properties();
$buttonGroupChildren = $buttonGroup->children()[0]->children();
$buttonGroupSelected = $buttonGroupChildren[1]->properties();
if (
    $buttonGroup->kind() !== NodeKind::Scroll
    || ($buttonGroupProperties[PropKey::ScrollHorizontal->value] ?? null) !== true
    || ($buttonGroupProperties[PropKey::ScrollFillViewport->value] ?? null) !== true
    || ($buttonGroupProperties[PropKey::ScrollPersistentScrollbar->value] ?? null) !== true
    || ($buttonGroupProperties[PropKey::ScrollIndicatorStyle->value] ?? null) !== ScrollIndicatorStyle::Dark->value
    || ($buttonGroupProperties[PropKey::ScrollFadingEdgeLength->value] ?? null) !== 12.0
    || isset($buttonGroupProperties[PropKey::Height->value])
    || $buttonGroup->children()[0]->kind() !== NodeKind::Row
    || ($buttonGroup->children()[0]->properties()[PropKey::PaddingBottom->value] ?? null) !== 4.0
    || ($buttonGroup->children()[0]->properties()[PropKey::MinHeight->value] ?? null) !== 52.0
    || ($buttonGroupProperties[PropKey::Gap->value] ?? null) !== 8.0
    || ($buttonGroupProperties[PropKey::MinHeight->value] ?? null) !== 48.0
    || count($buttonGroupChildren) !== 3
    || $buttonGroupChildren[0]->kind() !== NodeKind::Pressable
    || ($buttonGroupChildren[0]->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::ToggleButton->value
    || ($buttonGroupChildren[0]->properties()[PropKey::HitSlopLeft->value] ?? null)
        !== 0.0
    || ($buttonGroupChildren[0]->properties()[PropKey::HitSlopTop->value] ?? null)
        !== 4.0
    || ($buttonGroupChildren[0]->properties()[PropKey::Selected->value] ?? null) !== false
    || ($buttonGroupSelected[PropKey::Selected->value] ?? null) !== true
    || ($buttonGroupSelected[PropKey::Checked->value] ?? null) !== true
    || ($buttonGroupSelected[PropKey::AccessibilityCheckedState->value] ?? null) !== AccessibilityCheckedState::Checked->value
    || ($buttonGroupChildren[0]->properties()[PropKey::AccessibilityCheckedState->value] ?? null) !== AccessibilityCheckedState::Unchecked->value
    || ($buttonGroupSelected[PropKey::BackgroundColor->value] ?? null)
        !== ThemeManager::current()->color(ColorToken::Primary)
    || !isset($buttonGroupChildren[1]->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'p-btn-group must expose three independent 48 dp toggle targets with a visible selected state.',
    );
}
$buttonGroupChildren[1]->events()[EventKind::Press->value]();
if ($buttonGroupValue !== null) {
    throw new RuntimeException(
        'A non-mandatory p-btn-group must allow its selected item to be cleared.',
    );
}

$indicatorThemeMode = ThemeManager::configuredMode();
try {
    foreach ([ThemeMode::Light, ThemeMode::Dark] as $mode) {
        ThemeManager::mode($mode);
        foreach (['p-btn-group', 'p-btn-toggle'] as $tag) {
            $indicatorGroup = $tags[$tag]::make([], Text::make('Example'))->toElement();
            $expectedIndicator = $mode === ThemeMode::Light ? ScrollIndicatorStyle::Dark : ScrollIndicatorStyle::Light;
            if (($indicatorGroup->properties()[PropKey::ScrollIndicatorStyle->value] ?? null) !== $expectedIndicator->value) {
                throw new RuntimeException('Grouped buttons must select a contrasting indicator for each theme.');
            }
            foreach (ScrollIndicatorStyle::cases() as $indicatorStyle) {
                $customIndicator = $tags[$tag]::make(['scrollIndicatorStyle' => $indicatorStyle], Text::make('Example'))->toElement();
                if (($customIndicator->properties()[PropKey::ScrollIndicatorStyle->value] ?? null) !== $indicatorStyle->value) {
                    throw new RuntimeException('Applications must be able to override the group indicator appearance.');
                }
            }
        }
    }
} finally {
    ThemeManager::mode($indicatorThemeMode);
}

$styledGroup = $tags['p-btn-group']::make(
    ['accessibilityLabel' => 'Custom actions'],
    $tags['p-btn']::make([], Text::make('Action')),
)->style(new Style(width: 240.0, paddingHorizontal: 12.0, marginTop: 16.0, gap: 12.0))
    ->toElement();
$styledViewport = $styledGroup->properties();
$styledContent = $styledGroup->children()[0]->properties();
if (
    ($styledViewport[PropKey::Width->value] ?? null) !== 240.0
    || ($styledViewport[PropKey::PaddingHorizontal->value] ?? null) !== 12.0
    || ($styledViewport[PropKey::MarginTop->value] ?? null) !== 16.0
    || ($styledContent[PropKey::Gap->value] ?? null) !== 12.0
    || isset($styledContent[PropKey::PaddingHorizontal->value])
    || isset($styledContent[PropKey::MarginTop->value])
    || isset($styledContent[PropKey::WidthPercent->value])
) {
    throw new RuntimeException('Scrollable groups must preserve custom viewport sizing and row gaps without duplicating insets.');
}

$connectedValue = null;
$connectedButtonGroup = $tags['p-btn-group']::make(
    [
        'modelValue' => 'center',
        'connected' => true,
        'block' => true,
        'mandatory' => true,
    ],
    $tags['p-btn']::make(['value' => 'left'], Text::make('Left')),
    $tags['p-btn']::make(['value' => 'center'], Text::make('Center')),
    $tags['p-btn']::make(['value' => 'right'], Text::make('Right')),
)->onChange(static function (mixed $value) use (&$connectedValue): void {
    $connectedValue = $value;
})->toElement();
$connectedProperties = $connectedButtonGroup->properties();
$connectedChildren = $connectedButtonGroup->children()[0]->children();
$connectedLeading = $connectedChildren[0]->properties();
$connectedSelected = $connectedChildren[1]->properties();
$connectedTrailing = $connectedChildren[2]->properties();
if (
    ($connectedProperties[PropKey::Gap->value] ?? null) !== 2.0
    || ($connectedProperties[PropKey::WidthPercent->value] ?? null) !== 100.0
    || ($connectedLeading[PropKey::FlexGrow->value] ?? null) !== 1.0
    || ($connectedLeading[PropKey::MinWidth->value] ?? null) !== 0.0
    || ($connectedLeading[PropKey::BorderTopLeftRadius->value] ?? null) !== 20.0
    || ($connectedLeading[PropKey::BorderTopRightRadius->value] ?? null) !== 8.0
    || ($connectedSelected[PropKey::BorderTopLeftRadius->value] ?? null) !== 20.0
    || ($connectedSelected[PropKey::BorderTopRightRadius->value] ?? null) !== 20.0
    || ($connectedTrailing[PropKey::BorderTopLeftRadius->value] ?? null) !== 8.0
    || ($connectedTrailing[PropKey::BorderTopRightRadius->value] ?? null) !== 20.0
) {
    throw new RuntimeException(
        'Connected p-btn-group items must fill the row with asymmetric outer shapes and a selected pill.',
    );
}
$connectedChildren[1]->events()[EventKind::Press->value]();
if ($connectedValue !== 'center') {
    throw new RuntimeException(
        'A mandatory p-btn-group must retain its selected scalar value.',
    );
}

$rtlButtonGroup = $tags['p-btn-group']::make(
    [
        'modelValue' => 'week',
        'connected' => true,
        'block' => true,
        'mandatory' => true,
        'rtl' => true,
    ],
    $tags['p-btn']::make(['value' => 'day'], Text::make('Day')),
    $tags['p-btn']::make(['value' => 'week'], Text::make('Week')),
    $tags['p-btn']::make(['value' => 'month'], Text::make('Month')),
)->toElement();
$rtlProperties = $rtlButtonGroup->properties();
$rtlChildren = $rtlButtonGroup->children()[0]->children();
$rtlLogicalFirst = $rtlChildren[0]->properties();
$rtlLogicalLast = $rtlChildren[2]->properties();
if (
    ($rtlProperties[PropKey::FlexDirection->value] ?? null)
        !== FlexDirection::RowReverse->value
    || ($rtlProperties[PropKey::LayoutDirection->value] ?? null) !== 2
    || ($rtlLogicalFirst[PropKey::BorderTopLeftRadius->value] ?? null) !== 8.0
    || ($rtlLogicalFirst[PropKey::BorderTopRightRadius->value] ?? null) !== 20.0
    || ($rtlLogicalLast[PropKey::BorderTopLeftRadius->value] ?? null) !== 20.0
    || ($rtlLogicalLast[PropKey::BorderTopRightRadius->value] ?? null) !== 8.0
) {
    throw new RuntimeException(
        'RTL connected p-btn-group must reverse visual order and preserve logical outer shapes.',
    );
}

$multipleValue = null;
$multipleButtonGroup = $tags['p-btn-group']::make(
    ['modelValue' => ['bold'], 'multiple' => true, 'mandatory' => true],
    $tags['p-btn']::make(['value' => 'bold'], Text::make('Bold')),
    $tags['p-btn']::make(['value' => 'italic'], Text::make('Italic')),
)->onChange(static function (mixed $value) use (&$multipleValue): void {
    $multipleValue = $value;
})->toElement();
$multipleButtonGroup->children()[0]->children()[0]->events()[EventKind::Press->value]();
if ($multipleValue !== ['bold']) {
    throw new RuntimeException(
        'A mandatory multiple p-btn-group must never clear its final selection.',
    );
}

$disabledButtonGroup = $tags['p-btn-group']::make(
    ['disabled' => true, 'density' => 'compact', 'modelValue' => 'read'],
    $tags['p-btn']::make(['value' => 'read'], Text::make('Read')),
    $tags['p-btn']::make(['value' => 'write'], Text::make('Write')),
)->toElement();
if (($disabledButtonGroup->properties()[PropKey::Opacity->value] ?? null) !== 1.0) {
    throw new RuntimeException(
        'Disabled p-btn-group must not compound the state alpha already applied to its children.',
    );
}
$disabledGroupChildren = $disabledButtonGroup->children()[0]->children();
if (
    ($disabledGroupChildren[0]->properties()[PropKey::Selected->value] ?? null) !== true
    || ($disabledGroupChildren[0]->properties()[PropKey::BackgroundColor->value] ?? 0)
        === 0x00000000
    || ($disabledGroupChildren[1]->properties()[PropKey::BackgroundColor->value] ?? null)
        !== 0x00000000
) {
    throw new RuntimeException(
        'Disabled p-btn-group must preserve a visible distinction between selected and unselected items.',
    );
}
foreach ($disabledGroupChildren as $disabledButton) {
    $disabledProperties = $disabledButton->properties();
    if (
        ($disabledProperties[PropKey::Enabled->value] ?? null) !== false
        || ($disabledProperties[PropKey::MinHeight->value] ?? null) !== 32.0
        || isset($disabledProperties[PropKey::Height->value])
        || ($disabledProperties[PropKey::HitSlopTop->value] ?? null) !== 8.0
    ) {
        throw new RuntimeException(
            'p-btn-group density and disabled state must propagate to every child target.',
        );
    }
}
$findByValue = static function (
    \Pam\Native\Element $root,
    string $value,
) use (&$findByValue): ?\Pam\Native\Element {
    if (($root->properties()[PropKey::Value->value] ?? null) === $value) {
        return $root;
    }
    foreach ($root->children() as $child) {
        $match = $findByValue($child, $value);
        if ($match instanceof \Pam\Native\Element) {
            return $match;
        }
    }

    return null;
};
$parity = json_decode(
    file_get_contents(dirname(__DIR__).'/resources/material-parity.json')
        ?: throw new RuntimeException('Unable to read the Material parity contract.'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$paritySchema = json_decode(
    file_get_contents(dirname(__DIR__).'/resources/material-parity.schema.json')
        ?: throw new RuntimeException('Unable to read the Material parity schema.'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($parity) || !is_array($paritySchema)) {
    throw new RuntimeException('Material parity resources must decode to objects.');
}
$reference = $parity['reference'] ?? null;
$schemaProperties = $paritySchema['properties'] ?? null;
if (
    !is_array($reference)
    || !is_array($schemaProperties)
) {
    throw new RuntimeException('Material parity resources have an invalid shape.');
}
$schemaReferenceNode = $schemaProperties['reference'] ?? null;
$schemaModules = $schemaProperties['modules'] ?? null;
if (!is_array($schemaReferenceNode) || !is_array($schemaModules)) {
    throw new RuntimeException('Material parity schema is missing inventory nodes.');
}
$schemaReference = $schemaReferenceNode['properties'] ?? null;
if (!is_array($schemaReference)) {
    throw new RuntimeException('Material parity schema reference is invalid.');
}
$moduleCountNode = $schemaReference['moduleCount'] ?? null;
$componentCountNode = $schemaReference['componentCount'] ?? null;
if (
    !is_array($moduleCountNode)
    || !is_array($componentCountNode)
    || ($reference['moduleCount'] ?? null) !== count($modules)
    || ($reference['componentCount'] ?? null) !== count($tags)
    || ($moduleCountNode['const'] ?? null) !== count($modules)
    || ($componentCountNode['const'] ?? null) !== count($tags)
    || ($schemaModules['minItems'] ?? null) !== count($modules)
    || ($schemaModules['maxItems'] ?? null) !== count($modules)
) {
    throw new RuntimeException(
        'Material parity document, schema, module map and public tags must agree.',
    );
}

$sequentialIds = array_values($ids);
sort($sequentialIds, SORT_NUMERIC);
$componentCount = count($ids);
if ($sequentialIds !== range(1, $componentCount)) {
    throw new RuntimeException(
        "Material component IDs must be sequential from 1 through {$componentCount}.",
    );
}

$moduleTags = [];
foreach ($modules as $module => $components) {
    foreach ($components as $tag) {
        $moduleTags[$tag] = true;
    }
}
if (array_keys($moduleTags) !== array_keys($tags)) {
    throw new RuntimeException('Material modules and public tags must match exactly.');
}

$themes = [
    ['mode' => ThemeMode::Light, 'theme' => Themes::light(), 'name' => 'neutral-light'],
    ['mode' => ThemeMode::Dark, 'theme' => Themes::dark(), 'name' => 'neutral-dark'],
    ['mode' => ThemeMode::Light, 'theme' => Themes::pamLight(), 'name' => 'pam-light'],
    ['mode' => ThemeMode::Dark, 'theme' => Themes::pamDark(), 'name' => 'pam-dark'],
];
$semanticContrastPairs = [
    [ColorToken::PrimaryForeground, ColorToken::Primary],
    [ColorToken::SecondaryForeground, ColorToken::Secondary],
    [ColorToken::SuccessForeground, ColorToken::Success],
    [ColorToken::WarningForeground, ColorToken::Warning],
    [ColorToken::InfoForeground, ColorToken::Info],
    [ColorToken::DestructiveForeground, ColorToken::Destructive],
];
foreach ($themes as ['theme' => $theme, 'name' => $themeName]) {
    if ($theme->contrastRatio(ColorToken::Outline, ColorToken::Surface) < 3.0) {
        throw new RuntimeException("{$themeName} outline must retain 3:1 non-text contrast.");
    }
    foreach ($semanticContrastPairs as [$foreground, $background]) {
        if ($theme->contrastRatio($foreground, $background) < 4.5) {
            throw new RuntimeException(
                "{$themeName} {$foreground->name}/{$background->name} must retain 4.5:1 contrast.",
            );
        }
    }
}
$variants = ['elevated', 'flat', 'tonal', 'outlined', 'text', 'plain'];
$densities = ['default', 'comfortable', 'compact'];
$states = [
    [],
    ['disabled' => true],
    ['selected' => true],
    ['loading' => true],
];

$iconClass = $tags['p-icon'];
try {
    $iconClass::make(['icon' => 'not-in-the-native-icon-catalog'])->toElement();
    throw new RuntimeException('Unknown native icons must fail before reaching a blank view.');
} catch (InvalidArgumentException) {
    // Expected: silent blank icon rendering is not a valid product fallback.
}
$iconTheme = Themes::pamLight();
PamUI::theme($iconTheme, $iconTheme);
PamUI::mode(ThemeMode::Light);
$semanticIconStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PIcon',
    'color' => 'secondary',
], $iconTheme);
$customIconColor = 0xFF7DD3FC;
foreach ([
    ['StarIcon', [], new Style(tintColor: 0xFF123456), 0xFF123456],
    ['Icon', ['icon' => 'StarIcon'], new Style(textColor: 0xFF654321), 0xFF654321],
    ['PIcon', ['icon' => 'StarIcon', 'color' => 'secondary'], new Style(tintColor: 0xFF123456, textColor: 0xFF654321), 0xFF123456],
    ['Icon', ['icon' => 'StarIcon', 'color' => 0xFFABCDEF], null, 0xFFABCDEF],
] as [$iconPart, $iconProps, $iconOverride, $expectedColor]) {
    $styledIcon = \Pam\MobileUi\Rendering\ComponentRenderer::render(
        $iconPart, $iconProps, [], [], $iconOverride, null,
    );
    $styledIconHost = $styledIcon->properties()[PropKey::HostProperties->value] ?? null;
    if (!$styledIconHost instanceof BinaryValue
        || (Wire::decodeMap($styledIconHost->bytes)['color'] ?? null) !== $expectedColor) {
        throw new RuntimeException('Icon host must honor style color overrides without losing explicit colors.');
    }
}
$customIconStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PIcon',
    'color' => $customIconColor,
], $iconTheme);
$secondaryIcon = $iconClass::make([
    'name' => 'StarIcon',
    'color' => 'secondary',
])->toElement();
$secondaryIconHost =
    $secondaryIcon->properties()[PropKey::HostProperties->value] ?? null;
if (
    !$semanticIconStyle instanceof Style
    || !$customIconStyle instanceof Style
    || !$secondaryIconHost instanceof BinaryValue
    || $semanticIconStyle->textColor
        !== $iconTheme->color(ColorToken::Secondary)
    || $customIconStyle->textColor !== $customIconColor
    || (Wire::decodeMap($secondaryIconHost->bytes)['color'] ?? null)
        !== $iconTheme->color(ColorToken::Secondary)
) {
    throw new RuntimeException(
        'p-icon must resolve semantic and custom colors into a visible native tint.',
    );
}

$switchClass = $tags['p-switch'];
$errorSwitch = $switchClass::make([
    'label' => 'Error state',
    'checked' => true,
    'color' => 'error',
])->toElement();
$errorSwitchHost = $errorSwitch->properties()[PropKey::HostProperties->value] ?? null;
if (!$errorSwitchHost instanceof BinaryValue) {
    throw new RuntimeException('p-switch must expose its native Material color contract.');
}
$errorSwitchNative = Wire::decodeMap($errorSwitchHost->bytes);
if (
    ($errorSwitchNative['trackOnColor'] ?? null)
        !== $iconTheme->color(ColorToken::Destructive)
    || ($errorSwitchNative['activeThumbColor'] ?? null)
        !== $iconTheme->color(ColorToken::DestructiveForeground)
) {
    throw new RuntimeException(
        'p-switch semantic variants must reach the native track and selected thumb.',
    );
}

$readOnlySwitch = $switchClass::make([
    'label' => 'Read only state',
    'checked' => true,
    'readonly' => true,
])->toElement();
$readOnlySwitchHost = $readOnlySwitch->properties()[PropKey::HostProperties->value] ?? null;
if (
    !$readOnlySwitchHost instanceof BinaryValue
    || (Wire::decodeMap($readOnlySwitchHost->bytes)['readOnly'] ?? null) !== true
    || (Wire::decodeMap($readOnlySwitchHost->bytes)['interactionDisabled'] ?? null) !== true
) {
    throw new RuntimeException(
        'p-switch must forward its readonly alias to the native interaction guard.',
    );
}

$styleCases = 0;
$renderCases = 0;
foreach ($tags as $tag => $class) {
    if (!str_starts_with($tag, 'p-') || preg_match('/^p-[a-z][a-z0-9-]*$/D', $tag) !== 1) {
        throw new RuntimeException("Invalid Material tag {$tag}.");
    }
    $part = $class::componentName();
    if (($ids[$part] ?? null) === null) {
        throw new RuntimeException("Missing Material ID for {$part}.");
    }

    foreach ($themes as ['mode' => $mode, 'theme' => $theme, 'name' => $themeName]) {
        PamUI::theme($theme, $theme);
        PamUI::mode($mode);
        foreach ($variants as $variant) {
            foreach ($densities as $density) {
                foreach ($states as $state) {
                    $props = [
                        '__materialComponent' => $part,
                        'variant' => $variant,
                        'density' => $density,
                        'text' => $part,
                        ...$state,
                    ];
                    $style = MaterialStyleResolver::resolve($props, $theme);
                    if (!$style instanceof Style) {
                        throw new RuntimeException(
                            "Material style missing for {$part} ({$themeName}/{$variant}/{$density}).",
                        );
                    }
                    $styleCases++;
                }
            }
        }

        $class::make([
            'text' => $part,
            'value' => 1,
            'accessibilityLabel' => $part,
        ])->toElement();
        $renderCases++;
    }
}

$formClass = $tags['p-form'];
$form = $formClass::make([
    'accessibilityLabel' => 'Profile form',
])->toElement();
$formHost = $form->properties()[PropKey::HostProperties->value] ?? null;
if ($form->kind() !== NodeKind::CustomView || !$formHost instanceof BinaryValue) {
    throw new RuntimeException(
        'p-form must render the native form host instead of a passive layout row.',
    );
}
$formProperties = Wire::decodeMap($formHost->bytes);
if (($formProperties['behavior'] ?? null) !== NativeBehavior::FormControl->value) {
    throw new RuntimeException(
        'p-form must retain native form-control behavior.',
    );
}

$calendarDayClass = $tags['p-calendar-day'];
$calendarDay = $calendarDayClass::make([
    'text' => '28',
    'value' => '2026-07-28',
    'accessibilityLabel' => 'July 28, 2026',
])->toElement();
if ($calendarDay->kind() !== NodeKind::Pressable) {
    throw new RuntimeException(
        'p-calendar-day must render an interactive native pressable.',
    );
}
$selectedCalendarDay = $calendarDayClass::make(
    [
        'selected' => true,
        'state' => 'selected',
        'accessibilityLabel' => 'July 15, 2026',
    ],
    Text::make('15'),
)->toElement();
$calendarDayIndicator = $selectedCalendarDay->children()[0] ?? null;
if (
    !$calendarDayIndicator instanceof \Pam\Native\Element
    || ($calendarDayIndicator->properties()[PropKey::Width->value] ?? null) !== 40.0
    || ($calendarDayIndicator->properties()[PropKey::Height->value] ?? null) !== 40.0
    || ($calendarDayIndicator->properties()[PropKey::BorderRadius->value] ?? null) !== 20.0
) {
    throw new RuntimeException(
        'p-calendar-day must place its state inside a 40dp circular indicator.',
    );
}
$rangeStartDay = $calendarDayClass::make(
    ['state' => 'range-start', 'selected' => true],
    Text::make('12'),
)->toElement();
$rangeStartTrack = $rangeStartDay->children()[0] ?? null;
if (
    !$rangeStartTrack instanceof \Pam\Native\Element
    || ($rangeStartTrack->properties()[PropKey::Left->value] ?? null) !== 24.0
    || ($rangeStartTrack->properties()[PropKey::Top->value] ?? null) !== 4.0
    || ($rangeStartTrack->properties()[PropKey::Width->value] ?? null) !== 24.0
    || ($rangeStartTrack->properties()[PropKey::Height->value] ?? null) !== 40.0
) {
    throw new RuntimeException(
        'p-calendar-day range start must fill only its trailing 40dp half-track.',
    );
}

$listClass = $tags['p-list'];
$list = $listClass::make([
    'accessibilityLabel' => 'Settings',
])->toElement();
if ($list->kind() !== NodeKind::Column) {
    throw new RuntimeException(
        'p-list must stack list items vertically instead of rendering a horizontal row.',
    );
}

$twoLineItemClass = $tags['p-list-item'];
$twoLineItem = $twoLineItemClass::make(
    ['accessibilityLabel' => 'Account'],
    Text::make('Account'),
    Text::make('Profile and security'),
)->toElement();
if (($twoLineItem->properties()[PropKey::Height->value] ?? null) !== 72.0) {
    throw new RuntimeException(
        'p-list-item must infer the 72dp two-line Material height.',
    );
}
$namedThreeLineItem = $twoLineItemClass::make([
    'lines' => 'three',
    'text' => 'Release notes',
])->toElement();
if (($namedThreeLineItem->properties()[PropKey::Height->value] ?? null) !== 88.0) {
    throw new RuntimeException(
        'p-list-item must normalize the public named three-line profile to 88dp.',
    );
}
$compactItem = $twoLineItemClass::make([
    'density' => 'compact',
    'lines' => 1,
    'text' => 'Account',
])->toElement();
if (($compactItem->properties()[PropKey::Height->value] ?? null) !== 48.0) {
    throw new RuntimeException(
        'Compact one-line p-list-item must use a 48dp visual height.',
    );
}
$iconClass = $tags['p-icon'];
$oneLineNavigationItem = $twoLineItemClass::make(
    ['accessibilityLabel' => 'Open settings'],
    $iconClass::make(['icon' => 'settings']),
    Text::make('Settings'),
    $iconClass::make(['icon' => 'chevron-right']),
)->toElement();
if (($oneLineNavigationItem->properties()[PropKey::Height->value] ?? null) !== 56.0) {
    throw new RuntimeException(
        'Icon + label + trailing icon p-list-item anatomy must remain one line (56dp).',
    );
}

$textFieldClass = $tags['p-text-field'];
$filledTextField = $textFieldClass::make([
    'label' => 'Email',
    'modelValue' => 'team@pam.dev',
    'error' => true,
])->toElement();
$filledTextFieldHost = $filledTextField->properties()[PropKey::HostProperties->value] ?? null;
$outlinedTextField = $textFieldClass::make([
    'label' => 'Email',
    'variant' => 'outlined',
    'error' => true,
])->toElement();
$outlinedTextFieldHost = $outlinedTextField->properties()[PropKey::HostProperties->value] ?? null;
$underlinedTextField = $textFieldClass::make([
    'label' => 'Email',
    'variant' => 'underlined',
])->toElement();
$underlinedTextFieldHost = $underlinedTextField->properties()[PropKey::HostProperties->value] ?? null;
$filledTextFieldInput = null;
$filledTextFieldStack = [$filledTextField];
while ($filledTextFieldStack !== []) {
    $candidate = array_pop($filledTextFieldStack);
    if ($candidate->kind() === NodeKind::Input) {
        $filledTextFieldInput = $candidate;
        break;
    }
    array_push($filledTextFieldStack, ...$candidate->children());
}
if (
    !$filledTextFieldHost instanceof BinaryValue
    || !$outlinedTextFieldHost instanceof BinaryValue
    || !$underlinedTextFieldHost instanceof BinaryValue
    || !$filledTextFieldInput instanceof \Pam\Native\Element
    || ($filledTextFieldInput->properties()[
        PropKey::AccessibilityLabel->value
    ] ?? null) !== 'Email'
    || (Wire::decodeMap($filledTextFieldHost->bytes)['indicatorOnly'] ?? null) !== false
    || (Wire::decodeMap($outlinedTextFieldHost->bytes)['indicatorOnly'] ?? null) !== false
    || (Wire::decodeMap($underlinedTextFieldHost->bytes)['indicatorOnly'] ?? null) !== true
    || (Wire::decodeMap($filledTextFieldHost->bytes)['outlineWidth'] ?? null) !== 2.0
    || (Wire::decodeMap($outlinedTextFieldHost->bytes)['outlineWidth'] ?? null) !== 2.0
) {
    throw new RuntimeException(
        'Material fields must use a 2dp focused/error indicator while outlined fields retain a full outline.',
    );
}

$sliderClass = $tags['p-slider'];
$slider = $sliderClass::make(['value' => 40])->toElement();
$sliderHost = $slider->properties()[PropKey::HostProperties->value] ?? null;
$sliderTrack = $findByValue($slider, 'pam:slider-track');
$sliderThumb = $findByValue($slider, 'pam:slider-thumb');
if (
    !$sliderHost instanceof BinaryValue
    || !$sliderTrack instanceof \Pam\Native\Element
    || !$sliderThumb instanceof \Pam\Native\Element
) {
    throw new RuntimeException(
        'p-slider must expose its native host, track and handle anatomy.',
    );
}
$sliderNative = Wire::decodeMap($sliderHost->bytes);
if (
    ($sliderNative['trackThickness'] ?? null) !== 4.0
    || ($sliderNative['thumbWidth'] ?? null) !== 20.0
    || ($sliderNative['thumbHeight'] ?? null) !== 20.0
    || ($sliderNative['thumbTrackGap'] ?? null) !== 0.0
    || ($sliderNative['stateLayerSize'] ?? null) !== 40.0
    || ($sliderNative['stopIndicatorSize'] ?? null) !== 0.0
    || ($sliderNative['tickSize'] ?? null) !== 4.0
    || ($sliderTrack->properties()[PropKey::Height->value] ?? null) !== 4.0
    || ($sliderThumb->properties()[PropKey::Width->value] ?? null) !== 20.0
    || ($sliderThumb->properties()[PropKey::Height->value] ?? null) !== 20.0
) {
    throw new RuntimeException(
        'p-slider must preserve the PAM Material track, circular handle and state-layer metrics at the native boundary.',
    );
}

$rangeSliderClass = $tags['p-range-slider'];
$labelledRangeSlider = $rangeSliderClass::make([
    'modelValue' => [20, 80],
    'thumbLabel' => 'always',
])->toElement();
$labelledRangeTrack = $findByValue($labelledRangeSlider, 'pam:slider-track');
if (
    !$labelledRangeTrack instanceof \Pam\Native\Element
    || ($labelledRangeSlider->properties()[PropKey::MinHeight->value] ?? null) !== 80.0
    || ($labelledRangeTrack->properties()[PropKey::MarginTop->value] ?? null) !== 48.0
) {
    throw new RuntimeException(
        'A labelled range slider must reserve an 80dp value-indicator lane and place its track below the label.',
    );
}
$tickLabelledRangeSlider = $rangeSliderClass::make([
    'modelValue' => [20, 80],
    'step' => 25,
    'tickLabels' => ['0', '25', '50', '75', '100'],
])->toElement();
$tickLabelledRangeHost = $tickLabelledRangeSlider
    ->properties()[PropKey::HostProperties->value] ?? null;
$tickLabelledRangeNative = $tickLabelledRangeHost instanceof BinaryValue
    ? Wire::decodeMap($tickLabelledRangeHost->bytes)
    : [];
if (
    !$tickLabelledRangeHost instanceof BinaryValue
    || ($tickLabelledRangeSlider->properties()[PropKey::MinHeight->value] ?? null) !== 72.0
    || ($tickLabelledRangeNative['tickLabels'] ?? null)
        !== '["0","25","50","75","100"]'
    || ($tickLabelledRangeNative['showTicks'] ?? null) !== true
    || ($tickLabelledRangeNative['alwaysShowTicks'] ?? null) !== true
    || !is_int($tickLabelledRangeNative['tickLabelColor'] ?? null)
) {
    throw new RuntimeException(
        'Range-slider tick labels must reserve their own lane and reach the native host as visible labels.',
    );
}
$fullyLabelledRangeSlider = $rangeSliderClass::make([
    'modelValue' => [20, 80],
    'thumbLabel' => 'always',
    'tickLabels' => ['Low', 'High'],
])->toElement();
if (
    ($fullyLabelledRangeSlider->properties()[PropKey::MinHeight->value] ?? null)
        !== 104.0
) {
    throw new RuntimeException(
        'Value labels and tick labels must use separate non-overlapping slider lanes.',
    );
}
$verticalLabelledRangeSlider = $rangeSliderClass::make([
    'modelValue' => [20, 80],
    'orientation' => 2,
    'thumbLabel' => 'always',
])->toElement();
$verticalLabelledRangeTrack = $findByValue(
    $verticalLabelledRangeSlider,
    'pam:slider-track',
);
if (
    !$verticalLabelledRangeTrack instanceof \Pam\Native\Element
    || ($verticalLabelledRangeSlider->properties()[PropKey::Width->value] ?? null)
        !== 112.0
    || ($verticalLabelledRangeTrack->properties()[PropKey::MarginLeft->value] ?? null)
        !== 22.0
) {
    throw new RuntimeException(
        'Vertical orientation aliases must center the track and reserve an unclipped value-label lane: '
        .json_encode([
            'width' => $verticalLabelledRangeSlider
                ->properties()[PropKey::Width->value] ?? null,
            'trackMarginLeft' => $verticalLabelledRangeTrack instanceof \Pam\Native\Element
                ? ($verticalLabelledRangeTrack
                    ->properties()[PropKey::MarginLeft->value] ?? null)
                : null,
        ], JSON_THROW_ON_ERROR),
    );
}
$normalizedRangeSlider = $rangeSliderClass::make([
    'modelValue' => [83, 17],
    'minValue' => 0,
    'maxValue' => 100,
    'step' => 10,
])->toElement();
$normalizedRangeHost = $normalizedRangeSlider
    ->properties()[PropKey::HostProperties->value] ?? null;
$normalizedRangeNative = $normalizedRangeHost instanceof BinaryValue
    ? Wire::decodeMap($normalizedRangeHost->bytes)
    : [];
if (
    ($normalizedRangeNative['lowerValue'] ?? null) !== 20.0
    || ($normalizedRangeNative['upperValue'] ?? null) !== 80.0
    || ($normalizedRangeNative['value'] ?? null) !== 80.0
) {
    throw new RuntimeException(
        'Range-slider endpoints must clamp, snap and sort before crossing the native boundary.',
    );
}

$tabsClass = $tags['p-tabs'];
$tabClass = $tags['p-tab'];
$tabs = $tabsClass::make(
    ['value' => 'one'],
    $tabClass::make(['value' => 'one', 'text' => 'One']),
    $tabClass::make(['value' => 'two', 'text' => 'Two']),
)->toElement();
$tabsIndicator = $findByValue($tabs, 'pam:tabs-indicator');
if (
    !$tabsIndicator instanceof \Pam\Native\Element
    || ($tabsIndicator->properties()[PropKey::Height->value] ?? null) !== 3.0
) {
    throw new RuntimeException('p-tabs must render the 3dp Material active indicator.');
}
$selectedTabElement = $tabClass::make([
    'value' => 'one',
    'text' => 'One',
    'selected' => true,
])->toElement();
$selectedTabHost = $selectedTabElement
    ->properties()[PropKey::HostProperties->value] ?? null;
$selectedTabNative = $selectedTabHost instanceof BinaryValue
    ? Wire::decodeMap($selectedTabHost->bytes)
    : [];
if (
    ($selectedTabNative['selectedForegroundColor'] ?? null)
        !== ThemeManager::current()->color(ColorToken::Primary)
    || ($selectedTabNative['foregroundColor'] ?? null)
        !== ThemeManager::current()->color(ColorToken::MutedForeground)
) {
    throw new RuntimeException(
        'p-tab must keep selected text visible on the transparent Material tab surface.',
    );
}

$circularProgressClass = $tags['p-progress-circular'];
$circularProgress = $circularProgressClass::make(['value' => 50])->toElement();
$circularProgressHost = $circularProgress
    ->properties()[PropKey::HostProperties->value] ?? null;
if (!$circularProgressHost instanceof BinaryValue) {
    throw new RuntimeException('p-progress-circular must render a native progress host.');
}
$circularProgressNative = Wire::decodeMap($circularProgressHost->bytes);
if (($circularProgressNative['thickness'] ?? null) !== 4.0) {
    throw new RuntimeException(
        'p-progress-circular must preserve the 4dp Material stroke at the native boundary.',
    );
}

$bottomSheetClass = $tags['p-bottom-sheet'];
$bottomSheet = $bottomSheetClass::make(
    ['open' => true],
    Text::make('Sheet content'),
)->toElement();
$dragHandle = $findByValue($bottomSheet, 'pam:sheet-drag-indicator');
if (
    !$dragHandle instanceof \Pam\Native\Element
    || ($dragHandle->properties()[PropKey::Width->value] ?? null) !== 32.0
    || ($dragHandle->properties()[PropKey::Height->value] ?? null) !== 4.0
) {
    throw new RuntimeException(
        'p-bottom-sheet must render the Material 32x4dp drag handle.',
    );
}

$chipClass = $tags['p-chip'];
PamUI::theme(Themes::pamLight(), Themes::pamDark());
PamUI::mode(ThemeMode::Light);
$passiveChip = $chipClass::make(['text' => 'Stable'])->toElement();
$passiveChipLabel = $passiveChip->children()[0] ?? null;
if (
    $passiveChip->kind() !== NodeKind::Row
    || !$passiveChipLabel instanceof \Pam\Native\Element
    || ($passiveChipLabel->properties()[PropKey::TextColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::SecondaryForeground)
) {
    throw new RuntimeException(
        'A passive p-chip must stay passive and use its contrasting foreground.',
    );
}
$avatarClass = $tags['p-avatar'];
$avatar = $avatarClass::make([], Text::make('PA'))->toElement();
$avatarLabel = $avatar->children()[0] ?? null;
if (
    !$avatarLabel instanceof \Pam\Native\Element
    || ($avatarLabel->properties()[PropKey::TextColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::SecondaryForeground)
) {
    throw new RuntimeException(
        'p-avatar content must inherit a foreground that contrasts its surface.',
    );
}
$tonalAvatar = $avatarClass::make(
    ['variant' => 'tonal'],
    Text::make('PA'),
)->toElement();
$borderedAvatar = $avatarClass::make(
    ['border' => true],
    Text::make('PA'),
)->toElement();
if (
    ($tonalAvatar->properties()[PropKey::BackgroundColor->value] ?? null)
        === Themes::pamLight()->color(ColorToken::Secondary)
    || (($tonalAvatar->children()[0] ?? null)?->properties()[PropKey::TextColor->value]
        ?? null) !== Themes::pamLight()->color(ColorToken::Secondary)
    || ($borderedAvatar->properties()[PropKey::BorderWidth->value] ?? null) !== 2.0
    || ($borderedAvatar->properties()[PropKey::BorderColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::Surface)
) {
    throw new RuntimeException(
        'Tonal and bordered avatars must retain distinct, contrasting visual contracts.',
    );
}
$itemClass = $tags['p-item'];
$selectedItem = $itemClass::make(
    ['selected' => true],
    Text::make('Design'),
)->toElement();
$selectedItemLabel = $selectedItem->children()[0] ?? null;
if (
    !$selectedItemLabel instanceof \Pam\Native\Element
    || ($selectedItemLabel->properties()[PropKey::TextColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::AccentForeground)
) {
    throw new RuntimeException(
        'Selected p-item content must contrast its selected surface.',
    );
}
$slideGroupItemStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PSlideGroupItem',
    'selected' => true,
], Themes::pamLight());
if (
    !$slideGroupItemStyle instanceof Style
    || $slideGroupItemStyle->widthPercent !== null
    || $slideGroupItemStyle->minWidth !== 48.0
    || $slideGroupItemStyle->textColor
        !== Themes::pamLight()->color(ColorToken::SecondaryForeground)
) {
    throw new RuntimeException(
        'p-slide-group-item must render as a compact, contrasting horizontal pill.',
    );
}
$tonalSheetStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PSheet',
    'variant' => 'tonal',
], Themes::pamLight());
if (
    !$tonalSheetStyle instanceof Style
    || $tonalSheetStyle->backgroundColor
        !== Themes::pamLight()->color(ColorToken::Secondary)
    || $tonalSheetStyle->textColor
        !== Themes::pamLight()->color(ColorToken::SecondaryForeground)
) {
    throw new RuntimeException(
        'Tonal sheets must pair their secondary surface with its foreground token.',
    );
}
$chipClosed = false;
$closableChip = $chipClass::make([
    'text' => 'Draft',
    'closable' => true,
])->onClose(static function () use (&$chipClosed): void {
    $chipClosed = true;
})->toElement();
$chipClose = $closableChip->children()[1] ?? null;
if (
    $closableChip->kind() !== NodeKind::Row
    || !$chipClose instanceof \Pam\Native\Element
    || $chipClose->kind() !== NodeKind::Pressable
    || ($chipClose->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Close Draft'
    || !isset($chipClose->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'A closable p-chip must keep a passive root and expose a labelled close affordance.',
    );
}
$chipClose->events()[EventKind::Press->value]();
if (!$chipClosed) {
    throw new RuntimeException('p-chip close must emit its close callback.');
}

$stepperClass = $tags['p-stepper'];
$stepperHeaderClass = $tags['p-stepper-header'];
$stepperItemClass = $tags['p-stepper-item'];
$stepperWindowClass = $tags['p-stepper-window'];
$stepperWindowItemClass = $tags['p-stepper-window-item'];
$stepperActionsClass = $tags['p-stepper-actions'];
$stepper = $stepperClass::make(
    ['value' => 'delivery'],
    $stepperHeaderClass::make(
        $stepperItemClass::make(['value' => 'account'], Text::make('Account')),
        $stepperItemClass::make(['value' => 'delivery'], Text::make('Delivery')),
    ),
    $stepperWindowClass::make(
        $stepperWindowItemClass::make(
            ['value' => 'account'],
            Text::make('Account details'),
        ),
        $stepperWindowItemClass::make(
            ['value' => 'delivery'],
            Text::make('Delivery details'),
        ),
    ),
    $stepperActionsClass::make(Text::make('Continue')),
)->toElement();
$stepperWindow = $stepper->children()[1] ?? null;
$stepperActions = $stepper->children()[2] ?? null;
$accountPanel = $stepperWindow?->children()[0] ?? null;
$deliveryPanel = $stepperWindow?->children()[1] ?? null;
if (
    !$stepperWindow instanceof \Pam\Native\Element
    || !$stepperActions instanceof \Pam\Native\Element
    || !$accountPanel instanceof \Pam\Native\Element
    || !$deliveryPanel instanceof \Pam\Native\Element
    || ($stepperWindow->properties()[PropKey::Value->value] ?? null)
        !== 'pam:tabs-content-wrapper'
    || ($accountPanel->properties()[PropKey::Value->value] ?? null)
        !== 'pam:tabs-content:account'
    || ($deliveryPanel->properties()[PropKey::Value->value] ?? null)
        !== 'pam:tabs-content:delivery'
    || $stepperActions->kind() !== NodeKind::Row
) {
    throw new RuntimeException(
        'p-stepper must coordinate selected panels and render actions as actions.',
    );
}

$carouselClass = $tags['p-carousel'];
$carouselItemClass = $tags['p-carousel-item'];
$carousel = $carouselClass::make(
    ['value' => 'details', 'cycle' => true],
    $carouselItemClass::make(['value' => 'overview'], Text::make('Overview')),
    $carouselItemClass::make(['value' => 'details'], Text::make('Details')),
)->toElement();
$carouselHost = $carousel->properties()[PropKey::HostProperties->value] ?? null;
$firstCarouselItem = $carousel->children()[0] ?? null;
$secondCarouselItem = $carousel->children()[1] ?? null;
$firstCarouselHost =
    $firstCarouselItem?->properties()[PropKey::HostProperties->value] ?? null;
if (
    !$carouselHost instanceof BinaryValue
    || !$firstCarouselItem instanceof \Pam\Native\Element
    || !$firstCarouselHost instanceof BinaryValue
    || !$secondCarouselItem instanceof \Pam\Native\Element
) {
    throw new RuntimeException('p-carousel must render native coordinated pages.');
}
$carouselNative = Wire::decodeMap($carouselHost->bytes);
$firstCarouselNative = Wire::decodeMap($firstCarouselHost->bytes);
if (
    ($carouselNative['behavior'] ?? null) !== NativeBehavior::Tabs->value
    || ($carouselNative['navigationKind'] ?? null) !== 1
    || ($firstCarouselNative['behavior'] ?? null)
        !== NativeBehavior::TabsTrigger->value
    || ($firstCarouselNative['value'] ?? null) !== 'overview'
    || ($firstCarouselItem->properties()[PropKey::Selected->value] ?? false)
        !== false
    || ($secondCarouselItem->properties()[PropKey::Selected->value] ?? null)
        !== true
) {
    throw new RuntimeException(
        'p-carousel must coordinate its selected page for swipe and cycle behavior.',
    );
}

$verticalStepperClass = $tags['p-stepper-vertical'];
$verticalStepperItemClass = $tags['p-stepper-vertical-item'];
$verticalStepperActionsClass = $tags['p-stepper-vertical-actions'];
$verticalStepChange = null;
$verticalStepper = $verticalStepperClass::make(
    ['value' => 'details'],
    $verticalStepperItemClass::make(
        ['value' => 'account', 'title' => 'Account'],
        Text::make('Account'),
        Text::make('Account details'),
    ),
    $verticalStepperItemClass::make(
        ['value' => 'details', 'title' => 'Details'],
        Text::make('Details'),
        Text::make('Delivery details'),
        $verticalStepperActionsClass::make(Text::make('Continue')),
    ),
)->onChange(
    static function (string $value) use (&$verticalStepChange): void {
        $verticalStepChange = $value;
    },
)->toElement();
$verticalStepperHost =
    $verticalStepper->properties()[PropKey::HostProperties->value] ?? null;
$collapsedStep = $verticalStepper->children()[0] ?? null;
$expandedStep = $verticalStepper->children()[1] ?? null;
$collapsedTrigger = $collapsedStep?->children()[0] ?? null;
$collapsedContent = $collapsedStep?->children()[1] ?? null;
$expandedContent = $expandedStep?->children()[1] ?? null;
$collapsedToggle = $collapsedStep?->events()[EventKind::Toggle->value] ?? null;
if (
    !$verticalStepperHost instanceof BinaryValue
    || !$collapsedTrigger instanceof \Pam\Native\Element
    || !$collapsedContent instanceof \Pam\Native\Element
    || !$expandedContent instanceof \Pam\Native\Element
    || !$collapsedToggle instanceof Closure
) {
    throw new RuntimeException(
        'p-stepper-vertical must render coordinated native disclosure anatomy.',
    );
}
$verticalStepperNative = Wire::decodeMap($verticalStepperHost->bytes);
$collapsedToggle(true);
if (
    ($verticalStepperNative['behavior'] ?? null)
        !== NativeBehavior::AccordionGroup->value
    || ($collapsedTrigger->properties()[PropKey::Value->value] ?? null)
        !== 'pam:accordion-trigger'
    || ($collapsedContent->properties()[PropKey::Value->value] ?? null)
        !== 'pam:accordion-content'
    || ($collapsedContent->properties()[PropKey::Visible->value] ?? null)
        !== false
    || array_key_exists(PropKey::Visible->value, $expandedContent->properties())
    || $verticalStepChange !== 'account'
) {
    throw new RuntimeException(
        'p-stepper-vertical must expand one step with native state and typed change events.',
    );
}

foreach (['p-time-picker'] as $timePickerTag) {
    $timePickerClass = $tags[$timePickerTag];
    $timePicker = $timePickerClass::make([
        'accessibilityLabel' => 'Select time',
        'format' => '24hr',
    ])->toElement();
    $timePickerHost =
        $timePicker->properties()[PropKey::HostProperties->value] ?? null;
    $timePickerDisplay = $timePicker->children()[0] ?? null;
    if (
        $timePicker->kind() !== NodeKind::CustomView
        || !$timePickerHost instanceof BinaryValue
        || !$timePickerDisplay instanceof \Pam\Native\Element
        || ($timePickerDisplay->properties()[PropKey::Text->value] ?? null)
            !== 'Select time'
    ) {
        throw new RuntimeException(
            "{$timePickerTag} must use the native host with a visible value.",
        );
    }
    $timePickerProperties = Wire::decodeMap($timePickerHost->bytes);
    if (
        ($timePickerProperties['behavior'] ?? null)
            !== NativeBehavior::DateTimePicker->value
        || ($timePickerProperties['mode'] ?? null) !== ComponentMode::Time->value
        || ($timePickerProperties['is24Hour'] ?? null) !== true
    ) {
        throw new RuntimeException(
            "{$timePickerTag} must open a native time picker, not calendar behavior.",
        );
    }
}

$timePickerAmPm = $tags['p-time-picker']::make([
    'modelValue' => '14:35',
    'format' => 'ampm',
])->toElement();
$timePickerSeconds = $tags['p-time-picker']::make([
    'modelValue' => '14:35',
    'format' => '24hr',
    'useSeconds' => true,
])->toElement();
if (
    ($timePickerAmPm->children()[0]->properties()[PropKey::Text->value] ?? null)
        !== '2:35 PM'
    || ($timePickerSeconds->children()[0]->properties()[PropKey::Text->value] ?? null)
        !== '14:35:00'
) {
    throw new RuntimeException(
        'Time picker display must visibly represent AM/PM and seconds formats.',
    );
}

$dateInputClass = $tags['p-date-input'];
$dateInput = $dateInputClass::make([
    'modelValue' => '2026-07-28',
    'format' => 'DD/MM/YYYY',
    'minimumDate' => '2026-07-01',
    'maximumDate' => '2026-07-31',
    'readonly' => true,
    'accessibilityLabel' => 'Select date',
])->toElement();
$dateInputHost = $dateInput->properties()[PropKey::HostProperties->value] ?? null;
if (!$dateInputHost instanceof BinaryValue) {
    throw new RuntimeException('p-date-input must render a native date picker host.');
}
$dateInputNative = Wire::decodeMap($dateInputHost->bytes);
if (
    $dateInput->kind() !== NodeKind::CustomView
    || ($dateInputNative['behavior'] ?? null)
        !== NativeBehavior::DateTimePicker->value
    || ($dateInputNative['mode'] ?? null) !== ComponentMode::Date->value
    || ($dateInputNative['minimumDate'] ?? null) !== '2026-07-01'
    || ($dateInputNative['maximumDate'] ?? null) !== '2026-07-31'
    || ($dateInputNative['readOnly'] ?? null) !== true
    || ($dateInput->properties()[PropKey::Enabled->value] ?? null) !== false
) {
    throw new RuntimeException(
        'p-date-input must open a bounded native date selector instead of a plain text field.',
    );
}
$dateInputText = null;
$dateInputQueue = [$dateInput];
while ($dateInputQueue !== []) {
    $candidate = array_shift($dateInputQueue);
    $candidateText = $candidate->properties()[PropKey::Text->value]
        ?? $candidate->properties()[PropKey::Value->value]
        ?? null;
    if ($candidateText === '28/07/2026') {
        $dateInputText = $candidateText;
        break;
    }
    array_push($dateInputQueue, ...$candidate->children());
}
if ($dateInputText !== '28/07/2026') {
    throw new RuntimeException(
        'p-date-input must format controlled modelValue for display without changing its ISO value.',
    );
}

$ratingClass = $tags['p-rating'];
$rating = $ratingClass::make([
    'modelValue' => 3.5,
    'length' => 5,
    'halfIncrements' => true,
])->toElement();
$ratingHost = $rating->properties()[PropKey::HostProperties->value] ?? null;
if (!$ratingHost instanceof BinaryValue) {
    throw new RuntimeException('p-rating must retain native rating behavior.');
}
$ratingProperties = Wire::decodeMap($ratingHost->bytes);
if (
    ($ratingProperties['behavior'] ?? null) !== NativeBehavior::Slider->value
    || ($ratingProperties['rating'] ?? null) !== true
    || ($ratingProperties['min'] ?? null) !== 0.0
    || ($ratingProperties['max'] ?? null) !== 5.0
    || ($ratingProperties['step'] ?? null) !== 0.5
    || ($ratingProperties['value'] ?? null) !== 3.5
) {
    throw new RuntimeException(
        'p-rating must normalize its native range to its star length and increment.',
    );
}

$textFieldClass = $tags['p-text-field'];
$clearedValue = null;
$clearableField = $textFieldClass::make([
    'label' => 'Name',
    'modelValue' => 'Ada',
    'clearable' => true,
])->onChange(
    static function (string $value) use (&$clearedValue): void {
        $clearedValue = $value;
    },
)->toElement();
$fieldLayout = $clearableField->children()[0] ?? null;
$clearButton = $fieldLayout?->children()[2] ?? null;
if (
    !$clearButton instanceof \Pam\Native\Element
    || $clearButton->kind() !== NodeKind::Pressable
    || ($clearButton->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Clear Name'
    || ($clearButton->properties()[PropKey::HitSlopLeft->value] ?? null) !== 6.0
    || !isset($clearButton->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'A dirty clearable field must expose an accessible 44dp clear action.',
    );
}
$clearButton->events()[EventKind::Press->value]();
if ($clearedValue !== '') {
    throw new RuntimeException(
        'The clearable field action must emit an empty model value.',
    );
}
$emptyField = $textFieldClass::make([
    'modelValue' => '',
    'clearable' => true,
])->toElement();
if (count(($emptyField->children()[0] ?? null)?->children() ?? []) !== 1) {
    throw new RuntimeException(
        'An empty clearable field must not render a redundant clear action.',
    );
}

$itemClass = $tags['p-item'];
foreach (['p-text-field', 'p-textarea', 'p-password-field', 'p-masked-field', 'p-currency-field'] as $fieldTag) {
    foreach ([['readonly' => true], ['readOnly' => true], ['isReadOnly' => true], ['editable' => false]] as $readOnlyProps) {
        $readOnlyField = $tags[$fieldTag]::make([
            ...$readOnlyProps,
            'label' => 'Protected', 'modelValue' => '1234', 'clearable' => true,
        ])->onChange(static function (string $value): void {
            throw new RuntimeException('Read-only fields must not emit clear actions.');
        })->toElement();
        $pending = [$readOnlyField];
        $hasReadOnlyInput = false;
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node->kind() === NodeKind::Input) {
                if (($node->properties()[PropKey::InputEditable->value] ?? null) !== false) {
                    throw new RuntimeException($fieldTag.' must retain native read-only editing semantics.');
                }
                $hasReadOnlyInput = true;
            }
            if (($node->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Clear Protected') {
                throw new RuntimeException($fieldTag.' must not expose a mutating clear action when read-only.');
            }
            array_push($pending, ...$node->children());
        }
        if (!$hasReadOnlyInput) {
            throw new RuntimeException('Read-only fixture must include a native input.');
        }
    }
}
$item = $itemClass::make([
    'label' => 'Grid view',
    'value' => 'grid',
    'selected' => true,
    'accessibilityLabel' => 'Grid view',
])->toElement();
$itemHost = $item->properties()[PropKey::HostProperties->value] ?? null;
if (!$itemHost instanceof BinaryValue) {
    throw new RuntimeException('p-item must retain native selection behavior.');
}
$itemProperties = Wire::decodeMap($itemHost->bytes);
if (
    ($itemProperties['abstractSelectionItem'] ?? null) !== true
    || ($itemProperties['checked'] ?? null) !== true
    || !isset($itemProperties['foregroundColor'])
    || !isset($itemProperties['selectedForegroundColor'])
    || !isset($itemProperties['selectedContainerColor'])
    || $itemProperties['foregroundColor'] === $itemProperties['selectedForegroundColor']
    || ($item->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::Button->value
    || count($item->children()) !== 1
) {
    throw new RuntimeException(
        'p-item must be a selected button without checkbox chrome or semantics.',
    );
}

$itemGroupClass = $tags['p-item-group'];
$selectedItems = null;
$itemGroup = $itemGroupClass::make(
    ['value' => ['list']],
    $itemClass::make([
        'label' => 'Grid view',
        'value' => 'grid',
    ]),
)->onChange(
    static function (array $value) use (&$selectedItems): void {
        $selectedItems = $value;
    },
)->toElement();
$groupItem = $itemGroup->children()[0] ?? null;
$itemGroupStyle = $itemGroup->properties();
if (
    ($itemGroupStyle[PropKey::FlexDirection->value] ?? null)
        !== FlexDirection::Column->value
    || ($itemGroupStyle[PropKey::WidthPercent->value] ?? null) !== 100.0
) {
    throw new RuntimeException(
        'p-item-group must stack full-width items vertically by default.',
    );
}
$groupToggle = $groupItem?->events()[EventKind::Toggle->value] ?? null;
if (!$groupToggle instanceof Closure) {
    throw new RuntimeException(
        'p-item-group must forward its change contract to p-item.',
    );
}
$groupToggle(true);
if ($selectedItems !== ['list', 'grid']) {
    throw new RuntimeException(
        'p-item-group must emit the updated selected value list.',
    );
}

$chipGroupClass = $tags['p-chip-group'];
$chipClass = $tags['p-chip'];
$selectedChip = null;
$chipGroup = $chipGroupClass::make(
    ['value' => 'news'],
    $chipClass::make(['text' => 'News', 'value' => 'news']),
    $chipClass::make(['text' => 'Sports', 'value' => 'sports']),
)->onChange(
    static function (string $value) use (&$selectedChip): void {
        $selectedChip = $value;
    },
)->toElement();
$sportsChip = $chipGroup->children()[1] ?? null;
$sportsPress = $sportsChip?->events()[EventKind::Press->value] ?? null;
if (!$sportsPress instanceof Closure) {
    throw new RuntimeException(
        'p-chip-group must forward its change contract to p-chip.',
    );
}
$sportsPress();
if ($selectedChip !== 'sports') {
    throw new RuntimeException(
        'Single p-chip-group selection must emit the selected chip value.',
    );
}

$selectedChips = null;
$multipleChipGroup = $chipGroupClass::make(
    ['value' => ['news'], 'multiple' => true],
    $chipClass::make(['text' => 'Sports', 'value' => 'sports']),
)->onChange(
    static function (array $value) use (&$selectedChips): void {
        $selectedChips = $value;
    },
)->toElement();
$multiplePress = ($multipleChipGroup->children()[0] ?? null)?->events()[
    EventKind::Press->value
] ?? null;
if (!$multiplePress instanceof Closure) {
    throw new RuntimeException(
        'Multiple p-chip-group must bind chip presses.',
    );
}
$multiplePress();
if ($selectedChips !== ['news', 'sports']) {
    throw new RuntimeException(
        'Multiple p-chip-group selection must emit the updated value list.',
    );
}

$buttonToggleClass = $tags['p-btn-toggle'];
$buttonClass = $tags['p-btn'];
$selectedButton = null;
$buttonToggle = $buttonToggleClass::make(
    ['value' => 'left', 'block' => true],
    $buttonClass::make(['text' => 'Left', 'value' => 'left']),
    $buttonClass::make(['text' => 'Center', 'value' => 'center']),
    $buttonClass::make(['text' => 'Right', 'value' => 'right']),
)->onChange(
    static function (string $value) use (&$selectedButton): void {
        $selectedButton = $value;
    },
)->toElement();
$toggleProperties = $buttonToggle->properties();
$toggleChildren = $buttonToggle->children()[0]->children();
$toggleLeading = $toggleChildren[0]->properties();
$toggleMiddle = $toggleChildren[1]->properties();
$toggleTrailing = $toggleChildren[2]->properties();
if (
    $buttonToggle->kind() !== NodeKind::Scroll
    || ($toggleProperties[PropKey::ScrollHorizontal->value] ?? null) !== true
    || isset($toggleProperties[PropKey::Height->value])
    || ($toggleProperties[PropKey::Gap->value] ?? null) !== 0.0
    || ($toggleProperties[PropKey::MinHeight->value] ?? null) !== 48.0
    || ($toggleProperties[PropKey::WidthPercent->value] ?? null) !== 100.0
    || ($toggleLeading[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::ToggleButton->value
    || ($toggleLeading[PropKey::HitSlopLeft->value] ?? null) !== 0.0
    || ($toggleLeading[PropKey::HitSlopTop->value] ?? null) !== 4.0
    || ($toggleLeading[PropKey::Selected->value] ?? null) !== true
    || ($toggleLeading[PropKey::AccessibilityCheckedState->value] ?? null) !== AccessibilityCheckedState::Checked->value
    || ($toggleMiddle[PropKey::AccessibilityCheckedState->value] ?? null) !== AccessibilityCheckedState::Unchecked->value
    || ($toggleLeading[PropKey::BackgroundColor->value] ?? null)
        !== ThemeManager::current()->color(ColorToken::Secondary)
    || ($toggleLeading[PropKey::FlexGrow->value] ?? null) !== 1.0
    || ($toggleLeading[PropKey::BorderWidth->value] ?? null) !== 1.0
    || ($toggleLeading[PropKey::MarginLeft->value] ?? null) !== 0.0
    || ($toggleLeading[PropKey::BorderTopLeftRadius->value] ?? null) !== 20.0
    || ($toggleLeading[PropKey::BorderTopRightRadius->value] ?? null) !== 0.0
    || ($toggleMiddle[PropKey::BorderWidth->value] ?? null) !== 1.0
    || ($toggleMiddle[PropKey::MarginLeft->value] ?? null) !== -1.0
    || ($toggleMiddle[PropKey::BorderTopLeftRadius->value] ?? null) !== 0.0
    || ($toggleMiddle[PropKey::BorderTopRightRadius->value] ?? null) !== 0.0
    || ($toggleTrailing[PropKey::BorderWidth->value] ?? null) !== 1.0
    || ($toggleTrailing[PropKey::MarginLeft->value] ?? null) !== -1.0
    || ($toggleTrailing[PropKey::BorderTopLeftRadius->value] ?? null) !== 0.0
    || ($toggleTrailing[PropKey::BorderTopRightRadius->value] ?? null) !== 20.0
) {
    throw new RuntimeException(
        'p-btn-toggle must render a connected 48 dp segmented control with one shared outline and a visible selected state.',
    );
}
$rightButtonPress = ($toggleChildren[2] ?? null)?->events()[
    EventKind::Press->value
] ?? null;
if (!$rightButtonPress instanceof Closure) {
    throw new RuntimeException(
        'p-btn-toggle must forward its change contract to p-btn.',
    );
}
$rightButtonPress();
if ($selectedButton !== 'right') {
    throw new RuntimeException(
        'Single p-btn-toggle selection must emit the selected button value.',
    );
}

$optionalButton = 'unchanged';
$optionalToggle = $buttonToggleClass::make(
    ['value' => 'left'],
    $buttonClass::make(['text' => 'Left', 'value' => 'left']),
)->onChange(
    static function (mixed $value) use (&$optionalButton): void {
        $optionalButton = $value;
    },
)->toElement();
$optionalToggle->children()[0]->children()[0]->events()[EventKind::Press->value]();
if ($optionalButton !== null) {
    throw new RuntimeException(
        'A non-mandatory p-btn-toggle must allow its selected item to be cleared.',
    );
}

$mandatoryButtons = null;
$mandatoryToggle = $buttonToggleClass::make(
    ['value' => ['left'], 'multiple' => true, 'mandatory' => true],
    $buttonClass::make(['text' => 'Left', 'value' => 'left']),
)->onChange(
    static function (array $value) use (&$mandatoryButtons): void {
        $mandatoryButtons = $value;
    },
)->toElement();
$mandatoryPress = ($mandatoryToggle->children()[0]->children()[0] ?? null)?->events()[
    EventKind::Press->value
] ?? null;
if (!$mandatoryPress instanceof Closure) {
    throw new RuntimeException(
        'Multiple p-btn-toggle must bind button presses.',
    );
}
$mandatoryPress();
if ($mandatoryButtons !== ['left']) {
    throw new RuntimeException(
        'Mandatory p-btn-toggle must not deselect its final active button.',
    );
}


$rtlToggle = $buttonToggleClass::make(
    ['value' => 'center', 'rtl' => true, 'block' => true],
    $buttonClass::make(['text' => 'Left', 'value' => 'left']),
    $buttonClass::make(['text' => 'Center', 'value' => 'center']),
    $buttonClass::make(['text' => 'Right', 'value' => 'right']),
)->toElement();
$rtlToggleProperties = $rtlToggle->properties();
$rtlToggleChildren = $rtlToggle->children()[0]->children();
$rtlToggleLogicalFirst = $rtlToggleChildren[0]->properties();
$rtlToggleLogicalLast = $rtlToggleChildren[2]->properties();
if (
    ($rtlToggleProperties[PropKey::FlexDirection->value] ?? null)
        !== FlexDirection::RowReverse->value
    || ($rtlToggleProperties[PropKey::LayoutDirection->value] ?? null) !== 2
    || ($rtlToggleLogicalFirst[PropKey::MarginLeft->value] ?? null) !== -1.0
    || ($rtlToggleLogicalFirst[PropKey::BorderTopLeftRadius->value] ?? null) !== 0.0
    || ($rtlToggleLogicalFirst[PropKey::BorderTopRightRadius->value] ?? null) !== 20.0
    || ($rtlToggleLogicalLast[PropKey::MarginLeft->value] ?? null) !== 0.0
    || ($rtlToggleLogicalLast[PropKey::BorderTopLeftRadius->value] ?? null) !== 20.0
    || ($rtlToggleLogicalLast[PropKey::BorderTopRightRadius->value] ?? null) !== 0.0
) {
    throw new RuntimeException(
        'RTL p-btn-toggle must reverse visual order while preserving its physical outer corners and single outline.',
    );
}

$disabledToggle = $buttonToggleClass::make(
    ['value' => 'read', 'disabled' => true, 'density' => 'compact'],
    $buttonClass::make(['text' => 'Read', 'value' => 'read']),
    $buttonClass::make(['text' => 'Write', 'value' => 'write']),
)->toElement();
$disabledToggleChildren = $disabledToggle->children()[0]->children();
if (
    ($disabledToggle->properties()[PropKey::Opacity->value] ?? null) !== 1.0
    || ($disabledToggleChildren[0]->properties()[PropKey::Selected->value] ?? null) !== true
    || ($disabledToggleChildren[0]->properties()[PropKey::BackgroundColor->value] ?? 0)
        === 0x00000000
    || ($disabledToggleChildren[1]->properties()[PropKey::BackgroundColor->value] ?? null)
        !== 0x00000000
) {
    throw new RuntimeException(
        'Disabled p-btn-toggle must keep selected and unselected segments visually distinct without compounded opacity.',
    );
}
foreach ($disabledToggleChildren as $disabledToggleChild) {
    $disabledToggleProperties = $disabledToggleChild->properties();
    if (
        ($disabledToggleProperties[PropKey::Enabled->value] ?? null) !== false
        || ($disabledToggleProperties[PropKey::MinHeight->value] ?? null) !== 32.0
        || isset($disabledToggleProperties[PropKey::Height->value])
        || ($disabledToggleProperties[PropKey::HitSlopLeft->value] ?? null) !== 0.0
        || ($disabledToggleProperties[PropKey::HitSlopTop->value] ?? null) !== 8.0
    ) {
        throw new RuntimeException(
            'p-btn-toggle density and disabled state must propagate to each non-overlapping segment target.',
        );
    }
}

$tabsClass = $tags['p-tabs'];
$tabClass = $tags['p-tab'];
$selectedTab = null;
$tabs = $tabsClass::make(
    ['value' => 'overview'],
    $tabClass::make(['text' => 'Overview', 'value' => 'overview']),
    $tabClass::make(['text' => 'Activity', 'value' => 'activity']),
)->onChange(
    static function (string $value) use (&$selectedTab): void {
        $selectedTab = $value;
    },
)->toElement();
$activityPress = ($tabs->children()[1] ?? null)?->events()[
    EventKind::Press->value
] ?? null;
if (!$activityPress instanceof Closure) {
    throw new RuntimeException('p-tabs must bind press handling to p-tab.');
}
$activityPress();
if ($selectedTab !== 'activity') {
    throw new RuntimeException('p-tabs must emit the selected tab value.');
}

$slideGroupClass = $tags['p-slide-group'];
$slideItemClass = $tags['p-slide-group-item'];
$selectedSlide = null;
$slideGroup = $slideGroupClass::make(
    ['value' => 'first'],
    $slideItemClass::make(['text' => 'Second', 'value' => 'second']),
)->onChange(
    static function (string $value) use (&$selectedSlide): void {
        $selectedSlide = $value;
    },
)->toElement();
$slideHost = $slideGroup->children()[0] ?? null;
$slidePress = ($slideHost?->children()[0] ?? null)?->events()[
    EventKind::Press->value
] ?? null;
if (
    $slideGroup->kind() !== NodeKind::Scroll
    || ($slideGroup->properties()[PropKey::Height->value] ?? null) !== 48.0
    || $slideHost?->kind() !== NodeKind::CustomView
    || !$slidePress instanceof Closure
) {
    throw new RuntimeException(
        'p-slide-group must provide a native horizontal viewport and bind press handling to p-slide-group-item.',
    );
}
$slidePress();
if ($selectedSlide !== 'second') {
    throw new RuntimeException(
        'p-slide-group must emit the selected item value.',
    );
}

$tooltipClass = $tags['p-tooltip'];
foreach (['p-menu', 'p-tooltip', 'p-popover'] as $overlayTag) {
    $overlayClass = $tags[$overlayTag];
    foreach ([true, false] as $explicitLongPress) {
        $overlay = $overlayClass::make([
            'openOnClick' => false,
            'openOnContextmenu' => true,
            'openOnLongPress' => $explicitLongPress,
        ], $buttonClass::make(['text' => 'Open']), Text::make('Details'))->toElement();
        $payload = $overlay->properties()[PropKey::HostProperties->value] ?? null;
        if (!$payload instanceof BinaryValue) {
            throw new RuntimeException('Anchored overlay must expose native properties.');
        }
        $configuration = Wire::decodeMap($payload->bytes);
        if (($configuration['openOnClick'] ?? null) !== false
            || ($configuration['openOnLongPress'] ?? null) !== $explicitLongPress) {
            throw new RuntimeException('Explicit gesture configuration must win over legacy aliases.');
        }
    }
    $overlay = $overlayClass::make(['openOnContextmenu' => true],
        $buttonClass::make(['text' => 'Open']), Text::make('Details'))->toElement();
    $payload = $overlay->properties()[PropKey::HostProperties->value] ?? null;
    if (!$payload instanceof BinaryValue
        || (Wire::decodeMap($payload->bytes)['openOnLongPress'] ?? null) !== true) {
        throw new RuntimeException('Context-menu alias must enable native long press.');
    }
}
$tooltip = $tooltipClass::make(
    ['text' => 'More information'],
    $buttonClass::make(['text' => 'Details']),
)->toElement();
$tooltipChildren = $tooltip->children();
$tooltipTriggerTag = $tooltipChildren[0]->properties()[PropKey::Value->value]
    ?? null;
$tooltipContentTag = $tooltipChildren[1]->properties()[PropKey::Value->value]
    ?? null;
$tooltipHost = $tooltip->properties()[PropKey::HostProperties->value] ?? null;
if (!$tooltipHost instanceof BinaryValue) {
    throw new RuntimeException('p-tooltip must use its anchored native host.');
}
$tooltipProperties = Wire::decodeMap($tooltipHost->bytes);
if (
    $tooltipTriggerTag !== 'pam:overlay-trigger'
    || $tooltipContentTag !== 'pam:overlay-content'
    || ($tooltipProperties['openOnClick'] ?? null) !== false
    || ($tooltipProperties['openOnLongPress'] ?? null) !== true
    || ($tooltipProperties['openDelay'] ?? null) !== 500
) {
    throw new RuntimeException(
        'p-tooltip text shorthand must create long-press trigger and content anatomy.',
    );
}

foreach ([
    'p-select' => [false, false],
    'p-autocomplete' => [true, false],
    'p-combobox' => [true, true],
    'p-tag-input' => [true, true],
    'p-multi-select' => [true, false],
] as $selectionTag => [$searchable, $allowCustomValue]) {
    $selectionClass = $tags[$selectionTag];
    $selection = $selectionClass::make([
        'label' => 'Framework',
        'items' => ['PAM', 'Native'],
    ])->toElement();
    $stack = [$selection];
    $hasTrigger = false;
    $triggerWidthPercent = null;
    $triggerMinHeight = null;
    $triggerKind = null;
    $hasInput = false;
    $selectDragHandleWidth = null;
    $selectDragHandleHeight = null;
    $sheetProperties = null;
    $portalPresentation = null;
    while ($stack !== []) {
        $candidate = array_pop($stack);
        if ($candidate->kind() === NodeKind::Modal) {
            $portalPresentation = $candidate->properties()[
                PropKey::ModalPresentation->value
            ] ?? null;
        }
        $marker = $candidate->properties()[PropKey::Value->value] ?? null;
        if ($marker === 'pam:sheet-drag-indicator') {
            $selectDragHandleWidth = $candidate->properties()[PropKey::Width->value]
                ?? null;
            $selectDragHandleHeight = $candidate->properties()[PropKey::Height->value]
                ?? null;
        }
        $isTrigger = (
            is_string($marker)
            && str_starts_with($marker, 'pam:local-modal-trigger:')
        );
        $hasTrigger = $hasTrigger || $isTrigger;
        if ($isTrigger) {
            $triggerWidthPercent = $candidate->properties()[
                PropKey::WidthPercent->value
            ] ?? null;
            $triggerMinHeight = $candidate->properties()[
                PropKey::MinHeight->value
            ] ?? null;
            $triggerKind = $candidate->kind();
        }
        $hasInput = $hasInput || $candidate->kind() === NodeKind::Input;
        $host = $candidate->properties()[PropKey::HostProperties->value] ?? null;
        if ($host instanceof BinaryValue) {
            $decoded = Wire::decodeMap($host->bytes);
            if (
                array_key_exists('searchable', $decoded)
                && array_key_exists('enableDynamicSizing', $decoded)
            ) {
                $sheetProperties = $decoded;
            }
        }
        array_push($stack, ...$candidate->children());
    }
    if (!is_array($sheetProperties)) {
        throw new RuntimeException(
            "{$selectionTag} must expose native sheet properties.",
        );
    }
    if (
        !$hasTrigger
        || $triggerWidthPercent !== 100.0
        || $triggerMinHeight !== 64.0
        || $triggerKind !== NodeKind::Pressable
        || $hasInput
        || $selectDragHandleWidth !== MaterialTokens::BOTTOM_SHEET_HANDLE_WIDTH
        || $selectDragHandleHeight !== MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT
        || $portalPresentation !== ModalPresentation::FullScreen->value
        || $sheetProperties['enableDynamicSizing'] !== true
        || $sheetProperties['searchable'] !== $searchable
        || $sheetProperties['allowCustomValue'] !== $allowCustomValue
    ) {
        throw new RuntimeException(
            "{$selectionTag} must use one full-screen portal around one native searchable sheet.",
        );
    }
}

$createdTag = null;
foreach (['p-select', 'p-autocomplete', 'p-combobox', 'p-tag-input', 'p-multi-select'] as $optionTag) {
    foreach ([['isDisabled' => true], ['disabled' => 'false'],
        ['disabled' => false, 'isDisabled' => true]] as $optionLock) {
        $expectedLocked = $optionLock === ['isDisabled' => true];
        $optionControl = $tags[$optionTag]::make([
            'items' => [['value' => 73, 'label' => 'Option lock fixture', ...$optionLock]],
        ])->onChange(static function (mixed $value): void {})->toElement();
        $optionStack = [$optionControl];
        $matchedOptions = 0;
        while ($optionStack !== []) {
            $optionNode = array_pop($optionStack);
            $optionProperties = $optionNode->properties();
            if (in_array($optionProperties[PropKey::Value->value] ?? null, [73, '73'], true)) {
                $matchedOptions++;
                if (($optionProperties[PropKey::Enabled->value] ?? true) !== !$expectedLocked
                    || isset($optionNode->events()[EventKind::Press->value]) === $expectedLocked) {
                    throw new RuntimeException($optionTag.' option locks must honor aliases, false strings and canonical precedence.');
                }
            }
            array_push($optionStack, ...$optionNode->children());
        }
        if ($matchedOptions !== 1) {
            throw new RuntimeException('Option-lock regression must inspect exactly one selectable option.');
        }
    }
}
$tagInput = $tags['p-tag-input']::make([
    'label' => 'Technologies',
    'items' => ['Android', 'iOS'],
    'modelValue' => ['Android'],
])->onChange(static function (mixed $value) use (&$createdTag): void {
    $createdTag = $value;
})->toElement();
$stack = [$tagInput];
$customTagChange = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    $candidateChange = $candidate->events()[EventKind::Change->value] ?? null;
    if ($candidateChange instanceof Closure) {
        $customTagChange = $candidateChange;
    }
    array_push($stack, ...$candidate->children());
}
if (!$customTagChange instanceof Closure) {
    throw new RuntimeException(
        'p-tag-input must route native custom-value changes through its portal.',
    );
}
$customTagChange(['Android', 'Rust']);
if ($createdTag !== ['Android', 'Rust']) {
    throw new RuntimeException(
        'p-tag-input must emit created custom values through onChange.',
    );
}

$multipleAutocompleteValue = null;
$assertCustomSelection = static function (mixed $actual, mixed $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
};
foreach (['p-tag-input', 'p-combobox'] as $customControlTag) {
    $customResult = null;
    $customControl = $tags[$customControlTag]::make([
        'items' => [
            ['value' => 1, 'label' => 'Required', 'disabled' => true],
            ['value' => 2, 'label' => 'Unavailable', 'disabled' => true],
        ],
        'modelValue' => $customControlTag === 'p-tag-input' ? [1] : 1,
    ])->onChange(static function (mixed $value) use (&$customResult): void {
        $customResult = $value;
    })->toElement();
    $pendingCustomNodes = [$customControl];
    $portalChange = null;
    while ($pendingCustomNodes !== []) {
        $customNode = array_pop($pendingCustomNodes);
        $customChange = $customNode->events()[EventKind::Change->value] ?? null;
        if ($customChange instanceof Closure) $portalChange = $customChange;
        array_push($pendingCustomNodes, ...$customNode->children());
    }
    if (!$portalChange instanceof Closure) throw new RuntimeException('Custom selection needs a portal event.');
    foreach ([1, '1', 2, '2'] as $protectedInput) {
        $portalChange($protectedInput);
        $assertCustomSelection($customResult, null, 'Custom input must not bypass disabled options.');
    }
    $portalChange('New tag');
    $assertCustomSelection($customResult,
        $customControlTag === 'p-tag-input' ? [1, 'New tag'] : 'New tag',
        'Custom input must still accept an unprotected new value.');
    if ($customControlTag === 'p-tag-input') {
        $portalChange([2, 'New tag']);
        $assertCustomSelection($customResult, ['New tag', 1],
            'Tag replacement must retain protected selections and reject unavailable additions.');
    }
}
$multipleAutocomplete = $tags['p-autocomplete']::make([
    'label' => 'Team',
    'items' => ['Design', 'Engineering', 'Product'],
    'modelValue' => ['Design', 'Product'],
    'multiple' => true,
    'chips' => true,
])->onChange(static function (mixed $value) use (&$multipleAutocompleteValue): void {
    $multipleAutocompleteValue = $value;
})->toElement();
$stack = [$multipleAutocomplete];
$engineeringPress = null;
$multipleDisplay = false;
while ($stack !== []) {
    $candidate = array_pop($stack);
    $label = $candidate->properties()[PropKey::AccessibilityLabel->value] ?? null;
    if ($label === 'Engineering') {
        $engineeringPress = $candidate->events()[EventKind::Press->value] ?? null;
    }
    if (
        $candidate->kind() === NodeKind::Text
        && ($candidate->properties()[PropKey::Text->value] ?? null) === 'Design, Product'
    ) {
        $multipleDisplay = true;
    }
    array_push($stack, ...$candidate->children());
}
if (!$engineeringPress instanceof Closure || !$multipleDisplay) {
    throw new RuntimeException(
        'Multiple autocomplete must expose every selected value and selectable rows.',
    );
}
$engineeringPress();
if ($multipleAutocompleteValue !== ['Design', 'Product', 'Engineering']) {
    throw new RuntimeException(
        'Multiple autocomplete must emit the complete updated selection.',
    );
}

$autocompleteError = $tags['p-autocomplete']::make([
    'label' => 'Team',
    'items' => ['Design', 'Engineering'],
    'modelValue' => 'Design',
    'error' => true,
])->toElement();
$autocompleteField = $autocompleteError->children()[0] ?? null;
if (
    !$autocompleteField instanceof \Pam\Native\Element
    || ($autocompleteField->properties()[PropKey::BorderWidth->value] ?? null) !== 2.0
    || ($autocompleteField->properties()[PropKey::BorderBottomWidth->value] ?? null) !== null
) {
    throw new RuntimeException(
        'PAM autocomplete errors must use the complete semantic outline.',
    );
}

$modalMarker = static function (\Pam\Native\Element $root): ?string {
    $stack = [$root];
    while ($stack !== []) {
        $candidate = array_pop($stack);
        $marker = $candidate->properties()[PropKey::Value->value] ?? null;
        if (is_string($marker) && str_starts_with($marker, 'pam:local-modal:')) {
            return $marker;
        }
        array_push($stack, ...$candidate->children());
    }

    return null;
};
$firstAutocomplete = $tags['p-autocomplete']::make([
    'id' => 'team-primary',
    'label' => 'Team',
    'items' => ['Design', 'Engineering'],
])->toElement();
$secondAutocomplete = $tags['p-autocomplete']::make([
    'id' => 'team-secondary',
    'label' => 'Team',
    'items' => ['Design', 'Engineering'],
])->toElement();
if ($modalMarker($firstAutocomplete) === $modalMarker($secondAutocomplete)) {
    throw new RuntimeException(
        'Repeated autocomplete instances must keep independent modal identities.',
    );
}
$autocompleteChanges = [];
$interactiveAutocomplete = $tags['p-autocomplete']::make([
    'id' => 'team-interactive',
    'label' => 'Team',
    'items' => ['Design', 'Engineering'],
    'modelValue' => 'Design',
])->onChange(
    static function (mixed $value) use (&$autocompleteChanges): void {
        $autocompleteChanges[] = $value;
    },
)->toElement();
$stack = [$interactiveAutocomplete];
$engineeringPress = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    if (($candidate->properties()[PropKey::Value->value] ?? null) === 'Engineering') {
        $engineeringPress = $candidate->events()[EventKind::Press->value] ?? null;
    }
    array_push($stack, ...$candidate->children());
}
if (!$engineeringPress instanceof Closure) {
    throw new RuntimeException('Autocomplete options must expose a press callback.');
}
$engineeringPress();
if ($autocompleteChanges !== ['Engineering']) {
    throw new RuntimeException(
        'Autocomplete selection must publish the selected option value exactly once.',
    );
}
$readOnlyAutocomplete = $tags['p-autocomplete']::make([
    'id' => 'team-readonly',
    'label' => 'Team',
    'items' => ['Design', 'Engineering'],
    'modelValue' => 'Design',
    'readonly' => true,
])->onChange(static fn (mixed $value): mixed => $value)->toElement();
$stack = [$readOnlyAutocomplete];
$readOnlyTrigger = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    $marker = $candidate->properties()[PropKey::Value->value] ?? null;
    if (is_string($marker) && str_starts_with($marker, 'pam:readonly-select-trigger:')) {
        $readOnlyTrigger = $candidate;
        break;
    }
    array_push($stack, ...$candidate->children());
}
if (
    !$readOnlyTrigger instanceof \Pam\Native\Element
    || isset($readOnlyTrigger->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'Read-only autocomplete must expose its value without an opening action.',
    );
}
foreach (['p-select', 'p-autocomplete', 'p-combobox', 'p-tag-input', 'p-multi-select', 'p-command-palette'] as $lockedTag) {
    foreach (['readonly', 'readOnly', 'isReadOnly', 'disabled', 'isDisabled'] as $lockProp) {
        $lockedSelection = $tags[$lockedTag]::make([
            $lockProp => true, 'open' => true, 'items' => ['Locked option'],
        ])->onChange(static fn (mixed $value): mixed => $value)->toElement();
        $stack = [$lockedSelection];
        $foundLockedOption = false;
        while ($stack !== []) {
            $candidate = array_pop($stack);
            $properties = $candidate->properties();
            $marker = $properties[PropKey::Value->value] ?? null;
            if ($marker === 'Locked option') {
                $foundLockedOption = true;
                if (isset($candidate->events()[EventKind::Press->value])) {
                    throw new RuntimeException("{$lockedTag} {$lockProp} must suppress option mutations.");
                }
            }
            if ($candidate->kind() === NodeKind::Modal
                && (($properties[PropKey::Visible->value] ?? false) !== false
                    || isset($candidate->events()[EventKind::Change->value]))) {
                throw new RuntimeException("{$lockedTag} {$lockProp} must not open or accept custom values.");
            }
            if (is_string($marker)
                && (str_starts_with($marker, 'pam:readonly-select-trigger:')
                    || str_starts_with($marker, 'pam:local-modal-trigger:'))
                && (($properties[PropKey::Enabled->value] ?? true) !== false
                    || ($properties[PropKey::AccessibilityExpanded->value] ?? false) !== false
                    || isset($candidate->events()[EventKind::Press->value]))) {
                throw new RuntimeException("{$lockedTag} {$lockProp} must block its field trigger.");
            }
            array_push($stack, ...$candidate->children());
        }
        if (!$foundLockedOption) {
            throw new RuntimeException('Locked selection regression must inspect an actual option.');
        }
    }
}
$readOnlyTextField = $tags['p-text-field']::make([
    'label' => 'Name',
    'modelValue' => 'Ada',
    'readonly' => true,
])->toElement();
$stack = [$readOnlyTextField];
$readOnlyNativeInput = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    if ($candidate->kind() === NodeKind::Input) {
        $readOnlyNativeInput = $candidate;
        break;
    }
    array_push($stack, ...$candidate->children());
}
if (
    !$readOnlyNativeInput instanceof \Pam\Native\Element
    || ($readOnlyNativeInput->properties()[PropKey::InputEditable->value] ?? true)
        !== false
    || ($readOnlyNativeInput->properties()[
        PropKey::InputDisableFullscreenUi->value
    ] ?? false) !== true
) {
    throw new RuntimeException(
        'Material text inputs must be readonly-aware and remain inline in landscape.',
    );
}

foreach (['p-text-field', 'p-masked-field', 'p-currency-field'] as $affixTag) {
    $adornedField = $tags[$affixTag]::make([
        'label' => 'Amount',
        'prefix' => 'BRL total',
        'suffix' => 'per month',
        'modelValue' => '123',
        'clearable' => true,
    ])->toElement();
    $stack = [$adornedField];
    $adornedRow = null;
    while ($stack !== []) {
        $candidate = array_pop($stack);
        $rowChildren = $candidate->children();
        if (
            $candidate->kind() === NodeKind::Row
            && count($rowChildren) === 3
            && $rowChildren[1]->kind() === NodeKind::Input
        ) {
            $adornedRow = $candidate;
            break;
        }
        array_push($stack, ...$rowChildren);
    }
    if (!$adornedRow instanceof \Pam\Native\Element) {
        throw new RuntimeException($affixTag.' must keep its affixes and input in one row.');
    }
    [$leading, $editable, $trailing] = $adornedRow->children();
    if (
        ($leading->properties()[PropKey::Text->value] ?? null) !== 'BRL total'
        || ($trailing->properties()[PropKey::Text->value] ?? null) !== 'per month'
        || isset($leading->properties()[PropKey::PositionType->value])
        || isset($trailing->properties()[PropKey::PositionType->value])
        || isset($leading->properties()[PropKey::Height->value])
        || ($editable->properties()[PropKey::PaddingLeft->value] ?? null) !== 0.0
        || ($editable->properties()[PropKey::PaddingRight->value] ?? null) !== 0.0
        || ($adornedRow->properties()[PropKey::PaddingRight->value] ?? null) !== 40.0
    ) {
        throw new RuntimeException($affixTag.' must measure affixes in flow and reserve its clear action.');
    }
}

foreach (['p-text-field', 'p-password-field', 'p-masked-field', 'p-currency-field'] as $actionTag) {
    foreach ([false, true] as $loading) {
        $field = $tags[$actionTag]::make([
            'label' => 'Actions', 'modelValue' => '1234',
            'clearable' => true, 'loading' => $loading,
        ])->toElement();
        $stack = [$field];
        $slots = [];
        while ($stack !== []) {
            $node = array_pop($stack);
            $properties = $node->properties();
            if (
                ($properties[PropKey::PositionType->value] ?? null) === PositionType::Absolute->value
                && isset($properties[PropKey::Right->value], $properties[PropKey::Width->value])
            ) {
                $right = $properties[PropKey::Right->value];
                $width = $properties[PropKey::Width->value];
                if (!is_float($right) || !is_float($width)) {
                    throw new RuntimeException('Field action bounds must be numeric layout values.');
                }
                $slots[] = [$right, $width];
                if ($actionTag === 'p-password-field') {
                    $bottom = $properties[PropKey::Bottom->value] ?? null;
                    $height = $properties[PropKey::Height->value] ?? null;
                    if (!is_float($bottom) || !is_float($height) || $bottom + $height / 2.0 !== 24.0) {
                        throw new RuntimeException('Password trailing actions must share their vertical center.');
                    }
                }
            }
            array_push($stack, ...$node->children());
        }
        $expectedSlots = 1 + ($actionTag === 'p-password-field' ? 1 : 0) + ($loading ? 1 : 0);
        if (count($slots) !== $expectedSlots) {
            throw new RuntimeException('Field action fixture must retain every requested action.');
        }
        usort($slots, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        for ($i = 1; $i < count($slots); $i++) {
            if ($slots[$i][0] < $slots[$i - 1][0] + $slots[$i - 1][1] + 8.0) {
                throw new RuntimeException($actionTag.' trailing actions must have separate slots with 8dp clearance.');
            }
        }
    }
}
$textareaAffixes = $tags['p-textarea']::make([
    'label' => 'Message',
    'modelValue' => 'Hello',
    'prefix' => '@',
    'suffix' => 'note',
    'clearable' => true,
])->onChange(static fn (mixed $value): mixed => $value)->toElement();
$stack = [$textareaAffixes];
$textareaPrefix = null;
$textareaSuffix = null;
$textareaClear = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    $candidateValue = $candidate->properties()[PropKey::Text->value] ?? null;
    $candidateLabel = $candidate->properties()[PropKey::AccessibilityLabel->value]
        ?? null;
    if ($candidateValue === '@') {
        $textareaPrefix = $candidate;
    } elseif ($candidateValue === 'note') {
        $textareaSuffix = $candidate;
    } elseif ($candidateLabel === 'Clear Message') {
        $textareaClear = $candidate;
    }
    array_push($stack, ...$candidate->children());
}
if (
    !$textareaPrefix instanceof \Pam\Native\Element
    || ($textareaPrefix->properties()[PropKey::Top->value] ?? null) !== 13.0
    || isset($textareaPrefix->properties()[PropKey::Bottom->value])
    || !$textareaSuffix instanceof \Pam\Native\Element
    || ($textareaSuffix->properties()[PropKey::Top->value] ?? null) !== 13.0
    || isset($textareaSuffix->properties()[PropKey::Bottom->value])
    || !$textareaClear instanceof \Pam\Native\Element
    || ($textareaClear->properties()[PropKey::Top->value] ?? null) !== 20.0
    || isset($textareaClear->properties()[PropKey::Bottom->value])
) {
    throw new RuntimeException(
        'Textarea affixes and actions must align with the first editable line.',
    );
}

$extractUiTextField = $tags['p-text-field']::make([
    'disableFullscreenUI' => false,
])->toElement();
$stack = [$extractUiTextField];
$extractUiNativeInput = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    if ($candidate->kind() === NodeKind::Input) {
        $extractUiNativeInput = $candidate;
        break;
    }
    array_push($stack, ...$candidate->children());
}
if (
    !$extractUiNativeInput instanceof \Pam\Native\Element
    || ($extractUiNativeInput->properties()[
        PropKey::InputDisableFullscreenUi->value
    ] ?? true) !== false
) {
    throw new RuntimeException(
        'Material text inputs must preserve the explicit Android extract-UI opt-in.',
    );
}

$numberClass = $tags['p-number-input'];
$numberChanges = [];
$numberInput = $numberClass::make([
    'modelValue' => 10,
    'min' => 0,
    'max' => 10,
    'step' => 2,
])->onChange(
    static function (string $value) use (&$numberChanges): void {
        $numberChanges[] = $value;
    },
)->toElement();
$stack = [$numberInput];
$nativeNumberInput = null;
$disabledNumberButtons = 0;
$numberButtons = [];
while ($stack !== []) {
    $candidate = array_pop($stack);
    if ($candidate->kind() === NodeKind::Input) {
        $nativeNumberInput = $candidate;
    }
    if (
        $candidate->kind() === NodeKind::Pressable
    ) {
        $numberButtons[] = $candidate;
        if (($candidate->properties()[PropKey::Enabled->value] ?? true) === false) {
            $disabledNumberButtons++;
        }
    }
    array_push($stack, ...$candidate->children());
}
$numberChange = $nativeNumberInput
    ?->events()[EventKind::Change->value]
    ?? null;
if (!$numberChange instanceof Closure) {
    throw new RuntimeException('p-number-input must expose native change handling.');
}
$numberChange('13');
$numberChange('3.1');
if (
    $numberChanges !== ['10', '4']
    || $disabledNumberButtons !== 1
    || count($numberButtons) !== 2
    || array_any(
        $numberButtons,
        static fn (\Pam\Native\Element $button): bool =>
            ($button->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
            || ($button->properties()[PropKey::MinHeight->value] ?? null) !== 48.0,
    )
) {
    throw new RuntimeException(
        'p-number-input must clamp, snap and preserve 48dp effective targets at numeric limits.',
    );
}
$precisionNumberInput = $numberClass::make([
    'modelValue' => 4.5,
    'step' => 0.25,
    'precision' => 2,
])->toElement();
$stack = [$precisionNumberInput];
$precisionNativeInput = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    if ($candidate->kind() === NodeKind::Input) {
        $precisionNativeInput = $candidate;
        break;
    }
    array_push($stack, ...$candidate->children());
}
if (
    !$precisionNativeInput instanceof \Pam\Native\Element
    || ($precisionNativeInput->properties()[PropKey::Value->value] ?? null)
        !== '4.50'
) {
    throw new RuntimeException(
        'p-number-input precision must format the controlled native value.',
    );
}
$readOnlyNumberInput = $numberClass::make([
    'modelValue' => 8,
    'readonly' => true,
])->onChange(static fn (string $value): string => $value)->toElement();
$stack = [$readOnlyNumberInput];
$readOnlyNumberControls = [];
while ($stack !== []) {
    $candidate = array_pop($stack);
    if ($candidate->kind() === NodeKind::Pressable) {
        $readOnlyNumberControls[] = $candidate;
    }
    array_push($stack, ...$candidate->children());
}
if (
    count($readOnlyNumberControls) !== 2
    || array_any(
        $readOnlyNumberControls,
        static fn (\Pam\Native\Element $button): bool =>
            ($button->properties()[PropKey::Enabled->value] ?? true) !== false
            || isset($button->events()[EventKind::Press->value])
            || ($button->properties()[PropKey::Opacity->value] ?? null) !== 0.38,
    )
) {
    throw new RuntimeException(
        'Read-only number inputs must disable and visually mute both step controls.',
    );
}
$stackedNumberInput = $numberClass::make([
    'modelValue' => 8,
    'controlVariant' => 'stacked',
])->toElement();
$stack = [$stackedNumberInput];
$stackedControlGroup = null;
while ($stack !== []) {
    $candidate = array_pop($stack);
    $properties = $candidate->properties();
    if (
        ($properties[PropKey::Width->value] ?? null) === 48.0
        && ($properties[PropKey::Height->value] ?? null) === 96.0
        && ($properties[PropKey::BorderLeftWidth->value] ?? null) === 1.0
    ) {
        $stackedControlGroup = $candidate;
        break;
    }
    array_push($stack, ...$candidate->children());
}
$stackedFirstControl = $stackedControlGroup?->children()[0] ?? null;
if (
    !$stackedControlGroup instanceof \Pam\Native\Element
    || !$stackedFirstControl instanceof \Pam\Native\Element
    || ($stackedFirstControl->properties()[
        PropKey::BorderBottomWidth->value
    ] ?? null) !== 1.0
) {
    throw new RuntimeException(
        'Stacked number controls must use one continuous edge and one centered divider.',
    );
}
$stackedNumberStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PNumberInput',
    'controlVariant' => 'stacked',
], Themes::pamLight());
$defaultNumberStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PNumberInput',
], Themes::pamLight());
$comfortableNumberStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PNumberInput',
    'density' => 'comfortable',
], Themes::pamLight());
$compactNumberStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'PNumberInput',
    'density' => 'compact',
], Themes::pamLight());
if (
    !$stackedNumberStyle instanceof Style
    || $stackedNumberStyle->height !== 112.0
    || !$defaultNumberStyle instanceof Style
    || $defaultNumberStyle->height !== 72.0
    || !$comfortableNumberStyle instanceof Style
    || $comfortableNumberStyle->height !== 72.0
    || !$compactNumberStyle instanceof Style
    || $compactNumberStyle->height !== 72.0
) {
    throw new RuntimeException(
        'Number input variants must reserve their complete 48dp control geometry.',
    );
}

$otpClass = $tags['p-otp-input'];
$otpHost = $otpClass::make([
    'modelValue' => '482915',
    'focused' => true,
])->toElement()->properties()[PropKey::HostProperties->value] ?? null;
if (
    !$otpHost instanceof BinaryValue
    || (Wire::decodeMap($otpHost->bytes)['outlineWidth'] ?? null) !== 0.0
) {
    throw new RuntimeException(
        'OTP must suppress the compound input outline because its slots own focus and error borders.',
    );
}
$otpNodes = static function (\Pam\Native\Element $root): array {
    $nodes = [];
    $visit = static function (\Pam\Native\Element $node) use (&$visit, &$nodes): void {
        $nodes[] = $node;
        foreach ($node->children() as $child) {
            $visit($child);
        }
    };
    $visit($root);

    return $nodes;
};
$maskedOtp = $otpClass::make([
    'modelValue' => '482915',
    'length' => 6,
    'masked' => true,
])->toElement();
$maskedOtpNodes = $otpNodes($maskedOtp);
$maskedOtpInput = null;
$maskedOtpCells = [];
$maskedOtpGlyphs = [];
$otpVisualRow = null;
foreach ($maskedOtpNodes as $candidate) {
    $properties = $candidate->properties();
    if ($candidate->kind() === NodeKind::Input) {
        $maskedOtpInput = $candidate;
    }
    if (
        ($properties[PropKey::Width->value] ?? null) === 40.0
        && ($properties[PropKey::Height->value] ?? null) === 48.0
    ) {
        $maskedOtpCells[] = $candidate;
    }
    if ($candidate->kind() === NodeKind::Text) {
        $maskedOtpGlyphs[] = $properties[PropKey::Text->value] ?? '';
    }
    if (
        ($properties[PropKey::AccessibilityImportance->value] ?? null)
            === AccessibilityImportance::NoHideDescendants->value
    ) {
        $otpVisualRow = $candidate;
    }
}
if (
    !$maskedOtpInput instanceof \Pam\Native\Element
    || count($maskedOtpCells) !== 6
    || count(array_filter($maskedOtpGlyphs, static fn (mixed $glyph): bool => $glyph === '•')) !== 6
    || array_intersect(
        array_values(array_filter($maskedOtpGlyphs, 'is_string')),
        ['4', '8', '2', '9', '1', '5'],
    ) !== []
    || ($maskedOtpInput->properties()[PropKey::Secure->value] ?? false) !== true
    || ($maskedOtpInput->properties()[PropKey::MaxLength->value] ?? null) !== 6
    || ($maskedOtpInput->properties()[PropKey::InputCaretHidden->value] ?? false) !== true
    || ($maskedOtpInput->properties()[PropKey::InputUnderlineColor->value] ?? null) !== 0x00000000
    || ($maskedOtpInput->properties()[PropKey::KeyboardType->value] ?? null)
        !== KeyboardType::Number->value
    || ($maskedOtpInput->properties()[PropKey::Top->value] ?? null) !== 8.0
    || ($maskedOtpInput->properties()[PropKey::Height->value] ?? null) !== 48.0
    || ($maskedOtpInput->properties()[PropKey::TextColor->value] ?? null) !== 0x00000000
    || ($maskedOtpInput->properties()[PropKey::Opacity->value] ?? null) !== 0.01
    || ($maskedOtpInput->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'One-time password'
    || !$otpVisualRow instanceof \Pam\Native\Element
    || array_any(
        $maskedOtpCells,
        static fn (\Pam\Native\Element $cell): bool =>
            ($cell->properties()[PropKey::BorderWidth->value] ?? null) !== 1.0,
    )
) {
    throw new RuntimeException(
        'OTP masking must hide every glyph while preserving one accessible 48dp native editor.',
    );
}
$focusedOtpCells = array_values(array_filter(
    $otpNodes($otpClass::make([
        'modelValue' => '482915',
        'length' => 6,
        'focused' => true,
    ])->toElement()),
    static fn (\Pam\Native\Element $candidate): bool =>
        ($candidate->properties()[PropKey::Width->value] ?? null) === 40.0
        && ($candidate->properties()[PropKey::Height->value] ?? null) === 48.0,
));
if (
    count($focusedOtpCells) !== 6
    || ($focusedOtpCells[5]->properties()[PropKey::BorderWidth->value] ?? null) !== 2.0
    || ($focusedOtpCells[5]->properties()[PropKey::BorderColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::Primary)
) {
    throw new RuntimeException(
        'OTP must highlight only the controlled active slot while focused.',
    );
}
$errorOtpCells = array_values(array_filter(
    $otpNodes($otpClass::make([
        'modelValue' => '482915',
        'length' => 6,
        'error' => true,
        'helper' => 'Invalid code',
    ])->toElement()),
    static fn (\Pam\Native\Element $candidate): bool =>
        ($candidate->properties()[PropKey::Width->value] ?? null) === 40.0
        && ($candidate->properties()[PropKey::Height->value] ?? null) === 48.0,
));
if (
    count($errorOtpCells) !== 6
    || array_any(
        $errorOtpCells,
        static fn (\Pam\Native\Element $cell): bool =>
            ($cell->properties()[PropKey::BorderColor->value] ?? null)
                !== Themes::pamLight()->color(ColorToken::Destructive),
    )
) {
    throw new RuntimeException('OTP error state must reach every visual slot.');
}
$dividerOtp = $otpClass::make([
    'modelValue' => '482915',
    'divider' => '−',
])->toElement();
$dividerGlyphs = array_filter(
    $otpNodes($dividerOtp),
    static fn (\Pam\Native\Element $candidate): bool =>
        $candidate->kind() === NodeKind::Text
        && ($candidate->properties()[PropKey::Text->value] ?? null) === '−',
);
$dividerOtpStyle = MaterialStyleResolver::resolve([
    '__materialComponent' => 'POtpInput',
    'divider' => '−',
], Themes::pamLight());
if (count($dividerGlyphs) !== 5 || $dividerOtpStyle?->width !== 360.0) {
    throw new RuntimeException('OTP divider must render between all slots without overflow.');
}
$mergedOtpNodes = $otpNodes($otpClass::make([
    'modelValue' => '482915',
    'merged' => true,
])->toElement());
$mergedOtpCells = array_values(array_filter(
    $mergedOtpNodes,
    static fn (\Pam\Native\Element $candidate): bool =>
        ($candidate->properties()[PropKey::Width->value] ?? null) === 40.0
        && ($candidate->properties()[PropKey::Height->value] ?? null) === 48.0,
));
$mergedOtpRows = array_filter(
    $mergedOtpNodes,
    static fn (\Pam\Native\Element $candidate): bool =>
        ($candidate->properties()[PropKey::Gap->value] ?? null) === 0.0,
);
if (
    count($mergedOtpCells) !== 6
    || ($mergedOtpCells[1]->properties()[PropKey::BorderLeftWidth->value] ?? null) !== 0.0
    || $mergedOtpRows === []
) {
    throw new RuntimeException('Merged OTP slots must form one continuous field group.');
}
$loadingOtpNodes = $otpNodes($otpClass::make([
    'modelValue' => '482915',
    'loading' => true,
])->toElement());
$loadingOtpInput = null;
$loadingOtpIndicator = null;
foreach ($loadingOtpNodes as $candidate) {
    if ($candidate->kind() === NodeKind::Input) {
        $loadingOtpInput = $candidate;
    } elseif ($candidate->kind() === NodeKind::ActivityIndicator) {
        $loadingOtpIndicator = $candidate;
    }
}
if (
    !$loadingOtpInput instanceof \Pam\Native\Element
    || !$loadingOtpIndicator instanceof \Pam\Native\Element
    || ($loadingOtpInput->properties()[PropKey::AccessibilityBusy->value] ?? false)
        !== true
) {
    throw new RuntimeException(
        'Loading OTP must render a native indicator and announce its busy state.',
    );
}

$speedDialClass = $tags['p-speed-dial'];
$fabClass = $tags['p-fab'];
$buttonClass = $tags['p-btn'];
$speedDial = $speedDialClass::make(
    ['open' => true, 'direction' => 'top', 'persistent' => true],
    $fabClass::make('Create'),
    $buttonClass::make('Photo'),
    $buttonClass::make('Document'),
)->toElement();
$speedDialHost = $speedDial->properties()[PropKey::HostProperties->value]
    ?? null;
if (!$speedDialHost instanceof BinaryValue) {
    throw new RuntimeException('p-speed-dial must use its anchored native host.');
}
$speedDialProperties = Wire::decodeMap($speedDialHost->bytes);
$speedDialTrigger = $speedDial->children()[0] ?? null;
$speedDialContent = $speedDial->children()[1] ?? null;
if (
    ($speedDialProperties['placement'] ?? null) !== Placement::Top->value
    || ($speedDialProperties['dismissible'] ?? null) !== false
    || ($speedDialTrigger?->properties()[PropKey::Value->value] ?? null)
        !== 'pam:overlay-trigger'
    || ($speedDialContent?->properties()[PropKey::Value->value] ?? null)
        !== 'pam:overlay-content'
) {
    throw new RuntimeException(
        'p-speed-dial direction, persistence and anchored anatomy must be native.',
    );
}

$dialogClass = $tags['p-dialog'];
$persistentDialog = $dialogClass::make([
    'open' => true,
    'persistent' => true,
    'scrim' => false,
    'fullscreen' => true,
    'dismissible' => true,
    'allowSwipeDismissal' => true,
], Text::make('Unsaved changes'))->toElement();
$dialogSurface = $persistentDialog->children()[0] ?? null;
$dialogHost = $dialogSurface?->properties()[PropKey::HostProperties->value]
    ?? null;
if (!$dialogHost instanceof BinaryValue) {
    throw new RuntimeException('p-dialog must retain its native modal host.');
}
$dialogProperties = Wire::decodeMap($dialogHost->bytes);
if (
    ($dialogProperties['dismissible'] ?? null) !== false
    || ($dialogProperties['isDismissable'] ?? null) !== false
    || ($dialogProperties['allowSwipeDismissal'] ?? null) !== false
    || $persistentDialog->properties()[PropKey::ModalPresentation->value]
        !== ModalPresentation::FullScreen->value
    || $persistentDialog->properties()[PropKey::ModalAnimationType->value]
        !== ModalAnimationType::Fade->value
    || $persistentDialog->properties()[PropKey::ModalBackdropColor->value]
        !== 0x00000000
    || $persistentDialog->properties()[
        PropKey::ModalAllowSwipeDismissal->value
    ] !== false
    || $persistentDialog->properties()[
        PropKey::BottomSheetDismissible->value
    ] !== false
) {
    throw new RuntimeException(
        'p-dialog persistent, fullscreen and scrim semantics must reach the native window.',
    );
}

$bottomSheetClass = $tags['p-bottom-sheet'];
$persistentBottomSheet = $bottomSheetClass::make([
    'open' => true,
    'persistent' => true,
    'scrim' => false,
    'snapPoints' => [34, 68],
    'hideDragIndicator' => true,
], Text::make('Sheet content'))->toElement();
$bottomSheetHostNode = $persistentBottomSheet->children()[0] ?? null;
$bottomSheetHost = $bottomSheetHostNode?->properties()[
    PropKey::HostProperties->value
] ?? null;
$bottomSheetBackdrop = $bottomSheetHostNode?->children()[0] ?? null;
if (!$bottomSheetHost instanceof BinaryValue) {
    throw new RuntimeException('p-bottom-sheet must retain its native sheet host.');
}
$bottomSheetProperties = Wire::decodeMap($bottomSheetHost->bytes);
if (
    $persistentBottomSheet->kind() !== NodeKind::Modal
    || $persistentBottomSheet->properties()[PropKey::ModalPresentation->value]
        !== ModalPresentation::FullScreen->value
    || $persistentBottomSheet->properties()[
        PropKey::BottomSheetDismissible->value
    ] !== false
    || ($bottomSheetProperties['snapPoints'] ?? null) !== "34\n68"
    || ($bottomSheetProperties['backdropOpacity'] ?? null) !== 1.0
    || ($bottomSheetProperties['dismissible'] ?? null) !== false
    || ($bottomSheetProperties['enablePanDownToClose'] ?? null) !== false
    || ($bottomSheetBackdrop?->properties()[PropKey::BackgroundColor->value] ?? null)
        !== 0x00000000
) {
    throw new RuntimeException(
        'Persistent p-bottom-sheet must keep native detents, transparent opt-out and every dismissal guard.',
    );
}

$materialBottomSheet = $bottomSheetClass::make([
    'open' => true,
    'snapPoints' => [34],
], Text::make('Sheet content'))->toElement();
$materialBottomSheetHostNode = $materialBottomSheet->children()[0] ?? null;
$materialBottomSheetHost = $materialBottomSheetHostNode?->properties()[
    PropKey::HostProperties->value
] ?? null;
$materialBottomSheetBackdrop = $materialBottomSheetHostNode?->children()[0] ?? null;
if (!$materialBottomSheetHost instanceof BinaryValue) {
    throw new RuntimeException('p-bottom-sheet default host properties are missing.');
}
$materialBottomSheetProperties = Wire::decodeMap($materialBottomSheetHost->bytes);
if (
    ($materialBottomSheet->properties()[
        PropKey::BottomSheetDismissible->value
    ] ?? null) !== true
    ||
    ($materialBottomSheetProperties['backdropOpacity'] ?? null) !== 1.0
    || ($materialBottomSheetBackdrop?->properties()[PropKey::BackgroundColor->value] ?? null)
        !== 0x66000000
) {
    throw new RuntimeException(
        'p-bottom-sheet must render one undiluted 40% Material scrim.',
    );
}

$menuClass = $tags['p-menu'];
$persistentMenu = $menuClass::make(
    ['open' => true, 'persistent' => true, 'dismissible' => true],
    Text::make('Trigger'),
    Text::make('Action'),
)->toElement();
$menuHost = $persistentMenu->properties()[PropKey::HostProperties->value] ?? null;
if (!$menuHost instanceof BinaryValue) {
    throw new RuntimeException('p-menu must retain its anchored native host.');
}
$menuProperties = Wire::decodeMap($menuHost->bytes);
if (
    ($menuProperties['dismissible'] ?? null) !== false
    || ($menuProperties['isDismissable'] ?? null) !== false
) {
    throw new RuntimeException(
        'A persistent p-menu must override conflicting dismissal properties.',
    );
}

$overlayClass = $tags['p-overlay'];
$overlay = $overlayClass::make(
    ['open' => true],
    Text::make('Loading'),
)->toElement();
if (
    $overlay->properties()[PropKey::ModalAnimationType->value]
        !== ModalAnimationType::Fade->value
) {
    throw new RuntimeException(
        'p-overlay must use the Material fade transition by default.',
    );
}

$navIconClass = $tags['p-app-bar-nav-icon'];
$navPressed = false;
$navIcon = $navIconClass::make(['icon' => 'menu'])->onPress(
    static function () use (&$navPressed): void {
        $navPressed = true;
    },
)->toElement();
if (
    $navIcon->kind() !== NodeKind::Pressable
    || ($navIcon->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Open navigation'
    || ($navIcon->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
    || ($navIcon->properties()[PropKey::MinHeight->value] ?? null) !== 48.0
    || ($navIcon->properties()[PropKey::Width->value] ?? null) !== 48.0
    || ($navIcon->properties()[PropKey::Height->value] ?? null) !== 48.0
    || ($navIcon->properties()[PropKey::PaddingHorizontal->value] ?? null) !== 0.0
    || ($navIcon->properties()[PropKey::BorderRadius->value] ?? null) !== 9999.0
    || ($navIcon->properties()[PropKey::HitSlopLeft->value] ?? null) !== 0.0
    || ($navIcon->properties()[PropKey::HitSlopTop->value] ?? null) !== 0.0
    || ($navIcon->properties()[PropKey::HitSlopRight->value] ?? null) !== 0.0
    || ($navIcon->properties()[PropKey::HitSlopBottom->value] ?? null) !== 0.0
    || ($navIcon->properties()[PropKey::RippleBorderless->value] ?? null) !== true
    || ($navIcon->properties()[PropKey::RippleRadius->value] ?? null) !== 20.0
    || ($navIcon->properties()[PropKey::RippleAlpha->value] ?? null) !== 0.12
    || (($navIcon->children()[0] ?? null)?->properties()[PropKey::Width->value] ?? null)
        !== 24.0
    || !isset($navIcon->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'p-app-bar-nav-icon must be an accessible 48dp navigation action.',
    );
}
$navIcon->events()[EventKind::Press->value]();
if (!$navPressed) {
    throw new RuntimeException('p-app-bar-nav-icon must publish its press callback.');
}
$smallNavIcon = $navIconClass::make(['icon' => 'menu', 'size' => 'small'])
    ->toElement();
$largeNavIcon = $navIconClass::make(['icon' => 'menu', 'size' => 'large'])
    ->toElement();
if (
    (($smallNavIcon->children()[0] ?? null)?->properties()[PropKey::Width->value] ?? null)
        !== 20.0
    || (($largeNavIcon->children()[0] ?? null)?->properties()[PropKey::Width->value] ?? null)
        !== 28.0
    || ($smallNavIcon->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
    || ($largeNavIcon->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
    || ($smallNavIcon->properties()[PropKey::Width->value] ?? null) !== 48.0
    || ($largeNavIcon->properties()[PropKey::Width->value] ?? null) !== 48.0
) {
    throw new RuntimeException(
        'Navigation icon sizes must change only the glyph while preserving the centered 48dp target.',
    );
}
$appBarClass = $tags['p-app-bar'];
$iconButtonClass = $tags['p-icon-btn'];
$appBarActionPressed = false;
$primaryAppBar = $appBarClass::make(
    ['color' => 'primary'],
    Text::make('PAM Workspace'),
    $iconButtonClass::make([
        'icon' => 'more-vert',
        'variant' => 'text',
        'accessibilityLabel' => 'More options',
    ])->onPress(static function () use (&$appBarActionPressed): void {
        $appBarActionPressed = true;
    }),
)->toElement();
$primaryAppBarTitle = $primaryAppBar->children()[0] ?? null;
$primaryAppBarAction = $primaryAppBar->children()[1] ?? null;
$primaryAppBarActionIcon = $primaryAppBarAction?->children()[0] ?? null;
$primaryAppBarActionHost = $primaryAppBarActionIcon?->properties()[
    PropKey::HostProperties->value
] ?? null;
$primaryAppBarActionNative = $primaryAppBarActionHost instanceof BinaryValue
    ? Wire::decodeMap($primaryAppBarActionHost->bytes)
    : null;
if (
    ($primaryAppBar->properties()[PropKey::BackgroundColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::Primary)
    || !$primaryAppBarTitle instanceof \Pam\Native\Element
    || ($primaryAppBarTitle->properties()[PropKey::TextColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::PrimaryForeground)
    || !$primaryAppBarAction instanceof \Pam\Native\Element
    || ($primaryAppBarAction->properties()[PropKey::BackgroundColor->value] ?? null)
        !== 0x00000000
    || !is_array($primaryAppBarActionNative)
    || ($primaryAppBarActionNative['color'] ?? null)
        !== Themes::pamLight()->color(ColorToken::PrimaryForeground)
    || !isset($primaryAppBarAction->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'Primary app bars must propagate contrast and expose transparent 48dp actions.',
    );
}
$primaryAppBarAction->events()[EventKind::Press->value]();
if (!$appBarActionPressed) {
    throw new RuntimeException('App bar actions must publish their press callback.');
}
$prominentAppBar = $appBarClass::make(
    ['prominent' => true],
    $navIconClass::make(['icon' => 'menu']),
    Text::make('PAM Workspace'),
    $iconButtonClass::make(['icon' => 'more-vert', 'variant' => 'text']),
)->toElement();
$prominentNavigation = $prominentAppBar->children()[0] ?? null;
$prominentTitle = $prominentAppBar->children()[1] ?? null;
$prominentAction = $prominentAppBar->children()[2] ?? null;
if (
    ($prominentAppBar->properties()[PropKey::MinHeight->value] ?? null) !== 128.0
    || ($prominentAppBar->properties()[PropKey::PositionType->value] ?? null)
        !== PositionType::Relative->value
    || !$prominentTitle instanceof \Pam\Native\Element
    || ($prominentTitle->properties()[PropKey::PositionType->value] ?? null)
        !== PositionType::Absolute->value
    || ($prominentTitle->properties()[PropKey::Left->value] ?? null) !== 16.0
    || ($prominentTitle->properties()[PropKey::Bottom->value] ?? null) !== 16.0
    || ($prominentTitle->properties()[PropKey::FontSize->value] ?? null) !== 32.0
    || ($prominentTitle->properties()[PropKey::LineHeight->value] ?? null) !== 40.0
    || ($prominentNavigation?->properties()[PropKey::Left->value] ?? null) !== 0.0
    || ($prominentNavigation?->properties()[PropKey::Top->value] ?? null) !== 4.0
    || ($prominentAction?->properties()[PropKey::Right->value] ?? null) !== 0.0
    || ($prominentAction?->properties()[PropKey::Top->value] ?? null) !== 4.0
) {
    throw new RuntimeException(
        'Prominent app bars must reserve the upper action row and bottom-anchor a large headline.',
    );
}

$calendarClass = $tags['p-calendar'];
$calendar = $calendarClass::make([
    'modelValue' => '2026-07-28',
])->toElement();
$calendarHeader = $calendar->children()[0] ?? null;
$calendarWeekdays = $calendar->children()[1] ?? null;
$calendarGrid = $calendar->children()[2] ?? null;
$previousMonth = $calendarHeader?->children()[0] ?? null;
$calendarSelectors = $calendarHeader?->children()[1] ?? null;
$calendarMonth = $calendarSelectors?->children()[0] ?? null;
$calendarYear = $calendarSelectors?->children()[1] ?? null;
$nextMonth = $calendarHeader?->children()[2] ?? null;
if (
    !$previousMonth instanceof \Pam\Native\Element
    || ($previousMonth->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Previous month'
    || ($previousMonth->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
    || !$nextMonth instanceof \Pam\Native\Element
    || ($nextMonth->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Next month'
    || ($nextMonth->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
    || count($calendar->children()) !== 3
    || !$calendarWeekdays instanceof \Pam\Native\Element
    || count($calendarWeekdays->children()) !== 7
    || ($calendarWeekdays->properties()[PropKey::Height->value] ?? null) !== 32.0
    || ($calendarWeekdays->properties()[PropKey::AccessibilityImportance->value] ?? null)
        !== AccessibilityImportance::NoHideDescendants->value
    || !$calendarGrid instanceof \Pam\Native\Element
    || ($calendarGrid->properties()[PropKey::Height->value] ?? null) !== 240.0
    || ($calendarMonth?->properties()[PropKey::Value->value] ?? null)
        !== 'pam:calendar-month-select'
    || ($calendarMonth?->properties()[PropKey::MinHeight->value] ?? null) !== 48.0
    || ($calendarYear?->properties()[PropKey::Value->value] ?? null)
        !== 'pam:calendar-year-select'
    || ($calendarYear?->properties()[PropKey::MinHeight->value] ?? null) !== 48.0
    || ($previousMonth->children()[0] ?? null)?->kind() !== NodeKind::CustomView
    || ($nextMonth->children()[0] ?? null)?->kind() !== NodeKind::CustomView
) {
    throw new RuntimeException(
        'p-calendar must expose Material header selectors, weekday labels, native icons and a 48dp day grid.',
    );
}

$fixedWeekCalendar = $calendarClass::make([
    'modelValue' => '2026-07-28',
    'fixedWeeks' => true,
    'showWeek' => true,
    'firstDayOfWeek' => 1,
    'locale' => 'pt-BR',
])->toElement();
$fixedWeekLabels = $fixedWeekCalendar->children()[1] ?? null;
$fixedWeekGrid = $fixedWeekCalendar->children()[2] ?? null;
if (
    !$fixedWeekLabels instanceof \Pam\Native\Element
    || count($fixedWeekLabels->children()) !== 8
    || ($fixedWeekLabels->children()[1]->properties()[PropKey::Text->value] ?? null) !== 'S'
    || !$fixedWeekGrid instanceof \Pam\Native\Element
    || ($fixedWeekGrid->properties()[PropKey::Height->value] ?? null) !== 288.0
    || ($fixedWeekCalendar->properties()[PropKey::MinHeight->value] ?? null) !== 376.0
) {
    throw new RuntimeException(
        'p-calendar fixed weeks, week numbers and localized first-day labels must preserve 48dp row geometry.',
    );
}

$fourWeekCalendar = $calendarClass::make([
    'modelValue' => '2026-02-15',
    'visibleDate' => '2026-02-01',
    'fixedWeeks' => false,
])->toElement();
$fourWeekGrid = $fourWeekCalendar->children()[2] ?? null;
if (
    !$fourWeekGrid instanceof \Pam\Native\Element
    || ($fourWeekGrid->properties()[PropKey::Height->value] ?? null) !== 192.0
    || ($fourWeekCalendar->properties()[PropKey::MinHeight->value] ?? null) !== 280.0
) {
    throw new RuntimeException(
        'p-calendar dynamic months must shrink to the exact number of 48dp weeks without leaving a blank row.',
    );
}

$multipleDates = null;
$multipleCalendar = $calendarClass::make([
    'modelValue' => ['2026-07-08', '2026-07-15'],
    'visibleDate' => '2026-07-01',
    'multiple' => true,
])->onChange(static function (array $value) use (&$multipleDates): void {
    $multipleDates = $value;
})->toElement();
$multipleCalendarHost = $multipleCalendar
    ->properties()[PropKey::HostProperties->value] ?? null;
$multipleChange = $multipleCalendar->events()[EventKind::Change->value] ?? null;
$multipleChange?->__invoke("M\n2026-07-08\n2026-07-22");
$multipleNative = $multipleCalendarHost instanceof BinaryValue
    ? Wire::decodeMap($multipleCalendarHost->bytes)
    : [];
if (
    ($multipleNative['mode'] ?? null) !== 2
    || ($multipleNative['selectedValues'] ?? null) !== "2026-07-08\n2026-07-15"
    || $multipleDates !== ['2026-07-08', '2026-07-22']
) {
    throw new RuntimeException(
        'p-calendar multiple mode must cross the native boundary and decode its semantic selection.',
    );
}

$rangeDates = null;
$rangeCalendar = $calendarClass::make([
    'modelValue' => ['from' => '2026-07-10', 'to' => '2026-07-18'],
    'visibleDate' => '2026-07-01',
    'multiple' => 'range',
])->onChange(static function (array $value) use (&$rangeDates): void {
    $rangeDates = $value;
})->toElement();
$rangeCalendarHost = $rangeCalendar
    ->properties()[PropKey::HostProperties->value] ?? null;
$rangeChange = $rangeCalendar->events()[EventKind::Change->value] ?? null;
$rangeChange?->__invoke("R\n2026-07-12\n2026-07-20");
$rangeNative = $rangeCalendarHost instanceof BinaryValue
    ? Wire::decodeMap($rangeCalendarHost->bytes)
    : [];
if (
    ($rangeNative['mode'] ?? null) !== 3
    || ($rangeNative['rangeFrom'] ?? null) !== '2026-07-10'
    || ($rangeNative['rangeTo'] ?? null) !== '2026-07-18'
    || $rangeDates !== ['from' => '2026-07-12', 'to' => '2026-07-20']
) {
    throw new RuntimeException(
        'p-calendar range mode must cross the native boundary and decode both endpoints.',
    );
}

$listRangeCalendar = $calendarClass::make([
    'modelValue' => ['2026-07-12', '2026-07-18'],
    'visibleDate' => '2026-07-01',
    'multiple' => 'range',
])->toElement();
$listRangeHost = $listRangeCalendar
    ->properties()[PropKey::HostProperties->value] ?? null;
$listRangeNative = $listRangeHost instanceof BinaryValue
    ? Wire::decodeMap($listRangeHost->bytes)
    : [];
if (
    ($listRangeNative['rangeFrom'] ?? null) !== '2026-07-12'
    || ($listRangeNative['rangeTo'] ?? null) !== '2026-07-18'
) {
    throw new RuntimeException(
        'p-calendar range mode must normalize a two-date list into native range endpoints.',
    );
}

$treeClass = $tags['p-treeview'];
$selectedTreePath = null;
$tree = $treeClass::make([
    'items' => [
        [
            'title' => 'Mobile',
            'value' => 'mobile',
            'children' => [
                ['title' => 'Android', 'value' => 'android'],
            ],
        ],
    ],
    'opened' => ['mobile'],
    'modelValue' => 'mobile',
])->onChange(
    static function (string $value) use (&$selectedTreePath): void {
        $selectedTreePath = $value;
    },
)->toElement();
$treeFolder = $tree->children()[0] ?? null;
$treeFile = ($treeFolder?->children()[1] ?? null)?->children()[0] ?? null;
$treeFolderHost = $treeFolder?->properties()[PropKey::HostProperties->value] ?? null;
$treeFolderNative = $treeFolderHost instanceof BinaryValue
    ? Wire::decodeMap($treeFolderHost->bytes)
    : [];
if (
    !isset($treeFolderNative['foregroundColor'])
    || !isset($treeFolderNative['selectedForegroundColor'])
    || !isset($treeFolderNative['selectedContainerColor'])
    || $treeFolderNative['foregroundColor']
        === $treeFolderNative['selectedForegroundColor']
) {
    throw new RuntimeException(
        'p-treeview-item must expose contrasting native selection colors.',
    );
}
$treeFilePress = $treeFile?->events()[EventKind::Press->value] ?? null;
if (($treeFolder?->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Mobile'
    || ($treeFile?->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Android') {
    throw new RuntimeException('Treeview must expose authored labels, not internal paths, to accessibility.');
}
if (!$treeFilePress instanceof Closure) {
    throw new RuntimeException(
        'p-treeview must bind selection handling to generated tree items.',
    );
}
$treeFilePress();
if ($selectedTreePath !== 'android') {
    throw new RuntimeException(
        'p-treeview must emit the selected item path.',
    );
}

require __DIR__.'/material-tree-state.php';
$imageClass = $tags['p-img'];
foreach (['disabled', 'isDisabled'] as $treeDisabledAlias) {
    $lockedTree = $treeClass::make([
        'items' => [[
            'title' => 'Archived', 'value' => 'archived', $treeDisabledAlias => true,
            'children' => [['title' => 'Child', 'value' => 'child', 'disabled' => false]],
        ]],
        'opened' => ['archived'],
        'modelValue' => 'child',
    ])->onChange(static function (string $value): void {
        throw new RuntimeException('Disabled tree branch must not emit a selection.');
    })->toElement();
    $lockedFolder = $lockedTree->children()[0];
    $lockedLeaf = $lockedFolder->children()[1]->children()[0];
    foreach ([$lockedFolder, $lockedLeaf] as $lockedNode) {
        if (($lockedNode->properties()[PropKey::Enabled->value] ?? true) !== false
            || isset($lockedNode->events()[EventKind::Press->value])) {
            throw new RuntimeException('Treeview must propagate disabled branches through generated descendants.');
        }
    }
}
$imageError = null;
$image = $imageClass::make([
    'src' => 'https://cdn.example.com/cover.jpg',
    'alt' => 'Community cover',
])->onError(
    static function (ImageErrorEvent $error) use (&$imageError): void {
        $imageError = $error;
    },
)->toElement();
$imageErrorHandler = $image->events()[EventKind::ImageError->value] ?? null;
if (
    $image->kind() !== NodeKind::Image
    || ($image->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Community cover'
    || !$imageErrorHandler instanceof Closure
) {
    throw new RuntimeException(
        'p-img must expose its alt text and typed native image events.',
    );
}
$imageErrorHandler(Wire::map(['error' => 'Network unavailable']));
if (
    !$imageError instanceof ImageErrorEvent
    || $imageError->message !== 'Network unavailable'
) {
    throw new RuntimeException(
        'p-img must adapt native failures to ImageErrorEvent.',
    );
}

$infiniteClass = $tags['p-infinite-scroll'];
$endReached = false;
$infinite = $infiniteClass::make(
    [
        'loading' => true,
        'loadingText' => 'Loading packages',
        'onEndReachedThreshold' => 0.25,
    ],
    Text::make('Package 1'),
)->onEndReached(static function () use (&$endReached): void {
    $endReached = true;
})->toElement();
$infiniteEvent = $infinite->events()[EventKind::EndReached->value] ?? null;
$loadingRow = $infinite->children()[1] ?? null;
if (
    $infinite->kind() !== NodeKind::VirtualList
    || !$infiniteEvent instanceof Closure
    || ($infinite->properties()[PropKey::EndReachedThreshold->value] ?? null)
        !== 0.25
    || !$loadingRow instanceof \Pam\Native\Element
    || ($loadingRow->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::ProgressBar->value
) {
    throw new RuntimeException(
        'p-infinite-scroll must expose end-reached behavior and an accessible loading row.',
    );
}
$infiniteEvent();
if (!$endReached) {
    throw new RuntimeException('p-infinite-scroll must emit its end-reached callback.');
}

$virtualListClass = $tags['p-virtual-list'];
foreach (['p-virtual-list', 'p-section-list'] as $listTag) {
    $listClass = $tags[$listTag];
    foreach ([null, 0xFF123456, 0x00000000] as $customForeground) {
        $list = $listClass::make([
            'items' => ['First', 'Second'],
            'sections' => ['Group' => ['First', 'Second']],
            'rowHeight' => 64,
            'scrollEnabled' => false,
        ]);
        if ($customForeground !== null) {
            $list = $list->style(new Style(textColor: $customForeground));
        }
        $properties = $list->toElement()->properties();
        if (($properties[PropKey::TextColor->value] ?? null)
            !== ($customForeground ?? ThemeManager::current()->color(ColorToken::Foreground))
            || ($properties[PropKey::ListRowHeight->value] ?? null) !== 64.0
            || ($properties[PropKey::ScrollEnabled->value] ?? null) !== false) {
            throw new RuntimeException('Lists must preserve explicit foreground, row height and disabled scrolling.');
        }
    }
}
$virtualListReachedEnd = false;
$virtualList = $virtualListClass::make(
    ['rowHeight' => 60, 'prefetch' => 10, 'numColumns' => 2],
    Text::make('First item'),
    Text::make('Second item'),
)->onEndReached(static function () use (&$virtualListReachedEnd): void {
    $virtualListReachedEnd = true;
})->toElement();
$virtualListEndEvent = $virtualList->events()[EventKind::EndReached->value] ?? null;
if (
    $virtualList->kind() !== NodeKind::VirtualList
    || count($virtualList->children()) !== 2
    || ($virtualList->properties()[PropKey::ListRowHeight->value] ?? null) !== 60.0
    || ($virtualList->properties()[PropKey::ListPrefetch->value] ?? null) !== 10
    || ($virtualList->properties()[PropKey::ListNumColumns->value] ?? null) !== 2
    || !$virtualListEndEvent instanceof Closure
) {
    throw new RuntimeException(
        'p-virtual-list must preserve rich children, virtualization tuning and end-reached behavior.',
    );
}
$virtualListEndEvent();
if (!$virtualListReachedEnd) {
    throw new RuntimeException('p-virtual-list must emit its end-reached callback.');
}

$sectionListClass = $tags['p-section-list'];
$sectionList = $sectionListClass::make([
    'sections' => [
        'Foundations' => ['Color', 'Typography'],
        'Platforms' => ['Android', 'UIKit'],
    ],
    'rowHeight' => 48,
    'prefetch' => 6,
])->toElement();
if (
    $sectionList->kind() !== NodeKind::SectionList
    || ($sectionList->properties()[PropKey::ListRowHeight->value] ?? null) !== 48.0
    || ($sectionList->properties()[PropKey::ListPrefetch->value] ?? null) !== 6
    || !($sectionList->properties()[PropKey::SectionItems->value] ?? null) instanceof BinaryValue
) {
    throw new RuntimeException(
        'p-section-list must compile typed sections into the native section-list contract.',
    );
}

$refreshClass = $tags['p-pull-to-refresh'];
$didRefresh = false;
$refresh = $refreshClass::make(
    ['refreshing' => true, 'progressViewOffset' => 24],
    Text::make('Pullable content'),
)->onRefresh(static function () use (&$didRefresh): void {
    $didRefresh = true;
})->toElement();
$refreshEvent = $refresh->events()[EventKind::Refresh->value] ?? null;
if (
    $refresh->kind() !== NodeKind::RefreshControl
    || ($refresh->properties()[PropKey::Refreshing->value] ?? null) !== true
    || ($refresh->properties()[PropKey::RefreshProgressViewOffset->value] ?? null) !== 24.0
    || !$refreshEvent instanceof Closure
) {
    throw new RuntimeException(
        'p-pull-to-refresh must expose native refresh state, offset and behavior.',
    );
}
$refreshEvent();
if (!$didRefresh) {
    throw new RuntimeException('p-pull-to-refresh must emit its refresh callback.');
}

$gridClass = $tags['p-responsive-grid'];
$grid = $gridClass::make(
    ['columns' => 3, 'columnGap' => 12, 'rowGap' => 8],
    Text::make('A'),
    Text::make('B'),
    Text::make('C'),
)->toElement();
if (
    $grid->kind() !== NodeKind::Column
    || count($grid->children()) !== 3
    || ($grid->properties()[PropKey::GridColumns->value] ?? null) !== 3
    || ($grid->properties()[PropKey::GridColumnGap->value] ?? null) !== 12.0
    || ($grid->properties()[PropKey::GridRowGap->value] ?? null) !== 8.0
) {
    throw new RuntimeException(
        'p-responsive-grid must map columns and independent gutters to the native grid engine.',
    );
}
foreach ($grid->children() as $cell) {
    if (($cell->properties()[PropKey::GridSpan->value] ?? null) !== 1) {
        throw new RuntimeException('Unwrapped grid children must occupy one engine column.');
    }
}
$spanningGrid = $gridClass::make(
    ['columns' => 3],
    Text::make('Spanning')->property(PropKey::Value, 'pam:grid-item:2,2,2,2,2,2'),
)->toElement();
foreach ([0, 1, 2, 8] as $authoredSpan) {
    $authoredGrid = $gridClass::make(
        ['columns' => 3],
        Text::make('Native span')->property(PropKey::GridSpan, $authoredSpan)
            ->property(PropKey::GridSpanMd, 3),
    )->toElement();
    $cellProps = $authoredGrid->children()[0]->properties();
    if (($cellProps[PropKey::GridSpan->value] ?? null) !== max(1, $authoredSpan)
        || ($cellProps[PropKey::GridSpanMd->value] ?? null) !== 3) {
        throw new RuntimeException('Grid composition must preserve authored native spans; the engine clamps to available columns.');
    }
}
$autoFitGrid = $gridClass::make(['columns' => 4, 'minColumnWidth' => 120], Text::make('Adaptive'))->toElement();
if (($autoFitGrid->properties()[PropKey::GridMinColumnWidth->value] ?? null) !== 120.0) {
    throw new RuntimeException('Minimum column width must be delegated to native layout, not a host-only resize.');
}
$adaptiveAutoFit = $gridClass::make(['columns' => ['default' => 1, 'md' => 4], 'minColumnWidth' => 120], Text::make('Mixed'))->toElement();
if (($adaptiveAutoFit->properties()[PropKey::GridMinColumnWidth->value] ?? null) !== 120.0
    || !isset($adaptiveAutoFit->properties()[PropKey::GridTemplate->value])) {
    throw new RuntimeException('Responsive auto-fit must preserve both the native template and minimum width.');
}
if (($spanningGrid->children()[0]->properties()[PropKey::GridSpan->value] ?? null) !== 2) {
    throw new RuntimeException('Fixed GridItem spans must reach the shared layout engine.');
}
foreach ([
    $gridClass::make(['columns' => ['default' => 1, 'md' => 3]], Text::make('Adaptive')),
    $gridClass::make(['columns' => 3], Text::make('Adaptive span')
        ->property(PropKey::Value, 'pam:grid-item:1,1,2,2,3,3')),
] as $adaptiveGrid) {
    if ($adaptiveGrid->toElement()->kind() !== NodeKind::Column
        || !isset($adaptiveGrid->toElement()->properties()[PropKey::GridTemplate->value])) {
        throw new RuntimeException('Responsive columns and spans must be measured by the shared engine.');
    }
}
$sixTierGrid = $gridClass::make([
    'columns' => ['default' => 2, 'sm' => 3, 'md' => 4, 'lg' => 5, 'xl' => 6, '2xl' => 8],
    'columnGap' => ['default' => 8, 'md' => 16], 'rowGap' => 12,
], Text::make('Six tiers')->property(PropKey::Value, 'pam:grid-item:1,2,3,4,5,6'))->toElement();
if (($sixTierGrid->properties()[PropKey::GridTemplate->value] ?? null)
    !== '0,2,8,12;640,3,8,12;768,4,16,12;1024,5,16,12;1280,6,16,12;1536,8,16,12') {
    throw new RuntimeException('UI breakpoints and independent gutters must survive the native template mapping.');
}
foreach ([PropKey::GridSpan, PropKey::GridSpanSm, PropKey::GridSpanMd,
    PropKey::GridSpanLg, PropKey::GridSpanXl, PropKey::GridSpan2xl] as $tier => $key) {
    if (($sixTierGrid->children()[0]->properties()[$key->value] ?? null) !== $tier + 1) {
        throw new RuntimeException('Every GridItem span tier must reach native layout.');
    }
}
foreach ([\Pam\Native\FlexDirection::RowReverse, \Pam\Native\FlexDirection::Column,
    \Pam\Native\FlexDirection::ColumnReverse] as $direction) {
    $reverseGrid = $gridClass::make(['columns' => 3, 'flexDirection' => $direction->value], Text::make('Direction'))->toElement();
    if (($reverseGrid->properties()[PropKey::HostName->value] ?? null) !== 'pam.mobile_ui.grid') {
        throw new RuntimeException('Non-row grids must retain their existing direction-aware host.');
    }
}

$scaffoldClass = $tags['p-app-scaffold'];
$scaffold = $scaffoldClass::make(
    ['edges' => ['top', 'bottom']],
    Text::make('Screen content'),
)->toElement();
if (
    $scaffold->kind() !== NodeKind::SafeAreaView
    || count($scaffold->children()) !== 1
    || ($scaffold->properties()[PropKey::SafeAreaTop->value] ?? null) !== true
    || ($scaffold->properties()[PropKey::SafeAreaRight->value] ?? null) !== false
    || ($scaffold->properties()[PropKey::SafeAreaBottomEdge->value] ?? null) !== true
    || ($scaffold->properties()[PropKey::SafeAreaLeft->value] ?? null) !== false
) {
    throw new RuntimeException(
        'p-app-scaffold must preserve content and explicit native safe-area edges.',
    );
}

$searchClass = $tags['p-search-bar'];
foreach ([
    [EventKind::InputEndEditing, 'onEndEditing'],
    [EventKind::InputSelectionChange, 'onSelectionChange'],
    [EventKind::InputContentSizeChange, 'onContentSizeChange'],
    [EventKind::InputKeyPress, 'onKeyPress'],
] as [$searchNativeEvent, $searchEventMethod]) {
    $nativePayload = null;
    $nativeHandler = static function (mixed $payload) use (&$nativePayload): void {
        $nativePayload = $payload;
    };
    $eventSearch = $searchClass::make()->{$searchEventMethod}($nativeHandler)->toElement();
    $nativeEditor = $eventSearch->children()[1] ?? null;
    $forwarded = $nativeEditor?->events()[$searchNativeEvent->value] ?? null;
    if ($forwarded !== $nativeHandler) {
        throw new RuntimeException('Search must preserve all native input event handlers.');
    }
    $forwarded(['selectionStart' => 2]);
    if ($nativePayload !== ['selectionStart' => 2]) {
        throw new RuntimeException('Search must not transform native event payloads.');
    }
}
$searchValue = null;
$submittedQuery = null;
$search = $searchClass::make([
    'modelValue' => 'native',
    'placeholder' => 'Search components',
    'accessibilityLabel' => 'Search components',
])->onChange(static function (string $value) use (&$searchValue): void {
    $searchValue = $value;
})->onSubmit(static function (string $value) use (&$submittedQuery): void {
    $submittedQuery = $value;
})->toElement();
$searchInput = null;
foreach ($search->children() as $child) {
    if ($child->kind() === NodeKind::Input) {
        $searchInput = $child;
        break;
    }
}
$searchChange = $searchInput?->events()[EventKind::Change->value] ?? null;
if (
    $search->kind() !== NodeKind::Row
    || count($search->children()) !== 2
    || ($search->properties()[PropKey::Accessible->value] ?? null) !== false
    || ($search->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::Generic->value
    || !$searchInput instanceof \Pam\Native\Element
    || ($searchInput->properties()[PropKey::Value->value] ?? null) !== 'native'
    || ($searchInput->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Search components'
    || ($searchInput->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::Search->value
    || !$searchChange instanceof Closure
) {
    throw new RuntimeException(
        'p-search-bar must expose one labeled native input and no duplicate compound semantics.',
    );
}
$searchChange('navigation');
$searchSubmit = $searchInput->events()[EventKind::Submit->value] ?? null;
if (!$searchSubmit instanceof Closure) {
    throw new RuntimeException('Search must forward native keyboard submission to its consumer.');
}
$searchSubmit('navigation');
if ($submittedQuery !== 'navigation') {
    throw new RuntimeException('Search submission must preserve the query payload.');
}
foreach ([[], ['disabled' => true], ['isDisabled' => true], ['readonly' => true],
    ['readOnly' => true], ['isReadOnly' => true], ['editable' => false], ['modelValue' => '']] as $clearCase) {
    $clearedValue = null;
    $clearSearch = $searchClass::make([
        'clearable' => true, 'clearLabel' => 'Limpar busca', 'modelValue' => 'query', ...$clearCase,
    ])->onChange(static function (string $value) use (&$clearedValue): void {
        $clearedValue = $value;
    })->toElement();
    $clearButton = $clearSearch->children()[2] ?? null;
    $clearAction = $clearButton?->events()[EventKind::Press->value] ?? null;
    $canClear = $clearCase === [];
    if (!$clearButton instanceof \Pam\Native\Element
        || ($clearButton->properties()[PropKey::Enabled->value] ?? null) !== $canClear
        || ($clearButton->properties()[PropKey::Width->value] ?? null) !== 48.0
        || ($clearButton->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Limpar busca'
        || ($clearAction instanceof Closure) !== $canClear) {
        throw new RuntimeException('Search clear action must retain its touch area and honor all editing locks.');
    }
    if ($clearAction instanceof Closure) {
        $clearAction();
        if ($clearedValue !== '') {
            throw new RuntimeException('Search clear must publish an empty controlled query.');
        }
    }
}
if ($searchValue !== 'navigation') {
    throw new RuntimeException('p-search-bar must emit its native text change.');
}
foreach (['readonly', 'readOnly', 'isReadOnly', 'disabled', 'isDisabled'] as $searchLock) {
    $lockedSearch = $searchClass::make([
        $searchLock => true, 'modelValue' => 'Saved query',
        'accessibilityLabel' => 'Search documents',
    ])->toElement();
    $inputs = array_values(array_filter($lockedSearch->children(),
        static fn (\Pam\Native\Element $element): bool => $element->kind() === NodeKind::Input));
    if (count($inputs) !== 1
        || ($inputs[0]->properties()[PropKey::InputEditable->value] ?? true) !== false
        || ($inputs[0]->properties()[PropKey::Value->value] ?? null) !== 'Saved query'
        || ($inputs[0]->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Search documents') {
        throw new RuntimeException('Locked search must preserve its query and accessible label without editing.');
    }
}

$paginationClass = $tags['p-pagination'];
$selectedPage = null;
$pagination = $paginationClass::make([
    'modelValue' => 3,
    'length' => 7,
    'totalVisible' => 5,
])->onChange(static function (int $page) use (&$selectedPage): void {
    $selectedPage = $page;
})->toElement();
$pageButtons = $pagination->children();
$pageTwoPress = $pageButtons[1]->events()[EventKind::Press->value] ?? null;
if (
    $pagination->kind() !== NodeKind::Row
    || count($pageButtons) !== 5
    || ($pageButtons[2]->properties()[PropKey::Selected->value] ?? null) !== true
    || ($pageButtons[2]->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Page 3 of 7'
    || !$pageTwoPress instanceof Closure
    || array_any(
        $pageButtons,
        static fn (\Pam\Native\Element $button): bool =>
            ($button->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
            || ($button->properties()[PropKey::MinHeight->value] ?? null) !== 48.0,
    )
) {
    throw new RuntimeException(
        'p-pagination must generate accessible 48dp page controls and controlled selection.',
    );
}
$pageTwoPress();
if ($selectedPage !== 2) {
    throw new RuntimeException('p-pagination must emit the selected page.');
}

$segmentedClass = $tags['p-segmented-button'];
foreach (['p-pagination', 'p-segmented-button', 'p-filter-bar'] as $lockedTag) {
    foreach (['readonly', 'readOnly', 'isReadOnly'] as $alias) {
        $locked = $tags[$lockedTag]::make([
            'length' => 2,
            'items' => [['value' => 1, 'label' => 'First'], ['value' => 2, 'label' => 'Second']],
            'modelValue' => $lockedTag === 'p-filter-bar' ? [1] : 1,
            $alias => true,
        ])->onChange(static function (mixed $next): void {
            throw new RuntimeException('Readonly control dispatched a mutation.');
        })->toElement()->children();
        if (count($locked) !== 2 || ($locked[0]->properties()[PropKey::Selected->value] ?? null) !== true) {
            throw new RuntimeException($lockedTag.' must preserve selection without exposing clear-all when readonly.');
        }
        foreach ($locked as $control) {
            if (($control->properties()[PropKey::Enabled->value] ?? null) !== false
                || isset($control->events()[EventKind::Press->value])) {
                throw new RuntimeException($lockedTag.' must block generated controls for '.$alias);
            }
        }
    }
}
foreach (['p-segmented-button', 'p-filter-bar'] as $lockedTag) {
    $controls = $tags[$lockedTag]::make([
        'items' => [
            ['value' => 1, 'label' => 'Locked', 'isDisabled' => true],
            ['value' => 2, 'label' => 'Available', 'disabled' => false, 'isDisabled' => true],
        ],
    ])->onChange(static function (mixed $next): void {})->toElement()->children();
    if (($controls[0]->properties()[PropKey::Enabled->value] ?? null) !== false
        || isset($controls[0]->events()[EventKind::Press->value])
        || ($controls[1]->properties()[PropKey::Enabled->value] ?? null) !== true
        || !isset($controls[1]->events()[EventKind::Press->value])) {
        throw new RuntimeException($lockedTag.' must honor item isDisabled with explicit disabled precedence.');
    }
}
foreach ([1, 2, 3, 5] as $visiblePages) {
    $pages = $tags['p-pagination']::make([
        'length' => 2000, 'modelValue' => 1234, 'totalVisible' => $visiblePages,
    ])->toElement()->children();
    if (count($pages) !== $visiblePages + ($visiblePages < 3 ? 2 : 0)) {
        throw new RuntimeException('Pagination must retain numbered-page count plus compact navigation.');
    }
    foreach ($pages as $page) {
        if (
            isset($page->properties()[PropKey::Width->value])
            || isset($page->properties()[PropKey::Height->value])
            || ($page->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
            || ($page->properties()[PropKey::PaddingHorizontal->value] ?? null) !== 12.0
        ) {
            throw new RuntimeException('Large page labels must retain padding and grow beyond the minimum target.');
        }
    }
}
foreach ([1, 2] as $window) {
    foreach ([1, 5, 10] as $currentPage) {
        foreach ([false, true] as $locked) {
            $emittedPages = [];
            $controls = $paginationClass::make([
                'length' => 10, 'modelValue' => $currentPage,
                'totalVisible' => $window, 'readonly' => $locked,
            ])->onChange(static function (int $page) use (&$emittedPages): void {
                $emittedPages[] = $page;
            })->toElement()->children();
            foreach ([0 => max(1, $currentPage - 1), count($controls) - 1 => min(10, $currentPage + 1)] as $index => $target) {
                $press = $controls[$index]->events()[EventKind::Press->value] ?? null;
                $canMove = !$locked && $target !== $currentPage;
                if (($press instanceof Closure) !== $canMove
                    || ($controls[$index]->properties()[PropKey::Enabled->value] ?? null) !== $canMove) {
                    throw new RuntimeException('Compact pagination must respect bounds and readonly.');
                }
                if ($press instanceof Closure) {
                    $press();
                    if (end($emittedPages) !== $target) {
                        throw new RuntimeException('Compact navigation emitted the wrong page.');
                    }
                }
            }
        }
    }
}
foreach ([false, true] as $disabled) {
    $pages = $tags['p-pagination']::make([
        'length' => 3, 'modelValue' => 1, 'disabled' => $disabled,
    ])->onChange(static function (int $value): void {})->toElement()->children();
    foreach ($pages as $index => $page) {
        if (
            ($page->properties()[PropKey::Enabled->value] ?? null) !== !$disabled
            || isset($page->events()[EventKind::Press->value]) !== (!$disabled && $index !== 0)
        ) {
            throw new RuntimeException('Pagination must disable every generated page action.');
        }
    }
    $filters = $tags['p-filter-bar']::make([
        'items' => [
            ['label' => 'Available', 'value' => 1],
            ['label' => 'Unavailable', 'value' => 2, 'disabled' => true],
            ['label' => 'Another', 'value' => 3, 'disabled' => false],
        ],
        'modelValue' => [], 'disabled' => $disabled,
    ])->onChange(static function (array $value): void {})->toElement()->children();
    if (count($filters) !== 3) {
        throw new RuntimeException('Filter disabled-state fixture must expose all three controls.');
    }
    foreach ($filters as $index => $filter) {
        $expectedDisabled = $disabled || $index === 1;
        if (
            ($filter->properties()[PropKey::Enabled->value] ?? null) !== !$expectedDisabled
            || isset($filter->events()[EventKind::Press->value]) === $expectedDisabled
        ) {
            throw new RuntimeException('Filters must honor both item and group disabled state.');
        }
    }
}
$selectedSegment = null;
foreach ([false, true] as $selectedListItem) {
    $item = $tags['p-list-item']::make(['selected' => $selectedListItem],
        Text::make('Inherited label'),
        Text::make('Custom label')->style(new Style(textColor: 0xFF123456)),
    )->toElement();
    $expectedForeground = \Pam\MobileUi\Theme\ThemeManager::current()->color($selectedListItem
        ? \Pam\MobileUi\Enum\ColorToken::AccentForeground
        : \Pam\MobileUi\Enum\ColorToken::OnSurface);
    if (($item->children()[0]->properties()[PropKey::TextColor->value] ?? null) !== $expectedForeground
        || ($item->children()[1]->properties()[PropKey::TextColor->value] ?? null) !== 0xFF123456) {
        throw new RuntimeException('List Item direct text must inherit semantic foreground without replacing explicit colors.');
    }
}
foreach (['p-navigation-bar', 'p-navigation-rail'] as $navigationTag) {
    $navigationRoute = new \App\ComponentRoute($navigationTag, 'Navigation', $tags[$navigationTag]);
    foreach ([[], ['modelValue' => 1], ['modelValue' => 3]] as $variation) {
        $navigationDefaults = $samplePropsMethod->invoke($navigationRoute, $variation);
        if (!is_array($navigationDefaults)
            || ($navigationDefaults['modelValue'] ?? null) !== ($variation['modelValue'] ?? 2)) {
            throw new RuntimeException('Navigation showcase defaults must use integer IDs and preserve explicit initial selection.');
        }
    }
}
$segmentedRoute = new \App\ComponentRoute('p-segmented-button', 'Segmented Button', $tags['p-segmented-button']);
$segmentedDefaults = $samplePropsMethod->invoke($segmentedRoute, []);
if (!is_array($segmentedDefaults) || ($segmentedDefaults['modelValue'] ?? null) !== 2) {
    throw new RuntimeException('Segmented showcase defaults must match the integer item values.');
}
foreach ([[2, 1], [2]] as $filterSelection) {
    $clearedFilters = null;
    $protectedFilters = $tags['p-filter-bar']::make([
        'items' => [
            ['value' => 1, 'label' => 'Editable'],
            ['value' => 2, 'label' => 'Required', 'disabled' => true],
        ],
        'modelValue' => $filterSelection,
    ])->onChange(static function (array $next) use (&$clearedFilters): void {
        $clearedFilters = $next;
    })->toElement()->children();
    $clearFilters = $protectedFilters[2] ?? null;
    $protectedContent = $protectedFilters[1]->children()[0];
    $selectionMark = $protectedContent->children()[0] ?? null;
    if (count($protectedContent->children()) !== 2
        || ($selectionMark?->properties()[PropKey::Width->value] ?? null) !== 16.0
        || ($selectionMark?->properties()[PropKey::Height->value] ?? null) !== 16.0) {
        throw new RuntimeException('Selected protected filters must retain a visible selection mark beside their label.');
    }
    if ($filterSelection === [2]) {
        if ($protectedFilters[0]->children()[0]->kind() !== NodeKind::Text) {
            throw new RuntimeException('Unselected filters must not display a selection mark.');
        }
        if ($clearFilters !== null) {
            throw new RuntimeException('Protected-only filters must not offer a no-op clear action.');
        }
    } else {
        $clearFiltersHandler = $clearFilters?->events()[EventKind::Press->value] ?? null;
        if (!$clearFiltersHandler instanceof Closure) {
            throw new RuntimeException('Editable selected filters must expose a clear action.');
        }
        $clearFiltersHandler();
        if ($clearedFilters !== [2]) {
            throw new RuntimeException('Clearing filters must preserve disabled selections.');
        }
    }
}
foreach (['p-pagination', 'p-filter-bar', 'p-segmented-button', 'p-tree-select'] as $disabledTag) {
    foreach ([true, false] as $disabledValue) {
        $props = [
            'length' => 2,
            'items' => [['value' => 1, 'label' => 'First'], ['value' => 2, 'label' => 'Second']],
            'modelValue' => 1,
            'isDisabled' => $disabledValue,
        ];
        $aliased = $tags[$disabledTag]::make($props)
            ->onChange(static function (mixed $next): void {})->toElement();
        foreach (array_slice($aliased->children(), 0, 2) as $control) {
            if (($control->properties()[PropKey::Enabled->value] ?? null) !== !$disabledValue) {
                throw new RuntimeException($disabledTag.' must apply isDisabled to every generated control.');
            }
            if ($disabledValue && isset($control->events()[EventKind::Press->value])) {
                throw new RuntimeException($disabledTag.' disabled alias must suppress child handlers.');
            }
        }
        if ($disabledValue && $disabledTag === 'p-filter-bar' && count($aliased->children()) !== 2) {
            throw new RuntimeException('Disabled filters must not expose a clear-all action.');
        }
    }
}
$explicitEnabled = $tags['p-pagination']::make([
    'length' => 2, 'modelValue' => 1, 'disabled' => false, 'isDisabled' => true,
])->onChange(static function (int $page): void {})->toElement()->children()[1];
if (($explicitEnabled->properties()[PropKey::Enabled->value] ?? null) !== true
    || !isset($explicitEnabled->events()[EventKind::Press->value])) {
    throw new RuntimeException('Explicit disabled=false must take precedence over the compatibility alias.');
}
$segmented = $segmentedClass::make([
    'items' => [
        ['label' => 'Day', 'value' => 1],
        ['label' => 'Week', 'value' => 2],
        ['label' => 'Month', 'value' => 3],
    ],
    'modelValue' => 2,
])->onChange(static function (int $value) use (&$selectedSegment): void {
    $selectedSegment = $value;
})->toElement();
$segmentControls = $segmented->children();
$dayPress = $segmentControls[0]->events()[EventKind::Press->value] ?? null;
if (
    $segmented->kind() !== NodeKind::Row
    || count($segmentControls) !== 3
    || ($segmentControls[1]->properties()[PropKey::Selected->value] ?? null) !== true
    || ($segmentControls[1]->properties()[PropKey::Checked->value] ?? null) !== true
    || ($segmentControls[1]->properties()[PropKey::AccessibilityCheckedState->value] ?? null)
        !== AccessibilityCheckedState::Checked->value
    || ($segmentControls[1]->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::ToggleButton->value
    || !$dayPress instanceof Closure
    || array_any(
        $segmentControls,
        static fn (\Pam\Native\Element $control): bool =>
            ($control->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
            || ($control->properties()[PropKey::MinHeight->value] ?? null) !== 48.0,
    )
) {
    throw new RuntimeException(
        'p-segmented-button must generate accessible controlled native toggle buttons.',
    );
}
$dayPress();
if ($selectedSegment !== 1) {
    throw new RuntimeException('p-segmented-button must emit its selected value.');
}
foreach ([false, true] as $groupDisabled) {
    $disabledSegments = $segmentedClass::make([
        'items' => [
            ['label' => 'View', 'value' => 1],
            ['label' => 'Edit', 'value' => 2, 'disabled' => true],
            ['label' => 'Share', 'value' => 3, 'disabled' => false],
        ],
        'modelValue' => 1,
        'disabled' => $groupDisabled,
    ])->onChange(static function (int $value): void {})->toElement()->children();
    foreach ($disabledSegments as $index => $control) {
        $expectedDisabled = $groupDisabled || $index === 1;
        if (
            ($control->properties()[PropKey::Enabled->value] ?? null) !== !$expectedDisabled
            || isset($control->events()[EventKind::Press->value]) === $expectedDisabled
            || ($control->properties()[PropKey::Opacity->value] ?? null)
                !== ($expectedDisabled ? MaterialTokens::STATE_OPACITY[6] : 1.0)
        ) {
            throw new RuntimeException('Segmented items must honor individual and group disabled state.');
        }
    }
}
$iconSegments = $segmentedClass::make([
    'items' => ['Day', 'Week', 'Month'],
    'icons' => true,
])->toElement()->children();
foreach ($iconSegments as $control) {
    $row = $control->children()[0];
    $label = $row->children()[1];
    if (
        isset($control->properties()[PropKey::Height->value])
        || ($control->properties()[PropKey::PaddingVertical->value] ?? null) !== 8.0
        || ($control->properties()[PropKey::FlexShrink->value] ?? null) !== 1.0
        || ($control->properties()[PropKey::Width->value] ?? null) !== 0.0
        || ($label->properties()[PropKey::FlexShrink->value] ?? null) !== 0.0
        || $row->kind() !== NodeKind::Column
    ) {
        throw new RuntimeException('Segment labels must wrap inside padded, naturally growing controls.');
    }
}
if (
    count($iconSegments) !== 3
    || array_any(
        $iconSegments,
        static fn (\Pam\Native\Element $control): bool =>
            count($control->children()) !== 1
            || $control->children()[0]->kind() !== NodeKind::Column
            || count($control->children()[0]->children()) !== 2,
    )
) {
    throw new RuntimeException(
        'p-segmented-button icons variation must keep every icon above its label consistently.',
    );
}

foreach (['p-data-table', 'p-data-table-virtual', 'p-data-grid'] as $tableTag) {
    foreach (['disabled', 'isDisabled', 'loading', 'isLoading', 'readonly', 'readOnly', 'isReadOnly'] as $lock) {
        $lockedTable = $tags[$tableTag]::make([
            $lock => true, 'showSelect' => true,
            'headers' => [['key' => 'name', 'title' => 'Name']],
            'items' => [['id' => 1, 'name' => 'Ada']],
        ])->onChange(static function (): void { throw new RuntimeException('Locked table changed.'); })->toElement();
        $lockedStack = [$lockedTable];
        $lockedControls = 0;
        while ($lockedStack !== []) {
            $node = array_pop($lockedStack);
            if (($node->properties()[PropKey::AccessibilityRole->value] ?? null) === AccessibilityRole::Checkbox->value) {
                $lockedControls++;
                if (isset($node->events()[EventKind::Press->value])
                    || ($node->properties()[PropKey::Enabled->value] ?? null) !== false) {
                    throw new RuntimeException($tableTag.' must block selection for '.$lock);
                }
            }
            array_push($lockedStack, ...$node->children());
        }
        if ($lockedControls === 0) {
            throw new RuntimeException('Locked selection test must inspect actual controls.');
        }
    }
}
$dataTableClass = $tags['p-data-table'];
$longCellValue = 'International product research and accessibility';
$longCellMethod = new ReflectionMethod(\Pam\MobileUi\Rendering\ComponentRenderer::class, 'materialDataTableCell');
$longCell = $longCellMethod->invoke(null, $longCellValue, 72.0, false);
if (!$longCell instanceof \Pam\Native\Element) {
    throw new RuntimeException('Table cell must render an element.');
}
$longText = $longCell->children()[0]->properties();
$inspectableCell = $longCellMethod->invoke(null, $longCellValue, 72.0, false, true, 'Workspace');
if (!$inspectableCell instanceof \Pam\Native\Element
    || !isset($inspectableCell->events()[EventKind::Press->value])
    || ($inspectableCell->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Workspace: '.$longCellValue
    || ($inspectableCell->properties()[PropKey::AccessibilityRole->value] ?? null) !== AccessibilityRole::Button->value) {
    throw new RuntimeException('Inspectable cells must expose a named action for the full value.');
}
if (($longText[PropKey::AccessibilityLabel->value] ?? null) !== $longCellValue
    || ($longText[PropKey::TextEllipsizeMode->value] ?? null) !== \Pam\Native\TextEllipsizeMode::Tail->value
    || ($longText[PropKey::MaxWidthPercent->value] ?? null) !== 100.0
    || isset($longText[PropKey::WidthPercent->value])) {
    throw new RuntimeException('Long table values must constrain text and retain the full accessible name.');
}
foreach (['rowHeight', 'itemHeight'] as $heightProp) {
    $tallGrid = $tags['p-data-grid']::make([
        $heightProp => 72.0, 'showSelect' => true,
        'headers' => [['key' => 'name', 'title' => 'Name']],
        'items' => [['id' => 1, 'name' => 'Workspace']],
    ])->toElement();
    $tallStack = [$tallGrid];
    $foundVirtualHeight = false;
    $foundCellHeight = false;
    while ($tallStack !== []) {
        $node = array_pop($tallStack);
        $properties = $node->properties();
        if (isset($properties[PropKey::ListRowHeight->value])) {
            $foundVirtualHeight = $properties[PropKey::ListRowHeight->value] === 72.0;
        }
        if (($properties[PropKey::AccessibilityLabel->value] ?? null) === 'Select row 1') {
            $foundCellHeight = ($properties[PropKey::Height->value] ?? null) === 72.0;
        }
        array_push($tallStack, ...$node->children());
    }
    if (!$foundVirtualHeight || !$foundCellHeight) {
        throw new RuntimeException('Grid cells and virtual rows must honor the same explicit row height.');
    }
}
foreach ([[2], [1, 2]] as $initialSelection) {
    $bulkResult = null;
    $bulkTable = $dataTableClass::make([
        'showSelect' => true, 'modelValue' => $initialSelection,
        'headers' => [['key' => 'name', 'title' => 'Name']],
        'items' => [['id' => 1, 'name' => 'Available'], ['id' => 2, 'name' => 'Protected', 'disabled' => true]],
    ])->onChange(static function (array $value) use (&$bulkResult): void { $bulkResult = $value; })->toElement();
    $bulkStack = [$bulkTable];
    $bulkPress = null;
    while ($bulkStack !== []) {
        $node = array_pop($bulkStack);
        $nodeLabel = $node->properties()[PropKey::AccessibilityLabel->value] ?? null;
        if ($nodeLabel === 'Select all rows') {
            $bulkPress = $node->events()[EventKind::Press->value] ?? null;
        }
        if ($node->kind() === NodeKind::Row && count($node->children()) === 2) {
            $selectionColumn = $node->children()[0]->properties();
            $dataColumn = $node->children()[1]->properties();
            if (($selectionColumn[PropKey::Width->value] ?? null) !== 48.0
                || ($selectionColumn[PropKey::FlexGrow->value] ?? null) !== 0.0
                || ($dataColumn[PropKey::Width->value] ?? null) !== 0.0
                || ($dataColumn[PropKey::FlexGrow->value] ?? null) !== 1.0) {
                throw new RuntimeException('Selection column must stay compact while data fills remaining width.');
            }
        }
        if ($nodeLabel === 'Select row 2' && (isset($node->events()[EventKind::Press->value])
            || ($node->properties()[PropKey::Enabled->value] ?? null) !== false)) {
            throw new RuntimeException('Protected row selection must be disabled.');
        }
        array_push($bulkStack, ...$node->children());
    }
    if (!$bulkPress instanceof Closure) {
        throw new RuntimeException('Available rows require a bulk selection action.');
    }
    $bulkPress();
    if ($bulkResult !== (count($initialSelection) === 1 ? [2, 1] : [2])) {
        throw new RuntimeException('Bulk selection must preserve protected selections.');
    }
}
$selectedRows = null;
$dataTable = $dataTableClass::make([
    'headers' => [
        ['title' => 'Name', 'key' => 'name'],
    ],
    'items' => [
        ['id' => 1, 'name' => 'Ada'],
        ['id' => 2, 'name' => 'Grace'],
    ],
    'modelValue' => [1],
    'showSelect' => true,
])->onChange(
    static function (array $value) use (&$selectedRows): void {
        $selectedRows = $value;
    },
)->toElement();
$selectedCell = (($dataTable->children()[1] ?? null)?->children()[0] ?? null)
    ?->children()[0] ?? null;
$secondCell = (($dataTable->children()[2] ?? null)?->children()[0] ?? null)
    ?->children()[0] ?? null;
$secondPress = $secondCell?->events()[EventKind::Press->value] ?? null;
if (
    !$selectedCell instanceof \Pam\Native\Element
    || ($selectedCell->properties()[
        PropKey::AccessibilityCheckedState->value
    ] ?? null) !== AccessibilityCheckedState::Checked->value
    || !$secondCell instanceof \Pam\Native\Element
    || ($secondCell->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Select row 2'
    || !$secondPress instanceof Closure
) {
    throw new RuntimeException(
        'p-data-table selectable rows must expose controlled checkbox actions.',
    );
}
$secondPress();
$selectionMark = ($selectedCell->children()[0] ?? null)?->children()[0] ?? null;
if (!$selectionMark instanceof \Pam\Native\Element) {
    throw new RuntimeException('Selected table cells must contain a vector selection mark.');
}
$selectionMarkHost = $selectionMark->properties()[PropKey::HostProperties->value] ?? null;
if (!$selectionMarkHost instanceof BinaryValue
    || (Wire::decodeMap($selectionMarkHost->bytes)['color'] ?? null)
        !== \Pam\MobileUi\Theme\ThemeManager::current()->color(ColorToken::PrimaryForeground)
    || ($selectionMark->properties()[PropKey::Width->value] ?? null) !== 16.0
    || ($selectionMark->properties()[PropKey::Height->value] ?? null) !== 16.0
    || ($secondCell->children()[0] ?? null)?->children() !== []) {
    throw new RuntimeException('Table selection must use a bounded, contrasting vector mark only when selected.');
}
if ($selectedRows !== [1, 2]) {
    throw new RuntimeException(
        'p-data-table row selection must emit the updated model value.',
    );
}

$virtualTableClass = $tags['p-data-table-virtual'];
foreach ([
    [[], AccessibilityCheckedState::Unchecked],
    [[1], AccessibilityCheckedState::Mixed],
    [[1, 2], AccessibilityCheckedState::Checked],
] as [$headerSelection, $expectedHeaderState]) {
    $headerTable = $tags['p-data-table']::make([
        'headers' => [['title' => 'Name', 'key' => 'name']],
        'items' => [['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Grace']],
        'showSelect' => true, 'modelValue' => $headerSelection,
    ])->toElement();
    $headerStack = [$headerTable];
    $headerMatches = 0;
    while ($headerStack !== []) {
        $headerNode = array_pop($headerStack);
        if (($headerNode->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Select all rows') {
            $headerMatches++;
            if (($headerNode->properties()[PropKey::AccessibilityCheckedState->value] ?? null) !== $expectedHeaderState->value) {
                throw new RuntimeException('Table select-all must distinguish none, partial and complete selection.');
            }
            $headerMark = ($headerNode->children()[0] ?? null)?->children() ?? [];
            if (count($headerMark) !== ($headerSelection === [] ? 0 : 1)) {
                throw new RuntimeException('Partial and complete table selection must have a visible mark.');
            }
        }
        array_push($headerStack, ...$headerNode->children());
    }
    if ($headerMatches !== 1) throw new RuntimeException('Table must expose exactly one select-all header.');
}
$virtualTable = $virtualTableClass::make([
    'headers' => [
        ['title' => 'Name', 'key' => 'name'],
    ],
    'items' => [
        ['id' => 1, 'name' => 'Ada'],
        ['id' => 2, 'name' => 'Grace'],
    ],
])->toElement();
$virtualRows = $virtualTable->children()[0] ?? null;
if (
    ($virtualTable->properties()[PropKey::Height->value] ?? null) !== 312.0
    || !$virtualRows instanceof \Pam\Native\Element
    || $virtualRows->kind() !== NodeKind::VirtualList
    || ($virtualRows->properties()[PropKey::WidthPercent->value] ?? null) !== 100.0
    || ($virtualRows->properties()[PropKey::FlexGrow->value] ?? null) !== 1.0
    || ($virtualRows->properties()[PropKey::FlexShrink->value] ?? null) !== 1.0
) {
    throw new RuntimeException(
        'p-data-table-virtual must expose a bounded viewport filled by its native list.',
    );
}

$emptyTable = $dataTableClass::make([
    'headers' => [
        ['title' => 'Name', 'key' => 'name'],
    ],
    'items' => [],
    'noDataText' => 'No packages found',
])->toElement();
$emptyState = (($emptyTable->children()[1] ?? null)?->children()[0] ?? null)
    ?->children()[0] ?? null;
if (
    !$emptyState instanceof \Pam\Native\Element
    || ($emptyState->properties()[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::Summary->value
    || ($emptyState->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'No packages found'
) {
    throw new RuntimeException(
        'An empty p-data-table must expose its no-data state accessibly.',
    );
}

$linearProgressClass = $tags['p-progress-linear'];
$indeterminateProgress = $linearProgressClass::make([
    'indeterminate' => true,
    'reduceMotion' => true,
])->toElement();
$progressFill = $indeterminateProgress->children()[1] ?? null;
if (
    ($indeterminateProgress->properties()[
        PropKey::AccessibilityBusy->value
    ] ?? null) !== true
    || isset($indeterminateProgress->properties()[
        PropKey::AccessibilityValueNow->value
    ])
    || ($indeterminateProgress->properties()[
        PropKey::AccessibilityValueText->value
    ] ?? null) !== 'In progress'
    || !$progressFill instanceof \Pam\Native\Element
    || ($progressFill->properties()[PropKey::AnimateChanges->value] ?? true)
        !== false
) {
    throw new RuntimeException(
        'Indeterminate progress must announce a busy state and honor reduced motion.',
    );
}
$reducedSkeleton = MaterialStyleResolver::resolve(
    [
        '__materialComponent' => 'PSkeletonLoader',
        'reduceMotion' => true,
    ],
    Themes::light(),
);
if (
    !$reducedSkeleton instanceof Style
    ||
    $reducedSkeleton->animateChanges !== false
    || $reducedSkeleton->animationDurationMs !== null
) {
    throw new RuntimeException(
        'p-skeleton-loader must disable shimmer transitions for reduced motion.',
    );
}

/**
 * @param array<string, mixed> $props
 * @param array<string, Align|FlexDirection|PositionType|bool|float|int|null> $expected
 */
$assertGeometry = static function (
    string $component,
    array $props,
    array $expected,
) use ($themes): void {
    $style = MaterialStyleResolver::resolve(
        ['__materialComponent' => $component, ...$props],
        $themes[0]['theme'],
    );

    foreach ($expected as $property => $value) {
        $actual = $style->{$property};
        if ($actual !== $value) {
            throw new RuntimeException(
                sprintf(
                    '%s.%s expected %s, received %s.',
                    $component,
                    $property,
                    var_export($value, true),
                    var_export($actual, true),
                ),
            );
        }
    }
};

$assertGeometry('PBtn', [], [
    'minWidth' => 64.0,
    'minHeight' => 40.0,
    'height' => null,
    'paddingVertical' => 8.0,
]);
foreach (['PBtnGroup', 'PBtnToggle'] as $groupPart) {
    $assertGeometry($groupPart, [], ['alignItems' => \Pam\Native\Align::Center]);
    $assertGeometry($groupPart, ['block' => true], ['alignItems' => \Pam\Native\Align::Stretch]);
    $assertGeometry($groupPart, ['fullWidth' => true], ['alignItems' => \Pam\Native\Align::Stretch]);
}
$assertGeometry('PBtn', ['density' => 'comfortable'], [
    'minHeight' => 36.0,
]);
$assertGeometry('PBtn', ['density' => 'compact'], [
    'minHeight' => 32.0,
    'height' => null,
    'paddingVertical' => 4.0,
]);
foreach (['text', 'plain', 'outlined', 'tonal', 'flat', 'elevated'] as $buttonVariant) {
    $assertGeometry('PBtn', ['variant' => $buttonVariant, 'size' => 'x-small'], [
        'minHeight' => 32.0,
        'height' => null,
        'paddingVertical' => 4.0,
    ]);
}
$assertGeometry('PBtn', ['icon' => true, 'size' => 'x-small'], [
    'height' => 32.0,
    'paddingVertical' => 0.0,
]);
$assertGeometry('PAlert', ['density' => 'compact'], [
    'paddingVertical' => 12.0,
]);
$assertGeometry('PBtn', ['disabled' => true], [
    'opacity' => 1.0,
    'textColor' => 0x61000000 | (
        $themes[0]['theme']->color(ColorToken::OnSurface) & 0x00ffffff
    ),
    'backgroundColor' => 0x1F000000 | (
        $themes[0]['theme']->color(ColorToken::OnSurface) & 0x00ffffff
    ),
    'elevation' => 0.0,
]);
$assertGeometry('PBtn', ['block' => true], [
    'widthPercent' => 100.0,
    'minWidth' => null,
    'alignSelf' => \Pam\Native\Align::Stretch,
]);
$assertGeometry('PBtn', ['color' => 'success'], [
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::Success),
    'textColor' => $themes[0]['theme']->color(ColorToken::SuccessForeground),
]);
$assertGeometry('PBtn', ['color' => 'warning'], [
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::Warning),
    'textColor' => $themes[0]['theme']->color(ColorToken::WarningForeground),
]);
$assertGeometry('PBtn', ['variant' => 'outlined', 'color' => 'error'], [
    'backgroundColor' => 0,
    'borderColor' => $themes[0]['theme']->color(ColorToken::Destructive),
    'textColor' => $themes[0]['theme']->color(ColorToken::Destructive),
]);
$assertGeometry('PIconBtn', [], [
    'width' => 40.0,
    'height' => 40.0,
]);
$assertGeometry('PIconBtn', ['size' => 'x-small'], [
    'width' => 32.0,
    'height' => 32.0,
]);
$assertGeometry('PIconBtn', ['size' => 'small'], [
    'width' => 40.0,
    'height' => 40.0,
]);
$assertGeometry('PIconBtn', ['size' => 'large'], [
    'width' => 96.0,
    'height' => 96.0,
]);
$assertGeometry('PIconBtn', ['size' => 'x-large'], [
    'width' => 136.0,
    'height' => 136.0,
]);
$assertGeometry('PIconBtn', ['density' => 'comfortable'], [
    'width' => 36.0,
    'height' => 36.0,
]);
$assertGeometry('PIconBtn', ['density' => 'compact'], [
    'width' => 32.0,
    'height' => 32.0,
]);
$assertGeometry('PCard', [], [
    'padding' => 0.0,
    'borderRadius' => 24.0,
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::SurfaceElevated),
    'borderWidth' => 1.0,
    'elevation' => 1.0,
]);
$assertGeometry('PCard', ['variant' => 'filled'], [
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::SurfaceContainerHighest),
    'borderWidth' => 0.0,
    'elevation' => 0.0,
]);
$assertGeometry('PCard', ['variant' => 'outlined'], [
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::Surface),
    'borderColor' => $themes[0]['theme']->color(ColorToken::OutlineVariant),
    'borderWidth' => 1.0,
    'elevation' => 0.0,
]);
$assertGeometry('PCard', ['horizontal' => true], [
    'flexDirection' => \Pam\Native\FlexDirection::Row,
]);
$assertGeometry('PCard', ['disabled' => true], [
    'elevation' => 0.0,
    'opacity' => 0.38,
]);
$disabledCardPresses = 0;
$disabledCard = \Pam\MobileUi\Material\PCard::make(['disabled' => true])
    ->onPress(static function () use (&$disabledCardPresses): void {
        ++$disabledCardPresses;
    })
    ->toElement();
if (isset($disabledCard->events()[EventKind::Press->value])) {
    throw new RuntimeException('Disabled cards must not retain native press listeners.');
}
if ($disabledCardPresses !== 0) {
    throw new RuntimeException('Disabled-card regression test dispatched a callback.');
}
$assertGeometry('PChip', [], [
    'minHeight' => 32.0,
    'paddingHorizontal' => 16.0,
    'borderRadius' => 8.0,
]);
$assertGeometry('PChip', ['density' => 'comfortable'], [
    'minHeight' => 28.0,
    'paddingHorizontal' => 16.0,
]);
$assertGeometry('PChip', ['density' => 'compact'], [
    'minHeight' => 24.0,
    'paddingHorizontal' => 16.0,
]);
$assertGeometry('PList', [], [
    'paddingVertical' => 8.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PListItem', [], [
    'minHeight' => 56.0,
]);
$assertGeometry('PListItem', ['density' => 'comfortable'], [
    'minHeight' => 52.0,
]);
$assertGeometry('PListItem', ['density' => 'compact'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PListItem', ['lines' => 2], [
    'minHeight' => 72.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 10.0,
]);
$assertGeometry('PListItem', ['lines' => 3], [
    'minHeight' => 88.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 10.0,
]);
$assertGeometry('PTab', [], [
    'minWidth' => 90.0,
    'minHeight' => 48.0,
]);
$assertGeometry('PTab', ['density' => 'comfortable'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PTab', ['density' => 'compact'], [
    'minHeight' => 48.0,
]);
foreach (['PTextField', 'PPasswordField', 'PMaskedField', 'PCurrencyField', 'PColorInput', 'PDateInput'] as $scalableField) {
    $assertGeometry($scalableField, [], [
        'height' => null,
        'minHeight' => 56.0,
    ]);
}
$assertGeometry('PTextField', [], [
    'height' => null,
    'minHeight' => 56.0,
    'paddingTop' => 8.0,
    'paddingBottom' => 4.0,
    'borderWidth' => 1.0,
    'borderBottomWidth' => null,
    'borderRadius' => 16.0,
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::SurfaceContainerLow),
]);
$assertGeometry('PTextField', ['density' => 'comfortable'], [
    'height' => null,
    'minHeight' => 48.0,
]);
$assertGeometry('PTextField', ['density' => 'compact'], [
    'height' => null,
    'minHeight' => 40.0,
    'paddingTop' => 0.0,
    'paddingBottom' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'outlined'], [
    'borderWidth' => 1.0,
    'borderBottomWidth' => null,
    'borderRadius' => 16.0,
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::SurfaceElevated),
    'elevation' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'underlined'], [
    'paddingHorizontal' => 0.0,
    'borderWidth' => 0.0,
    'borderBottomWidth' => 1.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'solo'], [
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'elevation' => 1.0,
]);
$assertGeometry('PTextField', ['variant' => 'solo-inverted'], [
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'elevation' => 1.0,
]);
$assertGeometry('PTextField', ['variant' => 'solo-filled'], [
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'elevation' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'plain'], [
    'paddingHorizontal' => 0.0,
    'borderWidth' => 0.0,
    'borderBottomWidth' => null,
    'borderRadius' => 0.0,
]);
$findNativeInput = static function (\Pam\Native\Element $root): ?\Pam\Native\Element {
    $stack = [$root];
    while ($stack !== []) {
        $candidate = array_pop($stack);
        if ($candidate->kind() === NodeKind::Input) {
            return $candidate;
        }
        array_push($stack, ...$candidate->children());
    }
    return null;
};
$passwordToggled = null;
$passwordField = $tags['p-password-field']::make([
    'label' => 'Password',
    'modelValue' => 'secret',
])->onToggle(static function (bool $revealed) use (&$passwordToggled): void {
    $passwordToggled = $revealed;
})->toElement();
$passwordInput = $findNativeInput($passwordField);
$passwordToggle = null;
$passwordStack = [$passwordField];
while ($passwordStack !== []) {
    $candidate = array_pop($passwordStack);
    if (
        $candidate->kind() === NodeKind::Pressable
        && ($candidate->properties()[PropKey::AccessibilityLabel->value] ?? null)
            === 'Show password'
    ) {
        $passwordToggle = $candidate;
        break;
    }
    array_push($passwordStack, ...$candidate->children());
}
if (
    !$passwordInput instanceof \Pam\Native\Element
    || ($passwordInput->properties()[PropKey::Secure->value] ?? null) !== true
    || !$passwordToggle instanceof \Pam\Native\Element
    || ($passwordToggle->properties()[PropKey::Width->value] ?? null) !== 48.0
    || !isset($passwordToggle->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'p-password-field must expose a secure native input and an accessible 48dp reveal action.',
    );
}
$passwordToggle->events()[EventKind::Press->value]();
if ($passwordToggled !== true) {
    throw new RuntimeException('p-password-field reveal action must emit its next controlled state.');
}
require __DIR__.'/material-field-actions.php';
$disabledPassword = $tags['p-password-field']::make([
    'label' => 'Password',
    'modelValue' => 'secret',
    'disabled' => true,
])->onToggle(static function (): void {
    throw new RuntimeException('A disabled password reveal action must not emit.');
})->toElement();
$disabledPasswordToggle = null;
$passwordStack = [$disabledPassword];
while ($passwordStack !== []) {
    $candidate = array_pop($passwordStack);
    if (
        ($candidate->properties()[PropKey::AccessibilityLabel->value] ?? null)
            === 'Show password'
    ) {
        $disabledPasswordToggle = $candidate;
        break;
    }
    array_push($passwordStack, ...$candidate->children());
}
if (
    !$disabledPasswordToggle instanceof \Pam\Native\Element
    || ($disabledPasswordToggle->properties()[PropKey::Enabled->value] ?? null) !== false
    || isset($disabledPasswordToggle->events()[EventKind::Press->value])
) {
    throw new RuntimeException('A disabled password field must disable its reveal action.');
}
$maskedInput = $findNativeInput($tags['p-masked-field']::make([
    'label' => 'Phone',
    'pattern' => '(##) #####-####',
])->toElement());
$currencyInput = $findNativeInput($tags['p-currency-field']::make([
    'label' => 'Amount',
    'prefix' => 'R$ ',
    'decimalDigits' => 2,
])->toElement());
if (
    !$maskedInput instanceof \Pam\Native\Element
    || ($maskedInput->properties()[PropKey::InputFormat->value] ?? null) !== 2
    || ($maskedInput->properties()[PropKey::InputFormatPattern->value] ?? null)
        !== '(##) #####-####'
    || !$currencyInput instanceof \Pam\Native\Element
    || ($currencyInput->properties()[PropKey::InputFormat->value] ?? null) !== 3
    || ($currencyInput->properties()[PropKey::InputFormatDecimalDigits->value] ?? null) !== 2
) {
    throw new RuntimeException(
        'Masked and currency fields must delegate formatting to the typed PAM Native input contract.',
    );
}
$multiSelection = null;
$multiSelect = $tags['p-multi-select']::make([
    'label' => 'Teams',
    'items' => ['Design', 'Engineering', 'Product'],
    'modelValue' => ['Design'],
])->onChange(static function (array $values) use (&$multiSelection): void {
    $multiSelection = $values;
})->toElement();
$engineeringOption = null;
$multiStack = [$multiSelect];
while ($multiStack !== []) {
    $candidate = array_pop($multiStack);
    if (
        ($candidate->properties()[PropKey::AccessibilityLabel->value] ?? null)
            === 'Engineering'
        && isset($candidate->events()[EventKind::Press->value])
    ) {
        $engineeringOption = $candidate;
        break;
    }
    array_push($multiStack, ...$candidate->children());
}
if (!$engineeringOption instanceof \Pam\Native\Element) {
    throw new RuntimeException('p-multi-select must render selectable native options.');
}
$engineeringOption->events()[EventKind::Press->value]();
if ($multiSelection !== ['Design', 'Engineering']) {
    throw new RuntimeException('p-multi-select must preserve and extend controlled selection.');
}
$tagInput = $tags['p-tag-input']::make([
    'label' => 'Skills',
    'items' => ['PHP', 'Kotlin'],
    'modelValue' => ['PHP'],
])->toElement();
$tagAllowsCustom = false;
$tagStack = [$tagInput];
while ($tagStack !== []) {
    $candidate = array_pop($tagStack);
    $host = $candidate->properties()[PropKey::HostProperties->value] ?? null;
    if ($host instanceof BinaryValue) {
        $hostProps = Wire::decodeMap($host->bytes);
        $tagAllowsCustom = $tagAllowsCustom
            || ($hostProps['allowCustomValue'] ?? false) === true;
    }
    array_push($tagStack, ...$candidate->children());
}
if (!$tagAllowsCustom) {
    throw new RuntimeException('p-tag-input must expose native custom-value creation.');
}
$persistedCustomTag = $tags['p-tag-input']::make([
    'label' => 'Skills',
    'items' => ['PHP'],
    'modelValue' => ['PHP', 'Rust'],
])->toElement();
$rustOption = null;
$tagStack = [$persistedCustomTag];
while ($tagStack !== []) {
    $candidate = array_pop($tagStack);
    if (($candidate->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Rust') {
        $rustOption = $candidate;
        break;
    }
    array_push($tagStack, ...$candidate->children());
}
if (
    !$rustOption instanceof \Pam\Native\Element
    || ($rustOption->properties()[PropKey::Selected->value] ?? null) !== true
    || ($rustOption->properties()[PropKey::Checked->value] ?? null) !== true
) {
    throw new RuntimeException(
        'p-tag-input must preserve custom tags as selected removable options.',
    );
}
$initialCustomTag = $tags['p-tag-input']::make([
    'label' => 'Skills',
    'items' => ['PHP'],
    'modelValue' => ['PHP'],
])->toElement();
if (
    $modalMarker($initialCustomTag) === null
    || $modalMarker($initialCustomTag) !== $modalMarker($persistedCustomTag)
) {
    throw new RuntimeException('Creating a custom tag must preserve the open modal identity.');
}
$longFile = $tags['p-file-input']::make([
    'label' => 'Attachments', 'text' => str_repeat('long-file-name-', 20).'.pdf',
])->toElement();
$fileRow = $longFile->children()[0];
[$fileIcon, $fileCopy, $fileAction] = $fileRow->children();
if (
    ($fileCopy->properties()[PropKey::Width->value] ?? null) !== 0.0
    || ($fileCopy->properties()[PropKey::FlexGrow->value] ?? null) !== 1.0
    || ($fileCopy->properties()[PropKey::FlexShrink->value] ?? null) !== 1.0
    || ($fileIcon->properties()[PropKey::FlexShrink->value] ?? null) !== 0.0
    || ($fileAction->properties()[PropKey::FlexShrink->value] ?? null) !== 0.0
) {
    throw new RuntimeException('File labels must consume remaining width without squeezing icon or action.');
}
$longSearch = $tags['p-search-bar']::make(['modelValue' => str_repeat('long query ', 30)])->toElement();
$searchEditor = $longSearch->children()[1];
if (
    $searchEditor->kind() !== NodeKind::Input
    || ($searchEditor->properties()[PropKey::Width->value] ?? null) !== 0.0
    || ($searchEditor->properties()[PropKey::FlexGrow->value] ?? null) !== 1.0
    || ($searchEditor->properties()[PropKey::FlexShrink->value] ?? null) !== 1.0
) {
    throw new RuntimeException('Search editor must remain constrained to the available row width.');
}
$fileInput = $tags['p-file-input']::make(['label' => 'Attachments'])
    ->onPick(static function (mixed $files): void {})
    ->toElement();
if (
    $fileInput->kind() !== NodeKind::Pressable
    || ($fileInput->properties()[PropKey::MinHeight->value] ?? null) !== 72.0
    || ($fileInput->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Attachments'
    || !isset($fileInput->events()[EventKind::Press->value])
) {
    throw new RuntimeException(
        'p-file-input must be an accessible native picker trigger backed by the typed Files service.',
    );
}
$assertGeometry('PFileInput', [], [
    'widthPercent' => 100.0,
    'minHeight' => 72.0,
    'paddingHorizontal' => 16.0,
    'borderWidth' => 1.0,
    'borderRadius' => 16.0,
]);
$dateRange = $tags['p-date-range-picker']::make([
    'modelValue' => ['from' => '2026-09-01', 'to' => '2026-09-08'],
])->onChange(static function (array $range) use (&$dateRangeChanged): void {
    $dateRangeChanged = $range;
})->toElement();
$dateInputs = [];
$dateStack = [$dateRange];
while ($dateStack !== []) {
    $candidate = array_pop($dateStack);
    if ($candidate->kind() === NodeKind::CustomView) {
        $dateInputs[] = $candidate;
    }
    array_push($dateStack, ...$candidate->children());
}
if (
    $dateRange->kind() !== NodeKind::Row
    || count($dateRange->children()) !== 2
    || count($dateInputs) !== 2
    || !isset($dateInputs[0]->events()[EventKind::Change->value])
    || !isset($dateInputs[1]->events()[EventKind::Change->value])
) {
    throw new RuntimeException('p-date-range-picker must compose two typed native date controls.');
}
$blockedDateChanged = null;
$blockedDateRange = $tags['p-date-range-picker']::make([
    'disabledDates' => ['2026-09-10'],
    'modelValue' => ['from' => '2026-09-08', 'to' => '2026-09-14'],
])->onChange(static function (array $range) use (&$blockedDateChanged): void {
    $blockedDateChanged = $range;
})->toElement();
$blockedDateStack = [$blockedDateRange];
while ($blockedDateStack !== []) {
    $candidate = array_pop($blockedDateStack);
    $handler = $candidate->events()[EventKind::Change->value] ?? null;
    if ($candidate->kind() === NodeKind::CustomView && $handler instanceof Closure) {
        $handler('2026-09-10');
        foreach (['2026-02-30', '2025-02-29', '2026-13-01', 'not-a-date', '2026-9-1'] as $invalidDate) {
            $handler($invalidDate);
        }
        break;
    }
    array_push($blockedDateStack, ...$candidate->children());
}
if ($blockedDateChanged !== null) {
    throw new RuntimeException('p-date-range-picker must reject disabled dates.');
}
foreach ([
    ['minDate' => '2026-09-05', 'maxDate' => '2026-09-20'],
    ['minimumDate' => '2026-09-05', 'maximumDate' => '2026-09-20'],
] as $dateBounds) {
    $boundedChanges = [];
    $boundedRange = $tags['p-date-range-picker']::make([
        ...$dateBounds,
        'modelValue' => ['from' => '2026-09-08', 'to' => '2026-09-14'],
    ])->onChange(static function (array $next) use (&$boundedChanges): void {
        $boundedChanges[] = $next;
    })->toElement();
    foreach ($boundedRange->children() as $field) {
        $boundedHandler = $field->children()[1]->events()[EventKind::Change->value];
        $boundedHandler('2026-09-04');
        $boundedHandler('2026-09-21');
    }
    if ($boundedChanges !== []) {
        throw new RuntimeException('Date range endpoints must reject events outside either supported date-bound alias.');
    }
    $boundedRange->children()[0]->children()[1]->events()[EventKind::Change->value]('2026-09-05');
    $boundedRange->children()[1]->children()[1]->events()[EventKind::Change->value]('2026-09-20');
    $assertCustomSelection($boundedChanges, [
        ['from' => '2026-09-05', 'to' => '2026-09-14'],
        ['from' => '2026-09-08', 'to' => '2026-09-20'],
    ], 'Date range bounds must be inclusive and retain the controlled opposite endpoint.');
}
foreach (['p-date-range-picker', 'p-time-range-picker'] as $rangeTag) {
    foreach ([
        ['placeholder' => 'Escolha', 'expected' => ['Escolha', 'Escolha']],
        ['placeholder' => 'Escolha', 'fromPlaceholder' => 'Início', 'toPlaceholder' => 'Fim',
            'expected' => ['Início', 'Fim']],
    ] as $copyCase) {
        $localizedRange = $tags[$rangeTag]::make([
            ...$copyCase,
            'modelValue' => ['from' => '', 'to' => ''],
            'fromLabel' => 'Saída', 'toLabel' => 'Chegada',
        ])->toElement();
        foreach ($localizedRange->children() as $endpoint => $field) {
            $picker = $field->children()[1];
            if (($picker->children()[0]->properties()[PropKey::Text->value] ?? null)
                    !== $copyCase['expected'][$endpoint]
                || ($picker->properties()[PropKey::AccessibilityLabel->value] ?? null)
                    !== ['Saída', 'Chegada'][$endpoint]) {
                throw new RuntimeException('Range controls must honor shared/per-endpoint empty copy and preserve accessible labels.');
            }
        }
    }
    foreach ([false, true] as $isDisabled) {
        $adaptiveRange = $tags[$rangeTag]::make(['disabled' => $isDisabled])->toElement();
        foreach ($adaptiveRange->children() as $field) {
            $pickerStyle = $field->children()[1]->properties();
            if (isset($pickerStyle[PropKey::Height->value])
                || ($pickerStyle[PropKey::MinHeight->value] ?? null) !== 56.0
                || ($pickerStyle[PropKey::PaddingVertical->value] ?? null) !== 12.0) {
                throw new RuntimeException('Range fields must grow with scaled/wrapped text and preserve vertical breathing room.');
            }
        }
    }
    foreach (['disabled', 'isDisabled', 'readonly', 'readOnly', 'isReadOnly'] as $disabledProp) {
        $range = $tags[$rangeTag]::make([
            $disabledProp => true, 'fromLabel' => 'Departure', 'toLabel' => 'Arrival',
        ])->onChange(static function (array $value): void {})->toElement();
        foreach ($range->children() as $index => $field) {
            $picker = $field->children()[1];
            if (isset($picker->events()[EventKind::Change->value])
                || ($picker->properties()[PropKey::AccessibilityLabel->value] ?? null) !== ['Departure', 'Arrival'][$index]) {
                throw new RuntimeException('Range pickers must retain distinct accessible labels and suppress disabled/readonly changes.');
            }
        }
    }
}
$timeRangeChanged = null;
$openTimeRange = $tags['p-time-range-picker']::make([
    'modelValue' => ['from' => '09:00', 'to' => ''],
])->toElement();
$openTimeLabel = $openTimeRange->children()[1]->children()[1]->children()[0];
if (($openTimeLabel->properties()[PropKey::Text->value] ?? null) !== 'Select time') {
    throw new RuntimeException('An empty time range endpoint must display a visible selection hint.');
}
foreach ([
    ['p-date-range-picker', '2026-09-08', '2026-09-14', '2026-09-20', '2026-09-01'],
    ['p-time-range-picker', '09:00', '17:00', '18:00', '08:00'],
] as [$rangeTag, $from, $to, $later, $earlier]) {
    $emittedRanges = [];
    $range = $tags[$rangeTag]::make(['modelValue' => ['from' => $from, 'to' => $to]])
        ->onChange(static function (array $next) use (&$emittedRanges): void { $emittedRanges[] = $next; })
        ->toElement();
    $range->children()[0]->children()[1]->events()[EventKind::Change->value]($later);
    $range->children()[1]->children()[1]->events()[EventKind::Change->value]($earlier);
    if ($emittedRanges !== [['from' => $later, 'to' => ''], ['from' => $earlier, 'to' => '']]) {
        throw new RuntimeException('Existing interval normalization must never emit a reversed range.');
    }
}
$timeRange = $tags['p-time-range-picker']::make([
    'modelValue' => ['from' => '09:00', 'to' => '17:00'],
])->onChange(static function (array $range) use (&$timeRangeChanged): void {
    $timeRangeChanged = $range;
})->toElement();
$timeInput = null;
$timeStack = [$timeRange];
while ($timeStack !== []) {
    $candidate = array_pop($timeStack);
    if (
        $candidate->kind() === NodeKind::CustomView
        && isset($candidate->events()[EventKind::Change->value])
    ) {
        $timeInput = $candidate;
        break;
    }
    array_push($timeStack, ...$candidate->children());
}
if (
    count($timeRange->children()) !== 2
    || !$timeInput instanceof \Pam\Native\Element
) {
    throw new RuntimeException('p-time-range-picker must expose two independently interactive native time fields.');
}
$timeInput->events()[EventKind::Change->value]('10:30');
if (!is_array($timeRangeChanged) || !in_array('10:30', $timeRangeChanged, true)) {
    throw new RuntimeException('p-time-range-picker must emit the complete controlled interval.');
}
$filterChanged = null;
$filterBar = $tags['p-filter-bar']::make([
    'items' => ['Open', 'Paid', 'Overdue'],
    'modelValue' => ['Open'],
])->onChange(static function (array $filters) use (&$filterChanged): void {
    $filterChanged = $filters;
})->toElement();
if (count($filterBar->children()) !== 4) {
    throw new RuntimeException('p-filter-bar must render filters plus an explicit clear action.');
}
if (
    ($filterBar->children()[0]->properties()[PropKey::Checked->value] ?? null) !== true
    || ($filterBar->children()[1]->properties()[PropKey::Checked->value] ?? null) !== false
) {
    throw new RuntimeException('p-filter-bar must expose checked semantics for every filter.');
}
$filterBar->children()[1]->events()[EventKind::Press->value]();
if ($filterChanged !== ['Open', 'Paid']) {
    throw new RuntimeException('p-filter-bar must emit additive controlled filtering.');
}
$filterBar->children()[3]->events()[EventKind::Press->value]();
$assertEmptyFilters = static function (array $filters): void {
    if ($filters !== []) {
        throw new RuntimeException('p-filter-bar must expose a visible clear-all action.');
    }
};
$assertEmptyFilters($filterChanged);
$assertGeometry('PTimeRangePicker', [], [
    'widthPercent' => 100.0,
    'minHeight' => 72.0,
    'gap' => 12.0,
]);
$assertGeometry('PFilterBar', [], [
    'widthPercent' => 100.0,
    'minHeight' => 48.0,
    'gap' => 8.0,
    'flexWrap' => FlexWrap::Wrap,
]);
$reordered = null;
$reorderable = $tags['p-reorderable-list']::make([
    'items' => ['Research', 'Prototype', 'Build'],
])->onReorder(static function (array $items) use (&$reordered): void {
    $reordered = $items;
})->toElement();
if (
    count($reorderable->children()) !== 3
    || ($reorderable->children()[0]->children()[0]->properties()[PropKey::Draggable->value] ?? null) !== true
    || ($reorderable->children()[0]->children()[0]->properties()[PropKey::DropEnabled->value] ?? null) !== true
) {
    throw new RuntimeException('p-reorderable-list must use PAM Native drag and drop regions.');
}
$reorderable->children()[0]->children()[0]->events()[EventKind::Drop->value](Wire::map(['data' => 'Build']));
if ($reordered !== ['Build', 'Research', 'Prototype']) {
    throw new RuntimeException('p-reorderable-list must emit the reordered controlled collection.');
}
$swipeAction = null;
$dropCalls = 0;
$guardedList = $tags['p-reorderable-list']::make([
    'items' => [
        ['value' => 1, 'label' => 'First'],
        ['value' => 2, 'label' => 'Unavailable', 'disabled' => true],
        ['value' => 3, 'label' => 'Last'],
    ],
])->onReorder(static function (array $items) use (&$dropCalls): void { $dropCalls++; })->toElement();
$blockedRow = $guardedList->children()[1]->children()[0];
if (
    ($blockedRow->properties()[PropKey::Enabled->value] ?? null) !== false
    || ($blockedRow->properties()[PropKey::Draggable->value] ?? null) !== false
    || ($blockedRow->properties()[PropKey::DropEnabled->value] ?? null) !== false
    || isset($blockedRow->events()[EventKind::Drop->value])
) {
    throw new RuntimeException('Disabled reorder items must neither drag nor accept drops.');
}
$drop = $guardedList->children()[0]->children()[0]->events()[EventKind::Drop->value];
foreach (['unknown', '1', '2'] as $invalidDrop) {
    $drop(Wire::map(['data' => $invalidDrop]));
}
$assertDropCalls = static function (int $actual, int $expected): void {
    if ($actual !== $expected) {
        throw new RuntimeException('Only valid reorder operations may emit a change.');
    }
};
$assertDropCalls($dropCalls, 0);
$drop(Wire::map(['data' => '3']));
$assertDropCalls($dropCalls, 0);
foreach (['disabled', 'isDisabled'] as $itemLock) {
    $lockedMoves = 0;
    $list = $tags['p-reorderable-list']::make(['items' => [
        ['value' => 1, 'label' => 'First'],
        ['value' => 2, 'label' => 'Fixed', $itemLock => true],
        ['value' => 3, 'label' => 'Third'],
        ['value' => 4, 'label' => 'Fourth'],
    ]])->onReorder(static function (array $items) use (&$lockedMoves): void { $lockedMoves++; })->toElement();
    foreach ([[0, '4'], [3, '1']] as [$target, $sourceValue]) {
        $list->children()[$target]->children()[0]->events()[EventKind::Drop->value](Wire::map(['data' => $sourceValue]));
    }
    $assertDropCalls($lockedMoves, 0);
    if (($list->children()[1]->children()[0]->properties()[PropKey::Draggable->value] ?? true) !== false
        || ($list->children()[2]->children()[1]->properties()[PropKey::Enabled->value] ?? true) !== false) {
        throw new RuntimeException('Protected reorder aliases must block dragging and adjacent moves.');
    }
    $list->children()[2]->children()[0]->events()[EventKind::Drop->value](Wire::map(['data' => '4']));
    $assertDropCalls($lockedMoves, 1);
}
$assertOrder = static function (?array $actual, array $expected): void {
    if ($actual !== $expected) {
        throw new RuntimeException('Move controls must emit the controlled adjacent order.');
    }
};
$reorderable->children()[1]->children()[1]->events()[EventKind::Press->value]();
$assertOrder($reordered, ['Prototype', 'Research', 'Build']);
$reorderable->children()[1]->children()[2]->events()[EventKind::Press->value]();
$assertOrder($reordered, ['Research', 'Build', 'Prototype']);
$blockedMoveControls = [
    $reorderable->children()[0]->children()[1],
    $reorderable->children()[2]->children()[2],
    $guardedList->children()[0]->children()[2],
    $guardedList->children()[1]->children()[1],
    $guardedList->children()[1]->children()[2],
    $guardedList->children()[2]->children()[1],
];
foreach (['disabled', 'isDisabled'] as $disabledProp) {
    $disabledList = $tags['p-reorderable-list']::make([
        'items' => ['First', 'Last'], $disabledProp => true,
    ])->onReorder(static function (array $items): void {})->toElement();
    $blockedMoveControls[] = $disabledList->children()[0]->children()[2];
}
foreach ($blockedMoveControls as $control) {
    if (($control->properties()[PropKey::Enabled->value] ?? null) !== false
        || isset($control->events()[EventKind::Press->value])) {
        throw new RuntimeException('Move controls must respect boundaries and disabled rows.');
    }
}
$swipe = $tags['p-swipe-actions']::make([
    'title' => 'Design review',
    'startLabel' => 'Archive',
    'endLabel' => 'Delete',
])->onAction(static function (string $action) use (&$swipeAction): void {
    $swipeAction = $action;
})->toElement();
if (
    count($swipe->children()) !== 3
    || ($swipe->children()[1]->properties()[PropKey::GestureType->value] ?? null) !== 2
    || ($swipe->children()[1]->properties()[PropKey::GestureDirection->value] ?? null) !== 6
) {
    throw new RuntimeException('p-swipe-actions must use a horizontal PAM Native pan gesture.');
}
$swipe->children()[1]->events()[EventKind::GestureEnd->value](Wire::map([
    'type' => 2,
    'state' => 3,
    'translationX' => -96.0,
]));
if ($swipeAction !== 'Delete') {
    throw new RuntimeException('p-swipe-actions gesture must emit its semantic action.');
}
foreach (['Archive', 'Delete'] as $index => $expectedAction) {
    $control = $swipe->children()[2]->children()[$index];
    $control->events()[EventKind::Press->value]();
    if ($swipeAction !== $expectedAction) {
        throw new RuntimeException('Swipe action buttons must emit the same semantic action as gestures.');
    }
}
foreach (['disabled', 'isDisabled'] as $disabledProp) {
    $disabledControls = $tags['p-swipe-actions']::make([$disabledProp => true])
        ->onAction(static function (string $action): void {})
        ->toElement()->children()[2]->children();
    foreach ($disabledControls as $control) {
        if (($control->properties()[PropKey::Enabled->value] ?? null) !== false
            || isset($control->events()[EventKind::Press->value])) {
            throw new RuntimeException('Disabled swipe buttons must not expose actionable handlers.');
        }
    }
    $disabledSwipe = $tags['p-swipe-actions']::make([$disabledProp => true])
        ->onAction(static function (string $action): void {})
        ->toElement()->children()[1];
    foreach ([PropKey::GestureEnabled, PropKey::Enabled, PropKey::GestureNativeTransform] as $property) {
        if (($disabledSwipe->properties()[$property->value] ?? null) !== false) {
            throw new RuntimeException('Disabled swipe actions must block recognition and native movement.');
        }
    }
    if (isset($disabledSwipe->events()[EventKind::GestureEnd->value])) {
        throw new RuntimeException('Disabled swipe actions must not expose completion handlers.');
    }
}
$dataGrid = $tags['p-data-grid']::make([
    'headers' => [['title' => 'Name', 'key' => 'name']],
    'items' => [['name' => 'Aurora']],
])->toElement();
if ($dataGrid->kind() !== NodeKind::CustomView || count($dataGrid->children()) < 1) {
    throw new RuntimeException('p-data-grid must reuse the virtual native table contract.');
}
$treeSelectChanged = null;
foreach ([false, true, 'false', 'true'] as $keyboardAware) {
    $scaffold = $tags['p-app-scaffold']::make([
        'keyboardAware' => $keyboardAware,
        'behavior' => \Pam\Native\KeyboardAvoidingBehavior::Padding->value,
        'keyboardVerticalOffset' => 24,
        'keyboardAvoidingEnabled' => false,
    ], \Pam\Native\UI\Text::make('Keyboard content'))->toElement();
    $content = $scaffold->children()[0];
    $expectedAvoidance = $keyboardAware === true || $keyboardAware === 'true';
    if (($content->kind() === NodeKind::KeyboardAvoidingView) !== $expectedAvoidance) {
        throw new RuntimeException('Scaffold keyboardAware must opt into the native keyboard primitive.');
    }
    if ($expectedAvoidance && (
        ($content->properties()[PropKey::KeyboardBehavior->value] ?? null) !== \Pam\Native\KeyboardAvoidingBehavior::Padding->value
        || ($content->properties()[PropKey::KeyboardVerticalOffset->value] ?? null) !== 24.0
        || ($content->properties()[PropKey::KeyboardAvoidingEnabled->value] ?? null) !== false
    )) {
        throw new RuntimeException('Scaffold must forward native keyboard behavior, offset and enabled state.');
    }
}
foreach ([false, true] as $expandedTree) {
    $visualTree = $tags['p-tree-select']::make([
        'opened' => $expandedTree ? [1] : [], 'modelValue' => [2], 'multiple' => true,
        'items' => [['value' => 1, 'label' => 'Group', 'children' => [
            ['value' => 2, 'label' => 'Selected'], ['value' => 3, 'label' => 'Unselected'],
        ]]],
    ])->toElement()->children();
    $disclosure = $visualTree[0]->children()[0]->children()[0];
    if ($disclosure->kind() !== NodeKind::CustomView
        || ($disclosure->properties()[PropKey::Width->value] ?? null) !== 24.0
        || ($disclosure->properties()[PropKey::Height->value] ?? null) !== 24.0) {
        throw new RuntimeException('Tree disclosure must use a fixed vector, not a font glyph.');
    }
    if ($expandedTree) {
        $selectedIndicator = $visualTree[1]->children()[0]->children()[0];
        $mark = $selectedIndicator->children()[0];
        if ($mark->kind() !== NodeKind::CustomView
            || ($mark->properties()[PropKey::Width->value] ?? null) !== 16.0
            || $visualTree[2]->children()[0]->children()[0]->children() !== []) {
            throw new RuntimeException('Tree multiselect must show a vector check only when selected.');
        }
    }
}
foreach (['readonly', 'readOnly', 'isReadOnly'] as $treeLock) {
    $treeOpened = null;
    $treeRows = $tags['p-tree-select']::make([
        $treeLock => true, 'opened' => [1], 'modelValue' => 2,
        'items' => [['value' => 1, 'label' => 'Group', 'children' => [
            ['value' => 2, 'label' => 'Selected leaf'],
        ]]],
    ])->onChange(static function (mixed $value): void {
        throw new RuntimeException('Read-only tree must not change selection.');
    })->onToggle(static function (array $value) use (&$treeOpened): void {
        $treeOpened = $value;
    })->toElement()->children();
    if (count($treeRows) !== 2
        || isset($treeRows[1]->events()[EventKind::Press->value])
        || ($treeRows[1]->properties()[PropKey::Enabled->value] ?? true) !== false
        || ($treeRows[1]->properties()[PropKey::Checked->value] ?? false) !== true) {
        throw new RuntimeException('Read-only tree must preserve checked state without selectable leaves.');
    }
    $treeRows[0]->events()[EventKind::Press->value]();
    if ($treeOpened !== []) {
        throw new RuntimeException('Read-only tree must still allow browsing groups.');
    }
}
foreach (['disabled', 'isDisabled'] as $treeItemLock) {
    $treeRows = $tags['p-tree-select']::make([
        'opened' => [1, 2],
        'items' => [['value' => 1, $treeItemLock => true, 'children' => [
            ['value' => 2, 'children' => [['value' => 3, 'label' => 'Nested leaf']]],
        ]]],
    ])->onChange(static function (mixed $value): void {})
        ->onToggle(static function (array $value): void {})
        ->toElement()->children();
    if (count($treeRows) !== 3) {
        throw new RuntimeException('Disabled expanded tree must retain visible descendants.');
    }
    foreach ($treeRows as $row) {
        if (isset($row->events()[EventKind::Press->value])
            || ($row->properties()[PropKey::Enabled->value] ?? true) !== false) {
            throw new RuntimeException('Disabled tree branches must block all descendants.');
        }
    }
}
$compactTree = $tags['p-tree-select']::make([
    'density' => 'compact', 'items' => [['value' => 1, 'label' => 'Compact leaf']],
])->toElement();
if (($compactTree->children()[0]->properties()[PropKey::MinHeight->value] ?? null) !== 48.0) {
    throw new RuntimeException('Compact tree rows must retain the 48dp Android touch target.');
}
foreach ([false, true] as $treeDisabled) {
    $treeRows = $tags['p-tree-select']::make([
        'disabled' => $treeDisabled, 'opened' => [1], 'modelValue' => [2], 'multiple' => true,
        'items' => [
            ['value' => 1, 'label' => 'Group', 'children' => [
                ['value' => 2, 'label' => 'Selected leaf'],
                ['value' => 3, 'label' => 'Unavailable leaf', 'disabled' => true],
            ]],
        ],
    ])->onChange(static function (array $value): void {})
        ->onToggle(static function (array $value): void {})
        ->toElement()->children();
    if (count($treeRows) !== 3) {
        throw new RuntimeException('Opened tree group must expose both leaves.');
    }
    foreach ($treeRows as $index => $row) {
        $blocked = $treeDisabled || $index === 2;
        if (
            ($row->properties()[PropKey::Enabled->value] ?? null) !== !$blocked
            || isset($row->events()[EventKind::Press->value]) === $blocked
        ) {
            throw new RuntimeException('Tree item and group disabled states must block actions.');
        }
    }
    if (($treeRows[1]->properties()[PropKey::Checked->value] ?? null) !== true) {
        throw new RuntimeException('Selected tree leaf must publish checked native state.');
    }
    if (($treeRows[0]->properties()[PropKey::AccessibilityExpanded->value] ?? null) !== true) {
        throw new RuntimeException('Opened tree group must expose expanded accessibility state.');
    }
    $leafContent = $treeRows[1]->children()[0];
    if (
        ($leafContent->properties()[PropKey::PaddingLeft->value] ?? null) !== 36.0
        || ($leafContent->properties()[PropKey::PaddingVertical->value] ?? null) !== 8.0
        || ($leafContent->children()[1]->properties()[PropKey::Width->value] ?? null) !== 0.0
    ) {
        throw new RuntimeException('Tree labels must wrap inside padded, indented rows.');
    }
}
$treeSelect = $tags['p-tree-select']::make([
    'items' => [['title' => 'Android', 'value' => 'android']],
])->onChange(static function (string $value) use (&$treeSelectChanged): void {
    $treeSelectChanged = $value;
})->toElement();
$treeSelect->children()[0]->events()[EventKind::Press->value]();
if ($treeSelectChanged !== 'android') {
    throw new RuntimeException('p-tree-select must emit native tree selection.');
}
$resultPressed = false;
$result = $tags['p-result-state']::make([
    'status' => 'success',
    'title' => 'Ready',
    'actionLabel' => 'Continue',
])->onPress(static function () use (&$resultPressed): void {
    $resultPressed = true;
})->toElement();
$result->children()[3]->events()[EventKind::Press->value]();
if (!$resultPressed || count($result->children()) !== 4) {
    throw new RuntimeException('p-result-state must expose a directly actionable result layout.');
}
foreach (['disabled', 'isDisabled', 'loading', 'isLoading'] as $blockedProp) {
    $blockedResult = $tags['p-result-state']::make([
        'actionLabel' => 'Continue with the next stage of your application', $blockedProp => true,
    ])->onPress(static function (): void {})->toElement()->children()[3];
    if (($blockedResult->properties()[PropKey::Enabled->value] ?? null) !== false
        || isset($blockedResult->events()[EventKind::Press->value])
        || ($blockedResult->properties()[PropKey::MaxWidthPercent->value] ?? null) !== 100.0) {
        throw new RuntimeException('Result actions must fit their container and block disabled/loading interaction.');
    }
}
$selectedChartPoint = null;
foreach (\Pam\MobileUi\Enum\ResultStatus::cases() as $status) {
    $state = $tags['p-result-state']::make(['status' => $status->value])->toElement();
    $title = $state->children()[1]->properties()[PropKey::Text->value] ?? null;
    $description = $state->children()[2]->properties()[PropKey::Text->value] ?? null;
    if ($status !== \Pam\MobileUi\Enum\ResultStatus::Success
        && ($title === 'All done' || $description === 'Your request was completed successfully.')) {
        throw new RuntimeException('Non-success results must never default to success copy.');
    }
    $legacy = $tags['p-result-state']::make(['status' => strtolower($status->name)])->toElement();
    if ($legacy->children()[1]->properties() !== $state->children()[1]->properties()) {
        throw new RuntimeException('Legacy result names must resolve to the integer enum.');
    }
    $custom = $tags['p-result-state']::make(['status' => $status, 'title' => 'Custom title',
        'description' => 'Custom description'])->toElement();
    if (($custom->children()[1]->properties()[PropKey::Text->value] ?? null) !== 'Custom title'
        || ($custom->children()[2]->properties()[PropKey::Text->value] ?? null) !== 'Custom description') {
        throw new RuntimeException('Result status must preserve application copy overrides.');
    }
}
$emptyState = $tags['p-result-state']::make(['status' => \Pam\MobileUi\Enum\ResultStatus::Empty->value])->toElement();
foreach ([0, 5, true, 'unknown', 1.5] as $invalidResultStatus) {
    try {
        \Pam\MobileUi\Enum\ResultStatus::resolve($invalidResultStatus);
        throw new RuntimeException('Invalid result status must not silently display success.');
    } catch (InvalidArgumentException) {
        // Invalid configuration fails at the public boundary, before rendering.
    }
}
if (($emptyState->children()[0]->properties()[PropKey::BackgroundColor->value] ?? null)
    !== ThemeManager::current()->color(ColorToken::Muted)) {
    throw new RuntimeException('An empty result must use a neutral surface, not a success signal.');
}
$loadingResult = $tags['p-result-state']::make([
    'loading' => true, 'actionLabel' => 'Preparing',
])->toElement();
if ($loadingResult->children()[0]->children()[0]->kind() !== NodeKind::ActivityIndicator
    || ($loadingResult->children()[1]->properties()[PropKey::Text->value] ?? null) !== 'In progress'
    || ($loadingResult->children()[3]->properties()[PropKey::AccessibilityBusy->value] ?? false) !== true) {
    throw new RuntimeException('Loading results must display native progress and announce a busy action, not success.');
}
foreach ([1, 2] as $copyIndex) {
    if (($loadingResult->children()[$copyIndex]->properties()[PropKey::MaxWidthPercent->value] ?? null) !== 100.0) {
        throw new RuntimeException('Result copy must wrap within its available width.');
    }
}
$chart = $tags['p-chart']::make([
    'values' => '12,18,14,26',
    'accessibilityLabel' => 'Revenue trend',
])->onChange(static function (array $point) use (&$selectedChartPoint): void {
    $selectedChartPoint = $point;
})->toElement();
$chartHostValue = $chart->properties()[PropKey::HostProperties->value] ?? null;
if (!$chartHostValue instanceof BinaryValue) {
    throw new RuntimeException('p-chart must expose encoded native host properties.');
}
$chartHost = Wire::decodeMap($chartHostValue->bytes);
foreach (['p-chart', 'p-sparkline'] as $seriesTag) {
    $series = $tags[$seriesTag]::make([
        'type' => 'bar',
        'modelValue' => [-20, 30],
        'barMaxWidth' => 24.0,
        'showBaseline' => false,
    ])->toElement();
    $seriesPayload = $series->properties()[PropKey::HostProperties->value] ?? null;
    if (!$seriesPayload instanceof BinaryValue) {
        throw new RuntimeException('Bar chart must expose native configuration.');
    }
    $seriesProps = Wire::decodeMap($seriesPayload->bytes);
    if (($seriesProps['barMaxWidth'] ?? null) !== 24.0
        || ($seriesProps['showBaseline'] ?? null) !== false) {
        throw new RuntimeException('Chart and Sparkline must preserve bar geometry overrides.');
    }
}
if (
    $chart->kind() !== NodeKind::CustomView
    || ($chart->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Revenue trend'
    || ($chartHost['interactive'] ?? null) !== true
) {
    throw new RuntimeException('p-chart must render as an interactive native chart with an accessible summary.');
}
$chart->events()[EventKind::Change->value]('{"index":2,"value":14}');
foreach (['not-json', '{}', '{"index":-1,"value":14}', '{"index":1.5,"value":14}',
    '{"index":1,"value":null}', '{"index":1,"value":"NaN"}', '{"index":1,"value":1e999}'] as $invalidPoint) {
    $chart->events()[EventKind::Change->value]($invalidPoint);
}
if ($selectedChartPoint !== ['index' => 2, 'value' => 14.0]) {
    throw new RuntimeException('p-chart must emit its selected native data point.');
}
$drawerSelection = null;
foreach (['p-navigation-bar', 'p-navigation-rail'] as $navigationTag) {
    foreach ([false, true] as $expanded) {
        $navigationValue = null;
        $navigation = $tags[$navigationTag]::make([
            'expanded' => $expanded, 'modelValue' => 1,
            'items' => [
                ['value' => 1, 'label' => 'Home', 'icon' => 'StarIcon'],
                ['value' => 2, 'label' => 'Browse all destinations', 'icon' => 'SearchIcon'],
                ['value' => 3, 'label' => 'Unavailable', 'disabled' => true],
            ],
        ])->onChange(static function (int $value) use (&$navigationValue): void {
            $navigationValue = $value;
        })->toElement();
        $destinations = $navigation->children();
        if (count($destinations) !== 3
            || ($destinations[0]->properties()[PropKey::Selected->value] ?? false) !== true
            || ($destinations[2]->properties()[PropKey::Enabled->value] ?? true) !== false
            || isset($destinations[0]->events()[EventKind::Press->value])
            || isset($destinations[2]->events()[EventKind::Press->value])
            || ($destinations[1]->properties()[PropKey::AccessibilityRole->value] ?? null) !== \Pam\Native\AccessibilityRole::Tab->value) {
            throw new RuntimeException('Navigation items must compose selected, actionable and disabled semantic tabs.');
        }
        $destinations[1]->events()[EventKind::Press->value]();
        if ($navigationTag === 'p-navigation-bar') {
            $labelProperties = $destinations[1]->children()[0]->children()[1]->properties();
            if (($labelProperties[PropKey::NumberOfLines->value] ?? null) !== 0
                || ($destinations[1]->properties()[PropKey::MinWidth->value] ?? null) !== 64.0
                || isset($destinations[1]->properties()[PropKey::Width->value])
                || isset($destinations[1]->children()[0]->properties()[PropKey::WidthPercent->value])
                || ($destinations[1]->children()[0]->properties()[PropKey::JustifyContent->value] ?? null) !== Justify::Start->value
                || ($destinations[1]->properties()[PropKey::AccessibilityLabel->value] ?? null) !== 'Browse all destinations') {
                throw new RuntimeException('Navigation bar labels must wrap with top-aligned content and the full accessible name.');
            }
        }
        if ($navigationValue !== 2) {
            throw new RuntimeException('Navigation must emit the destination value, not its label.');
        }
        $custom = $tags[$navigationTag]::make(['items' => ['Ignored']], Text::make('Custom destination'))->toElement();
        if (count($custom->children()) !== 1 || $custom->children()[0]->kind() !== NodeKind::Text) {
            throw new RuntimeException('Navigation must preserve explicit custom children.');
        }
    }
}
$drawerRoute = new \App\ComponentRoute('p-navigation-drawer', 'Drawer', $tags['p-navigation-drawer']);
$drawerSamples = $catalogMethod->invoke($drawerRoute);
if (!is_array($drawerSamples)) {
    throw new RuntimeException('Drawer catalog must be an array of specimens.');
}
$adaptiveSample = null;
foreach ($drawerSamples as $sample) {
    if (is_array($sample) && ($sample['label'] ?? null) === 'Adaptive permanent') {
        $adaptiveSample = $sample['props'] ?? null;
    }
}
if (!is_array($adaptiveSample) || ($adaptiveSample['type'] ?? null) !== 'front'
    || ($adaptiveSample['permanentBreakpoint'] ?? null) !== 840.0) {
    throw new RuntimeException('Permanent drawer showcase must adapt instead of squeezing a fixed sidebar onto phones.');
}
$navigationDrawer = $tags['p-navigation-drawer']::make([
    'open' => true,
    'type' => 'front',
    'items' => ['Home', 'Explore', 'Settings'],
    'modelValue' => 'Home',
    'overlayColor' => 0x3D4F46E5,
])->onChange(static function (string $value) use (&$drawerSelection): void {
    $drawerSelection = $value;
})->toElement();
$drawerExplore = null;
$drawerStack = [$navigationDrawer];
while ($drawerStack !== []) {
    $candidate = array_pop($drawerStack);
    if (
        ($candidate->properties()[PropKey::AccessibilityLabel->value] ?? null)
            === 'Explore'
        && isset($candidate->events()[EventKind::Press->value])
    ) {
        $drawerExplore = $candidate;
        break;
    }
    array_push($drawerStack, ...$candidate->children());
}
if (
    $navigationDrawer->kind() !== NodeKind::DrawerLayout
    || ($navigationDrawer->properties()[PropKey::DrawerOpen->value] ?? null) !== true
    || ($navigationDrawer->properties()[PropKey::DrawerWidth->value] ?? null) !== 304.0
    || ($navigationDrawer->properties()[PropKey::DrawerOverlayColor->value] ?? null)
        !== 0x3D4F46E5
    || count($navigationDrawer->children()) !== 2
    || !$drawerExplore instanceof \Pam\Native\Element
) {
    throw new RuntimeException(
        'p-navigation-drawer must compose the cross-platform PAM Native drawer host.',
    );
}
$drawerExplore->events()[EventKind::Press->value]();
if ($drawerSelection !== 'Explore') {
    throw new RuntimeException('p-navigation-drawer destinations must emit selection.');
}
foreach (['disabled', 'isDisabled'] as $disabledAlias) {
    foreach ([false, true] as $wholeDrawerDisabled) {
        $guardedDrawer = $tags['p-navigation-drawer']::make([
            $disabledAlias => $wholeDrawerDisabled,
            'items' => [
                ['value' => 1, 'label' => 'Available destination'],
                ['value' => 2, 'label' => 'Unavailable destination', $disabledAlias => true],
            ],
        ])->onChange(static function (int $value): void {})->toElement();
        $stack = [$guardedDrawer];
        $destinations = 0;
        $hasScroll = false;
        while ($stack !== []) {
            $candidate = array_pop($stack);
            $hasScroll = $hasScroll || $candidate->kind() === NodeKind::Scroll;
            $label = $candidate->properties()[PropKey::AccessibilityLabel->value] ?? null;
            if (in_array($label, ['Available destination', 'Unavailable destination'], true)) {
                $destinations++;
                $enabled = !$wholeDrawerDisabled && $label === 'Available destination';
                if (($candidate->properties()[PropKey::Enabled->value] ?? null) !== $enabled
                    || isset($candidate->events()[EventKind::Press->value]) !== $enabled
                    || ($candidate->properties()[PropKey::PaddingVertical->value] ?? null) !== 12.0) {
                    throw new RuntimeException('Drawer destinations must respect item/global disabled aliases and retain padding.');
                }
            }
            array_push($stack, ...$candidate->children());
        }
        if (!$hasScroll || $destinations !== 2) {
            throw new RuntimeException('Drawer destinations must live in a scrollable surface.');
        }
    }
}
$customDrawer = $tags['p-navigation-drawer']::make([
    'modelValue' => '1',
    'items' => [['value' => 1, 'label' => 'Custom destination', 'icon' => 'StarIcon']],
])->onChange(static function (int $value): void {})->toElement();
$stack = [$customDrawer];
$customDestinationFound = false;
while ($stack !== []) {
    $candidate = array_pop($stack);
    if (($candidate->properties()[PropKey::AccessibilityLabel->value] ?? null) === 'Custom destination') {
        $customDestinationFound = true;
        $iconHost = $candidate->children()[0]->children()[0]->properties()[PropKey::HostProperties->value] ?? null;
        $expectedIconHost = $iconClass::make(['name' => 'StarIcon'])->toElement()->properties()[PropKey::HostProperties->value] ?? null;
        if (!$iconHost instanceof BinaryValue || !$expectedIconHost instanceof BinaryValue
            || Wire::decodeMap($iconHost->bytes)['icon'] !== Wire::decodeMap($expectedIconHost->bytes)['icon']
            || ($candidate->properties()[PropKey::Selected->value] ?? null) !== true
            || isset($candidate->events()[EventKind::Press->value])) {
            throw new RuntimeException('Drawer must honor custom icons and share navigation scalar selection semantics.');
        }
    }
    array_push($stack, ...$candidate->children());
}
if (!$customDestinationFound) {
    throw new RuntimeException('Custom drawer destination was not rendered.');
}
$commandPalette = $tags['p-command-palette']::make([
    'label' => 'Commands',
    'items' => ['New project', 'Open file', 'Publish'],
])->toElement();
$commandHost = null;
$commandStack = [$commandPalette];
while ($commandStack !== []) {
    $candidate = array_pop($commandStack);
    $host = $candidate->properties()[PropKey::HostProperties->value] ?? null;
    if ($host instanceof BinaryValue) {
        $decoded = Wire::decodeMap($host->bytes);
        if (($decoded['searchable'] ?? false) === true) {
            $commandHost = $decoded;
            break;
        }
    }
    array_push($commandStack, ...$candidate->children());
}
if (
    !is_array($commandHost)
    || ($commandHost['searchPlaceholder'] ?? null) !== 'Type a command'
) {
    throw new RuntimeException(
        'p-command-palette must expose an open, searchable native command surface.',
    );
}
$progressButton = $tags['p-progress-button']::make([
    'text' => 'Publishing',
    'progress' => 42,
])->toElement();
foreach (['p-result-state', 'p-progress-button'] as $loadingTag) {
    $aliasLoading = $tags[$loadingTag]::make(['isLoading' => true, 'text' => 'Preparing'])->toElement();
    if (($aliasLoading->properties()[PropKey::Enabled->value] ?? null) !== false
        || ($aliasLoading->properties()[PropKey::AccessibilityBusy->value] ?? null) !== true) {
        throw new RuntimeException('Feedback components must honor the isLoading alias.');
    }
    $explicitIdle = $tags[$loadingTag]::make(['isLoading' => true, 'loading' => false])->toElement();
    if (($explicitIdle->properties()[PropKey::AccessibilityBusy->value] ?? false) === true) {
        throw new RuntimeException('Explicit loading=false must take precedence over isLoading.');
    }
}
$progressTrack = null;
$progressStack = [$progressButton];
while ($progressStack !== []) {
    $candidate = array_pop($progressStack);
    if (($candidate->properties()[PropKey::Value->value] ?? null)
        === 'pam:progress-button-track') {
        $progressTrack = $candidate;
        break;
    }
    array_push($progressStack, ...$candidate->children());
}
if (
    !$progressTrack instanceof \Pam\Native\Element
    || ($progressTrack->properties()[PropKey::WidthPercent->value] ?? null) !== 42.0
    || ($progressButton->properties()[PropKey::AccessibilityBusy->value] ?? null) !== true
) {
    throw new RuntimeException(
        'p-progress-button must retain its action label and expose determinate native progress.',
    );
}
foreach ([-20, 0, 42, 100, 120] as $progressInput) {
    $expectedProgress = max(0.0, min(100.0, (float) $progressInput));
    $progressProperties = $tags['p-progress-button']::make([
        'progress' => $progressInput,
        'text' => 'Upload',
    ])->toElement()->properties();
    if (
        ($progressProperties[PropKey::AccessibilityBusy->value] ?? null)
            !== ($expectedProgress > 0.0 && $expectedProgress < 100.0)
        || ($progressProperties[PropKey::AccessibilityValueMin->value] ?? null) !== 0.0
        || ($progressProperties[PropKey::AccessibilityValueMax->value] ?? null) !== 100.0
        || ($progressProperties[PropKey::AccessibilityValueNow->value] ?? null) !== $expectedProgress
    ) {
        throw new RuntimeException('Progress button must expose a bounded percentage and accurate busy state.');
    }
}
foreach ([MaterialVariant::Text, MaterialVariant::Outlined, MaterialVariant::Tonal, MaterialVariant::Elevated] as $progressVariant) {
    $variantButton = $tags['p-progress-button']::make([
        'progress' => 42, 'text' => 'Upload', 'variant' => $progressVariant->value,
    ])->toElement();
    $variantStack = [$variantButton];
    $variantTrack = null;
    while ($variantStack !== []) {
        $candidate = array_pop($variantStack);
        if (($candidate->properties()[PropKey::Value->value] ?? null) === 'pam:progress-button-track') {
            $variantTrack = $candidate;
            break;
        }
        array_push($variantStack, ...$candidate->children());
    }
    $expectedStyle = MaterialStyleResolver::resolve([
        '__materialComponent' => 'PBtn', 'variant' => $progressVariant->value,
    ], ThemeManager::current());
    if (!$variantTrack instanceof \Pam\Native\Element
        || ($variantTrack->properties()[PropKey::BackgroundColor->value] ?? null) !== $expectedStyle?->textColor
        || ($variantTrack->properties()[PropKey::WidthPercent->value] ?? null) !== 42.0
    ) {
        throw new RuntimeException('Progress track must preserve geometry and follow the variant foreground.');
    }
}
$assertGeometry('PDialog', [], [
    'margin' => 24.0,
    'borderRadius' => 28.0,
    'elevation' => 6.0,
]);
$assertGeometry('PDialog', ['width' => 320], [
    'width' => 320.0,
    'widthPercent' => null,
    'maxWidth' => 560.0,
]);
$assertGeometry('PProgressCircular', [], [
    'width' => 40.0,
    'height' => 40.0,
    'animationDurationMs' => 1400,
]);
$assertGeometry('PProgressCircular', ['size' => 'x-small'], [
    'width' => 16.0,
    'height' => 16.0,
]);
$assertGeometry('PProgressCircular', ['size' => 'small'], [
    'width' => 24.0,
    'height' => 24.0,
]);
$assertGeometry('PProgressCircular', ['size' => 'large'], [
    'width' => 48.0,
    'height' => 48.0,
]);
$assertGeometry('PProgressCircular', ['size' => 'x-large'], [
    'width' => 64.0,
    'height' => 64.0,
]);
$assertGeometry('PProgressLinear', [], [
    'height' => 4.0,
    'animationDurationMs' => 2200,
]);
$assertGeometry('PSkeletonLoader', [], [
    'borderRadius' => 4.0,
    'animationDurationMs' => 1500,
]);
$assertGeometry('PAppBar', [], [
    'minHeight' => 64.0,
    'paddingHorizontal' => 4.0,
]);
$assertGeometry('PAppBar', ['density' => 'comfortable'], [
    'minHeight' => 56.0,
]);
$assertGeometry('PAppBar', ['density' => 'compact'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PToolbar', [], [
    'minHeight' => 64.0,
]);
$assertGeometry('PToolbar', ['density' => 'comfortable'], [
    'minHeight' => 56.0,
]);
$assertGeometry('PToolbar', ['density' => 'compact'], [
    'minHeight' => 48.0,
]);
$assertGeometry('PAlert', [], [
    'minHeight' => 80.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 12.0,
    'gap' => 12.0,
    'borderRadius' => 12.0,
    'elevation' => 1.0,
    'alignItems' => Align::Start,
]);
$assertGeometry('PAlert', ['border' => 'start'], [
    'paddingLeft' => 20.0,
    'borderLeftWidth' => 4.0,
]);
$assertGeometry('PAlert', ['border' => 'start', 'rtl' => true], [
    'paddingRight' => 20.0,
    'borderRightWidth' => 4.0,
]);
$assertGeometry('PAlert', ['border' => 'top'], [
    'paddingTop' => 16.0,
    'borderTopWidth' => 4.0,
]);
$assertGeometry('PBanner', [], [
    'paddingLeft' => 16.0,
    'paddingRight' => 8.0,
    'paddingTop' => 16.0,
    'paddingBottom' => 8.0,
    'gap' => 0.0,
    'borderRadius' => 0.0,
    'borderBottomWidth' => 0.0,
    'elevation' => 1.0,
    'animationDurationMs' => 250,
    'animateChanges' => true,
]);
$assertGeometry('PBanner', ['density' => 'comfortable'], [
    'paddingTop' => 12.0,
    'paddingBottom' => 8.0,
]);
$assertGeometry('PBanner', ['density' => 'compact'], [
    'paddingTop' => 8.0,
    'paddingBottom' => 4.0,
]);
$assertGeometry('PBanner', ['flat' => true, 'elevation' => 4], [
    'elevation' => 0.0,
]);
$assertGeometry('PBanner', ['reduceMotion' => true], [
    'animationDurationMs' => null,
    'animateChanges' => false,
]);
$assertGeometry('PBannerActions', [], [
    'widthPercent' => 100.0,
    'minHeight' => 48.0,
    'gap' => 8.0,
    'marginTop' => 4.0,
    'alignItems' => Align::Center,
    'justifyContent' => \Pam\Native\Justify::End,
]);
$assertGeometry('PBadge', [], [
    'minWidth' => 16.0,
    'minHeight' => 16.0,
    'borderRadius' => 9999.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBadge', ['bordered' => true], [
    'borderWidth' => 2.0,
]);
$assertGeometry('PBottomSheet', [], [
    'widthPercent' => 100.0,
    'paddingTop' => 12.0,
    'borderRadius' => 0.0,
    'borderTopLeftRadius' => 28.0,
    'borderTopRightRadius' => 28.0,
    'borderBottomRightRadius' => 0.0,
    'borderBottomLeftRadius' => 0.0,
    'elevation' => 6.0,
]);
$assertGeometry('PCardActions', [], [
    'widthPercent' => 100.0,
    'minHeight' => 56.0,
    'padding' => 8.0,
    'gap' => 8.0,
    'flexWrap' => \Pam\Native\FlexWrap::Wrap,
    'alignItems' => Align::Center,
    'justifyContent' => \Pam\Native\Justify::End,
]);
$assertGeometry('PExpansionPanelTitle', [], [
    'minHeight' => 56.0,
    'paddingHorizontal' => 16.0,
]);
$assertGeometry('PExpansionPanel', ['active' => true], [
    'marginVertical' => 0.0,
]);
$assertGeometry('PExpansionPanel', ['disabled' => true], [
    'opacity' => 0.26,
]);
$assertGeometry('PExpansionPanelTitle', ['active' => true], [
    'minHeight' => 64.0,
]);
$assertGeometry('PExpansionPanelText', [], [
    'paddingHorizontal' => 16.0,
    'paddingTop' => 12.0,
    'paddingBottom' => 20.0,
]);
$assertGeometry('PSnackbar', [], [
    'minHeight' => 48.0,
    'maxWidth' => 672.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 12.0,
    'elevation' => 6.0,
    'zIndex' => 1000,
    'positionType' => PositionType::Absolute,
    'left' => 8.0,
    'right' => 8.0,
    'bottom' => 8.0,
    'safeAreaBottom' => true,
]);
$assertGeometry('PSnackbar', ['location' => 'top end'], [
    'positionType' => PositionType::Absolute,
    'top' => 8.0,
    'right' => 8.0,
]);
$assertGeometry('PSnackbar', ['vertical' => true], [
    'minHeight' => 68.0,
    'flexDirection' => FlexDirection::Column,
    'alignItems' => Align::Start,
]);
$assertGeometry('PStepper', [], [
    'borderRadius' => 4.0,
    'elevation' => 1.0,
]);
$assertGeometry('PStepperHeader', [], [
    'elevation' => 1.0,
]);
$assertGeometry('PStepperItem', [], [
    'padding' => 24.0,
    'opacity' => 0.6,
]);
$assertGeometry('PStepperItem', ['active' => true], [
    'opacity' => 1.0,
]);
$assertGeometry('PStepperWindow', [], [
    'margin' => 24.0,
]);
$assertGeometry('PStepperWindowItem', [], [
    'paddingTop' => 24.0,
]);
$assertGeometry('PStepperActions', [], [
    'padding' => 16.0,
]);
$assertGeometry('PStepperVerticalActions', [], [
    'paddingHorizontal' => 24.0,
    'paddingBottom' => 16.0,
]);
$assertGeometry('PTooltip', [], [
    'widthPercent' => 100.0,
    'backgroundColor' => 0x00000000,
    'elevation' => 0.0,
]);
$assertGeometry('POverlay', [], [
    'widthPercent' => 100.0,
    'heightPercent' => 100.0,
    'backgroundColor' => 0x00000000,
    'opacity' => 1.0,
]);
$assertGeometry('PMenu', [], [
    'minWidth' => 112.0,
    'maxWidth' => 280.0,
    'borderRadius' => 4.0,
    'elevation' => 3.0,
]);
$assertGeometry('PAvatar', ['size' => 'x-small'], [
    'width' => 24.0,
    'height' => 24.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PAvatar', [], [
    'width' => 40.0,
    'height' => 40.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PAvatar', ['size' => 'x-large'], [
    'width' => 56.0,
    'height' => 56.0,
]);
$assertGeometry('PIcon', ['size' => 'x-small'], [
    'width' => 16.0,
    'height' => 16.0,
]);
$assertGeometry('PIcon', ['size' => 'x-large'], [
    'width' => 32.0,
    'height' => 32.0,
]);
$assertGeometry('PFab', ['size' => 'x-small'], [
    'width' => 40.0,
    'height' => 40.0,
    'borderRadius' => 16.0,
    'elevation' => 3.0,
]);
$assertGeometry('PFab', [], [
    'width' => 56.0,
    'height' => 56.0,
    'borderRadius' => 16.0,
]);
$assertGeometry('PFab', ['extended' => true], [
    'width' => null,
    'height' => null,
    'minHeight' => 56.0,
    'paddingVertical' => 8.0,
    'minWidth' => 64.0,
    'paddingHorizontal' => 16.0,
    'borderRadius' => 16.0,
]);
$assertGeometry('PFab', ['size' => 'large'], [
    'width' => 96.0,
    'height' => 96.0,
    'borderRadius' => 28.0,
]);
$assertGeometry('PFab', ['size' => 'x-large'], [
    'width' => 96.0,
    'height' => 96.0,
]);
$assertGeometry('PBadge', [], [
    'height' => 16.0,
    'minWidth' => 16.0,
    'borderRadius' => 9999.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBadge', ['dot' => true], [
    'width' => 6.0,
    'height' => 6.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PCarousel', [], [
    'height' => 240.0,
    'minHeight' => 240.0,
    'borderRadius' => 24.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PCarousel', ['height' => 320], [
    'height' => 320.0,
    'minHeight' => 320.0,
]);
$assertGeometry('PCarouselItem', [], [
    'widthPercent' => 100.0,
    'heightPercent' => 100.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PRating', [], [
    'height' => 48.0,
    'minHeight' => 48.0,
    'animationDurationMs' => 150,
]);
$assertGeometry('PTimeline', [], [
    'widthPercent' => 100.0,
    'gap' => 0.0,
]);
$assertGeometry('PTimelineItem', [], [
    'minHeight' => 72.0,
    'paddingHorizontal' => 0.0,
    'paddingVertical' => 8.0,
    'gap' => 12.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PSpeedDial', ['direction' => 'top'], [
    'minWidth' => 56.0,
    'minHeight' => 56.0,
    'flexDirection' => FlexDirection::Column,
]);
$assertGeometry('PSpeedDial', ['direction' => 'end'], [
    'minWidth' => 56.0,
    'minHeight' => 56.0,
    'flexDirection' => FlexDirection::Row,
]);
$assertGeometry('PDatePicker', [], [
    'width' => 328.0,
    'minWidth' => 328.0,
    'borderRadius' => 4.0,
]);
$assertGeometry('PTimePicker', [], [
    'width' => 328.0,
    'height' => 56.0,
    'minHeight' => 56.0,
    'paddingHorizontal' => 16.0,
    'borderWidth' => 1.0,
    'borderRadius' => 4.0,
    'flexDirection' => FlexDirection::Row,
    'alignItems' => Align::Center,
]);
$assertGeometry('PSlider', [], [
    'widthPercent' => 100.0,
    'minHeight' => 48.0,
    'paddingHorizontal' => 8.0,
    'borderRadius' => 6.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PRangeSlider', ['thumbLabel' => 'always'], [
    'widthPercent' => 100.0,
    'minHeight' => 80.0,
    'paddingHorizontal' => 8.0,
    'borderRadius' => 6.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PRangeSlider', ['vertical' => true], [
    'width' => 48.0,
    'minHeight' => 300.0,
    'marginTop' => 12.0,
    'marginBottom' => 12.0,
]);
$assertGeometry('PRangeSlider', ['orientation' => 2], [
    'width' => 48.0,
    'minWidth' => 48.0,
    'minHeight' => 300.0,
]);
$assertGeometry('PRangeSlider', ['tickLabels' => ['Low', 'High']], [
    'widthPercent' => 100.0,
    'minHeight' => 72.0,
]);
$assertGeometry('PRangeSlider', [
    'thumbLabel' => 'always',
    'tickLabels' => ['Low', 'High'],
], [
    'widthPercent' => 100.0,
    'minHeight' => 104.0,
]);
$assertGeometry('PSwitch', [], [
    'minHeight' => 48.0,
    'minWidth' => 52.0,
    'gap' => 10.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PCheckbox', ['disabled' => true], [
    'minHeight' => 48.0,
    'opacity' => 0.38,
]);
$assertGeometry('PRadioGroup', ['inline' => true], [
    'widthPercent' => 100.0,
    'gap' => 4.0,
]);
$assertGeometry('PDataTable', ['density' => 'comfortable'], [
    'minHeight' => 44.0,
]);
$assertGeometry('PDataTableVirtual', ['density' => 'compact'], [
    'height' => 216.0,
    'minHeight' => 216.0,
]);
$assertGeometry('PDataTableVirtual', ['height' => 280], [
    'height' => 280.0,
    'minHeight' => 280.0,
]);
$assertGeometry('PTreeview', [], [
    'widthPercent' => 100.0,
    'paddingVertical' => 8.0,
]);
$assertGeometry('PTreeviewItem', ['level' => 2], [
    'minHeight' => 48.0,
    'paddingLeft' => 64.0,
    'paddingRight' => 16.0,
]);
$assertGeometry('PTreeviewItem', ['active' => false, 'selected' => true], [
    'backgroundColor' => $themes[0]['theme']->color(ColorToken::Accent),
    'textColor' => $themes[0]['theme']->color(ColorToken::AccentForeground),
]);
$assertGeometry('PEmptyState', [], [
    'minHeight' => 240.0,
    'padding' => 16.0,
    'gap' => 8.0,
]);
$assertGeometry('POtpInput', [], [
    'width' => 320.0,
    'minHeight' => 64.0,
    'paddingHorizontal' => 8.0,
    'paddingVertical' => 8.0,
    'gap' => 8.0,
    'fontSize' => 20.0,
]);
$assertGeometry('POtpInput', ['divided' => true], [
    'width' => 360.0,
]);
foreach (['PSelect', 'PAutocomplete', 'PCombobox', 'PTagInput', 'PMultiSelect'] as $selectionField) {
    $selectionClass = '\\Pam\\MobileUi\\Material\\'.$selectionField;
    $defaultHint = match ($selectionField) {
        'PTagInput' => 'Add tags',
        'PCombobox' => 'Select or add',
        'PAutocomplete' => 'Search options',
        'PMultiSelect' => 'Select options',
        default => 'Select an option',
    };
    foreach ([[], ['placeholder' => 'Choose a category']] as $hintProps) {
        $field = $selectionClass::make([
            'label' => 'Categories', 'items' => [], 'modelValue' => [], ...$hintProps,
        ])->toElement();
        $textNodes = array_values(array_filter(
            $otpNodes($field),
            static fn (\Pam\Native\Element $node): bool => $node->kind() === NodeKind::Text,
        ));
        $labels = array_filter($textNodes, static fn (\Pam\Native\Element $node): bool =>
            ($node->properties()[PropKey::Text->value] ?? null) === 'Categories');
        $hint = $hintProps['placeholder'] ?? $defaultHint;
        $hints = array_values(array_filter($textNodes, static fn (\Pam\Native\Element $node): bool =>
            ($node->properties()[PropKey::Text->value] ?? null) === $hint));
        if (count($labels) !== 1 || count($hints) !== 1) {
            throw new RuntimeException($selectionField.' must separate its persistent label from its empty hint.');
        }
        if (($hints[0]->properties()[PropKey::TextColor->value] ?? null)
            !== ThemeManager::current()->color(ColorToken::MutedForeground)) {
            throw new RuntimeException($selectionField.' empty hint must use the semantic muted foreground.');
        }
    }
    if (in_array($selectionField, ['PSelect', 'PAutocomplete', 'PCombobox'], true)) {
        $selectedField = $selectionClass::make([
            'label' => 'Category', 'placeholder' => 'Mobile',
            'items' => ['Mobile'], 'modelValue' => 'Mobile',
        ])->toElement();
        foreach ($otpNodes($selectedField) as $node) {
            if ($node->kind() === NodeKind::Text
                && ($node->properties()[PropKey::Text->value] ?? null) === 'Mobile'
                && ($node->properties()[PropKey::TextColor->value] ?? null)
                    === ThemeManager::current()->color(ColorToken::MutedForeground)) {
                throw new RuntimeException($selectionField.' selected value must not become placeholder-colored when their strings match.');
            }
        }
    }
    $assertGeometry($selectionField, [], [
        'height' => null,
        'minHeight' => 64.0,
        'paddingTop' => 8.0,
        'paddingBottom' => 4.0,
    ]);
    $assertGeometry($selectionField, ['density' => 'compact'], [
        'height' => null,
        'minHeight' => 48.0,
        'paddingTop' => 4.0,
        'paddingBottom' => 4.0,
    ]);
}
foreach (['PMaskedField', 'PCurrencyField', 'PMultiSelect'] as $errorField) {
    $errorClass = '\\Pam\\MobileUi\\Material\\'.$errorField;
    $errorElement = $errorClass::make([
        'label' => 'Required value', 'error' => true,
        'errorMessage' => 'Choose or enter a valid value',
    ])->toElement();
    $errorHints = array_values(array_filter($otpNodes($errorElement),
        static fn (\Pam\Native\Element $node): bool =>
            $node->kind() === NodeKind::Text
            && ($node->properties()[PropKey::Text->value] ?? null) === 'Choose or enter a valid value'));
    if (count($errorHints) !== 1
        || ($errorHints[0]->properties()[PropKey::TextColor->value] ?? null)
            !== ThemeManager::current()->color(ColorToken::Destructive)) {
        throw new RuntimeException($errorField.' must render one visible semantic error message.');
    }
}
$assertGeometry('PAutocomplete', [], [
    'height' => null,
    'minHeight' => 64.0,
    'paddingTop' => 8.0,
    'paddingBottom' => 4.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PAutocomplete', ['chips' => true], [
    'height' => null,
]);
$assertGeometry('PSelect', ['density' => 'compact'], [
    'height' => null,
    'minHeight' => 48.0,
    'paddingTop' => 4.0,
]);
$assertGeometry('PCombobox', ['density' => 'comfortable'], [
    'height' => null,
    'minHeight' => 56.0,
    'paddingTop' => 4.0,
]);
$assertGeometry('PImg', ['cardMedia' => true], [
    'minHeight' => 200.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PSparkline', [], [
    'height' => 100.0,
    'minHeight' => 100.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PCalendar', [], [
    'minHeight' => 328.0,
    'borderWidth' => 0.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PCalendarDay', [], [
    'width' => 48.0,
    'height' => 48.0,
    'minWidth' => 48.0,
    'minHeight' => 48.0,
    'borderWidth' => 0.0,
    'fontSize' => 14.0,
    'lineHeight' => 20.0,
]);
$assertGeometry('PNavigationBar', [], [
    'widthPercent' => 100.0,
    'minHeight' => 80.0,
    'paddingHorizontal' => 8.0,
    'paddingVertical' => 8.0,
    'gap' => 4.0,
    'flexDirection' => FlexDirection::Row,
    'flexWrap' => FlexWrap::Wrap,
    'alignItems' => Align::Stretch,
    'justifyContent' => Justify::SpaceAround,
]);
$assertGeometry('PNavigationRail', [], [
    'width' => 80.0,
    'minHeight' => 280.0,
    'paddingHorizontal' => 12.0,
    'paddingVertical' => 16.0,
    'gap' => 12.0,
    'flexDirection' => FlexDirection::Column,
    'alignItems' => Align::Center,
]);
foreach (['PNavigationBar', 'PNavigationRail', 'PBottomAppBar'] as $navigationPart) {
    $assertGeometry($navigationPart, ['variant' => MaterialVariant::Text->value], [
        'backgroundColor' => 0x00000000, 'elevation' => 0.0, 'borderWidth' => 0.0,
    ]);
    $assertGeometry($navigationPart, ['variant' => MaterialVariant::Outlined->value], [
        'backgroundColor' => 0x00000000, 'elevation' => 0.0, 'borderWidth' => 1.0,
    ]);
    $assertGeometry($navigationPart, ['variant' => MaterialVariant::Tonal->value], [
        'backgroundColor' => $themes[0]['theme']->color(\Pam\MobileUi\Enum\ColorToken::SurfaceContainerHigh),
        'elevation' => 0.0,
    ]);
}
$assertGeometry('PNavigationRail', ['density' => MaterialDensity::Compact->value], [
    'minHeight' => 240.0, 'gap' => 8.0, 'paddingVertical' => 12.0,
]);
$assertGeometry('PBottomAppBar', [], [
    'widthPercent' => 100.0,
    'minHeight' => 80.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 12.0,
    'gap' => 8.0,
    'flexDirection' => FlexDirection::Row,
    'alignItems' => Align::Center,
    'justifyContent' => Justify::SpaceBetween,
]);
$assertGeometry('PSearchBar', [], [
    'flexDirection' => FlexDirection::Row,
    'alignItems' => Align::Center,
    'gap' => 12.0,
    'widthPercent' => 100.0,
    'height' => 56.0,
    'minHeight' => 56.0,
    'paddingHorizontal' => 20.0,
    'borderRadius' => 28.0,
    'fontSize' => 16.0,
    'lineHeight' => 24.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PPagination', [], [
    'widthPercent' => 100.0,
    'minHeight' => 48.0,
    'gap' => 4.0,
    'flexDirection' => FlexDirection::Row,
    'flexWrap' => FlexWrap::Wrap,
    'alignItems' => Align::Center,
    'justifyContent' => Justify::Center,
]);
$assertGeometry('PSegmentedButton', [], [
    'widthPercent' => 100.0,
    'minHeight' => 48.0,
    'gap' => 0.0,
    'borderWidth' => 1.0,
    'borderRadius' => 24.0,
    'overflow' => Overflow::Hidden,
    'flexDirection' => FlexDirection::Row,
    'alignItems' => Align::Stretch,
]);

fwrite(
    STDOUT,
    json_encode(
        [
            'modules' => count($modules),
            'components' => count($tags),
            'styleCases' => $styleCases,
            'renderCases' => $renderCases,
            'themes' => count($themes),
            'variants' => count($variants),
            'densities' => count($densities),
            'states' => count($states),
        ],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
    )."\n",
);
