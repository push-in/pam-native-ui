<?php

declare(strict_types=1);

use App\Activity;
use App\AppTheme;
use App\Catalog;
use App\ComponentGallery;
use App\ComponentRoute;
use App\Orders;
use App\Overview;
use App\Profile;
use App\ShowcaseRoute;
use App\ShowcaseApp;
use App\TypedCommunityCard;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\MobileUiPluginProvider;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\AccessibilityRole;
use Pam\Native\App;
use Pam\Native\EventKind;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\Navigation\Router;
use Pam\Native\NodeKind;
use Pam\Native\PropKey;
use Pam\Native\StatusBarAppearance;

$root = dirname(__DIR__);
require $root.'/tests/bootstrap.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $root.'/examples/kitchen-sink/src/'
        .str_replace('\\', '/', substr($class, strlen($prefix)))
        .'.php';
    if (is_file($path)) {
        require_once $path;
    }
});

(new MobileUiPluginProvider())->register();
App::views(
    $root.'/examples/kitchen-sink/resources/native',
    $root.'/examples/kitchen-sink/.pam-native/views',
);
AppTheme::install();
PamUI::mode(ThemeMode::Dark);
TypedCommunityCard::register();

$allowedApplicationTags = [
    'AppScreen' => true,
    'ContentState' => true,
    'StatusBanner' => true,
    'MetricCard' => true,
    'DrawerLayoutAndroid' => true,
    'Column' => true,
    'Grid' => true,
    'Pressable' => true,
    'Row' => true,
    'Spacer' => true,
    'Text' => true,
];
$renderedMaterialTags = [];
foreach (glob($root.'/examples/kitchen-sink/resources/native/*.pam') ?: [] as $template) {
    $source = file_get_contents($template);
    if (!is_string($source)) {
        throw new RuntimeException("Cannot read showcase template {$template}.");
    }
    if (preg_match('/<\/?v-[a-z]/', $source) === 1) {
        throw new RuntimeException("Vuetify v-* tag found in {$template}.");
    }
    preg_match_all(
        '/<(?<closing>\/)?p-card(?=[\s>])[^>]*>/',
        $source,
        $cardMatches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
    );
    $cardDepth = 0;
    foreach ($cardMatches as $cardMatch) {
        $isClosing = ($cardMatch['closing'][0] ?? '') === '/';
        if ($isClosing) {
            $cardDepth = max(0, $cardDepth - 1);
            continue;
        }
        $cardDepth++;
        if ($cardDepth > 1) {
            $line = substr_count(
                substr($source, 0, $cardMatch[0][1]),
                "\n",
            ) + 1;
            throw new RuntimeException(
                "Nested decorative p-card found in {$template}:{$line}.",
            );
        }
    }
    preg_match_all('/<(p-[a-z0-9-]+)(?=[\s\/>])/', $source, $materialMatches);
    foreach ($materialMatches[1] ?? [] as $tag) {
        $renderedMaterialTags[$tag] = true;
    }
    preg_match_all('/<\/?([A-Z][A-Za-z0-9]*)(?=[\s\/>])/', $source, $matches);
    foreach ($matches[1] ?? [] as $tag) {
        if (!isset($allowedApplicationTags[$tag])) {
            throw new RuntimeException(
                "Unsupported application or PAM Native tag {$tag} found in {$template}.",
            );
        }
    }
}

/** @var list<array{components: list<string>}> $materialModules */
$materialModules = require $root.'/resources/pam-material-components.php';
$expectedMaterialTags = [];
foreach ($materialModules as $module) {
    if (($module['mobile'] ?? true) !== true) {
        continue;
    }
    foreach ($module['components'] as $component) {
        $tag = strtolower(
            preg_replace('/(?<!^)[A-Z]/', '-$0', $component) ?? $component,
        );
        $expectedMaterialTags[$tag] = true;
    }
}
$missingMaterialTags = array_keys(
    array_diff_key($expectedMaterialTags, $renderedMaterialTags),
);
sort($missingMaterialTags);
if ($missingMaterialTags !== []) {
    throw new RuntimeException(
        'Showcase is missing p-* components: '.implode(', ', $missingMaterialTags),
    );
}

$screens = [
    Overview::class,
    Orders::class,
    Activity::class,
    Profile::class,
    Catalog::class,
    ComponentGallery::class,
];

foreach ($screens as $screen) {
    (new $screen())->toElement();
}

$validationDrawer = Router::drawer('overview')
    ->route('overview', 'Overview', static fn (): Overview => new Overview())
    ->build();
$showcaseRoot = (new ShowcaseApp($validationDrawer, [], []))->toElement();
$showcaseChildren = $showcaseRoot->children();
if ($showcaseRoot->kind() !== NodeKind::Screen
    || count($showcaseChildren) !== 2
    || $showcaseChildren[0]->kind() !== NodeKind::StatusBar
    || $showcaseChildren[1]->kind() !== NodeKind::DrawerLayout
) {
    throw new RuntimeException(
        'Showcase root must contain an explicit StatusBar and its full-size drawer.',
    );
}
$statusBarProperties = $showcaseChildren[0]->properties();
if (($statusBarProperties[PropKey::StatusBarColor->value] ?? null) !== 0xFFF8FAFC
    || ($statusBarProperties[PropKey::StatusBarStyle->value] ?? null)
        !== StatusBarAppearance::Dark->value
) {
    throw new RuntimeException(
        'Light showcase must author a light status surface with readable dark icons.',
    );
}

$showcaseRoutes = [
    'showcase-home',
    'showcase-actions',
    'showcase-forms',
    'showcase-data',
    'showcase-overlays',
];
foreach ($showcaseRoutes as $view) {
    (new ShowcaseRoute($view))->toElement();
}

$interactiveHome = new ShowcaseRoute('showcase-home');
$interactiveHome->drawer = $validationDrawer;
$interactiveHomeTree = $interactiveHome->toElement();
$navigationControl = null;
$navigationHeader = null;
$findNavigationControl = static function ($node, $parent = null) use (
    &$findNavigationControl,
    &$navigationControl,
    &$navigationHeader,
): void {
    if (
        ($node->properties()[PropKey::AccessibilityLabel->value] ?? null)
            === 'Open component navigation'
    ) {
        $navigationControl = $node;
        $navigationHeader = $parent;

        return;
    }
    foreach ($node->children() as $child) {
        $findNavigationControl($child, $node);
        if ($navigationControl !== null) {
            return;
        }
    }
};
$findNavigationControl($interactiveHomeTree);
if ($navigationControl === null) {
    throw new RuntimeException(
        'Showcase home navigation control lost its accessibility label.',
    );
}
if (!isset($navigationControl->events()[EventKind::Press->value])) {
    throw new RuntimeException(
        'Showcase home navigation control lost its press interaction.',
    );
}
$navigationHeaderProperties = $navigationHeader?->properties() ?? [];
if (
    $navigationHeader?->kind() !== NodeKind::Row
    || ($navigationHeaderProperties[PropKey::ZIndex->value] ?? null) !== 1
) {
    throw new RuntimeException(
        'Showcase top app bar must stay above the scroll body for Android hit testing.',
    );
}
$navigationProperties = $navigationControl->properties();
if (
    $navigationControl->kind() !== NodeKind::Pressable
    || ($navigationProperties[PropKey::Width->value] ?? null) !== 48.0
    || ($navigationProperties[PropKey::Height->value] ?? null) !== 48.0
    || ($navigationProperties[PropKey::AccessibilityRole->value] ?? null)
        !== AccessibilityRole::Button->value
) {
    throw new RuntimeException(
        'Showcase home navigation control must remain a semantic 48dp native Pressable.',
    );
}

$componentRouteCount = 0;
foreach (MaterialComponentMap::TAGS as $tag => $component) {
    $title = ucwords(str_replace('-', ' ', substr($tag, 2)));
    (new ComponentRoute($tag, $title, $component))->toElement();
    $componentRouteCount++;
}

$deepLinkedRoute = new ComponentRoute(
    'p-data-table-virtual',
    'Data Table Virtual',
    MaterialComponentMap::TAGS['p-data-table-virtual'],
);
$deepLinkedRoute->useAuditScenario('interactive');
$deepLinkedTree = $deepLinkedRoute->toElement();
$deepLinkedScroll = null;
$findDeepLinkedScroll = static function ($node) use (
    &$findDeepLinkedScroll,
    &$deepLinkedScroll,
): void {
    if ($node->kind() === NodeKind::Scroll) {
        $deepLinkedScroll = $node;

        return;
    }
    foreach ($node->children() as $child) {
        $findDeepLinkedScroll($child);
        if ($deepLinkedScroll !== null) {
            return;
        }
    }
};
$findDeepLinkedScroll($deepLinkedTree);
$deepLinkedScrollProperties = $deepLinkedScroll?->properties() ?? [];
if (
    ($deepLinkedScrollProperties[PropKey::ScrollRequest->value] ?? null) !== 1
    || ($deepLinkedScrollProperties[PropKey::ScrollTargetOffset->value] ?? null) !== 0.0
) {
    throw new RuntimeException(
        'Every component deep link must issue a one-shot scroll-to-top request.',
    );
}

$rangeAuditRoute = new ComponentRoute(
    'p-range-slider',
    'Range Slider',
    MaterialComponentMap::TAGS['p-range-slider'],
);
$rangeAuditRoute->toElement();
$rangeAuditRoute->useAuditScenario('advanced');
$advancedRangeTree = $rangeAuditRoute->toElement();
$advancedRangeLabels = [];
$collectText = static function ($node) use (&$collectText, &$advancedRangeLabels): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $advancedRangeLabels[$text] = true;
        }
    }
    foreach ($node->children() as $child) {
        $collectText($child);
    }
};
$collectText($advancedRangeTree);
$requiredAdvancedRangeLabels = [
    'Read Only',
    'Transient labels',
    'Tick labels',
    'Custom bounds',
    'Vertical',
    'Vertical labelled',
    'Descending input',
    'Coincident values',
];
foreach ($requiredAdvancedRangeLabels as $label) {
    if (!isset($advancedRangeLabels[$label])) {
        throw new RuntimeException(
            "Range Slider advanced audit route did not reactively render {$label}.",
        );
    }
}

$appBarAuditRoute = new ComponentRoute(
    'p-app-bar',
    'App Bar',
    MaterialComponentMap::TAGS['p-app-bar'],
);
$appBarAuditRoute->toElement();
$appBarAuditRoute->useAuditScenario('interactive');
$appBarAuditTree = $appBarAuditRoute->toElement();
$appBarAuditLabels = [];
$collectAppBarText = static function ($node) use (&$collectAppBarText, &$appBarAuditLabels): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $appBarAuditLabels[$text] = true;
        }
    }
    foreach ($node->children() as $child) {
        $collectAppBarText($child);
    }
};
$collectAppBarText($appBarAuditTree);
$requiredAppBarAuditLabels = [
    'Default',
    'Primary',
    'Prominent',
    'Compact',
    'Flat',
    'Elevation 0',
    'Elevation 1',
    'Elevation 2',
    'Elevation 3',
    'Elevation 4',
    'Elevation 5',
];
foreach ($requiredAppBarAuditLabels as $label) {
    if (!isset($appBarAuditLabels[$label])) {
        throw new RuntimeException(
            "App Bar interactive audit route did not reactively render {$label}.",
        );
    }
}

$appBarNavIconAuditRoute = new ComponentRoute(
    'p-app-bar-nav-icon',
    'App Bar Nav Icon',
    MaterialComponentMap::TAGS['p-app-bar-nav-icon'],
);
$appBarNavIconAuditRoute->toElement();
$appBarNavIconAuditRoute->useAuditScenario('interactive');
$appBarNavIconAuditTree = $appBarNavIconAuditRoute->toElement();
$appBarNavIconAuditLabels = [];
$collectAppBarNavIconText = static function ($node) use (
    &$collectAppBarNavIconText,
    &$appBarNavIconAuditLabels,
): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $appBarNavIconAuditLabels[$text] = true;
        }
    }
    foreach ($node->children() as $child) {
        $collectAppBarNavIconText($child);
    }
};
$collectAppBarNavIconText($appBarNavIconAuditTree);
$requiredAppBarNavIconAuditLabels = [
    'Default',
    'Back',
    'Close',
    'Extra Small',
    'Small',
    'Large',
    'Extra Large',
    'Disabled',
    'Primary',
    'Secondary',
    'Custom Color',
];
foreach ($requiredAppBarNavIconAuditLabels as $label) {
    if (!isset($appBarNavIconAuditLabels[$label])) {
        throw new RuntimeException(
            "App Bar Nav Icon audit route did not reactively render {$label}.",
        );
    }
}

$bannerAuditRoute = new ComponentRoute(
    'p-banner',
    'Banner',
    MaterialComponentMap::TAGS['p-banner'],
);
$bannerAuditRoute->toElement();
$bannerAuditRoute->useAuditScenario('interactive');
$bannerAuditTree = $bannerAuditRoute->toElement();
$bannerAuditLabels = [];
$bannerActionCount = 0;
$bannerAlertCount = 0;
$collectBannerAudit = static function ($node) use (
    &$collectBannerAudit,
    &$bannerAuditLabels,
    &$bannerActionCount,
    &$bannerAlertCount,
): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $bannerAuditLabels[$text] = true;
        }
    }
    if (isset($node->events()[EventKind::Press->value])) {
        $bannerActionCount++;
    }
    if (
        ($node->properties()[PropKey::AccessibilityRole->value] ?? null)
            === AccessibilityRole::Alert->value
    ) {
        $bannerAlertCount++;
    }
    foreach ($node->children() as $child) {
        $collectBannerAudit($child);
    }
};
$collectBannerAudit($bannerAuditTree);
$requiredBannerAuditLabels = [
    'Default',
    'One Line',
    'Three Lines',
    'Leading Icon',
    'Single Action',
    'Disabled Action',
    'Success',
    'Information',
    'Warning',
    'Error',
    'Compact',
    'Dismissible',
];
foreach ($requiredBannerAuditLabels as $label) {
    if (!isset($bannerAuditLabels[$label])) {
        throw new RuntimeException(
            "Banner audit route did not reactively render {$label}.",
        );
    }
}
if ($bannerActionCount < 12 || $bannerAlertCount !== 12) {
    throw new RuntimeException(
        'Banner audit route must expose all twelve alerts and their real native actions.',
    );
}

$bannerActionsAuditRoute = new ComponentRoute(
    'p-banner-actions',
    'Banner Actions',
    MaterialComponentMap::TAGS['p-banner-actions'],
);
$bannerActionsAuditRoute->toElement();
$bannerActionsAuditRoute->useAuditScenario('interactive');
$bannerActionsAuditTree = $bannerActionsAuditRoute->toElement();
$bannerActionsAuditLabels = [];
$bannerActionsPressCount = 0;
$collectBannerActionsAudit = static function ($node) use (
    &$collectBannerActionsAudit,
    &$bannerActionsAuditLabels,
    &$bannerActionsPressCount,
): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $bannerActionsAuditLabels[$text] = true;
        }
    }
    if (isset($node->events()[EventKind::Press->value])) {
        $bannerActionsPressCount++;
    }
    foreach ($node->children() as $child) {
        $collectBannerActionsAudit($child);
    }
};
$collectBannerActionsAudit($bannerActionsAuditTree);
foreach ([
    'Default',
    'Single Action',
    'Disabled Action',
    'Long Labels',
    'Independent Pair',
] as $label) {
    if (!isset($bannerActionsAuditLabels[$label])) {
        throw new RuntimeException(
            "Banner Actions audit route did not reactively render {$label}.",
        );
    }
}
if ($bannerActionsPressCount < 8) {
    throw new RuntimeException(
        'Banner Actions audit route must expose real callbacks for every enabled action.',
    );
}

$buttonAuditRoute = new ComponentRoute(
    'p-btn',
    'Button',
    MaterialComponentMap::TAGS['p-btn'],
);
$buttonAuditRoute->toElement();
$buttonAuditRoute->useAuditScenario('interactive');
$buttonAuditTree = $buttonAuditRoute->toElement();
$buttonAuditLabels = [];
$buttonAuditPressCount = 0;
$buttonAuditStates = [];
$buttonAuditPressStates = [];
$collectButtonAudit = static function ($node) use (
    &$collectButtonAudit,
    &$buttonAuditLabels,
    &$buttonAuditPressCount,
    &$buttonAuditPressStates,
    &$buttonAuditStates,
): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $buttonAuditLabels[$text] = true;
        }
    }
    $accessibilityLabel = $node->properties()[PropKey::AccessibilityLabel->value] ?? null;
    if (is_string($accessibilityLabel) && str_ends_with($accessibilityLabel, ' button')) {
        $buttonAuditStates[$accessibilityLabel] = $node->properties();
    }
    if (is_string($accessibilityLabel) && str_ends_with($accessibilityLabel, ' button')) {
        $hasPress = isset($node->events()[EventKind::Press->value]);
        $buttonAuditPressStates[$accessibilityLabel] = $hasPress;
        if ($hasPress) {
            $buttonAuditPressCount++;
        }
    }
    foreach ($node->children() as $child) {
        $collectButtonAudit($child);
    }
};
$collectButtonAudit($buttonAuditTree);
foreach ([
    'Default',
    'Elevated',
    'Flat',
    'Tonal',
    'Outlined',
    'Text',
    'Plain',
    'Leading icon',
    'Trailing icon',
    'Block',
    'Extra small',
    'Small',
    'Large',
    'Extra large',
    'Compact density',
    'Success',
    'Error',
    'Disabled',
    'Loading',
] as $label) {
    if (!isset($buttonAuditLabels[$label])) {
        throw new RuntimeException(
            "Button audit route did not reactively render {$label}.",
        );
    }
}
if ($buttonAuditPressCount !== 17) {
    throw new RuntimeException(
        "Button audit route exposes {$buttonAuditPressCount} press callbacks; expected only its 17 interactive variations.",
    );
}
$disabledButtonState = $buttonAuditStates['Unavailable button'] ?? [];
$loadingButtonState = $buttonAuditStates['Saving button'] ?? [];
if (
    ($disabledButtonState[PropKey::Enabled->value] ?? true) !== false
    || ($loadingButtonState[PropKey::Enabled->value] ?? true) !== false
    || ($loadingButtonState[PropKey::Loading->value] ?? false) !== true
    || ($buttonAuditPressStates['Unavailable button'] ?? true) !== false
    || ($buttonAuditPressStates['Saving button'] ?? true) !== false
) {
    throw new RuntimeException(
        'Button Disabled and Loading variations must remain non-interactive in the native protocol.',
    );
}

$buttonGroupAuditRoute = new ComponentRoute(
    'p-btn-group',
    'Button Group',
    MaterialComponentMap::TAGS['p-btn-group'],
);
$buttonGroupAuditRoute->toElement();
$buttonGroupAuditRoute->useAuditScenario('interactive');
$buttonGroupAuditTree = $buttonGroupAuditRoute->toElement();
$buttonGroupAuditLabels = [];
$buttonGroupToggleCount = 0;
$buttonGroupPressCount = 0;
$buttonGroupChangeCount = 0;
$buttonGroupSelectedCount = 0;
$buttonGroupDisabledCount = 0;
$collectButtonGroupAudit = static function ($node) use (
    &$collectButtonGroupAudit,
    &$buttonGroupAuditLabels,
    &$buttonGroupToggleCount,
    &$buttonGroupPressCount,
    &$buttonGroupChangeCount,
    &$buttonGroupSelectedCount,
    &$buttonGroupDisabledCount,
): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $buttonGroupAuditLabels[$text] = true;
        }
    }
    $properties = $node->properties();
    if (
        ($properties[PropKey::AccessibilityRole->value] ?? null)
            === AccessibilityRole::ToggleButton->value
    ) {
        $buttonGroupToggleCount++;
        $buttonGroupSelectedCount +=
            ($properties[PropKey::Selected->value] ?? false) === true ? 1 : 0;
        $buttonGroupDisabledCount +=
            ($properties[PropKey::Enabled->value] ?? true) === false ? 1 : 0;
        if (isset($node->events()[EventKind::Press->value])) {
            $buttonGroupPressCount++;
        }
    }
    if (isset($node->events()[EventKind::Change->value])) {
        $buttonGroupChangeCount++;
    }
    foreach ($node->children() as $child) {
        $collectButtonGroupAudit($child);
    }
};
$collectButtonGroupAudit($buttonGroupAuditTree);
foreach ([
    'Standard',
    'Connected',
    'Full width',
    'Single optional',
    'Mandatory',
    'Multiple',
    'Leading icons',
    'Compact density',
    'Rounded connected',
    'Tile connected',
    'Disabled item',
    'Disabled group',
    'Long labels',
    'RTL connected',
    'Success color',
] as $label) {
    if (!isset($buttonGroupAuditLabels[$label])) {
        throw new RuntimeException(
            "Button Group audit route did not reactively render {$label}.",
        );
    }
}
if (
    $buttonGroupToggleCount !== 44
    || $buttonGroupPressCount !== 40
    || $buttonGroupChangeCount !== 14
    || $buttonGroupSelectedCount !== 16
    || $buttonGroupDisabledCount !== 4
) {
    throw new RuntimeException(
        'Button Group audit route must expose all 15 controlled groups, 44 toggle targets, selected states and disabled states; got '
        ."{$buttonGroupToggleCount} toggles, {$buttonGroupPressCount} presses, "
        ."{$buttonGroupChangeCount} changes, {$buttonGroupSelectedCount} selected, "
        ."{$buttonGroupDisabledCount} disabled.",
    );
}

$buttonToggleAuditRoute = new ComponentRoute(
    'p-btn-toggle',
    'Button Toggle',
    MaterialComponentMap::TAGS['p-btn-toggle'],
);
$buttonToggleAuditRoute->toElement();
$buttonToggleAuditRoute->useAuditScenario('interactive');
$buttonToggleAuditTree = $buttonToggleAuditRoute->toElement();
$buttonToggleAuditLabels = [];
$buttonToggleTargetCount = 0;
$buttonTogglePressCount = 0;
$buttonToggleChangeCount = 0;
$buttonToggleSelectedCount = 0;
$buttonToggleDisabledCount = 0;
$collectButtonToggleAudit = static function ($node) use (
    &$collectButtonToggleAudit,
    &$buttonToggleAuditLabels,
    &$buttonToggleTargetCount,
    &$buttonTogglePressCount,
    &$buttonToggleChangeCount,
    &$buttonToggleSelectedCount,
    &$buttonToggleDisabledCount,
): void {
    if ($node->kind() === NodeKind::Text) {
        $text = $node->properties()[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $buttonToggleAuditLabels[$text] = true;
        }
    }
    $properties = $node->properties();
    if (
        ($properties[PropKey::AccessibilityRole->value] ?? null)
            === AccessibilityRole::ToggleButton->value
    ) {
        $buttonToggleTargetCount++;
        $buttonToggleSelectedCount +=
            ($properties[PropKey::Selected->value] ?? false) === true ? 1 : 0;
        $buttonToggleDisabledCount +=
            ($properties[PropKey::Enabled->value] ?? true) === false ? 1 : 0;
        if (isset($node->events()[EventKind::Press->value])) {
            $buttonTogglePressCount++;
        }
    }
    if (isset($node->events()[EventKind::Change->value])) {
        $buttonToggleChangeCount++;
    }
    foreach ($node->children() as $child) {
        $collectButtonToggleAudit($child);
    }
};
$collectButtonToggleAudit($buttonToggleAuditTree);
foreach ([
    'Single choice',
    'Multiple choice',
    'Full width',
    'Single optional',
    'Leading icons',
    'Compact density',
    'Two options',
    'Five options',
    'Disabled item',
    'Disabled group',
    'Long labels',
    'Tile',
    'RTL',
    'Success color',
] as $label) {
    if (!isset($buttonToggleAuditLabels[$label])) {
        throw new RuntimeException(
            "Button Toggle audit route did not reactively render {$label}.",
        );
    }
}
if (
    $buttonToggleTargetCount !== 42
    || $buttonTogglePressCount !== 38
    || $buttonToggleChangeCount !== 13
    || $buttonToggleSelectedCount !== 15
    || $buttonToggleDisabledCount !== 4
) {
    throw new RuntimeException(
        'Button Toggle audit route must expose all 14 controlled segmented groups, 42 targets, selected states and disabled states; got '
        ."{$buttonToggleTargetCount} targets, {$buttonTogglePressCount} presses, "
        ."{$buttonToggleChangeCount} changes, {$buttonToggleSelectedCount} selected, "
        ."{$buttonToggleDisabledCount} disabled.",
    );
}

$calendarAuditRoute = new ComponentRoute(
    'p-calendar',
    'Calendar',
    MaterialComponentMap::TAGS['p-calendar'],
);
$calendarAuditRoute->toElement();
$calendarAuditRoute->useAuditScenario('interactive');
$calendarAuditTree = $calendarAuditRoute->toElement();
$calendarAuditLabels = [];
$calendarHosts = [];
$calendarGridCount = 0;
$calendarMonthSelectors = 0;
$calendarYearSelectors = 0;
$collectCalendarAudit = static function ($node) use (
    &$collectCalendarAudit,
    &$calendarAuditLabels,
    &$calendarHosts,
    &$calendarGridCount,
    &$calendarMonthSelectors,
    &$calendarYearSelectors,
): void {
    $properties = $node->properties();
    if ($node->kind() === NodeKind::Text) {
        $text = $properties[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $calendarAuditLabels[$text] = true;
        }
    }
    $value = $properties[PropKey::Value->value] ?? null;
    $calendarGridCount += $value === 'pam:calendar-grid' ? 1 : 0;
    $calendarMonthSelectors += $value === 'pam:calendar-month-select' ? 1 : 0;
    $calendarYearSelectors += $value === 'pam:calendar-year-select' ? 1 : 0;
    $host = $properties[PropKey::HostProperties->value] ?? null;
    if ($host instanceof BinaryValue) {
        $decoded = Wire::decodeMap($host->bytes);
        if (($decoded['behavior'] ?? null) === 7) {
            $calendarHosts[] = $decoded;
        }
    }
    foreach ($node->children() as $child) {
        $collectCalendarAudit($child);
    }
};
$collectCalendarAudit($calendarAuditTree);
foreach ([
    'Single date',
    'Multiple dates',
    'Date range',
    'Limits and unavailable dates',
    'Adjacent dates hidden',
    'Week numbers',
    'Monday first',
    'Dynamic four-week month',
    'Disabled',
    'Read only',
    'RTL',
    'Success color',
] as $label) {
    if (!isset($calendarAuditLabels[$label])) {
        throw new RuntimeException(
            "Calendar audit route did not reactively render {$label}.",
        );
    }
}
$calendarModes = array_count_values(array_map(
    static fn (array $host): int => (int) ($host['mode'] ?? 0),
    $calendarHosts,
));
if (
    count($calendarHosts) !== 12
    || $calendarGridCount !== 12
    || $calendarMonthSelectors !== 12
    || $calendarYearSelectors !== 12
    || ($calendarModes[1] ?? 0) !== 10
    || ($calendarModes[2] ?? 0) !== 1
    || ($calendarModes[3] ?? 0) !== 1
    || count(array_filter(
        $calendarHosts,
        static fn (array $host): bool => ($host['showWeek'] ?? false) === true,
    )) !== 1
    || count(array_filter(
        $calendarHosts,
        static fn (array $host): bool => ($host['fixedWeeks'] ?? false) === false,
    )) !== 1
) {
    throw new RuntimeException(
        'Calendar audit route must expose 12 complete controlled calendars with single, multiple, range, week-number and dynamic-row modes.',
    );
}

$bottomSheetAuditRoute = new ComponentRoute(
    'p-bottom-sheet',
    'Bottom Sheet',
    MaterialComponentMap::TAGS['p-bottom-sheet'],
);
$bottomSheetAuditRoute->toElement();
$bottomSheetAuditRoute->useAuditScenario('interactive');
$bottomSheetAuditTree = $bottomSheetAuditRoute->toElement();
$bottomSheetAuditLabels = [];
$bottomSheetPressCount = 0;
$bottomSheetHosts = [];
$bottomSheetChangeHandlers = [];
$bottomSheetDragIndicators = 0;
$collectBottomSheetAudit = static function ($node) use (
    &$collectBottomSheetAudit,
    &$bottomSheetAuditLabels,
    &$bottomSheetPressCount,
    &$bottomSheetHosts,
    &$bottomSheetChangeHandlers,
    &$bottomSheetDragIndicators,
): void {
    $properties = $node->properties();
    if ($node->kind() === NodeKind::Text) {
        $text = $properties[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $bottomSheetAuditLabels[$text] = true;
        }
    }
    if (($properties[PropKey::Value->value] ?? null) === 'pam:sheet-drag-indicator') {
        $bottomSheetDragIndicators++;
    }
    if (isset($node->events()[EventKind::Press->value])) {
        $bottomSheetPressCount++;
    }
    $host = $properties[PropKey::HostProperties->value] ?? null;
    if ($host instanceof BinaryValue) {
        $decoded = Wire::decodeMap($host->bytes);
        if (($decoded['component'] ?? null) === 19) {
            $bottomSheetHosts[] = $decoded;
            $change = $node->events()[EventKind::Change->value] ?? null;
            if ($change instanceof Closure) {
                $bottomSheetChangeHandlers[] = [
                    'props' => $decoded,
                    'handler' => $change,
                ];
            }
        }
    }
    foreach ($node->children() as $child) {
        $collectBottomSheetAudit($child);
    }
};
$collectBottomSheetAudit($bottomSheetAuditTree);
foreach ([
    'Default',
    'Two detents',
    'Persistent',
    'No scrim',
    'No drag indicator',
    'Dynamic height',
    'Keyboard form',
    'Open default sheet',
    'Open two-detent sheet',
    'Open persistent sheet',
    'Open sheet without scrim',
    'Open sheet without indicator',
    'Open dynamic sheet',
    'Open keyboard form sheet',
] as $label) {
    if (!isset($bottomSheetAuditLabels[$label])) {
        throw new RuntimeException(
            "Bottom Sheet audit route did not render {$label}.",
        );
    }
}
if (
    count($bottomSheetHosts) !== 7
    || count($bottomSheetChangeHandlers) !== 7
    || $bottomSheetDragIndicators !== 6
    || $bottomSheetPressCount < 21
) {
    throw new RuntimeException(
        'Bottom Sheet audit route must expose seven real sheets, six indicators and complete actions.',
    );
}
$persistentSheet = array_values(array_filter(
    $bottomSheetHosts,
    static fn (array $props): bool => ($props['persistent'] ?? null) === true,
));
if (
    count($persistentSheet) !== 1
    || ($persistentSheet[0]['dismissible'] ?? null) !== false
    || ($persistentSheet[0]['enablePanDownToClose'] ?? null) !== false
) {
    throw new RuntimeException(
        'Persistent Bottom Sheet must reject backdrop, Back and pan dismissal while keeping explicit actions.',
    );
}
$twoDetentSheet = array_values(array_filter(
    $bottomSheetHosts,
    static fn (array $props): bool => ($props['snapPoints'] ?? null) === "34\n68",
));
if (count($twoDetentSheet) !== 1) {
    throw new RuntimeException(
        'Bottom Sheet audit route must preserve its two native detents.',
    );
}
$twoDetentChange = array_values(array_filter(
    $bottomSheetChangeHandlers,
    static fn (array $entry): bool =>
        ($entry['props']['snapPoints'] ?? null) === "34\n68",
));
if (count($twoDetentChange) !== 1) {
    throw new RuntimeException(
        'Two-detent Bottom Sheet must expose its real native change callback.',
    );
}
$twoDetentChange[0]['handler']('1');
$expandedBottomSheetTree = $bottomSheetAuditRoute->toElement();
$expandedBottomSheetLabels = [];
$expandedBottomSheetIndex = null;
$collectExpandedBottomSheet = static function ($node) use (
    &$collectExpandedBottomSheet,
    &$expandedBottomSheetLabels,
    &$expandedBottomSheetIndex,
): void {
    $properties = $node->properties();
    if ($node->kind() === NodeKind::Text) {
        $text = $properties[PropKey::Text->value] ?? null;
        if (is_string($text)) {
            $expandedBottomSheetLabels[$text] = true;
        }
    }
    $host = $properties[PropKey::HostProperties->value] ?? null;
    if ($host instanceof BinaryValue) {
        $decoded = Wire::decodeMap($host->bytes);
        if (($decoded['snapPoints'] ?? null) === "34\n68") {
            $expandedBottomSheetIndex = $decoded['snapToIndex'] ?? null;
        }
    }
    foreach ($node->children() as $child) {
        $collectExpandedBottomSheet($child);
    }
};
$collectExpandedBottomSheet($expandedBottomSheetTree);
if (
    !isset($expandedBottomSheetLabels['Expanded details'])
    || $expandedBottomSheetIndex !== 1
) {
    throw new RuntimeException(
        'Two-detent Bottom Sheet must reactively fill its expanded state and preserve native snap index 1.',
    );
}

fwrite(
    STDOUT,
    sprintf(
        "Rendered %d showcase screens, %d component routes and %d/%d material components for structural coverage only; this is not interaction approval.\n",
        count($screens) + count($showcaseRoutes),
        $componentRouteCount,
        count($expectedMaterialTags),
        count($expectedMaterialTags),
    ),
);
