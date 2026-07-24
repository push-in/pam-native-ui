<?php

declare(strict_types=1);

use Pam\MobileUi\Component\Button;
use Pam\MobileUi\Component\ButtonText;
use Pam\MobileUi\Component\BottomSheet;
use Pam\MobileUi\Component\BottomSheetPortal;
use Pam\MobileUi\Component\CheckIcon;
use Pam\MobileUi\Component\CalendarGrid;
use Pam\MobileUi\Component\Drawer;
use Pam\MobileUi\Component\DrawerContent;
use Pam\MobileUi\Component\HStack;
use Pam\MobileUi\Component\Attachment;
use Pam\MobileUi\Component\AttachmentPreview;
use Pam\MobileUi\Component\Attachments;
use Pam\MobileUi\Component\Message;
use Pam\MobileUi\Component\MessageContent;
use Pam\MobileUi\Component\ModelSelector;
use Pam\MobileUi\Component\ModelSelectorContent;
use Pam\MobileUi\Component\Popover;
use Pam\MobileUi\Component\PopoverContent;
use Pam\MobileUi\Component\PromptInput;
use Pam\MobileUi\Component\PromptInputTextarea;
use Pam\MobileUi\Component\Select;
use Pam\MobileUi\Component\SelectPortal;
use Pam\MobileUi\Component\SelectTrigger;
use Pam\MobileUi\Component\Slider;
use Pam\MobileUi\Component\SliderTrack;
use Pam\MobileUi\Component\Tabs;
use Pam\MobileUi\Component\TabsContent;
use Pam\MobileUi\Component\TabsList;
use Pam\MobileUi\Component\TabsTrigger;
use Pam\MobileUi\Component\Toast;
use Pam\MobileUi\Component\ToastTitle;
use Pam\MobileUi\Component\SwitchControl;
use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentCategory;
use Pam\MobileUi\Enum\ComponentMaturity;
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

$slider = Slider::make(['value' => 40.0])
    ->onChange(static function (): void {
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
    ['value' => 2],
    TabsList::make(
        TabsTrigger::make(['value' => 'account']),
        TabsTrigger::make(['value' => 'security']),
    ),
    TabsContent::make(['value' => 1], Text::make('First')),
    TabsContent::make(['value' => 2], Text::make('Second')),
)->toElement();
$tabsList = $tabs->children()[0] ?? null;
$firstTabContent = $tabs->children()[1] ?? null;
$secondTabContent = $tabs->children()[2] ?? null;
if (
    !$tabsList instanceof \Pam\Native\Element
    || !$firstTabContent instanceof \Pam\Native\Element
    || !$secondTabContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Tabs must render controlled content children.');
}
$firstTabsTrigger = $tabsList->children()[0] ?? null;
if (!$firstTabsTrigger instanceof \Pam\Native\Element) {
    throw new RuntimeException('Tabs list must render its semantic triggers.');
}
$assert(
    $firstTabsTrigger->properties()[PropKey::Value->value] === 'account',
    'Tab trigger values must reach Android as scalar semantic tags.',
);
$assert(
    CalendarGrid::make()->toElement()->properties()[PropKey::Value->value]
        === 'pam:calendar-grid',
    'Calendar grid geometry must be discoverable without intercepting header controls.',
);
$assert(
    $firstTabContent->properties()[PropKey::Visible->value] === false
        && !isset($secondTabContent->properties()[PropKey::Visible->value]),
    'Tabs must hide inactive panels without a PHP callback on every frame.',
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
        && isset($modelSelectorHost->events()[\Pam\Native\EventKind::Native->value]),
    'ModelSelector events must cross from its stable root into the native modal host.',
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
        && $closedSelectHost->kind() === NodeKind::CustomView
        && isset($closedSelectHost->events()[\Pam\Native\EventKind::Native->value]),
    'Select must forward semantic events into its native sheet host.',
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
