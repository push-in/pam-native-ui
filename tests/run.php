<?php

declare(strict_types=1);

use Pam\MobileUi\Component\Button;
use Pam\MobileUi\Component\ButtonText;
use Pam\MobileUi\Component\Accordion;
use Pam\MobileUi\Component\AccordionContent;
use Pam\MobileUi\Component\AccordionContentText;
use Pam\MobileUi\Component\AccordionHeader;
use Pam\MobileUi\Component\AccordionIcon;
use Pam\MobileUi\Component\AccordionItem;
use Pam\MobileUi\Component\AccordionTitleText;
use Pam\MobileUi\Component\AccordionTrigger;
use Pam\MobileUi\Component\BottomSheet;
use Pam\MobileUi\Component\BottomSheetPortal;
use Pam\MobileUi\Component\Checkbox;
use Pam\MobileUi\Component\CheckboxGroup;
use Pam\MobileUi\Component\CheckboxIcon;
use Pam\MobileUi\Component\CheckboxIndicator;
use Pam\MobileUi\Component\CheckboxLabel;
use Pam\MobileUi\Component\CheckIcon;
use Pam\MobileUi\Component\Calendar;
use Pam\MobileUi\Component\CalendarGrid;
use Pam\MobileUi\Component\CalendarHeader;
use Pam\MobileUi\Component\CalendarHeaderNextButton;
use Pam\MobileUi\Component\CalendarHeaderPrevButton;
use Pam\MobileUi\Component\CalendarHeaderTitle;
use Pam\MobileUi\Component\Drawer;
use Pam\MobileUi\Component\DrawerContent;
use Pam\MobileUi\Component\DateTimePicker;
use Pam\MobileUi\Component\DateTimePickerInput;
use Pam\MobileUi\Component\DateTimePickerTrigger;
use Pam\MobileUi\Component\HStack;
use Pam\MobileUi\Component\Attachment;
use Pam\MobileUi\Component\AttachmentPreview;
use Pam\MobileUi\Component\Attachments;
use Pam\MobileUi\Component\Message;
use Pam\MobileUi\Component\MessageContent;
use Pam\MobileUi\Component\Menu;
use Pam\MobileUi\Component\MenuItem;
use Pam\MobileUi\Component\ModelSelector;
use Pam\MobileUi\Component\ModelSelectorContent;
use Pam\MobileUi\Component\Popover;
use Pam\MobileUi\Component\PopoverContent;
use Pam\MobileUi\Component\PromptInput;
use Pam\MobileUi\Component\PromptInputTextarea;
use Pam\MobileUi\Component\Progress;
use Pam\MobileUi\Component\ProgressFilledTrack;
use Pam\MobileUi\Component\Radio;
use Pam\MobileUi\Component\RadioGroup;
use Pam\MobileUi\Component\RadioIcon;
use Pam\MobileUi\Component\RadioIndicator;
use Pam\MobileUi\Component\RadioLabel;
use Pam\MobileUi\Component\Select;
use Pam\MobileUi\Component\SelectContent;
use Pam\MobileUi\Component\SelectItem;
use Pam\MobileUi\Component\SelectPortal;
use Pam\MobileUi\Component\SelectTrigger;
use Pam\MobileUi\Component\Slider;
use Pam\MobileUi\Component\SliderFilledTrack;
use Pam\MobileUi\Component\SliderThumb;
use Pam\MobileUi\Component\SliderTrack;
use Pam\MobileUi\Component\Tabs;
use Pam\MobileUi\Component\TabsContent;
use Pam\MobileUi\Component\TabsContentWrapper;
use Pam\MobileUi\Component\TabsIndicator;
use Pam\MobileUi\Component\TabsList;
use Pam\MobileUi\Component\TabsTrigger;
use Pam\MobileUi\Component\Toast;
use Pam\MobileUi\Component\ToastTitle;
use Pam\MobileUi\Component\SwitchControl;
use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentCategory;
use Pam\MobileUi\Enum\ComponentMaturity;
use Pam\MobileUi\Enum\ComponentMode;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\ComponentState;
use Pam\MobileUi\Enum\ComponentType;
use Pam\MobileUi\Enum\ComponentVariant;
use Pam\MobileUi\Enum\DrawerAnchor;
use Pam\MobileUi\Enum\ImplementationKind;
use Pam\MobileUi\Enum\MessageRole;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\Orientation;
use Pam\MobileUi\Enum\ParityGate;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Enum\PrimitiveKind;
use Pam\MobileUi\Enum\SkeletonSpeed;
use Pam\MobileUi\Enum\TabsActivationMode;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Enum\ToastAction;
use Pam\MobileUi\Enum\VerificationStatus;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\StyleRecipes;
use Pam\MobileUi\MobileUiPluginProvider;
use Pam\MobileUi\Rendering\TailwindStyleCompiler;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\FlexDirection;
use Pam\Native\FontStyle;
use Pam\Native\Align;
use Pam\Native\Internal\TemplateCompiler;
use Pam\Native\Internal\TemplateRenderer;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\ModalPresentation;
use Pam\Native\NodeKind;
use Pam\Native\PointerEvents;
use Pam\Native\PositionType;
use Pam\Native\PropKey;
use Pam\Native\TemplateRegistry;
use Pam\Native\TextDecoration;
use Pam\Native\TextTransform;
use Pam\Native\UI\Text;

$pamNativeRoot = getenv('PAM_NATIVE_ROOT');
if ($pamNativeRoot === false || $pamNativeRoot === '') {
    $pamNativeRoot = dirname(__DIR__, 2).'/pam-native';
}

$roots = [
    'Pam\\Native\\' => $pamNativeRoot.'/packages/native/src/',
    'Pam\\MobileUi\\' => dirname(__DIR__).'/src/',
];

spl_autoload_register(static function (string $class) use ($roots): void {
    foreach ($roots as $prefix => $root) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $root.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

require dirname(__DIR__).'/src/Generated/ComponentFacades.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach ([
    ComponentCategory::cases(),
    ComponentMaturity::cases(),
    ComponentMode::cases(),
    ImplementationKind::cases(),
    MessageRole::cases(),
    PrimitiveKind::cases(),
    ThemeMode::cases(),
    ColorToken::cases(),
    ComponentSize::cases(),
    ComponentState::cases(),
    ComponentType::cases(),
    ComponentVariant::cases(),
    DrawerAnchor::cases(),
    ButtonVariant::cases(),
    Orientation::cases(),
    ParityGate::cases(),
    Placement::cases(),
    SkeletonSpeed::cases(),
    ToastAction::cases(),
    VerificationStatus::cases(),
    NativeBehavior::cases(),
] as $cases) {
    foreach ($cases as $index => $case) {
        $assert(
            $case->value === $index + 1,
            $case::class.' values must be sequential integers starting at one.',
        );
    }
}

$upstream = json_decode(
    (string) file_get_contents(dirname(__DIR__).'/resources/upstream-components.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($upstream)) {
    throw new RuntimeException('The upstream catalog must decode to an array.');
}
$modules = $upstream['modules'] ?? null;
if (!is_array($modules)) {
    throw new RuntimeException('The upstream module catalog is missing.');
}
$assert(count($modules) === 61, 'Every upstream technical module must be captured.');
$styles = json_decode(
    (string) file_get_contents(dirname(__DIR__).'/resources/styles.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($styles) || !is_array($styles['styles'] ?? null)) {
    throw new RuntimeException('The upstream style catalog is missing.');
}
$assert(
    count($styles['styles']) === 211,
    'Every upstream tva style definition must be captured.',
);
$inlineRecipes = json_decode(
    (string) file_get_contents(dirname(__DIR__).'/resources/inline-recipes.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($inlineRecipes) || !is_array($inlineRecipes['recipes'] ?? null)) {
    throw new RuntimeException('The generated inline recipe catalog is missing.');
}
$assert(
    count($inlineRecipes['recipes']) >= 45,
    'The upstream inline component recipes are incomplete.',
);
$assert(
    isset(StyleRecipes::COMPONENTS['PromptInputTextarea']),
    'Inline upstream recipes must be attached to generated component facades.',
);
$icons = json_decode(
    (string) file_get_contents(dirname(__DIR__).'/resources/icons.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($icons) || !is_array($icons['icons'] ?? null)) {
    throw new RuntimeException('The upstream icon catalog is missing.');
}
$assert(
    count($icons['icons']) === 55,
    'Every upstream vector icon path must be captured.',
);
$parity = json_decode(
    (string) file_get_contents(dirname(__DIR__).'/resources/parity.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (!is_array($parity) || !is_array($parity['modules'] ?? null)) {
    throw new RuntimeException('The parity catalog is missing.');
}
$assert(count($parity['modules']) === 61, 'The parity gate must cover all technical modules.');
$accordionParity = null;
$calendarParity = null;
$checkboxParity = null;
$dateTimeParity = null;
$radioParity = null;
foreach ($parity['modules'] as $module) {
    if (!is_array($module)) {
        continue;
    }
    if (($module['name'] ?? null) === 'calendar') {
        $calendarParity = $module['verification'] ?? null;
    }
    if (($module['name'] ?? null) === 'date-time-picker') {
        $dateTimeParity = $module['verification'] ?? null;
    }
    if (($module['name'] ?? null) === 'checkbox') {
        $checkboxParity = $module['verification'] ?? null;
    }
    if (($module['name'] ?? null) === 'radio') {
        $radioParity = $module['verification'] ?? null;
    }
    if (($module['name'] ?? null) === 'accordion') {
        $accordionParity = $module['verification'] ?? null;
    }
}
$assert(
    $accordionParity === [
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::NotApplicable->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
    ],
    'Accordion verification evidence must survive deterministic regeneration.',
);
$assert(
    $calendarParity === array_fill(0, 10, VerificationStatus::Verified->value),
    'Calendar verification evidence must survive deterministic regeneration.',
);
$assert(
    $dateTimeParity === array_fill(0, 10, VerificationStatus::Verified->value),
    'DateTimePicker verification evidence must survive deterministic regeneration.',
);
$assert(
    $checkboxParity === [
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::NotApplicable->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
        VerificationStatus::Verified->value,
    ],
    'Checkbox verification evidence must survive deterministic regeneration.',
);
$assert(
    $radioParity === array_fill(0, 10, VerificationStatus::Verified->value),
    'Radio verification evidence must survive deterministic regeneration.',
);
$assert(count(ComponentMap::TAGS) >= 300, 'The generated public component anatomy is incomplete.');
$assert(
    array_values(ComponentMap::IDS) === range(1, count(ComponentMap::IDS)),
    'Generated component IDs must be sequential and deterministic.',
);
$assert(class_exists(SwitchControl::class), 'Reserved PHP names must receive a typed facade alias.');

ThemeManager::mode(ThemeMode::Light);
$assert(
    Themes::light()->color(ColorToken::Background) === 0xffffffff,
    'The upstream light background token changed unexpectedly.',
);
$assert(
    Themes::dark()->color(ColorToken::Background) === 0xff0a0a0a,
    'The upstream dark background token changed unexpectedly.',
);

$button = Button::make('Continue')
    ->variant(ButtonVariant::Outline)
    ->size(ComponentSize::Large)
    ->onPress(static function (): void {
    })
    ->toElement();
$assert($button->kind() === NodeKind::Pressable, 'Button must use the flattened native pressable primitive.');
$assert(count($button->children()) === 1, 'Button text fallback must be composed automatically.');
$assert(
    count($button->events()) === 1,
    'Typed semantic events must be registered without per-frame callbacks.',
);
$assert(
    $button->properties()[PropKey::BorderWidth->value] === 1.0,
    'The upstream outline variant must compile its border recipe.',
);
$assert(
    $button->properties()[PropKey::MinHeight->value] === 40.0,
    'The upstream large button size must compile min-h-10 exactly.',
);
$assert(
    $button->properties()[PropKey::PaddingHorizontal->value] === 32.0,
    'The upstream large button size must compile px-8 exactly.',
);
$composedButton = Button::make(
    [
        'variant' => ButtonVariant::Outline,
        'size' => ComponentSize::Large,
    ],
    ButtonText::make('Continue'),
)->toElement();
$buttonText = $composedButton->children()[0] ?? null;
if (!$buttonText instanceof \Pam\Native\Element) {
    throw new RuntimeException('Composed button text must render.');
}
$assert(
    $buttonText->properties()[PropKey::TextColor->value]
        === Themes::light()->color(ColorToken::Foreground),
    'Parent variants must select the exact upstream child text color.',
);
$assert(
    $composedButton->properties()[PropKey::HitSlop->value] === 8,
    'Pressable components must expand their Android touch target without changing layout.',
);

$sliderEndValue = null;
$slider = Slider::make(['value' => 40.0])
    ->onChange(static function (): void {
    })
    ->onChangeEnd(static function (float $value) use (&$sliderEndValue): void {
        $sliderEndValue = $value;
    })
    ->toElement();
$assert($slider->kind() === NodeKind::CustomView, 'Gesture-heavy controls must stay in a native host.');
$assert(
    isset($slider->properties()[PropKey::HostProperties->value]),
    'Native component properties must use the packed host-property map.',
);
$assert(
    isset($slider->events()[\Pam\Native\EventKind::Change->value]),
    'Typed native hosts must preserve semantic change events.',
);
$sliderEnd = $slider->events()[\Pam\Native\EventKind::Native->value] ?? null;
if (!$sliderEnd instanceof Closure) {
    throw new RuntimeException('Slider must preserve its final-value callback.');
}
$sliderEnd('42.5');
$assert(
    $sliderEndValue === 42.5,
    'Slider onChangeEnd must decode the final native value as a float.',
);
$authoredSlider = Slider::make(
    [
        'defaultValue' => 30.0,
        'minValue' => 10.0,
        'maxValue' => 90.0,
        'sliderTrackHeight' => 8.0,
        'thumbSize' => 20.0,
    ],
    SliderTrack::make(SliderFilledTrack::make()),
    SliderThumb::make(),
)->toElement();
$authoredSliderNative = $authoredSlider->properties()[
    PropKey::HostProperties->value
] ?? null;
$authoredSliderTrack = $authoredSlider->children()[0] ?? null;
$authoredSliderFill = $authoredSliderTrack?->children()[0] ?? null;
$authoredSliderThumb = $authoredSlider->children()[1] ?? null;
if (
    !$authoredSliderNative instanceof BinaryValue
    || !$authoredSliderTrack instanceof \Pam\Native\Element
    || !$authoredSliderFill instanceof \Pam\Native\Element
    || !$authoredSliderThumb instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Slider must preserve its authored native anatomy.');
}
$authoredSliderProperties = Wire::decodeMap($authoredSliderNative->bytes);
$assert(
    $authoredSliderProperties['value'] === 30.0
        && $authoredSliderProperties['min'] === 10.0
        && $authoredSliderProperties['max'] === 90.0
        && $authoredSliderProperties['trackThickness'] === 8.0
        && $authoredSliderProperties['thumbSize'] === 20.0
        && $authoredSliderTrack->properties()[PropKey::Value->value]
            === 'pam:slider-track'
        && $authoredSliderFill->properties()[PropKey::Value->value]
            === 'pam:slider-filled-track'
        && $authoredSliderThumb->properties()[PropKey::Value->value]
            === 'pam:slider-thumb',
    'Slider aliases, geometry and authored anatomy must reach the native UI thread.',
);

$progress = Progress::make(
    [
        'value' => 35.0,
        'min' => 10.0,
        'max' => 60.0,
        'orientation' => Orientation::Vertical,
    ],
    ProgressFilledTrack::make(),
)->toElement();
$progressNative = $progress->properties()[PropKey::HostProperties->value] ?? null;
$progressFill = $progress->children()[0] ?? null;
if (
    !$progressNative instanceof BinaryValue
    || !$progressFill instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Progress must preserve its authored filled track.');
}
$progressProperties = Wire::decodeMap($progressNative->bytes);
$assert(
    $progressProperties['behavior'] === NativeBehavior::Progress->value
        && $progressProperties['value'] === 35.0
        && $progressProperties['min'] === 10.0
        && $progressProperties['max'] === 60.0
        && $progressProperties['orientation'] === Orientation::Vertical->value
        && is_int($progressProperties['trackColor'])
        && is_int($progressProperties['fillColor'])
        && $progressFill->properties()[PropKey::Value->value]
            === 'pam:progress-filled-track',
    'Progress range, orientation and authored colors must stay packed and native.',
);

$switchToggles = [];
$switch = SwitchControl::make([
    'defaultValue' => true,
    'size' => ComponentSize::Small,
    'trackColor' => [
        'false' => '#d4d4d4',
        'true' => '#525252',
    ],
    'thumbColor' => '#fafafa',
    'activeThumbColor' => '#ffffff',
])->onToggle(static function (bool $checked) use (&$switchToggles): void {
    $switchToggles[] = $checked;
})->toElement();
$switchNative = $switch->properties()[PropKey::HostProperties->value] ?? null;
$switchToggle = $switch->events()[\Pam\Native\EventKind::Toggle->value] ?? null;
if (!$switchNative instanceof BinaryValue || !$switchToggle instanceof Closure) {
    throw new RuntimeException('Switch must provide packed native state and toggle semantics.');
}
$switchProperties = Wire::decodeMap($switchNative->bytes);
$switchToggle(true);
$assert(
    $switch->kind() === NodeKind::CustomView
        && $switch->properties()[PropKey::ScaleX->value] === 0.75
        && $switch->properties()[PropKey::ScaleY->value] === 0.75
        && $switch->properties()[PropKey::Checked->value] === true
        && $switchProperties['behavior'] === NativeBehavior::SwitchControl->value
        && $switchProperties['checked'] === true
        && $switchProperties['trackOffColor'] === 0xffd4d4d4
        && $switchProperties['trackOnColor'] === 0xff525252
        && $switchProperties['thumbColor'] === 0xfffafafa
        && $switchProperties['activeThumbColor'] === 0xffffffff
        && $switchToggles === [true],
    'Switch defaults, sizes, colors and semantic events must use its optimized host.',
);

$icon = CheckIcon::make()->toElement();
$assert($icon->kind() === NodeKind::CustomView, 'Upstream SVG paths must render through the native icon host.');
$assert(
    isset($icon->properties()[PropKey::HostProperties->value]),
    'Native icons must carry their generated integer path ID.',
);

$row = HStack::make(
    Text::make('One'),
    Text::make('Two'),
)->toElement();
$assert(
    $row->properties()[PropKey::FlexDirection->value] === FlexDirection::Row->value,
    'HStack must select horizontal native layout without a wrapper runtime.',
);

$drawer = Drawer::make(
    [
        'size' => ComponentSize::Medium,
        'anchor' => DrawerAnchor::Left,
    ],
    DrawerContent::make(Text::make('Navigation')),
)->toElement();
$drawerHost = $drawer->children()[0] ?? null;
$drawerContent = $drawerHost?->children()[0] ?? null;
if (
    !$drawerHost instanceof \Pam\Native\Element
    || !$drawerContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Drawer content must inherit size and anchor.');
}
$assert(
    $drawer->kind() === NodeKind::Modal
        && $drawerHost->kind() === NodeKind::CustomView
        && $drawer->properties()[PropKey::ModalPresentation->value]
            === ModalPresentation::FullScreen->value
        && $drawerContent->properties()[PropKey::WidthPercent->value] === 50.0
        && $drawerContent->properties()[PropKey::HeightPercent->value] === 100.0,
    'Drawer must use a native full-screen window and preserve its compound dimensions.',
);

$reversedSlider = Slider::make(
    [
        'orientation' => Orientation::Horizontal,
        'isReversed' => true,
    ],
    SliderTrack::make(),
)->toElement();
$reversedTrack = $reversedSlider->children()[0] ?? null;
if (!$reversedTrack instanceof \Pam\Native\Element) {
    throw new RuntimeException('Slider track must inherit its orientation.');
}
$assert(
    $reversedTrack->properties()[PropKey::FlexDirection->value]
        === FlexDirection::RowReverse->value,
    'Boolean parent compound variants must compile reversed slider direction locally.',
);

$tabs = Tabs::make(
    [
        'value' => 'security',
        'activationMode' => TabsActivationMode::Manual,
    ],
    TabsList::make(
        TabsTrigger::make(['value' => 'account']),
        TabsTrigger::make(['value' => 'security']),
        TabsIndicator::make(),
    ),
    TabsContentWrapper::make(
        TabsContent::make(['value' => 'account'], Text::make('First')),
        TabsContent::make(['value' => 'security'], Text::make('Second')),
    ),
)->toElement();
$tabsList = $tabs->children()[0] ?? null;
$tabsContentWrapper = $tabs->children()[1] ?? null;
$firstTabContent = $tabsContentWrapper?->children()[0] ?? null;
$secondTabContent = $tabsContentWrapper?->children()[1] ?? null;
if (
    !$tabsList instanceof \Pam\Native\Element
    || !$tabsContentWrapper instanceof \Pam\Native\Element
    || !$firstTabContent instanceof \Pam\Native\Element
    || !$secondTabContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Tabs must render controlled content children.');
}
$firstTabsTrigger = $tabsList->children()[0] ?? null;
$secondTabsTrigger = $tabsList->children()[1] ?? null;
$tabsIndicator = $tabsList->children()[2] ?? null;
if (
    !$firstTabsTrigger instanceof \Pam\Native\Element
    || !$secondTabsTrigger instanceof \Pam\Native\Element
    || !$tabsIndicator instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Tabs list must render its semantic triggers.');
}
$firstTabsTriggerProperties = $firstTabsTrigger->properties()[
    PropKey::HostProperties->value
] ?? null;
if (!$firstTabsTriggerProperties instanceof BinaryValue) {
    throw new RuntimeException('TabsTrigger must use the native semantic host.');
}
$firstTabsTriggerNative = Wire::decodeMap($firstTabsTriggerProperties->bytes);
$assert(
    $firstTabsTrigger->kind() === NodeKind::CustomView
        && $firstTabsTriggerNative['behavior'] === NativeBehavior::TabsTrigger->value
        && $firstTabsTriggerNative['value'] === 'account'
        && !isset($firstTabsTrigger->properties()[PropKey::Selected->value])
        && $secondTabsTrigger->properties()[PropKey::Selected->value] === true
        && $tabsList->properties()[PropKey::Value->value] === 'pam:tabs-list'
        && $tabsContentWrapper->properties()[PropKey::Value->value]
            === 'pam:tabs-content-wrapper'
        && $tabsIndicator->properties()[PropKey::Value->value] === 'pam:tabs-indicator'
        && $firstTabContent->properties()[PropKey::Value->value]
            === 'pam:tabs-content:account'
        && $secondTabContent->properties()[PropKey::Value->value]
            === 'pam:tabs-content:security',
    'Tabs anatomy and semantic values must reach the coordinated native hosts.',
);
$assert(
    CalendarGrid::make()->toElement()->properties()[PropKey::Value->value]
        === 'pam:calendar-grid',
    'Calendar grid geometry must be discoverable without intercepting header controls.',
);
$calendarValues = [];
$calendar = Calendar::make(
    [
        'mode' => ComponentMode::Multiple,
        'value' => ['2026-07-23', '2026-07-24'],
        'disabledDates' => ['2026-07-25'],
        'firstDayOfWeek' => 1,
        'fixedWeeks' => true,
    ],
    CalendarHeader::make(
        CalendarHeaderPrevButton::make(),
        CalendarHeaderTitle::make('July 2026'),
        CalendarHeaderNextButton::make(),
    ),
    CalendarGrid::make(),
)->onChange(static function (array $values) use (&$calendarValues): void {
    $calendarValues = $values;
})->toElement();
$calendarProperties = $calendar->properties()[PropKey::HostProperties->value] ?? null;
$calendarChange = $calendar->events()[\Pam\Native\EventKind::Change->value] ?? null;
if (
    !$calendarProperties instanceof BinaryValue
    || !$calendarChange instanceof Closure
) {
    throw new RuntimeException('Calendar must compile native state and a typed change handler.');
}
$calendarNative = Wire::decodeMap($calendarProperties->bytes);
$calendarHeader = $calendar->children()[0] ?? null;
$calendarPrevious = $calendarHeader?->children()[0] ?? null;
$calendarTitle = $calendarHeader?->children()[1] ?? null;
$calendarNext = $calendarHeader?->children()[2] ?? null;
$calendarChange("M\n2026-07-23\n2026-07-28");
$assert(
    $calendarNative['mode'] === ComponentMode::Multiple->value
        && $calendarNative['selectedValues'] === "2026-07-23\n2026-07-24"
        && $calendarNative['disabledDates'] === '2026-07-25'
        && $calendarNative['firstDayOfWeek'] === 1
        && $calendarNative['fixedWeeks'] === true
        && $calendarValues === ['2026-07-23', '2026-07-28']
        && $calendarPrevious?->properties()[PropKey::Value->value] === 'pam:calendar-prev'
        && $calendarTitle?->properties()[PropKey::Value->value] === 'pam:calendar-title'
        && $calendarNext?->properties()[PropKey::Value->value] === 'pam:calendar-next',
    'Calendar must keep its complete selection and month chrome on the native UI thread.',
);
$calendarRange = [];
$rangeCalendar = Calendar::make([
    'mode' => ComponentMode::Range,
    'value' => [
        'from' => '2026-07-10',
        'to' => '2026-07-16',
    ],
])->onChange(static function (array $range) use (&$calendarRange): void {
    $calendarRange = $range;
})->toElement();
$rangeProperties = $rangeCalendar->properties()[PropKey::HostProperties->value] ?? null;
$rangeChange = $rangeCalendar->events()[\Pam\Native\EventKind::Change->value] ?? null;
if (
    !$rangeProperties instanceof BinaryValue
    || !$rangeChange instanceof Closure
) {
    throw new RuntimeException('Range Calendar must compile native state and events.');
}
$rangeNative = Wire::decodeMap($rangeProperties->bytes);
$rangeChange("R\n2026-07-20\n2026-07-27");
$assert(
    $rangeNative['mode'] === ComponentMode::Range->value
        && $rangeNative['rangeFrom'] === '2026-07-10'
        && $rangeNative['rangeTo'] === '2026-07-16'
        && $calendarRange === [
            'from' => '2026-07-20',
            'to' => '2026-07-27',
        ],
    'Calendar range payloads must cross the bridge once as a bounded native value.',
);
$dateTimePicker = DateTimePicker::make(
    [
        'mode' => ComponentMode::DateTime,
        'value' => '2026-07-23T14:35:40-03:00',
        'minimumDate' => '2026-07-01',
        'maximumDate' => '2026-07-31',
        'timeZoneOffsetInMinutes' => -180,
        'format' => 'DD/MM/YYYY HH:mm',
        'is24Hour' => true,
    ],
    DateTimePickerTrigger::make(
        DateTimePickerInput::make(),
    ),
)->onChange(static function (string $value): void {
})->toElement();
$dateTimeProperties = $dateTimePicker->properties()[PropKey::HostProperties->value] ?? null;
$dateTimeTrigger = $dateTimePicker->children()[0] ?? null;
$dateTimeInput = $dateTimeTrigger?->children()[0] ?? null;
if (
    !$dateTimeProperties instanceof BinaryValue
    || !$dateTimeInput instanceof \Pam\Native\Element
) {
    throw new RuntimeException('DateTimePicker must compile its native host and input.');
}
$dateTimeNative = Wire::decodeMap($dateTimeProperties->bytes);
$assert(
    $dateTimeNative['mode'] === ComponentMode::DateTime->value
        && $dateTimeNative['minimumDate'] === '2026-07-01'
        && $dateTimeNative['maximumDate'] === '2026-07-31'
        && $dateTimeNative['timeZoneOffsetInMinutes'] === -180
        && $dateTimeNative['is24Hour'] === true
        && $dateTimeInput->properties()[PropKey::Value->value] === '23/07/2026 14:35',
    'DateTimePicker must preserve its native limits, time zone and display format.',
);
$assert(
    $firstTabContent->properties()[PropKey::Visible->value] === false
        && !isset($secondTabContent->properties()[PropKey::Visible->value]),
    'Tabs must hide inactive panels without a PHP callback on every frame.',
);
$defaultTabs = Tabs::make(
    ['defaultValue' => 'home'],
    TabsList::make(
        TabsTrigger::make(['value' => 'home']),
        TabsTrigger::make(['value' => 'profile']),
    ),
    TabsContent::make(['value' => 'home'], Text::make('Home')),
    TabsContent::make(
        ['value' => 'profile', 'forceMount' => true],
        Text::make('Profile'),
    ),
)->toElement();
$defaultTabsList = $defaultTabs->children()[0] ?? null;
$defaultHomeTrigger = $defaultTabsList?->children()[0] ?? null;
$defaultHomeContent = $defaultTabs->children()[1] ?? null;
$forcedProfileContent = $defaultTabs->children()[2] ?? null;
$assert(
    $defaultHomeTrigger?->properties()[PropKey::Selected->value] === true
        && !isset($defaultHomeContent?->properties()[PropKey::Visible->value])
        && !isset($forcedProfileContent?->properties()[PropKey::Visible->value])
        && $forcedProfileContent?->properties()[PropKey::Value->value]
            === 'pam:tabs-content-force:profile',
    'Tabs defaultValue and forceMount must preserve upstream uncontrolled behavior.',
);

$toast = Toast::make(
    [
        'variant' => ComponentVariant::Solid,
        'action' => ToastAction::Error,
    ],
    ToastTitle::make('Could not save'),
)->toElement();
$toastTitle = $toast->children()[0] ?? null;
if (!$toastTitle instanceof \Pam\Native\Element) {
    throw new RuntimeException('Toast title must inherit action and variant.');
}
$assert(
    $toastTitle->properties()[PropKey::TextColor->value]
        === Themes::light()->color(ColorToken::DestructiveForeground),
    'Multi-axis color compound variants must use the exact semantic foreground token.',
);

$attachments = Attachments::make(
    ['variant' => ComponentVariant::List],
    Attachment::make(
        AttachmentPreview::make(),
    ),
)->toElement();
$attachment = $attachments->children()[0] ?? null;
if (!$attachment instanceof \Pam\Native\Element) {
    throw new RuntimeException('Attachment recipes must render their anatomy.');
}
$preview = $attachment->children()[0] ?? null;
if (!$preview instanceof \Pam\Native\Element) {
    throw new RuntimeException('Attachment previews must inherit the parent display variant.');
}
$assert(
    $attachments->properties()[PropKey::FlexDirection->value] === FlexDirection::Column->value
        && $attachment->properties()[PropKey::WidthPercent->value] === 100.0
        && $preview->properties()[PropKey::Width->value] === 48.0,
    'Attachment list variants must compile through inherited integer variant context.',
);

$message = Message::make(
    ['role' => MessageRole::User],
    MessageContent::make(Text::make('Hello')),
)->toElement();
$messageContent = $message->children()[0] ?? null;
if (!$messageContent instanceof \Pam\Native\Element) {
    throw new RuntimeException('Message content must inherit the message role.');
}
$assert(
    $message->properties()[PropKey::MarginTop->value] === 16.0
        && $messageContent->properties()[PropKey::AlignSelf->value] === Align::End->value
        && $messageContent->properties()[PropKey::MaxWidthPercent->value] === 90.0,
    'Chat role recipes must preserve the upstream user bubble layout.',
);

$assert(
    PromptInput::make(PromptInputTextarea::make())->toElement()->kind() === NodeKind::View,
    'PromptInput must remain a styled container instead of becoming a text field.',
);
$assert(
    PromptInputTextarea::make()->toElement()->kind() === NodeKind::Input,
    'PromptInputTextarea must use the optimized native input primitive.',
);
$modelSelector = ModelSelector::make(
    ['open' => false],
    ModelSelectorContent::make(),
)->onNativeEvent(static function (): void {
})->toElement();
$modelSelectorContent = $modelSelector->children()[0] ?? null;
$modelSelectorHost = $modelSelectorContent?->children()[0] ?? null;
if (
    !$modelSelectorContent instanceof \Pam\Native\Element
    || !$modelSelectorHost instanceof \Pam\Native\Element
) {
    throw new RuntimeException('ModelSelector must render its controlled content.');
}
$assert(
    $modelSelector->kind() === NodeKind::View
        && !isset($modelSelector->properties()[PropKey::Visible->value])
        && $modelSelectorContent->properties()[PropKey::Visible->value] === false,
    'ModelSelector must keep its trigger host mounted while hiding controlled content.',
);
$assert(
    $modelSelectorContent->kind() === NodeKind::Modal
        && $modelSelectorHost->kind() === NodeKind::CustomView
        && isset($modelSelectorContent->events()[\Pam\Native\EventKind::Native->value])
        && isset($modelSelectorHost->events()[\Pam\Native\EventKind::Native->value]),
    'ModelSelector events must reach both native window dismissal and its semantic host.',
);
$closedSelect = Select::make(
    ['open' => false],
    SelectTrigger::make('Choose stack'),
    SelectPortal::make(),
)->onNativeEvent(static function (): void {
})->toElement();
$closedSelectTrigger = $closedSelect->children()[0] ?? null;
$closedSelectPortal = $closedSelect->children()[1] ?? null;
$closedSelectHost = $closedSelectPortal?->children()[0] ?? null;
if (
    !$closedSelectTrigger instanceof \Pam\Native\Element
    || !$closedSelectPortal instanceof \Pam\Native\Element
    || !$closedSelectHost instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Select must render both trigger and portal.');
}
$assert(
    $closedSelect->kind() === NodeKind::View
        && !isset($closedSelect->properties()[PropKey::Visible->value])
        && !isset($closedSelectTrigger->properties()[PropKey::Visible->value])
        && $closedSelectPortal->properties()[PropKey::Visible->value] === false,
    'A closed Select must keep its trigger mounted and hide only its portal.',
);
$assert(
    $closedSelectPortal->kind() === NodeKind::Modal
        && $closedSelectPortal->properties()[PropKey::ModalPresentation->value]
            === ModalPresentation::Sheet->value
        && isset($closedSelectPortal->events()[\Pam\Native\EventKind::Native->value])
        && $closedSelectHost->kind() === NodeKind::CustomView
        && isset($closedSelectHost->events()[\Pam\Native\EventKind::Native->value]),
    'Select must forward dismissals and semantic events into its native sheet.',
);
$bottomSheet = BottomSheet::make(
    ['open' => true],
    BottomSheetPortal::make(),
)->onChange(static function (): void {
})->toElement();
$bottomSheetWindow = $bottomSheet->children()[0] ?? null;
$bottomSheetHost = $bottomSheetWindow?->children()[0] ?? null;
if (
    !$bottomSheetWindow instanceof \Pam\Native\Element
    || !$bottomSheetHost instanceof \Pam\Native\Element
) {
    throw new RuntimeException('BottomSheet must render its native portal window.');
}
$assert(
    $bottomSheet->kind() === NodeKind::View
        && $bottomSheetWindow->kind() === NodeKind::Modal
        && $bottomSheetWindow->properties()[PropKey::ModalPresentation->value]
            === ModalPresentation::Sheet->value
        && $bottomSheetHost->kind() === NodeKind::CustomView
        && isset($bottomSheetHost->events()[\Pam\Native\EventKind::Change->value]),
    'BottomSheet must keep its root stable and forward changes into its native sheet host.',
);

$selectedFramework = null;
$semanticSelect = Select::make(
    [
        'open' => true,
        'selectedValue' => 'laravel',
    ],
    SelectPortal::make(
        SelectContent::make(
            SelectItem::make([
                'label' => 'Laravel',
                'value' => 'laravel',
            ]),
        ),
    ),
)->onChange(static function (string $value) use (&$selectedFramework): void {
    $selectedFramework = $value;
})->toElement();
$semanticSelectPortal = $semanticSelect->children()[0] ?? null;
$semanticSelectHost = $semanticSelectPortal?->children()[0] ?? null;
$semanticSelectContent = $semanticSelectHost?->children()[0] ?? null;
$semanticSelectItem = $semanticSelectContent?->children()[0] ?? null;
$semanticSelectPress = $semanticSelectItem?->events()[
    \Pam\Native\EventKind::Press->value
] ?? null;
if (
    !$semanticSelectItem instanceof \Pam\Native\Element
    || !$semanticSelectPress instanceof Closure
) {
    throw new RuntimeException('Select must bind its parent change event to semantic items.');
}
$semanticSelectPress();
$assert(
    $selectedFramework === 'laravel'
        && $semanticSelectItem->properties()[PropKey::Value->value] === 'laravel'
        && $semanticSelectItem->properties()[PropKey::Checked->value] === true,
    'SelectItem must inherit controlled selection and deliver its value through the parent.',
);

$selectedPlan = null;
$localRadioToggles = 0;
$radioGroup = RadioGroup::make(
    ['value' => 'pro'],
    HStack::make(
        Radio::make(
            ['value' => 'pro'],
            RadioIndicator::make(RadioIcon::make()),
            RadioLabel::make('Pro'),
        )->onToggle(static function () use (&$localRadioToggles): void {
            $localRadioToggles++;
        }),
    ),
)->onChange(static function (string $value) use (&$selectedPlan): void {
    $selectedPlan = $value;
})->toElement();
$radioRow = $radioGroup->children()[0] ?? null;
$semanticRadio = $radioRow?->children()[0] ?? null;
$semanticRadioIndicator = $semanticRadio?->children()[0] ?? null;
$semanticRadioIcon = $semanticRadioIndicator?->children()[0] ?? null;
$semanticRadioToggle = $semanticRadio?->events()[
    \Pam\Native\EventKind::Toggle->value
] ?? null;
if (
    !$semanticRadio instanceof \Pam\Native\Element
    || !$semanticRadioIndicator instanceof \Pam\Native\Element
    || !$semanticRadioIcon instanceof \Pam\Native\Element
    || !$semanticRadioToggle instanceof Closure
) {
    throw new RuntimeException('RadioGroup must forward its handler through layout wrappers.');
}
$radioGroupNative = $radioGroup->properties()[PropKey::HostProperties->value] ?? null;
$radioNative = $semanticRadio->properties()[PropKey::HostProperties->value] ?? null;
if (!$radioGroupNative instanceof BinaryValue || !$radioNative instanceof BinaryValue) {
    throw new RuntimeException('RadioGroup and Radio must provide packed native state.');
}
$radioGroupProperties = Wire::decodeMap($radioGroupNative->bytes);
$radioProperties = Wire::decodeMap($radioNative->bytes);
$semanticRadioToggle('1');
$assert(
    $selectedPlan === 'pro'
        && $localRadioToggles === 1
        && $radioGroupProperties['behavior'] === NativeBehavior::RadioGroup->value
        && $radioProperties['behavior'] === NativeBehavior::Radio->value
        && $semanticRadio->properties()[PropKey::Value->value] === 'pro'
        && $semanticRadio->properties()[PropKey::Checked->value] === true,
    'RadioGroup must inherit controlled selection and preserve item and group callbacks.',
);
$defaultRadioGroup = RadioGroup::make(
    ['defaultValue' => 'starter'],
    Radio::make(['value' => 'starter']),
)->toElement();
$defaultRadio = $defaultRadioGroup->children()[0] ?? null;
$defaultRadioNative = $defaultRadio?->properties()[PropKey::HostProperties->value] ?? null;
if (!$defaultRadioNative instanceof BinaryValue) {
    throw new RuntimeException('Uncontrolled Radio must provide packed native state.');
}
$assert(
    Wire::decodeMap($defaultRadioNative->bytes)['checked'] === true
        && $semanticRadioIndicator->properties()[PropKey::Value->value]
            === 'pam:selection-indicator'
        && $semanticRadioIcon->properties()[PropKey::Value->value]
            === 'pam:selection-icon',
    'RadioGroup defaultValue and native indicator anatomy must be preserved.',
);

$selectedFeatures = [];
$checkboxGroup = CheckboxGroup::make(
    ['value' => ['camera']],
    Checkbox::make(
        ['value' => 'location'],
        CheckboxIndicator::make(CheckboxIcon::make()),
        CheckboxLabel::make('Location'),
    ),
)->onChange(
    static function (array $values) use (&$selectedFeatures): void {
        $selectedFeatures = $values;
    },
)->toElement();
$semanticCheckbox = $checkboxGroup->children()[0] ?? null;
$semanticCheckboxIndicator = $semanticCheckbox?->children()[0] ?? null;
$semanticCheckboxIcon = $semanticCheckboxIndicator?->children()[0] ?? null;
$semanticCheckboxToggle = $semanticCheckbox?->events()[
    \Pam\Native\EventKind::Toggle->value
] ?? null;
if (
    !$semanticCheckbox instanceof \Pam\Native\Element
    || !$semanticCheckboxIndicator instanceof \Pam\Native\Element
    || !$semanticCheckboxIcon instanceof \Pam\Native\Element
    || !$semanticCheckboxToggle instanceof Closure
) {
    throw new RuntimeException('CheckboxGroup must bind its value set to each checkbox.');
}
$checkboxGroupNative = $checkboxGroup->properties()[PropKey::HostProperties->value] ?? null;
if (!$checkboxGroupNative instanceof BinaryValue) {
    throw new RuntimeException('CheckboxGroup must provide packed native state.');
}
$semanticCheckboxToggle('1');
$assert(
    $selectedFeatures === ['camera', 'location']
        && Wire::decodeMap($checkboxGroupNative->bytes)['behavior']
            === NativeBehavior::CheckboxGroup->value
        && $semanticCheckboxIndicator->properties()[PropKey::Value->value]
            === 'pam:selection-indicator'
        && $semanticCheckboxIcon->properties()[PropKey::Value->value]
            === 'pam:selection-icon',
    'CheckboxGroup must publish the next selected value list without a second bridge event.',
);
$defaultCheckboxGroup = CheckboxGroup::make(
    [
        'defaultValue' => ['camera'],
        'isReadOnly' => true,
    ],
    Checkbox::make(
        ['value' => 'camera', 'isIndeterminate' => true],
        CheckboxIndicator::make(
            CheckboxIcon::make(['forceMount' => true]),
        ),
    ),
)->toElement();
$defaultCheckbox = $defaultCheckboxGroup->children()[0] ?? null;
$defaultCheckboxIndicator = $defaultCheckbox?->children()[0] ?? null;
$defaultCheckboxIcon = $defaultCheckboxIndicator?->children()[0] ?? null;
$defaultCheckboxNative = $defaultCheckbox?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$defaultCheckboxIcon instanceof \Pam\Native\Element
    || !$defaultCheckboxNative instanceof BinaryValue
) {
    throw new RuntimeException('Uncontrolled Checkbox must provide its complete native state.');
}
$defaultCheckboxProperties = Wire::decodeMap($defaultCheckboxNative->bytes);
$assert(
    $defaultCheckboxProperties['checked'] === true
        && $defaultCheckboxProperties['isIndeterminate'] === true
        && $defaultCheckboxProperties['isReadOnly'] === true
        && $defaultCheckboxIcon->properties()[PropKey::Value->value]
            === 'pam:selection-icon-force',
    'Checkbox defaultValue, mixed/read-only state and forceMount must reach Android.',
);

$expandedItems = [];
$accordion = Accordion::make(
    [
        'type' => ComponentMode::Multiple,
        'value' => ['performance'],
    ],
    AccordionItem::make(
        ['value' => 'freedom'],
        AccordionHeader::make(
            AccordionTrigger::make(
                AccordionTitleText::make('Freedom'),
                AccordionIcon::make(),
            ),
        ),
        AccordionContent::make(
            AccordionContentText::make('Build any application.'),
        ),
    ),
)->onChange(static function (array $values) use (&$expandedItems): void {
    $expandedItems = $values;
})->toElement();
$semanticAccordionItem = $accordion->children()[0] ?? null;
$semanticAccordionHeader = $semanticAccordionItem?->children()[0] ?? null;
$semanticAccordionTrigger = $semanticAccordionHeader?->children()[0] ?? null;
$semanticAccordionIcon = $semanticAccordionTrigger?->children()[1] ?? null;
$semanticAccordionContent = $semanticAccordionItem?->children()[1] ?? null;
$semanticAccordionToggle = $semanticAccordionItem?->events()[
    \Pam\Native\EventKind::Toggle->value
] ?? null;
if (
    !$semanticAccordionItem instanceof \Pam\Native\Element
    || !$semanticAccordionTrigger instanceof \Pam\Native\Element
    || !$semanticAccordionIcon instanceof \Pam\Native\Element
    || !$semanticAccordionContent instanceof \Pam\Native\Element
    || !$semanticAccordionToggle instanceof Closure
) {
    throw new RuntimeException('Accordion must compile its complete native anatomy.');
}
$semanticAccordionNative = $semanticAccordionItem->properties()[
    PropKey::HostProperties->value
] ?? null;
$semanticAccordionGroupNative = $accordion->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$semanticAccordionNative instanceof BinaryValue
    || !$semanticAccordionGroupNative instanceof BinaryValue
) {
    throw new RuntimeException('Accordion and AccordionItem must provide packed native state.');
}
$semanticAccordionProperties = Wire::decodeMap($semanticAccordionNative->bytes);
$semanticAccordionGroupProperties = Wire::decodeMap($semanticAccordionGroupNative->bytes);
$semanticAccordionToggle('1');
$assert(
    $expandedItems === ['performance', 'freedom']
        && $semanticAccordionGroupProperties['behavior']
            === NativeBehavior::AccordionGroup->value
        && $semanticAccordionGroupProperties['type'] === ComponentMode::Multiple->value
        && $semanticAccordionProperties['behavior'] === NativeBehavior::Accordion->value
        && $semanticAccordionProperties['expanded'] === false
        && $semanticAccordionTrigger->properties()[PropKey::Value->value]
            === 'pam:accordion-trigger'
        && $semanticAccordionIcon->properties()[PropKey::Value->value]
            === 'pam:accordion-icon'
        && $semanticAccordionContent->properties()[PropKey::Value->value]
            === 'pam:accordion-content',
    'Accordion must preserve its controlled list, anatomy and packed native state.',
);
$defaultAccordion = Accordion::make(
    [
        'defaultValue' => ['freedom'],
        'isCollapsible' => false,
    ],
    AccordionItem::make(['value' => 'freedom']),
)->toElement();
$defaultAccordionItem = $defaultAccordion->children()[0] ?? null;
$defaultAccordionNative = $defaultAccordionItem?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (!$defaultAccordionNative instanceof BinaryValue) {
    throw new RuntimeException('Uncontrolled AccordionItem must provide packed native state.');
}
$defaultAccordionProperties = Wire::decodeMap($defaultAccordionNative->bytes);
$assert(
    $defaultAccordionProperties['expanded'] === true
        && $defaultAccordionProperties['isCollapsible'] === false,
    'Accordion defaultValue and non-collapsible state must reach the native group.',
);

$selectedMenuItem = null;
$menu = Menu::make(
    ['selectedKeys' => ['settings']],
    MenuItem::make([
        'key' => 'settings',
        'textValue' => 'Settings',
    ]),
)->onChange(static function (string $value) use (&$selectedMenuItem): void {
    $selectedMenuItem = $value;
})->toElement();
$semanticMenuItem = $menu->children()[0] ?? null;
$semanticMenuPress = $semanticMenuItem?->events()[
    \Pam\Native\EventKind::Press->value
] ?? null;
if (
    !$semanticMenuItem instanceof \Pam\Native\Element
    || !$semanticMenuPress instanceof Closure
) {
    throw new RuntimeException('Menu must bind its collection selection to each item.');
}
$semanticMenuPress();
$assert(
    $selectedMenuItem === 'settings'
        && $semanticMenuItem->properties()[PropKey::Selected->value] === true,
    'MenuItem must inherit selected keys and publish its key through the root handler.',
);

$closedPopover = Popover::make(
    ['open' => false],
    Button::make('Open'),
    PopoverContent::make('Details'),
)->toElement();
$closedPopoverTrigger = $closedPopover->children()[0] ?? null;
$closedPopoverContent = $closedPopover->children()[1] ?? null;
if (
    !$closedPopoverTrigger instanceof \Pam\Native\Element
    || !$closedPopoverContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Popover must render both trigger and content.');
}
$assert(
    !isset($closedPopoverTrigger->properties()[PropKey::Visible->value])
        && $closedPopoverContent->properties()[PropKey::Visible->value] === false,
    'A closed Popover must never remove its trigger from the main tree.',
);

$advanced = TailwindStyleCompiler::compile(
    [
        'absolute right-0 bottom-1 aspect-square w-[60%] pt-4 -mt-1 '
        .'border-b rounded-t-xl underline line-through italic uppercase pointer-events-none',
    ],
    [],
    Themes::light(),
)->properties();
$assert(
    $advanced[PropKey::PositionType->value] === PositionType::Absolute->value
        && $advanced[PropKey::Right->value] === 0.0
        && $advanced[PropKey::Bottom->value] === 4.0,
    'Absolute positioning utilities must compile to numeric native layout properties.',
);
$assert(
    $advanced[PropKey::WidthPercent->value] === 60.0
        && $advanced[PropKey::AspectRatio->value] === 1.0,
    'Percentage and aspect-ratio recipes must stay native and resolution independent.',
);
$assert(
    $advanced[PropKey::PaddingTop->value] === 16.0
        && $advanced[PropKey::MarginTop->value] === -4.0
        && $advanced[PropKey::BorderBottomWidth->value] === 1.0,
    'Per-side spacing and borders must preserve the upstream recipes.',
);
$assert(
    $advanced[PropKey::BorderTopLeftRadius->value] === 12.0
        && $advanced[PropKey::BorderTopRightRadius->value] === 12.0,
    'Directional radius utilities must compile without broadening every corner.',
);
$assert(
    $advanced[PropKey::TextDecoration->value] === TextDecoration::UnderlineLineThrough->value
        && $advanced[PropKey::FontStyle->value] === FontStyle::Italic->value
        && $advanced[PropKey::TextTransform->value] === TextTransform::Uppercase->value,
    'Text decoration, style, and transform must be retained by the native renderer.',
);
$assert(
    $advanced[PropKey::PointerEvents->value] === PointerEvents::None->value,
    'Pointer event recipes must compile to the native interaction policy enum.',
);
$negativeSpace = TailwindStyleCompiler::compile(
    ['-space-x-2'],
    [],
    Themes::light(),
)->properties();
$assert(
    $negativeSpace[PropKey::Gap->value] === -8.0
        && $negativeSpace[PropKey::FlexDirection->value] === FlexDirection::Row->value,
    'Negative horizontal spacing utilities must compile to native row overlap.',
);
$nativeEffects = TailwindStyleCompiler::compile(
    [
        'placeholder:text-muted-foreground fill-primary scale-[0.75] '
        .'duration-100 truncate ring-2 ring-ring/50 pb-safe backdrop-blur-sm font-roboto',
    ],
    [],
    Themes::light(),
)->properties();
$assert(
    $nativeEffects[PropKey::PlaceholderColor->value]
        === Themes::light()->color(ColorToken::MutedForeground)
        && $nativeEffects[PropKey::TintColor->value]
            === Themes::light()->color(ColorToken::Primary),
    'Placeholder and vector fill colors must compile to their native paint properties.',
);
$assert(
    $nativeEffects[PropKey::ScaleX->value] === 0.75
        && $nativeEffects[PropKey::ScaleY->value] === 0.75
        && $nativeEffects[PropKey::AnimationDurationMs->value] === 100
        && $nativeEffects[PropKey::AnimateChanges->value] === true,
    'Recipe transforms and durations must run through Android property animation.',
);
$assert(
    $nativeEffects[PropKey::NumberOfLines->value] === 1
        && $nativeEffects[PropKey::Overflow->value] === \Pam\Native\Overflow::Hidden->value,
    'Truncation must remain a local native text and clipping operation.',
);
$assert(
    $nativeEffects[PropKey::SafeAreaBottom->value] === true
        && $nativeEffects[PropKey::BlurRadius->value] === 4.0
        && $nativeEffects[PropKey::FontFamily->value] === 'Roboto',
    'Safe-area, GPU blur, and font recipes must have explicit native paths.',
);

TemplateRegistry::reset();
(new MobileUiPluginProvider())->register();
$factory = TemplateRegistry::factory('Button');
if ($factory === null) {
    throw new RuntimeException('The exact Button tag must be registered.');
}
$tagButton = $factory(
    ['variant' => 'outline'],
    [Text::make('Tagged')],
    null,
)->toElement();
$assert(
    $tagButton->kind() === NodeKind::Pressable,
    'PAM Mobile UI tags must override the core tag through the plugin registry.',
);
$templateButton = TemplateRenderer::render(
    TemplateCompiler::compile(
        '<Button variant="outline" size="lg"><ButtonText>Tagged</ButtonText></Button>',
    ),
    null,
    [],
);
$templateButtonText = $templateButton->children()[0] ?? null;
if (!$templateButtonText instanceof \Pam\Native\Element) {
    throw new RuntimeException('Template button text must render.');
}
$assert(
    $templateButtonText->properties()[PropKey::TextColor->value]
        === Themes::light()->color(ColorToken::Foreground),
    'Tag syntax must propagate parent variants to styled anatomy children.',
);
$templateTabs = TemplateRenderer::render(
    TemplateCompiler::compile(
        <<<'PAM'
<Tabs defaultValue="account" activationMode="manual">
    <TabsList>
        <TabsTrigger value="account"><TabsTriggerText>Account</TabsTriggerText></TabsTrigger>
        <TabsTrigger value="security"><TabsTriggerText>Security</TabsTriggerText></TabsTrigger>
        <TabsIndicator />
    </TabsList>
    <TabsContentWrapper>
        <TabsContent value="account"><Text>Account content</Text></TabsContent>
        <TabsContent value="security"><Text>Security content</Text></TabsContent>
    </TabsContentWrapper>
</Tabs>
PAM,
    ),
    null,
    [],
);
$templateTabsProperties = $templateTabs->properties()[
    PropKey::HostProperties->value
] ?? null;
$templateTabsList = $templateTabs->children()[0] ?? null;
$templateTabsTrigger = $templateTabsList?->children()[0] ?? null;
$templateTabsWrapper = $templateTabs->children()[1] ?? null;
$templateTabsInactiveContent = $templateTabsWrapper?->children()[1] ?? null;
$templateTabsTriggerProperties = $templateTabsTrigger?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$templateTabsProperties instanceof BinaryValue
    || !$templateTabsTriggerProperties instanceof BinaryValue
    || !$templateTabsInactiveContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Tag Tabs must compile its native anatomy.');
}
$templateTabsNative = Wire::decodeMap($templateTabsProperties->bytes);
$templateTabsTriggerNative = Wire::decodeMap($templateTabsTriggerProperties->bytes);
$assert(
    $templateTabsNative['activationMode'] === TabsActivationMode::Manual->value
        && $templateTabsNative['defaultValue'] === 'account'
        && $templateTabsTriggerNative['behavior'] === NativeBehavior::TabsTrigger->value
        && $templateTabsTriggerNative['selected'] === true
        && $templateTabsInactiveContent->properties()[PropKey::Visible->value] === false,
    'Tag Tabs must normalize activation mode and uncontrolled state to integer native values.',
);
$templateRange = TemplateRenderer::render(
    TemplateCompiler::compile(
        <<<'PAM'
<VStack>
    <Slider defaultValue="30" minValue="10" maxValue="90" step="5">
        <SliderTrack>
            <SliderFilledTrack class="bg-emerald-600" />
        </SliderTrack>
        <SliderThumb />
    </Slider>
    <Progress value="40" orientation="vertical">
        <ProgressFilledTrack />
    </Progress>
    <Switch
        defaultValue="true"
        trackOffColor="#d4d4d4"
        trackOnColor="#525252"
        thumbColor="#fafafa"
    />
</VStack>
PAM,
    ),
    null,
    [],
);
$templateSlider = $templateRange->children()[0] ?? null;
$templateProgress = $templateRange->children()[1] ?? null;
$templateSwitch = $templateRange->children()[2] ?? null;
$templateSliderNative = $templateSlider?->properties()[
    PropKey::HostProperties->value
] ?? null;
$templateProgressNative = $templateProgress?->properties()[
    PropKey::HostProperties->value
] ?? null;
$templateSwitchNative = $templateSwitch?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$templateSliderNative instanceof BinaryValue
    || !$templateProgressNative instanceof BinaryValue
    || !$templateSwitchNative instanceof BinaryValue
) {
    throw new RuntimeException('Range and switch tag syntax must render native hosts.');
}
$templateSliderProperties = Wire::decodeMap($templateSliderNative->bytes);
$templateProgressProperties = Wire::decodeMap($templateProgressNative->bytes);
$templateSwitchProperties = Wire::decodeMap($templateSwitchNative->bytes);
$assert(
    $templateSliderProperties['value'] === 30.0
        && $templateSliderProperties['min'] === 10.0
        && $templateSliderProperties['max'] === 90.0
        && $templateSliderProperties['fillColor'] === 0xff059669
        && $templateProgressProperties['orientation']
            === Orientation::Vertical->value
        && $templateSwitchProperties['checked'] === true
        && $templateSwitchProperties['trackOffColor'] === 0xffd4d4d4
        && $templateSwitchProperties['trackOnColor'] === 0xff525252,
    'Tag users must receive the same packed native range and switch contract.',
);
$halfWidthClass = TemplateRegistry::classProperties('w-1/2');
$assert(
    $halfWidthClass !== null
        && $halfWidthClass[PropKey::WidthPercent->value] === 50.0,
    'PAM Mobile UI must compile application utility classes through the plugin registry.',
);
$assert(
    TemplateRegistry::classProperties('unsupported-community-utility') === null,
    'Unsupported application classes must remain explicit instead of silently disappearing.',
);

require dirname(__DIR__).'/examples/kitchen-sink/src/Catalog.php';
$catalogSource = file_get_contents(
    dirname(__DIR__).'/examples/kitchen-sink/resources/native/catalog.pam',
);
if ($catalogSource === false) {
    throw new RuntimeException('The kitchen-sink catalog template must be readable.');
}
$catalog = TemplateRenderer::render(
    TemplateCompiler::compile($catalogSource),
    new \App\Catalog(),
    [],
);
$assert(
    $catalog->kind() === NodeKind::SafeAreaView
        && count($catalog->children()) === 4,
    'The kitchen sink must compile every showcase section and its native overlay hosts.',
);

echo "PAM Mobile UI PHP tests passed.\n";
