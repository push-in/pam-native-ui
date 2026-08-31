<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Component\UiComponent;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Align;
use Pam\Native\Component;
use Pam\Native\Justify;
use Pam\Native\Navigation\DrawerNavigator;
use Pam\Native\PositionType;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\ActivityIndicator;
use Pam\Native\UI\Column;
use Pam\Native\UI\Pressable;
use Pam\Native\UI\Row;
use Pam\Native\UI\SafeAreaView;
use Pam\Native\UI\ScrollView;
use Pam\Native\UI\Text;
use Pam\Native\UI\View;

final class ComponentRoute extends Component
{
    public DrawerNavigator $drawer;

    /** @var array<int, mixed> */
    private array $sampleValues = [];

    /** @var array<int, string> */
    private array $expandedPanels = [];

    /**
     * @param class-string<UiComponent> $component
     */
    public function __construct(
        private readonly string $tag,
        private readonly string $title,
        private readonly string $component,
    ) {
    }

    public function render(): Renderable
    {
        $theme = ThemeManager::current();
        $samples = [];
        foreach ($this->variations() as $index => $variation) {
            $caption = Text::make($variation['label'])->style(new Style(
                textColor: $theme->color(ColorToken::MutedForeground),
                fontSize: 12.0,
                lineHeight: 16.0,
                fontWeight: 600,
            ));
            $component = $this->component;
            $previewProps = $this->sampleProps($variation['props']);
            if ($this->tag === 'p-data-table-virtual') {
                // The catalog places bounded virtual tables inside its own
                // documentation scroller. Keep boundary rows mounted so the
                // nested viewport never presents a half-painted sample.
                $previewProps['removeClippedSubviews'] = false;
            }
            if ($this->belongsTo([
                'p-autocomplete', 'p-color-input', 'p-combobox', 'p-date-input',
                'p-number-input',
                'p-otp-input', 'p-range-slider', 'p-rating', 'p-select',
                'p-slider', 'p-text-field', 'p-textarea',
            ])) {
                $previewProps['modelValue'] = $this->sampleValues[$index]
                    ?? $previewProps['modelValue']
                    ?? null;
            }
            if (
                $this->belongsTo([
                    'p-checkbox', 'p-radio',
                    'p-switch',
                ])
                && array_key_exists($index, $this->sampleValues)
            ) {
                $previewProps['checked'] = (bool) $this->sampleValues[$index];
            }
            if ($this->belongsTo(['p-stepper', 'p-stepper-vertical'])) {
                $itemTag = $this->tag === 'p-stepper'
                    ? 'p-stepper-item'
                    : 'p-stepper-vertical-item';
                $item = MaterialComponentMap::TAGS[$itemTag];
                $content = MaterialComponentMap::TAGS['p-stepper-window-item'];
                $horizontalStepStyle = new Style(
                    width: 92.0,
                    minWidth: 92.0,
                    paddingHorizontal: 8.0,
                );
                $activeHorizontalStepStyle = new Style(
                    width: 92.0,
                    minWidth: 92.0,
                    paddingHorizontal: 8.0,
                );
                $stepLabel = static function (
                    string $number,
                    string $label,
                    bool $active,
                ) use ($previewProps): Row {
                    return Row::make(
                        Text::make($number)->style(new Style(
                            width: 28.0,
                            height: 28.0,
                            borderRadius: 14.0,
                            backgroundColor: $active ? 0xFF0E6FA5 : 0xFFE8EEF6,
                            textColor: $active ? 0xFFFFFFFF : 0xFF5B6E87,
                            fontSize: 12.0,
                            lineHeight: 28.0,
                            fontWeight: 700,
                            textAlign: \Pam\Native\TextAlignment::Center,
                        )),
                        Text::make($label)->style(new Style(
                            textColor: $active ? 0xFF0E6FA5 : 0xFF5B6E87,
                            fontSize: 13.0,
                            lineHeight: 18.0,
                            fontWeight: $active ? 600 : 400,
                            flexShrink: 1.0,
                        )),
                    )->style(new Style(
                        gap: 6.0,
                        flexDirection: ($previewProps['altLabels'] ?? false)
                            ? \Pam\Native\FlexDirection::Column
                            : \Pam\Native\FlexDirection::Row,
                        alignItems: Align::Center,
                    ));
                };
                $steps = [
                    $item::make(
                        [
                            'value' => 'account',
                            'active' => true,
                            'selected' => true,
                            'complete' => true,
                        ],
                        $stepLabel('1', 'Account', true),
                    )->style(
                        $this->tag === 'p-stepper'
                            ? $activeHorizontalStepStyle
                            : new Style(),
                    ),
                    $item::make(
                        [
                            'value' => 'delivery',
                            'disabled' => !($previewProps['nonLinear'] ?? false),
                        ],
                        $stepLabel('2', 'Delivery', false),
                    )
                        ->style(
                            $this->tag === 'p-stepper'
                                ? $horizontalStepStyle
                                : new Style(),
                        ),
                    $item::make(
                        [
                            'value' => 'confirm',
                            'disabled' => !($previewProps['nonLinear'] ?? false),
                        ],
                        $stepLabel('3', 'Confirm', false),
                    )
                        ->style(
                            $this->tag === 'p-stepper'
                                ? $horizontalStepStyle
                                : new Style(),
                        ),
                ];
                if ($this->tag === 'p-stepper') {
                    $header = MaterialComponentMap::TAGS['p-stepper-header'];
                    $steps = [$header::make([], ...$steps)];
                }
                $steps[] = $content::make(
                        ['value' => 'account', 'selected' => true],
                        Column::make(
                            Text::make('Account details')->style(new Style(
                                fontSize: 18.0,
                                fontWeight: 600,
                                textColor: 0xFF0B172A,
                            )),
                            Text::make('Your profile is complete and ready for the next step.')
                                ->style(new Style(textColor: 0xFF5B6E87)),
                        )->style(new Style(padding: 20.0, gap: 8.0)),
                );
                $preview = $component::make($previewProps, ...$steps);
            } elseif ($this->tag === 'p-expansion-panels') {
                $panel = MaterialComponentMap::TAGS['p-expansion-panel'];
                $title = MaterialComponentMap::TAGS['p-expansion-panel-title'];
                $text = MaterialComponentMap::TAGS['p-expansion-panel-text'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $expandedPanel = $this->expandedPanels[$index] ?? 'details';
                $detailsExpanded = $expandedPanel === 'details';
                $deliveryExpanded = $expandedPanel === 'delivery';
                $supportExpanded = $expandedPanel === 'support';
                $previewProps['value'] = $expandedPanel;
                $previewProps['modelValue'] = $expandedPanel;
                $preview = $component::make(
                    $previewProps,
                    $panel::make(
                        [
                            'value' => 'details',
                            'open' => $detailsExpanded,
                            'expanded' => $detailsExpanded,
                        ],
                        $title::make([
                            'active' => $detailsExpanded,
                            'expanded' => $detailsExpanded,
                        ], Row::make(
                            Text::make('Product details'),
                            $icon::make([
                                'icon' => 'chevron-down',
                                'size' => 'small',
                            ])->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                rotation: $detailsExpanded ? 180.0 : 0.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            paddingHorizontal: 24.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make([
                            'active' => $detailsExpanded,
                            'expanded' => $detailsExpanded,
                        ], Text::make(
                            'Native components share the same tokens on Android and iOS.',
                        )),
                    )->onToggle(function (mixed $value) use ($index): bool {
                        $this->expandedPanels[$index] = $this->isEnabledValue($value)
                            ? 'details'
                            : '';

                        return true;
                    }),
                    $panel::make(
                        [
                            'value' => 'delivery',
                            'open' => $deliveryExpanded,
                            'expanded' => $deliveryExpanded,
                        ],
                        $title::make([
                            'active' => $deliveryExpanded,
                            'expanded' => $deliveryExpanded,
                        ], Row::make(
                            Text::make('Delivery'),
                            $icon::make([
                                'icon' => 'chevron-down',
                                'size' => 'small',
                            ])->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                rotation: $deliveryExpanded ? 180.0 : 0.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            paddingHorizontal: 24.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make(
                            [
                                'active' => $deliveryExpanded,
                                'expanded' => $deliveryExpanded,
                            ],
                            Text::make('Fast, predictable native rendering.'),
                        ),
                    )->onToggle(function (mixed $value) use ($index): bool {
                        $this->expandedPanels[$index] = $this->isEnabledValue($value)
                            ? 'delivery'
                            : '';

                        return true;
                    }),
                    $panel::make(
                        [
                            'value' => 'support',
                            'open' => $supportExpanded,
                            'expanded' => $supportExpanded,
                        ],
                        $title::make([
                            'active' => $supportExpanded,
                            'expanded' => $supportExpanded,
                        ], Row::make(
                            Text::make('Support'),
                            $icon::make([
                                'icon' => 'chevron-down',
                                'size' => 'small',
                            ])->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                rotation: $supportExpanded ? 180.0 : 0.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            paddingHorizontal: 24.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make(
                            [
                                'active' => $supportExpanded,
                                'expanded' => $supportExpanded,
                            ],
                            Text::make('Accessible states and keyboard navigation.'),
                        ),
                    )->onToggle(function (mixed $value) use ($index): bool {
                        $this->expandedPanels[$index] = $this->isEnabledValue($value)
                            ? 'support'
                            : '';

                        return true;
                    }),
                );
            } elseif ($this->tag === 'p-carousel') {
                $item = MaterialComponentMap::TAGS['p-carousel-item'];
                $slideValues = ['overview', 'details', 'activity'];
                $currentSlideValue =
                    $this->sampleValues[$index]
                    ?? $previewProps['modelValue']
                    ?? $previewProps['value']
                    ?? 'overview';
                $currentSlide = is_string($currentSlideValue)
                    ? $currentSlideValue
                    : 'overview';
                if (!in_array($currentSlide, $slideValues, true)) {
                    $currentSlide = 'overview';
                }
                $setSlide = function (string $value) use ($index): bool {
                    $this->sampleValues[$index] = $value;

                    return true;
                };
                $previewProps['value'] = $currentSlide;
                $previewProps['modelValue'] = $currentSlide;
                $slides = [];
                $controls = [];
                $indicator = MaterialComponentMap::TAGS['p-btn'];
                $currentIndex = array_search(
                    $currentSlide,
                    $slideValues,
                    true,
                );
                    $previousSlide = $slideValues[
                        ($currentIndex + count($slideValues) - 1)
                        % count($slideValues)
                    ];
                    $nextSlide = $slideValues[
                        ($currentIndex + 1) % count($slideValues)
                    ];
                if ($previewProps['showArrows'] ?? false) {
                        $controls[] = $indicator::make(
                            [
                                'value' => 'carousel-control-previous',
                                'accessibilityLabel' => 'Previous slide',
                                'carouselControl' => true,
                                'variant' => 'flat',
                            ],
                            Text::make('<')->style(new Style(
                                fontSize: 24.0,
                                fontWeight: 700,
                                textColor: 0xFFFFFFFF,
                                textAlign: \Pam\Native\TextAlignment::Center,
                            )),
                        )->onPress(
                            fn (): bool => $setSlide($previousSlide),
                        )->style(new Style(
                            positionType: PositionType::Absolute,
                            left: 16.0,
                            top: 218.0,
                            width: 48.0,
                            height: 48.0,
                            minWidth: 48.0,
                            minHeight: 48.0,
                            borderRadius: 24.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                            backgroundColor: 0x66000000,
                        ));
                }
                if (!($previewProps['hideDelimiters'] ?? false)) {
                        $delimiters = [];
                        foreach ($slideValues as $slideIndex => $value) {
                            $active = $value === $currentSlide;
                            $delimiters[] = $indicator::make(
                                [
                                    'value' => 'carousel-delimiter-'.$value,
                                    'active' => $active,
                                    'selected' => $active,
                                    'accessibilityLabel' => 'Go to slide '.($slideIndex + 1),
                                    'variant' => 'flat',
                                ],
                            )->onPress(
                                fn (): bool => $setSlide($value),
                            )->style(new Style(
                                width: 12.0,
                                height: 12.0,
                                minWidth: 12.0,
                                minHeight: 12.0,
                                padding: 0.0,
                                borderRadius: 6.0,
                                backgroundColor: 0x99FFFFFF,
                            ));
                        }
                        $controls[] = Row::make(...$delimiters)->style(new Style(
                            positionType: PositionType::Absolute,
                            left: 0.0,
                            right: 0.0,
                            bottom: 18.0,
                            minHeight: 28.0,
                            gap: 10.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                        ));
                }
                if ($previewProps['showArrows'] ?? false) {
                        $controls[] = $indicator::make(
                            [
                                'value' => 'carousel-control-next',
                                'accessibilityLabel' => 'Next slide',
                                'carouselControl' => true,
                                'variant' => 'flat',
                            ],
                            Text::make('>')->style(new Style(
                                fontSize: 24.0,
                                fontWeight: 700,
                                textColor: 0xFFFFFFFF,
                                textAlign: \Pam\Native\TextAlignment::Center,
                            )),
                        )->onPress(
                            fn (): bool => $setSlide($nextSlide),
                        )->style(new Style(
                            positionType: PositionType::Absolute,
                            right: 16.0,
                            top: 218.0,
                            width: 48.0,
                            height: 48.0,
                            minWidth: 48.0,
                            minHeight: 48.0,
                            borderRadius: 24.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                            backgroundColor: 0x66000000,
                        ));
                }
                $slides[] = $item::make(
                        [
                            'value' => 'overview',
                        ],
                        Column::make(
                            Text::make('Native by design')->style(new Style(
                                fontSize: 22.0,
                                fontWeight: 700,
                                textColor: 0xFFFFFFFF,
                            )),
                            Text::make('Fast transitions with no web view.')
                                ->style(new Style(textColor: 0xFFD9EEFA)),
                        )->style(new Style(
                            widthPercent: 100.0,
                            heightPercent: 100.0,
                            padding: 24.0,
                            gap: 8.0,
                            alignItems: Align::Start,
                            justifyContent: Justify::Center,
                            backgroundColor: 0xFF0E6FA5,
                        )),
                )->style(new Style(
                    positionType: PositionType::Absolute,
                    left: 0.0,
                    top: 0.0,
                    widthPercent: 100.0,
                    heightPercent: 100.0,
                ));
                $slides[] = $item::make(
                    [
                        'value' => 'details',
                    ],
                    Column::make(
                        Text::make('Composable')->style(new Style(
                            fontSize: 22.0,
                            fontWeight: 700,
                            textColor: 0xFFFFFFFF,
                        )),
                        Text::make('Build complex flows from small native parts.')
                            ->style(new Style(textColor: 0xFFE4F6F2)),
                    )->style(new Style(
                        widthPercent: 100.0,
                        heightPercent: 100.0,
                        padding: 24.0,
                        gap: 8.0,
                        alignItems: Align::Start,
                        justifyContent: Justify::Center,
                        backgroundColor: 0xFF00897B,
                    )),
                )->style(new Style(
                    positionType: PositionType::Absolute,
                    left: 0.0,
                    top: 0.0,
                    widthPercent: 100.0,
                    heightPercent: 100.0,
                ));
                $slides[] = $item::make(
                    [
                        'value' => 'activity',
                    ],
                    Column::make(
                        Text::make('Accessible')->style(new Style(
                            fontSize: 22.0,
                            fontWeight: 700,
                            textColor: 0xFFFFFFFF,
                        )),
                        Text::make('Keyboard, screen reader and reduced motion ready.')
                            ->style(new Style(textColor: 0xFFFFF0DC)),
                    )->style(new Style(
                        widthPercent: 100.0,
                        heightPercent: 100.0,
                        padding: 24.0,
                        gap: 8.0,
                        alignItems: Align::Start,
                        justifyContent: Justify::Center,
                        backgroundColor: 0xFFF57C00,
                    )),
                )->style(new Style(
                    positionType: PositionType::Absolute,
                    left: 0.0,
                    top: 0.0,
                    widthPercent: 100.0,
                    heightPercent: 100.0,
                ));
                $preview = $component::make(
                    $previewProps,
                    ...$slides,
                    ...$controls,
                )->style(new Style(
                    positionType: PositionType::Relative,
                    widthPercent: 100.0,
                    minHeight: 500.0,
                    overflow: \Pam\Native\Overflow::Hidden,
                ));
            } elseif ($this->belongsTo(['p-menu', 'p-tooltip'])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                unset($previewProps['open']);
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'placement' => \Pam\MobileUi\Enum\Placement::BottomStart->value,
                        'closeOnClick' => true,
                        'accessibilityLabel' => $this->title.' example',
                    ],
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make($this->tag === 'p-menu' ? 'Open menu' : 'More information'),
                    )->style(new Style(width: $this->tag === 'p-menu' ? 144.0 : 184.0)),
                    $this->tag === 'p-menu'
                        ? Column::make(
                            $listItem::make(
                                ['value' => 'edit-profile'],
                                Text::make('Edit profile'),
                            )->onPress(static fn (): bool => true),
                            $listItem::make(
                                ['value' => 'manage-notifications'],
                                Text::make('Manage notifications'),
                            )->onPress(static fn (): bool => true),
                            $listItem::make(
                                ['value' => 'sign-out'],
                                Text::make('Sign out'),
                            )->onPress(static fn (): bool => true),
                        )->style(new Style(
                            minWidth: 220.0,
                            paddingVertical: 8.0,
                            gap: 0.0,
                        ))
                        : Text::make('Rendered by a native anchored overlay.')
                            ->style(new Style(
                                textColor: 0xFFF7FAFF,
                                fontSize: 12.0,
                                lineHeight: 16.0,
                            )),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: $this->tag === 'p-menu' ? 220.0 : 48.0,
                ));
            } elseif ($this->belongsTo(['p-dialog', 'p-bottom-sheet', 'p-overlay'])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $open = (bool) ($this->sampleValues[$index] ?? false);
                $setOpen = function (bool $value) use ($index): bool {
                    $this->sampleValues[$index] = $value;

                    return true;
                };
                $show = $button::make(
                    ['variant' => 'tonal', 'size' => 'small'],
                    Text::make(match ($this->tag) {
                        'p-dialog' => 'Open dialog',
                        'p-bottom-sheet' => 'Open bottom sheet',
                        default => 'Show overlay',
                    }),
                )->style(new Style(width: 184.0))
                    ->onPress(fn (): bool => $setOpen(true));
                $close = $button::make(
                    [
                        'variant' => 'tonal',
                        'size' => 'small',
                        'value' => 'pam:modal-close',
                        'accessibilityLabel' => 'Close modal',
                    ],
                    Text::make('Close'),
                )->onPress(fn (): bool => $setOpen(false));
                $surface = $component::make(
                    [
                        ...$previewProps,
                        'open' => $open,
                        'dismissible' => true,
                        'persistent' => false,
                        'snapPoints' => $this->tag === 'p-bottom-sheet' ? [28] : null,
                        'accessibilityLabel' => $this->title.' example',
                    ],
                    Column::make(
                        Text::make(match ($this->tag) {
                            'p-dialog' => 'Discard draft?',
                            'p-bottom-sheet' => 'Choose an action',
                            default => 'Focused native content',
                        })->style(new Style(
                            textColor: 0xFF0B172A,
                            fontSize: 20.0,
                            lineHeight: 28.0,
                            fontWeight: 700,
                        )),
                        Text::make(match ($this->tag) {
                            'p-dialog' => 'Unsaved changes will be removed from this device.',
                            'p-bottom-sheet' => 'Share, duplicate or archive this item.',
                            default => 'Tap outside the surface to dismiss.',
                        })->style(new Style(
                            textColor: 0xFF5B6E87,
                            fontSize: 14.0,
                            lineHeight: 20.0,
                        )),
                        Row::make(
                            $close->style(new Style(width: 112.0)),
                        )->style(new Style(
                            widthPercent: 100.0,
                            justifyContent: Justify::End,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        maxWidth: 480.0,
                        minHeight: 140.0,
                        padding: $this->tag === 'p-bottom-sheet' ? 0.0 : 24.0,
                        gap: 16.0,
                        backgroundColor: $this->tag === 'p-bottom-sheet'
                            ? 0x00000000
                            : 0xFFFFFFFF,
                        borderRadius: $this->tag === 'p-bottom-sheet' ? 0.0 : 28.0,
                        elevation: $this->tag === 'p-overlay' ? 6.0 : 0.0,
                    )),
                )->onDismiss(fn (): bool => $setOpen(false));
                $preview = Column::make($show, $surface)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 72.0,
                    gap: 12.0,
                ));
            } elseif ($this->tag === 'p-snackbar') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                unset($previewProps['open']);
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'persistent' => true,
                        'accessibilityLabel' => $this->title.' notification',
                    ],
                    Text::make(
                        'Your changes were saved.',
                    )->style(new Style(
                        textColor: 0xFFF7FAFF,
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                    )),
                    $button::make(
                        [
                            'variant' => 'text',
                            'color' => 0xFF7DD3FC,
                            'size' => 'small',
                        ],
                        Text::make('Undo')
                            ->style(new Style(
                                textColor: 0xFF7DD3FC,
                                fontSize: 14.0,
                                fontWeight: 700,
                            )),
                    ),
                );
            } elseif ($this->tag === 'p-speed-dial') {
                $fab = MaterialComponentMap::TAGS['p-fab'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $speedDialOpen = (bool) ($this->sampleValues[$index] ?? false);
                $toggleSpeedDial = function () use ($index): bool {
                    $this->sampleValues[$index] = !(
                        $this->sampleValues[$index] ?? false
                    );

                    return true;
                };
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'open' => $speedDialOpen,
                        'modelValue' => $speedDialOpen,
                        'direction' => $previewProps['direction'] ?? 'bottom',
                        'placement' => \Pam\MobileUi\Enum\Placement::BottomStart->value,
                        'accessibilityLabel' => 'Create actions',
                    ],
                    $fab::make(
                        ['color' => 'primary', 'size' => 'small'],
                        Text::make('+')->style(new Style(
                            textColor: 0xFFFFFFFF,
                            fontSize: 24.0,
                            fontWeight: 500,
                        )),
                    )->onPress($toggleSpeedDial),
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make('New message'),
                    ),
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make('Upload file'),
                    ),
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make('Create folder'),
                    ),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 240.0,
                ));
            } elseif ($this->tag === 'p-badge') {
                $preview = $component::make(
                    $previewProps,
                    Text::make('8'),
                );
            } elseif ($this->belongsTo(['p-tabs', 'p-tab', 'p-slide-group'])) {
                $triggerTag = $this->tag === 'p-slide-group'
                    ? 'p-slide-group-item'
                    : 'p-tab';
                $trigger = MaterialComponentMap::TAGS[$triggerTag];
                $labels = match ($this->tag) {
                    'p-slide-group' => [
                        'Overview', 'Design', 'Android', 'iOS',
                        'Motion', 'Forms', 'Data', 'Release',
                    ],
                    default => ['Overview', 'Details', 'Activity'],
                };
                if (
                    $this->tag === 'p-slide-group'
                    && ($previewProps['centerActive'] ?? false)
                ) {
                    $labels = [
                        'Design', 'Android', 'iOS', 'Overview',
                        'Motion', 'Forms', 'Data', 'Release',
                    ];
                }
                if (
                    $this->tag === 'p-slide-group'
                    && ($previewProps['showArrows'] ?? false)
                ) {
                    $labels = ['‹', ...$labels, '›'];
                }
                $triggers = [];
                foreach ($labels as $label) {
                    $value = strtolower($label);
                    $active = $value === 'overview';
                    $triggerComponent = $trigger::make(
                        [
                            'value' => $value,
                            'active' => $active,
                            'selected' => $active,
                            '__pamButtonToggleItem' =>
                                $this->tag === 'p-slide-group',
                            'selectionCornerRadius' => $this->tag === 'p-slide-group'
                                ? 22
                                : 20,
                            ...array_intersect_key(
                                $previewProps,
                                array_flip([
                                    'disabled', 'fixedTabs', 'grow', 'stacked',
                                ]),
                            ),
                        ],
                        Text::make($label)->style(new Style(
                            textColor: $active ? 0xFF1867C0 : 0xFF5B6E87,
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            fontWeight: 500,
                            textAlign: \Pam\Native\TextAlignment::Center,
                        )),
                    );
                    if ($this->tag === 'p-slide-group') {
                        $triggerComponent = $triggerComponent->style(new Style(
                            width: 108.0,
                            minWidth: 108.0,
                            minHeight: 44.0,
                            paddingHorizontal: 12.0,
                            borderRadius: 22.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                            backgroundColor: 0xFFE8EEF6,
                        ));
                    }
                    $triggers[] = $triggerComponent;
                }
                $groupComponent = $this->tag === 'p-tab'
                    ? MaterialComponentMap::TAGS['p-tabs']
                    : $component;
                $group = $groupComponent::make(
                    [
                        ...$previewProps,
                        'value' => 'overview',
                        'modelValue' => 'overview',
                    ],
                    ...$triggers,
                );
                if ($this->tag === 'p-slide-group') {
                    $group = $group->style(new Style(
                        width: (float) count($triggers) * 116.0,
                        minHeight: 48.0,
                        gap: 8.0,
                    ));
                    $preview = ScrollView::make($group)
                        ->horizontal(true)
                        ->style(new Style(
                            widthPercent: 100.0,
                            minHeight: 56.0,
                        ));
                } else {
                    $preview = $group;
                }
            } elseif ($this->tag === 'p-btn-toggle') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $preview = $component::make(
                    [...$previewProps, 'value' => 'left'],
                    $button::make(
                        ['value' => 'left', 'variant' => 'outlined'],
                        Text::make('Left'),
                    ),
                    $button::make(
                        ['value' => 'center', 'variant' => 'outlined'],
                        Text::make('Center'),
                    ),
                    $button::make(
                        ['value' => 'right', 'variant' => 'outlined'],
                        Text::make('Right'),
                    ),
                )->style(new Style(widthPercent: 100.0, gap: 0.0));
            } elseif ($this->tag === 'p-card') {
                $cardActions = MaterialComponentMap::TAGS['p-card-actions'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $preview = $component::make(
                    $previewProps,
                    Text::make('Native experience')->style(new Style(
                        paddingHorizontal: 16.0,
                        paddingTop: 16.0,
                        fontSize: 20.0,
                        lineHeight: 28.0,
                        fontWeight: 600,
                        textColor: $theme->color(ColorToken::OnSurface),
                    )),
                    Text::make('Android and iOS')->style(new Style(
                        paddingHorizontal: 16.0,
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        textColor: $theme->color(ColorToken::MutedForeground),
                        opacity: 0.72,
                    )),
                    Text::make(
                        'One component API, rendered with platform-native views.',
                    )->style(new Style(
                        padding: 16.0,
                        fontSize: 15.0,
                        lineHeight: 22.0,
                        textColor: $theme->color(ColorToken::OnSurface),
                    )),
                    $cardActions::make(
                        [],
                        $button::make(
                            ['variant' => 'text'],
                            Text::make('Details'),
                        ),
                        $button::make(
                            ['variant' => 'tonal'],
                            Text::make('Continue'),
                        ),
                    ),
                );
            } elseif ($this->belongsTo(['p-app-bar', 'p-toolbar'])) {
                $button = MaterialComponentMap::TAGS['p-icon-btn'];
                $barChildren = [];
                if ($this->tag === 'p-app-bar') {
                    $nav = MaterialComponentMap::TAGS['p-app-bar-nav-icon'];
                    $barChildren[] = $nav::make(
                        [
                            'accessibilityLabel' => 'Open navigation',
                            'icon' => 'menu',
                        ],
                    );
                }
                $barChildren[] = Text::make('PAM Workspace')->style(
                    new Style(fontSize: 20.0, fontWeight: 500, flexGrow: 1.0),
                );
                $barChildren[] = $button::make(
                    [
                        'accessibilityLabel' => 'More options',
                        'icon' => 'more-vert',
                    ],
                );
                $preview = $component::make($previewProps, ...$barChildren);
            } elseif ($this->tag === 'p-list') {
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $preview = $component::make(
                    $previewProps,
                    $listItem::make(
                        ['value' => 'design'],
                        Text::make('Design system'),
                        Text::make('Tokens and components'),
                    ),
                    $listItem::make(
                        ['value' => 'native'],
                        Text::make('Native runtime'),
                        Text::make('Android and iOS'),
                    ),
                    $listItem::make(
                        ['value' => 'release'],
                        Text::make('Release'),
                        Text::make('Validated packages'),
                    ),
                );
            } elseif ($this->tag === 'p-infinite-scroll') {
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $infiniteItems = [];
                foreach (['Design', 'Engineering', 'Product', 'Research'] as $team) {
                    $infiniteItems[] = $listItem::make(
                        ['value' => strtolower($team)],
                        Text::make($team),
                    );
                }
                $infiniteItems[] = Column::make(
                    ActivityIndicator::make()->color(0xFF0E6FA5),
                    Text::make('Loading more')->style(new Style(
                        fontSize: 12.0,
                        textColor: 0xFF5B6E87,
                    )),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 64.0,
                    gap: 8.0,
                    alignItems: Align::Center,
                ));
                $preview = $component::make($previewProps, ...$infiniteItems)
                    ->style(new Style(widthPercent: 100.0, minHeight: 264.0));
            } elseif ($this->tag === 'p-empty-state') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $preview = $component::make(
                    $previewProps,
                    Text::make('Nothing here yet')->style(new Style(
                        fontSize: 20.0,
                        lineHeight: 28.0,
                        fontWeight: 600,
                        textColor: 0xFF0B172A,
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                    Text::make('Create the first item to get started.')->style(new Style(
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        textColor: 0xFF5B6E87,
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                    $button::make(
                        ['variant' => 'tonal'],
                        Text::make('Create item'),
                    ),
                );
            } elseif ($this->tag === 'p-timeline') {
                $item = MaterialComponentMap::TAGS['p-timeline-item'];
                $preview = $component::make(
                    $previewProps,
                    $item::make([], Text::make('Design tokens')),
                    $item::make([], Text::make('Native render')),
                    $item::make([], Text::make('Release')),
                );
            } elseif ($this->tag === 'p-list-item') {
                $preview = $component::make(
                    $previewProps,
                    Text::make('Primary label'),
                    Text::make('Supporting text'),
                );
            } elseif ($this->tag === 'p-chip-group') {
                $chip = MaterialComponentMap::TAGS['p-chip'];
                $preview = $component::make(
                    $previewProps,
                    $chip::make(['value' => 'one'], Text::make('One')),
                    $chip::make(['value' => 'two'], Text::make('Two')),
                    $chip::make(['value' => 'three'], Text::make('Three')),
                    $chip::make(['value' => 'four'], Text::make('Four')),
                );
            } elseif ($this->tag === 'p-divider') {
                $preview = Column::make(
                    Text::make('Content above'),
                    $component::make($previewProps),
                    Text::make('Content below'),
                )->style(new Style(
                    widthPercent: 100.0,
                    gap: 12.0,
                ));
            } elseif ($this->belongsTo([
                'p-alert', 'p-banner',
            ])) {
                $actionsTag = $this->tag === 'p-alert'
                    ? null
                    : 'p-banner-actions';
                $button = MaterialComponentMap::TAGS['p-btn'];
                $copy = Column::make(
                    Text::make(
                        $this->tag === 'p-alert'
                            ? 'Your workspace is ready'
                            : 'A new native release is available',
                    )->style(new Style(fontSize: 20.0, fontWeight: 600)),
                    Text::make(
                        'The same tokens, spacing and interactions are shared by Android and iOS.',
                    ),
                )->style(new Style(
                    flexGrow: 1.0,
                    flexShrink: 1.0,
                    gap: 4.0,
                ));
                $children = [$copy];
                if ($actionsTag !== null) {
                    $actions = MaterialComponentMap::TAGS[$actionsTag];
                    $children[] = $actions::make(
                        [],
                        $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Later'),
                        ),
                        $button::make(
                            ['variant' => 'tonal', 'size' => 'small'],
                            Text::make('Update'),
                        ),
                    );
                }
                $preview = $component::make($previewProps, ...$children);
            } elseif ($this->tag === 'p-btn-group') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $preview = $component::make(
                    $previewProps,
                    $button::make(['variant' => 'outlined'], Text::make('Day')),
                    $button::make(['variant' => 'outlined'], Text::make('Week')),
                    $button::make(['variant' => 'outlined'], Text::make('Month')),
                )->style(new Style(widthPercent: 100.0, gap: 0.0));
            } elseif ($this->belongsTo([
                'p-radio-group', 'p-item-group',
            ])) {
                $itemTag = match ($this->tag) {
                    'p-radio-group' => 'p-radio',
                    'p-item-group' => 'p-item',
                    default => 'p-checkbox',
                };
                $item = MaterialComponentMap::TAGS[$itemTag];
                $preview = $component::make(
                    $previewProps,
                    $item::make(
                        [
                            'value' => 'design',
                            'label' => 'Design',
                            'checked' => true,
                            'selected' => true,
                        ],
                    ),
                    $item::make(
                        ['value' => 'engineering', 'label' => 'Engineering'],
                    ),
                    $item::make(
                        ['value' => 'product', 'label' => 'Product'],
                    ),
                );
            } elseif ($this->belongsTo([
                'p-card-actions',
            ])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $children = match ($this->tag) {
                    default => [
                        $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Cancel'),
                        ),
                        $button::make(
                            ['variant' => 'tonal', 'size' => 'small'],
                            Text::make('Continue'),
                        ),
                    ],
                };
                $preview = $component::make($previewProps, ...$children);
            } elseif ($this->belongsTo([
                'p-checkbox', 'p-radio',
                'p-switch',
            ])) {
                $preview = $component::make($previewProps);
            } elseif ($this->tag === 'p-time-picker') {
                $preview = $component::make($previewProps);
            } elseif ($this->belongsTo([
                'p-calendar-day',
            ])) {
                $preview = $component::make(
                    $previewProps,
                    Text::make(match ($this->tag) {
                        'p-calendar-day' => '15',
                        default => 'Native picker surface',
                    }),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 56.0,
                ));
            } elseif ($this->tag === 'p-expansion-panel') {
                $title = MaterialComponentMap::TAGS['p-expansion-panel-title'];
                $text = MaterialComponentMap::TAGS['p-expansion-panel-text'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $preview = $component::make(
                    [...$previewProps, 'open' => true, 'expanded' => true],
                    $title::make(
                        ['active' => true, 'expanded' => true],
                        Row::make(
                            Text::make('Product details')->style(new Style(
                                fontSize: 16.0,
                                lineHeight: 24.0,
                                fontWeight: 500,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            $icon::make([
                                'icon' => 'chevron-down',
                                'size' => 'small',
                            ])->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                rotation: 180.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            paddingHorizontal: 24.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        )),
                    ),
                    $text::make(
                        ['active' => true, 'expanded' => true],
                        Text::make(
                            'Native views with shared material tokens.',
                        )->style(new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                        )),
                    ),
                );
            } elseif ($this->belongsTo([
                'p-expansion-panel-title',
                'p-expansion-panel-text',
            ])) {
                $panelText = match ($this->tag) {
                    'p-expansion-panel-title' => 'Product details',
                    'p-expansion-panel-text' => 'Native views with shared material tokens.',
                    default => 'Product details and supporting content',
                };
                $preview = $component::make(
                    [...$previewProps, 'open' => true, 'expanded' => true],
                    Text::make($panelText)->style(new Style(
                        fontSize: 16.0,
                        lineHeight: 24.0,
                        fontWeight: $this->tag === 'p-expansion-panel-title'
                            ? 500
                            : 400,
                        textColor: $this->tag === 'p-expansion-panel-text'
                            ? $theme->color(ColorToken::MutedForeground)
                            : $theme->color(ColorToken::OnSurface),
                    )),
                );
            } elseif ($this->tag === 'p-form') {
                $field = MaterialComponentMap::TAGS['p-text-field'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $preview = $component::make(
                    $previewProps,
                    $field::make([
                        'label' => 'Project name',
                        'modelValue' => 'Aurora',
                        'variant' => 'outlined',
                        'required' => true,
                    ]),
                    $field::make([
                        'label' => 'Owner',
                        'modelValue' => 'Design team',
                        'variant' => 'outlined',
                    ]),
                    $button::make(
                        ['variant' => 'flat', 'color' => 'primary', 'block' => true],
                        Text::make('Save project'),
                    ),
                )->style(new Style(widthPercent: 100.0, gap: 16.0));
            } elseif ($this->belongsTo([

            ])) {
                $tile = static fn (string $label, int $color): View => View::make(
                    Text::make($label)->style(new Style(
                        textColor: 0xFFFFFFFF,
                        fontWeight: 700,
                    )),
                )->style(new Style(
                    minHeight: 56.0,
                    padding: 16.0,
                    borderRadius: 8.0,
                    backgroundColor: $color,
                    alignItems: Align::Center,
                    justifyContent: Justify::Center,
                    flexGrow: 1.0,
                ));
                $preview = $component::make(
                    $previewProps,
                    $tile('One', 0xFF0E6FA5),
                    $tile('Two', 0xFF00897B),
                    $tile('Three', 0xFFF57C00),
                );
            } elseif ($this->belongsTo([
                'p-img',])) {
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'source' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1200',
                        'alt' => 'Forest landscape',
                    ],
                    Column::make(
                        Text::make('Native media')->style(new Style(
                            textColor: 0xFFFFFFFF,
                            fontSize: 22.0,
                            fontWeight: 700,
                        )),
                        Text::make('Cover, contain, ratio and loading states')
                            ->style(new Style(textColor: 0xFFE6F4FB)),
                    )->style(new Style(
                        widthPercent: 100.0,
                        minHeight: 180.0,
                        padding: 24.0,
                        gap: 6.0,
                        borderRadius: 12.0,
                        backgroundColor: 0xFF0E6FA5,
                        justifyContent: Justify::End,
                    )),
                )->style(new Style(widthPercent: 100.0, minHeight: 180.0));
            } elseif ($this->belongsTo([
                'p-item', 'p-slide-group-item', 'p-tab',
            ])) {
                $preview = $component::make(
                    $previewProps,
                    Text::make($this->title),
                );
            } elseif ($this->tag === 'p-sheet') {
                $preview = $component::make(
                    $previewProps,
                    Text::make('Native sheet surface')->style(new Style(
                        fontSize: 18.0,
                        fontWeight: 600,
                    )),
                    Text::make('Elevation, borders, shapes and semantic colors.'),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 120.0,
                    padding: 20.0,
                    gap: 8.0,
                ));
            } elseif ($this->belongsTo([
                'p-skeleton-loader', 'p-sparkline',
            ])) {
                $previewStyle = $this->tag === 'p-sparkline'
                    ? new Style(widthPercent: 100.0, minHeight: 140.0)
                    : new Style(widthPercent: 100.0);
                $preview = $component::make($previewProps)->style($previewStyle);
            } elseif ($this->belongsTo([
                'p-stepper-header', 'p-stepper-item', 'p-stepper-window',
                'p-stepper-window-item', 'p-stepper-actions',
                'p-stepper-vertical-item', 'p-stepper-vertical-actions',
            ])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $children = str_contains($this->tag, 'actions')
                    ? [
                        $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Back'),
                        ),
                        $button::make(
                            ['variant' => 'tonal', 'size' => 'small'],
                            Text::make('Continue'),
                        ),
                    ]
                    : [
                        Text::make(
                            str_contains($this->tag, 'item')
                                ? '1  Account'
                                : 'Account details',
                        ),
                    ];
                $preview = $component::make(
                    [...$previewProps, 'active' => true, 'selected' => true],
                    ...$children,
                );
            } elseif ($this->tag === 'p-avatar') {
                $avatarSize = match ($previewProps['size'] ?? null) {
                    'x-small' => 24.0,
                    'small' => 32.0,
                    'large' => 48.0,
                    'x-large' => 56.0,
                    default => is_numeric($previewProps['size'] ?? null)
                        ? (float) $previewProps['size']
                        : 40.0,
                };
                $preview = $component::make(
                    $previewProps,
                    Text::make('PA')->style(new Style(
                        fontSize: max(9.0, $avatarSize * 0.36),
                        lineHeight: max(12.0, $avatarSize * 0.48),
                        fontWeight: 600,
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                );
            } elseif ($this->tag === 'p-treeview-item') {
                $preview = $component::make(
                    [...$previewProps, 'open' => true, 'selected' => true],
                    Text::make('▾  Mobile'),
                    Text::make('    Android'),
                    Text::make('    iOS'),
                );
            } else {
                $preview = $this->generatesNativeAnatomy()
                    ? $component::make($previewProps)
                    : $component::make(
                        $previewProps,
                        Text::make($this->previewText()),
                    );
            }
            if ($this->belongsTo([
                'p-autocomplete', 'p-color-input', 'p-combobox', 'p-date-input',
                'p-number-input',
                'p-otp-input', 'p-range-slider', 'p-rating', 'p-select',
                'p-slider', 'p-text-field', 'p-textarea',
            ])) {
                if ($preview instanceof UiComponent) {
                    $preview = $preview->onChange(
                        function (mixed $value) use ($index): bool {
                            $this->sampleValues[$index] = $value;

                            return true;
                        },
                    );
                }
            }
            if (
                $preview instanceof UiComponent
                && $this->belongsTo([
                    'p-checkbox', 'p-radio',
                    'p-switch',
                ])
            ) {
                $preview = $preview->onToggle(
                    function (mixed $value) use ($index): bool {
                        $this->sampleValues[$index] = in_array(
                            $value,
                            [true, 1, '1'],
                            true,
                        );

                        return true;
                    },
                );
            }
            if ($this->tag === 'p-snackbar') {
                $preview = View::make($preview)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: ($previewProps['vertical'] ?? false) === true
                        ? 112.0
                        : (($previewProps['multiLine'] ?? false) === true
                            ? 88.0
                            : 72.0),
                    positionType: PositionType::Relative,
                ));
            }
            $intrinsicPreview = $this->belongsTo([
                'p-avatar', 'p-badge', 'p-btn', 'p-checkbox',
                'p-chip', 'p-fab', 'p-icon', 'p-icon-btn', 'p-progress-circular',
                'p-radio', 'p-rating', 'p-switch',
            ]);
            $samples[] = Column::make($caption, $preview)
                ->style(new Style(
                    widthPercent: 100.0,
                    paddingVertical: 4.0,
                    gap: 4.0,
                    alignItems: $intrinsicPreview ? Align::Start : Align::Stretch,
                ));
        }

        $menu = Pressable::make(
            Text::make('Menu')->style(new Style(
                textColor: $theme->color(ColorToken::PrimaryForeground),
                fontSize: 14.0,
                fontWeight: 600,
                lineHeight: 20.0,
            )),
        )
            ->onPress(function (): bool {
                $this->drawer->openDrawer();

                return true;
            })
            ->style(new Style(
                minWidth: 64.0,
                minHeight: 48.0,
                paddingHorizontal: 12.0,
                borderRadius: 24.0,
                backgroundColor: $theme->color(ColorToken::Primary),
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Open component navigation');

        $heading = Row::make(
            Column::make(
                Text::make($this->title)->style(new Style(
                    textColor: $theme->color(ColorToken::OnSurface),
                    fontSize: 22.0,
                    lineHeight: 28.0,
                    fontWeight: 700,
                )),
                Text::make($this->tag)->style(new Style(
                    textColor: $theme->color(ColorToken::MutedForeground),
                    fontSize: 14.0,
                    lineHeight: 20.0,
                )),
            )->style(new Style(
                widthPercent: 70.0,
                flexGrow: 1.0,
                flexShrink: 1.0,
                gap: 2.0,
            )),
            $menu,
        )->style(new Style(
            widthPercent: 100.0,
            minHeight: 72.0,
            paddingHorizontal: 16.0,
            paddingVertical: 10.0,
            gap: 12.0,
            alignItems: Align::Center,
            backgroundColor: $theme->color(ColorToken::Background),
            elevation: 1.0,
        ));

        return SafeAreaView::make(
            Column::make(
                $heading,
                ScrollView::make(
                    Column::make(
                        Text::make('Variations')->style(new Style(
                            textColor: $theme->color(ColorToken::OnSurface),
                            fontSize: 18.0,
                            lineHeight: 24.0,
                            fontWeight: 600,
                        )),
                        ...$samples,
                    )->style(new Style(
                        widthPercent: 100.0,
                        padding: 16.0,
                        paddingBottom: 24.0,
                        gap: 8.0,
                    )),
                )->style(new Style(
                    widthPercent: 100.0,
                    flexGrow: 1.0,
                    flexShrink: 1.0,
                )),
            )->style(new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                flexGrow: 1.0,
                backgroundColor: $theme->color(ColorToken::Background),
            )),
        )->style(new Style(
            widthPercent: 100.0,
            heightPercent: 100.0,
            flexGrow: 1.0,
            backgroundColor: $theme->color(ColorToken::Background),
        ));
    }

    /**
     * @return list<array{label: string, props: array<string, mixed>}>
     */
    private function variations(): array
    {
        $variations = [
            ['label' => 'Default', 'props' => []],
        ];

        if ($this->belongsTo([
            'p-alert', 'p-avatar', 'p-btn', 'p-card', 'p-chip',
            'p-fab', 'p-icon-btn', 'p-list-item', 'p-sheet',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Elevated', 'props' => ['variant' => 'elevated', 'elevation' => 3]],
                ['label' => 'Flat', 'props' => ['variant' => 'flat', 'elevation' => 0]],
                ['label' => 'Tonal', 'props' => ['variant' => 'tonal']],
                ['label' => 'Outlined', 'props' => ['variant' => 'outlined', 'border' => true]],
                ['label' => 'Text', 'props' => ['variant' => 'text']],
                ['label' => 'Plain', 'props' => ['variant' => 'plain']],
            ]);
        }

        if ($this->tag === 'p-btn') {
            $variations = array_merge($variations, [
                ['label' => 'Block', 'props' => ['block' => true]],
                ['label' => 'Rounded', 'props' => ['rounded' => true]],
                ['label' => 'Pill', 'props' => ['rounded' => 'pill']],
                ['label' => 'Tile', 'props' => ['tile' => true]],
                ['label' => 'Slim', 'props' => ['slim' => true]],
            ]);
        }

        if ($this->belongsTo(['p-card', 'p-sheet'])) {
            $variations = array_merge($variations, [
                ['label' => 'Rounded XL', 'props' => ['rounded' => 'xl']],
                ['label' => 'Tile', 'props' => ['tile' => true]],
            ]);
        }

        if ($this->belongsTo([
            'p-alert', 'p-avatar', 'p-badge', 'p-btn', 'p-card', 'p-chip',
            'p-fab', 'p-icon', 'p-icon-btn', 'p-progress-circular',
            'p-progress-linear', 'p-rating', 'p-sheet', 'p-switch',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Primary', 'props' => ['color' => 'primary']],
                ['label' => 'Secondary', 'props' => ['color' => 'secondary']],
                ['label' => 'Success', 'props' => ['color' => 'success']],
                ['label' => 'Information', 'props' => ['color' => 'info']],
                ['label' => 'Warning', 'props' => ['color' => 'warning']],
                ['label' => 'Error', 'props' => ['color' => 'error']],
            ]);
        }

        if ($this->belongsTo([
            'p-avatar', 'p-badge', 'p-btn', 'p-checkbox',
            'p-chip', 'p-fab', 'p-icon', 'p-icon-btn', 'p-progress-circular',
            'p-radio', 'p-rating', 'p-switch',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'X Small', 'props' => ['size' => 'x-small']],
                ['label' => 'Small', 'props' => ['size' => 'small']],
                ['label' => 'Large', 'props' => ['size' => 'large']],
                ['label' => 'X Large', 'props' => ['size' => 'x-large']],
            ]);
        }

        if ($this->belongsTo([
            'p-alert', 'p-autocomplete', 'p-btn', 'p-checkbox', 'p-chip',
            'p-combobox', 'p-data-table',
            'p-data-table-virtual',
            'p-list', 'p-list-item', 'p-number-input', 'p-radio', 'p-select',
            'p-slider', 'p-switch', 'p-tab', 'p-tabs',
            'p-text-field', 'p-textarea',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Comfortable', 'props' => ['density' => 'comfortable']],
                ['label' => 'Compact', 'props' => ['density' => 'compact']],
            ]);
        }

        if ($this->belongsTo([
            'p-app-bar', 'p-card', 'p-dialog', 'p-menu',
            'p-sheet', 'p-snackbar', 'p-toolbar',
        ])) {
            foreach ([0, 1, 2, 4, 8, 12, 16, 24] as $elevation) {
                $variations[] = [
                    'label' => 'Elevation '.$elevation,
                    'props' => ['elevation' => $elevation],
                ];
            }
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-btn', 'p-chip', 'p-color-input',
            'p-combobox', 'p-date-input', 'p-number-input',
            'p-otp-input', 'p-select', 'p-text-field', 'p-textarea',
        ])) {
            $variations[] = ['label' => 'Disabled', 'props' => ['disabled' => true]];
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-btn', 'p-color-input', 'p-combobox',
            'p-date-input', 'p-otp-input', 'p-select',
        ])) {
            $variations[] = ['label' => 'Loading', 'props' => ['loading' => true]];
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-color-input', 'p-combobox', 'p-date-input',
            'p-number-input', 'p-otp-input', 'p-select',
            'p-text-field', 'p-textarea',
        ])) {
            $variations[] = ['label' => 'Read Only', 'props' => ['readonly' => true]];
            $variations[] = [
                'label' => 'Error',
                'props' => ['error' => true, 'helper' => 'This value needs attention'],
            ];
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-color-input', 'p-combobox', 'p-date-input',
            'p-number-input', 'p-select',
            'p-text-field', 'p-textarea',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Filled', 'props' => ['variant' => 'filled']],
                ['label' => 'Outlined', 'props' => ['variant' => 'outlined']],
                ['label' => 'Underlined', 'props' => ['variant' => 'underlined']],
                ['label' => 'Solo', 'props' => ['variant' => 'solo']],
                ['label' => 'Solo inverted', 'props' => ['variant' => 'solo-inverted']],
                ['label' => 'Solo filled', 'props' => ['variant' => 'solo-filled']],
                ['label' => 'Plain', 'props' => ['variant' => 'plain']],
            ]);
            $variations[] = [
                'label' => 'Hint and required',
                'props' => [
                    'required' => true,
                    'helper' => 'Required field',
                    'persistentHint' => true,
                ],
            ];
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-combobox', 'p-select',
            'p-text-field', 'p-textarea',
        ])) {
            $variations[] = [
                'label' => 'Clearable',
                'props' => [
                    'clearable' => true,
                    'modelValue' => 'Clear this value',
                ],
            ];
        }

        if ($this->belongsTo([
            'p-text-field', 'p-textarea',
        ])) {
            $variations[] = [
                'label' => 'Prefix and suffix',
                'props' => [
                    'prefix' => '$',
                    'suffix' => 'USD',
                    'modelValue' => '120',
                ],
            ];
        }

        if ($this->belongsTo(['p-text-field', 'p-textarea'])) {
            $variations[] = [
                'label' => 'Counter',
                'props' => [
                    'counter' => true,
                    'maxLength' => 40,
                    'modelValue' => 'Native value',
                ],
            ];
        }

        if ($this->belongsTo([
            'p-checkbox', 'p-radio', 'p-switch',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Off', 'props' => ['checked' => false]],
                ['label' => 'On', 'props' => ['checked' => true]],
                ['label' => 'Disabled', 'props' => ['checked' => true, 'disabled' => true]],
            ]);
        }

        if ($this->belongsTo(['p-checkbox'])) {
            $variations[] = [
                'label' => 'Indeterminate',
                'props' => ['checked' => false, 'indeterminate' => true],
            ];
        }

        if ($this->belongsTo(['p-slider', 'p-range-slider'])) {
            $variations = array_merge($variations, [
                ['label' => 'Minimum', 'props' => ['modelValue' => 0]],
                ['label' => 'Maximum', 'props' => ['modelValue' => 100]],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
            ]);
        }

        if ($this->belongsTo(['p-progress-circular', 'p-progress-linear'])) {
            $variations[] = ['label' => 'Indeterminate', 'props' => ['indeterminate' => true]];
            $variations[] = ['label' => 'Thin', 'props' => ['height' => 2, 'width' => 2]];
            $variations[] = ['label' => 'Thick', 'props' => ['height' => 8, 'width' => 6]];
        }

        if ($this->tag === 'p-skeleton-loader') {
            $variations = [
                ['label' => 'Text', 'props' => ['type' => 'text']],
                ['label' => 'Heading', 'props' => ['type' => 'heading']],
                ['label' => 'Avatar', 'props' => ['type' => 'avatar']],
                ['label' => 'Chip', 'props' => ['type' => 'chip']],
                ['label' => 'Button', 'props' => ['type' => 'button']],
                ['label' => 'Image', 'props' => ['type' => 'image']],
                ['label' => 'Card', 'props' => ['type' => 'card']],
                ['label' => 'Article', 'props' => ['type' => 'article']],
            ];
        }

        $variations = array_merge($variations, $this->componentVariations());
        $unique = [];
        foreach ($variations as $variation) {
            $unique[$variation['label']] = $variation;
        }

        return array_values($unique);
    }

    /**
     * Mobile-relevant Vuetify variations that belong to a specific component
     * family. Keeping these profiles here makes every catalog route explicit
     * instead of silently falling back to a single generic preview.
     *
     * @return list<array{label: string, props: array<string, mixed>}>
     */
    private function componentVariations(): array
    {
        $variations = [];
        $add = static function (
            array &$target,
            string $label,
            array $props,
        ): void {
            $target[] = ['label' => $label, 'props' => $props];
        };

        if ($this->belongsTo([
            'p-app-bar', 'p-toolbar',
        ])) {
            $add($variations, 'Prominent', ['prominent' => true]);
            $add($variations, 'Compact', ['density' => 'compact']);
            $add($variations, 'Primary', ['color' => 'primary']);
            $add($variations, 'Flat', ['flat' => true, 'elevation' => 0]);
        }

        if ($this->tag === 'p-app-bar-nav-icon') {
            $add($variations, 'Small', ['size' => 'small']);
            $add($variations, 'Large', ['size' => 'large']);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Primary', ['color' => 'primary']);
        }

        if ($this->belongsTo([
            'p-alert', 'p-banner',
            'p-banner-actions', 'p-empty-state',
        ])) {
            $add($variations, 'Success', ['type' => 'success', 'color' => 'success']);
            $add($variations, 'Information', ['type' => 'info', 'color' => 'info']);
            $add($variations, 'Warning', ['type' => 'warning', 'color' => 'warning']);
            $add($variations, 'Error', ['type' => 'error', 'color' => 'error']);
            $add($variations, 'Closable', ['closable' => true]);
            $add($variations, 'Compact', ['density' => 'compact']);
        }

        if ($this->tag === 'p-alert') {
            foreach (['start', 'top', 'end', 'bottom'] as $border) {
                $add(
                    $variations,
                    'Border '.ucfirst($border),
                    ['border' => $border, 'variant' => 'tonal'],
                );
            }
        }

        if ($this->belongsTo(['p-avatar', 'p-badge'])) {
            $add($variations, 'Rounded', ['rounded' => true]);
            $add($variations, 'Tile', ['tile' => true]);
            $add($variations, 'Bordered', ['border' => true]);
        }

        if ($this->tag === 'p-badge') {
            $add($variations, 'Dot', ['dot' => true]);
        }





        if ($this->belongsTo(['p-btn-group', 'p-btn-toggle'])) {
            $add($variations, 'Divided', ['divided' => true]);
            $add($variations, 'Rounded', ['rounded' => true]);
            $add($variations, 'Tile', ['tile' => true]);
            $add($variations, 'Mandatory', ['mandatory' => true]);
            $add($variations, 'Multiple', ['multiple' => true]);
            $add($variations, 'Compact', ['density' => 'compact']);
        }

        if ($this->belongsTo([
            'p-card', 'p-card-actions', 'p-sheet',
        ])) {
            $add($variations, 'Bordered', ['border' => true, 'variant' => 'outlined']);
            $add($variations, 'Loading', ['loading' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Horizontal', ['direction' => 'horizontal']);
            $add($variations, 'Primary', ['color' => 'primary']);
        }

        if ($this->belongsTo(['p-carousel', 'p-carousel-item'])) {
            $add($variations, 'Continuous', ['continuous' => true]);
            $add($variations, 'Cycle', ['cycle' => true, 'interval' => 3000]);
            $add($variations, 'Hide Delimiters', ['hideDelimiters' => true]);
            $add($variations, 'Show Arrows', ['showArrows' => true]);
            $add($variations, 'Vertical', ['direction' => 'vertical', 'vertical' => true]);
            $add($variations, 'Reverse', ['reverse' => true]);
            $add($variations, 'Crossfade', ['transition' => 'fade']);
        }

        if ($this->belongsTo([
            'p-checkbox', 'p-radio', 'p-switch',
        ])) {
            $add($variations, 'Read Only', ['checked' => true, 'readonly' => true]);
            $add($variations, 'Error', ['error' => true, 'errorMessage' => 'Invalid choice']);
            $add($variations, 'Primary', ['checked' => true, 'color' => 'primary']);
            $add($variations, 'Success', ['checked' => true, 'color' => 'success']);
            $add($variations, 'Warning', ['checked' => true, 'color' => 'warning']);
            $add($variations, 'Error Color', ['checked' => true, 'color' => 'error']);
            $add($variations, 'Inline', ['inline' => true]);
        }

        if ($this->belongsTo([
            'p-radio-group', 'p-item-group',
            'p-chip-group',
        ])) {
            $add($variations, 'Mandatory', ['mandatory' => true]);
            $add($variations, 'Multiple', ['multiple' => true]);
            $add($variations, 'Column', ['direction' => 'vertical']);
            $add($variations, 'Inline', ['inline' => true, 'direction' => 'horizontal']);
            $add($variations, 'Disabled', ['disabled' => true]);
        }

        if ($this->belongsTo(['p-chip', 'p-chip-group'])) {
            $add($variations, 'Closable', ['closable' => true]);
            $add($variations, 'Filter', ['filter' => true, 'selected' => true]);
            $add($variations, 'Label', ['label' => true]);
            $add($variations, 'Link', ['link' => true]);
            $add($variations, 'Column', ['column' => true]);
        }

        if ($this->tag === 'p-color-input') {
            $add($variations, 'HEX', ['mode' => 'hex']);
            $add($variations, 'RGB', ['mode' => 'rgb']);
            $add($variations, 'HSL', ['mode' => 'hsl']);
            $add($variations, 'Swatches', ['showSwatches' => true]);
            $add($variations, 'Hide Inputs', ['hideInputs' => true]);
            $add($variations, 'Hide Canvas', ['hideCanvas' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
        }

        if ($this->belongsTo([
            'p-data-table', 'p-data-table-virtual',
        ])) {
            $add($variations, 'Compact', ['density' => 'compact']);
            $add($variations, 'Comfortable', ['density' => 'comfortable']);
            $add($variations, 'Fixed Header', ['fixedHeader' => true, 'height' => 260]);
            $add($variations, 'Striped', ['striped' => true]);
            $add($variations, 'Loading', ['loading' => true]);
            $add($variations, 'Selectable', ['showSelect' => true]);
            $add($variations, 'Mobile', ['mobile' => true]);
        }

        if ($this->belongsTo([
            'p-date-input', 'p-date-picker', 'p-calendar',
            'p-calendar-day',
        ])) {
            $add($variations, 'Multiple', ['multiple' => true]);
            $add($variations, 'Range', ['multiple' => 'range']);
            $add($variations, 'Landscape', ['landscape' => true]);
            $add($variations, 'Week Numbers', ['showWeek' => true]);
            $add($variations, 'Adjacent Months', ['showAdjacentMonths' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Read Only', ['readonly' => true]);
        }

        if ($this->belongsTo([
            'p-dialog', 'p-bottom-sheet', 'p-overlay',
        ])) {
            $add($variations, 'Persistent', ['persistent' => true]);
            $add($variations, 'No Scrim', ['scrim' => false]);
            $add($variations, 'Fullscreen', ['fullscreen' => true]);
            $add($variations, 'Width Small', ['width' => 320]);
            $add($variations, 'Width Large', ['width' => 560]);
        }

        if ($this->belongsTo([
            'p-divider',
        ])) {
            $add($variations, 'Vertical', ['vertical' => true, 'orientation' => 2]);
            $add($variations, 'Inset', ['inset' => true]);
            $add($variations, 'Thick', ['thickness' => 4]);
            $add($variations, 'Dashed', ['style' => 'dashed']);
            $add($variations, 'Primary', ['color' => 'primary']);
        }

        if ($this->belongsTo([
            'p-expansion-panels', 'p-expansion-panel',
            'p-expansion-panel-title', 'p-expansion-panel-text',
        ])) {
            $add($variations, 'Accordion', ['variant' => 'accordion']);
            $add($variations, 'Inset', ['variant' => 'inset']);
            $add($variations, 'Popout', ['variant' => 'popout']);
            $add($variations, 'Multiple', ['multiple' => true]);
            $add($variations, 'Mandatory', ['mandatory' => true]);
            $add($variations, 'Readonly', ['readonly' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
        }

        if ($this->tag === 'p-fab') {
            $add($variations, 'Extended', ['extended' => true, 'text' => 'Create']);
            $add($variations, 'Top Start', ['location' => 'top start']);
            $add($variations, 'Top End', ['location' => 'top end']);
            $add($variations, 'Bottom Start', ['location' => 'bottom start']);
            $add($variations, 'Bottom End', ['location' => 'bottom end']);
        }

        if ($this->belongsTo([

            'p-form',
        ])) {
            $add($variations, 'Focused', ['focused' => true]);
            $add($variations, 'Required', ['required' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Read Only', ['readonly' => true]);
            $add($variations, 'Error', ['error' => true, 'errorMessage' => 'Invalid value']);
            $add($variations, 'Success', ['success' => true, 'messages' => 'Looks good']);
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-combobox', 'p-select',
        ])) {
            $add($variations, 'Multiple', ['multiple' => true, 'chips' => true]);
            $add($variations, 'Chips', ['chips' => true]);
            $add($variations, 'Small Chips', ['chips' => true, 'smallChips' => true]);
            $add($variations, 'Return Object', ['returnObject' => true]);
            $add($variations, 'Hide Details', ['hideDetails' => true]);
            $add($variations, 'No Data', ['items' => []]);
        }

        if ($this->belongsTo(['p-text-field', 'p-textarea'])) {
            $add($variations, 'Prepend Icon', ['prependIcon' => 'SearchIcon']);
            $add($variations, 'Append Icon', ['appendIcon' => 'SettingsIcon']);
            $add($variations, 'Persistent Placeholder', ['persistentPlaceholder' => true]);
            $add($variations, 'Hide Details', ['hideDetails' => true]);
            $add($variations, 'Single Line', ['singleLine' => true]);
        }

        if ($this->tag === 'p-textarea') {
            $add($variations, 'Auto Grow', ['autoGrow' => true, 'rows' => 2]);
            $add($variations, 'No Resize', ['noResize' => true]);
            $add($variations, 'Four Rows', ['rows' => 4]);
        }

        if ($this->tag === 'p-number-input') {
            $add($variations, 'Inset Controls', ['controlVariant' => 'inset']);
            $add($variations, 'Split Controls', ['controlVariant' => 'split']);
            $add($variations, 'Stacked Controls', ['controlVariant' => 'stacked']);
            $add($variations, 'Precision', ['precision' => 2, 'step' => 0.25]);
            $add($variations, 'Reverse', ['reverse' => true]);
        }

        if ($this->tag === 'p-otp-input') {
            $add($variations, 'Four Digits', ['length' => 4]);
            $add($variations, 'Six Digits', ['length' => 6]);
            $add($variations, 'Masked', ['type' => 'password']);
            $add($variations, 'Divided', ['divider' => true]);
            $add($variations, 'Focused', ['focused' => true]);
        }

        if ($this->belongsTo([

        ])) {
            $add($variations, 'Compact Gap', ['gap' => 4]);
            $add($variations, 'Default Gap', ['gap' => 12]);
            $add($variations, 'Large Gap', ['gap' => 24]);
            $add($variations, 'Centered', ['align' => 'center', 'justify' => 'center']);
            $add($variations, 'Space Between', ['justify' => 'space-between']);
            $add($variations, 'Reverse', ['reverse' => true]);
        }

        if ($this->belongsTo([
            'p-icon', 'p-icon-btn',
        ])) {
            $add($variations, 'Start', ['start' => true]);
            $add($variations, 'End', ['end' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Loading', ['loading' => true]);
        }

        if ($this->belongsTo(['p-img'])) {
            $add($variations, 'Cover', ['cover' => true]);
            $add($variations, 'Contain', ['cover' => false]);
            $add($variations, 'Aspect 16:9', ['aspectRatio' => 16 / 9]);
            $add($variations, 'Aspect Square', ['aspectRatio' => 1]);
            $add($variations, 'Gradient', ['gradient' => 'to top, #0008, transparent']);
            $add($variations, 'Loading', ['loading' => true]);
        }

        if ($this->tag === 'p-infinite-scroll') {
            $add($variations, 'End', ['side' => 'end']);
            $add($variations, 'Both Sides', ['side' => 'both']);
            $add($variations, 'Manual', ['mode' => 'manual']);
            $add($variations, 'Empty', ['empty' => true]);
            $add($variations, 'Error', ['error' => true]);
        }

        if ($this->belongsTo(['p-item', 'p-slide-group-item', 'p-tab'])) {
            $add($variations, 'Active', ['active' => true, 'selected' => true]);
            $add($variations, 'Inactive', ['active' => false, 'selected' => false]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Primary', ['color' => 'primary']);
        }

        if ($this->belongsTo([
            'p-list', 'p-list-item',
        ])) {
            $add($variations, 'One Line', ['lines' => 'one']);
            $add($variations, 'Two Lines', ['lines' => 'two']);
            $add($variations, 'Three Lines', ['lines' => 'three']);
            $add($variations, 'Navigation', ['nav' => true]);
            $add($variations, 'Rounded', ['rounded' => true]);
            $add($variations, 'Slim', ['slim' => true]);
            $add($variations, 'Active', ['active' => true, 'color' => 'primary']);
        }

        if ($this->belongsTo(['p-menu', 'p-tooltip'])) {
            foreach ([
                'top', 'top start', 'top end', 'bottom', 'bottom start',
                'bottom end', 'start', 'end',
            ] as $location) {
                $add(
                    $variations,
                    'Location '.ucwords($location),
                    ['location' => $location, 'placement' => $location],
                );
            }
            $add($variations, 'Open On Click', ['openOnClick' => true]);
            $add($variations, 'Open On Long Press', ['openOnClick' => false, 'openOnContextmenu' => true]);
            $add($variations, 'Persistent', ['persistent' => true]);
        }



        if ($this->belongsTo(['p-progress-circular', 'p-progress-linear'])) {
            $add($variations, 'Reverse', ['reverse' => true]);
            $add($variations, 'Rounded', ['rounded' => true]);
            $add($variations, 'Striped', ['striped' => true]);
            $add($variations, 'Stream', ['stream' => true]);
            $add($variations, 'Buffer', ['modelValue' => 42, 'bufferValue' => 72]);
            $add($variations, 'Rotate', ['rotate' => 90]);
        }

        if ($this->tag === 'p-rating') {
            $add($variations, 'Half Increments', [
                'halfIncrements' => true,
                'step' => 0.5,
                'modelValue' => 3.5,
            ]);
            $add($variations, 'Clearable', ['clearable' => true]);
            $add($variations, 'Hover', ['hover' => true]);
            $add($variations, 'Read Only', ['readonly' => true]);
            $add($variations, 'Length 10', ['length' => 10, 'modelValue' => 7]);
            $add($variations, 'Dense', ['density' => 'compact']);
        }

        if ($this->belongsTo(['p-slider', 'p-range-slider'])) {
            $add($variations, 'Step 10', ['step' => 10, 'modelValue' => $this->tag === 'p-range-slider' ? [20, 80] : 60]);
            $add($variations, 'Ticks', ['step' => 10, 'ticks' => true]);
            $add($variations, 'Tick Labels', ['step' => 25, 'ticks' => 'always', 'tickLabels' => ['0', '25', '50', '75', '100']]);
            $add($variations, 'Thumb Label', ['thumbLabel' => true]);
            $add($variations, 'Always Thumb Label', ['thumbLabel' => 'always']);
            $add($variations, 'Vertical', ['vertical' => true, 'orientation' => 2]);
            $add($variations, 'Reverse', ['reverse' => true]);
            $add($variations, 'Read Only', ['readonly' => true]);
            $add($variations, 'Primary', ['color' => 'primary', 'trackColor' => 'secondary']);
            $add($variations, 'Success', ['color' => 'success']);
            $add($variations, 'Warning', ['color' => 'warning']);
            $add($variations, 'Error', ['color' => 'error']);
        }

        if ($this->tag === 'p-skeleton-loader') {
            $add($variations, 'Loading', ['loading' => true, 'type' => 'article']);
            $add($variations, 'Boilerplate', ['boilerplate' => true, 'type' => 'article']);
            $add($variations, 'Elevation', ['elevation' => 4, 'type' => 'card']);
        }

        if ($this->belongsTo(['p-slide-group', 'p-slide-group-item'])) {
            $add($variations, 'Center Active', ['centerActive' => true]);
            $add($variations, 'Show Arrows', ['showArrows' => true]);
            $add($variations, 'Mandatory', ['mandatory' => true]);
            $add($variations, 'Multiple', ['multiple' => true]);
            $add($variations, 'Column', ['direction' => 'vertical']);
            $add($variations, 'Disabled', ['disabled' => true]);
        }

        if ($this->tag === 'p-snackbar') {
            $add($variations, 'Top', ['location' => 'top']);
            $add($variations, 'Bottom', ['location' => 'bottom']);
            $add($variations, 'Multi Line', ['multiLine' => true]);
            $add($variations, 'Vertical', ['vertical' => true]);
            $add($variations, 'Timeout', ['timeout' => 3000]);
            $add($variations, 'Persistent', ['timeout' => -1, 'persistent' => true]);
            $add($variations, 'Success', ['color' => 'success']);
            $add($variations, 'Error', ['color' => 'error']);
        }

        if ($this->tag === 'p-sparkline') {
            $add($variations, 'Line', ['type' => 'trend']);
            $add($variations, 'Bars', ['type' => 'bar']);
            $add($variations, 'Fill', ['fill' => true]);
            $add($variations, 'Smooth', ['smooth' => true]);
            $add($variations, 'Labels', ['showLabels' => true]);
            $add($variations, 'Auto Draw', ['autoDraw' => true]);
        }

        if ($this->tag === 'p-speed-dial') {
            foreach (['top', 'bottom', 'start', 'end'] as $location) {
                $add($variations, 'Direction '.ucfirst($location), ['direction' => $location]);
            }
            $add($variations, 'Scale Transition', ['transition' => 'scale']);
            $add($variations, 'Persistent', ['persistent' => true]);
        }

        if ($this->belongsTo([
            'p-stepper', 'p-stepper-header', 'p-stepper-item',
            'p-stepper-window', 'p-stepper-window-item', 'p-stepper-actions',
            'p-stepper-vertical', 'p-stepper-vertical-item',
            'p-stepper-vertical-actions',
        ])) {
            $add($variations, 'Alternate Labels', ['altLabels' => true]);
            $add($variations, 'Editable', ['editable' => true]);
            $add($variations, 'Non Linear', ['nonLinear' => true]);
            $add($variations, 'Mobile', ['mobile' => true]);
            $add($variations, 'Flat', ['flat' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Error Step', ['error' => true]);
            $add($variations, 'Complete Step', ['complete' => true]);
        }

        if ($this->belongsTo(['p-tabs', 'p-tab'])) {
            $add($variations, 'Grow', ['grow' => true]);
            $add($variations, 'Centered', ['alignTabs' => 'center']);
            $add($variations, 'End', ['alignTabs' => 'end']);
            $add($variations, 'Fixed Tabs', ['fixedTabs' => true]);
            $add($variations, 'Stacked', ['stacked' => true]);
            $add($variations, 'Vertical', ['direction' => 'vertical']);
            $add($variations, 'Hide Slider', ['hideSlider' => true]);
        }

        if ($this->tag === 'p-time-picker') {
            $add($variations, '24 Hour', ['format' => '24hr']);
            $add($variations, 'AM PM', ['format' => 'ampm']);
            $add($variations, 'Seconds', ['useSeconds' => true]);
            $add($variations, 'Landscape', ['landscape' => true]);
            $add($variations, 'Read Only', ['readonly' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
        }

        if ($this->belongsTo(['p-timeline', 'p-timeline-item'])) {
            $add($variations, 'Start', ['side' => 'start']);
            $add($variations, 'End', ['side' => 'end']);
            $add($variations, 'Compact', ['density' => 'compact']);
            $add($variations, 'Comfortable', ['density' => 'comfortable']);
            $add($variations, 'Truncate Line', ['truncateLine' => 'both']);
            $add($variations, 'Success', ['color' => 'success']);
        }

        if ($this->belongsTo(['p-treeview', 'p-treeview-item'])) {
            $add($variations, 'Compact', ['density' => 'compact']);
        }

        return $variations;
    }

    /**
     * @param array<string, mixed> $variation
     * @return array<string, mixed>
     */
    private function sampleProps(array $variation): array
    {
        $defaults = [
            'accessibilityLabel' => $this->title.' preview',
            'source' => '',
        ];

        if ($this->belongsTo([
            'p-autocomplete', 'p-checkbox', 'p-color-input',
            'p-combobox', 'p-date-input',
            'p-number-input', 'p-otp-input', 'p-radio', 'p-select',
            'p-slider', 'p-switch', 'p-text-field',
            'p-textarea',
        ])) {
            $defaults['label'] = $this->title;
        }

        if ($this->belongsTo([
            'p-checkbox', 'p-radio',
            'p-switch',
        ])) {
            $defaults['checked'] = true;
        }

        if ($this->belongsTo([
            'p-dialog', 'p-menu', 'p-overlay', 'p-snackbar', 'p-tooltip',
        ])) {
            $defaults['open'] = false;
        }

        if ($this->belongsTo([
            'p-btn-toggle', 'p-carousel',
            'p-expansion-panels', 'p-slide-group', 'p-stepper',
            'p-stepper-vertical', 'p-tab', 'p-tabs',
        ])) {
            $defaults['active'] = true;
        }
        if ($this->tag === 'p-tab') {
            $defaults['selected'] = true;
        }

        return $variation + $this->componentFixture() + $defaults;
    }

    /** @return array<string, mixed> */
    private function componentFixture(): array
    {
        return match ($this->tag) {
            'p-treeview' => [
                'items' => [
                    [
                        'title' => 'Applications',
                        'value' => 'applications',
                        'children' => [
                            [
                                'title' => 'Mobile',
                                'value' => 'applications/mobile',
                                'children' => [
                                    ['title' => 'Android', 'value' => 'applications/mobile/android'],
                                    ['title' => 'iOS', 'value' => 'applications/mobile/ios'],
                                ],
                            ],
                            ['title' => 'Web', 'value' => 'applications/web'],
                        ],
                    ],
                    ['title' => 'Design system', 'value' => 'design-system'],
                ],
                'opened' => ['applications', 'applications/mobile'],
                'modelValue' => 'applications/mobile/android',
            ],
            'p-calendar', 'p-date-picker', 'p-date-input' => [
                'locale' => 'pt-BR',
                'modelValue' => '2026-07-15',
                'visibleDate' => '2026-07-01',
            ],
            'p-data-table', 'p-data-table-virtual' => [
                'headers' => [
                    ['title' => 'Product', 'key' => 'name'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Price', 'key' => 'price'],
                ],
                'items' => $this->tableRows($this->tag === 'p-data-table-virtual' ? 36 : 6),
                'itemHeight' => 52,
            ],
            'p-autocomplete', 'p-combobox', 'p-select' => [
                'items' => ['Design', 'Engineering', 'Product', 'Research'],
                'modelValue' => 'Design',
                'placeholder' => 'Choose a team',
            ],
            'p-number-input' => [
                'min' => 0,
                'max' => 20,
                'step' => 1,
                'modelValue' => 8,
            ],
            'p-otp-input' => [
                'length' => 6,
                'modelValue' => '482915',
            ],
            'p-slider' => [
                'min' => 0,
                'max' => 100,
                'modelValue' => 64,
                'helper' => 'Drag to adjust the value',
            ],
            'p-range-slider' => [
                'min' => 0,
                'max' => 100,
                'modelValue' => [24, 76],
                'helper' => 'Choose the allowed range',
            ],
            'p-rating' => [
                'length' => 5,
                'min' => 0,
                'max' => 5,
                'step' => 1,
                'modelValue' => 4,
            ],
            'p-icon' => ['name' => 'StarIcon', 'color' => 0xFF0B172A],
            'p-icon-btn' => ['icon' => 'SettingsIcon'],
            'p-app-bar-nav-icon' => ['icon' => 'MenuIcon'],
            'p-tabs' => [
                'items' => ['Overview', 'Details', 'Activity'],
                'value' => 'overview',
            ],
            'p-slide-group' => [
                'items' => ['Overview', 'Details', 'Activity'],
                'value' => 'overview',
            ],
            'p-carousel' => [
                'items' => ['Overview', 'Details', 'Activity'],
                'value' => 'overview',
            ],
            'p-stepper', 'p-stepper-vertical' => [
                'items' => ['Account', 'Delivery', 'Confirm'],
                'value' => 'account',
            ],
            'p-expansion-panels' => [
                'items' => ['Product details', 'Delivery', 'Support'],
                'value' => 'details',
            ],
            'p-progress-circular', 'p-progress-linear' => ['modelValue' => 64],
            'p-skeleton-loader' => [
                'type' => 'article',
                'loading' => true,
                'pulseDuration' => 1400,
            ],
            'p-sparkline' => [
                'values' => '12,18,14,26,22,34,31,42,38,52,48,64',
                'type' => 'trend',
                'smooth' => true,
                'fill' => true,
                'lineWidth' => 3,
            ],
            'p-color-input' => ['modelValue' => '#5CBBF6'],
            'p-time-picker' => ['modelValue' => '14:35'],
            'p-text-field' => [
                'modelValue' => 'Native field',
                'placeholder' => 'Type here',
                'helper' => 'Rendered by the platform',
            ],
            'p-textarea' => [
                'modelValue' => 'Built for Android and iOS.',
                'placeholder' => 'Write a message',
                'helper' => 'Up to 280 characters',
                'rows' => 3,
            ],
            'p-switch' => [
                'checked' => true,
                'label' => 'Enabled',
            ],
            'p-alert', 'p-banner', 'p-empty-state', 'p-snackbar' => [
                'text' => 'Everything is ready to continue.',
            ],
            default => [],
        };
    }

    /** @return list<array{name: string, status: string, price: string}> */
    private function tableRows(int $count): array
    {
        $products = ['Aurora', 'Atlas', 'Canvas', 'Pulse', 'Relay', 'Studio'];
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'name' => $products[$index % count($products)].' '.($index + 1),
                'status' => $index % 3 === 0 ? 'Review' : 'Active',
                'price' => 'R$ '.number_format(49.9 + ($index * 7.5), 2, ',', '.'),
            ];
        }

        return $rows;
    }

    private function previewText(): string
    {
        return match ($this->tag) {
            'p-icon' => 'star',
            default => $this->title,
        };
    }

    private function isEnabledValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    private function generatesNativeAnatomy(): bool
    {
        return $this->belongsTo([
            'p-autocomplete',
            'p-calendar',
            'p-color-input',
            'p-combobox',
            'p-carousel',
            'p-data-table',
            'p-data-table-virtual',
            'p-date-input',
            'p-date-picker',
            'p-expansion-panels',
            'p-number-input',
            'p-otp-input',
            'p-progress-circular',
            'p-progress-linear',
            'p-range-slider',
            'p-rating',
            'p-select',
            'p-slider',
            'p-stepper',
            'p-stepper-vertical',
            'p-switch',
            'p-text-field',
            'p-textarea',
            'p-time-picker',
            'p-treeview',
        ]);
    }

    /** @param list<string> $tags */
    private function belongsTo(array $tags): bool
    {
        return in_array($this->tag, $tags, true);
    }

    /** @return array{} */
    public function templateState(): array
    {
        return [];
    }
}
