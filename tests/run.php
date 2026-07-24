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
use Pam\MobileUi\Component\Actionsheet;
use Pam\MobileUi\Component\ActionsheetFlatList;
use Pam\MobileUi\Component\ActionsheetSectionList;
use Pam\MobileUi\Component\ActionsheetVirtualizedList;
use Pam\MobileUi\Component\AlertDialog;
use Pam\MobileUi\Component\BottomSheet;
use Pam\MobileUi\Component\BottomSheetBackdrop;
use Pam\MobileUi\Component\BottomSheetContent;
use Pam\MobileUi\Component\BottomSheetDragIndicator;
use Pam\MobileUi\Component\BottomSheetFlatList;
use Pam\MobileUi\Component\BottomSheetItem;
use Pam\MobileUi\Component\BottomSheetPortal;
use Pam\MobileUi\Component\BottomSheetSectionList;
use Pam\MobileUi\Component\BottomSheetTrigger;
use Pam\MobileUi\Component\BlankContext;
use Pam\MobileUi\Component\BlankProvider;
use Pam\MobileUi\Component\Checkbox;
use Pam\MobileUi\Component\CheckboxGroup;
use Pam\MobileUi\Component\CheckboxIcon;
use Pam\MobileUi\Component\CheckboxIndicator;
use Pam\MobileUi\Component\CheckboxLabel;
use Pam\MobileUi\Component\CheckIcon;
use Pam\MobileUi\Component\Calendar;
use Pam\MobileUi\Component\CalendarDay;
use Pam\MobileUi\Component\CalendarGrid;
use Pam\MobileUi\Component\CalendarHeader;
use Pam\MobileUi\Component\CalendarHeaderMonthSelect;
use Pam\MobileUi\Component\CalendarHeaderNextButton;
use Pam\MobileUi\Component\CalendarHeaderPrevButton;
use Pam\MobileUi\Component\CalendarHeaderTitle;
use Pam\MobileUi\Component\CalendarHeaderYearSelect;
use Pam\MobileUi\Component\CalendarWeekDay;
use Pam\MobileUi\Component\CalendarWeekNumber;
use Pam\MobileUi\Component\Drawer;
use Pam\MobileUi\Component\DrawerContent;
use Pam\MobileUi\Component\DateTimePicker;
use Pam\MobileUi\Component\DateTimePickerInput;
use Pam\MobileUi\Component\DateTimePickerTrigger;
use Pam\MobileUi\Component\Grid;
use Pam\MobileUi\Component\GridItem;
use Pam\MobileUi\Component\GluestackUIProvider;
use Pam\MobileUi\Component\HStack;
use Pam\MobileUi\Component\Heading;
use Pam\MobileUi\Component\FormControl;
use Pam\MobileUi\Component\FormControlError;
use Pam\MobileUi\Component\FormControlErrorText;
use Pam\MobileUi\Component\FormControlHelper;
use Pam\MobileUi\Component\FormControlHelperText;
use Pam\MobileUi\Component\FormControlLabel;
use Pam\MobileUi\Component\FormControlLabelText;
use Pam\MobileUi\Component\Input;
use Pam\MobileUi\Component\InputField;
use Pam\MobileUi\Component\InputSlot;
use Pam\MobileUi\Component\Image;
use Pam\MobileUi\Component\ImageBackground;
use Pam\MobileUi\Component\ImageViewer;
use Pam\MobileUi\Component\ImageViewerCloseButton;
use Pam\MobileUi\Component\ImageViewerContent;
use Pam\MobileUi\Component\ImageViewerCounter;
use Pam\MobileUi\Component\ImageViewerNavigation;
use Pam\MobileUi\Component\ImageViewerTrigger;
use Pam\MobileUi\Component\KeyboardAvoidingView;
use Pam\MobileUi\Component\Link;
use Pam\MobileUi\Component\RefreshControl;
use Pam\MobileUi\Component\FlatList;
use Pam\MobileUi\Component\SectionList;
use Pam\MobileUi\Component\VirtualizedList;
use Pam\MobileUi\Component\Table;
use Pam\MobileUi\Component\TableBody;
use Pam\MobileUi\Component\TableData;
use Pam\MobileUi\Component\TableHead;
use Pam\MobileUi\Component\TableHeader;
use Pam\MobileUi\Component\TableRow;
use Pam\MobileUi\Component\Attachment;
use Pam\MobileUi\Component\AttachmentEmpty;
use Pam\MobileUi\Component\AttachmentHoverCard;
use Pam\MobileUi\Component\AttachmentHoverCardContent;
use Pam\MobileUi\Component\AttachmentPreview;
use Pam\MobileUi\Component\AttachmentRemove;
use Pam\MobileUi\Component\Attachments;
use Pam\MobileUi\Component\AvatarFallback;
use Pam\MobileUi\Component\AvatarImage;
use Pam\MobileUi\Component\Conversation;
use Pam\MobileUi\Component\ConversationContent;
use Pam\MobileUi\Component\ConversationScrollButton;
use Pam\MobileUi\Component\FileTree;
use Pam\MobileUi\Component\FileTreeFile;
use Pam\MobileUi\Component\FileTreeFolder;
use Pam\MobileUi\Component\Message;
use Pam\MobileUi\Component\MessageAction;
use Pam\MobileUi\Component\MessageBranch;
use Pam\MobileUi\Component\MessageBranchContent;
use Pam\MobileUi\Component\MessageBranchNext;
use Pam\MobileUi\Component\MessageBranchPage;
use Pam\MobileUi\Component\MessageBranchPrevious;
use Pam\MobileUi\Component\MessageBranchSelector;
use Pam\MobileUi\Component\MessageContent;
use Pam\MobileUi\Component\MessageResponse;
use Pam\MobileUi\Component\MessageToolbar;
use Pam\MobileUi\Component\Menu;
use Pam\MobileUi\Component\MenuItem;
use Pam\MobileUi\Component\Modal;
use Pam\MobileUi\Component\ModelSelector;
use Pam\MobileUi\Component\ModelSelectorContent;
use Pam\MobileUi\Component\ModelSelectorGroup;
use Pam\MobileUi\Component\ModelSelectorInput;
use Pam\MobileUi\Component\ModelSelectorItem;
use Pam\MobileUi\Component\ModelSelectorList;
use Pam\MobileUi\Component\ModelSelectorLogo;
use Pam\MobileUi\Component\ModelSelectorName;
use Pam\MobileUi\Component\ModelSelectorTrigger;
use Pam\MobileUi\Component\Popover;
use Pam\MobileUi\Component\PopoverArrow;
use Pam\MobileUi\Component\PopoverCloseButton;
use Pam\MobileUi\Component\PopoverContent;
use Pam\MobileUi\Component\Pressable;
use Pam\MobileUi\Component\PromptInput;
use Pam\MobileUi\Component\PromptInputProvider;
use Pam\MobileUi\Component\PromptInputActionMenu;
use Pam\MobileUi\Component\PromptInputActionMenuContent;
use Pam\MobileUi\Component\PromptInputActionMenuTrigger;
use Pam\MobileUi\Component\PromptInputBody;
use Pam\MobileUi\Component\PromptInputButton;
use Pam\MobileUi\Component\PromptInputSubmit;
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
use Pam\MobileUi\Component\SelectFlatList;
use Pam\MobileUi\Component\SelectInput;
use Pam\MobileUi\Component\SelectItem;
use Pam\MobileUi\Component\SelectPortal;
use Pam\MobileUi\Component\SelectSectionList;
use Pam\MobileUi\Component\SelectTrigger;
use Pam\MobileUi\Component\SelectVirtualizedList;
use Pam\MobileUi\Component\Slider;
use Pam\MobileUi\Component\SliderFilledTrack;
use Pam\MobileUi\Component\SliderThumb;
use Pam\MobileUi\Component\SliderTrack;
use Pam\MobileUi\Component\Skeleton;
use Pam\MobileUi\Component\SkeletonText;
use Pam\MobileUi\Component\Spinner;
use Pam\MobileUi\Component\StatusBar;
use Pam\MobileUi\Component\ScrollView;
use Pam\MobileUi\Component\SafeAreaView;
use Pam\MobileUi\Component\Tabs;
use Pam\MobileUi\Component\TabsContent;
use Pam\MobileUi\Component\TabsContentWrapper;
use Pam\MobileUi\Component\TabsIndicator;
use Pam\MobileUi\Component\TabsList;
use Pam\MobileUi\Component\TabsTrigger;
use Pam\MobileUi\Component\Toast;
use Pam\MobileUi\Component\ToastTitle;
use Pam\MobileUi\Component\Tooltip;
use Pam\MobileUi\Component\TooltipContent;
use Pam\MobileUi\Component\SwitchControl;
use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Enum\BackdropPressBehavior;
use Pam\MobileUi\Enum\AttachmentType;
use Pam\MobileUi\Enum\BranchControlAction;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentCategory;
use Pam\MobileUi\Enum\ComponentMaturity;
use Pam\MobileUi\Enum\ComponentMode;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\ComponentState;
use Pam\MobileUi\Enum\ComponentType;
use Pam\MobileUi\Enum\ComponentVariant;
use Pam\MobileUi\Enum\DrawerAnchor;
use Pam\MobileUi\Enum\FileTreeAction;
use Pam\MobileUi\Enum\ImplementationKind;
use Pam\MobileUi\Enum\ImageViewerControlAction;
use Pam\MobileUi\Enum\InputSlotAction;
use Pam\MobileUi\Enum\MessageRole;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\Orientation;
use Pam\MobileUi\Enum\ParityGate;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Enum\PrimitiveKind;
use Pam\MobileUi\Enum\SelectionMode;
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
use Pam\Native\AccessibilityRole;
use Pam\Native\AccessibilityCheckedState;
use Pam\Native\AccessibilityImportance;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\FontStyle;
use Pam\Native\Align;
use Pam\Native\Internal\TemplateCompiler;
use Pam\Native\Internal\TemplateRenderer;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\ModalPresentation;
use Pam\Native\ImageFit;
use Pam\Native\KeyboardAvoidingBehavior;
use Pam\Native\NodeKind;
use Pam\Native\PointerEvents;
use Pam\Native\PositionType;
use Pam\Native\PropKey;
use Pam\Native\RefreshIndicatorSize;
use Pam\Native\ReturnKeyType;
use Pam\Native\SafeAreaMode;
use Pam\Native\StatusBarAppearance;
use Pam\Native\TemplateRegistry;
use Pam\Native\TextBreakStrategy;
use Pam\Native\TextDataDetectorType;
use Pam\Native\TextDecoration;
use Pam\Native\TextEllipsizeMode;
use Pam\Native\TextHyphenationFrequency;
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
    AttachmentType::cases(),
    PrimitiveKind::cases(),
    ThemeMode::cases(),
    ColorToken::cases(),
    ComponentSize::cases(),
    ComponentState::cases(),
    ComponentType::cases(),
    ComponentVariant::cases(),
    DrawerAnchor::cases(),
    BranchControlAction::cases(),
    FileTreeAction::cases(),
    ImageViewerControlAction::cases(),
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
$themedProvider = GluestackUIProvider::make(
    ['mode' => 'dark'],
    Button::make('Scoped theme'),
)->toElement();
$themedButton = $themedProvider->children()[0] ?? null;
$blankChild = BlankProvider::make(Text::make('Blank'))->toElement();
$blankContextChild = BlankContext::make(Text::make('Context'))->toElement();
$promptProvider = PromptInputProvider::make(
    null,
    Text::make('One'),
    Text::make('Two'),
)->toElement();
$assert(
    $themedProvider->kind() === NodeKind::View
        && $themedProvider->properties()[PropKey::WidthPercent->value] === 100.0
        && $themedProvider->properties()[PropKey::HeightPercent->value] === 100.0
        && $themedProvider->properties()[PropKey::FlexGrow->value] === 1.0
        && $themedButton?->properties()[PropKey::BackgroundColor->value]
            === Themes::dark()->color(ColorToken::Primary)
        && ThemeManager::configuredMode() === ThemeMode::Light
        && $blankChild->kind() === NodeKind::Text
        && $blankContextChild->kind() === NodeKind::Text
        && $promptProvider->kind() === NodeKind::View
        && $promptProvider->properties()[PropKey::Collapsable->value] === true
        && count($promptProvider->children()) === 2,
    'Providers must scope theme rendering and avoid unnecessary native layout hosts.',
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
        && $drawer->properties()[PropKey::Visible->value] === false
        && $drawerHost->kind() === NodeKind::CustomView
        && $drawer->properties()[PropKey::ModalPresentation->value]
            === ModalPresentation::FullScreen->value
        && $drawerContent->properties()[PropKey::WidthPercent->value] === 50.0
        && $drawerContent->properties()[PropKey::HeightPercent->value] === 100.0,
    'Drawer must use a native full-screen window and preserve its compound dimensions.',
);
$defaultClosedOverlays = [
    Actionsheet::make()->toElement(),
    AlertDialog::make()->toElement(),
    Modal::make()->toElement(),
];
foreach ($defaultClosedOverlays as $defaultClosedOverlay) {
    $assert(
        $defaultClosedOverlay->kind() === NodeKind::Modal
            && $defaultClosedOverlay->properties()[PropKey::Visible->value]
                === false,
        'Standalone overlays must follow the upstream default-closed contract.',
    );
}

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

$loadedSkeleton = Skeleton::make(
    ['isLoaded' => true],
    Text::make('Loaded content'),
)->toElement();
$loadingSkeleton = Skeleton::make(
    ['isLoaded' => false, 'speed' => SkeletonSpeed::Fast],
    Text::make('Hidden while loading'),
)->toElement();
$skeletonLines = SkeletonText::make([
    '_lines' => 3,
    'gap' => 3,
    'speed' => SkeletonSpeed::VeryFast,
])->toElement();
$loadingSkeletonNative = $loadingSkeleton->properties()[
    PropKey::HostProperties->value
] ?? null;
$skeletonLinesNative = $skeletonLines->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$loadingSkeletonNative instanceof BinaryValue
    || !$skeletonLinesNative instanceof BinaryValue
) {
    throw new RuntimeException('Skeletons must compile their native pulse state.');
}
$loadingSkeletonProperties = Wire::decodeMap($loadingSkeletonNative->bytes);
$skeletonLinesProperties = Wire::decodeMap($skeletonLinesNative->bytes);
$assert(
    $loadedSkeleton->kind() === NodeKind::Text
        && $loadedSkeleton->properties()[PropKey::Text->value] === 'Loaded content'
        && $loadingSkeleton->children() === []
        && $loadingSkeletonProperties['pulseDuration'] === 1_500
        && count($skeletonLines->children()) === 3
        && $skeletonLinesProperties['pulseDuration'] === 2_000
        && $skeletonLinesProperties['lines'] === 3
        && $skeletonLines->children()[2]
            ->properties()[PropKey::WidthPercent->value] === 80.0,
    'Skeleton loading, loaded content, line count, gap and speed must match upstream behavior.',
);

$singleSkeletonLine = SkeletonText::make()->toElement();
$singleSkeletonNative = $singleSkeletonLine->properties()[
    PropKey::HostProperties->value
] ?? null;
if (!$singleSkeletonNative instanceof BinaryValue) {
    throw new RuntimeException('Single-line SkeletonText must keep its pulse host.');
}
$imagePrimitive = Image::make([
    'source' => 'file:///cover.png',
    'resizeMode' => 'contain',
    'tintColor' => '#2563eb',
])->toElement();
$imageBackground = ImageBackground::make(
    ['source' => 'file:///background.png', 'resizeMode' => 'center'],
    Text::make('Overlay'),
)->toElement();
$reactNativeImage = AvatarImage::make([
    'source' => ['uri' => 'https://example.test/david.png'],
    'alt' => 'David Balbino',
])->toElement();
$avatarFallback = AvatarFallback::make('db')->toElement();
$spinnerPrimitive = Spinner::make(['color' => '#dc2626'])->toElement();
$stoppedSpinner = Spinner::make(['animating' => false])->toElement();
$promptButton = PromptInputButton::make('Attach')->toElement();
$scrollPrimitive = ScrollView::make(
    [
        'scrollEnabled' => false,
        'showsVerticalScrollIndicator' => false,
    ],
    Text::make('Scrollable'),
)->toElement();
$keyboardPrimitive = KeyboardAvoidingView::make(
    [
        'behavior' => 'padding',
        'keyboardVerticalOffset' => 24,
        'enabled' => false,
    ],
    Text::make('Form'),
)->toElement();
$safeAreaPrimitive = SafeAreaView::make(
    [
        'edges' => ['top', 'bottom'],
        'mode' => 'margin',
    ],
    Text::make('Safe content'),
)->toElement();
$refreshPrimitive = RefreshControl::make(
    [
        'refreshing' => true,
        'colors' => ['#112233', 'rgba(68, 85, 102, 0.75)'],
        'progressBackgroundColor' => '#f8fafc',
        'progressViewOffset' => 16,
        'enabled' => false,
        'size' => 'large',
    ],
    Text::make('Refresh content'),
)->toElement();
$nativeTextPrimitive = \Pam\MobileUi\Component\Text::make([
    'text' => 'Selectable https://pam.dev',
    'numberOfLines' => 2,
    'selectable' => true,
    'selectionColor' => '#11223366',
    'ellipsizeMode' => 'middle',
    'allowFontScaling' => true,
    'maxFontSizeMultiplier' => 1.5,
    'adjustsFontSizeToFit' => true,
    'minimumFontScale' => 0.5,
    'textBreakStrategy' => 'balanced',
    'android_hyphenationFrequency' => 'full',
    'dataDetectorType' => 'link',
])->toElement();
$statusPrimitive = StatusBar::make([
    'backgroundColor' => '#171717',
    'barStyle' => 'light-content',
    'hidden' => true,
    'animated' => true,
    'translucent' => true,
])->toElement();
$nativeInputProps = InputField::make([
    'placeholderTextColor' => 'rgba(255, 255, 255, 0.4)',
    'selectionColor' => '#11223344',
    'returnKeyType' => 'search',
    'pointerEvents' => 'box-none',
    'collapsable' => false,
])->toElement();
$nativePressableProps = Pressable::make(
    [
        'android_ripple' => ['color' => '#01020380'],
        'pressedOpacity' => 0.5,
        'hitSlop' => [
            'top' => 4,
            'right' => 8,
            'bottom' => 12,
            'left' => 6,
        ],
    ],
    Text::make('Native pressable'),
)->toElement();
$headingSemantics = Heading::make('Account')->toElement();
$linkSemantics = Link::make('Open documentation')->toElement();
$checkboxSemantics = Checkbox::make(['value' => 'terms'])->toElement();
$radioSemantics = Radio::make(['value' => 'monthly'])->toElement();
$progressSemantics = Progress::make(['value' => 40])->toElement();
$listSemantics = FlatList::make(['items' => ['One', 'Two']])->toElement();
$tabListSemantics = TabsList::make()->toElement();
$tabSemantics = TabsTrigger::make(['value' => 'profile'])->toElement();
$rolePrecedence = Pressable::make(
    [
        'accessibilityRole' => 'button',
        'role' => 'link',
    ],
    Text::make('Role precedence'),
)->toElement();
$mixedCheckboxSemantics = Checkbox::make([
    'ariaChecked' => 'mixed',
    'ariaLive' => 'polite',
    'importantForAccessibility' => 'yes',
])->toElement();
$busyButtonSemantics = Button::make(
    [
        'accessibilityState' => [
            'busy' => true,
            'disabled' => true,
        ],
    ],
    ButtonText::make('Saving'),
)->toElement();
$expandedTriggerSemantics = AccordionTrigger::make([
    'ariaExpanded' => true,
])->toElement();
$hiddenSemantics = Pressable::make(
    ['ariaHidden' => true],
    Text::make('Decorative'),
)->toElement();
$fluentRangeSemantics = Progress::make()
    ->accessible()
    ->accessibilityLiveRegion(AccessibilityLiveRegion::Assertive)
    ->importantForAccessibility(AccessibilityImportance::Yes)
    ->accessibilityValue(0.0, 1.0, 0.5, 'Half')
    ->toElement();
$assert(
    Wire::decodeMap($singleSkeletonNative->bytes)['behavior']
        === NativeBehavior::Skeleton->value
        && $imagePrimitive->properties()[PropKey::ImageFit->value]
            === ImageFit::Contain->value
        && $imagePrimitive->properties()[PropKey::TintColor->value]
            === 0xff2563eb
        && $imageBackground->properties()[PropKey::ImageFit->value]
            === ImageFit::Center->value
        && $reactNativeImage->kind() === NodeKind::Image
        && $reactNativeImage->properties()[PropKey::Source->value]
            === 'https://example.test/david.png'
        && $reactNativeImage->properties()[PropKey::AccessibilityLabel->value]
            === 'David Balbino'
        && $avatarFallback->kind() === NodeKind::Text
        && $avatarFallback->properties()[PropKey::Text->value] === 'db'
        && $avatarFallback->properties()[PropKey::TextTransform->value]
            === TextTransform::Uppercase->value
        && $spinnerPrimitive->properties()[PropKey::ProgressColor->value]
            === 0xffdc2626
        && $stoppedSpinner->properties()[PropKey::Visible->value] === false
        && $promptButton->kind() === NodeKind::Pressable
        && $promptButton->children()[0]->properties()[PropKey::Text->value]
            === 'Attach'
        && $scrollPrimitive->properties()[PropKey::ScrollEnabled->value] === false
        && $scrollPrimitive->properties()[PropKey::ShowsScrollIndicator->value] === false
        && $keyboardPrimitive->properties()[PropKey::KeyboardBehavior->value]
            === KeyboardAvoidingBehavior::Padding->value
        && $keyboardPrimitive
            ->properties()[PropKey::KeyboardVerticalOffset->value] === 24.0
        && $keyboardPrimitive
            ->properties()[PropKey::KeyboardAvoidingEnabled->value] === false
        && $safeAreaPrimitive->properties()[PropKey::SafeAreaTop->value] === true
        && $safeAreaPrimitive
            ->properties()[PropKey::SafeAreaRight->value] === false
        && $safeAreaPrimitive
            ->properties()[PropKey::SafeAreaBottomEdge->value] === true
        && $safeAreaPrimitive->properties()[PropKey::SafeAreaLeft->value] === false
        && $safeAreaPrimitive->properties()[PropKey::SafeAreaMode->value]
            === SafeAreaMode::Margin->value
        && $refreshPrimitive->properties()[PropKey::Refreshing->value] === true
        && $refreshPrimitive->properties()[PropKey::RefreshColors->value]
            === implode(',', [0xff112233, 0xbf445566])
        && $refreshPrimitive
            ->properties()[PropKey::RefreshProgressBackgroundColor->value]
            === 0xfff8fafc
        && $refreshPrimitive
            ->properties()[PropKey::RefreshProgressViewOffset->value] === 16.0
        && $refreshPrimitive->properties()[PropKey::Enabled->value] === false
        && $refreshPrimitive->properties()[PropKey::RefreshIndicatorSize->value]
            === RefreshIndicatorSize::Large->value
        && $nativeTextPrimitive->properties()[PropKey::NumberOfLines->value] === 2
        && $nativeTextPrimitive->properties()[PropKey::TextSelectable->value] === true
        && $nativeTextPrimitive->properties()[PropKey::SelectionColor->value]
            === 0x66112233
        && $nativeTextPrimitive->properties()[PropKey::TextEllipsizeMode->value]
            === TextEllipsizeMode::Middle->value
        && $nativeTextPrimitive
            ->properties()[PropKey::TextAllowFontScaling->value] === true
        && $nativeTextPrimitive
            ->properties()[PropKey::TextMaxFontSizeMultiplier->value] === 1.5
        && $nativeTextPrimitive
            ->properties()[PropKey::TextAdjustsFontSizeToFit->value] === true
        && $nativeTextPrimitive
            ->properties()[PropKey::TextMinimumFontScale->value] === 0.5
        && $nativeTextPrimitive->properties()[PropKey::TextBreakStrategy->value]
            === TextBreakStrategy::Balanced->value
        && $nativeTextPrimitive
            ->properties()[PropKey::TextHyphenationFrequency->value]
            === TextHyphenationFrequency::Full->value
        && $nativeTextPrimitive
            ->properties()[PropKey::TextDataDetectorType->value]
            === TextDataDetectorType::Link->value
        && $statusPrimitive->properties()[PropKey::StatusBarColor->value]
            === 0xff171717
        && $statusPrimitive->properties()[PropKey::StatusBarStyle->value]
            === StatusBarAppearance::Light->value
        && $statusPrimitive->properties()[PropKey::StatusBarHidden->value] === true
        && $statusPrimitive->properties()[PropKey::StatusBarAnimated->value] === true
        && $statusPrimitive->properties()[PropKey::StatusBarTranslucent->value] === true
        && $nativeInputProps->properties()[PropKey::PlaceholderColor->value]
            === 0x66ffffff
        && $nativeInputProps->properties()[PropKey::SelectionColor->value]
            === 0x44112233
        && $nativeInputProps->properties()[PropKey::ReturnKeyType->value]
            === ReturnKeyType::Search->value
        && $nativeInputProps->properties()[PropKey::PointerEvents->value]
            === PointerEvents::BoxNone->value
        && $nativeInputProps->properties()[PropKey::Collapsable->value] === false
        && $nativePressableProps->properties()[PropKey::RippleColor->value]
            === 0x80010203
        && $nativePressableProps->properties()[PropKey::PressOpacity->value] === 0.5
        && $nativePressableProps->properties()[PropKey::HitSlop->value] === 12
        && $headingSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::Header->value
        && $linkSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::Link->value
        && $checkboxSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::Checkbox->value
        && $radioSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::Radio->value
        && $progressSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::ProgressBar->value
        && $listSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::List->value
        && $tabListSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::TabList->value
        && $tabSemantics->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::Tab->value
        && $rolePrecedence->properties()[PropKey::AccessibilityRole->value]
            === AccessibilityRole::Link->value
        && $mixedCheckboxSemantics
            ->properties()[PropKey::AccessibilityCheckedState->value]
            === AccessibilityCheckedState::Mixed->value
        && $mixedCheckboxSemantics->properties()[PropKey::Checked->value] === false
        && $mixedCheckboxSemantics
            ->properties()[PropKey::AccessibilityLiveRegion->value]
            === AccessibilityLiveRegion::Polite->value
        && $mixedCheckboxSemantics
            ->properties()[PropKey::AccessibilityImportance->value]
            === AccessibilityImportance::Yes->value
        && $busyButtonSemantics
            ->properties()[PropKey::AccessibilityBusy->value] === true
        && $busyButtonSemantics->properties()[PropKey::Enabled->value] === false
        && $expandedTriggerSemantics
            ->properties()[PropKey::AccessibilityExpanded->value] === true
        && $hiddenSemantics
            ->properties()[PropKey::AccessibilityImportance->value]
            === AccessibilityImportance::NoHideDescendants->value
        && $progressSemantics
            ->properties()[PropKey::AccessibilityValueMin->value] === 0.0
        && $progressSemantics
            ->properties()[PropKey::AccessibilityValueMax->value] === 100.0
        && $progressSemantics
            ->properties()[PropKey::AccessibilityValueNow->value] === 40.0
        && $fluentRangeSemantics
            ->properties()[PropKey::Accessible->value] === true
        && $fluentRangeSemantics
            ->properties()[PropKey::AccessibilityLiveRegion->value]
            === AccessibilityLiveRegion::Assertive->value
        && $fluentRangeSemantics
            ->properties()[PropKey::AccessibilityValueText->value] === 'Half',
    'Core facades and aliases must forward image, a11y, spinner, text action, scroll, keyboard and status properties.',
);

$calendarAnatomy = Calendar::make(
    ['year' => 2026, 'month' => 7],
    CalendarHeader::make(
        CalendarHeaderPrevButton::make(),
        CalendarHeaderMonthSelect::make(),
        CalendarHeaderYearSelect::make(),
        CalendarHeaderNextButton::make(),
    ),
)->toElement();
$calendarHeader = $calendarAnatomy->children()[0] ?? null;
$calendarMonth = $calendarHeader?->children()[1] ?? null;
$calendarYear = $calendarHeader?->children()[2] ?? null;
$assert(
    $calendarHeader?->children()[0]->kind() === NodeKind::Pressable
        && $calendarHeader->children()[3]->kind() === NodeKind::Pressable
        && CalendarDay::make()->toElement()->kind() === NodeKind::Pressable
        && CalendarWeekDay::make('Mon')->toElement()->children()[0]
            ->properties()[PropKey::Text->value] === 'Mon'
        && CalendarWeekNumber::make(29)->toElement()->children()[0]
            ->properties()[PropKey::Text->value] === '29'
        && $calendarMonth?->children()[0]
            ->properties()[PropKey::Text->value] === 'July'
        && $calendarYear?->children()[0]
            ->properties()[PropKey::Text->value] === '2026'
        && MessageBranchPage::make()->toElement()->kind() === NodeKind::Text
        && ModelSelectorInput::make()->toElement()->kind() === NodeKind::Input
        && ModelSelectorName::make('PAM')->toElement()->kind() === NodeKind::Text
        && SelectInput::make()->toElement()->kind() === NodeKind::Input
        && SelectInput::make()->toElement()
            ->properties()[PropKey::Enabled->value] === false
        && GridItem::make()->toElement()->kind() === NodeKind::View,
    'Compound anatomy must preserve upstream pressable, text, input and container primitive kinds.',
);

$grid = Grid::make(
    ['className' => 'grid-cols-2 sm:grid-cols-3 gap-x-2 gap-y-3'],
    GridItem::make(
        ['className' => 'col-span-1 sm:col-span-2'],
        Text::make('One'),
    ),
    GridItem::make(
        ['colSpan' => 1],
        Text::make('Two'),
    ),
    GridItem::make(
        ['className' => 'col-span-2 sm:col-span-3'],
        Text::make('Three'),
    ),
)->toElement();
$gridNative = $grid->properties()[PropKey::HostProperties->value] ?? null;
if (!$gridNative instanceof BinaryValue) {
    throw new RuntimeException('Grid must pack responsive rules for Android.');
}
$gridProperties = Wire::decodeMap($gridNative->bytes);
$assert(
    $grid->kind() === NodeKind::CustomView
        && $grid->properties()[PropKey::HostName->value]
            === 'pam.mobile_ui.grid'
        && $gridProperties['columns'] === '2,3,3,3,3,3'
        && $gridProperties['columnGaps'] === '8,8,8,8,8,8'
        && $gridProperties['rowGaps'] === '12,12,12,12,12,12'
        && $gridProperties['direction'] === 2
        && $grid->children()[0]->properties()[PropKey::Value->value]
            === 'pam:grid-item:1,2,2,2,2,2'
        && $grid->children()[2]->properties()[PropKey::Value->value]
            === 'pam:grid-item:2,3,3,3,3,3'
        && $grid->properties()[PropKey::MinHeight->value] === 108.0,
    'Grid must preserve responsive columns, spans and independent native gaps.',
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
        [
            'data' => [
                'type' => 'file',
                'filename' => 'architecture.png',
                'mediaType' => 'image/png',
                'url' => 'file:///architecture.png',
            ],
        ],
        AttachmentPreview::make(),
        AttachmentRemove::make(),
    ),
)->toElement();
$attachmentContent = $attachments->children()[0] ?? null;
$attachment = $attachmentContent?->children()[0] ?? null;
if (!$attachment instanceof \Pam\Native\Element) {
    throw new RuntimeException('Attachment recipes must render their anatomy.');
}
$preview = $attachment->children()[0] ?? null;
$attachmentRemove = $attachment->children()[1] ?? null;
if (
    !$preview instanceof \Pam\Native\Element
    || !$attachmentRemove instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Attachment previews must inherit the parent display variant.');
}
$assert(
    $attachments->properties()[PropKey::FlexDirection->value] === FlexDirection::Column->value
        && $attachmentContent->kind() === NodeKind::Column
        && $attachment->properties()[PropKey::WidthPercent->value] === 100.0
        && $preview->properties()[PropKey::Width->value] === 48.0,
    'Attachment list variants must compile through inherited integer variant context.',
);
$assert(
    $preview->children()[0]->kind() === NodeKind::Image
        && $attachmentRemove->kind() === NodeKind::Pressable
        && MessageAction::make(Text::make('Copy'))->toElement()->kind()
            === NodeKind::Pressable,
    'Chat attachment previews and authored actions must keep their native interaction paths.',
);
$customAttachmentEmpty = AttachmentEmpty::make('Nothing queued')->toElement();
$assert(
    $customAttachmentEmpty->children()[0]
        ->properties()[PropKey::Text->value] === 'Nothing queued',
    'AttachmentEmpty must preserve custom authored fallback content.',
);
$attachmentHover = AttachmentHoverCard::make(
    ['open' => true],
    Button::make(ButtonText::make('Preview')),
    AttachmentHoverCardContent::make(Text::make('architecture.png')),
)->toElement();
$attachmentHoverNative = $attachmentHover->properties()[
    PropKey::HostProperties->value
] ?? null;
$promptActionMenu = PromptInputActionMenu::make(
    ['open' => true],
    PromptInputActionMenuTrigger::make(Text::make('+')),
    PromptInputActionMenuContent::make(Text::make('Select document')),
)->toElement();
$promptActionNative = $promptActionMenu->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$attachmentHoverNative instanceof BinaryValue
    || !$promptActionNative instanceof BinaryValue
) {
    throw new RuntimeException('Chat overlay aliases must compile native hosts.');
}
$attachmentHoverProperties = Wire::decodeMap($attachmentHoverNative->bytes);
$promptActionProperties = Wire::decodeMap($promptActionNative->bytes);
$assert(
    $attachmentHoverProperties['behavior'] === NativeBehavior::Tooltip->value
        && $attachmentHoverProperties['placement'] === Placement::Top->value
        && $attachmentHoverProperties['openDelay'] === 0
        && $attachmentHoverProperties['closeDelay'] === 100
        && $attachmentHover->children()[0]
            ->properties()[PropKey::Value->value] === 'pam:overlay-trigger'
        && $attachmentHover->children()[1]
            ->properties()[PropKey::Value->value] === 'pam:overlay-content'
        && $promptActionProperties['behavior'] === NativeBehavior::Menu->value
        && $promptActionProperties['placement'] === Placement::Top->value
        && $promptActionProperties['offset'] === 5
        && $promptActionMenu->children()[1]
            ->properties()[PropKey::Value->value] === 'pam:overlay-content',
    'Chat hover cards and action menus must preserve their upstream native defaults.',
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

$messageResponse = MessageResponse::make([
    'message' => [
        'parts' => [
            [
                'type' => 'text',
                'text' => '# PAM Native'.PHP_EOL.'**Fast** and native.',
            ],
            [
                'type' => 'file',
                'filename' => 'architecture.png',
                'url' => 'file:///architecture.png',
            ],
        ],
    ],
])->onNativeEvent(static function (string $uri): void {
})->toElement();
$markdownChild = $messageResponse->children()[0] ?? null;
$messageFile = $messageResponse->children()[1] ?? null;
$markdownNative = $markdownChild?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$markdownChild instanceof \Pam\Native\Element
    || !$messageFile instanceof \Pam\Native\Element
    || !$markdownNative instanceof BinaryValue
) {
    throw new RuntimeException('MessageResponse must render text and file parts.');
}
$markdownProperties = Wire::decodeMap($markdownNative->bytes);
$userToolbar = Message::make(
    [
        'role' => MessageRole::User,
        'message' => [
            'parts' => [['type' => 'text', 'text' => 'Hello']],
        ],
    ],
    MessageToolbar::make(MessageAction::make('Copy')),
)->toElement()->children()[0] ?? null;
$emptyAssistantToolbar = Message::make(
    [
        'role' => MessageRole::Assistant,
        'message' => [
            'parts' => [['type' => 'file', 'url' => 'file:///result.png']],
        ],
    ],
    MessageToolbar::make(MessageAction::make('Copy')),
)->toElement()->children()[0] ?? null;
$modelLogo = ModelSelectorLogo::make(['provider' => 'anthropic'])->toElement();
$assert(
    $messageResponse->kind() === NodeKind::Column
        && $markdownChild->kind() === NodeKind::CustomView
        && $markdownChild->properties()[PropKey::HostName->value]
            === 'pam.mobile_ui.markdown'
        && $markdownProperties['source']
            === '# PAM Native'.PHP_EOL.'**Fast** and native.'
        && isset($markdownChild->events()[\Pam\Native\EventKind::Native->value])
        && $messageFile->kind() === NodeKind::Image
        && $userToolbar?->properties()[PropKey::Visible->value] === false
        && $emptyAssistantToolbar?->properties()[PropKey::Visible->value] === false
        && $modelLogo->children()[0]
            ->properties()[PropKey::Text->value] === 'AN',
    'Chat responses must keep Markdown, files, toolbar context and provider fallbacks native.',
);

$branchIndexes = [];
$messageBranch = MessageBranch::make(
    ['defaultBranch' => 1, 'loop' => false],
    MessageBranchContent::make(
        Text::make('First response'),
        Text::make('Second response'),
    ),
    MessageBranchSelector::make(
        MessageBranchPrevious::make(),
        MessageBranchPage::make(),
        MessageBranchNext::make(),
    ),
)->onChange(
    static function (string $index) use (&$branchIndexes): void {
        $branchIndexes[] = (int) $index;
    },
)->toElement();
$messageBranchContent = $messageBranch->children()[0] ?? null;
$messageBranchSelector = $messageBranch->children()[1] ?? null;
$messageBranchPrevious = $messageBranchSelector?->children()[0] ?? null;
$messageBranchCounter = $messageBranchSelector?->children()[1] ?? null;
$messageBranchNative = $messageBranch->properties()[
    PropKey::HostProperties->value
] ?? null;
$messageBranchPreviousNative = $messageBranchPrevious?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$messageBranchContent instanceof \Pam\Native\Element
    || !$messageBranchSelector instanceof \Pam\Native\Element
    || !$messageBranchPreviousNative instanceof BinaryValue
    || !$messageBranchNative instanceof BinaryValue
) {
    throw new RuntimeException('MessageBranch must compile its native pager anatomy.');
}
$messageBranchProperties = Wire::decodeMap($messageBranchNative->bytes);
$messageBranchPreviousProperties = Wire::decodeMap(
    $messageBranchPreviousNative->bytes,
);
$messageBranch->events()[\Pam\Native\EventKind::Change->value]('0');
$assert(
    $messageBranchProperties['behavior'] === NativeBehavior::MessageBranch->value
        && $messageBranchProperties['defaultBranch'] === 1
        && $messageBranchProperties['loop'] === false
        && $messageBranchContent->children()[0]
            ->properties()[PropKey::Value->value]
            === 'pam:message-branch-page:0'
        && $messageBranchSelector->properties()[PropKey::Value->value]
            === 'pam:message-branch-selector'
        && $messageBranchPreviousProperties['behavior']
            === NativeBehavior::MessageBranchControl->value
        && $messageBranchPreviousProperties['navigationAction']
            === BranchControlAction::Previous->value
        && $messageBranchCounter?->properties()[PropKey::Value->value]
            === 'pam:message-branch-counter'
        && $branchIndexes === [0],
    'Message branches must navigate locally and expose one semantic index.',
);

$promptFiles = [[
    'id' => 'architecture',
    'filename' => 'architecture.md',
    'mediaType' => 'text/markdown',
    'type' => AttachmentType::File->value,
    'url' => 'file:///architecture.md',
]];
/** @var list<array{text: string, files: list<array<string, string|int|float|bool|null>>}> */
$submittedPrompts = [];
$prompt = PromptInputProvider::make(
    ['files' => $promptFiles],
    PromptInput::make(
        PromptInputBody::make(
            PromptInputTextarea::make(['value' => 'Build a PAM screen']),
        ),
        PromptInputSubmit::make(),
    )->onSubmit(
        static function (array $submission) use (&$submittedPrompts): void {
            $submittedPrompts[] = $submission;
        },
    ),
)->toElement();
$promptBody = $prompt->children()[0] ?? null;
$promptTextarea = $promptBody?->children()[0] ?? null;
$promptSubmit = $prompt->children()[1] ?? null;
$promptNative = $prompt->properties()[PropKey::HostProperties->value] ?? null;
$promptSubmitNative = $promptSubmit?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$promptTextarea instanceof \Pam\Native\Element
    || !$promptNative instanceof BinaryValue
    || !$promptSubmitNative instanceof BinaryValue
) {
    throw new RuntimeException('PromptInput must compile its native submit coordinator.');
}
$promptProperties = Wire::decodeMap($promptNative->bytes);
$promptSubmitProperties = Wire::decodeMap($promptSubmitNative->bytes);
$prompt->events()[\Pam\Native\EventKind::Submit->value]('Ship it');
$assert(
    $prompt->kind() === NodeKind::CustomView
        && $promptTextarea->kind() === NodeKind::Input
        && $promptProperties['behavior'] === NativeBehavior::PromptInput->value
        && $promptProperties['clearOnSubmit'] === true
        && $promptProperties['trimOnSubmit'] === true
        && $promptProperties['attachmentCount'] === 1
        && $promptTextarea->properties()[PropKey::Placeholder->value]
            === 'Let’s start building it'
        && $promptTextarea->properties()[PropKey::Multiline->value] === true
        && $promptSubmitProperties['behavior']
            === NativeBehavior::PromptInputSubmit->value
        && $submittedPrompts === [[
            'text' => 'Ship it',
            'files' => $promptFiles,
        ]],
    'PromptInput must compose the upstream text-and-files submission without retransmitting file data.',
);

$horizontalScroll = ScrollView::make(
    [
        'horizontal' => true,
        'scrollEnabled' => false,
        'showsHorizontalScrollIndicator' => true,
        'fillViewport' => false,
        'contentOffset' => 24,
        'overScrollMode' => 'never',
    ],
    Text::make('One'),
    Text::make('Two'),
)->toElement();
$horizontalScrollNative = $horizontalScroll->properties()[
    PropKey::HostProperties->value
] ?? null;
$attachmentsGrid = Attachments::make(
    ['variant' => 'grid'],
    Attachment::make(Text::make('one.png')),
    Attachment::make(Text::make('two.png')),
)->toElement();
$attachmentsList = Attachments::make(
    ['variant' => 'list'],
    Attachment::make(Text::make('architecture.md')),
)->toElement();
if (!$horizontalScrollNative instanceof BinaryValue) {
    throw new RuntimeException('Horizontal ScrollView must pack native properties.');
}
$horizontalScrollProperties = Wire::decodeMap($horizontalScrollNative->bytes);
$assert(
    $horizontalScroll->kind() === NodeKind::CustomView
        && $horizontalScroll->properties()[PropKey::HostName->value]
            === 'pam.mobile_ui.horizontal_scroll'
        && $horizontalScroll->children()[0]->kind() === NodeKind::Row
        && $horizontalScrollProperties['scrollEnabled'] === false
        && $horizontalScrollProperties['showsIndicator'] === true
        && $horizontalScrollProperties['fillViewport'] === false
        && $horizontalScrollProperties['contentOffset'] === 24.0
        && $horizontalScrollProperties['overScrollMode'] === 'never'
        && $attachmentsGrid->properties()[PropKey::HostName->value]
            === 'pam.mobile_ui.horizontal_scroll'
        && $attachmentsGrid->children()[0]->kind() === NodeKind::Row
        && $attachmentsList->children()[0]->kind() === NodeKind::Column,
    'Horizontal ScrollView and Attachments must use the Android scrolling primitive with one content container.',
);

$conversation = Conversation::make(
    ConversationContent::make(
        Message::make(
            ['role' => MessageRole::Assistant],
            MessageContent::make(Text::make('Ready')),
        ),
    ),
    ConversationScrollButton::make(),
)->toElement();
$conversationContent = $conversation->children()[0] ?? null;
$conversationScrollButton = $conversation->children()[1] ?? null;
$conversationNative = $conversation->properties()[
    PropKey::HostProperties->value
] ?? null;
$conversationScrollButtonNative = $conversationScrollButton?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$conversationContent instanceof \Pam\Native\Element
    || !$conversationNative instanceof BinaryValue
    || !$conversationScrollButtonNative instanceof BinaryValue
) {
    throw new RuntimeException('Conversation must compile its native scroll coordinator.');
}
$conversationProperties = Wire::decodeMap($conversationNative->bytes);
$conversationScrollProperties = Wire::decodeMap(
    $conversationScrollButtonNative->bytes,
);
$assert(
    $conversation->kind() === NodeKind::CustomView
        && $conversationProperties['behavior'] === NativeBehavior::Chat->value
        && $conversationProperties['autoScroll'] === true
        && $conversationContent->kind() === NodeKind::Scroll
        && $conversationContent->properties()[PropKey::Value->value]
            === 'pam:conversation-content'
        && $conversationScrollProperties['behavior']
            === NativeBehavior::ConversationScrollButton->value,
    'Conversation must own scrolling and latest-message affordance natively.',
);

$fileTreeSelections = [];
$fileTreeEvents = [];
$fileTree = FileTree::make(
    [
        'defaultExpanded' => ['/src'],
        'selectedPath' => '/src/App.php',
    ],
    FileTreeFolder::make(
        ['path' => '/src', 'name' => 'src'],
        FileTreeFile::make(['path' => '/src/App.php', 'name' => 'App.php']),
    ),
)->onChange(
    static function (string $path) use (&$fileTreeSelections): void {
        $fileTreeSelections[] = $path;
    },
)->onNativeEvent(
    static function (string $payload) use (&$fileTreeEvents): void {
        $fileTreeEvents[] = $payload;
    },
)->toElement();
$fileTreeFolder = $fileTree->children()[0] ?? null;
$fileTreeHeader = $fileTreeFolder?->children()[0] ?? null;
$fileTreeContent = $fileTreeFolder?->children()[1] ?? null;
$fileTreeFile = $fileTreeContent?->children()[0] ?? null;
$fileTreeNative = $fileTree->properties()[PropKey::HostProperties->value] ?? null;
$fileTreeFolderNative = $fileTreeFolder?->properties()[
    PropKey::HostProperties->value
] ?? null;
$fileTreeFileNative = $fileTreeFile?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$fileTreeFolder instanceof \Pam\Native\Element
    || !$fileTreeHeader instanceof \Pam\Native\Element
    || !$fileTreeContent instanceof \Pam\Native\Element
    || !$fileTreeNative instanceof BinaryValue
    || !$fileTreeFolderNative instanceof BinaryValue
    || !$fileTreeFileNative instanceof BinaryValue
) {
    throw new RuntimeException('FileTree must compile its native hierarchy.');
}
$fileTreeProperties = Wire::decodeMap($fileTreeNative->bytes);
$fileTreeFolderProperties = Wire::decodeMap($fileTreeFolderNative->bytes);
$fileTreeFileProperties = Wire::decodeMap($fileTreeFileNative->bytes);
$fileTree->events()[\Pam\Native\EventKind::Change->value]('/src/App.php');
$fileTree->events()[\Pam\Native\EventKind::Native->value](
    Wire::map([
        'action' => FileTreeAction::Expanded->value,
        'path' => '/src',
        'expanded' => false,
    ]),
);
$assert(
    $fileTreeProperties['behavior'] === NativeBehavior::FileTree->value
        && $fileTreeProperties['expandedPaths'] === '/src'
        && $fileTreeFolderProperties['behavior']
            === NativeBehavior::FileTreeFolder->value
        && $fileTreeFolderProperties['path'] === '/src'
        && $fileTreeFileProperties['behavior']
            === NativeBehavior::FileTreeFile->value
        && $fileTreeHeader->properties()[PropKey::Value->value]
            === 'pam:file-tree-header'
        && $fileTreeContent->properties()[PropKey::Value->value]
            === 'pam:file-tree-content'
        && $fileTreeSelections === ['/src/App.php']
        && count($fileTreeEvents) === 1,
    'FileTree must coordinate expansion and selection through bounded semantic events.',
);

$assert(
    PromptInput::make(PromptInputTextarea::make())->toElement()->kind()
        === NodeKind::CustomView,
    'PromptInput must remain a styled native container instead of becoming a text field.',
);
$assert(
    PromptInputTextarea::make()->toElement()->kind() === NodeKind::Input,
    'PromptInputTextarea must use the optimized native input primitive.',
);
$listEndReached = 0;
$flatList = FlatList::make([
    'items' => ['Laravel', 'PAM', 'Android'],
    'rowHeight' => 64,
    'prefetch' => 8,
    'numColumns' => 2,
    'initialScrollIndex' => 1,
    'removeClippedSubviews' => false,
    'showsVerticalScrollIndicator' => false,
    'onEndReachedThreshold' => 0.25,
])->onEndReached(static function () use (&$listEndReached): void {
    $listEndReached++;
})->toElement();
$sectionList = SectionList::make([
    'sections' => [
        'Frameworks' => ['Laravel', 'PAM'],
        'Platforms' => ['Android'],
    ],
    'itemHeight' => 56,
])->toElement();
$virtualizedList = VirtualizedList::make([
    'items' => ['One', 'Two'],
    'scrollEnabled' => false,
    'horizontal' => true,
    'inverted' => true,
    'maxToRenderPerBatch' => 6,
])->onScroll(static function (): void {
})->toElement();
$sheetListKinds = [
    ActionsheetFlatList::make(['items' => ['One']])->toElement()->kind(),
    ActionsheetVirtualizedList::make(['items' => ['One']])->toElement()->kind(),
    BottomSheetFlatList::make(['items' => ['One']])->toElement()->kind(),
    SelectFlatList::make(['items' => ['One']])->toElement()->kind(),
    SelectVirtualizedList::make(['items' => ['One']])->toElement()->kind(),
];
$sheetSectionKinds = [
    ActionsheetSectionList::make([
        'sections' => ['Group' => ['One']],
    ])->toElement()->kind(),
    BottomSheetSectionList::make([
        'sections' => ['Group' => ['One']],
    ])->toElement()->kind(),
    SelectSectionList::make([
        'sections' => ['Group' => ['One']],
    ])->toElement()->kind(),
];
$assert(
    $flatList->kind() === NodeKind::List
        && $flatList->properties()[PropKey::ListRowHeight->value] === 64.0
        && $flatList->properties()[PropKey::ListPrefetch->value] === 8
        && $flatList->properties()[PropKey::ListNumColumns->value] === 2
        && $flatList->properties()[PropKey::ListInitialScrollIndex->value] === 1
        && $flatList
            ->properties()[PropKey::ListRemoveClippedSubviews->value] === false
        && $flatList->properties()[PropKey::ShowsScrollIndicator->value] === false
        && $flatList->properties()[PropKey::EndReachedThreshold->value] === 0.25
        && isset($flatList->events()[\Pam\Native\EventKind::EndReached->value])
        && $sectionList->kind() === NodeKind::SectionList
        && $sectionList->properties()[PropKey::ListRowHeight->value] === 56.0
        && $virtualizedList->kind() === NodeKind::List
        && $virtualizedList->properties()[PropKey::ScrollEnabled->value] === false
        && $virtualizedList->properties()[PropKey::ListHorizontal->value] === true
        && $virtualizedList->properties()[PropKey::ListInverted->value] === true
        && $virtualizedList->properties()[PropKey::ListPrefetch->value] === 6
        && isset($virtualizedList->events()[\Pam\Native\EventKind::Scroll->value])
        && count(array_filter(
            $sheetListKinds,
            static fn (NodeKind $kind): bool => $kind === NodeKind::List,
        )) === count($sheetListKinds)
        && count(array_filter(
            $sheetSectionKinds,
            static fn (NodeKind $kind): bool => $kind === NodeKind::SectionList,
        )) === count($sheetSectionKinds),
    'List facades and sheet aliases must preserve recycled native rows, sizing, indicators and end events.',
);

$table = Table::make(
    TableHeader::make(
        TableRow::make(
            TableHead::make('Package'),
            TableHead::make('Runtime'),
        ),
    ),
    TableBody::make(
        TableRow::make(
            TableData::make('pushinbr/pam-mobile-ui'),
            TableData::make('Android'),
        ),
    ),
)->toElement();
$tableNativeValue = $table->properties()[PropKey::HostProperties->value] ?? null;
$tableHeader = $table->children()[0] ?? null;
$tableHeaderRow = $tableHeader?->children()[0] ?? null;
$tableHeaderRowNativeValue = $tableHeaderRow?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$tableNativeValue instanceof BinaryValue
    || !$tableHeaderRowNativeValue instanceof BinaryValue
) {
    throw new RuntimeException('Table and TableRow must render native semantic hosts.');
}
$tableNative = Wire::decodeMap($tableNativeValue->bytes);
$tableHeaderRowNative = Wire::decodeMap($tableHeaderRowNativeValue->bytes);
$assert(
    $tableNative['behavior'] === NativeBehavior::Table->value
        && $tableHeaderRowNative['behavior'] === NativeBehavior::TableRow->value
        && $tableHeaderRowNative['isHeaderRow'] === true
        && count($tableHeaderRow->children()) === 2,
    'Table must retain authored cells while packing header-row collection semantics.',
);

$imageViewerOpenChanges = [];
$imageViewerIndexes = [];
$imageViewer = ImageViewer::make(
    [
        'images' => [
            ['url' => 'file:///mountain.jpg', 'alt' => 'Mountain'],
            ['url' => 'file:///ocean.jpg', 'alt' => 'Ocean'],
        ],
        'open' => true,
        'initialIndex' => 1,
    ],
    ImageViewerTrigger::make(
        Image::make(['source' => 'file:///thumbnail.jpg']),
    ),
    ImageViewerContent::make(
        ImageViewerNavigation::make(),
        ImageViewerCounter::make(),
        ImageViewerCloseButton::make(),
    ),
)->onToggle(
    static function (bool $open) use (&$imageViewerOpenChanges): void {
        $imageViewerOpenChanges[] = $open;
    },
)->onChange(
    static function (string $index) use (&$imageViewerIndexes): void {
        $imageViewerIndexes[] = (int) $index;
    },
)->toElement();
$imageViewerTrigger = $imageViewer->children()[0] ?? null;
$imageViewerModal = $imageViewer->children()[1] ?? null;
$imageViewerContent = $imageViewerModal?->children()[0] ?? null;
$imageViewerNavigation = $imageViewerContent?->children()[2] ?? null;
$imageViewerCounter = $imageViewerContent?->children()[3] ?? null;
$imageViewerClose = $imageViewerContent?->children()[4] ?? null;
$imageViewerProperties = $imageViewerContent?->properties()[
    PropKey::HostProperties->value
] ?? null;
$imagePreviousProperties = $imageViewerNavigation?->children()[0]
    ?->properties()[PropKey::HostProperties->value] ?? null;
$imageCloseProperties = $imageViewerClose?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$imageViewerTrigger instanceof \Pam\Native\Element
    || !$imageViewerContent instanceof \Pam\Native\Element
    || !$imageViewerNavigation instanceof \Pam\Native\Element
    || !$imageViewerCounter instanceof \Pam\Native\Element
    || !$imageViewerProperties instanceof BinaryValue
    || !$imagePreviousProperties instanceof BinaryValue
    || !$imageCloseProperties instanceof BinaryValue
) {
    throw new RuntimeException('ImageViewer must compile its gallery and native controls.');
}
$imageViewerNative = Wire::decodeMap($imageViewerProperties->bytes);
$imagePreviousNative = Wire::decodeMap($imagePreviousProperties->bytes);
$imageCloseNative = Wire::decodeMap($imageCloseProperties->bytes);
$imageViewerTrigger->events()[\Pam\Native\EventKind::Press->value]();
$imageViewerContent->events()[\Pam\Native\EventKind::Change->value]('0');
$imageViewerContent->events()[\Pam\Native\EventKind::Native->value](
    Wire::map(['action' => 1, 'dismissed' => true]),
);
$assert(
    $imageViewerContent->kind() === NodeKind::CustomView
        && $imageViewerNative['behavior'] === NativeBehavior::ImageViewer->value
        && $imageViewerNative['initialIndex'] === 1
        && count($imageViewerContent->children()) === 5
        && $imageViewerContent->children()[0]
            ->properties()[PropKey::Value->value]
            === 'pam:image-viewer-image:0'
        && $imageViewerContent->children()[1]
            ->properties()[PropKey::AccessibilityLabel->value] === 'Ocean'
        && count($imageViewerNavigation->children()) === 2
        && $imagePreviousNative['behavior']
            === NativeBehavior::ImageViewerControl->value
        && $imagePreviousNative['navigationAction']
            === ImageViewerControlAction::Previous->value
        && $imageViewerCounter->children()[0]
            ->properties()[PropKey::Value->value]
            === 'pam:image-viewer-counter'
        && $imageCloseNative['behavior'] === NativeBehavior::OverlayDismiss->value
        && $imageViewerOpenChanges === [true, false]
        && $imageViewerIndexes === [0],
    'ImageViewer must synthesize images, navigation, counter and semantic events.',
);
$defaultImageViewer = ImageViewer::make(
    ['images' => ['file:///default.jpg']],
    ImageViewerTrigger::make('Open gallery'),
    ImageViewerContent::make(),
)->toElement();
$defaultImageViewerTrigger = $defaultImageViewer->children()[0] ?? null;
$defaultImageViewerModal = $defaultImageViewer->children()[1] ?? null;
$defaultImageViewerHost = $defaultImageViewerModal?->children()[0] ?? null;
$defaultImageViewerProperties = $defaultImageViewerHost?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$defaultImageViewerTrigger instanceof \Pam\Native\Element
    || !$defaultImageViewerModal instanceof \Pam\Native\Element
    || !$defaultImageViewerProperties instanceof BinaryValue
) {
    throw new RuntimeException('Default ImageViewer must retain its trigger and gallery host.');
}
$defaultImageViewerNative = Wire::decodeMap(
    $defaultImageViewerProperties->bytes,
);
$assert(
    !isset($defaultImageViewer->properties()[PropKey::Visible->value])
        && !isset(
            $defaultImageViewerTrigger->properties()[PropKey::Visible->value],
        )
        && $defaultImageViewerModal->properties()[PropKey::Visible->value]
            === false
        && $defaultImageViewerNative['initialIndex'] === 0
        && $defaultImageViewerNative['defaultOpen'] === false,
    'ImageViewer must default closed at index zero without unmounting its trigger.',
);

$inputChanges = [];
$compoundInput = Input::make(
    ['type' => 'password', 'readOnly' => true],
    InputSlot::make(
        ['slotAction' => InputSlotAction::Clear],
        Text::make('Clear'),
    ),
    InputField::make(['value' => 'secret', 'sync' => 'native']),
)->onChange(static function (string $value) use (&$inputChanges): void {
    $inputChanges[] = $value;
})->toElement();
$compoundInputNativeValue = $compoundInput->properties()[
    PropKey::HostProperties->value
] ?? null;
$compoundInputSlot = $compoundInput->children()[0] ?? null;
$compoundInputField = $compoundInput->children()[1] ?? null;
$compoundInputSlotNativeValue = $compoundInputSlot?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$compoundInputNativeValue instanceof BinaryValue
    || !$compoundInputSlotNativeValue instanceof BinaryValue
    || !$compoundInputField instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Compound Input must render its native group, slot and field.');
}
$compoundInputNative = Wire::decodeMap($compoundInputNativeValue->bytes);
$compoundInputSlotNative = Wire::decodeMap($compoundInputSlotNativeValue->bytes);
$assert(
    $compoundInput->kind() === NodeKind::CustomView
        && $compoundInputNative['behavior'] === NativeBehavior::InputGroup->value
        && $compoundInputNative['readOnly'] === true
        && $compoundInputSlotNative['behavior'] === NativeBehavior::InputSlot->value
        && $compoundInputSlotNative['slotAction'] === InputSlotAction::Clear->value
        && $compoundInputField->kind() === NodeKind::Input
        && $compoundInputField->properties()[PropKey::Secure->value] === true
        && $compoundInputField->properties()[PropKey::InputSyncMode->value] === 1
        && isset($compoundInputField->events()[\Pam\Native\EventKind::Change->value]),
    'Input must keep focus, password, clear and root change behavior in native compound anatomy.',
);

$form = FormControl::make(
    ['required' => true, 'invalid' => false],
    FormControlLabel::make(FormControlLabelText::make('Email')),
    Input::make(InputField::make(['placeholder' => 'you@example.com'])),
    FormControlHelper::make(
        FormControlHelperText::make('We never share your email.'),
    ),
    FormControlError::make(FormControlErrorText::make('Email is invalid.')),
)->toElement();
$formNativeValue = $form->properties()[PropKey::HostProperties->value] ?? null;
$formLabel = $form->children()[0] ?? null;
$formInput = $form->children()[1] ?? null;
$formError = $form->children()[3] ?? null;
if (
    !$formNativeValue instanceof BinaryValue
    || !$formLabel instanceof \Pam\Native\Element
    || !$formInput instanceof \Pam\Native\Element
    || !$formError instanceof \Pam\Native\Element
) {
    throw new RuntimeException('FormControl must retain its complete authored anatomy.');
}
$formNative = Wire::decodeMap($formNativeValue->bytes);
$formInputNativeValue = $formInput->properties()[PropKey::HostProperties->value] ?? null;
if (!$formInputNativeValue instanceof BinaryValue) {
    throw new RuntimeException('FormControl Input must inherit its native state.');
}
$formInputNative = Wire::decodeMap($formInputNativeValue->bytes);
$assert(
    $formNative['behavior'] === NativeBehavior::FormControl->value
        && $formNative['required'] === true
        && $formLabel->kind() === NodeKind::View
        && $formLabel->properties()[PropKey::Value->value] === 'pam:form-label'
        && count($formLabel->children()) === 2
        && $formLabel->children()[1]->properties()[PropKey::Value->value]
            === 'pam:form-required'
        && $formInputNative['required'] === true
        && $formError->properties()[PropKey::Value->value] === 'pam:form-error'
        && $formError->properties()[PropKey::Visible->value] === false,
    'FormControl must inject required anatomy, hide inactive feedback and link native field state.',
);
$modelSelectorOpenChanges = [];
$modelSelectorValues = [];
$modelSelectorNativeEvents = [];
$modelSelector = ModelSelector::make(
    ['open' => false, 'size' => ComponentSize::Small, 'value' => 'pam-fast'],
    ModelSelectorContent::make(
        ['title' => 'Choose a model'],
        ModelSelectorInput::make(),
        ModelSelectorList::make(
            ModelSelectorGroup::make(
                ['heading' => 'PAM'],
                ModelSelectorItem::make(
                    ['value' => 'pam-fast'],
                    ModelSelectorName::make('PAM Fast'),
                ),
            ),
        ),
    ),
    ModelSelectorTrigger::make(
        ['asChild' => true],
        Button::make('Choose model'),
    ),
)->onToggle(
    static function (bool $open) use (&$modelSelectorOpenChanges): void {
        $modelSelectorOpenChanges[] = $open;
    },
)->onChange(
    static function (string $value) use (&$modelSelectorValues): void {
        $modelSelectorValues[] = $value;
    },
)->onNativeEvent(
    static function (string $payload) use (&$modelSelectorNativeEvents): void {
        $modelSelectorNativeEvents[] = $payload;
    },
)->toElement();
$modelSelectorTrigger = $modelSelector->children()[0] ?? null;
$modelSelectorContent = $modelSelector->children()[1] ?? null;
$modelSelectorHost = $modelSelectorContent?->children()[0] ?? null;
$modelSelectorHeader = $modelSelectorHost?->children()[0] ?? null;
$modelSelectorTitle = $modelSelectorHeader?->children()[0] ?? null;
$modelSelectorClose = $modelSelectorHeader?->children()[1] ?? null;
$modelSelectorBody = $modelSelectorHost?->children()[1]?->children()[0] ?? null;
$modelSelectorInput = $modelSelectorBody?->children()[0] ?? null;
$modelSelectorList = $modelSelectorBody?->children()[1] ?? null;
$modelSelectorGroup = $modelSelectorList?->children()[0] ?? null;
$modelSelectorHeading = $modelSelectorGroup?->children()[0] ?? null;
$modelSelectorItem = $modelSelectorGroup?->children()[1] ?? null;
$modelSelectorCloseNative = $modelSelectorClose?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$modelSelectorTrigger instanceof \Pam\Native\Element
    || !$modelSelectorContent instanceof \Pam\Native\Element
    || !$modelSelectorHost instanceof \Pam\Native\Element
    || !$modelSelectorHeader instanceof \Pam\Native\Element
    || !$modelSelectorTitle instanceof \Pam\Native\Element
    || !$modelSelectorCloseNative instanceof BinaryValue
    || !$modelSelectorBody instanceof \Pam\Native\Element
    || !$modelSelectorInput instanceof \Pam\Native\Element
    || !$modelSelectorHeading instanceof \Pam\Native\Element
    || !$modelSelectorItem instanceof \Pam\Native\Element
) {
    throw new RuntimeException('ModelSelector must render its controlled content.');
}
$modelSelectorCloseProperties = Wire::decodeMap($modelSelectorCloseNative->bytes);
$modelSelectorTrigger->events()[\Pam\Native\EventKind::Press->value]();
$modelSelectorItem->events()[\Pam\Native\EventKind::Press->value]();
$modelSelectorHost->events()[\Pam\Native\EventKind::Native->value](
    Wire::map(['action' => 1, 'dismissed' => true]),
);
$assert(
    $modelSelector->kind() === NodeKind::View
        && $modelSelector->properties()[PropKey::Collapsable->value] === true
        && !isset($modelSelector->properties()[PropKey::Visible->value])
        && $modelSelectorContent->properties()[PropKey::Visible->value] === false,
    'ModelSelector must keep its trigger host mounted while hiding controlled content.',
);
$assert(
    $modelSelectorTrigger->kind() === NodeKind::Pressable
        && $modelSelectorContent->kind() === NodeKind::Modal
        && $modelSelectorHost->kind() === NodeKind::CustomView
        && isset($modelSelectorContent->events()[\Pam\Native\EventKind::Native->value])
        && isset($modelSelectorHost->events()[\Pam\Native\EventKind::Native->value])
        && $modelSelectorHost->properties()[PropKey::AccessibilityLabel->value]
            === 'Choose a model'
        && $modelSelectorHost->properties()[PropKey::WidthPercent->value] === 70.0
        && $modelSelectorHost->properties()[PropKey::MaxWidth->value] === 420.0
        && $modelSelectorTitle->properties()[PropKey::Width->value] === 1.0
        && $modelSelectorTitle->properties()[PropKey::Height->value] === 1.0
        && $modelSelectorCloseProperties['behavior']
            === NativeBehavior::OverlayDismiss->value
        && $modelSelectorInput->kind() === NodeKind::Input
        && $modelSelectorInput->properties()[PropKey::Placeholder->value]
            === 'Search models...'
        && $modelSelectorHeading->properties()[PropKey::Text->value] === 'PAM'
        && $modelSelectorHeading->properties()[PropKey::Value->value]
            === 'pam:model-selector-heading'
        && $modelSelectorItem->properties()[PropKey::Selected->value] === true
        && $modelSelectorOpenChanges === [true, false]
        && $modelSelectorValues === ['pam-fast']
        && count($modelSelectorNativeEvents) === 1,
    'ModelSelector must preserve provider, modal anatomy, sizing, selection and controlled open events.',
);
$closedSelect = Select::make(
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
$defaultSheetOpenChanges = [];
$defaultClosedSheet = BottomSheet::make(
    BottomSheetPortal::make(
        ['snapPoints' => '25%, 50; 90'],
        BottomSheetBackdrop::make(),
        BottomSheetContent::make(),
    ),
    BottomSheetTrigger::make('Open sheet'),
)->onToggle(
    static function (bool $open) use (&$defaultSheetOpenChanges): void {
        $defaultSheetOpenChanges[] = $open;
    },
)->toElement();
$defaultClosedSheetTrigger = $defaultClosedSheet->children()[0] ?? null;
$defaultClosedSheetWindow = $defaultClosedSheet->children()[1] ?? null;
$defaultClosedSheetHost = $defaultClosedSheetWindow?->children()[0] ?? null;
$defaultClosedSheetBackdrop = $defaultClosedSheetHost?->children()[0] ?? null;
$defaultClosedSheetContent = $defaultClosedSheetHost?->children()[1] ?? null;
if (
    !$defaultClosedSheetTrigger instanceof \Pam\Native\Element
    || !$defaultClosedSheetWindow instanceof \Pam\Native\Element
    || !$defaultClosedSheetHost instanceof \Pam\Native\Element
    || !$defaultClosedSheetBackdrop instanceof \Pam\Native\Element
    || !$defaultClosedSheetContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Default-closed BottomSheet must render its stable window.');
}
$defaultClosedSheetProperties = $defaultClosedSheetHost->properties()[
    PropKey::HostProperties->value
] ?? null;
if (!$defaultClosedSheetProperties instanceof BinaryValue) {
    throw new RuntimeException('Default-closed BottomSheet must carry native state.');
}
$defaultClosedSheetNative = Wire::decodeMap($defaultClosedSheetProperties->bytes);
$defaultClosedSheetTrigger->events()[\Pam\Native\EventKind::Press->value]();
$defaultClosedSheetHost->events()[\Pam\Native\EventKind::Native->value](
    Wire::map(['action' => 1, 'dismissed' => true]),
);
$assert(
    $defaultClosedSheet->properties()[PropKey::Collapsable->value] === true
        && !isset($defaultClosedSheet->properties()[PropKey::Visible->value])
        && $defaultClosedSheetWindow->properties()[PropKey::Visible->value]
            === false
        && $defaultClosedSheetNative['defaultIsOpen'] === false
        && $defaultClosedSheetNative['defaultSnapIndex'] === 0
        && $defaultClosedSheetNative['snapToIndex'] === 0
        && $defaultClosedSheetNative['enablePanDownToClose'] === true
        && $defaultClosedSheetNative['enableDynamicSizing'] === false
        && $defaultClosedSheetNative['snapPoints'] === "25\n50\n90"
        && $defaultClosedSheetBackdrop->properties()[PropKey::Value->value]
            === 'pam:overlay-backdrop'
        && $defaultClosedSheetContent->properties()[PropKey::Value->value]
            === 'pam:overlay-content'
        && $defaultSheetOpenChanges === [true, false],
    'BottomSheet must preserve provider semantics, upstream defaults and controlled trigger events.',
);
$bottomSheet = BottomSheet::make(
    ['open' => true],
    BottomSheetPortal::make(
        [
            'snapPoints' => ['25%', 50, 90],
            'snapToIndex' => 1,
            'pressBehavior' => BackdropPressBehavior::Collapse,
        ],
        BottomSheetBackdrop::make(),
        BottomSheetContent::make(
            BottomSheetDragIndicator::make(),
            BottomSheetItem::make('Save')->onPress(static function (): void {
            }),
        ),
    ),
)->onChange(static function (): void {
})->toElement();
$bottomSheetWindow = $bottomSheet->children()[0] ?? null;
$bottomSheetHost = $bottomSheetWindow?->children()[0] ?? null;
$bottomSheetBackdrop = $bottomSheetHost?->children()[0] ?? null;
$bottomSheetContent = $bottomSheetHost?->children()[1] ?? null;
$bottomSheetIndicator = $bottomSheetContent?->children()[0] ?? null;
$bottomSheetItem = $bottomSheetContent?->children()[1] ?? null;
if (
    !$bottomSheetWindow instanceof \Pam\Native\Element
    || !$bottomSheetHost instanceof \Pam\Native\Element
    || !$bottomSheetBackdrop instanceof \Pam\Native\Element
    || !$bottomSheetContent instanceof \Pam\Native\Element
    || !$bottomSheetIndicator instanceof \Pam\Native\Element
    || !$bottomSheetItem instanceof \Pam\Native\Element
) {
    throw new RuntimeException('BottomSheet must render its native portal window.');
}
$bottomSheetProperties = $bottomSheetHost->properties()[
    PropKey::HostProperties->value
] ?? null;
$bottomSheetItemProperties = $bottomSheetItem->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$bottomSheetProperties instanceof BinaryValue
    || !$bottomSheetItemProperties instanceof BinaryValue
) {
    throw new RuntimeException('BottomSheet host and item must carry native state.');
}
$bottomSheetNative = Wire::decodeMap($bottomSheetProperties->bytes);
$bottomSheetItemNative = Wire::decodeMap($bottomSheetItemProperties->bytes);
$assert(
    $bottomSheet->kind() === NodeKind::View
        && $bottomSheetWindow->kind() === NodeKind::Modal
        && $bottomSheetWindow->properties()[PropKey::ModalPresentation->value]
            === ModalPresentation::Sheet->value
        && $bottomSheetHost->kind() === NodeKind::CustomView
        && isset($bottomSheetHost->events()[\Pam\Native\EventKind::Change->value])
        && $bottomSheetNative['snapPoints'] === "25\n50\n90"
        && $bottomSheetNative['snapToIndex'] === 1
        && $bottomSheetNative['pressBehavior']
            === BackdropPressBehavior::Collapse->value
        && $bottomSheetBackdrop->properties()[PropKey::Value->value]
            === 'pam:overlay-backdrop'
        && $bottomSheetContent->properties()[PropKey::Value->value]
            === 'pam:overlay-content'
        && $bottomSheetIndicator->properties()[PropKey::Value->value]
            === 'pam:sheet-drag-indicator'
        && $bottomSheetItem->kind() === NodeKind::CustomView
        && $bottomSheetItem->children()[0]
            ->properties()[PropKey::Text->value] === 'Save'
        && $bottomSheetItemNative['behavior'] === NativeBehavior::SheetItem->value,
    'BottomSheet must pack snap points and coordinate its complete native anatomy.',
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
        && $semanticSelectItem->kind() === NodeKind::CustomView
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
$multipleMenuSelection = null;
$anchoredMenu = Menu::make(
    [
        'defaultIsOpen' => false,
        'selectionMode' => SelectionMode::Multiple,
        'selectedKeys' => ['settings'],
        'disabledKeys' => ['billing'],
        'placement' => Placement::BottomStart,
        'crossOffset' => 4,
        'shouldFlip' => true,
    ],
    Button::make('More'),
    MenuItem::make(['key' => 'settings', 'textValue' => 'Settings']),
    MenuItem::make(['key' => 'billing', 'textValue' => 'Billing']),
)->onChange(
    static function (array $values) use (&$multipleMenuSelection): void {
        $multipleMenuSelection = $values;
    },
)->toElement();
$anchoredMenuTrigger = $anchoredMenu->children()[0] ?? null;
$anchoredMenuContent = $anchoredMenu->children()[1] ?? null;
$anchoredMenuItem = $anchoredMenuContent?->children()[0] ?? null;
$disabledMenuItem = $anchoredMenuContent?->children()[1] ?? null;
if (
    !$anchoredMenuTrigger instanceof \Pam\Native\Element
    || !$anchoredMenuContent instanceof \Pam\Native\Element
    || !$anchoredMenuItem instanceof \Pam\Native\Element
    || !$disabledMenuItem instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Menu must preserve its trigger and native item collection.');
}
$anchoredMenuHostProperties = $anchoredMenu->properties()[
    PropKey::HostProperties->value
] ?? null;
$anchoredMenuItemProperties = $anchoredMenuItem->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$anchoredMenuHostProperties instanceof BinaryValue
    || !$anchoredMenuItemProperties instanceof BinaryValue
) {
    throw new RuntimeException('Menu root and items must carry packed native state.');
}
$anchoredMenuNative = Wire::decodeMap($anchoredMenuHostProperties->bytes);
$anchoredMenuItemNative = Wire::decodeMap($anchoredMenuItemProperties->bytes);
$anchoredMenuPress = $anchoredMenuItem->events()[
    \Pam\Native\EventKind::Press->value
] ?? null;
if (!$anchoredMenuPress instanceof Closure) {
    throw new RuntimeException('Menu items must inherit the root selection callback.');
}
$anchoredMenuPress();
$assert(
    $multipleMenuSelection === []
        && $anchoredMenuTrigger->properties()[PropKey::Value->value]
            === 'pam:overlay-trigger'
        && $anchoredMenuContent->properties()[PropKey::Value->value]
            === 'pam:overlay-content'
        && $anchoredMenuContent->properties()[PropKey::Visible->value] === false
        && $anchoredMenuNative['defaultIsOpen'] === false
        && $anchoredMenuNative['selectionMode'] === SelectionMode::Multiple->value
        && $anchoredMenuNative['placement'] === Placement::BottomStart->value
        && $anchoredMenuItemNative['behavior'] === NativeBehavior::MenuItem->value
        && $disabledMenuItem->properties()[PropKey::Enabled->value] === false,
    'Menu must package its anchored trigger, collection modes and disabled keys.',
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
$openPopover = Popover::make(
    ['isOpen' => true, 'placement' => Placement::Top],
    Button::make('Open'),
    PopoverContent::make(
        PopoverArrow::make(),
        PopoverCloseButton::make(),
    ),
)->toElement();
$openPopoverTrigger = $openPopover->children()[0] ?? null;
$openPopoverContent = $openPopover->children()[1] ?? null;
$openPopoverArrow = $openPopoverContent?->children()[0] ?? null;
$openPopoverClose = $openPopoverContent?->children()[1] ?? null;
$tooltip = Tooltip::make(
    Button::make('Help'),
    TooltipContent::make('Native hint'),
)->toElement();
$tooltipTrigger = $tooltip->children()[0] ?? null;
$tooltipContent = $tooltip->children()[1] ?? null;
if (
    !$openPopoverTrigger instanceof \Pam\Native\Element
    || !$openPopoverContent instanceof \Pam\Native\Element
    || !$openPopoverArrow instanceof \Pam\Native\Element
    || !$openPopoverClose instanceof \Pam\Native\Element
    || !$tooltipTrigger instanceof \Pam\Native\Element
    || !$tooltipContent instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Popover and Tooltip must expose their anchored anatomy.');
}
$popoverCloseProperties = $openPopoverClose->properties()[
    PropKey::HostProperties->value
] ?? null;
if (!$popoverCloseProperties instanceof BinaryValue) {
    throw new RuntimeException('PopoverCloseButton must carry native dismiss behavior.');
}
$popoverCloseNative = Wire::decodeMap($popoverCloseProperties->bytes);
$assert(
    $openPopoverTrigger->properties()[PropKey::Value->value]
        === 'pam:overlay-trigger'
        && $openPopoverContent->properties()[PropKey::Value->value]
            === 'pam:overlay-content'
        && $openPopoverArrow->properties()[PropKey::Value->value]
            === 'pam:overlay-arrow'
        && $popoverCloseNative['behavior'] === NativeBehavior::OverlayDismiss->value
        && $tooltipTrigger->properties()[PropKey::Value->value]
            === 'pam:overlay-trigger'
        && $tooltipContent->properties()[PropKey::Value->value]
            === 'pam:overlay-content'
        && $tooltipContent->properties()[PropKey::Visible->value] === false,
    'Popover and Tooltip must tag triggers, content and arrows for native positioning.',
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
$templateGallery = TemplateRenderer::render(
    TemplateCompiler::compile(
        <<<'PAM'
<ImageViewer images="$gallery" open="true" initialIndex="1" loop="true">
    <ImageViewerTrigger><Button><ButtonText>Open</ButtonText></Button></ImageViewerTrigger>
    <ImageViewerContent>
        <ImageViewerNavigation />
        <ImageViewerCounter />
        <ImageViewerCloseButton />
    </ImageViewerContent>
</ImageViewer>
PAM,
    ),
    null,
    [
        'gallery' => [
            ['url' => 'file:///mountain.jpg', 'alt' => 'Mountain'],
            ['url' => 'file:///ocean.jpg', 'alt' => 'Ocean'],
        ],
    ],
);
$templateGalleryModal = $templateGallery->children()[1] ?? null;
$templateGalleryContent = $templateGalleryModal?->children()[0] ?? null;
$templateGalleryNative = $templateGalleryContent?->properties()[
    PropKey::HostProperties->value
] ?? null;
if (
    !$templateGalleryContent instanceof \Pam\Native\Element
    || !$templateGalleryNative instanceof BinaryValue
) {
    throw new RuntimeException('Tag ImageViewer must compile its native gallery.');
}
$templateGalleryProperties = Wire::decodeMap($templateGalleryNative->bytes);
$assert(
    $templateGalleryProperties['behavior'] === NativeBehavior::ImageViewer->value
        && $templateGalleryProperties['initialIndex'] === 1
        && $templateGalleryProperties['loop'] === true
        && count($templateGalleryContent->children()) === 5
        && $templateGalleryContent->children()[1]
            ->properties()[PropKey::AccessibilityLabel->value] === 'Ocean',
    'Bound gallery arrays must flow through tag context into optimized native children.',
);
$templatePromptScope = new class {
    /** @var array{text: string, files: list<array<string, string|int|float|bool|null>>} */
    public array $submission = ['text' => '', 'files' => []];

    /**
     * @param array{
     *     text: string,
     *     files: list<array<string, string|int|float|bool|null>>
     * } $submission
     */
    public function submitPrompt(array $submission): void
    {
        $this->submission = $submission;
    }
};
$templatePromptFiles = [[
    'id' => 'diagram',
    'filename' => 'diagram.png',
    'mediaType' => 'image/png',
    'type' => AttachmentType::File->value,
    'url' => 'file:///diagram.png',
]];
$templatePrompt = TemplateRenderer::render(
    TemplateCompiler::compile(
        <<<'PAM'
<PromptInputProvider files="$files">
    <PromptInput on:submit="submitPrompt">
        <PromptInputTextarea />
        <PromptInputSubmit />
    </PromptInput>
</PromptInputProvider>
PAM,
    ),
    $templatePromptScope,
    ['files' => $templatePromptFiles],
);
$templatePromptProperties = $templatePrompt->properties()[
    PropKey::HostProperties->value
] ?? null;
if (!$templatePromptProperties instanceof BinaryValue) {
    throw new RuntimeException('Tag PromptInput must compile its native coordinator.');
}
$templatePromptNative = Wire::decodeMap($templatePromptProperties->bytes);
$templatePrompt->events()[\Pam\Native\EventKind::Submit->value]('Draw it');
$assert(
    $templatePromptNative['attachmentCount'] === 1
        && $templatePrompt->children()[0]
            ->properties()[PropKey::Placeholder->value]
            === 'Let’s start building it'
        && $templatePromptScope->submission === [
            'text' => 'Draw it',
            'files' => $templatePromptFiles,
        ],
    'Tag PromptInput must adapt the upstream text-and-files callback without a file bridge payload.',
);
$compoundEventScope = new class {
    public string $selected = '';

    /** @var list<bool> */
    public array $toggles = [];

    public function selectValue(string $value): void
    {
        $this->selected = $value;
    }

    public function toggleSheet(bool $open): void
    {
        $this->toggles[] = $open;
    }
};
$templateSelect = TemplateRenderer::render(
    TemplateCompiler::compile(
        <<<'PAM'
<Select on:change="selectValue">
    <SelectTrigger><SelectInput /></SelectTrigger>
    <SelectPortal>
        <SelectContent>
            <SelectItem value="laravel" label="Laravel" />
        </SelectContent>
    </SelectPortal>
</Select>
PAM,
    ),
    $compoundEventScope,
    [],
);
$templateSelectPortal = $templateSelect->children()[1] ?? null;
$templateSelectHost = $templateSelectPortal?->children()[0] ?? null;
$templateSelectContent = $templateSelectHost?->children()[0] ?? null;
$templateSelectItem = $templateSelectContent?->children()[0] ?? null;
if (!$templateSelectItem instanceof \Pam\Native\Element) {
    throw new RuntimeException('Tag Select item must retain inherited events.');
}
$templateSelectItem->events()[\Pam\Native\EventKind::Press->value]();

$templateSheet = TemplateRenderer::render(
    TemplateCompiler::compile(
        <<<'PAM'
<BottomSheet on:toggle="toggleSheet">
    <BottomSheetTrigger>Open sheet</BottomSheetTrigger>
    <BottomSheetPortal>
        <BottomSheetContent />
    </BottomSheetPortal>
</BottomSheet>
PAM,
    ),
    $compoundEventScope,
    [],
);
$templateSheetTrigger = $templateSheet->children()[0] ?? null;
$templateSheetModal = $templateSheet->children()[1] ?? null;
$templateSheetHost = $templateSheetModal?->children()[0] ?? null;
if (
    !$templateSheetTrigger instanceof \Pam\Native\Element
    || !$templateSheetHost instanceof \Pam\Native\Element
) {
    throw new RuntimeException('Tag BottomSheet must retain trigger and native host events.');
}
$templateSheetTrigger->events()[\Pam\Native\EventKind::Press->value]();
$templateSheetHost->events()[\Pam\Native\EventKind::Native->value](
    Wire::map(['action' => 1, 'dismissed' => true]),
);
$assert(
    $compoundEventScope->selected === 'laravel'
        && $compoundEventScope->toggles === [true, false],
    'Tag compound roots must forward selection, trigger and dismissal events during composition.',
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
