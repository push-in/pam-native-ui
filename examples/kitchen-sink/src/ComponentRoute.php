<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Component\UiComponent;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Material\PTreeview;
use Pam\MobileUi\Theme\MaterialTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Align;
use Pam\Native\App as NativeApp;
use Pam\Native\Component;
use Pam\Native\Justify;
use Pam\Native\KeyboardAvoidingBehavior;
use Pam\Native\Navigation\DrawerNavigator;
use Pam\Native\Navigation\DrawerType;
use Pam\Native\PositionType;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\ActivityIndicator;
use Pam\Native\UI\Column;
use Pam\Native\UI\KeyboardAvoidingView;
use Pam\Native\UI\Pressable;
use Pam\Native\UI\Row;
use Pam\Native\UI\SafeAreaView;
use Pam\Native\UI\ScrollView;
use Pam\Native\UI\Text;
use Pam\Native\UI\View;

/**
 * @property (\stdClass&object{
 *   auditRevision: int,
 *   auditScenario: string|null,
 *   interactionRevision: int
 * }) $state
 */
final class ComponentRoute extends Component
{
    public DrawerNavigator $drawer;

    /** @var array<int, mixed> */
    private array $sampleValues = [];

    /** @var array<int, string> */
    private array $expandedPanels = [];

    /** @var array<int, bool> */
    private array $sampleFocused = [];

    /** @var array<int, int> */
    private array $sheetSnapIndexes = [];

    /** @var array<int, CardInteractionPreview> */
    private array $cardInteractionPreviews = [];

    /** @var array<int, CardActionsInteractionPreview> */
    private array $cardActionsInteractionPreviews = [];

    /** @var array<int, ChipInteractionPreview> */
    private array $chipInteractionPreviews = [];

    /** @var array<int, ChipGroupInteractionPreview> */
    private array $chipGroupInteractionPreviews = [];

    /** @var array<int, ColorInputInteractionPreview> */
    private array $colorInputInteractionPreviews = [];

    /** @var array<int, DataTableInteractionPreview> */
    private array $dataTableInteractionPreviews = [];

    /** @var array<int, VirtualDataTableInteractionPreview> */
    private array $virtualDataTableInteractionPreviews = [];

    /**
     * @param class-string<UiComponent> $component
     */
    public function __construct(
        private readonly string $tag,
        private readonly string $title,
        private readonly string $component,
    ) {
    }

    /** @return array{auditScenario: null|string, auditRevision: int, interactionRevision: int} */
    protected function initialState(): array
    {
        return [
            'auditScenario' => null,
            'auditRevision' => 0,
            'interactionRevision' => 0,
        ];
    }

    public function render(): Renderable
    {
        // Interactive previews keep their compact sample data in local arrays.
        // Reading this revision subscribes the component to every interaction
        // update so PHP never reuses a stale rendered subtree after a native
        // event changes one of those values.
        $interactionRevision = $this->state->interactionRevision;
        unset($interactionRevision);
        $theme = ThemeManager::current();
        $samples = [];
        foreach ($this->variations() as $index => $variation) {
            $variationName = strtolower($variation['label']);
            $variationProps = $variation['props'];
            $sampleTone = match (true) {
                ($variationProps['error'] ?? false) === true,
                str_contains($variationName, 'error'),
                str_contains($variationName, 'invalid') => ColorToken::Destructive,
                ($variationProps['success'] ?? false) === true,
                str_contains($variationName, 'success') => ColorToken::Success,
                ($variationProps['loading'] ?? false) === true,
                str_contains($variationName, 'loading') => ColorToken::Warning,
                ($variationProps['disabled'] ?? false) === true,
                str_contains($variationName, 'disabled') => ColorToken::Muted,
                ($variationProps['readonly'] ?? false) === true,
                ($variationProps['readOnly'] ?? false) === true,
                str_contains($variationName, 'read only') => ColorToken::Secondary,
                $index % 3 === 1 => ColorToken::Secondary,
                $index % 3 === 2 => ColorToken::Info,
                default => ColorToken::Primary,
            };
            $sampleOnTone = match ($sampleTone) {
                ColorToken::Secondary => ColorToken::SecondaryForeground,
                ColorToken::Info => ColorToken::InfoForeground,
                ColorToken::Warning => ColorToken::WarningForeground,
                ColorToken::Destructive => ColorToken::DestructiveForeground,
                ColorToken::Success => ColorToken::SuccessForeground,
                ColorToken::Muted => ColorToken::MutedForeground,
                default => ColorToken::PrimaryForeground,
            };
            $caption = Text::make($variation['label'])->style(new Style(
                alignSelf: Align::Start,
                height: 30.0,
                minHeight: 30.0,
                paddingHorizontal: 12.0,
                borderRadius: 15.0,
                backgroundColor: $theme->color($sampleTone),
                textColor: $theme->color($sampleOnTone),
                fontSize: 12.0,
                lineHeight: 30.0,
                fontWeight: 700,
            ));
            $component = $this->component;
            $previewProps = $this->sampleProps($variation['props']);
            $previewProps['id'] ??= 'showcase-'.$this->tag.'-'.$index;
            if ($this->tag === 'p-data-table-virtual') {
                // The catalog places bounded virtual tables inside its own
                // documentation scroller. Keep boundary rows mounted so the
                // nested viewport never presents a half-painted sample.
                $previewProps['removeClippedSubviews'] = false;
            }
            if ($this->tag === 'p-chip') {
                $profile = self::stringValue($previewProps['chipProfile'] ?? 'assist');
                $preview = $this->chipInteractionPreviews[$index]
                    ??= new ChipInteractionPreview($profile);
                $samples[] = Column::make($caption, $preview)->style(new Style(
                    widthPercent: 100.0,
                    gap: 8.0,
                ));
                continue;
            }
            if ($this->tag === 'p-chip-group') {
                $profile = self::stringValue($previewProps['chipGroupProfile'] ?? 'single');
                $preview = $this->chipGroupInteractionPreviews[$index]
                    ??= new ChipGroupInteractionPreview($profile);
                $samples[] = Column::make($caption, $preview)->style(new Style(
                    widthPercent: 100.0,
                    gap: 8.0,
                ));
                continue;
            }
            if ($this->tag === 'p-color-input') {
                $profile = self::stringValue($previewProps['colorProfile'] ?? 'hex');
                $preview = $this->colorInputInteractionPreviews[$index]
                    ??= new ColorInputInteractionPreview($profile);
                $samples[] = Column::make($caption, $preview)->style(new Style(
                    widthPercent: 100.0,
                    gap: 8.0,
                ));
                continue;
            }
            if ($this->tag === 'p-data-table') {
                $profile = self::stringValue($previewProps['dataTableProfile'] ?? 'standard');
                $preview = $this->dataTableInteractionPreviews[$index]
                    ??= new DataTableInteractionPreview($profile);
                $samples[] = Column::make($caption, $preview)->style(new Style(
                    widthPercent: 100.0,
                    gap: 8.0,
                ));
                continue;
            }
            if ($this->tag === 'p-data-table-virtual') {
                $profile = self::stringValue($previewProps['virtualTableProfile'] ?? 'standard');
                $preview = $this->virtualDataTableInteractionPreviews[$index]
                    ??= new VirtualDataTableInteractionPreview($profile);
                $samples[] = Column::make($caption, $preview)->style(new Style(
                    widthPercent: 100.0,
                    gap: 8.0,
                ));
                continue;
            }
            if ($this->belongsTo([
                'p-autocomplete', 'p-calendar', 'p-color-input', 'p-combobox', 'p-date-input',
                'p-date-picker',
                'p-number-input',
                'p-otp-input', 'p-range-slider', 'p-rating', 'p-select',
                'p-slider', 'p-text-field', 'p-textarea',
            ])) {
                $previewProps['modelValue'] = $this->sampleValues[$index]
                    ?? $previewProps['modelValue']
                    ?? null;
            }
            if ($this->tag === 'p-otp-input') {
                $previewProps['focused'] = $this->sampleFocused[$index]
                    ?? ($previewProps['focused'] ?? false);
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
                $stepOrder = ['account', 'delivery', 'confirm'];
                $currentStep = self::stringValue($this->sampleValues[$index]
                    ?? $previewProps['modelValue']
                    ?? 'account');
                $currentIndex = array_search($currentStep, $stepOrder, true);
                $currentIndex = is_int($currentIndex) ? $currentIndex : 0;
                $previewProps['modelValue'] = $stepOrder[$currentIndex];
                $previewProps['value'] = $stepOrder[$currentIndex];
                $previewProps['flat'] = true;
                $itemTag = $this->tag === 'p-stepper'
                    ? 'p-stepper-item'
                    : 'p-stepper-vertical-item';
                $item = MaterialComponentMap::TAGS[$itemTag];
                $content = MaterialComponentMap::TAGS['p-stepper-window-item'];
                $horizontalStepStyle = new Style(
                    width: 108.0,
                    minWidth: 108.0,
                    paddingHorizontal: 8.0,
                );
                $activeHorizontalStepStyle = new Style(
                    width: 108.0,
                    minWidth: 108.0,
                    paddingHorizontal: 8.0,
                );
                $verticalStepStyle = new Style(
                    widthPercent: 100.0,
                    paddingHorizontal: 12.0,
                    paddingVertical: 6.0,
                );
                $stepLabel = static function (
                    string $number,
                    string $label,
                    bool $active,
                ) use ($previewProps, $theme): Row {
                    return Row::make(
                        Text::make($number)->style(new Style(
                            width: 28.0,
                            height: 28.0,
                            borderRadius: 14.0,
                            backgroundColor: $active
                                ? $theme->color(ColorToken::Primary)
                                : $theme->color(ColorToken::Muted),
                            textColor: $active
                                ? $theme->color(ColorToken::PrimaryForeground)
                                : $theme->color(ColorToken::MutedForeground),
                            fontSize: 12.0,
                            lineHeight: 28.0,
                            fontWeight: 700,
                            textAlign: \Pam\Native\TextAlignment::Center,
                        )),
                        Text::make($label)->style(new Style(
                            textColor: $active
                                ? $theme->color(ColorToken::Primary)
                                : $theme->color(ColorToken::MutedForeground),
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
                            'active' => $currentIndex === 0,
                            'selected' => $currentIndex === 0,
                            'complete' => $currentIndex > 0,
                            'accessibilityLabel' => 'Step 1, Account',
                        ],
                        $stepLabel($currentIndex > 0 ? '✓' : '1', 'Account', $currentIndex === 0),
                    )->style(
                        $this->tag === 'p-stepper'
                            ? $activeHorizontalStepStyle
                            : $verticalStepStyle,
                    ),
                    $item::make(
                        [
                            'value' => 'delivery',
                            'active' => $currentIndex === 1,
                            'selected' => $currentIndex === 1,
                            'complete' => $currentIndex > 1,
                            'accessibilityLabel' => 'Step 2, Delivery',
                        ],
                        $stepLabel($currentIndex > 1 ? '✓' : '2', 'Delivery', $currentIndex === 1),
                    )
                        ->style(
                            $this->tag === 'p-stepper'
                                ? $horizontalStepStyle
                                : $verticalStepStyle,
                        ),
                    $item::make(
                        [
                            'value' => 'confirm',
                            'active' => $currentIndex === 2,
                            'selected' => $currentIndex === 2,
                            'accessibilityLabel' => 'Step 3, Confirm',
                        ],
                        $stepLabel('3', 'Confirm', $currentIndex === 2),
                    )
                        ->style(
                            $this->tag === 'p-stepper'
                                ? $horizontalStepStyle
                                : $verticalStepStyle,
                        ),
                ];
                if ($this->tag === 'p-stepper') {
                    $header = MaterialComponentMap::TAGS['p-stepper-header'];
                    $steps = [$header::make([], ...$steps)];
                }
                $stepTitles = ['Account details', 'Delivery address', 'Review and confirm'];
                $stepCopies = [
                    'Your profile is complete and ready for the next step.',
                    'Choose where your native package should be delivered.',
                    'Review the information before finishing the setup.',
                ];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $back = $button::make(
                    ['variant' => 'text', 'disabled' => $currentIndex === 0, 'accessibilityLabel' => 'Previous step'],
                    Text::make('Back'),
                );
                if ($currentIndex > 0) {
                    $back = $back->onPress(function () use ($index, $stepOrder, $currentIndex): bool {
                        $this->setSampleValue($index, $stepOrder[$currentIndex - 1]);

                        return true;
                    });
                }
                $next = $button::make(
                    ['variant' => 'tonal', 'accessibilityLabel' => $currentIndex === 2 ? 'Finish setup' : 'Next step'],
                    Text::make($currentIndex === 2 ? 'Finish' : 'Continue'),
                )->onPress(function () use ($index, $stepOrder, $currentIndex): bool {
                    $this->setSampleValue($index, $stepOrder[min(2, $currentIndex + 1)]);

                    return true;
                });
                $steps[] = $content::make(
                        ['value' => $stepOrder[$currentIndex], 'selected' => true],
                        Column::make(
                            Text::make($stepTitles[$currentIndex])->style(new Style(
                                fontSize: 18.0,
                                lineHeight: 24.0,
                                fontWeight: 600,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            Text::make($stepCopies[$currentIndex])->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                            )),
                            Row::make($back, $next)->style(new Style(
                                widthPercent: 100.0,
                                paddingTop: 12.0,
                                gap: 8.0,
                                justifyContent: Justify::End,
                            )),
                        )->style(new Style(
                            paddingHorizontal: 20.0,
                            paddingTop: 16.0,
                            paddingBottom: 8.0,
                            gap: 8.0,
                        )),
                );
                $preview = $component::make($previewProps, ...$steps)
                    ->onChange(function (mixed $value) use ($index): bool {
                        $this->setSampleValue($index, self::stringValue($value));

                        return true;
                    })->style(new Style(
                        widthPercent: 100.0,
                        backgroundColor: 0x00000000,
                        borderWidth: 0.0,
                        elevation: 0.0,
                    ));
            } elseif ($this->tag === 'p-expansion-panels') {
                $panel = MaterialComponentMap::TAGS['p-expansion-panel'];
                $title = MaterialComponentMap::TAGS['p-expansion-panel-title'];
                $text = MaterialComponentMap::TAGS['p-expansion-panel-text'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $expandedPanel = $this->expandedPanels[$index] ?? 'details';
                $detailsExpanded = $expandedPanel === 'details';
                $deliveryExpanded = $expandedPanel === 'delivery';
                $supportExpanded = $expandedPanel === 'support';
                $profile = is_string($previewProps['expansionProfile'] ?? null)
                    ? $previewProps['expansionProfile']
                    : 'default';
                $compact = $profile === 'compact';
                $disabledKey = $profile === 'disabled' ? 'support' : null;
                $previewProps['value'] = $expandedPanel;
                $previewProps['modelValue'] = $expandedPanel;
                $previewProps['accessibilityLabel'] = 'Product information sections';
                $makePanel = function (
                    string $key,
                    string $label,
                    string $description,
                    bool $expanded,
                ) use (
                    $panel,
                    $title,
                    $text,
                    $icon,
                    $theme,
                    $index,
                    $compact,
                    $disabledKey,
                ) {
                    $disabled = $disabledKey === $key;
                    $headerHeight = $compact ? 48.0 : ($expanded ? 64.0 : 56.0);
                    $result = $panel::make(
                        [
                            'value' => $key,
                            'open' => $expanded,
                            'expanded' => $expanded,
                            'disabled' => $disabled,
                            'accessibilityLabel' => $label,
                            'accessibilityHint' => $expanded
                                ? 'Double tap to collapse this section'
                                : 'Double tap to expand this section',
                        ],
                        $title::make([
                            'active' => $expanded,
                            'expanded' => $expanded,
                            'disabled' => $disabled,
                            'accessibilityElementsHidden' => true,
                        ], Row::make(
                            Text::make($label)->style(new Style(
                                fontSize: 16.0,
                                lineHeight: 24.0,
                                fontWeight: $expanded ? 600 : 500,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            $icon::make([
                                'icon' => 'ChevronDownIcon',
                                'size' => 'small',
                                'accessibilityElementsHidden' => true,
                            ])->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                rotation: $expanded ? 180.0 : 0.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            minHeight: $headerHeight,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make([
                            'active' => $expanded,
                            'expanded' => $expanded,
                        ], Text::make($description)->style(new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                        ))),
                    );
                    if (!$disabled) {
                        $result = $result->onToggle(function (mixed $value) use (
                            $index,
                            $key,
                        ): bool {
                            $this->setExpandedPanel(
                                $index,
                                $this->isEnabledValue($value) ? $key : '',
                            );

                            return true;
                        });
                    }

                    return $result;
                };
                $preview = $component::make(
                    $previewProps,
                    $makePanel('details', 'Product details', 'Native components share the same tokens on Android and iOS.', $detailsExpanded),
                    $makePanel('delivery', 'Delivery', 'Fast, predictable native rendering with stable layout.', $deliveryExpanded),
                    $makePanel('support', 'Support', 'Accessible states, clear labels and keyboard navigation.', $supportExpanded),
                );
            } elseif ($this->belongsTo(['p-carousel', 'p-carousel-item'])) {
                $carousel = MaterialComponentMap::TAGS['p-carousel'];
                $item = MaterialComponentMap::TAGS['p-carousel-item'];
                $carouselItemProps = [];
                if ($this->tag === 'p-carousel-item') {
                    $carouselItemProps = $previewProps;
                    $previewProps = [
                        'height' => $carouselItemProps['height']
                            ?? MaterialTokens::CAROUSEL_HEIGHT,
                        'hideDelimiters' => $carouselItemProps['hideDelimiters']
                            ?? false,
                    ];
                }
                $previewProps['accessibilityLabel'] = $this->tag === 'p-carousel-item'
                    ? 'Carousel item preview'
                    : 'Carousel preview';
                $carouselHeight = is_numeric($previewProps['height'] ?? null)
                    ? (float) $previewProps['height']
                    : MaterialTokens::CAROUSEL_HEIGHT;
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
                    $this->setSampleValue($index, $value);

                    return true;
                };
                $previewProps['value'] = $currentSlide;
                $previewProps['modelValue'] = $currentSlide;
                $previewProps['cornerRadius'] = MaterialTokens::CAROUSEL_RADIUS;
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
                            Text::make('‹')->style(new Style(
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
                            top: ($carouselHeight - 48.0) / 2.0,
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
                                width: $active ? 24.0 : 8.0,
                                height: 8.0,
                                minWidth: $active ? 24.0 : 8.0,
                                minHeight: 8.0,
                                padding: 0.0,
                                borderRadius: 4.0,
                                backgroundColor: $active ? 0xFFFFFFFF : 0x99FFFFFF,
                            ));
                        }
                        $controls[] = Row::make(...$delimiters)->style(new Style(
                            positionType: PositionType::Absolute,
                            left: 0.0,
                            right: 0.0,
                            bottom: 20.0,
                            minHeight: 24.0,
                            gap: 8.0,
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
                            Text::make('›')->style(new Style(
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
                            top: ($carouselHeight - 48.0) / 2.0,
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
                            ...$carouselItemProps,
                            'value' => 'overview',
                        ],
                    Column::make(
                            Text::make('PAM NATIVE · 01')->style(new Style(
                                fontSize: 11.0,
                                lineHeight: 16.0,
                                fontWeight: 700,
                                textColor: 0xFFCDEBFA,
                            )),
                            Text::make('Native by design')->style(new Style(
                                fontSize: 28.0,
                                lineHeight: 34.0,
                                fontWeight: 700,
                                textColor: 0xFFFFFFFF,
                            )),
                            Text::make('Fast transitions with no web view.')
                                ->style(new Style(
                                    fontSize: 16.0,
                                    lineHeight: 24.0,
                                    textColor: 0xFFD9EEFA,
                                )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            heightPercent: 100.0,
                            paddingHorizontal: 32.0,
                            paddingVertical: 28.0,
                            gap: 10.0,
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
                        ...$carouselItemProps,
                        'value' => 'details',
                    ],
                    Column::make(
                        Text::make('PAM NATIVE · 02')->style(new Style(
                            fontSize: 11.0,
                            lineHeight: 16.0,
                            fontWeight: 700,
                            textColor: 0xFFC9F3EA,
                        )),
                        Text::make('Composable')->style(new Style(
                            fontSize: 28.0,
                            lineHeight: 34.0,
                            fontWeight: 700,
                            textColor: 0xFFFFFFFF,
                        )),
                        Text::make('Build complex flows from small native parts.')
                            ->style(new Style(
                                fontSize: 16.0,
                                lineHeight: 24.0,
                                textColor: 0xFFE4F6F2,
                            )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        heightPercent: 100.0,
                        paddingHorizontal: 32.0,
                        paddingVertical: 28.0,
                        gap: 10.0,
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
                        ...$carouselItemProps,
                        'value' => 'activity',
                    ],
                    Column::make(
                        Text::make('PAM NATIVE · 03')->style(new Style(
                            fontSize: 11.0,
                            lineHeight: 16.0,
                            fontWeight: 700,
                            textColor: 0xFFFFE0B5,
                        )),
                        Text::make('Accessible')->style(new Style(
                            fontSize: 28.0,
                            lineHeight: 34.0,
                            fontWeight: 700,
                            textColor: 0xFFFFFFFF,
                        )),
                        Text::make('Keyboard, screen reader and reduced motion ready.')
                            ->style(new Style(
                                fontSize: 16.0,
                                lineHeight: 24.0,
                                textColor: 0xFFFFF0DC,
                            )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        heightPercent: 100.0,
                        paddingHorizontal: 32.0,
                        paddingVertical: 28.0,
                        gap: 10.0,
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
                $preview = $carousel::make(
                    $previewProps,
                    ...$slides,
                    ...$controls,
                )->onChange(
                    static fn (mixed $value): bool => is_string($value)
                        && $setSlide($value),
                );
            } elseif ($this->belongsTo(['p-menu', 'p-tooltip'])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $menuSelection = is_string($this->sampleValues[$index] ?? null)
                    ? (string) $this->sampleValues[$index]
                    : null;
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
                        Text::make($this->tag === 'p-menu'
                            ? match ($menuSelection) {
                                'edit-profile' => 'Profile selected',
                                'manage-notifications' => 'Notifications selected',
                                'sign-out' => 'Sign out selected',
                                default => 'Open menu',
                            }
                            : 'More information'),
                    )->style(new Style(width: $this->tag === 'p-menu' ? 144.0 : 184.0)),
                    $this->tag === 'p-menu'
                        ? Column::make(
                            $listItem::make(
                                ['value' => 'edit-profile'],
                                Text::make($menuSelection === 'edit-profile' ? 'Profile selected' : 'Edit profile'),
                            )->onPress(function () use ($index): bool {
                                $this->setSampleValue($index, 'edit-profile');

                                return true;
                            }),
                            $listItem::make(
                                ['value' => 'manage-notifications'],
                                Text::make($menuSelection === 'manage-notifications' ? 'Notifications selected' : 'Manage notifications'),
                            )->onPress(function () use ($index): bool {
                                $this->setSampleValue($index, 'manage-notifications');

                                return true;
                            }),
                            $listItem::make(
                                ['value' => 'sign-out'],
                                Text::make($menuSelection === 'sign-out' ? 'Sign out selected' : 'Sign out'),
                            )->onPress(function () use ($index): bool {
                                $this->setSampleValue($index, 'sign-out');

                                return true;
                            }),
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
            } elseif ($this->tag === 'p-bottom-sheet') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $textField = MaterialComponentMap::TAGS['p-text-field'];
                $profile = self::stringValue($previewProps['sheetProfile'] ?? 'default');
                unset($previewProps['sheetProfile']);
                $open = (bool) ($this->sampleValues[$index] ?? false);
                $setOpen = function (bool $value) use ($index): bool {
                    $this->setSampleValue($index, $value);

                    return true;
                };
                $profileCopy = match ($profile) {
                    'detents' => [
                        'Open two-detent sheet',
                        'Flexible workspace',
                        'Drag the indicator to move between compact and expanded views.',
                    ],
                    'persistent' => [
                        'Open persistent sheet',
                        'Review required',
                        'This sheet stays open until you choose one of its visible actions.',
                    ],
                    'no-scrim' => [
                        'Open sheet without scrim',
                        'Non-blocking details',
                        'Keep surrounding context visible while presenting a short action.',
                    ],
                    'no-indicator' => [
                        'Open sheet without indicator',
                        'Fixed presentation',
                        'A stable surface for content that does not advertise drag handling.',
                    ],
                    'dynamic' => [
                        'Open dynamic sheet',
                        'Sized to its content',
                        'The surface uses its intrinsic content height instead of a fixed detent.',
                    ],
                    'keyboard' => [
                        'Open keyboard form sheet',
                        'Rename workspace',
                        'Update the name without losing context behind this sheet.',
                    ],
                    default => [
                        'Open default sheet',
                        'Share this workspace',
                        'Choose how collaborators can access your latest mobile prototype.',
                    ],
                };
                [$triggerLabel, $sheetTitle, $sheetDescription] = $profileCopy;
                $persistent = $profile === 'persistent';
                $keyboard = $profile === 'keyboard';
                $detents = $profile === 'detents';
                $sheetSnapIndex = $this->sheetSnapIndexes[$index]
                    ?? self::intValue($previewProps['defaultSnapIndex'] ?? 0);
                if ($detents) {
                    $previewProps['snapToIndex'] = $sheetSnapIndex;
                }

                $show = $button::make(
                    [
                        'variant' => 'tonal',
                        'accessibilityLabel' => $triggerLabel,
                    ],
                    Text::make($triggerLabel),
                )->style(new Style(
                    width: 248.0,
                    height: 48.0,
                    minHeight: 48.0,
                ))
                    ->onPress(fn (): bool => $setOpen(true));

                $cancel = $button::make(
                    [
                        'variant' => 'text',
                        'value' => 'pam:modal-close',
                        'accessibilityLabel' => 'Close '.$sheetTitle,
                    ],
                    Text::make($persistent ? 'Not now' : 'Cancel'),
                )->style(new Style(
                    width: 104.0,
                    height: 48.0,
                    minHeight: 48.0,
                ))
                    ->onPress(fn (): bool => $setOpen(false));
                $confirm = $button::make(
                    [
                        'variant' => 'flat',
                        'color' => 'primary',
                        'value' => 'pam:modal-close',
                        'accessibilityLabel' => $keyboard
                            ? 'Save workspace name'
                            : 'Confirm '.$sheetTitle,
                    ],
                    Text::make($keyboard ? 'Save' : ($persistent ? 'Review' : 'Continue')),
                )->style(new Style(
                    width: 120.0,
                    height: 48.0,
                    minHeight: 48.0,
                ))
                    ->onPress(fn (): bool => $setOpen(false));

                $body = [
                    Column::make(
                        Text::make('PAM NATIVE')->style(new Style(
                            textColor: $theme->color(ColorToken::Primary),
                            fontSize: 12.0,
                            lineHeight: 16.0,
                            fontWeight: 700,
                        )),
                        Text::make($sheetTitle)->style(new Style(
                            textColor: $theme->color(ColorToken::OnSurface),
                            fontSize: 22.0,
                            lineHeight: 28.0,
                            fontWeight: 700,
                        )),
                        Text::make($sheetDescription)->style(new Style(
                            textColor: $theme->color(ColorToken::MutedForeground),
                            fontSize: 14.0,
                            lineHeight: 20.0,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        gap: 4.0,
                    )),
                ];
                if ($keyboard) {
                    $body[] = $textField::make([
                        'label' => 'Workspace name',
                        'modelValue' => 'Atlas mobile',
                        'helper' => 'Visible to everyone in this workspace',
                        'clearable' => true,
                        'accessibilityLabel' => 'Workspace name',
                    ]);
                } else {
                    $body[] = Row::make(
                        View::make(
                            $icon::make([
                                'icon' => 'check',
                                'color' => $theme->color(ColorToken::Primary),
                                'size' => 18,
                            ]),
                        )->style(new Style(
                            width: 32.0,
                            height: 32.0,
                            minWidth: 32.0,
                            minHeight: 32.0,
                            borderRadius: 16.0,
                            backgroundColor: $theme->color(ColorToken::Accent),
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                        )),
                        Text::make('Native gesture, focus and Back handling included.')
                            ->style(new Style(
                                textColor: $theme->color(ColorToken::OnSurface),
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                flexGrow: 1.0,
                                flexShrink: 1.0,
                            )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        minHeight: 40.0,
                        gap: 12.0,
                        alignItems: Align::Center,
                    ));
                }
                if ($detents && $sheetSnapIndex === 1) {
                    $detailRows = [];
                    foreach ([
                        ['globe', 'Native layout', 'Measured and composed by the Android view system.'],
                        ['chevrons-up-down', 'Responsive gestures', 'The surface follows your drag and settles at a valid detent.'],
                        ['lock', 'Safe dismissal', 'Back, backdrop and explicit actions follow the selected policy.'],
                    ] as [$detailIcon, $detailTitle, $detailDescription]) {
                        $detailRows[] = Row::make(
                            View::make(
                                $icon::make([
                                    'icon' => $detailIcon,
                                    'color' => $theme->color(ColorToken::Primary),
                                    'size' => 20,
                                ])->style(new Style(
                                    width: 24.0,
                                    height: 24.0,
                                    minWidth: 24.0,
                                    minHeight: 24.0,
                                )),
                            )->style(new Style(
                                width: 48.0,
                                height: 48.0,
                                minWidth: 48.0,
                                minHeight: 48.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                            Column::make(
                                Text::make($detailTitle)->style(new Style(
                                    textColor: $theme->color(ColorToken::OnSurface),
                                    fontSize: 14.0,
                                    lineHeight: 20.0,
                                    fontWeight: 600,
                                )),
                                Text::make($detailDescription)->style(new Style(
                                    textColor: $theme->color(ColorToken::MutedForeground),
                                    fontSize: 12.0,
                                    lineHeight: 16.0,
                                )),
                            )->style(new Style(
                                flexGrow: 1.0,
                                flexShrink: 1.0,
                                gap: 2.0,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            minHeight: 56.0,
                            gap: 8.0,
                            alignItems: Align::Center,
                        ));
                    }
                    $body[] = Column::make(
                        Text::make('Expanded details')->style(new Style(
                            textColor: $theme->color(ColorToken::OnSurface),
                            fontSize: 16.0,
                            lineHeight: 24.0,
                            fontWeight: 700,
                        )),
                        ...$detailRows,
                    )->style(new Style(
                        widthPercent: 100.0,
                        gap: 4.0,
                    ));
                    $body[] = View::make()->style(new Style(
                        minHeight: 8.0,
                        flexGrow: 1.0,
                    ));
                }
                $body[] = Row::make($cancel, $confirm)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 48.0,
                    gap: 8.0,
                    alignItems: Align::Center,
                    justifyContent: Justify::End,
                ));

                $surface = $component::make(
                    [
                        ...$previewProps,
                        'open' => $open,
                        'dismissible' => !$persistent,
                        'persistent' => $persistent,
                        'enablePanDownToClose' => !$persistent,
                        'pressBehavior' => $persistent ? 2 : 0,
                        'accessibilityLabel' => $sheetTitle.' bottom sheet',
                    ],
                    Column::make(...$body)->style(new Style(
                        widthPercent: 100.0,
                        gap: 16.0,
                        flexGrow: $detents && $sheetSnapIndex === 1
                            ? 1.0
                            : null,
                    )),
                )
                    ->onChange(function (mixed $value) use ($index): bool {
                        if (is_numeric($value)) {
                            $this->setSheetSnapIndex($index, (int) $value);
                        }

                        return true;
                    })
                    ->onDismiss(fn (): bool => $setOpen(false));
                $preview = Column::make($show, $surface)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 64.0,
                    gap: 12.0,
                ));
            } elseif ($this->belongsTo(['p-dialog', 'p-overlay'])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $open = (bool) ($this->sampleValues[$index] ?? false);
                $dialogProfile = self::stringValue($previewProps['dialogProfile'] ?? 'default');
                unset($previewProps['dialogProfile']);
                $persistent = (bool) ($previewProps['persistent'] ?? false);
                $fullscreen = (bool) ($previewProps['fullscreen'] ?? false);
                $dialogWidth = is_numeric($previewProps['width'] ?? null)
                    ? (float) $previewProps['width']
                    : 480.0;
                $setOpen = function (bool $value) use ($index): bool {
                    $this->setSampleValue($index, $value);

                    return true;
                };
                $show = $button::make(
                    ['variant' => 'tonal', 'size' => 'small'],
                    Text::make(match ($this->tag) {
                        'p-dialog' => match ($dialogProfile) {
                            'persistent' => 'Open persistent dialog',
                            'fullscreen' => 'Open fullscreen dialog',
                            'compact' => 'Open compact dialog',
                            'large' => 'Open large dialog',
                            'no-scrim' => 'Open dialog without scrim',
                            default => 'Open dialog',
                        },
                            default => 'Show overlay',
                    }),
                )->style(new Style(width: $dialogProfile === 'persistent' ? 224.0 : 208.0))
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
                $cancel = $button::make(
                    [
                        'variant' => 'text',
                        'size' => 'small',
                        'accessibilityLabel' => 'Keep draft',
                    ],
                    Text::make('Keep draft'),
                )->onPress(fn (): bool => $setOpen(false));
                $confirm = $button::make(
                    [
                        'variant' => 'tonal',
                        'size' => 'small',
                        'value' => 'pam:modal-close',
                        'accessibilityLabel' => 'Discard draft',
                    ],
                    Text::make('Discard'),
                )->onPress(fn (): bool => $setOpen(false));
                $surface = $component::make(
                    [
                        ...$previewProps,
                        'open' => $open,
                        'dismissible' => !$persistent,
                        'persistent' => $persistent,
                        'accessibilityLabel' => $this->title.' example',
                    ],
                    Column::make(
                        Text::make(match ($this->tag) {
                            'p-dialog' => 'Discard draft?',
                            default => 'Focused native content',
                        })->style(new Style(
                            textColor: 0xFF0B172A,
                            fontSize: 20.0,
                            lineHeight: 28.0,
                            fontWeight: 700,
                        )),
                        Text::make(match ($this->tag) {
                            'p-dialog' => 'Unsaved changes will be removed from this device.',
                            default => 'Tap outside the surface to dismiss.',
                        })->style(new Style(
                            textColor: 0xFF5B6E87,
                            fontSize: 14.0,
                            lineHeight: 20.0,
                        )),
                        Row::make(
                            ...($this->tag === 'p-dialog'
                                ? [
                                    $cancel->style(new Style(minWidth: 112.0)),
                                    $confirm->style(new Style(minWidth: 112.0)),
                                ]
                                : [$close->style(new Style(width: 112.0))]),
                        )->style(new Style(
                            widthPercent: 100.0,
                            gap: 8.0,
                            justifyContent: Justify::End,
                        )),
                    )->style(new Style(
                        // Material dialogs keep roughly 24dp of breathing room
                        // on compact phone viewports. An authored 100% width
                        // could touch both edges when the 480dp max-width was
                        // wider than the logical viewport at higher density.
                        widthPercent: $fullscreen || $dialogProfile === 'compact'
                            ? 100.0
                            : 88.0,
                        heightPercent: $fullscreen ? 100.0 : null,
                        maxWidth: $fullscreen ? null : $dialogWidth,
                        minHeight: $fullscreen ? null : 180.0,
                        paddingHorizontal: 24.0,
                        // Full-screen Material dialogs draw edge-to-edge, but
                        // their authored content must remain outside the
                        // status/navigation-bar touch regions. The native
                        // modal surface owns the full viewport, so reserve the
                        // 24dp system inset plus the normal 24dp content inset
                        // in this showcase rather than placing the heading
                        // underneath Android's status icons.
                        paddingTop: $fullscreen ? 48.0 : 24.0,
                        paddingBottom: $fullscreen ? 48.0 : 24.0,
                        gap: 16.0,
                        backgroundColor: 0xFFFFFFFF,
                        borderRadius: $fullscreen ? 0.0 : 28.0,
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
                $undone = (bool) ($this->sampleValues[$index] ?? false);
                unset($previewProps['open']);
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'persistent' => true,
                        'accessibilityLabel' => $this->title.' notification',
                    ],
                    Text::make(
                        $undone ? 'Changes were restored.' : 'Your changes were saved.',
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
                            'disabled' => $undone,
                        ],
                        Text::make($undone ? 'Restored' : 'Undo')
                            ->style(new Style(
                                textColor: 0xFF7DD3FC,
                                fontSize: 14.0,
                                fontWeight: 700,
                            )),
                    )->onPress(function () use ($index): bool {
                        $this->setSampleValue($index, true);

                        return true;
                    }),
                );
            } elseif ($this->tag === 'p-speed-dial') {
                $fab = MaterialComponentMap::TAGS['p-fab'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $speedDialState = $this->sampleValues[$index] ?? false;
                $speedDialOpen = $speedDialState === true;
                $toggleSpeedDial = function () use ($index): bool {
                    $this->setSampleValue(
                        $index,
                        ($this->sampleValues[$index] ?? false) !== true,
                    );

                    return true;
                };
                $chooseSpeedDialAction = function (string $action) use ($index): bool {
                    $this->setSampleValue($index, $action);

                    return true;
                };
                $selectedSpeedDialAction = is_string($speedDialState)
                    ? $speedDialState
                    : null;
                $speedDial = $component::make(
                    [
                        ...$previewProps,
                        'open' => $speedDialOpen === true,
                        'modelValue' => $speedDialOpen === true,
                        'direction' => $previewProps['direction'] ?? 'bottom',
                        'placement' => \Pam\MobileUi\Enum\Placement::BottomStart->value,
                        'accessibilityLabel' => 'Create actions',
                    ],
                    $fab::make(
                        ['color' => 'primary', 'size' => 'small'],
                        Text::make($selectedSpeedDialAction !== null ? '✓' : '+')->style(new Style(
                            textColor: 0xFFFFFFFF,
                            fontSize: 24.0,
                            fontWeight: 500,
                        )),
                    )->onPress($toggleSpeedDial),
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make('New message'),
                    )->onPress(fn (): bool => $chooseSpeedDialAction('message')),
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make('Upload file'),
                    )->onPress(fn (): bool => $chooseSpeedDialAction('upload')),
                    $button::make(
                        ['variant' => 'tonal', 'size' => 'small'],
                        Text::make('Create folder'),
                    )->onPress(fn (): bool => $chooseSpeedDialAction('folder')),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: $speedDialOpen ? 240.0 : 64.0,
                ));
                $speedDialChildren = [$speedDial];
                if ($selectedSpeedDialAction !== null) {
                    $speedDialChildren[] = Text::make(match ($selectedSpeedDialAction) {
                        'message' => 'Message created',
                        'upload' => 'Upload started',
                        'folder' => 'Folder created',
                        default => 'Action completed',
                    })->style(new Style(
                        textColor: $theme->color(ColorToken::Primary),
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        fontWeight: 600,
                    ));
                }
                $preview = Column::make(...$speedDialChildren)->style(new Style(
                    widthPercent: 100.0,
                    gap: 12.0,
                ));
            } elseif ($this->tag === 'p-badge') {
                $avatar = MaterialComponentMap::TAGS['p-avatar'];
                $profile = self::stringValue($previewProps['badgeProfile'] ?? 'count');
                unset($previewProps['badgeProfile']);
                $isDot = $profile === 'dot';
                $badgeText = match ($profile) {
                    'overflow' => '99+',
                    'single' => '1',
                    'new' => 'NEW',
                    default => '8',
                };
                $previewProps['dot'] = $isDot;
                $previewProps['accessibilityLabel'] = match ($profile) {
                    'dot' => 'Online',
                    'overflow' => '99 or more unread notifications',
                    'single' => '1 unread notification',
                    'new' => 'New activity',
                    default => '8 unread notifications',
                };
                $badge = $component::make(
                    $previewProps,
                    ...($isDot ? [] : [Text::make($badgeText)]),
                )->style(new Style(
                    positionType: PositionType::Absolute,
                    top: 0.0,
                    right: 0.0,
                ));
                $preview = View::make(
                    $avatar::make(
                        [
                            'size' => 'x-large',
                            'variant' => 'tonal',
                            'accessibilityLabel' => 'PAM account',
                        ],
                        Text::make('PA')->style(new Style(
                            fontSize: 18.0,
                            lineHeight: 24.0,
                            fontWeight: 600,
                        )),
                    ),
                    $badge,
                )->style(new Style(
                    width: 64.0,
                    height: 64.0,
                    positionType: PositionType::Relative,
                ));
            } elseif ($this->belongsTo(['p-tabs', 'p-tab', 'p-slide-group'])) {
                $triggerTag = $this->tag === 'p-slide-group'
                    ? 'p-slide-group-item'
                    : 'p-tab';
                $trigger = MaterialComponentMap::TAGS[$triggerTag];
                $labels = match ($this->tag) {
                    'p-slide-group' => [
                        'Overview', 'Design', 'Android', 'iOS', 'Motion',
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
                $selectedValue = self::stringValue($this->sampleValues[$index]
                    ?? $previewProps['modelValue']
                    ?? 'overview');
                $triggers = [];
                foreach ($labels as $label) {
                    $value = strtolower($label);
                    $active = $value === $selectedValue;
                    $triggerComponent = $trigger::make(
                        [
                            'value' => $value,
                            'active' => $active,
                            'selected' => $active,
                            'disabled' => $this->tag === 'p-tab' && $value === 'activity',
                            'accessibilityLabel' => $label,
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
                            textColor: $active
                                ? $theme->color(
                                    $this->tag === 'p-slide-group'
                                        ? ColorToken::SecondaryForeground
                                        : ColorToken::Primary,
                                )
                                : $theme->color(ColorToken::MutedForeground),
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
                            backgroundColor: $active
                                ? $theme->color(ColorToken::Secondary)
                                : $theme->color(ColorToken::Muted),
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
                        'value' => $selectedValue,
                        'modelValue' => $selectedValue,
                    ],
                    ...$triggers,
                )->onChange(function (mixed $value) use ($index): bool {
                    $this->setSampleValue($index, self::stringValue($value));

                    return true;
                });
                if ($this->tag === 'p-slide-group') {
                    $vertical = ($previewProps['direction'] ?? null) === 'vertical'
                        || ($previewProps['vertical'] ?? false) === true;
                    $group = $group->style(new Style(
                        width: $vertical
                            ? 160.0
                            : (float) count($triggers) * 116.0,
                        height: $vertical
                            ? (float) count($triggers) * 52.0
                            : 48.0,
                        minHeight: $vertical
                            ? (float) count($triggers) * 52.0
                            : 48.0,
                        gap: 8.0,
                    ));
                    $preview = $group;
                } else {
                    $panelTitle = match ($selectedValue) {
                        'details' => 'Component details',
                        'activity' => 'Recent activity',
                        default => 'Product overview',
                    };
                    $panelCopy = match ($selectedValue) {
                        'details' => 'Tokens, behaviors and accessibility contracts.',
                        'activity' => 'Native interactions update without a JS bridge.',
                        default => 'A concise summary connected to the selected tab.',
                    };
                    $preview = Column::make(
                        $group,
                        Column::make(
                            Text::make($panelTitle)->style(new Style(
                                fontSize: 18.0,
                                lineHeight: 24.0,
                                fontWeight: 600,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            Text::make($panelCopy)->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            paddingHorizontal: 16.0,
                            paddingVertical: 20.0,
                            gap: 6.0,
                        )),
                    )->style(new Style(widthPercent: 100.0));
                }
            } elseif ($this->tag === 'p-btn-toggle') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $toggleProfile = self::stringValue(
                    $previewProps['buttonToggleProfile'] ?? 'default'
                );
                unset($previewProps['buttonToggleProfile']);
                $toggleOptions = match ($toggleProfile) {
                    'multiple' => [
                        ['walk', 'Walk', null, false],
                        ['ride', 'Ride', null, false],
                        ['drive', 'Drive', null, false],
                    ],
                    'full-width' => [
                        ['list', 'List', null, false],
                        ['board', 'Board', null, false],
                        ['calendar', 'Calendar', null, false],
                    ],
                    'optional' => [
                        ['grid', 'Grid', null, false],
                        ['list', 'List', null, false],
                        ['compact', 'Compact', null, false],
                    ],
                    'icons' => [
                        ['new', 'New', 'AddIcon', false],
                        ['recent', 'Recent', 'ClockIcon', false],
                        ['saved', 'Saved', 'FavouriteIcon', false],
                    ],
                    'compact' => [
                        ['left', 'Left', null, false],
                        ['center', 'Center', null, false],
                        ['right', 'Right', null, false],
                    ],
                    'two' => [
                        ['monthly', 'Monthly', null, false],
                        ['yearly', 'Yearly', null, false],
                    ],
                    'five' => [
                        ['mon', 'Mon', null, false],
                        ['tue', 'Tue', null, false],
                        ['wed', 'Wed', null, false],
                        ['thu', 'Thu', null, false],
                        ['fri', 'Fri', null, false],
                    ],
                    'disabled-item' => [
                        ['view', 'View', null, false],
                        ['edit', 'Edit', null, true],
                        ['share', 'Share', null, false],
                    ],
                    'disabled-group' => [
                        ['read', 'Read', null, false],
                        ['write', 'Write', null, false],
                        ['admin', 'Admin', null, false],
                    ],
                    'long' => [
                        ['recent', 'Recent activity', null, false],
                        ['assigned', 'Assigned to me', null, false],
                    ],
                    'tile' => [
                        ['one', 'One', null, false],
                        ['two', 'Two', null, false],
                        ['three', 'Three', null, false],
                    ],
                    'rtl' => [
                        ['day', 'Day', null, false],
                        ['week', 'Week', null, false],
                        ['month', 'Month', null, false],
                    ],
                    'success' => [
                        ['draft', 'Draft', null, false],
                        ['review', 'Review', null, false],
                        ['published', 'Published', null, false],
                    ],
                    default => [
                        ['day', 'Day', null, false],
                        ['week', 'Week', null, false],
                        ['month', 'Month', null, false],
                    ],
                };
                $selectedValue = array_key_exists($index, $this->sampleValues)
                    ? $this->sampleValues[$index]
                    : ($previewProps['modelValue'] ?? null);
                $previewProps['modelValue'] = $selectedValue;
                $toggleButtons = [];
                foreach ($toggleOptions as [$value, $label, $iconName, $disabled]) {
                    $selected = is_array($selectedValue)
                        ? in_array($value, $selectedValue, true)
                        : $selectedValue === $value;
                    $content = [Text::make($label)];
                    $leadingIcon = $selected
                        ? 'CheckIcon'
                        : ($iconName ?? 'CheckIcon');
                    array_unshift(
                        $content,
                        $icon::make([
                            'icon' => $leadingIcon,
                            'size' => 18,
                            'decorative' => true,
                        ])->style(new Style(
                            width: 20.0,
                            height: 20.0,
                            minWidth: 20.0,
                            minHeight: 20.0,
                            // Reserve the selected-icon slot on every segment
                            // so state changes never resize neighboring targets.
                            opacity: $selected || is_string($iconName) ? 1.0 : 0.0,
                        )),
                    );
                    $toggleButtons[] = $button::make(
                        [
                            'value' => $value,
                            'variant' => 'outlined',
                            'disabled' => $disabled,
                            'accessibilityLabel' => $label,
                            'accessibilityHint' => 'Select '.$label,
                        ],
                        ...$content,
                    );
                }
                $preview = $component::make(
                    $previewProps,
                    ...$toggleButtons,
                )->onChange(function (mixed $value) use ($index): bool {
                    $this->setSampleValue($index, $value);

                    return true;
                })->style(new Style(widthPercent: 100.0));
            } elseif ($this->tag === 'p-card-actions') {
                $profile = is_string($previewProps['actionProfile'] ?? null)
                    ? $previewProps['actionProfile']
                    : 'pair';
                $preview = $this->cardActionsInteractionPreviews[$index]
                    ??= new CardActionsInteractionPreview($profile);
            } elseif ($this->tag === 'p-card') {
                $cardActions = MaterialComponentMap::TAGS['p-card-actions'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $profile = is_string($previewProps['cardProfile'] ?? null)
                    ? $previewProps['cardProfile']
                    : 'elevated';
                $interaction = is_string($this->sampleValues[$index] ?? null)
                    ? $this->sampleValues[$index]
                    : null;
                $disabled = ($previewProps['disabled'] ?? false) === true;
                $interactionDisabled = $disabled
                    || ($previewProps['loading'] ?? false) === true;
                $title = match ($interaction) {
                    'card' => 'Card activated',
                    'details' => 'Details selected',
                    'continue' => 'Continue selected',
                    default => match ($profile) {
                        'filled' => 'Quiet workspace',
                        'outlined' => 'Release checklist',
                        'horizontal' => 'Native everywhere',
                        'loading' => 'Syncing changes',
                        'disabled' => 'Archived workspace',
                        'tile' => 'Edge-to-edge surface',
                        default => 'Native experience',
                    },
                };
                $subtitle = match ($profile) {
                    'horizontal' => 'Android · iOS',
                    'loading' => 'Runtime connection',
                    'disabled' => 'Read only',
                    default => 'PAM Studio',
                };
                $description = match ($interaction) {
                    'card' => 'The complete card press target responded.',
                    'details' => 'The secondary action responded independently.',
                    'continue' => 'The primary action responded independently.',
                    default => match ($profile) {
                        'filled' => 'A calm filled surface for related information.',
                        'outlined' => 'Typography, border and actions stay on the Material grid.',
                        'horizontal' => 'One typed PHP interface, rendered by each native platform.',
                        'loading' => 'Publishing the latest component contract.',
                        'disabled' => 'Actions and the parent surface are unavailable.',
                        'tile' => 'Square geometry for intentional edge-aligned layouts.',
                        default => 'Build polished products with platform-native views.',
                    },
                };
                $content = Column::make(
                    Text::make($title)->style(new Style(
                        fontSize: 20.0,
                        lineHeight: 28.0,
                        fontWeight: 500,
                        textColor: $theme->color(ColorToken::OnSurface),
                    )),
                    Text::make($subtitle)->style(new Style(
                        fontSize: 12.0,
                        lineHeight: 16.0,
                        fontWeight: 500,
                        textColor: $theme->color(ColorToken::MutedForeground),
                    )),
                    Text::make($description)->style(new Style(
                        marginTop: 8.0,
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        fontWeight: 400,
                        textColor: $theme->color(ColorToken::OnSurface),
                    )),
                )->style(new Style(
                    widthPercent: 100.0,
                    paddingHorizontal: 16.0,
                    paddingTop: 16.0,
                    paddingBottom: 8.0,
                    gap: 4.0,
                    flexGrow: 1.0,
                    flexShrink: 1.0,
                ));
                $actions = $cardActions::make(
                    [],
                    $button::make(
                        [
                            'variant' => 'text',
                            'disabled' => $interactionDisabled,
                            'accessibilityLabel' => 'Show card details',
                        ],
                        Text::make('Details'),
                    )->onPress(function () use ($index): bool {
                        $this->setSampleValue($index, 'details');

                        return true;
                    }),
                    $button::make(
                        [
                            'variant' => 'tonal',
                            'disabled' => $interactionDisabled,
                            'accessibilityLabel' => 'Continue from card',
                        ],
                        Text::make('Continue'),
                    )->onPress(function () use ($index): bool {
                        $this->setSampleValue($index, 'continue');

                        return true;
                    }),
                );
                $children = [$content];
                if ($profile === 'elevated') {
                    $children[] = $actions;
                }
                if ($profile === 'horizontal') {
                    array_unshift(
                        $children,
                        View::make(
                            Text::make('PAM')->style(new Style(
                                fontSize: 20.0,
                                lineHeight: 28.0,
                                fontWeight: 700,
                                textColor: $theme->color(ColorToken::PrimaryForeground),
                            )),
                        )->style(new Style(
                            width: 112.0,
                            minWidth: 112.0,
                            minHeight: 188.0,
                            alignSelf: Align::Stretch,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                            backgroundColor: $theme->color(ColorToken::Primary),
                        )),
                    );
                    $children = [
                        $children[0],
                        Column::make($content)->style(new Style(
                            flexGrow: 1.0,
                            flexShrink: 1.0,
                        )),
                    ];
                }
                $preview = $component::make(
                    $previewProps,
                    ...$children,
                );
                if (in_array($profile, ['interactive', 'disabled'], true)) {
                    // Component state belongs to the PHP object instance. Keep
                    // each interactive sample stable across route renders so a
                    // native event cannot be followed by a fresh initial state.
                    $preview = $this->cardInteractionPreviews[$index]
                        ??= new CardInteractionPreview($previewProps);
                }
            } elseif ($this->tag === 'p-app-bar-nav-icon') {
                $navigationRequested = (bool) ($this->sampleValues[$index] ?? false);
                $baseAccessibilityLabel = is_string(
                    $previewProps['accessibilityLabel'] ?? null,
                ) && $previewProps['accessibilityLabel'] !== ''
                    ? $previewProps['accessibilityLabel']
                    : 'Open navigation';
                $baseIcon = is_string($previewProps['icon'] ?? null)
                    ? $previewProps['icon']
                    : 'menu';
                $previewProps['accessibilityLabel'] = $navigationRequested
                    ? 'Close navigation'
                    : $baseAccessibilityLabel;
                $previewProps['icon'] = $navigationRequested ? 'close' : $baseIcon;
                $preview = $component::make($previewProps)->onPress(
                    function () use ($index, $navigationRequested): bool {
                        $this->setSampleValue($index, !$navigationRequested);

                        return true;
                    },
                );
            } elseif ($this->belongsTo(['p-app-bar', 'p-toolbar'])) {
                $button = MaterialComponentMap::TAGS['p-icon-btn'];
                $barChildren = [];
                $barState = $this->sampleValues[$index] ?? null;
                if ($this->tag === 'p-app-bar') {
                    $nav = MaterialComponentMap::TAGS['p-app-bar-nav-icon'];
                    $barChildren[] = $nav::make(
                        [
                            'accessibilityLabel' => 'Open navigation',
                            'icon' => 'menu',
                        ],
                    )->onPress(function () use ($index): bool {
                        $this->setSampleValue($index, 'navigation');

                        return true;
                    });
                }
                $barChildren[] = Text::make(match ($barState) {
                    'navigation' => 'Navigation requested',
                    'options' => 'Options requested',
                    default => 'PAM Workspace',
                })->style(
                    new Style(
                        fontSize: 20.0,
                        lineHeight: 28.0,
                        fontWeight: 600,
                        textColor: $theme->color(ColorToken::OnSurface),
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                    ),
                );
                $barChildren[] = $button::make(
                    [
                        'accessibilityLabel' => 'More options',
                        'icon' => 'more-vert',
                        'variant' => 'text',
                    ],
                )->onPress(function () use ($index): bool {
                    $this->setSampleValue($index, 'options');

                    return true;
                });
                $preview = $component::make($previewProps, ...$barChildren);
            } elseif ($this->tag === 'p-list') {
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $selectedItem = self::stringValue($this->sampleValues[$index] ?? 'design');
                $listEntries = [
                    ['design', 'Design system', 'Tokens and components'],
                    ['native', 'Native runtime', 'Android and iOS'],
                    ['release', 'Release', 'Validated packages'],
                ];
                $listChildren = [];
                foreach ($listEntries as [$value, $title, $supporting]) {
                    $selected = $selectedItem === $value;
                    $listChildren[] = $listItem::make(
                        [
                            'value' => $value,
                            'lines' => 2,
                            'selected' => $selected,
                            'accessibilityLabel' => $title.', '.$supporting,
                            'accessibilityHint' => 'Select '.$title,
                        ],
                        Text::make($title)->style(new Style(
                            textColor: $selected
                                ? $theme->color(ColorToken::AccentForeground)
                                : $theme->color(ColorToken::OnSurface),
                            fontSize: 16.0,
                            lineHeight: 24.0,
                            fontWeight: 500,
                        )),
                        Text::make($supporting)->style(new Style(
                            textColor: $selected
                                ? $theme->color(ColorToken::AccentForeground)
                                : $theme->color(ColorToken::MutedForeground),
                            fontSize: 14.0,
                            lineHeight: 20.0,
                        )),
                    )->onPress(function () use ($index, $value): bool {
                        $this->setSampleValue($index, $value);

                        return true;
                    });
                }
                $preview = $component::make(
                    [...$previewProps, 'accessibilityLabel' => 'Product areas'],
                    ...$listChildren,
                );
            } elseif ($this->tag === 'p-infinite-scroll') {
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $profile = self::stringValue($previewProps['infiniteProfile'] ?? 'loading');
                unset($previewProps['infiniteProfile']);
                $infiniteItems = [];
                $infiniteEntries = [
                    ['Aurora', 'Design system foundations'],
                    ['Atlas', 'Native navigation patterns'],
                    ['Canvas', 'Forms and validation'],
                    ['Pulse', 'Realtime product feedback'],
                ];
                // Keep the complete state matrix above the system navigation
                // area on compact Android screens. The interaction, terminal,
                // manual and error states matter more here than repeating rows.
                $visibleEntryCount = $profile === 'gallery' ? 1 : ($profile === 'loading' ? 2 : 1);
                foreach (array_slice($infiniteEntries, 0, $visibleEntryCount) as [$team, $supporting]) {
                    $infiniteItems[] = $listItem::make(
                        ['value' => strtolower($team), 'lines' => 2],
                        Text::make($team)->style(new Style(
                            fontSize: 16.0,
                            lineHeight: 24.0,
                            fontWeight: 500,
                        )),
                        Text::make($supporting)->style(new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                        )),
                    );
                }
                if ($profile === 'gallery') {
                    $manualLoaded = ($this->sampleValues[$index] ?? null) === 'loaded';
                    $manualAction = $button::make(
                        [
                            'variant' => 'text',
                            'size' => 'small',
                            'disabled' => $manualLoaded,
                            'accessibilityLabel' => $manualLoaded
                                ? '4 more items loaded'
                                : 'Load more items',
                        ],
                        Text::make($manualLoaded ? '4 items loaded' : 'Load more'),
                    );
                    if (!$manualLoaded) {
                        $manualAction = $manualAction->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, 'loaded');

                            return true;
                        });
                    }
                    $statusRows = [
                        ['Loading', Row::make(
                            ActivityIndicator::make()->color($theme->color(ColorToken::Primary)),
                            Text::make('Loading more'),
                        )->style(new Style(gap: 10.0, alignItems: Align::Center))],
                        ['Complete', Text::make('You are all caught up')->style(new Style(
                            textColor: $theme->color(ColorToken::MutedForeground),
                        ))],
                        ['Manual', $manualAction],
                        ['Error', Text::make('Could not load more')->style(new Style(
                            textColor: $theme->color(ColorToken::Destructive),
                        ))],
                    ];
                    foreach ($statusRows as [$statusLabel, $statusContent]) {
                        $infiniteItems[] = Row::make(
                            Text::make($statusLabel)->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                fontWeight: 600,
                                textColor: $theme->color(ColorToken::MutedForeground),
                            )),
                            $statusContent,
                        )->style(new Style(
                            widthPercent: 100.0,
                            minHeight: 44.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ));
                    }
                    $preview = $component::make($previewProps, ...$infiniteItems)
                        ->style(new Style(widthPercent: 100.0, minHeight: 320.0));
                    $samples[] = Column::make($caption, $preview)->style(new Style(
                        widthPercent: 100.0,
                        gap: 8.0,
                    ));
                    continue;
                }
                $status = match ($profile) {
                    'end' => Text::make('You are all caught up')->style(new Style(
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        textColor: $theme->color(ColorToken::MutedForeground),
                    )),
                    'error' => Text::make('Could not load more items')->style(new Style(
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        textColor: $theme->color(ColorToken::Destructive),
                    )),
                    'manual' => $button::make(
                        ['variant' => 'text', 'size' => 'small', 'accessibilityLabel' => 'Load more items'],
                        Text::make('Load more'),
                    )->onPress(function () use ($index): bool {
                        $this->setSampleValue($index, 'loaded');

                        return true;
                    }),
                    default => Row::make(
                        ActivityIndicator::make()->color($theme->color(ColorToken::Primary)),
                        Text::make('Loading more')->style(new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                        )),
                    )->style(new Style(gap: 12.0, alignItems: Align::Center)),
                };
                if ($profile === 'manual' && ($this->sampleValues[$index] ?? null) === 'loaded') {
                    $status = Text::make('4 more items loaded')->style(new Style(
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        textColor: $theme->color(ColorToken::Primary),
                    ));
                }
                $infiniteItems[] = Row::make($status)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 56.0,
                    alignItems: Align::Center,
                    justifyContent: Justify::Center,
                ));
                $preview = $component::make($previewProps, ...$infiniteItems)
                    ->style(new Style(widthPercent: 100.0));
            } elseif ($this->tag === 'p-empty-state') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $viewportHeight = NativeApp::windowMetrics()->height;
                $shortViewport = $viewportHeight > 0.0 && $viewportHeight < 500.0;
                $profile = is_string($previewProps['emptyStateProfile'] ?? null)
                    ? $previewProps['emptyStateProfile']
                    : 'default';
                unset($previewProps['emptyStateProfile']);
                $activated = ($this->sampleValues[$index] ?? null) === 'activated';
                [$iconName, $title, $description, $action] = $activated
                    ? [
                        'CheckCircleIcon',
                        'Ready to go',
                        'The empty-state action completed successfully.',
                        'Do it again',
                    ]
                    : match ($profile) {
                        'search' => [
                            'SearchIcon',
                            'No matching results',
                            'Try a different term or clear the active filters.',
                            'Clear filters',
                        ],
                        'offline' => [
                            'GlobeIcon',
                            'You are offline',
                            'Reconnect to load the latest workspace activity.',
                            'Try again',
                        ],
                        'permission' => [
                            'LockIcon',
                            'Access required',
                            'Allow photo access to add media from this device.',
                            'Review access',
                        ],
                        'compact' => [
                            'MailIcon',
                            'Inbox clear',
                            'New notifications will appear here.',
                            'Refresh',
                        ],
                        default => [
                            'AddIcon',
                            'Nothing here yet',
                            'Create the first item to get started.',
                            'Create item',
                        ],
                    };
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'accessibilityLabel' => $title.'. '.$description,
                    ],
                    View::make(
                        $icon::make([
                            'icon' => $iconName,
                            'size' => $shortViewport ? 24 : 32,
                            'decorative' => true,
                        ])->style(new Style(
                            width: $shortViewport ? 24.0 : 32.0,
                            height: $shortViewport ? 24.0 : 32.0,
                            minWidth: $shortViewport ? 24.0 : 32.0,
                            minHeight: $shortViewport ? 24.0 : 32.0,
                        )),
                    )->style(new Style(
                        width: $shortViewport ? 48.0 : 64.0,
                        height: $shortViewport ? 48.0 : 64.0,
                        minWidth: $shortViewport ? 48.0 : 64.0,
                        minHeight: $shortViewport ? 48.0 : 64.0,
                        borderRadius: $shortViewport ? 24.0 : 32.0,
                        backgroundColor: $theme->color(ColorToken::Accent),
                        alignItems: Align::Center,
                        justifyContent: Justify::Center,
                    )),
                    Text::make($title)->style(new Style(
                        fontSize: $shortViewport ? 20.0 : 22.0,
                        lineHeight: $shortViewport ? 24.0 : 28.0,
                        fontWeight: 600,
                        textColor: $theme->color(ColorToken::OnSurface),
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                    Text::make($description)->style(new Style(
                        maxWidth: 360.0,
                        fontSize: $shortViewport ? 13.0 : 14.0,
                        lineHeight: $shortViewport ? 18.0 : 20.0,
                        textColor: $theme->color(ColorToken::MutedForeground),
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                    $button::make(
                        [
                            'variant' => $activated ? 'outlined' : 'tonal',
                            'accessibilityLabel' => $action,
                        ],
                        Text::make($action),
                    )->style(new Style(minWidth: 160.0))
                        ->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, 'activated');

                            return true;
                        }),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: $shortViewport
                        ? 184.0
                        : ($profile === 'compact' ? 200.0 : 264.0),
                    height: $shortViewport ? 184.0 : null,
                    maxHeight: $shortViewport ? 184.0 : null,
                    paddingHorizontal: 24.0,
                    paddingVertical: $shortViewport ? 4.0 : 24.0,
                    gap: $shortViewport ? 4.0 : 12.0,
                    alignItems: Align::Center,
                    justifyContent: Justify::Center,
                    backgroundColor: 0x00000000,
                    elevation: 0.0,
                ));
            } elseif ($this->tag === 'p-timeline') {
                $item = MaterialComponentMap::TAGS['p-timeline-item'];
                $timelineEntries = [
                    ['✓', 'Design approved', 'Tokens and component anatomy locked', '09:12', true],
                    ['✓', 'Android validated', 'Layout and interactions passed on device', '10:46', true],
                    ['3', 'Release ready', 'Documentation evidence is being prepared', 'Now', false],
                ];
                $timelineChildren = [];
                foreach ($timelineEntries as [$marker, $title, $supporting, $time, $complete]) {
                    $timelineChildren[] = $item::make(
                        ['complete' => $complete, 'active' => !$complete],
                        Column::make(
                            Row::make(
                                Text::make($title)->style(new Style(
                                    fontSize: 16.0,
                                    lineHeight: 22.0,
                                    fontWeight: 600,
                                    textColor: $theme->color(ColorToken::OnSurface),
                                    flexGrow: 1.0,
                                    flexShrink: 1.0,
                                )),
                                Text::make($time)->style(new Style(
                                    fontSize: 12.0,
                                    lineHeight: 18.0,
                                    textColor: $theme->color(ColorToken::MutedForeground),
                                )),
                            )->style(new Style(widthPercent: 100.0, gap: 12.0)),
                            Text::make($supporting)->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            paddingLeft: 40.0,
                            paddingRight: 12.0,
                            flexGrow: 1.0,
                            flexShrink: 1.0,
                            gap: 2.0,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        minHeight: 72.0,
                        paddingHorizontal: 0.0,
                        paddingVertical: 10.0,
                    ));
                }
                $preview = $component::make(
                    $previewProps,
                    ...$timelineChildren,
                )->style(new Style(widthPercent: 100.0, gap: 2.0));
            } elseif ($this->tag === 'p-list-item') {
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $profile = self::stringValue($previewProps['listItemProfile'] ?? 'two');
                unset($previewProps['listItemProfile']);
                $pressed = (bool) ($this->sampleValues[$index] ?? false);
                $requestedLines = $previewProps['lines'] ?? match ($profile) {
                    'one' => 1,
                    'three', 'long' => 3,
                    default => 2,
                };
                $lineProfile = is_scalar($requestedLines)
                    ? (string) $requestedLines
                    : 'two';
                $selected = $profile === 'selected' || $pressed;
                $itemForeground = $selected
                    ? $theme->color(ColorToken::AccentForeground)
                    : $theme->color(ColorToken::OnSurface);
                $title = $pressed ? 'Design system selected' : 'Design system';
                $copy = [
                    Text::make($title)->style(new Style(
                        textColor: $itemForeground,
                        fontSize: 16.0,
                        lineHeight: 24.0,
                        fontWeight: 500,
                    )),
                ];
                if (!in_array($lineProfile, ['1', 'one'], true)) {
                    $copy[] = Text::make(
                        in_array($lineProfile, ['3', 'three'], true)
                            ? 'Tokens, components and guidance for native product teams.'
                            : '84 production-ready components',
                    )->style(new Style(
                        textColor: $selected
                            ? $itemForeground
                            : $theme->color(ColorToken::MutedForeground),
                        fontSize: 14.0,
                        lineHeight: 20.0,
                    ));
                }
                $disabled = $profile === 'disabled';
                $previewProps['lines'] = (int) match ($lineProfile) {
                    '3', 'three' => 3,
                    '1', 'one' => 1,
                    default => 2,
                };
                $previewProps['disabled'] = $disabled;
                $previewProps['selected'] = $selected;
                $previewProps['accessibilityLabel'] = $title;
                $previewProps['accessibilityHint'] = $disabled
                    ? 'Unavailable'
                    : 'Opens design system';
                $preview = $component::make(
                    $previewProps,
                    $icon::make([
                        'icon' => 'SettingsIcon',
                        'size' => 'small',
                        'color' => $itemForeground,
                        'accessibilityElementsHidden' => true,
                    ]),
                    Column::make(...$copy)->style(new Style(
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                        gap: 0.0,
                    )),
                    $icon::make([
                        'icon' => $pressed ? 'CheckIcon' : 'ChevronRightIcon',
                        'size' => 'small',
                        'color' => $itemForeground,
                        'accessibilityElementsHidden' => true,
                    ]),
                );
                if (!$disabled) {
                    $preview = $preview->onPress(function () use ($index, $pressed): bool {
                        $this->setSampleValue($index, !$pressed);

                        return true;
                    });
                }
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
                if (($previewProps['orientation'] ?? null) === 2) {
                    $preview = Row::make(
                        Text::make('Leading content'),
                        $component::make($previewProps),
                        Text::make('Trailing content'),
                    )->style(new Style(
                        widthPercent: 100.0,
                        height: 64.0,
                        gap: 16.0,
                        alignItems: Align::Center,
                    ));
                } else {
                    $preview = Column::make(
                        Text::make('Content above'),
                        $component::make($previewProps),
                        Text::make('Content below'),
                    )->style(new Style(
                        widthPercent: 100.0,
                        gap: 12.0,
                    ));
                }
            } elseif ($this->tag === 'p-banner') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $actions = MaterialComponentMap::TAGS['p-banner-actions'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $bannerState = $this->sampleValues[$index] ?? null;
                $bannerProfile = is_string($previewProps['bannerProfile'] ?? null)
                    ? $previewProps['bannerProfile']
                    : (($previewProps['closable'] ?? false) === true
                        ? 'dismissible'
                        : 'default');
                if ($bannerState === 'dismissed') {
                    $preview = Row::make(
                        Text::make('Banner dismissed')->style(new Style(
                            textColor: $theme->color(ColorToken::MutedForeground),
                            fontSize: 14.0,
                            lineHeight: 20.0,
                        )),
                        $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Restore'),
                        )->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, null);

                            return true;
                        }),
                    )->style(new Style(
                        widthPercent: 100.0,
                        minHeight: 48.0,
                        gap: 8.0,
                        alignItems: Align::Center,
                        justifyContent: Justify::SpaceBetween,
                    ));
                } else {
                    $message = match (true) {
                        $bannerState === 'later' =>
                            'Reminder scheduled for tomorrow.',
                        $bannerState === 'updated' =>
                            'Update started. Downloading securely in the background.',
                        $bannerState === 'acknowledged' =>
                            'Message acknowledged.',
                        $bannerProfile === 'one-line' =>
                            'Your workspace is back online.',
                        $bannerProfile === 'three-lines' =>
                            'A new release is ready. Install it now to receive performance improvements and the latest accessibility fixes.',
                        $bannerProfile === 'icon' =>
                            'Cloud sync is paused. Review your connection before continuing.',
                        ($previewProps['type'] ?? null) === 'success' =>
                            'Success: the latest release was installed.',
                        ($previewProps['type'] ?? null) === 'info' =>
                            'Information: a newer release is available.',
                        ($previewProps['type'] ?? null) === 'warning' =>
                            'Warning: connect to power before updating.',
                        ($previewProps['type'] ?? null) === 'error' =>
                            'Error: the update could not be downloaded.',
                        default =>
                            'A new native release is ready with performance and accessibility improvements.',
                    };
                    $previewProps['accessibilityLabel'] = 'Banner. '.$message;
                    $messageText = Text::make($message)->style(new Style(
                        textColor: $theme->color(ColorToken::Foreground),
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        fontWeight: 400,
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                    ));
                    $iconName = match ($previewProps['type'] ?? null) {
                        'success' => 'CheckCircleIcon',
                        'info' => 'InfoIcon',
                        'warning' => 'AlertCircleIcon',
                        'error' => 'CloseCircleIcon',
                        default => $bannerProfile === 'icon' ? 'InfoIcon' : null,
                    };
                    $accentToken = match ($previewProps['type'] ?? null) {
                        'success' => ColorToken::Success,
                        'info' => ColorToken::Info,
                        'warning' => ColorToken::Warning,
                        'error' => ColorToken::Destructive,
                        default => ColorToken::Primary,
                    };
                    $content = $messageText;
                    if ($iconName !== null) {
                        $content = Row::make(
                            View::make($icon::make([
                                'icon' => $iconName,
                                'color' => $theme->color($accentToken),
                                'size' => 'small',
                            ]))->style(new Style(
                                width: 40.0,
                                height: 40.0,
                                minWidth: 40.0,
                                minHeight: 40.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                            $messageText,
                        )->style(new Style(
                            widthPercent: 100.0,
                            gap: 8.0,
                            alignItems: Align::Start,
                        ));
                    }

                    $actionButtons = [];
                    if ($bannerProfile === 'dismissible') {
                        $actionButtons[] = $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Dismiss'),
                        )->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, 'dismissed');

                            return true;
                        });
                    } elseif ($bannerProfile === 'single-action') {
                        $actionButtons[] = $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Got it'),
                        )->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, 'acknowledged');

                            return true;
                        });
                    } else {
                        $actionButtons[] = $button::make(
                            [
                                'variant' => 'text',
                                'size' => 'small',
                                'disabled' => $bannerProfile === 'disabled-action',
                            ],
                            Text::make('Later'),
                        )->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, 'later');

                            return true;
                        });
                        $actionButtons[] = $button::make(
                            ['variant' => 'text', 'size' => 'small'],
                            Text::make('Update'),
                        )->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, 'updated');

                            return true;
                        });
                    }
                    $preview = $component::make(
                        $previewProps,
                        $content,
                        $actions::make([], ...$actionButtons),
                    );
                }
            } elseif ($this->tag === 'p-banner-actions') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $actionState = is_string($this->sampleValues[$index] ?? null)
                    ? $this->sampleValues[$index]
                    : null;
                $actionProfile = is_string($previewProps['actionProfile'] ?? null)
                    ? $previewProps['actionProfile']
                    : 'default';
                unset($previewProps['actionProfile']);
                $previewProps['accessibilityLabel'] = match ($actionProfile) {
                    'single' => 'Banner actions. Single action.',
                    'disabled' => 'Banner actions. Disabled leading action.',
                    'long' => 'Banner actions. Long labels.',
                    'independent' => 'Banner actions. Independent pair.',
                    default => 'Banner actions. Default pair.',
                };

                $makeAction = function (
                    string $label,
                    string $nextState,
                    bool $disabled = false,
                ) use ($button, $index): UiComponent {
                    return $button::make(
                        [
                            'variant' => 'text',
                            'size' => 'small',
                            'disabled' => $disabled,
                        ],
                        Text::make($label),
                    )->onPress(function () use ($index, $nextState): bool {
                        $this->setSampleValue($index, $nextState);

                        return true;
                    });
                };

                $actionButtons = match ($actionProfile) {
                    'single' => [
                        $makeAction(
                            $actionState === 'acknowledged'
                                ? 'Acknowledged'
                                : 'Got it',
                            'acknowledged',
                            $actionState === 'acknowledged',
                        ),
                    ],
                    'disabled' => [
                        $makeAction('Later', 'later', true),
                        $makeAction(
                            $actionState === 'updating' ? 'Updating' : 'Update',
                            'updating',
                        ),
                    ],
                    'long' => [
                        $makeAction(
                            $actionState === 'reminded' ? 'Reminder set' : 'Remind me later',
                            'reminded',
                        ),
                        $makeAction(
                            $actionState === 'installing' ? 'Installing' : 'Install update',
                            'installing',
                        ),
                    ],
                    'independent' => [
                        $makeAction(
                            $actionState === 'cancelled' ? 'Cancelled' : 'Cancel',
                            'cancelled',
                        ),
                        $makeAction(
                            $actionState === 'retrying' ? 'Retrying' : 'Retry',
                            'retrying',
                        ),
                    ],
                    default => [
                        $makeAction(
                            $actionState === 'later' ? 'Scheduled' : 'Later',
                            'later',
                        ),
                        $makeAction(
                            $actionState === 'updating' ? 'Updating' : 'Update',
                            'updating',
                        ),
                    ],
                };
                $preview = $component::make($previewProps, ...$actionButtons);
            } elseif ($this->tag === 'p-alert') {
                $alertProfile = match ($previewProps['type'] ?? null) {
                    'success' => [
                        'Changes saved',
                        'Your workspace is synced and ready to use.',
                    ],
                    'info' => [
                        'Update available',
                        'A newer workspace configuration is ready to review.',
                    ],
                    'warning' => [
                        'Review required',
                        'Check these settings before continuing.',
                    ],
                    'error' => [
                        'We could not save the changes',
                        'Review your connection and try again.',
                    ],
                    default => [
                        'Workspace ready',
                        'Native tokens and interactions are synced across platforms.',
                    ],
                };
                $copy = Column::make(
                    Text::make($alertProfile[0])->style(new Style(
                        fontSize: 16.0,
                        lineHeight: 24.0,
                        fontWeight: 600,
                    )),
                    Text::make($alertProfile[1])->style(new Style(
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        fontWeight: 400,
                    )),
                )->style(new Style(
                    flexGrow: 1.0,
                    flexShrink: 1.0,
                    gap: 2.0,
                ));
                $previewProps['accessibilityLabel'] =
                    $alertProfile[0].'. '.$alertProfile[1];
                $preview = $component::make($previewProps, $copy);
            } elseif ($this->tag === 'p-btn-group') {
                $button = MaterialComponentMap::TAGS['p-btn'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $groupProfile = self::stringValue(
                    $previewProps['buttonGroupProfile'] ?? 'default'
                );
                unset($previewProps['buttonGroupProfile']);
                $groupOptions = match ($groupProfile) {
                    'connected' => [
                        ['list', 'List', null, false],
                        ['board', 'Board', null, false],
                        ['timeline', 'Timeline', null, false],
                    ],
                    'full-width' => [
                        ['today', 'Today', null, false],
                        ['week', '7 days', null, false],
                        ['month', '30 days', null, false],
                    ],
                    'optional' => [
                        ['grid', 'Grid', null, false],
                        ['list', 'List', null, false],
                        ['compact', 'Compact', null, false],
                    ],
                    'mandatory' => [
                        ['daily', 'Daily', null, false],
                        ['weekly', 'Weekly', null, false],
                        ['monthly', 'Monthly', null, false],
                    ],
                    'multiple' => [
                        ['bold', 'Bold', null, false],
                        ['italic', 'Italic', null, false],
                        ['underline', 'Underline', null, false],
                    ],
                    'icons' => [
                        ['new', 'New', 'AddIcon', false],
                        ['recent', 'Recent', 'ClockIcon', false],
                        ['saved', 'Saved', 'FavouriteIcon', false],
                    ],
                    'compact' => [
                        ['left', 'Left', null, false],
                        ['center', 'Center', null, false],
                        ['right', 'Right', null, false],
                    ],
                    'rounded' => [
                        ['personal', 'Personal', null, false],
                        ['team', 'Team', null, false],
                        ['company', 'Company', null, false],
                    ],
                    'tile' => [
                        ['one', 'One', null, false],
                        ['two', 'Two', null, false],
                        ['three', 'Three', null, false],
                    ],
                    'disabled-item' => [
                        ['view', 'View', null, false],
                        ['edit', 'Edit', null, true],
                        ['share', 'Share', null, false],
                    ],
                    'disabled-group' => [
                        ['read', 'Read', null, false],
                        ['write', 'Write', null, false],
                        ['admin', 'Admin', null, false],
                    ],
                    'long' => [
                        ['recent', 'Recent activity', null, false],
                        ['assigned', 'Assigned to me', null, false],
                    ],
                    'rtl' => [
                        ['day', 'Day', null, false],
                        ['week', 'Week', null, false],
                        ['month', 'Month', null, false],
                    ],
                    'success' => [
                        ['draft', 'Draft', null, false],
                        ['review', 'Review', null, false],
                        ['published', 'Published', null, false],
                    ],
                    default => [
                        ['day', 'Day', null, false],
                        ['week', 'Week', null, false],
                        ['month', 'Month', null, false],
                    ],
                };
                $selectedValue = array_key_exists($index, $this->sampleValues)
                    ? $this->sampleValues[$index]
                    : ($previewProps['modelValue'] ?? null);
                $previewProps['modelValue'] = $selectedValue;
                $groupButtons = [];
                foreach ($groupOptions as [$value, $label, $iconName, $disabled]) {
                    $content = [Text::make($label)];
                    if (is_string($iconName)) {
                        array_unshift(
                            $content,
                            $icon::make(['icon' => $iconName, 'size' => 18])
                                ->style(new Style(
                                    width: 20.0,
                                    height: 20.0,
                                    minWidth: 20.0,
                                    minHeight: 20.0,
                                )),
                        );
                    }
                    $groupButtons[] = $button::make(
                        [
                            'value' => $value,
                            'variant' => 'outlined',
                            'disabled' => $disabled,
                            'accessibilityLabel' => $label,
                            'accessibilityHint' => 'Select '.$label,
                        ],
                        ...$content,
                    );
                }
                $preview = $component::make(
                    $previewProps,
                    ...$groupButtons,
                )->onChange(function (mixed $value) use ($index): bool {
                    $this->setSampleValue($index, $value);

                    return true;
                })->style(new Style(widthPercent: 100.0));
            } elseif ($this->belongsTo([
                'p-radio-group', 'p-item-group',
            ])) {
                $itemTag = match ($this->tag) {
                    'p-radio-group' => 'p-radio',
                    'p-item-group' => 'p-item',
                    default => 'p-checkbox',
                };
                $item = MaterialComponentMap::TAGS[$itemTag];
                $groupValue = $this->sampleValues[$index]
                    ?? ($previewProps['modelValue'] ?? 'design');
                $previewProps['modelValue'] = $groupValue;
                $selectedValues = is_array($groupValue)
                    ? array_map(static fn (mixed $value): string => self::stringValue($value), $groupValue)
                    : [self::stringValue($groupValue)];
                $groupDisabled = ($previewProps['disabled'] ?? false) === true;
                $disabledValues = array_map(
                    static fn (mixed $value): string => self::stringValue($value),
                    is_array($previewProps['disabledValues'] ?? null)
                        ? $previewProps['disabledValues']
                        : [],
                );
                $preview = $component::make(
                    $previewProps,
                    $item::make(
                        [
                            'value' => 'design',
                            'label' => 'Design',
                            'checked' => in_array('design', $selectedValues, true),
                            'selected' => in_array('design', $selectedValues, true),
                            'disabled' => $groupDisabled || in_array('design', $disabledValues, true),
                            'accessibilityLabel' => 'Design',
                        ],
                    ),
                    $item::make(
                        [
                            'value' => 'engineering',
                            'label' => 'Engineering',
                            'checked' => in_array('engineering', $selectedValues, true),
                            'selected' => in_array('engineering', $selectedValues, true),
                            'disabled' => $groupDisabled || in_array('engineering', $disabledValues, true),
                            'accessibilityLabel' => 'Engineering',
                        ],
                    ),
                    $item::make(
                        [
                            'value' => 'product',
                            'label' => 'Product',
                            'checked' => in_array('product', $selectedValues, true),
                            'selected' => in_array('product', $selectedValues, true),
                            'disabled' => $groupDisabled || in_array('product', $disabledValues, true),
                            'accessibilityLabel' => 'Product',
                        ],
                    ),
                )->onChange(function (mixed $value) use ($index): bool {
                    $this->setSampleValue($index, $value);

                    return true;
                });
            } elseif ($this->tag === 'p-item') {
                $selected = array_key_exists($index, $this->sampleValues)
                    ? (bool) $this->sampleValues[$index]
                    : (bool) ($previewProps['selected'] ?? $previewProps['checked'] ?? false);
                $disabled = ($previewProps['disabled'] ?? false) === true;
                $label = self::stringValue($previewProps['label'] ?? 'Design');
                $previewProps['selected'] = $selected;
                $previewProps['checked'] = $selected;
                $previewProps['accessibilityLabel'] = $label;
                $preview = $component::make($previewProps, Text::make($label));
                if (!$disabled) {
                    $preview = $preview->onChange(function (mixed $value) use ($index): bool {
                        $this->setSampleValue($index, $this->isEnabledValue($value));

                        return true;
                    });
                }
            } elseif ($this->belongsTo([
                'p-checkbox', 'p-radio',
                'p-switch',
            ])) {
                $selected = array_key_exists($index, $this->sampleValues)
                    ? (bool) $this->sampleValues[$index]
                    : (bool) ($previewProps['checked'] ?? false);
                $previewProps['checked'] = $selected;
                $previewProps['modelValue'] = $selected;
                if (is_string($previewProps['label'] ?? null)) {
                    $previewProps['accessibilityLabel'] = $previewProps['label'];
                }
                $preview = $component::make($previewProps)
                    ->onChange(function (mixed $value) use ($index): bool {
                        $this->setSampleValue($index, $this->isEnabledValue($value));

                        return true;
                    });
            } elseif ($this->tag === 'p-time-picker') {
                $selectedTime = array_key_exists($index, $this->sampleValues)
                    ? $this->sampleValues[$index]
                    : ($previewProps['modelValue'] ?? '14:35');
                $previewProps['modelValue'] = $selectedTime;
                $preview = $component::make($previewProps)
                    ->onChange(function (mixed $value) use ($index): bool {
                        $this->setSampleValue($index, $value);

                        return true;
                    });
            } elseif ($this->tag === 'p-calendar-day') {
                $profile = self::stringValue($previewProps['calendarDayProfile'] ?? 'day');
                unset($previewProps['calendarDayProfile']);
                if ($profile === 'range') {
                    $preview = Row::make(
                        $component::make(
                            [
                                ...$previewProps,
                                'state' => 'range-start',
                                'selected' => true,
                            ],
                            Text::make('12'),
                        ),
                        $component::make(
                            [
                                ...$previewProps,
                                'state' => 'range-middle',
                                'selected' => false,
                            ],
                            Text::make('13'),
                        ),
                        $component::make(
                            [
                                ...$previewProps,
                                'state' => 'range-end',
                                'selected' => true,
                            ],
                            Text::make('14'),
                        ),
                    )->style(new Style(
                        gap: 0.0,
                        alignItems: Align::Center,
                    ));
                } else {
                    $selected = (bool) (
                        $this->sampleValues[$index]
                        ?? ($previewProps['selected'] ?? false)
                    );
                    $previewProps['selected'] = $selected;
                    if ($selected) {
                        $previewProps['state'] = 'selected';
                    } elseif (($previewProps['state'] ?? null) === 'selected') {
                        $previewProps['state'] = 'default';
                    }
                    $day = self::stringValue($previewProps['day'] ?? '15');
                    unset($previewProps['day']);
                    $preview = $component::make(
                        $previewProps,
                        Text::make($day),
                    );
                    if (($previewProps['disabled'] ?? false) !== true) {
                        $preview = $preview->onPress(function () use (
                            $index,
                            $selected,
                        ): bool {
                            $this->setSampleValue($index, !$selected);

                            return true;
                        });
                    }
                }
            } elseif ($this->tag === 'p-expansion-panel') {
                $title = MaterialComponentMap::TAGS['p-expansion-panel-title'];
                $text = MaterialComponentMap::TAGS['p-expansion-panel-text'];
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $expanded = (bool) ($this->sampleValues[$index] ?? true);
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'open' => $expanded,
                        'expanded' => $expanded,
                        'accessibilityLabel' => 'Product details',
                        'accessibilityHint' => $expanded
                            ? 'Double tap to collapse this section'
                            : 'Double tap to expand this section',
                    ],
                    $title::make(
                        [
                            'active' => $expanded,
                            'expanded' => $expanded,
                            'accessibilityElementsHidden' => true,
                        ],
                        Row::make(
                            Text::make('Product details')->style(new Style(
                                fontSize: 16.0,
                                lineHeight: 24.0,
                                fontWeight: 500,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            $icon::make([
                                'icon' => 'ChevronDownIcon',
                                'size' => 'small',
                            ])->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                rotation: $expanded ? 180.0 : 0.0,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            )),
                        )->style(new Style(
                            widthPercent: 100.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        )),
                    ),
                    $text::make(
                        ['active' => $expanded, 'expanded' => $expanded],
                        Text::make(
                            'Native views with shared material tokens.',
                        )->style(new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                        )),
                    ),
                )->onToggle(function (mixed $value) use ($index): bool {
                    $this->setSampleValue($index, $this->isEnabledValue($value));

                    return true;
                });
            } elseif ($this->tag === 'p-expansion-panel-title') {
                $icon = MaterialComponentMap::TAGS['p-icon'];
                $expanded = (bool) ($this->sampleValues[$index] ?? false);
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'active' => $expanded,
                        'expanded' => $expanded,
                        'accessibilityLabel' => 'Product details',
                        'accessibilityHint' => $expanded
                            ? 'Double tap to collapse this section'
                            : 'Double tap to expand this section',
                    ],
                    Row::make(
                        Text::make('Product details')->style(new Style(
                            fontSize: 16.0,
                            lineHeight: 24.0,
                            fontWeight: $expanded ? 600 : 500,
                            textColor: $theme->color(ColorToken::OnSurface),
                        )),
                        $icon::make([
                            'icon' => 'ChevronDownIcon',
                            'size' => 'small',
                            'accessibilityElementsHidden' => true,
                        ])->style(new Style(
                            width: 24.0,
                            height: 24.0,
                            minWidth: 24.0,
                            minHeight: 24.0,
                            rotation: $expanded ? 180.0 : 0.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        alignItems: Align::Center,
                        justifyContent: Justify::SpaceBetween,
                    )),
                )->onPress(function () use ($index, $expanded): bool {
                    $this->setSampleValue($index, !$expanded);

                    return true;
                });
            } elseif ($this->tag === 'p-expansion-panel-text') {
                $previewProps['accessibilityLabel'] =
                    'Product details. Native views share material tokens across platforms.';
                $preview = $component::make(
                    [...$previewProps, 'open' => true, 'expanded' => true],
                    Column::make(
                        Text::make('Built once, rendered natively')->style(new Style(
                            fontSize: 16.0,
                            lineHeight: 24.0,
                            fontWeight: 500,
                            textColor: $theme->color(ColorToken::OnSurface),
                        )),
                        Text::make(
                            'Android and iOS share the same semantic tokens while preserving platform behavior.',
                        )->style(new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            textColor: $theme->color(ColorToken::MutedForeground),
                        )),
                    )->style(new Style(gap: 8.0)),
                );
            } elseif ($this->tag === 'p-form') {
                $field = MaterialComponentMap::TAGS['p-text-field'];
                $button = MaterialComponentMap::TAGS['p-btn'];
                $formState = is_array($this->sampleValues[$index] ?? null)
                    ? $this->sampleValues[$index]
                    : [
                        'project' => 'Aurora',
                        'owner' => 'Design team',
                        'submitted' => false,
                    ];
                $projectValue = self::stringValue($formState['project'] ?? '');
                $ownerValue = self::stringValue($formState['owner'] ?? '');
                $submitted = ($formState['submitted'] ?? false) === true;
                $content = [
                    $field::make([
                        'label' => 'Project name',
                        'modelValue' => $projectValue,
                        'variant' => 'outlined',
                        'required' => true,
                        'accessibilityLabel' => 'Project name',
                    ])->onChange(function (mixed $value) use ($index, $formState): bool {
                        $formState['project'] = self::stringValue($value);
                        $formState['submitted'] = false;
                        $this->setSampleValue($index, $formState);

                        return true;
                    }),
                    $field::make([
                        'label' => 'Owner',
                        'modelValue' => $ownerValue,
                        'variant' => 'outlined',
                        'accessibilityLabel' => 'Project owner',
                    ])->onChange(function (mixed $value) use ($index, $formState): bool {
                        $formState['owner'] = self::stringValue($value);
                        $formState['submitted'] = false;
                        $this->setSampleValue($index, $formState);

                        return true;
                    }),
                ];
                if ($submitted) {
                    $content[] = Text::make('Project saved successfully')->style(new Style(
                        textColor: $theme->color(ColorToken::Success),
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        fontWeight: 500,
                    ));
                }
                $content[] = $button::make(
                    [
                        'variant' => 'flat',
                        'color' => 'primary',
                        'block' => true,
                        'accessibilityLabel' => $submitted ? 'Project saved' : 'Save project',
                    ],
                    Text::make($submitted ? 'Saved' : 'Save project'),
                )->onPress(function () use ($index, $formState): bool {
                    $formState['submitted'] = true;
                    $this->setSampleValue($index, $formState);

                    return true;
                });
                $preview = $component::make(
                    $previewProps,
                    ...$content,
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
                $imageHeight = is_numeric($previewProps['height'] ?? null)
                    ? max(1.0, (float) $previewProps['height'])
                    : 180.0;
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
                )->style(new Style(
                    widthPercent: 100.0,
                    height: $imageHeight,
                    minHeight: $imageHeight,
                ));
            } elseif ($this->tag === 'p-slide-group-item') {
                $selected = (bool) ($this->sampleValues[$index]
                    ?? $previewProps['selected']
                    ?? $previewProps['active']
                    ?? false);
                $label = self::stringValue($previewProps['label'] ?? 'Overview');
                $itemValue = self::stringValue($previewProps['value']
                    ?? strtolower(str_replace(' ', '-', $label)));
                $item = $component::make(
                    [
                        ...$previewProps,
                        'value' => $itemValue,
                        'active' => $selected,
                        'selected' => $selected,
                        '__pamButtonToggleItem' => true,
                        'selectionCornerRadius' => 22,
                        'accessibilityLabel' => $label,
                    ],
                    Text::make($label)->style(new Style(
                        textColor: $selected
                            ? $theme->color(ColorToken::SecondaryForeground)
                            : $theme->color(ColorToken::MutedForeground),
                    )),
                )->style(new Style(
                    width: 132.0,
                    minWidth: 132.0,
                    minHeight: 44.0,
                    justifyContent: Justify::Center,
                    backgroundColor: $selected
                        ? $theme->color(ColorToken::Secondary)
                        : $theme->color(ColorToken::Muted),
                    borderRadius: 22.0,
                ));
                $group = MaterialComponentMap::TAGS['p-slide-group'];
                $preview = $group::make(
                    [
                        'value' => $selected ? $itemValue : '__none__',
                        'modelValue' => $selected ? $itemValue : '__none__',
                        'mandatory' => false,
                        'accessibilityLabel' => $label . ' state',
                    ],
                    $item,
                )->onChange(function (mixed $value) use ($index): bool {
                    $this->setSampleValue($index, self::stringValue($value) !== '');

                    return true;
                })->style(new Style(
                    width: 132.0,
                    minWidth: 132.0,
                    minHeight: 44.0,
                ));
            } elseif ($this->belongsTo([
                'p-item', 'p-tab',
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
            } elseif ($this->tag === 'p-skeleton-loader') {
                $profile = self::stringValue($previewProps['skeletonProfile'] ?? 'text');
                unset($previewProps['skeletonProfile']);
                if ($profile === 'article') {
                    $preview = Column::make(
                        Row::make(
                            $component::make([...$previewProps, 'type' => 'avatar']),
                            Column::make(
                                $component::make([...$previewProps, 'type' => 'heading']),
                                $component::make([...$previewProps, 'type' => 'text']),
                            )->style(new Style(flexGrow: 1.0, gap: 10.0)),
                        )->style(new Style(widthPercent: 100.0, gap: 16.0, alignItems: Align::Center)),
                        $component::make([...$previewProps, 'type' => 'image'])->style(new Style(
                            widthPercent: 100.0,
                            height: 144.0,
                            minHeight: 144.0,
                        )),
                        $component::make([...$previewProps, 'type' => 'text']),
                        $component::make([...$previewProps, 'type' => 'text'])->style(new Style(
                            widthPercent: 72.0,
                        )),
                    )->style(new Style(widthPercent: 100.0, gap: 12.0));
                } else {
                    $preview = $component::make($previewProps)->style(new Style(widthPercent: 100.0));
                }
            } elseif ($this->tag === 'p-sparkline') {
                $preview = $component::make($previewProps)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 140.0,
                ));
            } elseif ($this->belongsTo([
                'p-stepper-header', 'p-stepper-item', 'p-stepper-window',
                'p-stepper-window-item', 'p-stepper-actions',
                'p-stepper-vertical-item', 'p-stepper-vertical-actions',
            ])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                if (str_contains($this->tag, 'actions')) {
                    $advanced = (bool) ($this->sampleValues[$index] ?? false);
                    $backAction = $button::make(
                        ['variant' => 'text', 'size' => 'small', 'accessibilityLabel' => 'Previous step'],
                        Text::make('Back'),
                    );
                    $nextAction = $button::make(
                        ['variant' => 'tonal', 'size' => 'small', 'accessibilityLabel' => 'Continue to next step'],
                        Text::make($advanced ? 'Continued' : 'Continue'),
                    )->onPress(function () use ($index): bool {
                        $this->setSampleValue($index, true);

                        return true;
                    });
                    $preview = $component::make(
                        $previewProps,
                        $backAction,
                        $nextAction,
                    )->style(new Style(
                        widthPercent: 100.0,
                        gap: 8.0,
                        justifyContent: Justify::End,
                    ));
                } elseif ($this->tag === 'p-stepper-header') {
                    $stepItem = MaterialComponentMap::TAGS['p-stepper-item'];
                    $headerChildren = [];
                    foreach ([['1', 'Account', true], ['2', 'Ship', false], ['3', 'Review', false]] as [$number, $label, $active]) {
                        $headerChildren[] = $stepItem::make(
                            ['active' => $active, 'selected' => $active, 'value' => strtolower($label)],
                            Column::make(
                                Text::make($number)->style(new Style(
                                    width: 28.0,
                                    height: 28.0,
                                    borderRadius: 14.0,
                                    backgroundColor: $active
                                        ? $theme->color(ColorToken::Primary)
                                        : $theme->color(ColorToken::Muted),
                                    textColor: $active
                                        ? $theme->color(ColorToken::PrimaryForeground)
                                        : $theme->color(ColorToken::MutedForeground),
                                    lineHeight: 28.0,
                                    textAlign: \Pam\Native\TextAlignment::Center,
                                )),
                                Text::make($label)->style(new Style(
                                    fontSize: 14.0,
                                    lineHeight: 20.0,
                                    fontWeight: 600,
                                    textColor: $theme->color(ColorToken::Primary),
                                )),
                            )->style(new Style(gap: 4.0, alignItems: Align::Center)),
                        )->style(new Style(
                            width: 72.0,
                            minWidth: 72.0,
                            padding: 0.0,
                            alignItems: Align::Center,
                        ));
                    }
                    $preview = $component::make($previewProps, ...$headerChildren)
                        ->style(new Style(widthPercent: 100.0));
                } elseif (str_contains($this->tag, 'window')) {
                    $preview = $component::make(
                        [...$previewProps, 'active' => true, 'selected' => true, 'value' => 'delivery'],
                        Column::make(
                            Text::make('Delivery address')->style(new Style(
                                fontSize: 18.0,
                                lineHeight: 24.0,
                                fontWeight: 600,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            Text::make('Choose where the package should be delivered.')->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                            )),
                        )->style(new Style(widthPercent: 100.0, paddingVertical: 16.0, gap: 6.0)),
                    )->style(new Style(widthPercent: 100.0));
                } else {
                    $state = self::stringValue($previewProps['stepState'] ?? 'active');
                    unset($previewProps['stepState']);
                    $active = (bool) ($this->sampleValues[$index] ?? ($state === 'active'));
                    $complete = $state === 'complete';
                    $disabled = $state === 'disabled';
                    $value = self::stringValue($previewProps['value'] ?? 'delivery');
                    $stepItem = $component::make(
                        [...$previewProps, 'active' => $active, 'selected' => $active, 'complete' => $complete, 'disabled' => $disabled],
                        Row::make(
                            Text::make($complete ? '✓' : '2')->style(new Style(
                                width: 28.0,
                                height: 28.0,
                                borderRadius: 14.0,
                                backgroundColor: ($active || $complete)
                                    ? $theme->color(ColorToken::Primary)
                                    : $theme->color(ColorToken::Muted),
                                textColor: ($active || $complete)
                                    ? $theme->color(ColorToken::PrimaryForeground)
                                    : $theme->color(ColorToken::MutedForeground),
                                lineHeight: 28.0,
                                textAlign: \Pam\Native\TextAlignment::Center,
                            )),
                            Text::make('Delivery')->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                fontWeight: $active ? 600 : 400,
                                textColor: ($active || $complete)
                                    ? $theme->color(ColorToken::Primary)
                                    : $theme->color(ColorToken::MutedForeground),
                            )),
                        )->style(new Style(gap: 8.0, alignItems: Align::Center)),
                    )->style(new Style(minHeight: 48.0));
                    $stepperTag = $this->tag === 'p-stepper-item'
                        ? 'p-stepper'
                        : 'p-stepper-vertical';
                    $stepper = MaterialComponentMap::TAGS[$stepperTag];
                    $preview = $stepper::make(
                        [
                            'value' => $active ? $value : '',
                            'modelValue' => $active ? $value : '',
                            'editable' => true,
                            'flat' => true,
                            'accessibilityLabel' => 'Delivery step state',
                        ],
                        $stepItem,
                    )->onChange(function (mixed $next) use ($index, $value): bool {
                        $this->setSampleValue($index, self::stringValue($next) === $value);

                        return true;
                    })->style(new Style(
                        widthPercent: 100.0,
                        backgroundColor: 0x00000000,
                        borderWidth: 0.0,
                        elevation: 0.0,
                    ));
                }
            } elseif ($this->tag === 'p-btn') {
                $buttonProfile = self::stringValue($previewProps['buttonProfile'] ?? 'label');
                $buttonLabel = self::stringValue($previewProps['buttonLabel'] ?? 'Continue');
                unset($previewProps['buttonProfile'], $previewProps['buttonLabel']);
                $pressCount = self::intValue($this->sampleValues[$index] ?? 0);
                $visibleLabel = $pressCount > 0
                    ? 'Pressed '.$pressCount
                    : $buttonLabel;
                $previewProps['accessibilityLabel'] = $buttonLabel.' button';
                $buttonChildren = [Text::make($visibleLabel)];
                if (in_array($buttonProfile, ['leading', 'trailing'], true)) {
                    $buttonIcon = MaterialComponentMap::TAGS['p-icon']::make([
                        'icon' => $buttonProfile === 'leading'
                            ? 'AddIcon'
                            : 'ChevronRightIcon',
                        'size' => 18,
                    ])->style(new Style(
                        width: 20.0,
                        height: 20.0,
                        minWidth: 20.0,
                        minHeight: 20.0,
                    ));
                    $buttonChildren = $buttonProfile === 'leading'
                        ? [$buttonIcon, ...$buttonChildren]
                        : [...$buttonChildren, $buttonIcon];
                }
                $preview = $component::make(
                    $previewProps,
                    ...$buttonChildren,
                )->onPress(function () use ($index, $pressCount): bool {
                    $this->setSampleValue($index, $pressCount + 1);

                    return true;
                });
            } elseif ($this->tag === 'p-fab') {
                $profile = self::stringValue($previewProps['fabProfile'] ?? 'default');
                unset($previewProps['fabProfile']);
                $pressed = (bool) ($this->sampleValues[$index] ?? false);
                $extended = $profile === 'extended';
                $disabled = $profile === 'disabled';
                $previewProps['extended'] = $extended;
                $previewProps['disabled'] = $disabled;
                if ($profile === 'small') {
                    $previewProps['size'] = 'small';
                } elseif ($profile === 'large') {
                    $previewProps['size'] = 'large';
                } else {
                    unset($previewProps['size']);
                }
                $label = match ($profile) {
                    'extended' => 'Create project',
                    'small' => 'Edit project',
                    'large' => 'Share project',
                    'disabled' => 'Create unavailable',
                    default => 'Create project',
                };
                $previewProps['accessibilityLabel'] = $pressed
                    ? $label.' completed'
                    : $label;
                $iconName = $pressed ? 'CheckIcon' : match ($profile) {
                    'small' => 'EditIcon',
                    'large' => 'ShareIcon',
                    default => 'AddIcon',
                };
                $fabIcon = MaterialComponentMap::TAGS['p-icon']::make([
                    'icon' => $iconName,
                    'size' => $profile === 'large' ? 'x-large' : 'small',
                    'accessibilityElementsHidden' => true,
                ]);
                $fabChildren = $extended
                    ? [$fabIcon, Text::make($pressed ? 'Created' : 'Create')]
                    : [$fabIcon];
                $preview = $component::make($previewProps, ...$fabChildren);
                if (!$disabled) {
                    $preview = $preview->onPress(function () use ($index, $pressed): bool {
                        $this->setSampleValue($index, !$pressed);

                        return true;
                    });
                }
            } elseif ($this->tag === 'p-icon-btn') {
                $profile = self::stringValue($previewProps['iconButtonProfile'] ?? 'standard');
                unset($previewProps['iconButtonProfile']);
                $pressed = (bool) ($this->sampleValues[$index] ?? false);
                $disabled = $profile === 'disabled';
                $label = match ($profile) {
                    'tonal' => 'Favorite',
                    'outlined' => 'Share',
                    'small' => 'More options',
                    'large' => 'Edit project',
                    'disabled' => 'Delete unavailable',
                    default => 'Settings',
                };
                $previewProps['accessibilityLabel'] = $pressed
                    ? $label.' activated'
                    : $label;
                $iconName = $pressed ? 'CheckIcon' : match ($profile) {
                    'tonal' => 'FavouriteIcon',
                    'outlined' => 'ShareIcon',
                    'small' => 'ThreeDotsIcon',
                    'large' => 'EditIcon',
                    'disabled' => 'TrashIcon',
                    default => 'SettingsIcon',
                };
                $previewProps['icon'] = $iconName;
                $icon = MaterialComponentMap::TAGS['p-icon']::make([
                    'icon' => $iconName,
                    'size' => $profile === 'large' ? 'large' : 'small',
                    'accessibilityElementsHidden' => true,
                ]);
                $preview = $component::make($previewProps, $icon);
                if (!$disabled) {
                    $preview = $preview->onPress(function () use ($index, $pressed): bool {
                        $this->setSampleValue($index, !$pressed);

                        return true;
                    });
                }
            } elseif ($this->tag === 'p-avatar') {
                $avatarProfile = self::stringValue($previewProps['avatarProfile'] ?? 'initials');
                unset($previewProps['avatarProfile']);
                $avatarSize = match ($previewProps['size'] ?? null) {
                    'x-small' => 24.0,
                    'small' => 32.0,
                    'large' => 48.0,
                    'x-large' => 56.0,
                    default => is_numeric($previewProps['size'] ?? null)
                        ? (float) $previewProps['size']
                        : 40.0,
                };
                $initials = match ($avatarProfile) {
                    'team' => 'DS',
                    'large' => 'AK',
                    default => 'PA',
                };
                $previewProps['accessibilityLabel'] = match ($avatarProfile) {
                    'icon' => 'PAM account handle',
                    'team' => 'Design system team',
                    'large' => 'Ana Klein',
                    default => 'PAM account',
                };
                $avatarContent = is_string($previewProps['icon'] ?? null)
                    ? MaterialComponentMap::TAGS['p-icon']::make([
                        'icon' => $previewProps['icon'],
                        'size' => $avatarSize <= 32.0 ? 'x-small' : 'small',
                        'accessibilityElementsHidden' => true,
                    ])
                    : Text::make($initials)->style(new Style(
                        fontSize: max(9.0, $avatarSize * 0.36),
                        lineHeight: max(12.0, $avatarSize * 0.48),
                        fontWeight: 600,
                        textAlign: \Pam\Native\TextAlignment::Center,
                    ));
                $preview = $component::make($previewProps, $avatarContent);
            } elseif ($this->tag === 'p-timeline-item') {
                $activated = (bool) ($this->sampleValues[$index] ?? false);
                $preview = $component::make(
                    [...$previewProps, 'active' => !$activated, 'complete' => $activated],
                    Row::make(
                        Text::make($activated ? '✓' : '1')->style(new Style(
                            width: 28.0,
                            height: 28.0,
                            borderRadius: 14.0,
                            backgroundColor: $theme->color(ColorToken::Primary),
                            textColor: $theme->color(ColorToken::PrimaryForeground),
                            lineHeight: 28.0,
                            fontWeight: 700,
                            textAlign: \Pam\Native\TextAlignment::Center,
                        )),
                        Column::make(
                            Text::make($activated ? 'Validation complete' : 'Validate on Android')->style(new Style(
                                fontSize: 16.0,
                                lineHeight: 22.0,
                                fontWeight: 600,
                                textColor: $theme->color(ColorToken::OnSurface),
                            )),
                            Text::make($activated ? 'Interaction recorded successfully.' : 'Tap to record this milestone.')->style(new Style(
                                fontSize: 14.0,
                                lineHeight: 20.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                            )),
                        )->style(new Style(flexGrow: 1.0, gap: 2.0)),
                    )->style(new Style(widthPercent: 100.0, gap: 14.0, alignItems: Align::Start)),
                )->style(new Style(widthPercent: 100.0, minHeight: 72.0, paddingVertical: 10.0))
                    ->onPress(function () use ($index, $activated): bool {
                        $this->setSampleValue($index, !$activated);

                        return true;
                    });
            } elseif ($this->tag === 'p-treeview-item') {
                $expanded = (bool) ($this->sampleValues[$index] ?? true);
                $preview = PTreeview::make(
                    [
                        ...$previewProps,
                        'defaultExpandedPaths' => $expanded ? ['mobile'] : [],
                        'items' => [
                            [
                                'title' => 'Mobile',
                                'value' => 'mobile',
                                'children' => [
                                    ['title' => 'Android', 'value' => 'mobile/android'],
                                    ['title' => 'iOS', 'value' => 'mobile/ios'],
                                ],
                            ],
                        ],
                    ],
                )->style(new Style(widthPercent: 100.0, paddingVertical: 8.0));
            } else {
                $preview = $this->generatesNativeAnatomy()
                    ? $component::make($previewProps)
                    : $component::make(
                        $previewProps,
                        Text::make($this->previewText()),
                    );
            }
            if (
                $this->tag === 'p-alert'
                && ($previewProps['closable'] ?? false) === true
                && $preview instanceof UiComponent
            ) {
                if (($this->sampleValues[$index] ?? true) === false) {
                    $restore = MaterialComponentMap::TAGS['p-btn'];
                    $preview = Row::make(
                        Text::make('Alert dismissed')->style(new Style(
                            textColor: $theme->color(ColorToken::MutedForeground),
                            fontSize: 14.0,
                            lineHeight: 20.0,
                        )),
                        $restore::make(
                            [
                                'variant' => 'text',
                                'size' => 'small',
                                'accessibilityLabel' => 'Restore alert',
                            ],
                            Text::make('Restore'),
                        )->onPress(function () use ($index): bool {
                            $this->setSampleValue($index, true);

                            return true;
                        }),
                    )->style(new Style(
                        widthPercent: 100.0,
                        minHeight: 48.0,
                        gap: 8.0,
                        alignItems: Align::Center,
                        justifyContent: Justify::SpaceBetween,
                    ));
                } else {
                    $preview = $preview->onClose(
                        function (mixed $value = null) use ($index): bool {
                            $this->setSampleValue($index, false);

                            return true;
                        },
                    );
                }
            }
            if ($this->belongsTo([
                'p-autocomplete', 'p-calendar', 'p-color-input', 'p-combobox', 'p-date-input',
                'p-date-picker',
                'p-number-input',
                'p-otp-input', 'p-range-slider', 'p-rating', 'p-select',
                'p-slider', 'p-text-field', 'p-textarea',
            ])) {
                if ($preview instanceof UiComponent) {
                    $preview = $preview->onChange(
                        function (mixed $value) use ($index): bool {
                            $this->setSampleValue($index, $value);

                            return true;
                        },
                    );
                    if ($this->tag === 'p-otp-input') {
                        $preview = $preview
                            ->onFocus(function () use ($index): bool {
                                $this->setSampleFocused($index, true);

                                return true;
                            })
                            ->onBlur(function () use ($index): bool {
                                $this->setSampleFocused($index, false);

                                return true;
                            });
                    }
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
                        $this->setSampleValue(
                            $index,
                            in_array($value, [true, 1, '1'], true),
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
                'p-app-bar-nav-icon', 'p-avatar', 'p-badge', 'p-btn', 'p-checkbox',
                'p-calendar-day', 'p-chip', 'p-fab', 'p-icon', 'p-icon-btn', 'p-progress-circular',
                'p-otp-input', 'p-radio', 'p-rating', 'p-switch',
            ]);
            // The preview wrapper is layout-only. Components live directly on
            // the page canvas; a decorative surface here would create fake
            // card-in-card compositions and misrepresent their real anatomy.
            $previewSurface = View::make($preview)->style(new Style(
                widthPercent: 100.0,
                minHeight: $intrinsicPreview ? 56.0 : null,
                paddingVertical: $intrinsicPreview ? 4.0 : 0.0,
                backgroundColor: 0x00000000,
                alignItems: $intrinsicPreview ? Align::Start : Align::Stretch,
                justifyContent: Justify::Center,
            ));
            $samples[] = Column::make($caption, $previewSurface)
                ->style(new Style(
                    widthPercent: 100.0,
                    gap: 8.0,
                    alignItems: Align::Stretch,
                ));
        }

        if ($this->tag === 'p-icon') {
            $iconRows = [];
            foreach (array_chunk($samples, 2) as $pair) {
                $tiles = array_map(
                    static fn (Column $sample): Column => $sample->style(new Style(
                        widthPercent: 48.0,
                        flexGrow: 1.0,
                        gap: 8.0,
                        alignItems: Align::Start,
                    )),
                    $pair,
                );
                $iconRows[] = Row::make(...$tiles)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 88.0,
                    gap: 16.0,
                    alignItems: Align::Start,
                ));
            }
            $samples = $iconRows;
        }

        // Layout-engine padding does not contribute to the native
        // ScrollView's measured content extent on every Android version.
        // Keep a full interaction target plus 24dp breathing room after the
        // final sample so it can scroll completely above gesture/three-button
        // navigation on physical Android devices.
        $samples[] = Text::make('')->style(new Style(
            width: 1.0,
            height: 72.0,
            minHeight: 72.0,
            fontSize: 1.0,
            lineHeight: 1.0,
        ));

        $icon = MaterialComponentMap::TAGS['p-icon'];
        $menuGlyph = View::make(
            $icon::make([
                'icon' => 'menu',
                'color' => $theme->color(ColorToken::AccentForeground),
            ]),
        )->style(new Style(
            width: 40.0,
            height: 40.0,
            minWidth: 40.0,
            minHeight: 40.0,
            borderRadius: 20.0,
            backgroundColor: $theme->color(ColorToken::Accent),
            alignItems: Align::Center,
            justifyContent: Justify::Center,
        ));
        $menu = Pressable::make($menuGlyph)
            ->onPress(function (): bool {
                $this->drawer->openDrawer();

                return true;
            })
            ->style(new Style(
                width: 48.0,
                height: 48.0,
                minWidth: 48.0,
                minHeight: 48.0,
                borderRadius: 24.0,
                backgroundColor: 0x00000000,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Open component navigation');

        $headingChildren = [
            View::make()->style(new Style(
                width: 4.0,
                height: 48.0,
                borderRadius: 2.0,
                backgroundColor: $theme->color(ColorToken::Secondary),
            )),
            Column::make(
                Text::make($this->title)->style(new Style(
                    textColor: $theme->color(ColorToken::OnSurface),
                    fontSize: 24.0,
                    lineHeight: 30.0,
                    fontWeight: 700,
                )),
                Text::make($this->tag)->style(new Style(
                    alignSelf: Align::Start,
                    height: 24.0,
                    paddingHorizontal: 9.0,
                    borderRadius: 12.0,
                    backgroundColor: $theme->color(ColorToken::Accent),
                    textColor: $theme->color(ColorToken::AccentForeground),
                    fontSize: 12.0,
                    lineHeight: 24.0,
                    fontWeight: 700,
                )),
            )->style(new Style(
                widthPercent: 70.0,
                flexGrow: 1.0,
                flexShrink: 1.0,
                gap: 4.0,
            )),
        ];
        if (
            !isset($this->drawer)
            || $this->drawer->resolvedType() !== DrawerType::Permanent
        ) {
            $headingChildren[] = $menu;
        }
        $heading = Row::make(...$headingChildren)->style(new Style(
            widthPercent: 100.0,
            minHeight: 88.0,
            paddingHorizontal: 16.0,
            paddingVertical: 12.0,
            gap: 12.0,
            alignItems: Align::Center,
            backgroundColor: $theme->color(ColorToken::SurfaceContainerLowest),
            borderBottomWidth: 1.0,
            borderColor: $theme->color(ColorToken::OutlineVariant),
            elevation: 0.0,
        ));

        return SafeAreaView::make(
            KeyboardAvoidingView::make(
                ScrollView::make(
                    Column::make(
                        $heading,
                        Column::make(
                            Text::make('Variations')->style(new Style(
                                textColor: $theme->color(ColorToken::OnSurface),
                                fontSize: 24.0,
                                lineHeight: 32.0,
                                fontWeight: 700,
                            )),
                            Text::make('Real states, real gestures, rendered by the platform.')
                                ->style(new Style(
                                    textColor: $theme->color(ColorToken::MutedForeground),
                                    fontSize: 13.0,
                                    lineHeight: 18.0,
                                )),
                            ...$samples,
                        )->style(new Style(
                            widthPercent: 100.0,
                            padding: 16.0,
                            paddingBottom: 24.0,
                            gap: 20.0,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                    )),
                )->key(
                    'component-route-scroll:'.$this->tag.':'.$this->state->auditRevision,
                )->scrollRequest(
                    (int) $this->state->auditRevision,
                    targetOffset: 0.0,
                )->style(new Style(
                    widthPercent: 100.0,
                    heightPercent: 100.0,
                    flexGrow: 1.0,
                    flexShrink: 1.0,
                    backgroundColor: $theme->color(ColorToken::Background),
                )),
                KeyboardAvoidingBehavior::Resize,
            )->style(new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                flexGrow: 1.0,
                flexShrink: 1.0,
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
     * Switches this route to a deterministic, deep-link-only audit surface.
     * The public catalog remains unchanged; Android automation gets a stable
     * tree without unrelated animated samples preventing accessibility dumps.
     */
    public function useAuditScenario(?string $scenario): void
    {
        // Deep links may target the route that is already mounted. Local
        // component state invalidates that rendered tree immediately, while a
        // plain property mutation would leave the previous scenario on screen.
        if ($this->state->auditScenario !== $scenario) {
            $this->sampleValues = [];
            $this->expandedPanels = [];
            $this->sampleFocused = [];
            $this->sheetSnapIndexes = [];
        }
        $this->state->auditScenario = $scenario;
        $this->state->auditRevision = (int) $this->state->auditRevision + 1;
    }

    private function setSampleValue(int $index, mixed $value): void
    {
        $this->sampleValues[$index] = $value;
        $this->invalidateInteractivePreview();
    }

    private function setExpandedPanel(int $index, string $value): void
    {
        $this->expandedPanels[$index] = $value;
        $this->invalidateInteractivePreview();
    }

    private function setSampleFocused(int $index, bool $focused): void
    {
        $this->sampleFocused[$index] = $focused;
        $this->invalidateInteractivePreview();
    }

    private function setSheetSnapIndex(int $index, int $value): void
    {
        $this->sheetSnapIndexes[$index] = max(0, $value);
        $this->invalidateInteractivePreview();
    }

    private function invalidateInteractivePreview(): void
    {
        $this->state->interactionRevision =
            (int) $this->state->interactionRevision + 1;
    }

    /**
     * @return list<array{label: string, props: array<string, mixed>}>
     */
    private function variations(): array
    {
        $auditScenario = $this->state->auditScenario;
        if (is_string($auditScenario)) {
            return $this->auditVariations($auditScenario);
        }

        if ($this->tag === 'p-chip') {
            return $this->chipVariations();
        }

        if ($this->tag === 'p-chip-group') {
            return $this->chipGroupVariations();
        }

        if ($this->tag === 'p-color-input') {
            return $this->colorInputVariations();
        }

        if ($this->tag === 'p-data-table') {
            return $this->dataTableVariations();
        }

        if ($this->tag === 'p-data-table-virtual') {
            return $this->virtualDataTableVariations();
        }

        if ($this->tag === 'p-icon') {
            return [
                ['label' => 'Actions', 'props' => ['name' => 'AddIcon', 'accessibilityLabel' => 'Add']],
                ['label' => 'Communication', 'props' => ['name' => 'MessageCircleIcon', 'color' => 'info', 'accessibilityLabel' => 'Messages']],
                ['label' => 'Status', 'props' => ['name' => 'CheckCircleIcon', 'color' => 'success', 'accessibilityLabel' => 'Completed']],
                ['label' => 'Small', 'props' => ['name' => 'FavouriteIcon', 'size' => 'small', 'accessibilityLabel' => 'Favorite']],
                ['label' => 'Large', 'props' => ['name' => 'StarIcon', 'size' => 'x-large', 'color' => 'warning', 'accessibilityLabel' => 'Featured']],
            ];
        }

        if ($this->tag === 'p-icon-btn') {
            return [
                ['label' => 'Standard', 'props' => ['iconButtonProfile' => 'standard']],
                ['label' => 'Tonal', 'props' => ['iconButtonProfile' => 'tonal', 'variant' => 'tonal']],
                ['label' => 'Outlined', 'props' => ['iconButtonProfile' => 'outlined', 'variant' => 'outlined']],
                ['label' => 'Small', 'props' => ['iconButtonProfile' => 'small', 'size' => 'small']],
                ['label' => 'Large', 'props' => ['iconButtonProfile' => 'large', 'size' => 'medium']],
                ['label' => 'Disabled', 'props' => ['iconButtonProfile' => 'disabled', 'disabled' => true]],
            ];
        }

        if ($this->tag === 'p-slide-group-item') {
            return [
                ['label' => 'Default', 'props' => ['label' => 'Overview', 'selected' => false]],
                ['label' => 'Selected', 'props' => ['label' => 'Android', 'selected' => true]],
                ['label' => 'Disabled', 'props' => ['label' => 'Archived', 'selected' => false, 'disabled' => true]],
                ['label' => 'Long label', 'props' => ['label' => 'Design system', 'selected' => false]],
            ];
        }

        if ($this->tag === 'p-img') {
            return [
                ['label' => 'Cover 16:9', 'props' => ['cover' => true, 'aspectRatio' => 16 / 9, 'height' => 216]],
                ['label' => 'Contained', 'props' => ['cover' => false, 'aspectRatio' => 16 / 9, 'height' => 216]],
                ['label' => 'Square', 'props' => ['cover' => true, 'aspectRatio' => 1, 'height' => 280, 'rounded' => true]],
                ['label' => 'Loading', 'props' => ['loading' => true, 'aspectRatio' => 16 / 9, 'height' => 216]],
            ];
        }

        if ($this->tag === 'p-carousel-item') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Selected', 'props' => ['selected' => true]],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Compact', 'props' => ['height' => 180]],
                ['label' => 'Immersive', 'props' => ['height' => 320, 'hideDelimiters' => true]],
            ];
        }

        $variations = [
            ['label' => 'Default', 'props' => []],
        ];

        if ($this->tag === 'p-bottom-sheet') {
            return $this->bottomSheetVariations();
        }

        if ($this->tag === 'p-btn') {
            return $this->buttonVariations();
        }

        if ($this->tag === 'p-btn-group') {
            return $this->buttonGroupVariations();
        }

        if ($this->tag === 'p-btn-toggle') {
            return $this->buttonToggleVariations();
        }

        if ($this->tag === 'p-calendar') {
            return $this->calendarVariations();
        }

        if ($this->tag === 'p-calendar-day') {
            return $this->calendarDayVariations();
        }

        if ($this->tag === 'p-card') {
            return $this->cardVariations();
        }

        if ($this->tag === 'p-card-actions') {
            return [
                ['label' => 'Action Pair', 'props' => ['actionProfile' => 'pair']],
                ['label' => 'Single Action', 'props' => ['actionProfile' => 'single']],
                ['label' => 'Disabled Leading', 'props' => ['actionProfile' => 'disabled']],
                ['label' => 'Destructive Choice', 'props' => ['actionProfile' => 'destructive']],
                ['label' => 'Long Labels', 'props' => ['actionProfile' => 'long']],
                ['label' => 'Three Actions', 'props' => ['actionProfile' => 'three']],
            ];
        }

        if ($this->belongsTo([
            'p-alert', 'p-btn', 'p-card', 'p-chip',
            'p-fab', 'p-icon-btn', 'p-sheet',
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

        if ($this->tag === 'p-avatar') {
            $variations = array_merge($variations, [
                ['label' => 'Elevated', 'props' => ['variant' => 'elevated', 'elevation' => 3]],
                ['label' => 'Flat', 'props' => ['variant' => 'flat', 'elevation' => 0]],
                ['label' => 'Tonal', 'props' => ['variant' => 'tonal']],
                ['label' => 'Outlined', 'props' => ['variant' => 'outlined']],
                ['label' => 'Icon', 'props' => ['icon' => 'star']],
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
            $elevations = $this->belongsTo(['p-app-bar', 'p-toolbar'])
                ? [0, 1, 2, 3, 4, 5]
                : [0, 1, 2, 4, 8, 12, 16, 24];
            foreach ($elevations as $elevation) {
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
                    'helper' => 'Maximum 40 characters',
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
            $minimumValue = $this->tag === 'p-range-slider' ? [0, 0] : 0;
            $maximumValue = $this->tag === 'p-range-slider' ? [100, 100] : 100;
            $variations = array_merge($variations, [
                ['label' => 'Minimum', 'props' => ['modelValue' => $minimumValue]],
                ['label' => 'Maximum', 'props' => ['modelValue' => $maximumValue]],
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
            $add($variations, 'Back', [
                'icon' => 'ChevronLeftIcon',
                'accessibilityLabel' => 'Navigate back',
            ]);
            $add($variations, 'Close', [
                'icon' => 'close',
                'accessibilityLabel' => 'Close current screen',
            ]);
            $add($variations, 'Extra Small', ['size' => 'x-small']);
            $add($variations, 'Small', ['size' => 'small']);
            $add($variations, 'Large', ['size' => 'large']);
            $add($variations, 'Extra Large', ['size' => 'x-large']);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Primary', ['color' => 'primary']);
            $add($variations, 'Secondary', ['color' => 'secondary']);
            $add($variations, 'Custom Color', ['color' => 0xFF9A3412]);
        }

        if ($this->tag === 'p-banner') {
            $add($variations, 'One Line', [
                'lines' => 1,
                'bannerProfile' => 'one-line',
            ]);
            $add($variations, 'Three Lines', [
                'lines' => 3,
                'bannerProfile' => 'three-lines',
            ]);
            $add($variations, 'Leading Icon', [
                'bannerProfile' => 'icon',
            ]);
            $add($variations, 'Single Action', [
                'bannerProfile' => 'single-action',
            ]);
            $add($variations, 'Disabled Action', [
                'bannerProfile' => 'disabled-action',
            ]);
        }

        if ($this->tag === 'p-banner-actions') {
            $add($variations, 'Single Action', ['actionProfile' => 'single']);
            $add($variations, 'Disabled Action', ['actionProfile' => 'disabled']);
            $add($variations, 'Long Labels', ['actionProfile' => 'long']);
            $add($variations, 'Independent Pair', ['actionProfile' => 'independent']);
        }

        if ($this->belongsTo([
            'p-alert', 'p-banner',
            'p-empty-state',
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
            'p-card', 'p-sheet',
        ])) {
            $add($variations, 'Bordered', ['border' => true, 'variant' => 'outlined']);
            $add($variations, 'Loading', ['loading' => true]);
            $add($variations, 'Disabled', ['disabled' => true]);
            $add($variations, 'Horizontal', ['direction' => 'horizontal']);
            $add($variations, 'Primary', ['color' => 'primary']);
        }

        if ($this->tag === 'p-carousel') {
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
            'p-dialog', 'p-overlay',
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
            $add($variations, 'Secondary', ['color' => 'secondary']);
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

        if ($this->tag === 'p-slide-group') {
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
            $add($variations, 'Collapsed', ['opened' => [], 'defaultExpandedPaths' => []]);
            $add($variations, 'Selected', ['modelValue' => 'applications/mobile/android']);
            $add($variations, 'Multiple Selection', [
                'multiple' => true,
                'modelValue' => ['applications/mobile/android', 'design-system'],
            ]);
            $add($variations, 'Disabled', ['disabled' => true]);
        }

        return $variations;
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function bottomSheetVariations(): array
    {
        return [
            [
                'label' => 'Default',
                'props' => ['sheetProfile' => 'default', 'snapPoints' => [34]],
            ],
            [
                'label' => 'Two detents',
                'props' => [
                    'sheetProfile' => 'detents',
                    'snapPoints' => [34, 68],
                    'defaultSnapIndex' => 0,
                ],
            ],
            [
                'label' => 'Persistent',
                'props' => ['sheetProfile' => 'persistent', 'snapPoints' => [42]],
            ],
            [
                'label' => 'No scrim',
                'props' => [
                    'sheetProfile' => 'no-scrim',
                    'snapPoints' => [34],
                    'scrim' => false,
                ],
            ],
            [
                'label' => 'No drag indicator',
                'props' => [
                    'sheetProfile' => 'no-indicator',
                    'snapPoints' => [34],
                    'hideDragIndicator' => true,
                ],
            ],
            [
                'label' => 'Dynamic height',
                'props' => [
                    'sheetProfile' => 'dynamic',
                    'enableDynamicSizing' => true,
                ],
            ],
            [
                'label' => 'Keyboard form',
                'props' => ['sheetProfile' => 'keyboard', 'snapPoints' => [58]],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function buttonVariations(): array
    {
        return [
            ['label' => 'Default', 'props' => ['buttonLabel' => 'Continue']],
            [
                'label' => 'Elevated',
                'props' => ['variant' => 'elevated', 'elevation' => 2, 'buttonLabel' => 'Create'],
            ],
            [
                'label' => 'Flat',
                'props' => ['variant' => 'flat', 'buttonLabel' => 'Confirm'],
            ],
            [
                'label' => 'Tonal',
                'props' => ['variant' => 'tonal', 'buttonLabel' => 'Save draft'],
            ],
            [
                'label' => 'Outlined',
                'props' => ['variant' => 'outlined', 'buttonLabel' => 'Add item'],
            ],
            [
                'label' => 'Text',
                'props' => ['variant' => 'text', 'buttonLabel' => 'Learn more'],
            ],
            [
                'label' => 'Plain',
                'props' => ['variant' => 'plain', 'buttonLabel' => 'Skip'],
            ],
            [
                'label' => 'Leading icon',
                'props' => ['buttonProfile' => 'leading', 'buttonLabel' => 'New project'],
            ],
            [
                'label' => 'Trailing icon',
                'props' => ['variant' => 'text', 'buttonProfile' => 'trailing', 'buttonLabel' => 'Next'],
            ],
            [
                'label' => 'Block',
                'props' => ['block' => true, 'buttonLabel' => 'Continue securely'],
            ],
            [
                'label' => 'Extra small',
                'props' => ['size' => 'x-small', 'buttonLabel' => 'Extra small'],
            ],
            [
                'label' => 'Small',
                'props' => ['size' => 'small', 'buttonLabel' => 'Small'],
            ],
            [
                'label' => 'Large',
                'props' => ['size' => 'large', 'buttonLabel' => 'Large action'],
            ],
            [
                'label' => 'Extra large',
                'props' => ['size' => 'x-large', 'buttonLabel' => 'Hero action'],
            ],
            [
                'label' => 'Compact density',
                'props' => ['density' => 'compact', 'buttonLabel' => 'Compact'],
            ],
            [
                'label' => 'Success',
                'props' => ['color' => 'success', 'buttonLabel' => 'Publish'],
            ],
            [
                'label' => 'Error',
                'props' => ['color' => 'error', 'buttonLabel' => 'Delete'],
            ],
            [
                'label' => 'Disabled',
                'props' => ['disabled' => true, 'buttonLabel' => 'Unavailable'],
            ],
            [
                'label' => 'Loading',
                'props' => ['loading' => true, 'buttonLabel' => 'Saving'],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function buttonGroupVariations(): array
    {
        return [
            [
                'label' => 'Standard',
                'props' => [
                    'buttonGroupProfile' => 'default',
                    'modelValue' => 'week',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Connected',
                'props' => [
                    'buttonGroupProfile' => 'connected',
                    'modelValue' => 'board',
                    'connected' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Full width',
                'props' => [
                    'buttonGroupProfile' => 'full-width',
                    'modelValue' => 'week',
                    'block' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Single optional',
                'props' => [
                    'buttonGroupProfile' => 'optional',
                    'modelValue' => 'list',
                ],
            ],
            [
                'label' => 'Mandatory',
                'props' => [
                    'buttonGroupProfile' => 'mandatory',
                    'modelValue' => 'weekly',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Multiple',
                'props' => [
                    'buttonGroupProfile' => 'multiple',
                    'modelValue' => ['bold', 'italic'],
                    'multiple' => true,
                    'connected' => true,
                    'block' => true,
                ],
            ],
            [
                'label' => 'Leading icons',
                'props' => [
                    'buttonGroupProfile' => 'icons',
                    'modelValue' => 'recent',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Compact density',
                'props' => [
                    'buttonGroupProfile' => 'compact',
                    'modelValue' => 'center',
                    'density' => 'compact',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Rounded connected',
                'props' => [
                    'buttonGroupProfile' => 'rounded',
                    'modelValue' => 'team',
                    'connected' => true,
                    'rounded' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Tile connected',
                'props' => [
                    'buttonGroupProfile' => 'tile',
                    'modelValue' => 'two',
                    'connected' => true,
                    'tile' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Disabled item',
                'props' => [
                    'buttonGroupProfile' => 'disabled-item',
                    'modelValue' => 'view',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Disabled group',
                'props' => [
                    'buttonGroupProfile' => 'disabled-group',
                    'modelValue' => 'read',
                    'disabled' => true,
                ],
            ],
            [
                'label' => 'Long labels',
                'props' => [
                    'buttonGroupProfile' => 'long',
                    'modelValue' => 'recent',
                    'block' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'RTL connected',
                'props' => [
                    'buttonGroupProfile' => 'rtl',
                    'modelValue' => 'week',
                    'connected' => true,
                    'block' => true,
                    'mandatory' => true,
                    'rtl' => true,
                ],
            ],
            [
                'label' => 'Success color',
                'props' => [
                    'buttonGroupProfile' => 'success',
                    'modelValue' => 'published',
                    'color' => 'success',
                    'mandatory' => true,
                ],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function buttonToggleVariations(): array
    {
        return [
            [
                'label' => 'Single choice',
                'props' => [
                    'buttonToggleProfile' => 'default',
                    'modelValue' => 'week',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Multiple choice',
                'props' => [
                    'buttonToggleProfile' => 'multiple',
                    'modelValue' => ['walk', 'ride'],
                    'multiple' => true,
                ],
            ],
            [
                'label' => 'Full width',
                'props' => [
                    'buttonToggleProfile' => 'full-width',
                    'modelValue' => 'board',
                    'block' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Single optional',
                'props' => [
                    'buttonToggleProfile' => 'optional',
                    'modelValue' => 'list',
                ],
            ],
            [
                'label' => 'Leading icons',
                'props' => [
                    'buttonToggleProfile' => 'icons',
                    'modelValue' => 'recent',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Compact density',
                'props' => [
                    'buttonToggleProfile' => 'compact',
                    'modelValue' => 'center',
                    'density' => 'compact',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Two options',
                'props' => [
                    'buttonToggleProfile' => 'two',
                    'modelValue' => 'monthly',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Five options',
                'props' => [
                    'buttonToggleProfile' => 'five',
                    'modelValue' => 'wed',
                    'block' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Disabled item',
                'props' => [
                    'buttonToggleProfile' => 'disabled-item',
                    'modelValue' => 'view',
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Disabled group',
                'props' => [
                    'buttonToggleProfile' => 'disabled-group',
                    'modelValue' => 'read',
                    'disabled' => true,
                ],
            ],
            [
                'label' => 'Long labels',
                'props' => [
                    'buttonToggleProfile' => 'long',
                    'modelValue' => 'recent',
                    'block' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'Tile',
                'props' => [
                    'buttonToggleProfile' => 'tile',
                    'modelValue' => 'two',
                    'tile' => true,
                    'mandatory' => true,
                ],
            ],
            [
                'label' => 'RTL',
                'props' => [
                    'buttonToggleProfile' => 'rtl',
                    'modelValue' => 'week',
                    'block' => true,
                    'mandatory' => true,
                    'rtl' => true,
                ],
            ],
            [
                'label' => 'Success color',
                'props' => [
                    'buttonToggleProfile' => 'success',
                    'modelValue' => 'published',
                    'color' => 'success',
                    'mandatory' => true,
                ],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function calendarVariations(): array
    {
        return [
            [
                'label' => 'Single date',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'locale' => 'pt-BR',
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Multiple dates',
                'props' => [
                    'modelValue' => ['2026-07-08', '2026-07-15'],
                    'visibleDate' => '2026-07-01',
                    'multiple' => true,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Date range',
                'props' => [
                    'modelValue' => [
                        'from' => '2026-07-10',
                        'to' => '2026-07-18',
                    ],
                    'visibleDate' => '2026-07-01',
                    'multiple' => 'range',
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Limits and unavailable dates',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'minDate' => '2026-07-05',
                    'maxDate' => '2026-07-25',
                    'disabledDates' => ['2026-07-12', '2026-07-19'],
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Adjacent dates hidden',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'showOutsideDays' => false,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Week numbers',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'showWeek' => true,
                    'firstDayOfWeek' => 1,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Monday first',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'firstDayOfWeek' => 1,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Dynamic four-week month',
                'props' => [
                    'modelValue' => '2026-02-15',
                    'visibleDate' => '2026-02-01',
                    'fixedWeeks' => false,
                ],
            ],
            [
                'label' => 'Disabled',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'disabled' => true,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Read only',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'readOnly' => true,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'RTL',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'rtl' => true,
                    'fixedWeeks' => true,
                ],
            ],
            [
                'label' => 'Success color',
                'props' => [
                    'modelValue' => '2026-07-15',
                    'visibleDate' => '2026-07-01',
                    'color' => 'success',
                    'fixedWeeks' => true,
                ],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function calendarDayVariations(): array
    {
        return [
            ['label' => 'Default', 'props' => ['day' => '14']],
            [
                'label' => 'Selected',
                'props' => ['day' => '15', 'selected' => true],
            ],
            [
                'label' => 'Today',
                'props' => ['day' => '16', 'state' => 'today'],
            ],
            [
                'label' => 'Disabled',
                'props' => [
                    'day' => '17',
                    'state' => 'disabled',
                    'disabled' => true,
                ],
            ],
            [
                'label' => 'Outside month',
                'props' => [
                    'day' => '28',
                    'state' => 'outside-month',
                    'outside' => true,
                ],
            ],
            [
                'label' => 'Range',
                'props' => ['calendarDayProfile' => 'range'],
            ],
            [
                'label' => 'Success color',
                'props' => [
                    'day' => '18',
                    'selected' => true,
                    'color' => 'success',
                ],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function cardVariations(): array
    {
        return [
            [
                'label' => 'Elevated',
                'props' => ['variant' => 'elevated', 'cardProfile' => 'elevated'],
            ],
            [
                'label' => 'Filled',
                'props' => ['variant' => 'filled', 'cardProfile' => 'filled'],
            ],
            [
                'label' => 'Outlined',
                'props' => ['variant' => 'outlined', 'cardProfile' => 'outlined'],
            ],
            [
                'label' => 'Interactive',
                'props' => [
                    'variant' => 'elevated',
                    'cardProfile' => 'interactive',
                    'accessibilityLabel' => 'Open native experience card',
                    'accessibilityHint' => 'Activates the complete card surface',
                ],
            ],
            [
                'label' => 'Horizontal',
                'props' => [
                    'variant' => 'outlined',
                    'cardProfile' => 'horizontal',
                    'horizontal' => true,
                ],
            ],
            [
                'label' => 'Loading',
                'props' => ['variant' => 'filled', 'cardProfile' => 'loading', 'loading' => true],
            ],
            [
                'label' => 'Disabled',
                'props' => [
                    'variant' => 'elevated',
                    'cardProfile' => 'disabled',
                    'disabled' => true,
                    'accessibilityLabel' => 'Archived workspace card',
                ],
            ],
            [
                'label' => 'Tile',
                'props' => ['variant' => 'outlined', 'cardProfile' => 'tile', 'tile' => true],
            ],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function chipVariations(): array
    {
        return [
            ['label' => 'Assist', 'props' => ['chipProfile' => 'assist']],
            ['label' => 'Filter', 'props' => ['chipProfile' => 'filter']],
            ['label' => 'Input', 'props' => ['chipProfile' => 'input']],
            ['label' => 'Outlined', 'props' => ['chipProfile' => 'outlined']],
            ['label' => 'Disabled', 'props' => ['chipProfile' => 'disabled']],
            ['label' => 'Long Label', 'props' => ['chipProfile' => 'long']],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function chipGroupVariations(): array
    {
        return [
            ['label' => 'Single Select', 'props' => ['chipGroupProfile' => 'single']],
            ['label' => 'Multiple Select', 'props' => ['chipGroupProfile' => 'multiple']],
            ['label' => 'Vertical', 'props' => ['chipGroupProfile' => 'vertical']],
            ['label' => 'Disabled', 'props' => ['chipGroupProfile' => 'disabled']],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function colorInputVariations(): array
    {
        return [
            ['label' => 'HEX', 'props' => ['colorProfile' => 'hex']],
            ['label' => 'RGB', 'props' => ['colorProfile' => 'rgb']],
            ['label' => 'HSL', 'props' => ['colorProfile' => 'hsl']],
            ['label' => 'Palette', 'props' => ['colorProfile' => 'palette']],
            ['label' => 'Invalid', 'props' => ['colorProfile' => 'invalid']],
            ['label' => 'Disabled', 'props' => ['colorProfile' => 'disabled']],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function dataTableVariations(): array
    {
        return [
            ['label' => 'Standard', 'props' => ['dataTableProfile' => 'standard']],
            ['label' => 'Compact', 'props' => ['dataTableProfile' => 'compact']],
            ['label' => 'Comfortable', 'props' => ['dataTableProfile' => 'comfortable']],
            ['label' => 'Striped', 'props' => ['dataTableProfile' => 'striped']],
            ['label' => 'Selectable', 'props' => ['dataTableProfile' => 'selectable']],
            ['label' => 'Loading', 'props' => ['dataTableProfile' => 'loading']],
            ['label' => 'Empty', 'props' => ['dataTableProfile' => 'empty']],
            ['label' => 'Mobile', 'props' => ['dataTableProfile' => 'mobile']],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function virtualDataTableVariations(): array
    {
        return [
            ['label' => 'Standard', 'props' => ['virtualTableProfile' => 'standard']],
            ['label' => 'Compact', 'props' => ['virtualTableProfile' => 'compact']],
            ['label' => 'Fixed Header', 'props' => ['virtualTableProfile' => 'fixed']],
            ['label' => 'Striped', 'props' => ['virtualTableProfile' => 'striped']],
            ['label' => 'Selectable', 'props' => ['virtualTableProfile' => 'selectable']],
            ['label' => 'Loading', 'props' => ['virtualTableProfile' => 'loading']],
            ['label' => 'Empty', 'props' => ['virtualTableProfile' => 'empty']],
            ['label' => 'Mobile', 'props' => ['virtualTableProfile' => 'mobile']],
        ];
    }

    /** @return list<array{label: string, props: array<string, mixed>}> */
    private function auditVariations(string $scenario): array
    {
        if ($this->tag === 'p-chip') {
            return $this->chipVariations();
        }

        if ($this->tag === 'p-chip-group') {
            return $this->chipGroupVariations();
        }

        if ($this->tag === 'p-color-input') {
            return $this->colorInputVariations();
        }

        if ($this->tag === 'p-data-table') {
            return $this->dataTableVariations();
        }

        if ($this->tag === 'p-data-table-virtual') {
            return $this->virtualDataTableVariations();
        }

        if ($this->belongsTo(['p-icon', 'p-icon-btn', 'p-img'])) {
            // These compact showcase matrices are deterministic and already
            // exercise the public catalog, so the Android audit uses them too.
            $auditScenario = $this->state->auditScenario;
            $this->state->auditScenario = null;
            try {
                return $this->variations();
            } finally {
                $this->state->auditScenario = $auditScenario;
            }
        }

        if ($this->tag === 'p-list-item') {
            return [
                ['label' => 'One line', 'props' => ['listItemProfile' => 'one']],
                ['label' => 'Two lines', 'props' => ['listItemProfile' => 'two']],
                ['label' => 'Three lines', 'props' => ['listItemProfile' => 'three']],
                ['label' => 'Selected', 'props' => ['listItemProfile' => 'selected']],
                ['label' => 'Disabled', 'props' => ['listItemProfile' => 'disabled']],
            ];
        }

        if ($this->tag === 'p-list') {
            return [['label' => 'Selectable list', 'props' => []]];
        }

        if ($this->tag === 'p-item') {
            return [
                ['label' => 'Default', 'props' => ['label' => 'Design']],
                ['label' => 'Selected', 'props' => ['label' => 'Engineering', 'selected' => true, 'checked' => true]],
                ['label' => 'Disabled', 'props' => ['label' => 'Archived', 'disabled' => true]],
            ];
        }

        if ($this->tag === 'p-item-group') {
            return [
                [
                    'label' => 'Selection states',
                    'props' => [
                        'modelValue' => 'design',
                        'disabledValues' => ['product'],
                    ],
                ],
            ];
        }

        if ($this->tag === 'p-radio') {
            return [
                ['label' => 'Unchecked', 'props' => ['label' => 'Email updates', 'checked' => false]],
                ['label' => 'Checked', 'props' => ['label' => 'Product releases', 'checked' => true]],
                ['label' => 'Disabled', 'props' => ['label' => 'Managed by organization', 'checked' => true, 'disabled' => true]],
                ['label' => 'Error', 'props' => ['label' => 'Required choice', 'checked' => false, 'error' => true, 'color' => 'error']],
            ];
        }

        if ($this->tag === 'p-radio-group') {
            return [
                ['label' => 'Single selection', 'props' => ['modelValue' => 'design']],
                ['label' => 'Disabled', 'props' => ['modelValue' => 'engineering', 'disabled' => true]],
            ];
        }

        if ($this->tag === 'p-switch') {
            return [
                ['label' => 'Off', 'props' => ['label' => 'Analytics', 'checked' => false]],
                ['label' => 'On', 'props' => ['label' => 'Notifications', 'checked' => true]],
                ['label' => 'Disabled', 'props' => ['label' => 'Organization policy', 'checked' => true, 'disabled' => true]],
                ['label' => 'Error', 'props' => ['label' => 'Required setting', 'checked' => true, 'error' => true, 'color' => 'error']],
            ];
        }

        if ($this->tag === 'p-rating') {
            return [
                ['label' => 'Empty', 'props' => ['modelValue' => 0, 'accessibilityLabel' => 'Empty rating']],
                ['label' => 'Three stars', 'props' => ['modelValue' => 3, 'accessibilityLabel' => 'Three star rating']],
                ['label' => 'Half increments', 'props' => ['modelValue' => 3.5, 'step' => 0.5, 'halfIncrements' => true, 'accessibilityLabel' => 'Three and a half star rating']],
                ['label' => 'Disabled', 'props' => ['modelValue' => 4, 'disabled' => true, 'accessibilityLabel' => 'Disabled rating']],
            ];
        }

        if ($this->tag === 'p-progress-circular') {
            return [
                ['label' => '25 percent', 'props' => ['modelValue' => 25, 'accessibilityLabel' => '25 percent complete']],
                ['label' => '64 percent', 'props' => ['modelValue' => 64, 'accessibilityLabel' => '64 percent complete']],
                ['label' => 'Indeterminate', 'props' => ['indeterminate' => true, 'accessibilityLabel' => 'Loading']],
                ['label' => 'Large', 'props' => ['modelValue' => 82, 'size' => 'large', 'accessibilityLabel' => '82 percent complete']],
            ];
        }

        if ($this->tag === 'p-progress-linear') {
            return [
                ['label' => '25 percent', 'props' => ['modelValue' => 25, 'accessibilityLabel' => '25 percent complete']],
                ['label' => '64 percent', 'props' => ['modelValue' => 64, 'accessibilityLabel' => '64 percent complete']],
                ['label' => 'Buffer', 'props' => ['modelValue' => 42, 'bufferValue' => 72, 'accessibilityLabel' => '42 percent loaded, 72 percent buffered']],
                ['label' => 'Indeterminate', 'props' => ['indeterminate' => true, 'accessibilityLabel' => 'Loading']],
            ];
        }

        if ($this->tag === 'p-skeleton-loader') {
            return [
                ['label' => 'Text', 'props' => ['type' => 'text', 'accessibilityLabel' => 'Loading text']],
                ['label' => 'Avatar', 'props' => ['type' => 'avatar', 'accessibilityLabel' => 'Loading avatar']],
                ['label' => 'Button', 'props' => ['type' => 'button', 'accessibilityLabel' => 'Loading action']],
                ['label' => 'Article composition', 'props' => ['skeletonProfile' => 'article', 'accessibilityLabel' => 'Loading article']],
            ];
        }

        if ($this->tag === 'p-sparkline') {
            return [
                ['label' => 'Trend', 'props' => ['values' => '12,18,14,26,22,34,31,42,38,52,48,64', 'type' => 'trend', 'smooth' => false, 'fill' => false, 'accessibilityLabel' => 'Revenue trend']],
                ['label' => 'Smooth fill', 'props' => ['values' => '18,24,20,38,34,48,44,58', 'type' => 'trend', 'smooth' => true, 'fill' => true, 'accessibilityLabel' => 'Smooth adoption trend']],
                ['label' => 'Bars', 'props' => ['values' => '12,28,19,34,26,42,38', 'type' => 'bar', 'accessibilityLabel' => 'Weekly activity bars']],
            ];
        }

        if ($this->tag === 'p-infinite-scroll') {
            return [
                ['label' => 'Load states', 'props' => ['infiniteProfile' => 'gallery', 'mode' => 'manual', 'accessibilityLabel' => 'Product list load states']],
            ];
        }

        if ($this->tag === 'p-slide-group') {
            return [[
                'label' => 'Scrollable destinations',
                'props' => [
                    'modelValue' => 'overview',
                    'mandatory' => true,
                    'accessibilityLabel' => 'Product destinations',
                ],
            ]];
        }

        if ($this->tag === 'p-slide-group-item') {
            return [
                ['label' => 'Default', 'props' => ['label' => 'Overview', 'value' => 'overview']],
                ['label' => 'Selected', 'props' => ['label' => 'Android', 'value' => 'android', 'active' => true, 'selected' => true]],
                ['label' => 'Disabled', 'props' => ['label' => 'Archived', 'value' => 'archived', 'disabled' => true]],
            ];
        }

        if ($this->belongsTo(['p-tabs', 'p-tab'])) {
            return [[
                'label' => $this->tag === 'p-tab' ? 'Tab states' : 'Interactive tabs',
                'props' => [
                    'modelValue' => 'overview',
                    'grow' => true,
                    'accessibilityLabel' => 'Product sections',
                ],
            ]];
        }

        if ($this->belongsTo(['p-stepper', 'p-stepper-vertical'])) {
            return [[
                'label' => 'Interactive checkout',
                'props' => ['modelValue' => 'account', 'editable' => true, 'accessibilityLabel' => 'Checkout progress'],
            ]];
        }

        if ($this->belongsTo(['p-stepper-item', 'p-stepper-vertical-item'])) {
            return [
                ['label' => 'Active', 'props' => ['stepState' => 'active', 'value' => 'delivery']],
                ['label' => 'Complete', 'props' => ['stepState' => 'complete', 'value' => 'delivery']],
                ['label' => 'Disabled', 'props' => ['stepState' => 'disabled', 'value' => 'delivery']],
            ];
        }

        if ($this->belongsTo(['p-stepper-header'])) {
            return [['label' => 'Progress header', 'props' => ['modelValue' => 'account']]];
        }

        if ($this->belongsTo(['p-stepper-window', 'p-stepper-window-item'])) {
            return [['label' => 'Active content', 'props' => ['modelValue' => 'delivery']]];
        }

        if ($this->belongsTo(['p-stepper-actions', 'p-stepper-vertical-actions'])) {
            return [['label' => 'Navigation actions', 'props' => []]];
        }

        if ($this->tag === 'p-checkbox') {
            return [
                [
                    'label' => 'Unchecked',
                    'props' => ['label' => 'Email updates', 'checked' => false],
                ],
                [
                    'label' => 'Checked',
                    'props' => ['label' => 'Product announcements', 'checked' => true],
                ],
                [
                    'label' => 'Indeterminate',
                    'props' => [
                        'label' => 'Select all projects',
                        'checked' => false,
                        'indeterminate' => true,
                    ],
                ],
                [
                    'label' => 'Disabled',
                    'props' => [
                        'label' => 'Managed by your organization',
                        'checked' => true,
                        'disabled' => true,
                    ],
                ],
                [
                    'label' => 'Long Label',
                    'props' => [
                        'label' => 'Notify me when a production release is ready for review',
                        'checked' => false,
                    ],
                ],
                [
                    'label' => 'Error State',
                    'props' => [
                        'label' => 'Accept the publishing policy',
                        'checked' => false,
                        'error' => true,
                        'color' => 'error',
                    ],
                ],
            ];
        }

        if ($this->tag === 'p-carousel') {
            return match ($scenario) {
                'arrows' => [[
                    'label' => 'Arrow Controls',
                    'props' => ['showArrows' => true, 'continuous' => true],
                ]],
                'vertical' => [[
                    'label' => 'Vertical Gesture',
                    'props' => ['vertical' => true, 'direction' => 'vertical'],
                ]],
                'reverse' => [[
                    'label' => 'Reverse Direction',
                    'props' => ['reverse' => true],
                ]],
                'bounded' => [[
                    'label' => 'Bounded',
                    'props' => ['continuous' => false],
                ]],
                'cycle' => [[
                    'label' => 'Automatic Cycle',
                    'props' => ['cycle' => true, 'continuous' => true, 'interval' => 1600],
                ]],
                'without-delimiters' => [[
                    'label' => 'Without Delimiters',
                    'props' => ['hideDelimiters' => true],
                ]],
                default => [[
                    'label' => 'Default',
                    'props' => ['continuous' => true],
                ]],
            };
        }

        if ($this->tag === 'p-divider') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Inset', 'props' => ['inset' => true]],
                ['label' => 'Primary', 'props' => ['color' => 'primary']],
                ['label' => 'Thick', 'props' => ['thickness' => 4]],
                ['label' => 'Secondary', 'props' => ['color' => 'secondary']],
                ['label' => 'Vertical', 'props' => ['vertical' => true, 'orientation' => 2]],
            ];
        }

        if ($this->tag === 'p-dialog') {
            return match ($scenario) {
                'persistent' => [[
                    'label' => 'Persistent',
                    'props' => ['dialogProfile' => 'persistent', 'persistent' => true],
                ]],
                'no-scrim' => [[
                    'label' => 'No Scrim',
                    'props' => ['dialogProfile' => 'no-scrim', 'scrim' => false],
                ]],
                'fullscreen' => [[
                    'label' => 'Fullscreen',
                    'props' => ['dialogProfile' => 'fullscreen', 'fullscreen' => true],
                ]],
                'compact' => [[
                    'label' => 'Width Small',
                    'props' => ['dialogProfile' => 'compact', 'width' => 320],
                ]],
                'large' => [[
                    'label' => 'Width Large',
                    'props' => ['dialogProfile' => 'large', 'width' => 560],
                ]],
                default => [[
                    'label' => 'Default',
                    'props' => ['dialogProfile' => 'default'],
                ]],
            };
        }

        if ($this->tag === 'p-empty-state') {
            return match ($scenario) {
                'search' => [[
                    'label' => 'No Results',
                    'props' => ['emptyStateProfile' => 'search'],
                ]],
                'offline' => [[
                    'label' => 'Offline',
                    'props' => ['emptyStateProfile' => 'offline'],
                ]],
                'permission' => [[
                    'label' => 'Permission',
                    'props' => ['emptyStateProfile' => 'permission'],
                ]],
                'compact' => [[
                    'label' => 'Compact',
                    'props' => ['emptyStateProfile' => 'compact'],
                ]],
                default => [[
                    'label' => 'Default',
                    'props' => ['emptyStateProfile' => 'default'],
                ]],
            };
        }

        if ($this->tag === 'p-alert') {
            return match ($scenario) {
                'success' => [[
                    'label' => 'Success',
                    'props' => ['type' => 'success', 'color' => 'success'],
                ]],
                'warning' => [[
                    'label' => 'Warning',
                    'props' => ['type' => 'warning', 'color' => 'warning'],
                ]],
                'error' => [[
                    'label' => 'Error',
                    'props' => ['type' => 'error', 'color' => 'error'],
                ]],
                'closable' => [[
                    'label' => 'Closable',
                    'props' => ['type' => 'info', 'color' => 'info', 'closable' => true],
                ]],
                'compact' => [[
                    'label' => 'Compact',
                    'props' => ['type' => 'info', 'color' => 'info', 'density' => 'compact'],
                ]],
                default => [[
                    'label' => 'Information',
                    'props' => ['type' => 'info', 'color' => 'info'],
                ]],
            };
        }

        if ($this->tag === 'p-avatar') {
            return match ($scenario) {
                'small' => [[
                    'label' => 'Small',
                    'props' => ['avatarProfile' => 'team', 'size' => 'small'],
                ]],
                'large' => [[
                    'label' => 'Large',
                    'props' => ['avatarProfile' => 'large', 'size' => 'x-large'],
                ]],
                'icon' => [[
                    'label' => 'Icon',
                    'props' => ['avatarProfile' => 'icon', 'icon' => 'AtSignIcon'],
                ]],
                'outlined' => [[
                    'label' => 'Outlined',
                    'props' => ['avatarProfile' => 'team', 'variant' => 'outlined'],
                ]],
                'square' => [[
                    'label' => 'Square',
                    'props' => ['avatarProfile' => 'team', 'tile' => true],
                ]],
                default => [
                    [
                        'label' => 'Initials',
                        'props' => ['avatarProfile' => 'initials'],
                    ],
                    [
                        'label' => 'Team',
                        'props' => ['avatarProfile' => 'team', 'size' => 'small', 'variant' => 'tonal'],
                    ],
                    [
                        'label' => 'Guest',
                        'props' => ['avatarProfile' => 'icon', 'icon' => 'AtSignIcon', 'size' => 'x-large'],
                    ],
                ],
            };
        }

        if ($this->tag === 'p-badge') {
            return match ($scenario) {
                'single' => [[
                    'label' => 'Single',
                    'props' => ['badgeProfile' => 'single'],
                ]],
                'overflow' => [[
                    'label' => 'Overflow',
                    'props' => ['badgeProfile' => 'overflow'],
                ]],
                'dot' => [[
                    'label' => 'Status Dot',
                    'props' => ['badgeProfile' => 'dot', 'color' => 'success'],
                ]],
                'new' => [[
                    'label' => 'Label',
                    'props' => ['badgeProfile' => 'new', 'color' => 'primary'],
                ]],
                default => [
                    [
                        'label' => 'Count',
                        'props' => ['badgeProfile' => 'count'],
                    ],
                    [
                        'label' => 'Status',
                        'props' => ['badgeProfile' => 'dot', 'color' => 'success'],
                    ],
                    [
                        'label' => 'Overflow',
                        'props' => ['badgeProfile' => 'overflow'],
                    ],
                ],
            };
        }

        if ($this->tag === 'p-fab') {
            return match ($scenario) {
                'extended' => [[
                    'label' => 'Extended',
                    'props' => ['fabProfile' => 'extended'],
                ]],
                'small' => [[
                    'label' => 'Small',
                    'props' => ['fabProfile' => 'small'],
                ]],
                'large' => [[
                    'label' => 'Large',
                    'props' => ['fabProfile' => 'large'],
                ]],
                'disabled' => [[
                    'label' => 'Disabled',
                    'props' => ['fabProfile' => 'disabled'],
                ]],
                default => [
                    ['label' => 'Standard', 'props' => ['fabProfile' => 'default']],
                    ['label' => 'Extended', 'props' => ['fabProfile' => 'extended']],
                    ['label' => 'Small', 'props' => ['fabProfile' => 'small']],
                ],
            };
        }

        if ($this->tag === 'p-expansion-panels') {
            return match ($scenario) {
                'compact' => [[
                    'label' => 'Compact',
                    'props' => ['expansionProfile' => 'compact'],
                ]],
                'disabled' => [[
                    'label' => 'Disabled Item',
                    'props' => ['expansionProfile' => 'disabled'],
                ]],
                default => [[
                    'label' => 'Default',
                    'props' => ['expansionProfile' => 'default'],
                ]],
            };
        }

        if ($this->tag === 'p-card-actions') {
            return [
                ['label' => 'Action Pair', 'props' => ['actionProfile' => 'pair']],
                ['label' => 'Single Action', 'props' => ['actionProfile' => 'single']],
                ['label' => 'Disabled Leading', 'props' => ['actionProfile' => 'disabled']],
                ['label' => 'Destructive Choice', 'props' => ['actionProfile' => 'destructive']],
                ['label' => 'Long Labels', 'props' => ['actionProfile' => 'long']],
                ['label' => 'Three Actions', 'props' => ['actionProfile' => 'three']],
            ];
        }

        if ($this->tag === 'p-card') {
            return $this->cardVariations();
        }

        if ($this->tag === 'p-calendar-day') {
            return $this->calendarDayVariations();
        }

        if ($this->tag === 'p-bottom-sheet') {
            return $this->bottomSheetVariations();
        }

        if ($this->tag === 'p-btn') {
            return $this->buttonVariations();
        }

        if ($this->tag === 'p-btn-group') {
            return $this->buttonGroupVariations();
        }

        if ($this->tag === 'p-btn-toggle') {
            return $this->buttonToggleVariations();
        }

        if ($this->tag === 'p-calendar') {
            return $this->calendarVariations();
        }

        if ($this->tag === 'p-banner-actions') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Single Action', 'props' => ['actionProfile' => 'single']],
                ['label' => 'Disabled Action', 'props' => ['actionProfile' => 'disabled']],
                ['label' => 'Long Labels', 'props' => ['actionProfile' => 'long']],
                ['label' => 'Independent Pair', 'props' => ['actionProfile' => 'independent']],
            ];
        }

        if ($this->tag === 'p-app-bar-nav-icon') {
            return [
                ['label' => 'Default', 'props' => []],
                [
                    'label' => 'Back',
                    'props' => [
                        'icon' => 'ChevronLeftIcon',
                        'accessibilityLabel' => 'Navigate back',
                    ],
                ],
                [
                    'label' => 'Close',
                    'props' => [
                        'icon' => 'close',
                        'accessibilityLabel' => 'Close current screen',
                    ],
                ],
                ['label' => 'Extra Small', 'props' => ['size' => 'x-small']],
                ['label' => 'Small', 'props' => ['size' => 'small']],
                ['label' => 'Large', 'props' => ['size' => 'large']],
                ['label' => 'Extra Large', 'props' => ['size' => 'x-large']],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Primary', 'props' => ['color' => 'primary']],
                ['label' => 'Secondary', 'props' => ['color' => 'secondary']],
                ['label' => 'Custom Color', 'props' => ['color' => 0xFF9A3412]],
            ];
        }

        if ($this->tag === 'p-banner') {
            return [
                ['label' => 'Default', 'props' => []],
                [
                    'label' => 'One Line',
                    'props' => ['lines' => 1, 'bannerProfile' => 'one-line'],
                ],
                [
                    'label' => 'Three Lines',
                    'props' => ['lines' => 3, 'bannerProfile' => 'three-lines'],
                ],
                ['label' => 'Leading Icon', 'props' => ['bannerProfile' => 'icon']],
                [
                    'label' => 'Single Action',
                    'props' => ['bannerProfile' => 'single-action'],
                ],
                [
                    'label' => 'Disabled Action',
                    'props' => ['bannerProfile' => 'disabled-action'],
                ],
                [
                    'label' => 'Success',
                    'props' => ['type' => 'success', 'color' => 'success'],
                ],
                [
                    'label' => 'Information',
                    'props' => ['type' => 'info', 'color' => 'info'],
                ],
                [
                    'label' => 'Warning',
                    'props' => ['type' => 'warning', 'color' => 'warning'],
                ],
                [
                    'label' => 'Error',
                    'props' => ['type' => 'error', 'color' => 'error'],
                ],
                ['label' => 'Compact', 'props' => ['density' => 'compact']],
                [
                    'label' => 'Dismissible',
                    'props' => ['bannerProfile' => 'dismissible'],
                ],
            ];
        }

        if ($this->tag === 'p-app-bar') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Primary', 'props' => ['color' => 'primary']],
                ['label' => 'Prominent', 'props' => ['prominent' => true]],
                ['label' => 'Compact', 'props' => ['density' => 'compact']],
                ['label' => 'Flat', 'props' => ['flat' => true, 'elevation' => 0]],
                ['label' => 'Elevation 0', 'props' => ['elevation' => 0]],
                ['label' => 'Elevation 1', 'props' => ['elevation' => 1]],
                ['label' => 'Elevation 2', 'props' => ['elevation' => 2]],
                ['label' => 'Elevation 3', 'props' => ['elevation' => 3]],
                ['label' => 'Elevation 4', 'props' => ['elevation' => 4]],
                ['label' => 'Elevation 5', 'props' => ['elevation' => 5]],
            ];
        }

        if ($this->tag === 'p-textarea') {
            return [
                ['label' => 'Default', 'props' => []],
                [
                    'label' => 'Isolated instance',
                    'props' => ['modelValue' => 'Independent value'],
                ],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Read Only', 'props' => ['readonly' => true]],
                [
                    'label' => 'Error',
                    'props' => [
                        'error' => true,
                        'helper' => 'This value needs attention',
                    ],
                ],
                [
                    'label' => 'Clearable',
                    'props' => ['clearable' => true, 'modelValue' => 'Clear me'],
                ],
                [
                    'label' => 'Prefix and suffix',
                    'props' => [
                        'prefix' => '@',
                        'suffix' => 'note',
                        'modelValue' => 'Message',
                    ],
                ],
                [
                    'label' => 'Counter',
                    'props' => [
                        'counter' => true,
                        'maxLength' => 20,
                        'helper' => 'Maximum 20 characters',
                        'modelValue' => "First\nline",
                    ],
                ],
                [
                    'label' => 'Auto grow',
                    'props' => [
                        'autoGrow' => true,
                        'rows' => 2,
                        'modelValue' => 'Growing note',
                    ],
                ],
                [
                    'label' => 'Four rows',
                    'props' => ['rows' => 4, 'modelValue' => 'Fixed four-row area'],
                ],
                [
                    'label' => 'Fixed rows',
                    'props' => [
                        'noResize' => true,
                        'rows' => 3,
                        'modelValue' => 'Scrollable fixed area',
                    ],
                ],
            ];
        }

        if ($this->tag === 'p-text-field') {
            return [
                ['label' => 'Default', 'props' => []],
                [
                    'label' => 'Isolated instance',
                    'props' => ['modelValue' => 'Independent value'],
                ],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Read Only', 'props' => ['readonly' => true]],
                [
                    'label' => 'Error',
                    'props' => [
                        'error' => true,
                        'helper' => 'This value needs attention',
                    ],
                ],
                [
                    'label' => 'Clearable',
                    'props' => ['clearable' => true, 'modelValue' => 'Clear me'],
                ],
                [
                    'label' => 'Prefix and suffix',
                    'props' => [
                        'prefix' => '$',
                        'suffix' => 'USD',
                        'modelValue' => '120',
                    ],
                ],
                [
                    'label' => 'Counter',
                    'props' => [
                        'counter' => true,
                        'maxLength' => 12,
                        'modelValue' => 'Native',
                    ],
                ],
            ];
        }

        if ($this->tag === 'p-number-input') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Isolated instance', 'props' => ['modelValue' => 3]],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Read Only', 'props' => ['readonly' => true]],
                [
                    'label' => 'Error',
                    'props' => [
                        'error' => true,
                        'helper' => 'Enter a valid quantity',
                    ],
                ],
                ['label' => 'Minimum', 'props' => ['modelValue' => 0]],
                ['label' => 'Maximum', 'props' => ['modelValue' => 20]],
                [
                    'label' => 'Decimal step',
                    'props' => ['modelValue' => 4.5, 'step' => 0.25, 'precision' => 2],
                ],
                [
                    'label' => 'Inset controls',
                    'props' => ['controlVariant' => 'inset'],
                ],
                [
                    'label' => 'Split controls',
                    'props' => ['controlVariant' => 'split'],
                ],
                [
                    'label' => 'Stacked controls',
                    'props' => ['controlVariant' => 'stacked'],
                ],
                [
                    'label' => 'Reverse controls',
                    'props' => ['reverse' => true],
                ],
                [
                    'label' => 'Hidden controls',
                    'props' => ['controlVariant' => 'hidden'],
                ],
            ];
        }

        if ($this->tag === 'p-otp-input') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Isolated instance', 'props' => ['modelValue' => '123456']],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Read Only', 'props' => ['readonly' => true]],
                ['label' => 'Empty', 'props' => ['modelValue' => '']],
                ['label' => 'Four digits', 'props' => ['length' => 4, 'modelValue' => '4829']],
                ['label' => 'Masked', 'props' => ['masked' => true]],
                ['label' => 'Divider', 'props' => ['divider' => '−']],
                ['label' => 'Merged', 'props' => ['merged' => true]],
                [
                    'label' => 'Error',
                    'props' => [
                        'error' => true,
                        'helper' => 'Enter the complete verification code',
                    ],
                ],
                ['label' => 'Loading', 'props' => ['loading' => true]],
            ];
        }

        if ($this->tag === 'p-color-input') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Isolated instance', 'props' => ['modelValue' => '#E91E63']],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Read Only', 'props' => ['readonly' => true]],
                ['label' => 'Empty', 'props' => ['modelValue' => '']],
                [
                    'label' => 'Error',
                    'props' => ['error' => true, 'helper' => 'Choose a valid color'],
                ],
            ];
        }

        if ($this->tag === 'p-date-input') {
            return [
                [
                    'label' => 'Default',
                    'props' => ['accessibilityLabel' => 'Delivery date'],
                ],
                [
                    'label' => 'Isolated instance',
                    'props' => [
                        'modelValue' => '2026-09-01',
                        'accessibilityLabel' => 'Review date',
                    ],
                ],
                [
                    'label' => 'Disabled',
                    'props' => [
                        'disabled' => true,
                        'accessibilityLabel' => 'Archived date',
                    ],
                ],
                [
                    'label' => 'Read Only',
                    'props' => [
                        'readonly' => true,
                        'accessibilityLabel' => 'Created date',
                    ],
                ],
                [
                    'label' => 'Empty',
                    'props' => [
                        'modelValue' => null,
                        'accessibilityLabel' => 'Optional date',
                    ],
                ],
                [
                    'label' => 'Error',
                    'props' => [
                        'error' => true,
                        'helper' => 'Choose a valid date',
                        'accessibilityLabel' => 'Invalid date',
                    ],
                ],
            ];
        }

        if ($this->tag === 'p-date-picker') {
            return match ($scenario) {
                'range' => [[
                    'label' => 'Date range',
                    'props' => ['multiple' => 'range', 'modelValue' => ['2026-07-12', '2026-07-18']],
                ]],
                'week' => [[
                    'label' => 'Week numbers',
                    'props' => ['showWeek' => true, 'firstDayOfWeek' => 1],
                ]],
                'bounded' => [[
                    'label' => 'Bounded dates',
                    'props' => ['minimumDate' => '2026-07-10', 'maximumDate' => '2026-07-24'],
                ]],
                'readonly' => [[
                    'label' => 'Read Only',
                    'props' => ['readonly' => true],
                ]],
                'disabled' => [[
                    'label' => 'Disabled',
                    'props' => ['disabled' => true],
                ]],
                default => [[
                    'label' => 'Default',
                    'props' => [],
                ]],
            };
        }

        if ($this->tag === 'p-time-picker') {
            return match ($scenario) {
                '24-hour' => [[
                    'label' => '24 Hour',
                    'props' => ['format' => '24hr', 'modelValue' => '14:35'],
                ]],
                'ampm' => [[
                    'label' => 'AM PM',
                    'props' => ['format' => 'ampm', 'modelValue' => '09:15'],
                ]],
                'readonly' => [[
                    'label' => 'Read Only',
                    'props' => ['readonly' => true],
                ]],
                'disabled' => [[
                    'label' => 'Disabled',
                    'props' => ['disabled' => true],
                ]],
                default => [[
                    'label' => 'Default',
                    'props' => ['modelValue' => '14:35'],
                ]],
            };
        }

        if ($this->tag === 'p-range-slider') {
            if ($scenario === 'advanced') {
                return [
                    [
                        'label' => 'Read Only',
                        'props' => [
                            'modelValue' => [20, 80],
                            'readonly' => true,
                        ],
                    ],
                    [
                        'label' => 'Transient labels',
                        'props' => [
                            'modelValue' => [25, 75],
                            'thumbLabel' => true,
                        ],
                    ],
                    [
                        'label' => 'Tick labels',
                        'props' => [
                            'modelValue' => [25, 75],
                            'step' => 25,
                            'ticks' => 'always',
                            'tickLabels' => ['0', '25', '50', '75', '100'],
                        ],
                    ],
                    [
                        'label' => 'Custom bounds',
                        'props' => [
                            'modelValue' => [-30, 40],
                            'min' => -50,
                            'max' => 50,
                            'step' => 10,
                            'ticks' => 'always',
                            'thumbLabel' => 'always',
                        ],
                    ],
                    [
                        'label' => 'Vertical',
                        'props' => [
                            'modelValue' => [20, 80],
                            'orientation' => 2,
                        ],
                    ],
                    [
                        'label' => 'Vertical labelled',
                        'props' => [
                            'modelValue' => [20, 80],
                            'orientation' => 2,
                            'thumbLabel' => 'always',
                        ],
                    ],
                    [
                        'label' => 'Descending input',
                        'props' => [
                            'modelValue' => [83, 17],
                            'step' => 10,
                            'thumbLabel' => 'always',
                        ],
                    ],
                    [
                        'label' => 'Coincident values',
                        'props' => [
                            'modelValue' => [50, 50],
                            'thumbLabel' => 'always',
                        ],
                    ],
                ];
            }

            return [
                ['label' => 'Default', 'props' => []],
                [
                    'label' => 'Isolated instance',
                    'props' => ['modelValue' => [15, 45]],
                ],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Full range', 'props' => ['modelValue' => [0, 100]]],
                [
                    'label' => 'Step 10',
                    'props' => [
                        'modelValue' => [20, 80],
                        'step' => 10,
                        'ticks' => 'always',
                        'thumbLabel' => 'always',
                    ],
                ],
                [
                    'label' => 'Reversed',
                    'props' => ['modelValue' => [10, 40], 'reversed' => true],
                ],
            ];
        }

        if ($this->tag === 'p-slider') {
            return [
                ['label' => 'Default', 'props' => []],
                ['label' => 'Isolated instance', 'props' => ['modelValue' => 24]],
                ['label' => 'Disabled', 'props' => ['disabled' => true]],
                ['label' => 'Minimum', 'props' => ['modelValue' => 0]],
                ['label' => 'Maximum', 'props' => ['modelValue' => 100]],
                [
                    'label' => 'Step 10',
                    'props' => [
                        'modelValue' => 60,
                        'step' => 10,
                        'ticks' => 'always',
                        'thumbLabel' => 'always',
                    ],
                ],
                [
                    'label' => 'Reversed',
                    'props' => ['modelValue' => 25, 'reversed' => true],
                ],
            ];
        }

        if (!in_array($this->tag, [
            'p-autocomplete', 'p-combobox', 'p-select',
        ], true)) {
            return [['label' => 'Default', 'props' => []]];
        }

        if ($scenario === 'loading') {
            return [[
                'label' => 'Loading',
                'props' => ['loading' => true],
            ]];
        }

        $variations = [
            ['label' => 'Default', 'props' => []],
            ['label' => 'Isolated instance', 'props' => ['density' => 'comfortable']],
            ['label' => 'Disabled', 'props' => ['disabled' => true]],
            ['label' => 'Read Only', 'props' => ['readonly' => true]],
            ['label' => 'No Data', 'props' => ['items' => [], 'modelValue' => null]],
        ];
        if (in_array($this->tag, ['p-combobox', 'p-select'], true)) {
            return $variations;
        }

        return [
            ...$variations,
            [
                'label' => 'Multiple',
                'props' => [
                    'multiple' => true,
                    'chips' => true,
                    'modelValue' => ['Design', 'Product'],
                ],
            ],
        ];
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
            'p-calendar', 'p-date-picker' => [
                'locale' => 'pt-BR',
                'modelValue' => '2026-07-15',
                'visibleDate' => '2026-07-01',
            ],
            'p-date-input' => [
                'locale' => 'pt-BR',
                'format' => 'DD/MM/YYYY',
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
            'p-app-bar-nav-icon' => [
                'icon' => 'MenuIcon',
                'accessibilityLabel' => 'Open navigation',
            ],
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
                'maxLength' => 280,
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

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable
            ? (string) $value
            : '';
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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
