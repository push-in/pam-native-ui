<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentMode;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Rendering\MaterialStyleResolver;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\AccessibilityCheckedState;
use Pam\Native\AccessibilityRole;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\EventKind;
use Pam\Native\ImageErrorEvent;
use Pam\Native\Align;
use Pam\Native\FlexDirection;
use Pam\Native\ModalAnimationType;
use Pam\Native\ModalPresentation;
use Pam\Native\NodeKind;
use Pam\Native\PositionType;
use Pam\Native\PropKey;
use Pam\Native\Style;
use Pam\Native\UI\Text;

require __DIR__.'/bootstrap.php';

$tags = MaterialComponentMap::TAGS;
$ids = MaterialComponentMap::IDS;
$modules = MaterialComponentMap::MODULES;
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
if (($twoLineItem->properties()[PropKey::Height->value] ?? null) !== 64.0) {
    throw new RuntimeException(
        'p-list-item must infer the 64dp two-line Material height.',
    );
}
$compactItem = $twoLineItemClass::make([
    'density' => 'compact',
    'lines' => 1,
    'text' => 'Account',
])->toElement();
if (($compactItem->properties()[PropKey::Height->value] ?? null) !== 40.0) {
    throw new RuntimeException(
        'Compact one-line p-list-item must use a 40dp visual height.',
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
$itemClass = $tags['p-item'];
$selectedItem = $itemClass::make(
    ['selected' => true],
    Text::make('Design'),
)->toElement();
$selectedItemLabel = $selectedItem->children()[0] ?? null;
if (
    !$selectedItemLabel instanceof \Pam\Native\Element
    || ($selectedItemLabel->properties()[PropKey::TextColor->value] ?? null)
        !== Themes::pamLight()->color(ColorToken::SecondaryForeground)
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
    || $slideGroupItemStyle->minWidth !== 96.0
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
    ) {
        throw new RuntimeException(
            "{$timePickerTag} must open a native time picker, not calendar behavior.",
        );
    }
}

$dateInputClass = $tags['p-date-input'];
$dateInput = $dateInputClass::make([
    'modelValue' => '2026-07-28',
    'minimumDate' => '2026-07-01',
    'maximumDate' => '2026-07-31',
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
) {
    throw new RuntimeException(
        'p-date-input must open a bounded native date selector instead of a plain text field.',
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
    ['value' => 'left'],
    $buttonClass::make(['text' => 'Left', 'value' => 'left']),
    $buttonClass::make(['text' => 'Right', 'value' => 'right']),
)->onChange(
    static function (string $value) use (&$selectedButton): void {
        $selectedButton = $value;
    },
)->toElement();
$rightButtonPress = ($buttonToggle->children()[1] ?? null)?->events()[
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

$mandatoryButtons = null;
$mandatoryToggle = $buttonToggleClass::make(
    ['value' => ['left'], 'multiple' => true, 'mandatory' => true],
    $buttonClass::make(['text' => 'Left', 'value' => 'left']),
)->onChange(
    static function (array $value) use (&$mandatoryButtons): void {
        $mandatoryButtons = $value;
    },
)->toElement();
$mandatoryPress = ($mandatoryToggle->children()[0] ?? null)?->events()[
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
$slidePress = ($slideGroup->children()[0] ?? null)?->events()[
    EventKind::Press->value
] ?? null;
if (!$slidePress instanceof Closure) {
    throw new RuntimeException(
        'p-slide-group must bind press handling to p-slide-group-item.',
    );
}
$slidePress();
if ($selectedSlide !== 'second') {
    throw new RuntimeException(
        'p-slide-group must emit the selected item value.',
    );
}

$tooltipClass = $tags['p-tooltip'];
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
] as $selectionTag => [$searchable, $allowCustomValue]) {
    $selectionClass = $tags[$selectionTag];
    $selection = $selectionClass::make([
        'label' => 'Framework',
        'items' => ['PAM', 'Native'],
    ])->toElement();
    $stack = [$selection];
    $hasTrigger = false;
    $hasInput = false;
    $sheetProperties = null;
    while ($stack !== []) {
        $candidate = array_pop($stack);
        $marker = $candidate->properties()[PropKey::Value->value] ?? null;
        $hasTrigger = $hasTrigger || (
            is_string($marker)
            && str_starts_with($marker, 'pam:local-modal-trigger:')
        );
        $hasInput = $hasInput || $candidate->kind() === NodeKind::Input;
        $host = $candidate->properties()[PropKey::HostProperties->value] ?? null;
        if ($host instanceof BinaryValue) {
            $decoded = Wire::decodeMap($host->bytes);
            if (array_key_exists('searchable', $decoded)) {
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
        || $hasInput
        || $sheetProperties['searchable'] !== $searchable
        || $sheetProperties['allowCustomValue'] !== $allowCustomValue
    ) {
        throw new RuntimeException(
            "{$selectionTag} must open a platform-neutral searchable mobile sheet.",
        );
    }
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
while ($stack !== []) {
    $candidate = array_pop($stack);
    if ($candidate->kind() === NodeKind::Input) {
        $nativeNumberInput = $candidate;
    }
    if (
        $candidate->kind() === NodeKind::Pressable
        && ($candidate->properties()[PropKey::Enabled->value] ?? true) === false
    ) {
        $disabledNumberButtons++;
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
if ($numberChanges !== ['10', '4'] || $disabledNumberButtons !== 1) {
    throw new RuntimeException(
        'p-number-input must clamp, snap and disable controls at numeric limits.',
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
) {
    throw new RuntimeException(
        'p-dialog persistent, fullscreen and scrim semantics must reach the native window.',
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
$navIcon = $navIconClass::make(['icon' => 'menu'])->toElement();
if (
    $navIcon->kind() !== NodeKind::Pressable
    || ($navIcon->properties()[PropKey::AccessibilityLabel->value] ?? null)
        !== 'Open navigation'
    || ($navIcon->properties()[PropKey::MinWidth->value] ?? null) !== 48.0
    || ($navIcon->properties()[PropKey::MinHeight->value] ?? null) !== 48.0
) {
    throw new RuntimeException(
        'p-app-bar-nav-icon must be an accessible 48dp navigation action.',
    );
}

$calendarClass = $tags['p-calendar'];
$calendar = $calendarClass::make([
    'modelValue' => '2026-07-28',
])->toElement();
$calendarHeader = $calendar->children()[0] ?? null;
$previousMonth = $calendarHeader?->children()[0] ?? null;
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
) {
    throw new RuntimeException(
        'p-calendar month navigation must expose labelled 48dp actions.',
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
$treeFilePress = $treeFile?->events()[EventKind::Press->value] ?? null;
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

$imageClass = $tags['p-img'];
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

$dataTableClass = $tags['p-data-table'];
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
if ($selectedRows !== [1, 2]) {
    throw new RuntimeException(
        'p-data-table row selection must emit the updated model value.',
    );
}

$virtualTableClass = $tags['p-data-table-virtual'];
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
    'minHeight' => 36.0,
]);
$assertGeometry('PBtn', ['density' => 'comfortable'], [
    'minHeight' => 32.0,
]);
$assertGeometry('PBtn', ['density' => 'compact'], [
    'minHeight' => 28.0,
]);
$assertGeometry('PBtn', ['disabled' => true], [
    'opacity' => 0.26,
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
    'width' => 36.0,
    'height' => 36.0,
]);
$assertGeometry('PIconBtn', ['size' => 'x-small'], [
    'width' => 20.0,
    'height' => 20.0,
]);
$assertGeometry('PIconBtn', ['size' => 'small'], [
    'width' => 28.0,
    'height' => 28.0,
]);
$assertGeometry('PIconBtn', ['size' => 'large'], [
    'width' => 44.0,
    'height' => 44.0,
]);
$assertGeometry('PIconBtn', ['size' => 'x-large'], [
    'width' => 52.0,
    'height' => 52.0,
]);
$assertGeometry('PIconBtn', ['density' => 'comfortable'], [
    'width' => 32.0,
    'height' => 32.0,
]);
$assertGeometry('PIconBtn', ['density' => 'compact'], [
    'width' => 28.0,
    'height' => 28.0,
]);
$assertGeometry('PCard', [], [
    'padding' => 0.0,
    'borderRadius' => 4.0,
]);
$assertGeometry('PChip', [], [
    'minHeight' => 32.0,
    'paddingHorizontal' => 12.0,
    'borderRadius' => 9999.0,
]);
$assertGeometry('PChip', ['density' => 'comfortable'], [
    'minHeight' => 28.0,
    'paddingHorizontal' => 10.0,
]);
$assertGeometry('PChip', ['density' => 'compact'], [
    'minHeight' => 24.0,
    'paddingHorizontal' => 8.0,
]);
$assertGeometry('PList', [], [
    'paddingVertical' => 8.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PListItem', [], [
    'minHeight' => 48.0,
]);
$assertGeometry('PListItem', ['density' => 'comfortable'], [
    'minHeight' => 44.0,
]);
$assertGeometry('PListItem', ['density' => 'compact'], [
    'minHeight' => 40.0,
]);
$assertGeometry('PListItem', ['lines' => 2], [
    'minHeight' => 64.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 12.0,
]);
$assertGeometry('PListItem', ['lines' => 3], [
    'minHeight' => 88.0,
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 16.0,
]);
$assertGeometry('PTab', [], [
    'minWidth' => 90.0,
    'minHeight' => 48.0,
]);
$assertGeometry('PTab', ['density' => 'comfortable'], [
    'minHeight' => 44.0,
]);
$assertGeometry('PTab', ['density' => 'compact'], [
    'minHeight' => 36.0,
]);
$assertGeometry('PTextField', [], [
    'height' => 56.0,
    'minHeight' => 56.0,
    'paddingTop' => 8.0,
    'paddingBottom' => 4.0,
]);
$assertGeometry('PTextField', ['density' => 'comfortable'], [
    'height' => 48.0,
    'minHeight' => 48.0,
]);
$assertGeometry('PTextField', ['density' => 'compact'], [
    'height' => 40.0,
    'minHeight' => 40.0,
    'paddingTop' => 0.0,
    'paddingBottom' => 0.0,
]);
$assertGeometry('PTextField', ['variant' => 'outlined'], [
    'borderWidth' => 1.0,
    'borderBottomWidth' => null,
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
    'width' => 32.0,
    'height' => 32.0,
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
    'paddingHorizontal' => 16.0,
    'paddingVertical' => 16.0,
    'gap' => 16.0,
    'borderRadius' => 4.0,
    'elevation' => 1.0,
]);
$assertGeometry('PAlert', ['border' => 'start'], [
    'paddingLeft' => 24.0,
    'borderLeftWidth' => 8.0,
]);
$assertGeometry('PAlert', ['border' => 'start', 'rtl' => true], [
    'paddingRight' => 24.0,
    'borderRightWidth' => 8.0,
]);
$assertGeometry('PAlert', ['border' => 'top'], [
    'paddingTop' => 24.0,
    'borderTopWidth' => 8.0,
]);
$assertGeometry('PBanner', [], [
    'paddingLeft' => 16.0,
    'paddingRight' => 8.0,
    'paddingTop' => 16.0,
    'paddingBottom' => 16.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PBannerActions', [], [
    'gap' => 8.0,
    'marginTop' => 20.0,
]);
$assertGeometry('PBadge', [], [
    'minWidth' => 20.0,
    'minHeight' => 20.0,
    'borderRadius' => 9999.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBadge', ['bordered' => true], [
    'borderWidth' => 2.0,
]);
$assertGeometry('PBottomSheet', [], [
    'widthPercent' => 100.0,
    'borderRadius' => 28.0,
    'elevation' => 6.0,
]);
$assertGeometry('PCardActions', [], [
    'minHeight' => 52.0,
    'padding' => 8.0,
    'gap' => 8.0,
]);
$assertGeometry('PExpansionPanelTitle', [], [
    'minHeight' => 48.0,
    'paddingHorizontal' => 24.0,
]);
$assertGeometry('PExpansionPanel', ['active' => true], [
    'marginVertical' => 16.0,
]);
$assertGeometry('PExpansionPanel', ['disabled' => true], [
    'opacity' => 0.26,
]);
$assertGeometry('PExpansionPanelTitle', ['active' => true], [
    'minHeight' => 64.0,
]);
$assertGeometry('PExpansionPanelText', [], [
    'paddingHorizontal' => 24.0,
    'paddingTop' => 8.0,
    'paddingBottom' => 16.0,
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
    'borderRadius' => 9999.0,
    'elevation' => 3.0,
]);
$assertGeometry('PFab', ['size' => 'large'], [
    'width' => 72.0,
    'height' => 72.0,
]);
$assertGeometry('PFab', ['size' => 'x-large'], [
    'width' => 96.0,
    'height' => 96.0,
]);
$assertGeometry('PBadge', [], [
    'height' => 20.0,
    'minWidth' => 20.0,
    'borderRadius' => 9999.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PBadge', ['dot' => true], [
    'width' => 9.0,
    'height' => 9.0,
    'borderWidth' => 0.0,
]);
$assertGeometry('PCarousel', [], [
    'height' => 500.0,
    'minHeight' => 500.0,
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
    'height' => 40.0,
    'minHeight' => 40.0,
    'animationDurationMs' => 150,
]);
$assertGeometry('PTimeline', [], [
    'widthPercent' => 100.0,
    'gap' => 0.0,
]);
$assertGeometry('PTimelineItem', [], [
    'minHeight' => 86.0,
    'padding' => 24.0,
    'gap' => 24.0,
    'borderWidth' => 2.0,
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
    'minHeight' => 32.0,
    'paddingHorizontal' => 8.0,
    'borderRadius' => 6.0,
    'animationDurationMs' => 300,
]);
$assertGeometry('PRangeSlider', ['vertical' => true], [
    'width' => 32.0,
    'minHeight' => 300.0,
    'marginTop' => 12.0,
    'marginBottom' => 12.0,
]);
$assertGeometry('PSwitch', [], [
    'minHeight' => 40.0,
    'minWidth' => 40.0,
    'gap' => 10.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PCheckbox', ['disabled' => true], [
    'minHeight' => 40.0,
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
$assertGeometry('PAutocomplete', [], [
    'height' => 56.0,
    'minHeight' => 56.0,
    'animationDurationMs' => 200,
]);
$assertGeometry('PAutocomplete', ['chips' => true], [
    'height' => 64.0,
]);
$assertGeometry('PSelect', ['density' => 'compact'], [
    'height' => 40.0,
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
    'minHeight' => 344.0,
    'borderWidth' => 1.0,
    'borderRadius' => 0.0,
]);
$assertGeometry('PCalendarDay', [], [
    'minHeight' => 56.0,
    'paddingTop' => 4.0,
    'fontSize' => 12.0,
    'lineHeight' => 20.0,
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
