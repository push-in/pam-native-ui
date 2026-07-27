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
            if ($this->belongsTo([
                'p-autocomplete', 'p-combobox', 'p-select',
                'p-number-input', 'p-otp-input',
            ])) {
                $previewProps['modelValue'] = $this->sampleValues[$index]
                    ?? $previewProps['modelValue']
                    ?? null;
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
                ): Row {
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
                        ['value' => 'delivery'],
                        $stepLabel('2', 'Delivery', false),
                    )
                        ->style(
                            $this->tag === 'p-stepper'
                                ? $horizontalStepStyle
                                : new Style(),
                        ),
                    $item::make(
                        ['value' => 'confirm'],
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
                $preview = $component::make(
                    $previewProps,
                    $panel::make(
                        ['value' => 'details', 'open' => true],
                        $title::make([], Row::make(
                            Text::make('Product details'),
                            Text::make('^'),
                        )->style(new Style(
                            widthPercent: 100.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make([], Text::make(
                            'Native components share the same tokens on Android and iOS.',
                        )),
                    ),
                    $panel::make(
                        ['value' => 'delivery', 'open' => false],
                        $title::make([], Row::make(
                            Text::make('Delivery'),
                            Text::make('v'),
                        )->style(new Style(
                            widthPercent: 100.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make([], Text::make('Fast, predictable native rendering.')),
                    ),
                    $panel::make(
                        ['value' => 'support', 'open' => false],
                        $title::make([], Row::make(
                            Text::make('Support'),
                            Text::make('v'),
                        )->style(new Style(
                            widthPercent: 100.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::SpaceBetween,
                        ))),
                        $text::make([], Text::make('Accessible states and keyboard navigation.')),
                    ),
                );
            } elseif ($this->belongsTo(['p-carousel', 'p-window'])) {
                $itemTag = $this->tag === 'p-carousel'
                    ? 'p-carousel-item'
                    : 'p-window-item';
                $item = MaterialComponentMap::TAGS[$itemTag];
                $slides = [];
                if ($this->tag === 'p-carousel') {
                    $indicator = MaterialComponentMap::TAGS['p-slide-group-item'];
                    $slides = [
                        $indicator::make(
                            ['value' => 'overview', 'active' => true],
                            Text::make('1'),
                        ),
                        $indicator::make(['value' => 'details'], Text::make('2')),
                        $indicator::make(['value' => 'activity'], Text::make('3')),
                    ];
                }
                $slides[] = $item::make(
                        ['value' => 'overview', 'selected' => true],
                        Column::make(
                            Text::make('Native by design')->style(new Style(
                                fontSize: 22.0,
                                fontWeight: 700,
                                textColor: 0xFFFFFFFF,
                            )),
                            Text::make('Fast transitions with no web view.')
                                ->style(new Style(textColor: 0xFFD9EEFA)),
                        )->style(new Style(
                            minHeight: 180.0,
                            padding: 24.0,
                            gap: 8.0,
                            borderRadius: 12.0,
                            backgroundColor: 0xFF0E6FA5,
                        )),
                );
                $slides[] = $item::make(
                    ['value' => 'details'],
                    Text::make('Composable'),
                );
                $slides[] = $item::make(
                    ['value' => 'activity'],
                    Text::make('Accessible'),
                );
                $preview = $component::make($previewProps, ...$slides)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 220.0,
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
                            $listItem::make([], Text::make('Edit profile')),
                            $listItem::make([], Text::make('Manage notifications')),
                            $listItem::make([], Text::make('Sign out')),
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
                    ['variant' => 'tonal', 'size' => 'small'],
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
            } elseif ($this->belongsTo(['p-snackbar', 'p-snackbar-queue'])) {
                $button = MaterialComponentMap::TAGS['p-btn'];
                unset($previewProps['open']);
                $preview = $component::make(
                    [
                        ...$previewProps,
                        'persistent' => true,
                        'accessibilityLabel' => $this->title.' notification',
                    ],
                    Text::make(
                        $this->tag === 'p-snackbar-queue'
                            ? '3 updates synced and ready.'
                            : 'Your changes were saved.',
                    )->style(new Style(
                        textColor: 0xFFF7FAFF,
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                    )),
                    $button::make(
                        ['variant' => 'text', 'color' => 'primary', 'size' => 'small'],
                        Text::make($this->tag === 'p-snackbar-queue' ? 'View' : 'Undo')
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
                unset($previewProps['open']);
                $preview = $component::make(
                    [
                        ...$previewProps,
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
                    ),
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
            } elseif ($this->belongsTo(['p-tabs', 'p-slide-group', 'p-pagination'])) {
                $triggerTag = $this->tag === 'p-slide-group'
                    ? 'p-slide-group-item'
                    : 'p-tab';
                $trigger = MaterialComponentMap::TAGS[$triggerTag];
                $labels = $this->tag === 'p-pagination'
                    ? ['‹', '1', '2', '3', '4', '5', '›']
                    : ['Overview', 'Details', 'Activity'];
                $triggers = [];
                foreach ($labels as $label) {
                    $value = strtolower($label);
                    $active = $value === (
                        $this->tag === 'p-pagination' ? '3' : 'overview'
                    );
                    $triggerComponent = $trigger::make(
                        [
                            'value' => $value,
                            'active' => $active,
                            'selected' => $active,
                        ],
                        Text::make($label)->style(new Style(
                            textColor: $active ? 0xFFFFFFFF : 0xFF5B6E87,
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            fontWeight: $active ? 600 : 400,
                            textAlign: \Pam\Native\TextAlignment::Center,
                        )),
                    );
                    if ($this->tag === 'p-pagination') {
                        $triggerComponent = $triggerComponent->style(new Style(
                            width: 40.0,
                            minWidth: 40.0,
                            height: 40.0,
                            minHeight: 40.0,
                            borderRadius: 20.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                            backgroundColor: $active
                                ? 0xFF0E6FA5
                                : 0x00000000,
                        ));
                    }
                    $triggers[] = $triggerComponent;
                }
                $preview = $component::make($previewProps, ...$triggers);
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
            } elseif ($this->tag === 'p-breadcrumbs') {
                $item = MaterialComponentMap::TAGS['p-breadcrumbs-item'];
                $preview = $component::make(
                    $previewProps,
                    $item::make(['value' => 'home'], Text::make('Home')),
                    Text::make('/')->style(new Style(
                        width: 16.0,
                        textColor: 0xFF5B6E87,
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                    $item::make(['value' => 'library'], Text::make('Library')),
                    Text::make('/')->style(new Style(
                        width: 16.0,
                        textColor: 0xFF5B6E87,
                        textAlign: \Pam\Native\TextAlignment::Center,
                    )),
                    $item::make(
                        ['value' => 'components', 'active' => true],
                        Text::make('Components'),
                    ),
                );
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
                $toolbarTitle = MaterialComponentMap::TAGS['p-toolbar-title'];
                $button = MaterialComponentMap::TAGS['p-icon-btn'];
                $barChildren = [];
                if ($this->tag === 'p-app-bar') {
                    $nav = MaterialComponentMap::TAGS['p-app-bar-nav-icon'];
                    $barChildren[] = $nav::make(
                        ['accessibilityLabel' => 'Open navigation'],
                        Text::make('<'),
                    );
                }
                $barChildren[] = $toolbarTitle::make(
                    [],
                    Text::make('PAM Workspace'),
                );
                $barChildren[] = $button::make(
                    ['accessibilityLabel' => 'More options'],
                    Text::make('...'),
                );
                $preview = $component::make($previewProps, ...$barChildren);
            } elseif ($this->tag === 'p-list') {
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $listTitle = MaterialComponentMap::TAGS['p-list-item-title'];
                $listSubtitle = MaterialComponentMap::TAGS['p-list-item-subtitle'];
                $preview = $component::make(
                    $previewProps,
                    $listItem::make(
                        ['value' => 'design'],
                        $listTitle::make([], Text::make('Design system')),
                        $listSubtitle::make([], Text::make('Tokens and components')),
                    ),
                    $listItem::make(
                        ['value' => 'native'],
                        $listTitle::make([], Text::make('Native runtime')),
                        $listSubtitle::make([], Text::make('Android and iOS')),
                    ),
                    $listItem::make(
                        ['value' => 'release'],
                        $listTitle::make([], Text::make('Release')),
                        $listSubtitle::make([], Text::make('Validated packages')),
                    ),
                );
            } elseif ($this->tag === 'p-data-iterator') {
                $card = MaterialComponentMap::TAGS['p-card'];
                $iteratorItems = [];
                foreach ([
                    ['Aurora', 'Design tokens'],
                    ['Atlas', 'Native runtime'],
                    ['Canvas', 'Release pipeline'],
                ] as [$name, $description]) {
                    $iteratorItems[] = $card::make(
                        ['variant' => 'outlined'],
                        Text::make($name)->style(new Style(
                            fontSize: 16.0,
                            fontWeight: 600,
                            textColor: 0xFF0B172A,
                        )),
                        Text::make($description)->style(new Style(
                            fontSize: 14.0,
                            textColor: 0xFF5B6E87,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        padding: 16.0,
                        gap: 4.0,
                    ));
                }
                $preview = $component::make($previewProps, ...$iteratorItems);
            } elseif ($this->tag === 'p-infinite-scroll') {
                $listItem = MaterialComponentMap::TAGS['p-list-item'];
                $listTitle = MaterialComponentMap::TAGS['p-list-item-title'];
                $infiniteItems = [];
                foreach (['Design', 'Engineering', 'Product', 'Research'] as $team) {
                    $infiniteItems[] = $listItem::make(
                        ['value' => strtolower($team)],
                        $listTitle::make([], Text::make($team)),
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
                $title = MaterialComponentMap::TAGS['p-list-item-title'];
                $subtitle = MaterialComponentMap::TAGS['p-list-item-subtitle'];
                $preview = $component::make(
                    $previewProps,
                    $title::make([], Text::make('Primary label')),
                    $subtitle::make([], Text::make('Supporting text')),
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
            } else {
                $preview = $this->generatesNativeAnatomy()
                    ? $component::make($previewProps)
                    : $component::make(
                        $previewProps,
                        Text::make($this->previewText()),
                    );
            }
            if ($this->belongsTo([
                'p-autocomplete', 'p-combobox', 'p-select',
                'p-number-input', 'p-otp-input',
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
            $intrinsicPreview = $this->belongsTo([
                'p-avatar', 'p-badge', 'p-btn', 'p-checkbox', 'p-checkbox-btn',
                'p-chip', 'p-fab', 'p-icon', 'p-icon-btn', 'p-progress-circular',
                'p-radio', 'p-rating', 'p-selection-control', 'p-switch',
            ]);
            $samples[] = Column::make($caption, $preview)->style(new Style(
                widthPercent: 100.0,
                paddingVertical: 8.0,
                gap: 8.0,
                alignItems: $intrinsicPreview ? Align::Start : Align::Stretch,
            ));
        }

        $menu = Pressable::make(
            Text::make('Menu')->style(new Style(
                textColor: $theme->color(ColorToken::PrimaryForeground),
                fontSize: 14.0,
                fontWeight: 600,
            )),
        )
            ->onPress(function (): bool {
                $this->drawer->openDrawer();

                return true;
            })
            ->style(new Style(
                minWidth: 72.0,
                minHeight: 40.0,
                paddingHorizontal: 16.0,
                borderRadius: 20.0,
                backgroundColor: $theme->color(ColorToken::Primary),
                alignItems: Align::Center,
            ))
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Open component navigation');

        $heading = Row::make(
            Column::make(
                Text::make($this->title)->style(new Style(
                    textColor: $theme->color(ColorToken::OnSurface),
                    fontSize: 24.0,
                    lineHeight: 32.0,
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
            minHeight: 88.0,
            paddingHorizontal: 20.0,
            paddingVertical: 16.0,
            gap: 16.0,
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
                            fontSize: 20.0,
                            lineHeight: 28.0,
                            fontWeight: 600,
                        )),
                        ...$samples,
                    )->style(new Style(
                        widthPercent: 100.0,
                        padding: 20.0,
                        paddingBottom: 32.0,
                        gap: 12.0,
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
            $variations[] = ['label' => 'Block', 'props' => ['block' => true]];
        }

        if ($this->belongsTo([
            'p-alert', 'p-avatar', 'p-badge', 'p-btn', 'p-card', 'p-chip',
            'p-fab', 'p-icon', 'p-icon-btn', 'p-progress-circular',
            'p-progress-linear', 'p-rating', 'p-sheet', 'p-switch',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Primary', 'props' => ['color' => 'primary']],
                ['label' => 'Secondary', 'props' => ['color' => 'secondary']],
                ['label' => 'Success', 'props' => ['action' => 'success']],
                ['label' => 'Information', 'props' => ['action' => 'info']],
                ['label' => 'Warning', 'props' => ['action' => 'warning']],
                ['label' => 'Error', 'props' => ['action' => 'error']],
            ]);
        }

        if ($this->belongsTo([
            'p-avatar', 'p-badge', 'p-btn', 'p-chip', 'p-fab', 'p-icon',
            'p-icon-btn', 'p-progress-circular', 'p-rating', 'p-switch',
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
            'p-combobox', 'p-data-table', 'p-data-table-server',
            'p-data-table-virtual', 'p-field', 'p-file-input', 'p-input',
            'p-list', 'p-list-item', 'p-number-input', 'p-radio', 'p-select',
            'p-selection-control', 'p-slider', 'p-switch', 'p-tab', 'p-tabs',
            'p-text-field', 'p-textarea',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Comfortable', 'props' => ['density' => 'comfortable']],
                ['label' => 'Compact', 'props' => ['density' => 'compact']],
            ]);
        }

        if ($this->belongsTo([
            'p-app-bar', 'p-bottom-navigation', 'p-card', 'p-dialog', 'p-menu',
            'p-navigation-drawer', 'p-sheet', 'p-snackbar', 'p-toolbar',
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
            'p-combobox', 'p-date-input', 'p-file-input', 'p-number-input',
            'p-otp-input', 'p-select', 'p-text-field', 'p-textarea',
        ])) {
            $variations[] = ['label' => 'Disabled', 'props' => ['disabled' => true]];
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-btn', 'p-color-input', 'p-combobox',
            'p-date-input', 'p-file-input', 'p-otp-input', 'p-select',
        ])) {
            $variations[] = ['label' => 'Loading', 'props' => ['loading' => true]];
        }

        if ($this->belongsTo([
            'p-autocomplete', 'p-color-input', 'p-combobox', 'p-date-input',
            'p-file-input', 'p-number-input', 'p-otp-input', 'p-select',
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
            'p-field', 'p-file-input', 'p-input', 'p-number-input', 'p-select',
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
            'p-checkbox', 'p-radio', 'p-selection-control', 'p-switch',
        ])) {
            $variations = array_merge($variations, [
                ['label' => 'Off', 'props' => ['checked' => false]],
                ['label' => 'On', 'props' => ['checked' => true]],
                ['label' => 'Disabled', 'props' => ['checked' => true, 'disabled' => true]],
            ]);
        }

        if ($this->belongsTo(['p-checkbox', 'p-checkbox-btn'])) {
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
            'p-autocomplete', 'p-checkbox', 'p-checkbox-btn', 'p-color-input',
            'p-combobox', 'p-date-input', 'p-field', 'p-file-input', 'p-input',
            'p-number-input', 'p-otp-input', 'p-radio', 'p-select',
            'p-selection-control', 'p-slider', 'p-switch', 'p-text-field',
            'p-textarea',
        ])) {
            $defaults['label'] = $this->title;
        }

        if ($this->belongsTo([
            'p-checkbox', 'p-checkbox-btn', 'p-radio', 'p-selection-control',
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
            'p-bottom-navigation', 'p-btn-toggle', 'p-carousel',
            'p-expansion-panels', 'p-pagination', 'p-slide-group', 'p-stepper',
            'p-stepper-vertical', 'p-tab', 'p-tabs', 'p-window',
        ])) {
            $defaults['active'] = true;
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
            'p-data-table', 'p-data-table-server', 'p-data-table-virtual' => [
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
            'p-rating' => ['length' => 5, 'modelValue' => 4],
            'p-icon' => ['name' => 'StarIcon', 'color' => 0xFF0B172A],
            'p-icon-btn' => ['icon' => 'SettingsIcon'],
            'p-app-bar-nav-icon' => ['icon' => 'MenuIcon'],
            'p-pagination' => ['length' => 7, 'value' => '3'],
            'p-tabs' => [
                'items' => ['Overview', 'Details', 'Activity'],
                'value' => 'overview',
            ],
            'p-slide-group' => [
                'items' => ['Overview', 'Details', 'Activity'],
                'value' => 'overview',
            ],
            'p-carousel', 'p-window' => [
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
            'p-color-input', 'p-color-picker' => ['modelValue' => '#5CBBF6'],
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
            'p-file-input', 'p-file-upload' => [
                'placeholder' => 'Select a file',
                'helper' => 'PDF, PNG or JPG',
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
            'p-breadcrumbs-divider' => '/',
            'p-code' => 'PAM',
            'p-kbd' => 'K',
            default => $this->title,
        };
    }

    private function generatesNativeAnatomy(): bool
    {
        return $this->belongsTo([
            'p-autocomplete',
            'p-calendar',
            'p-color-input',
            'p-color-picker',
            'p-combobox',
            'p-carousel',
            'p-data-table',
            'p-data-table-server',
            'p-data-table-virtual',
            'p-date-input',
            'p-date-picker',
            'p-date-picker-month',
            'p-date-picker-months',
            'p-date-picker-years',
            'p-field',
            'p-file-input',
            'p-file-upload',
            'p-expansion-panels',
            'p-input',
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
            'p-window',
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
