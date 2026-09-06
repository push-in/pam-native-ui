<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Closure;
use InvalidArgumentException;
use Pam\MobileUi\Enum\CalendarDayState;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ComponentMode;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\InputSlotAction;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\MaterialVariant;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Enum\SelectionMode;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Theme\MaterialTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Align;
use Pam\Native\AccessibilityCheckedState;
use Pam\Native\AccessibilityImportance;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\AnimationEasing;
use Pam\Native\AnimationKeyframe;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\ImageCachePolicy;
use Pam\Native\ImageErrorEvent;
use Pam\Native\InputSyncMode;
use Pam\Native\ImageFit;
use Pam\Native\ImageLoadEvent;
use Pam\Native\ImageProgressEvent;
use Pam\Native\Internal\Wire;
use Pam\Native\ImageResizeMethod;
use Pam\Native\InputAutoCapitalize;
use Pam\Native\InputAutofillImportance;
use Pam\Native\InputContentSizeEvent;
use Pam\Native\InputKeyEvent;
use Pam\Native\InputMode;
use Pam\Native\InputSelectionEvent;
use Pam\Native\InputSubmitBehavior;
use Pam\Native\InputTextAlignVertical;
use Pam\Native\Justify;
use Pam\Native\KeyboardAvoidingBehavior;
use Pam\Native\KeyboardType;
use Pam\Native\ModalAnimationType;
use Pam\Native\ModalOrientation;
use Pam\Native\ModalPresentation;
use Pam\Native\NodeKind;
use Pam\Native\PositionType;
use Pam\Native\PointerEvents;
use Pam\Native\PressEvent;
use Pam\Native\PropKey;
use Pam\Native\RefreshIndicatorSize;
use Pam\Native\ReturnKeyType;
use Pam\Native\SafeAreaMode;
use Pam\Native\ScrollKeyboardDismissMode;
use Pam\Native\ScrollOverScrollMode;
use Pam\Native\StatusBarAppearance;
use Pam\Native\Style;
use Pam\Native\TextAlignment;
use Pam\Native\TextBreakStrategy;
use Pam\Native\TextDataDetectorType;
use Pam\Native\TextEllipsizeMode;
use Pam\Native\TextHyphenationFrequency;
use Pam\Native\UI\ActivityIndicator;
use Pam\Native\UI\Animated;
use Pam\Native\UI\Column;
use Pam\Native\UI\CustomView;
use Pam\Native\UI\FlatList;
use Pam\Native\UI\Image;
use Pam\Native\UI\ImageBackground;
use Pam\Native\UI\Input;
use Pam\Native\UI\InputAccessoryView;
use Pam\Native\UI\KeyboardAvoidingView;
use Pam\Native\UI\Modal as NativeModal;
use Pam\Native\UI\Pressable;
use Pam\Native\UI\RefreshControl;
use Pam\Native\UI\Row;
use Pam\Native\UI\SafeAreaView;
use Pam\Native\UI\Scroll;
use Pam\Native\UI\SectionList;
use Pam\Native\UI\StatusBar;
use Pam\Native\UI\Text;
use Pam\Native\UI\View;

final class ComponentRenderer
{
    private function __construct()
    {
    }

    /**
     * Resolve controlled item state before descendants are rendered so layout
     * children receive the same selected/expanded context as the Android host.
     *
     * @param array<string, mixed> $props
     * @param array<string, mixed> $parentProps
     * @return array<string, mixed>
     */
    public static function withParentState(
        string $part,
        array $props,
        array $parentProps,
    ): array {
        $mappedPart = self::legacyMaterialPart($part);
        $props = self::controlledItemState($mappedPart, $props, $parentProps);

        return $mappedPart === $part
            ? $props
            : self::controlledItemState($part, $props, $parentProps);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @param array<int, Closure> $events
     */
    public static function render(
        string $part,
        array $props,
        array $children,
        array $events,
        ?Style $styleOverride,
        ?string $elementKey,
    ): Element {
        if (!isset(ComponentMap::IDS[$part]) && !isset(MaterialComponentMap::IDS[$part])) {
            throw new InvalidArgumentException("Unknown PAM Native UI component {$part}.");
        }

        if (
            in_array($part, [
                
                'PDataTable',
                
                'PDataTableVirtual',
            ], true)
            && !array_key_exists('rowHeight', $props)
        ) {
            $props['rowHeight'] = match ($props['density'] ?? 'default') {
                MaterialDensity::Comfortable->value, 'comfortable' => 44.0,
                MaterialDensity::Compact->value, 'compact' => 36.0,
                default => 52.0,
            };
        }

        if ($part === 'PBottomSheet') {
            return self::materialBottomSheet(
                $props,
                $children,
                $events,
                $styleOverride,
                $elementKey,
            );
        }

        if (isset(MaterialComponentMap::IDS[$part])) {
            $props['__materialComponent'] = $part;
        }
        if (
            $part === 'PAppBarNavIcon'
            && !array_key_exists('accessibilityLabel', $props)
            && !array_key_exists('ariaLabel', $props)
        ) {
            $props['accessibilityLabel'] = 'Open navigation';
        }
        if (in_array($part, ['PDialog', 'PMenu', 'POverlay', 'PSpeedDial'], true)) {
            $props = self::materialOverlayProperties($props);
        }
        if (
            $part === 'PSpeedDial'
            && array_key_exists('direction', $props)
        ) {
            $props['placement'] = match ($props['direction'] ?? null) {
                'top' => Placement::Top->value,
                'start', 'left' => Placement::Left->value,
                'end', 'right' => Placement::Right->value,
                default => Placement::Bottom->value,
            };
        }
        $part = self::legacyMaterialPart($part);
        $props = self::withDefaults($part, $props);
        if (
            in_array(
                $props['__materialComponent'] ?? null,
                ['PBtn', 'PIconBtn', 'PFab'],
                true,
            )
            && !array_key_exists('hitSlop', $props)
        ) {
            $density = match ($props['density'] ?? null) {
                MaterialDensity::Comfortable->value,
                'comfortable' => MaterialDensity::Comfortable,
                MaterialDensity::Compact->value,
                'compact' => MaterialDensity::Compact,
                default => MaterialDensity::Default,
            };
            $visualHeight = match ($props['__materialComponent']) {
                'PFab' => match ($props['size'] ?? null) {
                    'xs', 'x-small', 'sm', 'small',
                    ComponentSize::ExtraSmall->value,
                    ComponentSize::Small->value => MaterialTokens::FAB_SMALL_SIZE,
                    'md', 'medium', ComponentSize::Medium->value =>
                        MaterialTokens::FAB_MEDIUM_SIZE,
                    'lg', 'large', 'xl', 'x-large',
                    ComponentSize::Large->value,
                    ComponentSize::ExtraLarge->value => MaterialTokens::FAB_LARGE_SIZE,
                    default => MaterialTokens::componentSize(
                        $props['size'] ?? null,
                        MaterialTokens::FAB_SIZE,
                    ),
                },
                default => MaterialTokens::buttonHeightForSize(
                    $props['size'] ?? null,
                    $density,
                ),
            };
            // Material controls can be visually smaller than 48 dp, but their
            // effective target must be exactly 48 dp. A generic 8 dp expansion
            // made adjacent banner actions overlap; deriving the inset keeps
            // the accessible target large without ambiguous hit regions.
            $touchInset = max(
                0.0,
                (MaterialTokens::MINIMUM_TOUCH_TARGET - $visualHeight) / 2.0,
            );
            $segmentedItem = self::flag($props, '__pamButtonGroupItem')
                || self::flag($props, '__pamButtonToggleItem');
            $props['hitSlop'] = $segmentedItem
                ? [
                    // Segmented items are already wide enough. Expanding them
                    // horizontally would make adjacent hit regions overlap.
                    'horizontal' => 0.0,
                    'vertical' => $touchInset,
                ]
                : $touchInset;
        }
        if (($props['__materialComponent'] ?? null) === 'PAppBarNavIcon') {
            // The component itself already owns Material's complete 48 dp
            // target. The generic pressable default would add another 8 dp
            // on every edge, producing overlapping navigation hit regions.
            $props['hitSlop'] ??= 0.0;
            if (
                !array_key_exists('android_ripple', $props)
                && !array_key_exists('androidRipple', $props)
                && !array_key_exists('rippleColor', $props)
            ) {
                $props['android_ripple'] = [
                    'color' => self::iconColor($props),
                    'borderless' => true,
                    'radius' => 20.0,
                    'alpha' => MaterialTokens::STATE_OPACITY[4],
                ];
            }
        }
        if (
            $part === 'FileTree'
            && ($props['__materialComponent'] ?? null) === 'PTreeview'
        ) {
            $opened = $props['opened'] ?? $props['expanded'] ?? [];
            if (is_array($opened)) {
                $props['expandedPaths'] ??= implode(
                    "\n",
                    array_map(
                        static fn (mixed $value): string =>
                            is_scalar($value) ? (string) $value : '',
                        $opened,
                    ),
                );
            }
            $selected = $props['modelValue']
                ?? $props['selected']
                ?? $props['value']
                ?? null;
            if (is_array($selected)) {
                $selected = reset($selected);
            }
            if (is_scalar($selected)) {
                $props['selectedPath'] ??= (string) $selected;
            }
        }
        $children = self::fallbackChildren($part, $props, $children, $events);
        if (
            $part === 'Tooltip'
            && ($props['__materialComponent'] ?? null) === 'PTooltip'
            && count($children) === 1
            && self::text($props, 'text') !== ''
        ) {
            $children[] = self::themedText(self::text($props, 'text'));
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PListItem'
            && !array_key_exists('lines', $props)
        ) {
            $props['lines'] = self::materialListItemLineCount($children);
        }
        if (
            in_array(
                $props['__materialComponent'] ?? null,
                ['PIconBtn', 'PAppBarNavIcon'],
                true,
            )
            && (
                is_string($props['icon'] ?? null)
                || is_string($props['name'] ?? null)
            )
        ) {
            $iconProperties = self::iconProperties($part, $props);
            $iconSize = match ($props['size'] ?? null) {
                'x-small', 'xs', ComponentSize::ExtraSmall->value => 18.0,
                'small', 'sm', ComponentSize::Small->value => 20.0,
                'large', 'lg', ComponentSize::Large->value => 28.0,
                'x-large', 'xl', ComponentSize::ExtraLarge->value => 32.0,
                default => 24.0,
            };
            $children = [
                CustomView::make(
                    'pam.mobile_ui.icon',
                    $iconProperties,
                )
                    ->style(new Style(width: $iconSize, height: $iconSize))
                    ->property(
                        PropKey::Value,
                        'pam:icon:'.$iconProperties['icon'],
                    )
                    ->accessible(false)
                    ->accessibilityImportance(
                        AccessibilityImportance::NoHideDescendants,
                    ),
            ];
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PTabs'
            && !self::flag($props, 'hideSlider')
            && self::taggedElement($children, 'pam:tabs-indicator') === null
        ) {
            $children[] = View::make()
                ->style(new Style(
                    positionType: PositionType::Absolute,
                    left: 0.0,
                    bottom: 0.0,
                    width: 1.0,
                    height: 3.0,
                    backgroundColor: ThemeManager::current()->color(
                        ColorToken::Primary,
                    ),
                    borderRadius: 1.5,
                ))
                ->property(PropKey::Value, 'pam:tabs-indicator')
                ->property(PropKey::PointerEvents, PointerEvents::None->value);
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PDataTableVirtual'
            && $part === 'Table'
        ) {
            $fixedHeader = self::flag($props, 'fixedHeader');
            $header = $fixedHeader && $children !== []
                ? array_shift($children)
                : null;
            $defaultVirtualRowHeight = match ($props['density'] ?? null) {
                'compact', 3 => 44.0,
                'comfortable', 2 => 48.0,
                default => 52.0,
            };
            $virtualRows = \Pam\Native\UI\VirtualizedList::make(...$children)
                ->rowHeight(self::number(
                    $props,
                    'rowHeight',
                    self::number($props, 'itemHeight', $defaultVirtualRowHeight),
                ))
                ->prefetch(self::integer($props, 'prefetch', 8))
                ->removeClippedSubviews(
                    self::flag($props, 'removeClippedSubviews', true),
                )
                ->showsIndicator(
                    self::flag($props, 'showsScrollIndicator', true),
                )
                ->style(new Style(
                    widthPercent: 100.0,
                    flexGrow: 1.0,
                    flexShrink: 1.0,
                ));
            $endReached = $events[EventKind::EndReached->value] ?? null;
            if ($endReached !== null) {
                $virtualRows = $virtualRows->onEndReached(
                    $endReached,
                    self::number($props, 'endReachedThreshold', 0.5),
                );
            }
            $children = $header === null
                ? [$virtualRows]
                : [
                    Column::make($header, $virtualRows)->style(new Style(
                        widthPercent: 100.0,
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                    )),
                ];
        }
        if (self::isTransparentProvider($part)) {
            $provider = self::transparentChildren($children);
            if ($elementKey !== null) {
                $provider = $provider->key($elementKey);
            }

            return self::providerSemantics($part, $props, $provider);
        }
        if ($part === 'BottomSheet') {
            $selector = View::make(...self::providerRootChildren($children))
                ->collapsable();
            if ($elementKey !== null) {
                $selector = $selector->key($elementKey);
            }

            return self::providerSemantics($part, $props, $selector);
        }
        if ($part === 'Skeleton' && self::flag($props, 'isLoaded')) {
            $loaded = self::oneChild($children);
            if ($children === []) {
                $loaded = $loaded->visible(false);
            }
            if ($elementKey !== null) {
                $loaded = $loaded->key($elementKey);
            }

            return self::providerSemantics($part, $props, $loaded);
        }
        if ($part === 'Skeleton') {
            $children = [];
        }
        $children = self::fileTreeFolderChildren($part, $props, $children);
        $children = self::anchoredOverlayChildren($part, $props, $children);
        $parentProps = is_array($props['__parentVariants'] ?? null)
            ? $props['__parentVariants']
            : [];
        if (
            $part === 'Checkbox'
            && !array_key_exists('checked', $props)
            && !array_key_exists('isChecked', $props)
            && array_key_exists('defaultIsChecked', $props)
        ) {
            $props['checked'] = self::flag($props, 'defaultIsChecked');
        }
        if (
            $part === 'Switch'
            && !array_key_exists('checked', $props)
            && !array_key_exists('isChecked', $props)
        ) {
            if (array_key_exists('value', $props)) {
                $props['checked'] = self::flag($props, 'value');
            } elseif (array_key_exists('defaultValue', $props)) {
                $props['checked'] = self::flag($props, 'defaultValue');
            }
        }
        $props = self::controlledItemState($part, $props, $parentProps);
        if (($props['__materialComponent'] ?? null) === 'PChip') {
            $props['__pamInteractive'] =
                isset($events[EventKind::Press->value])
                || isset($events[EventKind::LongPress->value])
                || ($parentProps['__pamParentComponent'] ?? null) === 'PChipGroup'
                || self::flag($props, 'clickable')
                || self::flag($props, 'link');
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PItem'
            && !array_key_exists('checked', $props)
        ) {
            $props['checked'] = self::flag(
                $props,
                'selected',
                self::flag($props, 'active'),
            );
        }
        if (
            isset($props['__materialComponent'])
            && array_key_exists('modelValue', $props)
            && !array_key_exists('value', $props)
        ) {
            $props['value'] = $props['modelValue'];
        }
        $runtimeProps = [...$parentProps, ...$props];
        $events = self::componentEvents($part, $runtimeProps, $events);
        $children = self::appBarChildren($runtimeProps, $children);
        $children = self::formControlChildren($part, $runtimeProps, $children);
        $children = self::inputEventChildren($part, $events, $children);
        $children = self::alertChildren($runtimeProps, $events, $children);
        $children = self::chipChildren($runtimeProps, $events, $children);
        $children = self::buttonGroupChildren($runtimeProps, $children);
        $children = self::buttonToggleChildren($runtimeProps, $children);
        if (
            ($props['__materialComponent'] ?? null) === 'PProgressLinear'
            && $children === []
        ) {
            $rawProgress = $props['modelValue'] ?? $props['value'] ?? 0;
            $indeterminate = self::flag($props, 'indeterminate');
            $reduceMotion = self::flag($props, 'reduceMotion');
            $reverse = self::flag($props, 'reverse');
            $progress = $indeterminate
                ? 38.0
                : (is_numeric($rawProgress)
                ? max(0.0, min(100.0, (float) $rawProgress))
                : 0.0);
            $rawBuffer = $props['bufferValue'] ?? $props['buffer-value'] ?? 100;
            $buffer = is_numeric($rawBuffer)
                ? max($progress, min(100.0, (float) $rawBuffer))
                : 100.0;
            $height = isset($props['height']) && is_numeric($props['height'])
                ? max(1.0, (float) $props['height'])
                : 4.0;
            $filledOffset = $indeterminate ? 12.0 : 0.0;
            $children = [
                View::make()
                    ->style(new Style(
                        widthPercent: $buffer,
                        height: $height,
                        positionType: \Pam\Native\PositionType::Absolute,
                        top: 0.0,
                        left: $reverse ? null : 0.0,
                        right: $reverse ? 0.0 : null,
                        backgroundColor: self::materialSemanticColor(
                            [...$props, 'variant' => 'tonal'],
                        ),
                        borderRadius: 999.0,
                        opacity: 0.42,
                    ))
                    ->property(PropKey::Value, 'pam:progress-buffer-track'),
                View::make()
                    ->style(new Style(
                        widthPercent: $progress,
                        height: $height,
                        positionType: \Pam\Native\PositionType::Absolute,
                        top: 0.0,
                        left: $reverse ? null : $filledOffset,
                        right: $reverse ? $filledOffset : null,
                        backgroundColor: self::materialSemanticColor($props),
                        borderRadius: 999.0,
                        animationDurationMs: $reduceMotion
                            ? null
                            : ($indeterminate ? 1100 : 250),
                        animateChanges: !$reduceMotion,
                    ))
                    ->property(PropKey::Value, 'pam:progress-filled-track'),
            ];
        }
        if (
            $part === 'Modal'
            && ($props['__materialComponent'] ?? null) !== 'PDialog'
        ) {
            $children = array_map(
                static fn (Element $child): Element => $child->style(
                    new Style(marginHorizontal: 24.0),
                ),
                $children,
            );
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PChipGroup'
            && (($props['direction'] ?? null) === 'vertical'
                || self::flag($props, 'column'))
        ) {
            $props['__pamChildCount'] = count($children);
        }
        $style = StyleResolver::resolve($part, $props, ThemeManager::current());
        $materialComponent = $props['__materialComponent'] ?? null;
        $materialLoading = self::flag(
            $props,
            'loading',
            self::flag($props, 'isLoading'),
        );
        if (
            $materialLoading
            && in_array($materialComponent, ['PBtn', 'PIconBtn', 'PFab'], true)
        ) {
            $loadingForeground = $style->textColor
                ?? ThemeManager::current()->color(
                    ColorToken::PrimaryForeground,
                );
            $props['progressColor'] = $loadingForeground;
            $children = [
                ActivityIndicator::make()
                    ->color($loadingForeground)
                    ->style(new Style(width: 20.0, height: 20.0)),
            ];
        }
        if (
            $materialLoading
            && in_array($materialComponent, ['PCard', 'PSheet'], true)
        ) {
            $theme = ThemeManager::current();
            $loadingIndicator = View::make()->style(new Style(
                widthPercent: 42.0,
                height: 4.0,
                borderRadius: 999.0,
                backgroundColor: $theme->color(ColorToken::Primary),
            ));
            if (!self::flag($props, 'reduceMotion')) {
                $loadingIndicator = Animated::make(
                    $loadingIndicator->style(new Style(widthPercent: 100.0)),
                    [
                        new AnimationKeyframe(
                            offset: 0.0,
                            translationXPercent: -42.0,
                        ),
                        new AnimationKeyframe(
                            offset: 1.0,
                            translationXPercent: 100.0,
                        ),
                    ],
                    durationMs: 1_250,
                    easing: AnimationEasing::Linear,
                )->iterations(0)->style(new Style(
                    widthPercent: 42.0,
                    height: 4.0,
                ));
            }
            array_unshift(
                $children,
                View::make($loadingIndicator)->style(new Style(
                    widthPercent: 100.0,
                    height: 4.0,
                    borderRadius: 999.0,
                    backgroundColor: $theme->color(ColorToken::Secondary),
                    overflow: \Pam\Native\Overflow::Hidden,
                )),
            );
        }
        if (
            in_array($part, ['Input', 'Textarea'], true)
            && (
                $style->textColor !== null
                || $style->placeholderColor !== null
            )
        ) {
            $inputStyle = new Style(
                textColor: $style->textColor,
                placeholderColor: $style->placeholderColor,
                fontSize: 16.0,
                lineHeight: 24.0,
                fontWeight: 400,
            );
            $children = array_map(
                static fn (Element $child): Element =>
                    $child->kind() === NodeKind::Input
                        ? $child->style($inputStyle)
                        : $child,
                $children,
            );
        }
        if (
            isset($props['__materialComponent'])
            && in_array(
                $props['__materialComponent'],
                ['PBtn', 'PIconBtn', 'PFab'],
                true,
            )
            && $style->textColor !== null
        ) {
            $foreground = $style->textColor;
            $children = array_map(
                static fn (Element $child): Element =>
                    self::applyButtonForeground($child, $foreground)->style(
                        new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            fontWeight: 500,
                            letterSpacing: 0.1,
                        ),
                    ),
                $children,
            );
        }
        if (
            in_array(
                $props['__materialComponent'] ?? null,
                ['PCard', 'PSheet'],
                true,
            )
            && $style->textColor !== null
        ) {
            $surfaceForeground = $style->textColor;
            $children = array_map(
                static fn (Element $child): Element =>
                    $child->kind() === NodeKind::Text
                        ? $child->style(new Style(textColor: $surfaceForeground))
                        : $child,
                $children,
            );
        }
        if (
            in_array(
                $props['__materialComponent'] ?? null,
                ['PAlert', 'PBanner', 'PSnackbar', 'PAppBar', 'PToolbar'],
                true,
            )
            && $style->textColor !== null
        ) {
            $foreground = $style->textColor;
            $children = array_map(
                static fn (Element $child): Element =>
                    self::applyForegroundTree($child, $foreground),
                $children,
            );
        }
        if (
            isset($props['__materialComponent'])
            && in_array(
                $props['__materialComponent'],
                [
                    'PAvatar', 'PBadge', 'PChip', 'PItem',
                    'PSlideGroupItem',
                ],
                true,
            )
        ) {
            $foregroundProps = $props['__materialComponent'] === 'PBadge'
                ? $props + ['color' => 'error']
                : $props;
            $foreground = $style->textColor
                ?? MaterialStyleResolver::semanticForeground(
                    $foregroundProps,
                    ThemeManager::current(),
                );
            if ($foreground !== null) {
                $contentStyle = match ($props['__materialComponent']) {
                    'PBadge' => new Style(
                        fontSize: 11.0,
                        lineHeight: 16.0,
                        fontWeight: 500,
                        letterSpacing: 0.5,
                    ),
                    'PChip', 'PSlideGroupItem' => new Style(
                        fontSize: 14.0,
                        lineHeight: 20.0,
                        fontWeight: 500,
                        letterSpacing: 0.1,
                    ),
                    'PItem' => new Style(
                        fontSize: 16.0,
                        lineHeight: 24.0,
                        fontWeight: 400,
                        letterSpacing: 0.5,
                    ),
                    default => null,
                };
                $children = array_map(
                    static fn (Element $child): Element => $contentStyle === null
                        ? self::applyButtonForeground($child, $foreground)
                        : self::applyButtonForeground($child, $foreground)
                            ->style($contentStyle),
                    $children,
                );
            }
        }
        if (($props['__materialComponent'] ?? null) === 'PTab') {
            $children = array_map(
                static fn (Element $child): Element => $child->style(new Style(
                    fontSize: 12.0,
                    lineHeight: 16.0,
                    fontWeight: 500,
                    letterSpacing: 0.5,
                )),
                $children,
            );
        }
        if (($props['__materialComponent'] ?? null) === 'PCalendarDay') {
            $theme = ThemeManager::current();
            $state = match ($props['state'] ?? CalendarDayState::Default->value) {
                CalendarDayState::Selected->value, 'selected' => CalendarDayState::Selected,
                CalendarDayState::Disabled->value, 'disabled' => CalendarDayState::Disabled,
                CalendarDayState::OutsideMonth->value,
                'outside', 'outside-month' => CalendarDayState::OutsideMonth,
                CalendarDayState::Today->value, 'today' => CalendarDayState::Today,
                CalendarDayState::RangeStart->value,
                'range-start' => CalendarDayState::RangeStart,
                CalendarDayState::RangeMiddle->value,
                'range-middle' => CalendarDayState::RangeMiddle,
                CalendarDayState::RangeEnd->value,
                'range-end' => CalendarDayState::RangeEnd,
                default => CalendarDayState::Default,
            };
            $selected = self::flag(
                $props,
                'selected',
                self::flag($props, 'isSelected'),
            ) || in_array($state, [
                CalendarDayState::Selected,
                CalendarDayState::RangeStart,
                CalendarDayState::RangeEnd,
            ], true);
            $today = $state === CalendarDayState::Today
                || self::flag($props, 'today');
            $rangeStart = $state === CalendarDayState::RangeStart;
            $rangeMiddle = $state === CalendarDayState::RangeMiddle;
            $rangeEnd = $state === CalendarDayState::RangeEnd;
            $foreground = $style->textColor
                ?? $theme->color(ColorToken::OnSurface);
            $indicatorColor = self::materialSemanticColor($props);
            $todayColor = MaterialStyleResolver::blendArgb(
                $indicatorColor,
                $theme->color(ColorToken::Background),
                0.12,
            );
            $indicatorChildren = array_map(
                static fn (Element $child): Element =>
                    self::applyForegroundTree($child, $foreground)->style(
                        new Style(
                            fontSize: 14.0,
                            lineHeight: 20.0,
                            fontWeight: 400,
                            textAlign: TextAlignment::Center,
                        ),
                    ),
                $children,
            );
            $calendarDayChildren = [];
            if ($rangeStart || $rangeMiddle || $rangeEnd) {
                $trackLeft = $rangeStart ? 24.0 : 0.0;
                $trackWidth = $rangeMiddle ? 48.0 : 24.0;
                $calendarDayChildren[] = View::make()->style(new Style(
                    positionType: PositionType::Absolute,
                    left: $trackLeft,
                    top: 4.0,
                    width: $trackWidth,
                    height: 40.0,
                    backgroundColor: MaterialStyleResolver::blendArgb(
                        $indicatorColor,
                        $theme->color(ColorToken::Background),
                        0.12,
                    ),
                ))
                    ->property(PropKey::PointerEvents, PointerEvents::None->value)
                    ->accessible(false)
                    ->accessibilityImportance(
                        AccessibilityImportance::NoHideDescendants,
                    );
            }
            $calendarDayChildren[] = View::make(...$indicatorChildren)->style(new Style(
                width: 40.0,
                height: 40.0,
                minWidth: 40.0,
                minHeight: 40.0,
                borderWidth: $today && !$selected ? 1.0 : 0.0,
                borderColor: $today && !$selected ? $indicatorColor : null,
                borderRadius: 20.0,
                backgroundColor: match (true) {
                    $selected => $indicatorColor,
                    $today => $todayColor,
                    default => 0x00000000,
                },
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ));
            $children = $calendarDayChildren;
        }
        $nativeBackground = $style->backgroundColor;
        if ($styleOverride !== null && $styleOverride->backgroundColor !== null) {
            $nativeBackground = $styleOverride->backgroundColor;
        }
        if (in_array($part, ['Input', 'Textarea'], true)) {
            $overrideRadius = $styleOverride === null
                ? null
                : $styleOverride->borderRadius;
            $overrideWidth = $styleOverride === null
                ? null
                : $styleOverride->borderWidth;
            $runtimeProps['outlineRadius'] = $overrideRadius
                ?? $style->borderRadius
                ?? ($part === 'Textarea' ? 4.0 : 6.0);
            $runtimeProps['outlineWidth'] = $materialComponent === 'POtpInput'
                ? 0.0
                : max(
                    $materialComponent === null ? 1.0 : 2.0,
                    $overrideWidth
                        ?? $style->borderWidth
                        ?? 1.0,
                );
        }
        $styleAppliedToContent = false;
        $rootStyle = $style;
        if (in_array($part, [
            'BottomSheetPortal',
            'Modal',
            'Portal',
            'SelectPortal',
        ], true)) {
            $rootStyle = new Style();
        }
        if (
            $part === 'Menu'
            && isset($children[1])
            && ($children[1]->properties()[PropKey::Value->value] ?? null)
                === 'pam:overlay-content'
        ) {
            $children[1] = $children[1]->style($style);
            if ($styleOverride !== null) {
                $children[1] = $children[1]->style($styleOverride);
            }
            $rootStyle = new Style();
            $styleAppliedToContent = true;
        }
        $materialField = in_array(
            $props['__materialComponent'] ?? null,
            [
                'PTextField', 'PTextarea', 'PNumberInput',
                'POtpInput', 'PColorInput', 'PDateInput',
                'PSelect', 'PAutocomplete', 'PCombobox',
            ],
            true,
        );
        if ($materialField) {
            $theme = ThemeManager::current();
            $fieldLoading = self::flag(
                $props,
                'loading',
                self::flag($props, 'isLoading'),
            );
            $fieldDisabled = self::flag(
                $props,
                'disabled',
                self::flag($props, 'isDisabled'),
            );
            $fieldReadOnly = self::flag(
                $props,
                'readonly',
                self::flag($props, 'readOnly'),
            );
            $fieldChildren = [];
            $label = self::text($props, 'label');
            $required = self::flag($props, 'required');
            $prefix = self::text($props, 'prefix');
            $suffix = self::text($props, 'suffix');
            $materialComponent = $props['__materialComponent'];
            $modelText = self::text(
                $props,
                'modelValue',
                self::text($props, 'value'),
            );
            if ($materialComponent === 'PColorInput') {
                $colorValue = $props['modelValue'] ?? $props['value'] ?? null;
                if (is_int($colorValue)) {
                    $modelText = sprintf('#%06X', $colorValue & 0xFFFFFF);
                }
            }
            $clearable = self::flag($props, 'clearable')
                && $modelText !== ''
                && !$fieldDisabled;
            $inputPaddingLeft = $prefix === '' ? 0.0 : 32.0;
            $inputPaddingRight = match (true) {
                $fieldLoading && ($clearable || $suffix !== '') => 64.0,
                $fieldLoading => 32.0,
                $clearable && $suffix !== '' => 64.0,
                $clearable || $suffix !== '' => 32.0,
                in_array(
                    $props['__materialComponent'],
                    ['PAutocomplete', 'PCombobox'],
                    true,
                ) => 32.0,
                default => 0.0,
            };
            if (
                $label !== ''
                && $materialComponent !== 'POtpInput'
            ) {
                $fieldChildren[] = Text::make(
                    $label.($required ? ' *' : ''),
                )->style(new Style(
                    textColor: $theme->color(ColorToken::MutedForeground),
                    fontSize: 12.0,
                    lineHeight: 16.0,
                    fontWeight: 400,
                    widthPercent: 100.0,
                ));
            }
            $hasNativeInput = false;
            foreach ($children as $child) {
                if (
                    $child->kind() === NodeKind::Input
                    && in_array($materialComponent, ['PNumberInput', 'POtpInput'], true)
                ) {
                    continue;
                }
                $hasNativeInput = $hasNativeInput || $child->kind() === NodeKind::Input;
                if (
                    $child->kind() === NodeKind::Input
                    && $materialComponent === 'PColorInput'
                ) {
                    $child = $child
                        ->property(PropKey::Value, $modelText)
                        ->property(PropKey::InputAutoCorrect, false)
                        ->property(
                            PropKey::InputAutoCapitalize,
                            InputAutoCapitalize::None->value,
                        );
                }
                if ($fieldDisabled) {
                    $child = self::applyInputEnabledTree($child, false);
                }
                $fieldChildren[] = $child->kind() === NodeKind::Input
                    ? $child->style(new Style(
                        widthPercent: 100.0,
                        flexGrow: 1.0,
                        minHeight: $part === 'Textarea' ? 72.0 : 24.0,
                        paddingLeft: $inputPaddingLeft,
                        paddingRight: $inputPaddingRight,
                        textColor: $theme->color(ColorToken::OnSurface),
                        placeholderColor: $theme->color(ColorToken::MutedForeground),
                        backgroundColor: 0x00000000,
                        borderWidth: 0.0,
                        borderRadius: 0.0,
                        fontSize: 16.0,
                        lineHeight: 24.0,
                    ))
                    : $child;
            }
            if (
                !$hasNativeInput
                && in_array($materialComponent, [
                    'PTextField',
                    'PTextarea',
                    'PNumberInput',
                    'POtpInput',
                ], true)
            ) {
                $inputProps = $props;
                if ($materialComponent === 'PNumberInput') {
                    $inputProps['keyboardType'] ??= 'decimal-pad';
                    $precision = is_numeric($props['precision'] ?? null)
                        ? max(0, (int) $props['precision'])
                        : null;
                    if (is_numeric($props['modelValue'] ?? $props['value'] ?? null)) {
                        $inputProps['modelValue'] = self::numberInputValue(
                            self::scalarFloat($props['modelValue'] ?? $props['value']),
                            $precision,
                        );
                    }
                } elseif ($materialComponent === 'POtpInput') {
                    $inputProps['keyboardType'] ??= 'number-pad';
                    $inputProps['maxLength'] ??= max(
                        1,
                        self::integer($props, 'length', 6),
                    );
                    $inputProps['autoComplete'] ??= 'one-time-code';
                    $inputProps['secure'] = self::flag($props, 'masked')
                        || self::flag($props, 'secure')
                        || self::flag($props, 'secureTextEntry')
                        || ($props['type'] ?? null) === 'password';
                }
                $nativeInput = self::input(
                    $materialComponent === 'PTextarea'
                        ? 'TextareaInput'
                        : 'InputField',
                    $inputProps,
                );
                foreach ([
                    EventKind::Change,
                    EventKind::Focus,
                    EventKind::Blur,
                    EventKind::Submit,
                    EventKind::InputEndEditing,
                    EventKind::InputSelectionChange,
                    EventKind::InputContentSizeChange,
                    EventKind::InputKeyPress,
                ] as $inputEvent) {
                    $handler = $events[$inputEvent->value] ?? null;
                    if ($handler !== null) {
                        if (
                            $inputEvent === EventKind::Change
                            && $materialComponent === 'PNumberInput'
                        ) {
                            $minimum = self::number(
                                $props,
                                'min',
                                -PHP_FLOAT_MAX,
                            );
                            $maximum = self::number(
                                $props,
                                'max',
                                PHP_FLOAT_MAX,
                            );
                            $step = max(
                                0.000001,
                                self::number($props, 'step', 1.0),
                            );
                            $origin = is_numeric($props['min'] ?? null)
                                ? $minimum
                                : 0.0;
                            $precision = is_numeric($props['precision'] ?? null)
                                ? max(0, (int) $props['precision'])
                                : null;
                            $handler = static function (
                                string $payload,
                            ) use (
                                $handler,
                                $minimum,
                                $maximum,
                                $step,
                                $origin,
                                $precision,
                            ): void {
                                if (!is_numeric($payload)) {
                                    return;
                                }
                                $value = min(
                                    $maximum,
                                    max($minimum, (float) $payload),
                                );
                                $snapped = $origin
                                    + round(($value - $origin) / $step) * $step;
                                $handler(self::numberInputValue(
                                    min($maximum, max($minimum, $snapped)),
                                    $precision,
                                ));
                            };
                        }
                        $nativeInput = $nativeInput->on($inputEvent, $handler);
                    }
                }
                if ($materialComponent === 'PNumberInput') {
                    $minimum = self::number($props, 'min', -PHP_FLOAT_MAX);
                    $maximum = self::number($props, 'max', PHP_FLOAT_MAX);
                    $step = max(0.000001, self::number($props, 'step', 1.0));
                    $modelValue = $props['modelValue'] ?? $props['value'] ?? null;
                    $current = is_numeric($modelValue)
                        ? (float) $modelValue
                        : max(0.0, $minimum);
                    $precision = is_numeric($props['precision'] ?? null)
                        ? max(0, (int) $props['precision'])
                        : null;
                    $controlVariant = self::text(
                        $props,
                        'controlVariant',
                        'default',
                    );
                    if (!in_array(
                        $controlVariant,
                        ['default', 'inset', 'split', 'stacked', 'hidden'],
                        true,
                    )) {
                        $controlVariant = 'default';
                    }
                    $reverse = self::flag($props, 'reverse');
                    $controlsDisabled = $fieldDisabled || $fieldReadOnly;
                    $change = $events[EventKind::Change->value] ?? null;
                    $control = static function (
                        string $glyph,
                        string $accessibilityLabel,
                        float $next,
                        bool $atLimit,
                        bool $leading,
                    ) use (
                        $change,
                        $controlVariant,
                        $controlsDisabled,
                        $fieldDisabled,
                        $precision,
                        $theme,
                    ): Element {
                        $enabled = !$controlsDisabled && !$atLimit;
                        $button = Pressable::make(
                            Text::make($glyph)->style(new Style(
                                textColor: $enabled
                                    ? $theme->color(ColorToken::OnSurface)
                                    : $theme->color(ColorToken::MutedForeground),
                                fontSize: 20.0,
                                lineHeight: 24.0,
                                textAlign: TextAlignment::Center,
                            )),
                        )
                            ->style(new Style(
                                width: 48.0,
                                height: 48.0,
                                minWidth: 48.0,
                                minHeight: 48.0,
                                borderRadius: $controlVariant === 'inset'
                                    ? 12.0
                                    : 0.0,
                                backgroundColor: $controlVariant === 'inset'
                                    ? $theme->color(ColorToken::Muted)
                                    : 0x00000000,
                                borderLeftWidth: in_array(
                                    $controlVariant,
                                    ['split', 'stacked'],
                                    true,
                                ) && !$leading ? 1.0 : null,
                                borderRightWidth: $controlVariant === 'split'
                                    && $leading ? 1.0 : null,
                                borderColor: $theme->color(ColorToken::OutlineVariant),
                                opacity: $enabled || $fieldDisabled ? 1.0 : 0.38,
                                alignItems: Align::Center,
                                justifyContent: Justify::Center,
                            ))
                            ->enabled($enabled)
                            ->accessibilityLabel($accessibilityLabel)
                            ->accessibilityRole(AccessibilityRole::Button);
                        return $change === null || !$enabled
                            ? $button
                            : $button->on(
                                EventKind::Press,
                                static fn () => $change(
                                    self::numberInputValue($next, $precision),
                                ),
                            );
                    };
                    $decrease = $control(
                        '−',
                        'Decrease value',
                        max($minimum, $current - $step),
                        $current <= $minimum,
                        true,
                    );
                    $increase = $control(
                        '+',
                        'Increase value',
                        min($maximum, $current + $step),
                        $current >= $maximum,
                        false,
                    );
                    $numberInput = $nativeInput->style(new Style(
                        flexGrow: 1.0,
                        minWidth: 64.0,
                        height: $controlVariant === 'stacked' ? 96.0 : 48.0,
                        textColor: $theme->color(ColorToken::OnSurface),
                        placeholderColor: $theme->color(ColorToken::MutedForeground),
                        fontSize: 16.0,
                        lineHeight: 24.0,
                        textAlign: TextAlignment::Center,
                    ));
                    $stackedControls = static function (
                        Element $first,
                        Element $second,
                        bool $leading,
                    ) use ($theme): Element {
                        $first = $first->style(new Style(
                            borderBottomWidth: 1.0,
                            borderColor: $theme->color(ColorToken::OutlineVariant),
                        ));

                        return Column::make($first, $second)->style(new Style(
                            width: 48.0,
                            height: 96.0,
                            borderLeftWidth: $leading ? null : 1.0,
                            borderRightWidth: $leading ? 1.0 : null,
                            borderColor: $theme->color(ColorToken::OutlineVariant),
                        ));
                    };
                    $numberChildren = match ($controlVariant) {
                        'hidden' => [$numberInput],
                        'stacked' => $reverse
                            ? [
                                $stackedControls($decrease, $increase, true),
                                $numberInput,
                            ]
                            : [
                                $numberInput,
                                $stackedControls($increase, $decrease, false),
                            ],
                        default => $reverse
                            ? [$increase, $numberInput, $decrease]
                            : [$decrease, $numberInput, $increase],
                    };
                    $fieldChildren[] = Row::make(...$numberChildren)->style(new Style(
                        widthPercent: 100.0,
                        gap: $controlVariant === 'split' ? 0.0 : 4.0,
                        alignItems: Align::Center,
                    ));
                } elseif ($materialComponent === 'POtpInput') {
                    $length = max(1, self::integer($props, 'length', 6));
                    $otpValue = self::text(
                        $props,
                        'modelValue',
                        self::text($props, 'value'),
                    );
                    $masked = self::flag($props, 'masked')
                        || self::flag($props, 'secure')
                        || self::flag($props, 'secureTextEntry')
                        || ($props['type'] ?? null) === 'password';
                    $focused = self::flag($props, 'focused');
                    $invalidOtp = self::flag($props, 'error')
                        || self::text(
                            $props,
                            'errorMessage',
                            self::text($props, 'messages'),
                        ) !== '';
                    $merged = self::flag($props, 'merged');
                    $rawDivider = $props['divider'] ?? null;
                    $divider = match (true) {
                        is_string($rawDivider) && $rawDivider !== '' => $rawDivider,
                        $rawDivider === true || self::flag($props, 'divided') => '−',
                        default => '',
                    };
                    $valueLength = function_exists('mb_strlen')
                        ? mb_strlen($otpValue)
                        : strlen($otpValue);
                    $characters = [];
                    for ($index = 0; $index < $length; $index++) {
                        $character = function_exists('mb_substr')
                            ? mb_substr($otpValue, $index, 1)
                            : substr($otpValue, $index, 1);
                        $active = $focused
                            && $index === min($valueLength, $length - 1);
                        $displayCharacter = $masked && $character !== ''
                            ? '•'
                            : $character;
                        $borderColor = $theme->color(match (true) {
                            $invalidOtp => ColorToken::Destructive,
                            $active => ColorToken::Primary,
                            default => ColorToken::Border,
                        });
                        $slotContent = $fieldLoading && $index === $length - 1
                            ? ActivityIndicator::make()
                                ->color($theme->color(ColorToken::Primary))
                                ->style(new Style(width: 20.0, height: 20.0))
                            : Text::make($displayCharacter)->style(new Style(
                                textColor: $theme->color(ColorToken::OnSurface),
                                fontSize: $masked ? 24.0 : 20.0,
                                lineHeight: 24.0,
                                fontWeight: 500,
                                textAlign: TextAlignment::Center,
                            ));
                        $characters[] = View::make($slotContent)->style(new Style(
                            width: 40.0,
                            height: 48.0,
                            alignItems: Align::Center,
                            justifyContent: Justify::Center,
                            backgroundColor: $theme->color(ColorToken::Surface),
                            borderColor: $borderColor,
                            borderWidth: $active ? 2.0 : 1.0,
                            borderLeftWidth: $merged && $index > 0 ? 0.0 : null,
                            borderTopLeftRadius: $merged && $index > 0 ? 0.0 : 4.0,
                            borderBottomLeftRadius: $merged && $index > 0 ? 0.0 : 4.0,
                            borderTopRightRadius: $merged && $index < $length - 1
                                ? 0.0
                                : 4.0,
                            borderBottomRightRadius: $merged && $index < $length - 1
                                ? 0.0
                                : 4.0,
                        ));
                        if ($divider !== '' && $index < $length - 1) {
                            $characters[] = Text::make($divider)->style(new Style(
                                width: 12.0,
                                height: 48.0,
                                textColor: $theme->color(ColorToken::MutedForeground),
                                fontSize: 16.0,
                                lineHeight: 48.0,
                                textAlign: TextAlignment::Center,
                            ));
                        }
                    }
                    $fieldChildren[] = Row::make(...$characters)->style(new Style(
                        widthPercent: 100.0,
                        gap: $merged ? 0.0 : ($divider === '' ? 6.0 : 2.0),
                        justifyContent: Justify::Center,
                    ))->accessibilityImportance(
                        AccessibilityImportance::NoHideDescendants,
                    );
                    $nativeInput = $nativeInput
                        ->accessibilityLabel(
                            $label !== '' ? $label : 'One-time password',
                        )
                        ->accessibilityHint(
                            ($fieldLoading ? 'Loading. ' : '')
                                .'Enter a '.$length.' character verification code',
                        )
                        ->accessibilityBusy($fieldLoading)
                        ->accessibilityRole(AccessibilityRole::Input)
                        ->accessible()
                        ->caretHidden()
                        ->underlineColor(0x00000000);
                    $fieldChildren[] = $nativeInput->style(new Style(
                        positionType: PositionType::Absolute,
                        left: 0.0,
                        top: 8.0,
                        widthPercent: 100.0,
                        height: 48.0,
                        padding: 0.0,
                        textColor: 0x00000000,
                        placeholderColor: 0x00000000,
                        backgroundColor: 0x00000000,
                        borderWidth: 0.0,
                        borderRadius: 0.0,
                        opacity: 0.01,
                        zIndex: 1,
                    ));
                } else {
                    $fieldChildren[] = $nativeInput->style(new Style(
                        widthPercent: 100.0,
                        flexGrow: 1.0,
                        minHeight: $materialComponent === 'PTextarea' ? 72.0 : 24.0,
                        paddingLeft: $inputPaddingLeft,
                        paddingRight: $inputPaddingRight,
                        textColor: $theme->color(ColorToken::OnSurface),
                        placeholderColor: $theme->color(ColorToken::MutedForeground),
                        backgroundColor: 0x00000000,
                        borderWidth: 0.0,
                        borderRadius: 0.0,
                        fontSize: 16.0,
                        lineHeight: 24.0,
                    ));
                }
                $hasNativeInput = true;
            }
            if ($prefix !== '') {
                $fieldChildren[] = Text::make($prefix)->style(new Style(
                    positionType: PositionType::Absolute,
                    left: 0.0,
                    // Android's top-aligned EditText and its adjacent TextView
                    // use different font-line metrics. Thirteen dp places the
                    // visible affix glyph on the first editable text baseline;
                    // 24 dp visibly dropped it by roughly 11 dp.
                    top: $materialComponent === 'PTextarea' ? 13.0 : null,
                    bottom: $materialComponent === 'PTextarea' ? null : 4.0,
                    minWidth: 28.0,
                    height: 24.0,
                    textColor: $theme->color(ColorToken::MutedForeground),
                    fontSize: 16.0,
                    lineHeight: 24.0,
                ));
            }
            if ($suffix !== '') {
                $fieldChildren[] = Text::make($suffix)->style(new Style(
                    positionType: PositionType::Absolute,
                    right: $clearable ? 32.0 : 0.0,
                    top: $materialComponent === 'PTextarea' ? 13.0 : null,
                    bottom: $materialComponent === 'PTextarea' ? null : 4.0,
                    minWidth: 28.0,
                    height: 24.0,
                    textColor: $theme->color(ColorToken::MutedForeground),
                    fontSize: 16.0,
                    lineHeight: 24.0,
                    textAlign: TextAlignment::End,
                ));
            }
            if ($clearable) {
                $clear = Pressable::make(
                    Text::make('×')->style(new Style(
                        textColor: $theme->color(ColorToken::MutedForeground),
                        fontSize: 20.0,
                        lineHeight: 24.0,
                        textAlign: TextAlignment::Center,
                    )),
                )
                    ->style(new Style(
                        positionType: PositionType::Absolute,
                        right: 0.0,
                        top: $materialComponent === 'PTextarea' ? 20.0 : null,
                        bottom: $materialComponent === 'PTextarea' ? null : 0.0,
                        width: 32.0,
                        height: 32.0,
                        borderRadius: 16.0,
                        alignItems: Align::Center,
                        justifyContent: Justify::Center,
                    ))
                    ->hitSlop(6.0)
                    ->accessibilityRole(AccessibilityRole::Button)
                    ->accessibilityLabel(
                        self::text(
                            $props,
                            'clearLabel',
                            $label === '' ? 'Clear field' : 'Clear '.$label,
                        ),
                    );
                $change = $events[EventKind::Change->value] ?? null;
                if ($change !== null) {
                    $clear = $clear->on(
                        EventKind::Press,
                        static fn () => $change(''),
                    );
                }
                $fieldChildren[] = $clear;
            }
            if (
                $fieldLoading
                && $materialComponent !== 'POtpInput'
                && !in_array(
                    $materialComponent,
                    ['PSelect', 'PAutocomplete', 'PCombobox'],
                    true,
                )
            ) {
                $fieldChildren[] = ActivityIndicator::make()
                    ->color($theme->color(ColorToken::Primary))
                    ->style(new Style(
                        positionType: PositionType::Absolute,
                        right: 0.0,
                        top: $materialComponent === 'PTextarea' ? 24.0 : null,
                        bottom: $materialComponent === 'PTextarea' ? null : 4.0,
                        width: 24.0,
                        height: 24.0,
                    ));
            }
            if (
                !$hasNativeInput
                && !in_array(
                    $materialComponent,
                    ['PSelect', 'PAutocomplete', 'PCombobox'],
                    true,
                )
            ) {
                $displayValue = $materialComponent === 'PDateInput'
                    ? self::dateTimeDisplayValue($props)
                    : self::text(
                        $props,
                        'value',
                        self::text($props, 'placeholder'),
                    );
                if ($displayValue !== '') {
                    $fieldChildren[] = Text::make($displayValue)->style(new Style(
                        textColor: $theme->color(ColorToken::OnSurface),
                        fontSize: 16.0,
                        lineHeight: 24.0,
                        widthPercent: 100.0,
                    ));
                }
            }
            $selectionField = in_array($materialComponent, [
                'PSelect', 'PAutocomplete', 'PCombobox',
            ], true);
            if ($selectionField) {
                $triggerDescriptor = null;
                $visualChildren = [];
                foreach ($fieldChildren as $fieldChild) {
                    $marker = $fieldChild->properties()[
                        PropKey::Value->value
                    ] ?? null;
                    if (
                        is_string($marker)
                        && (
                            str_starts_with($marker, 'pam:local-modal-trigger:')
                            || str_starts_with($marker, 'pam:readonly-select-trigger:')
                        )
                    ) {
                        $triggerDescriptor = $fieldChild;
                        continue;
                    }
                    $visualChildren[] = $fieldChild;
                }
                $readOnly = self::flag(
                    $props,
                    'readonly',
                    self::flag($props, 'readOnly'),
                );
                $field = Pressable::make(...$visualChildren)
                    ->style($style)
                    ->enabled(!$fieldDisabled && !$readOnly)
                    ->accessible(true)
                    ->accessibilityRole(AccessibilityRole::ComboBox)
                    ->accessibilityLabel($label !== '' ? $label : $modelText)
                    ->accessibilityHint($readOnly
                        ? 'Read only'
                        : ($materialComponent === 'PSelect'
                            ? 'Opens available options'
                            : 'Opens searchable options'))
                    ->accessibilityExpanded(self::flag($props, 'open'));
                if ($triggerDescriptor instanceof Element) {
                    $marker = $triggerDescriptor->properties()[
                        PropKey::Value->value
                    ] ?? null;
                    if (is_string($marker)) {
                        $field = $field->property(PropKey::Value, $marker);
                    }
                    foreach ($triggerDescriptor->events() as $event => $handler) {
                        $field = $field->on(EventKind::from($event), $handler);
                    }
                }
            } else {
                $field = View::make(...$fieldChildren)->style($style);
            }
            if ($styleOverride !== null) {
                $field = $field->style($styleOverride);
            }
            if (self::flag($props, 'reduceMotion')) {
                $field = $field
                    ->property(PropKey::AnimateChanges, false)
                    ->property(PropKey::AnimationDurationMs, 0);
            }
            $children = [$field];
            $errorMessage = self::text(
                $props,
                'errorMessage',
                self::text($props, 'messages'),
            );
            $invalid = self::flag($props, 'error') || $errorMessage !== '';
            $helper = $errorMessage !== '' ? $errorMessage : self::text(
                $props,
                'helper',
                self::text($props, 'hint'),
            );
            if ($invalid && $materialComponent !== 'POtpInput') {
                $fieldVariant = $props['variant'] ?? null;
                $outlinedField = in_array($fieldVariant, [
                    'outlined', 'outline', MaterialVariant::Outlined->value,
                ], true);
                $children[0] = $children[0]->style(new Style(
                    borderColor: $theme->color(ColorToken::Destructive),
                    borderWidth: $outlinedField ? 2.0 : 0.0,
                    borderBottomWidth: $outlinedField ? null : 2.0,
                ));
            }
            $counterEnabled = self::flag($props, 'counter');
            $maximumLength = max(0, self::integer($props, 'maxLength', 0));
            $counterText = $counterEnabled
                ? (function_exists('mb_strlen')
                    ? mb_strlen($modelText)
                    : strlen($modelText)).($maximumLength > 0 ? '/'.$maximumLength : '')
                : '';
            if ($helper !== '' || $counterText !== '') {
                $details = [];
                if ($helper !== '') {
                    $details[] = Text::make($helper)->style(new Style(
                        textColor: $invalid
                            ? $theme->color(ColorToken::Destructive)
                            : $theme->color(ColorToken::MutedForeground),
                        fontSize: 12.0,
                        lineHeight: 16.0,
                        flexGrow: 1.0,
                        flexShrink: 1.0,
                    ));
                } else {
                    $details[] = View::make()->style(new Style(flexGrow: 1.0));
                }
                if ($counterText !== '') {
                    $details[] = Text::make($counterText)->style(new Style(
                        textColor: $theme->color(ColorToken::MutedForeground),
                        fontSize: 12.0,
                        lineHeight: 16.0,
                        textAlign: TextAlignment::End,
                    ));
                }
                $children[] = Row::make(...$details)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 16.0,
                    paddingHorizontal: 16.0,
                    gap: 8.0,
                    alignItems: Align::Center,
                    justifyContent: Justify::SpaceBetween,
                ));
            }
            $rootStyle = new Style(
                widthPercent: 100.0,
                gap: 4.0,
            );
            $nativeBackground = null;
            $styleAppliedToContent = true;
        }
        $element = self::primitive(
            $part,
            $runtimeProps,
            $children,
            $nativeBackground,
        )->style($rootStyle);

        if (
            self::hasSemanticValue($part)
            && isset($props['value'])
            && is_scalar($props['value'])
        ) {
            $element = $element->property(PropKey::Value, $props['value']);
        } elseif ($part === 'CalendarGrid') {
            $element = $element
                ->property(PropKey::Value, 'pam:calendar-grid')
                ->property(PropKey::MinHeight, 240.0);
        } elseif ($part === 'CalendarHeaderPrevButton') {
            $element = $element->property(PropKey::Value, 'pam:calendar-prev');
        } elseif ($part === 'CalendarHeaderNextButton') {
            $element = $element->property(PropKey::Value, 'pam:calendar-next');
        } elseif ($part === 'CalendarHeaderTitle') {
            $element = $element->property(PropKey::Value, 'pam:calendar-title');
        } elseif ($part === 'CalendarHeaderMonthSelect') {
            $element = $element->property(
                PropKey::Value,
                'pam:calendar-month-select',
            );
        } elseif ($part === 'CalendarHeaderYearSelect') {
            $element = $element->property(
                PropKey::Value,
                'pam:calendar-year-select',
            );
        } elseif ($part === 'AccordionTrigger') {
            $element = $element->property(PropKey::Value, 'pam:accordion-trigger');
        } elseif ($part === 'AccordionContent') {
            $element = $element->property(PropKey::Value, 'pam:accordion-content');
        } elseif ($part === 'AccordionIcon') {
            $element = $element->property(PropKey::Value, 'pam:accordion-icon');
        } elseif ($part === 'CheckboxIndicator' || $part === 'RadioIndicator') {
            $element = $element
                ->property(PropKey::Value, 'pam:selection-indicator')
                ->property(PropKey::PointerEvents, PointerEvents::None->value);
        } elseif ($part === 'CheckboxIcon' || $part === 'RadioIcon') {
            $element = $element
                ->property(
                    PropKey::Value,
                    self::flag($props, 'forceMount')
                        ? 'pam:selection-icon-force'
                        : 'pam:selection-icon',
                )
                ->property(PropKey::PointerEvents, PointerEvents::None->value);
        } elseif (in_array($part, [
            'BottomSheetBackdrop',
            'PopoverBackdrop',
            'SelectBackdrop',
        ], true)) {
            $backdropTag = 'pam:overlay-backdrop';
            if (isset($props['pressBehavior']) && is_scalar($props['pressBehavior'])) {
                $backdropTag .= ':'.(string) $props['pressBehavior'];
            }
            $element = $element->property(PropKey::Value, $backdropTag);
        } elseif (in_array($part, [
            'BottomSheetContent',
            'PopoverContent',
            'SelectContent',
            'TooltipContent',
        ], true)) {
            $element = $element
                ->property(PropKey::Value, 'pam:overlay-content')
                ->property(
                    PropKey::PositionType,
                    PositionType::Absolute->value,
                );
            if ($part === 'PopoverContent') {
                $element = $element->property(PropKey::MinHeight, 88.0);
            } elseif ($part === 'TooltipContent') {
                $element = $element->property(PropKey::MinHeight, 44.0);
            }
        } elseif ($part === 'PopoverArrow') {
            $element = $element->property(PropKey::Value, 'pam:overlay-arrow');
        } elseif (in_array($part, [
            'BottomSheetDragIndicator',
            'SelectDragIndicator',
        ], true)) {
            $element = $element->property(PropKey::Value, 'pam:sheet-drag-indicator');
        } elseif (in_array($part, [
            'SelectDragIndicatorWrapper',
        ], true)) {
            $element = $element->property(
                PropKey::Value,
                'pam:sheet-drag-indicator-wrapper',
            );
        } elseif ($part === 'TabsList') {
            $element = $element->property(PropKey::Value, 'pam:tabs-list');
        } elseif ($part === 'TabsContentWrapper') {
            $element = $element->property(PropKey::Value, 'pam:tabs-content-wrapper');
        } elseif ($part === 'TabsContent' && isset($props['value']) && is_scalar($props['value'])) {
            $element = $element->property(
                PropKey::Value,
                (self::flag($props, 'forceMount')
                    ? 'pam:tabs-content-force:'
                    : 'pam:tabs-content:') . (string) $props['value'],
            );
        } elseif ($part === 'TabsIndicator' || $part === 'TabsAnimatedIndicator') {
            $element = $element
                ->property(PropKey::Value, 'pam:tabs-indicator')
                ->property(PropKey::PointerEvents, PointerEvents::None->value)
                ->property(PropKey::PositionType, PositionType::Absolute->value)
                ->property(PropKey::Left, 0.0)
                ->property(PropKey::Top, 0.0);
        } elseif ($part === 'SliderTrack') {
            $element = $element->property(PropKey::Value, 'pam:slider-track');
        } elseif ($part === 'SliderFilledTrack') {
            $element = $element->property(PropKey::Value, 'pam:slider-filled-track');
        } elseif ($part === 'SliderThumb') {
            $element = $element
                ->property(PropKey::Value, 'pam:slider-thumb')
                ->property(PropKey::PositionType, PositionType::Absolute->value)
                ->property(PropKey::Left, 0.0)
                ->property(PropKey::Top, 0.0);
        } elseif ($part === 'ProgressFilledTrack') {
            $element = $element->property(PropKey::Value, 'pam:progress-filled-track');
        } elseif ($part === 'FormControlLabel') {
            $element = $element->property(PropKey::Value, 'pam:form-label');
        } elseif ($part === 'FormControlLabelAstrick') {
            $element = $element->property(PropKey::Value, 'pam:form-required');
        } elseif ($part === 'FormControlHelper') {
            $element = $element->property(PropKey::Value, 'pam:form-helper');
        } elseif ($part === 'FormControlError') {
            $element = $element->property(PropKey::Value, 'pam:form-error');
        } elseif ($part === 'GridItem') {
            $element = $element->property(
                PropKey::Value,
                self::gridItemTag($props),
            );
        }

        if ($styleOverride !== null && !$styleAppliedToContent) {
            $element = $element->style($styleOverride);
        }
        if (self::flag($runtimeProps, 'reduceMotion')) {
            $element = $element
                ->property(PropKey::AnimateChanges, false)
                ->property(PropKey::AnimationDurationMs, 0);
        }
        if (array_key_exists('rtl', $props)) {
            $element = $element->property(
                PropKey::LayoutDirection,
                self::flag($props, 'rtl') ? 2 : 1,
            );
        }
        $element = self::explicitNativeProperties($part, $props, $element);
        if (($props['__materialComponent'] ?? null) === 'PProgressCircular') {
            $rawProgressSize = $props['size'] ?? 'md';
            $componentSize = is_int($rawProgressSize)
                ? ComponentSize::tryFrom($rawProgressSize)
                : null;
            $progressSize = $componentSize !== null
                ? match ($componentSize) {
                    ComponentSize::TwoExtraSmall => 12.0,
                    ComponentSize::ExtraSmall => 16.0,
                    ComponentSize::Small => 24.0,
                    ComponentSize::Large => 48.0,
                    ComponentSize::ExtraLarge => 64.0,
                    default => 32.0,
                }
                : (is_numeric($rawProgressSize)
                    ? max(8.0, (float) $rawProgressSize)
                    : match ($rawProgressSize) {
                    'xs', 'x-small' => 16.0,
                    'sm', 'small' => 24.0,
                    'lg', 'large' => 48.0,
                    'xl', 'x-large' => 64.0,
                    default => 32.0,
                });
            $element = $element
                ->property(PropKey::Width, $progressSize)
                ->property(PropKey::Height, $progressSize);
        }
        $disabled = self::flag(
            $props,
            'disabled',
            self::flag($props, 'isDisabled'),
        ) || self::accessibilityStateBoolean($runtimeProps, 'disabled') === true;
        $readOnly = self::flag(
            $props,
            'readonly',
            self::flag($props, 'readOnly', self::flag($props, 'isReadOnly')),
        ) || (
            array_key_exists('editable', $props)
            && !self::flag($props, 'editable', true)
        );
        $nativeReadOnly = self::isInput($part) || in_array(
            $part,
            [
                'Calendar',
                'Checkbox',
                'CheckboxGroup',
                'FormControl',
                'Input',
                'Radio',
                'RadioGroup',
                'Textarea',
            ],
            true,
        );
        $interactionDisabled = $disabled
            || $materialLoading
            || ($readOnly && !$nativeReadOnly);
        if ($interactionDisabled) {
            $element = $element->enabled(false);
        }
        $shouldHide = (
            self::flag($props, 'hidden')
            || (
                ($runtimeProps['__materialComponent'] ?? null) === 'PBanner'
                && array_key_exists('visible', $runtimeProps)
                && !self::flag($runtimeProps, 'visible', true)
            )
            || (
                $part === 'AccordionContent'
                && array_key_exists('expanded', $parentProps)
                && !self::flag($parentProps, 'expanded')
            )
            || (
                $part === 'TabsContent'
                && !self::flag($props, 'forceMount')
                && (
                    array_key_exists('value', $parentProps)
                    || array_key_exists('defaultValue', $parentProps)
                )
                && array_key_exists('value', $props)
                && !self::sameScalar(
                    $parentProps['value'] ?? $parentProps['defaultValue'],
                    $props['value'],
                )
            )
            || (
                $part === 'FormControlError'
                && !self::flag($runtimeProps, 'invalid', self::flag($runtimeProps, 'isInvalid'))
            )
            || (
                self::isClosed($runtimeProps)
                && (
                    self::hidesEntireRootWhenClosed($part)
                    || (
                        self::isOverlayContent($part)
                        && !in_array($part, [
                            'PopoverBackdrop',
                            'PopoverContent',
                            'TooltipContent',
                        ], true)
                    )
                )
            )
        );
        if ($shouldHide) {
            $element = $element->visible(false);
            if ($part === 'AccordionContent') {
                $element = $element
                    ->property(PropKey::Height, 0.0)
                    ->property(PropKey::MinHeight, 0.0);
            }
        }
        $label = $props['accessibilityLabel']
            ?? $props['ariaLabel']
            ?? (
                self::isImage($part) || $part === 'ImageBackground'
                    ? ($props['alt'] ?? null)
                    : null
            );
        if (is_string($label) && $label !== '') {
            $element = $element->accessibilityLabel($label);
            if (
                (self::isImage($part) || $part === 'ImageBackground')
                && isset($props['alt'])
            ) {
                $element = $element->accessible();
            }
        }
        $hint = $props['accessibilityHint'] ?? null;
        if (is_string($hint) && $hint !== '') {
            $element = $element->accessibilityHint($hint);
        }
        $role = self::accessibilityRole($part, $props);
        if (
            $role !== AccessibilityRole::Generic
            || self::hasAccessibilityMetadata($runtimeProps)
        ) {
            $element = $element->accessibilityRole($role);
        }
        if (isset($props['testId']) && is_string($props['testId'])) {
            $element = $element->testId($props['testId']);
        }
        $element = self::accessibilityProperties(
            $part,
            $runtimeProps,
            $element,
        );
        foreach ($events as $event => $handler) {
            $kind = EventKind::from($event);
            if (
                $interactionDisabled
                && in_array($kind, [
                    EventKind::Press,
                    EventKind::Change,
                    EventKind::LongPress,
                    EventKind::Submit,
                    EventKind::Toggle,
                    EventKind::PressIn,
                    EventKind::PressOut,
                    EventKind::PressMove,
                    EventKind::TouchStart,
                    EventKind::TouchMove,
                    EventKind::TouchEnd,
                    EventKind::GestureBegin,
                    EventKind::GestureUpdate,
                    EventKind::GestureEnd,
                    EventKind::GestureCancel,
                    EventKind::DragStart,
                    EventKind::DragEnd,
                    EventKind::Drop,
                    EventKind::MenuAction,
                    EventKind::AccessibilityAction,
                ], true)
            ) {
                continue;
            }
            $element = $element->on($kind, $handler);
        }

        if (self::usesNativeWindow($part)) {
            $element = NativeModal::make(
                $element,
                !$shouldHide,
                self::modalPresentation($part, $runtimeProps),
            )
                ->animationType(self::modalAnimationType($part, $runtimeProps))
                ->backdropColor(
                    in_array($part, [
                        'BottomSheetPortal',
                        'SelectPortal',
                    ], true)
                        ? 0x00000000
                        : (
                            self::modalBackdropColor($runtimeProps)
                        ),
                )
                ->transparent(self::flag($runtimeProps, 'transparent', true))
                ->hardwareAccelerated(self::flag(
                    $runtimeProps,
                    'hardwareAccelerated',
                ))
                ->navigationBarTranslucent(self::flag(
                    $runtimeProps,
                    'navigationBarTranslucent',
                ))
                ->statusBarTranslucent(self::flag(
                    $runtimeProps,
                    'statusBarTranslucent',
                ))
                ->allowSwipeDismissal(self::flag(
                    $runtimeProps,
                    'allowSwipeDismissal',
                ))
                // The window host owns Android Back. Propagate the same
                // dismissal policy used by the inner Material surface even
                // when a bottom sheet is rendered through a transparent
                // full-screen portal instead of PamModalHost's sheet mode.
                ->property(
                    PropKey::BottomSheetDismissible,
                    self::modalDismissible($runtimeProps),
                );
            foreach ([
                EventKind::Native,
                EventKind::ModalRequestClose,
                EventKind::ModalShow,
                EventKind::ModalDismiss,
                EventKind::ModalOrientationChange,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler !== null) {
                    $element = $element->on($kind, $handler);
                }
            }
            $element = self::providerSemantics($part, $runtimeProps, $element);
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PSlideGroup'
            && ($props['direction'] ?? null) !== 'vertical'
            && !self::flag($props, 'vertical')
        ) {
            // Material filter/navigation rails keep their 48dp targets and
            // scroll when labels exceed the viewport. Compressing or clipping
            // the final destination makes it unreachable on compact phones.
            $slideGroupRootProperties = $element->properties();
            $element = Scroll::make($element)
                ->horizontal(true)
                ->scrollEnabled(true)
                ->showsIndicator(false)
                ->fillViewport(true)
                ->nestedScrollEnabled(true)
                ->overScrollMode(ScrollOverScrollMode::Auto)
                ->style(new Style(
                    widthPercent: 100.0,
                    height: MaterialTokens::MINIMUM_TOUCH_TARGET,
                    minHeight: MaterialTokens::MINIMUM_TOUCH_TARGET,
                ));
            // The scroll container becomes the public root node. Preserve the
            // semantics already resolved for the slide group instead of
            // leaving assistive technology focused on an unlabeled scroller.
            if (is_string($label) && $label !== '') {
                $element = $element->accessibilityLabel($label);
            }
            if (is_string($hint) && $hint !== '') {
                $element = $element->accessibilityHint($hint);
            }
            $element = $element->accessibilityRole($role);
            foreach ([
                PropKey::Enabled,
                PropKey::Selected,
                PropKey::Checked,
                PropKey::AccessibilityCheckedState,
                PropKey::AccessibilityBusy,
                PropKey::AccessibilityExpanded,
            ] as $semanticProperty) {
                if (array_key_exists($semanticProperty->value, $slideGroupRootProperties)) {
                    $element = $element->property(
                        $semanticProperty,
                        $slideGroupRootProperties[$semanticProperty->value],
                    );
                }
            }
        }
        if ($elementKey !== null) {
            $element = $element->key($elementKey);
        }

        return $element;
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     */
    private static function primitive(
        string $part,
        array $props,
        array $children,
        ?int $nativeBackground,
    ): Element {
        if (self::isText($part)) {
            $text = self::text($props, 'text');
            if ($text === '') {
                foreach ($children as $child) {
                    if ($child->kind() !== NodeKind::Text) {
                        continue;
                    }
                    $childText = $child->properties()[PropKey::Text->value] ?? null;
                    if (is_string($childText)) {
                        $text = $childText;
                        break;
                    }
                }
            }

            return Text::make($text)->property(
                PropKey::TextColor,
                ThemeManager::current()->color(ColorToken::Foreground),
            );
        }
        if (self::isInput($part)) {
            return self::input($part, $props);
        }
        if (self::isIcon($part)) {
            $iconProperties = self::iconProperties($part, $props);

            return CustomView::make(
                'pam.mobile_ui.icon',
                $iconProperties,
            )->property(
                PropKey::Value,
                'pam:icon:'.$iconProperties['icon'],
            );
        }
        if (self::isImage($part)) {
            $image = Image::make(
                self::imageSource($props),
            )->fit(self::imageFit($props));

            return self::configuredImage($image, $props);
        }
        if ($part === 'ImageBackground') {
            $image = ImageBackground::make(
                self::imageSource($props),
                ...$children,
            )->fit(self::imageFit($props));

            return self::configuredImage($image, $props);
        }
        if ($part === 'Spinner' || $part === 'ButtonSpinner') {
            $spinner = ActivityIndicator::make(
                self::flag(
                    $props,
                    'animating',
                    self::flag($props, 'visible', true),
                ),
            )
                ->hidesWhenStopped(
                    self::flag($props, 'hidesWhenStopped', true),
                )
                ->size(self::activityIndicatorSize($props));
            $color = self::packedColor($props['color'] ?? null);

            return $color === null ? $spinner : $spinner->color($color);
        }
        if (
            in_array($part, [
                'ScrollView',
                'BottomSheetScrollView',
                'SelectScrollView',
            ], true)
            && self::flag($props, 'horizontal')
        ) {
            return self::horizontalScroll($props, $children);
        }
        if ($part === 'VirtualizedList' && $children !== []) {
            return self::configuredList(
                \Pam\Native\UI\VirtualizedList::make(...$children)
                    ->rowHeight(self::number($props, 'rowHeight', 52.0))
                    ->prefetch(self::integer($props, 'prefetch', 8))
                    ->horizontal(self::flag($props, 'horizontal'))
                    ->columns(self::integer($props, 'numColumns', 1))
                    ->inverted(self::flag($props, 'inverted'))
                    ->removeClippedSubviews(
                        self::flag($props, 'removeClippedSubviews', true),
                    )
                    ->scrollEnabled(self::flag($props, 'scrollEnabled', true))
                    ->showsIndicator(
                        self::flag($props, 'showsScrollIndicator', true),
                    ),
                $props,
            );
        }
        if (in_array($part, [
            'FlatList',
            'VirtualizedList',
            'BottomSheetFlatList',
            'SelectFlatList',
            'SelectVirtualizedList',
        ], true)) {
            return self::configuredList(
                FlatList::make(self::stringList($props['items'] ?? [])),
                $props,
            );
        }
        if (in_array($part, [
            'SectionList',
            'BottomSheetSectionList',
            'SelectSectionList',
        ], true)) {
            return self::configuredList(
                SectionList::make(self::sections($props['sections'] ?? [])),
                $props,
            );
        }
        if (in_array($part, [
            'ScrollView',
            'BottomSheetScrollView',
            'SelectScrollView',
        ], true)) {
            return self::configuredScroll(
                $props,
                $children,
                false,
            );
        }
        if ($part === 'RefreshControl') {
            $control = RefreshControl::make(
                self::oneChild($children),
                self::flag($props, 'refreshing'),
            )
                ->enabled(self::flag($props, 'enabled', true))
                ->progressViewOffset(
                    self::number($props, 'progressViewOffset', 0.0),
                )
                ->size(self::refreshIndicatorSize($props));
            $colors = self::refreshColors($props);
            if ($colors !== []) {
                $control = $control->colors(...$colors);
            }
            $background = self::packedColor(
                $props['progressBackgroundColor'] ?? null,
            );

            return $background === null
                ? $control
                : $control->progressBackgroundColor($background);
        }
        if ($part === 'SafeAreaView') {
            $edges = self::safeAreaEdges($props);

            return SafeAreaView::make(...$children)
                ->edges(
                    $edges['top'],
                    $edges['right'],
                    $edges['bottom'],
                    $edges['left'],
                )
                ->mode(self::safeAreaMode($props));
        }
        if ($part === 'KeyboardAvoidingView') {
            return KeyboardAvoidingView::make(
                self::oneChild($children),
                self::keyboardAvoidingBehavior($props),
            )
                ->verticalOffset(
                    self::number($props, 'keyboardVerticalOffset', 0.0),
                )
                ->avoidingEnabled(self::flag($props, 'enabled', true));
        }
        if ($part === 'InputAccessoryView') {
            return InputAccessoryView::make(...$children);
        }
        if ($part === 'StatusBar') {
            return StatusBar::make(
                self::packedColor(
                    $props['backgroundColor'] ?? $props['color'] ?? null,
                ),
                self::statusBarAppearance($props),
                self::flag($props, 'hidden'),
            )
                ->animated(self::flag($props, 'animated'))
                ->translucent(self::flag($props, 'translucent'));
        }
        if ($part === 'Grid') {
            return self::grid($props, $children);
        }
        if ($part === 'TableRow') {
            $cells = array_map(
                static fn (Element $child): Element => View::make($child)
                    ->property(PropKey::Value, 'pam:grid-item:1,1,1,1,1,1'),
                $children,
            );
            return self::grid(
                ['columns' => max(1, count($cells))],
                $cells,
            )->property(
                PropKey::Value,
                self::flag($props, 'isHeaderRow')
                    ? 'pam:table-row:header'
                    : 'pam:table-row',
            );
        }
        $behavior = self::nativeBehavior($part);
        if ($behavior !== NativeBehavior::Container) {
            $host = CustomView::make(
                'pam.mobile_ui.host',
                self::nativeProperties(
                    $part,
                    $behavior,
                    $props,
                    $children,
                    $nativeBackground,
                ),
                ...$children,
            );
            if ($behavior === NativeBehavior::ChipGroup) {
                $host = $host->style(new Style(
                    height: max(40.0, (float) ceil(count($children) / 2) * 40.0),
                ));
            } elseif ($behavior === NativeBehavior::ListItem) {
                $requestedLines = $props['lines'] ?? null;
                $lines = match (is_string($requestedLines)
                    ? strtolower(trim($requestedLines))
                    : $requestedLines) {
                    1, '1', 'one' => 1,
                    2, '2', 'two' => 2,
                    3, '3', 'three' => 3,
                    default => count($children) > 1 ? 2 : 1,
                };
                $densityOffset = match ($props['density'] ?? null) {
                    MaterialDensity::Comfortable->value,
                    'comfortable' => -4.0,
                    MaterialDensity::Compact->value,
                    'compact' => -8.0,
                    default => 0.0,
                };
                $host = $host->style(new Style(
                    height: match ($lines) {
                        2 => MaterialTokens::LIST_TWO_LINE_HEIGHT,
                        3 => MaterialTokens::LIST_THREE_LINE_HEIGHT,
                        default => MaterialTokens::LIST_ONE_LINE_HEIGHT,
                    } + $densityOffset,
                ));
            } elseif ($behavior === NativeBehavior::Timeline) {
                $host = $host->style(new Style(height: max(64.0, count($children) * 64.0)));
            } elseif ($behavior === NativeBehavior::TimelineItem) {
                $host = $host->style(new Style(height: 64.0));
            }

            return $host;
        }
        if ($part === 'HStack' || $part === 'ButtonGroup' || $part === 'AvatarGroup') {
            return Row::make(...$children);
        }
        if (
            $part === 'Button'
            && ($props['__materialComponent'] ?? null) === 'PChip'
            && !self::flag($props, '__pamInteractive')
        ) {
            return Row::make(...$children);
        }
        if (
            $part === 'VStack'
            || $part === 'FileTree'
            || in_array($part, [
                'PopoverContent',
                'PopoverBody',
                'TooltipContent',
            ], true)
        ) {
            return Column::make(...$children);
        }
        if (self::isPressable($part)) {
            $theme = ThemeManager::current();
            $ripple = self::ripple($props);
            $rippleColor = $ripple['color']
                ?? $theme->color(ColorToken::Accent);
            $pressedOpacity = self::number(
                $props,
                array_key_exists('pressedOpacity', $props)
                    ? 'pressedOpacity'
                    : 'pressOpacity',
                1.0,
            );
            $hitSlop = self::edgeInsets($props['hitSlop'] ?? 8, 8.0);
            $retention = self::edgeInsets(
                $props['pressRetentionOffset'] ?? null,
                20.0,
                30.0,
            );

            return Pressable::make(...$children)
                ->ripple(
                    $rippleColor,
                    $ripple['borderless'],
                    $ripple['radius'],
                    $ripple['foreground'],
                    $ripple['alpha'],
                )
                ->pressedOpacity($pressedOpacity)
                ->hitSlopEdges(
                    $hitSlop['left'],
                    $hitSlop['top'],
                    $hitSlop['right'],
                    $hitSlop['bottom'],
                )
                ->pressRetentionEdges(
                    $retention['left'],
                    $retention['top'],
                    $retention['right'],
                    $retention['bottom'],
                )
                ->delayLongPress(self::pressDelay($props, 'delayLongPress', 500))
                ->delayPressIn(self::pressDelay(
                    $props,
                    'delayPressIn',
                    self::pressDelay($props, 'unstable_pressDelay', 0),
                ))
                ->delayPressOut(self::pressDelay($props, 'delayPressOut', 0))
                ->androidDisableSound(self::flag(
                    $props,
                    'android_disableSound',
                    self::flag($props, 'androidDisableSound'),
                ))
                ->accessibilityRole(AccessibilityRole::Button);
        }

        return View::make(...$children);
    }

    /**
     * Material sheets expose one compact p-* component while retaining the
     * native portal anatomy used by Android and UIKit. This keeps the sheet
     * outside normal layout, gives it a real scrim and preserves native
     * gestures, snap points, safe-area handling and dismiss events.
     *
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @param array<int, Closure> $events
     */
    private static function materialBottomSheet(
        array $props,
        array $children,
        array $events,
        ?Style $styleOverride,
        ?string $elementKey,
    ): Element {
        $props['__materialComponent'] = 'PBottomSheet';
        $props = self::materialOverlayProperties($props);
        // The backdrop color already owns the Material scrim alpha. Keep the
        // native host at full strength so 0x66 black remains an actual 40%
        // scrim instead of being multiplied into a barely visible 20% layer.
        $props['backdropOpacity'] ??= 1.0;
        $props = self::withDefaults('BottomSheet', $props);
        $nativeDismiss = $events[EventKind::Native->value] ?? null;
        if (
            $nativeDismiss !== null
            && !isset($events[EventKind::ModalRequestClose->value])
        ) {
            $events[EventKind::ModalRequestClose->value] =
                static function () use ($nativeDismiss): void {
                    $nativeDismiss('');
                };
        }

        $backdrop = self::render(
            'BottomSheetBackdrop',
            $props,
            [],
            [],
            new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                minHeight: 0.0,
                padding: 0.0,
                gap: 0.0,
                backgroundColor: self::modalBackdropColor($props),
                borderRadius: 0.0,
                elevation: 0.0,
                opacity: 1.0,
            ),
            null,
        );
        $contentChildren = $children;
        if (!self::flag($props, 'hideDragIndicator')) {
            $indicator = View::make()
                ->property(PropKey::Value, 'pam:sheet-drag-indicator')
                ->style(new Style(
                    width: MaterialTokens::BOTTOM_SHEET_HANDLE_WIDTH,
                    height: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT,
                    minWidth: MaterialTokens::BOTTOM_SHEET_HANDLE_WIDTH,
                    minHeight: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT,
                    backgroundColor: ThemeManager::current()->color(
                        ColorToken::MutedForeground,
                    ),
                    borderRadius: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT / 2.0,
                ));
            array_unshift(
                $contentChildren,
                Row::make($indicator)->style(new Style(
                    widthPercent: 100.0,
                    minHeight: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT,
                    alignItems: Align::Center,
                    justifyContent: Justify::Center,
                )),
            );
        }
        $content = self::render(
            'BottomSheetContent',
            $props,
            $contentChildren,
            [],
            $styleOverride,
            null,
        );
        $portal = self::render(
            'BottomSheetPortal',
            $props,
            [$backdrop, $content],
            $events,
            null,
            $elementKey,
        );

        return self::providerSemantics(
            'PBottomSheet',
            $props,
            $portal,
        );
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return array<string, string|int|float|bool>
     */
    private static function nativeProperties(
        string $part,
        NativeBehavior $behavior,
        array $props,
        array $children,
        ?int $nativeBackground,
    ): array {
        $values = [
            'part' => self::componentId($part),
            'component' => self::componentId($part),
            'behavior' => $behavior->value,
            'theme' => ThemeManager::resolvedMode()->value,
            'trackColor' => ThemeManager::current()->color(ColorToken::Muted),
            'fillColor' => self::materialSemanticColor($props),
            'foregroundColor' => ThemeManager::current()->color(ColorToken::Foreground),
            'selectedForegroundColor' => ThemeManager::current()->color(
                ColorToken::PrimaryForeground,
            ),
        ];
        $materialComponent = is_string($props['__materialComponent'] ?? null)
            ? $props['__materialComponent']
            : null;
        if ($materialComponent === 'PTab') {
            $values['foregroundColor'] = ThemeManager::current()->color(
                ColorToken::MutedForeground,
            );
            $values['selectedForegroundColor'] = ThemeManager::current()->color(
                ColorToken::Primary,
            );
        } elseif ($materialComponent === 'PSlideGroupItem') {
            $values['foregroundColor'] = ThemeManager::current()->color(
                ColorToken::MutedForeground,
            );
            $values['selectedForegroundColor'] = ThemeManager::current()->color(
                ColorToken::SecondaryForeground,
            );
        } elseif ($materialComponent === 'PItem') {
            $values['foregroundColor'] = ThemeManager::current()->color(
                ColorToken::OnSurface,
            );
            $values['selectedForegroundColor'] = ThemeManager::current()->color(
                ColorToken::AccentForeground,
            );
            $values['selectedContainerColor'] = ThemeManager::current()->color(
                ColorToken::Accent,
            );
        } elseif (
            $materialComponent === 'PTreeviewItem'
            || $part === 'FileTreeFolder'
            || $part === 'FileTreeFile'
        ) {
            $values['foregroundColor'] = ThemeManager::current()->color(
                ColorToken::OnSurface,
            );
            $values['selectedForegroundColor'] = ThemeManager::current()->color(
                ColorToken::AccentForeground,
            );
            $values['selectedContainerColor'] = ThemeManager::current()->color(
                ColorToken::Accent,
            );
        }
        $values['abstractSelectionItem'] = $materialComponent === 'PItem';
        $values['preserveChildForeground'] = $materialComponent === 'PStepperItem'
            || $materialComponent === 'PStepperVerticalItem';
        $values['navigationKind'] = match ($materialComponent) {
            'PCarousel' => 1,
            'PSlideGroup' => 3,
            'PTabs' => 4,
            'PStepper' => 5,
            default => 0,
        };
        $values['rating'] = $materialComponent === 'PRating';
        if ($materialComponent === 'PTimePicker') {
            $format = strtolower(self::scalarString($props['format'] ?? ''));
            $values['is24Hour'] = self::flag($props, 'is24Hour')
                || in_array($format, ['24', '24h', '24hr', '24-hour'], true);
        }

        foreach ($props as $name => $value) {
            if (
                preg_match('/^[A-Za-z][A-Za-z0-9_]{0,254}$/D', $name) === 1
                && (is_string($value) || is_int($value) || is_float($value) || is_bool($value))
            ) {
                $values[$name] = $value;
            }
        }
        $values['invalid'] = self::flag(
            $props,
            'error',
            self::flag($props, 'invalid', self::flag($props, 'isInvalid')),
        );
        $values['readOnly'] = self::flag(
            $props,
            'readonly',
            self::flag($props, 'readOnly', self::flag($props, 'isReadOnly')),
        );
        $values['interactionDisabled'] = $values['readOnly']
            || self::flag($props, 'disabled', self::flag($props, 'isDisabled'));
        if (self::flag($props, '__pamButtonToggleItem')) {
            $values['buttonToggleItem'] = true;
        }
        if (array_key_exists('defaultIsOpen', $props)) {
            $values['initiallyOpen'] = self::flag($props, 'defaultIsOpen');
        }
        if ($behavior === NativeBehavior::Toast) {
            foreach (['open', 'isOpen', 'persistent'] as $flag) {
                if (array_key_exists($flag, $props)) {
                    $values[$flag] = self::flag($props, $flag);
                }
            }
            $duration = $props['duration'] ?? $props['timeout'] ?? 4_000;
            $values['duration'] = is_numeric($duration)
                ? min(60_000, max(500, (int) $duration))
                : 4_000;
        }
        if ($behavior === NativeBehavior::Progress) {
            $values['circular'] = ($props['__materialComponent'] ?? null)
                === 'PProgressCircular';
        }
        if ($behavior === NativeBehavior::Calendar) {
            $values = [...$values, ...self::calendarNativeProperties($props)];
        }
        if ($behavior === NativeBehavior::BottomSheet) {
            $values = [...$values, ...self::sheetNativeProperties($props)];
        }
        if (
            $behavior === NativeBehavior::Slider
            || $behavior === NativeBehavior::Progress
            || $behavior === NativeBehavior::SwitchControl
        ) {
            $values = [
                ...$values,
                ...self::rangeNativeProperties(
                    $behavior,
                    $props,
                    $children,
                    $nativeBackground,
                ),
            ];
        }
        if (
            $behavior === NativeBehavior::InputGroup
            || $behavior === NativeBehavior::InputSlot
            || $behavior === NativeBehavior::FormControl
        ) {
            $values = [
                ...$values,
                ...self::inputNativeProperties($part, $behavior, $props),
            ];
        }
        if ($behavior === NativeBehavior::Skeleton) {
            $values['pulseDuration'] = self::skeletonDurationMillis($props);
            $values['lines'] = max(
                1,
                self::integer(
                    $props,
                    '_lines',
                    self::integer($props, 'lines', 1),
                ),
            );
        }
        if ($behavior === NativeBehavior::FileTree) {
            $values = [...$values, ...self::fileTreeNativeProperties($props)];
        }

        return $values;
    }

    /** @param array<string, mixed> $props */
    private static function materialSemanticColor(array $props): int
    {
        $semantic = $props['action'] ?? $props['color'] ?? 'primary';
        $token = match ($semantic) {
            'secondary' => ColorToken::Secondary,
            'success' => ColorToken::Success,
            'info', 'information' => ColorToken::Info,
            'warning' => ColorToken::Warning,
            'error', 'destructive' => ColorToken::Destructive,
            default => ColorToken::Primary,
        };

        return ThemeManager::current()->color($token);
    }

    public static function nativeBehavior(string $part): NativeBehavior
    {
        $part = self::legacyMaterialPart($part);

        return match ($part) {
            'FileTree' => NativeBehavior::FileTree,
            'FileTreeFolder' => NativeBehavior::FileTreeFolder,
            'FileTreeFile' => NativeBehavior::FileTreeFile,
            'PSparkline' => NativeBehavior::Sparkline,
            'ChipGroup' => NativeBehavior::ChipGroup,
            'ListItem' => NativeBehavior::ListItem,
            'Timeline' => NativeBehavior::Timeline,
            'TimelineItem' => NativeBehavior::TimelineItem,
            'Accordion' => NativeBehavior::AccordionGroup,
            'AccordionItem' => NativeBehavior::Accordion,
            'CheckboxGroup' => NativeBehavior::CheckboxGroup,
            'RadioGroup' => NativeBehavior::RadioGroup,
            'Switch' => NativeBehavior::SwitchControl,
            'BottomSheetPortal',
            'SelectPortal' => NativeBehavior::BottomSheet,
            'Slider' => NativeBehavior::Slider,
            'Tabs' => NativeBehavior::Tabs,
            'TabsTrigger' => NativeBehavior::TabsTrigger,
            'SelectItem' => NativeBehavior::SheetItem,
            'MenuItem' => NativeBehavior::MenuItem,
            'ModalCloseButton',
            'PopoverCloseButton' => NativeBehavior::OverlayDismiss,
            'Input',
            'Textarea' => NativeBehavior::InputGroup,
            'InputSlot' => NativeBehavior::InputSlot,
            'FormControl' => NativeBehavior::FormControl,
            'Table' => NativeBehavior::Table,
            'TableRow' => NativeBehavior::TableRow,
            'Calendar' => NativeBehavior::Calendar,
            'DateTimePicker' => NativeBehavior::DateTimePicker,
            'Skeleton' => NativeBehavior::Skeleton,
            'Checkbox' => NativeBehavior::Checkbox,
            'Radio' => NativeBehavior::Radio,
            'Toast' => NativeBehavior::Toast,
            'Progress' => NativeBehavior::Progress,
            'Modal' => NativeBehavior::Modal,
            'Popover' => NativeBehavior::Popover,
            'Menu' => NativeBehavior::Menu,
            'Tooltip' => NativeBehavior::Tooltip,
            'Portal' => NativeBehavior::Portal,
            default => NativeBehavior::Container,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public static function withDefaults(string $part, array $props): array
    {
        $materialComponent = $props['__materialComponent']
            ?? (isset(MaterialComponentMap::IDS[$part]) ? $part : null);
        $part = self::legacyMaterialPart($part);

        if (self::isIcon($part) && !array_key_exists('size', $props)) {
            $props['size'] = 'sm';
        }
        if ($part === 'TableHeader') {
            $props['isHeaderRow'] = true;
        } elseif ($part === 'TableFooter') {
            $props['isFooterRow'] = true;
        }
        if ($part === 'Divider' && !array_key_exists('orientation', $props)) {
            $props['orientation'] = 1;
        }
        if ($part === 'PamUIProvider' && !array_key_exists('mode', $props)) {
            $props['mode'] = 'system';
        }
        if ($part === 'Modal' && !array_key_exists('size', $props)) {
            $props['size'] = 3;
        }
        if (
            in_array($part, ['DateTimePicker', 'Select'], true)
            && !array_key_exists('size', $props)
        ) {
            $props['size'] = 3;
        }
        if ($part === 'DateTimePicker' && !array_key_exists('mode', $props)) {
            $props['mode'] = match ($materialComponent) {
                'PTimePicker' => ComponentMode::Time->value,
                'PDateInput' => ComponentMode::Date->value,
                default => ComponentMode::DateTime->value,
            };
        }
        if (
            in_array(
                $part,
                [
                    'BottomSheet',
                    'Modal',
                    'Select',
                ],
                true,
            )
            && !array_key_exists('open', $props)
            && !array_key_exists('isOpen', $props)
            && !array_key_exists('defaultIsOpen', $props)
        ) {
            $props['defaultIsOpen'] = false;
        }
        if (
            $part === 'BottomSheet'
            && !array_key_exists('defaultSnapIndex', $props)
        ) {
            $props['defaultSnapIndex'] = 0;
        } elseif ($part === 'BottomSheetPortal') {
            if (!array_key_exists('enablePanDownToClose', $props)) {
                $props['enablePanDownToClose'] = true;
            }
            if (!array_key_exists('enableDynamicSizing', $props)) {
                $props['enableDynamicSizing'] = false;
            }
        } elseif ($part === 'BottomSheetBackdrop') {
            if (!array_key_exists('appearsOnIndex', $props)) {
                $props['appearsOnIndex'] = 0;
            }
            if (!array_key_exists('disappearsOnIndex', $props)) {
                $props['disappearsOnIndex'] = -1;
            }
        } elseif (
            $part === 'BottomSheetContent'
            && !array_key_exists('focusScope', $props)
        ) {
            $props['focusScope'] = true;
        }
        if ($part === 'SelectInput') {
            if (!array_key_exists('editable', $props)) {
                $props['editable'] = false;
            }
        }
        if (!in_array(
            $part,
            [
                'Menu',
                'Popover',
                'Tooltip',
            ],
            true,
        )) {
            return $props;
        }
        if (
            !array_key_exists('open', $props)
            && !array_key_exists('isOpen', $props)
            && !array_key_exists('defaultIsOpen', $props)
        ) {
            $props['defaultIsOpen'] = false;
        }
        if (!array_key_exists('placement', $props)) {
            $props['placement'] = match ($part) {
                'Menu' => Placement::BottomStart->value,
                default => Placement::Bottom->value,
            };
        }
        if (
            $part === 'Menu'
            && !array_key_exists('selectionMode', $props)
        ) {
            $props['selectionMode'] = array_key_exists('selectedKeys', $props)
                ? SelectionMode::Single->value
                : SelectionMode::None->value;
        }
        if (
            $part === 'Tooltip'
            && !array_key_exists('trapFocus', $props)
        ) {
            $props['trapFocus'] = false;
        }
        if ($part === 'Tooltip') {
            $props['openOnClick'] ??= false;
            $props['openOnLongPress'] ??= true;
            $props['openDelay'] ??= 500;
            $props['closeDelay'] ??= 100;
        }

        return $props;
    }

    private static function legacyMaterialPart(string $part): string
    {
        return match ($part) {
            
            'PBanner',
            'PToolbar', 'PAppBar',
            'PBtnGroup',
            'PCardActions', 'PBannerActions' => 'HStack',
            'PList' => 'VStack',
            'PAlert' => 'Alert',
            'PAppBarNavIcon' => 'Button',
            'PAvatar' => 'Avatar',
            'PBadge' => 'Badge',
            'PBottomSheet' => 'BottomSheet',
            'PBtn', 'PIconBtn' => 'Button',
            'PBtnToggle' => 'HStack',
            'PItemGroup' => 'CheckboxGroup',
            'PChipGroup' => 'ChipGroup',
            'PCalendar' => 'Calendar',
            'PCalendarDay' => 'CalendarDay',
            'PCarousel', 'PStepper' => 'Tabs',
            'PStepperVertical' => 'Accordion',
            'PCard', 'PSheet', 'PEmptyState' => 'Card',
            'PCheckbox', 'PItem' => 'Checkbox',
            'PChip' => 'Button',
            'PRating' => 'Slider',
            'PDatePicker' => 'Calendar',
            'PTimePicker' => 'DateTimePicker',
            'PDialog' => 'Modal',
            'PDivider' => 'Divider',
            'PExpansionPanels' => 'Accordion',
            'PExpansionPanel' => 'AccordionItem',
            'PExpansionPanelTitle' => 'AccordionTrigger',
            'PExpansionPanelText' => 'AccordionContent',
            'PFab' => 'Fab',
            'PForm' => 'FormControl',
            'PIcon' => 'Icon',
            'PImg' => 'Image',
            'PTextField', 'PNumberInput', 'POtpInput',
            'PColorInput' => 'Input',
            'PDateInput' => 'DateTimePicker',
            'PMenu' => 'Menu',
            'PListItem' => 'ListItem',
            'POverlay' => 'Portal',
            'PProgressCircular', 'PProgressLinear' => 'Progress',
            'PRadio' => 'Radio',
            'PRadioGroup' => 'RadioGroup',
            'PSelect', 'PAutocomplete', 'PCombobox' => 'Select',
            'PSkeletonLoader' => 'Skeleton',
            'PSlideGroup' => 'Tabs',
            'PCarouselItem', 'PSlideGroupItem', 'PStepperItem' => 'TabsTrigger',
            'PStepperVerticalItem' => 'AccordionItem',
            'PSlider', 'PRangeSlider' => 'Slider',
            'PSnackbar' => 'Toast',
            'PSpeedDial' => 'Popover',
            'PStepperHeader' => 'TabsList',
            'PStepperWindow' => 'TabsContentWrapper',
            'PStepperWindowItem' => 'TabsContent',
            'PStepperActions', 'PStepperVerticalActions' => 'HStack',
            'PSwitch' => 'Switch',
            'PDataTable', 'PDataTableVirtual' => 'Table',
            'PTabs' => 'Tabs',
            'PTab' => 'TabsTrigger',
            'PTextarea' => 'Textarea',
            'PTooltip' => 'Tooltip',
            'PTimeline' => 'Timeline',
            'PTimelineItem' => 'TimelineItem',
            'PTreeview' => 'FileTree',
            'PTreeviewItem' => 'FileTreeFolder',
            'PInfiniteScroll' => 'VirtualizedList',
            default => $part,
        };
    }

    private static function componentId(string $part): int
    {
        return ComponentMap::IDS[$part]
            ?? MaterialComponentMap::IDS[$part]
            ?? match ($part) {
                'ChipGroup' => MaterialComponentMap::IDS['PChipGroup'],
                'ListItem' => MaterialComponentMap::IDS['PListItem'],
                'Timeline' => MaterialComponentMap::IDS['PTimeline'],
                'TimelineItem' => MaterialComponentMap::IDS['PTimelineItem'],
                default => throw new InvalidArgumentException(
                    "Unknown component part {$part}.",
                ),
            };
    }

    public static function forwardsEventsToDescendants(string $part): bool
    {
        $materialPart = $part;
        $part = self::legacyMaterialPart($part);

        return in_array($materialPart, ['PBtnGroup', 'PBtnToggle'], true)
            || in_array($part, [
            'Accordion',
            'BottomSheet',
            'CheckboxGroup',
            'ChipGroup',
            'Menu',
            'RadioGroup',
            'Select',
            'Tabs',
        ], true);
    }

    /**
     * @param array<string, mixed> $targetProps
     * @param array<string, mixed> $sourceProps
     * @param array<int, Closure> $events
     * @return array<int, Closure>
     */
    public static function inheritedEvents(
        string $source,
        string $target,
        array $targetProps,
        array $sourceProps,
        array $events,
    ): array {
        $materialSource = $source;
        $materialTarget = $target;
        $source = self::legacyMaterialPart($source);
        $target = self::legacyMaterialPart($target);

        if ($source === 'BottomSheet') {
            if ($target === 'BottomSheetTrigger') {
                $toggle = $events[EventKind::Toggle->value] ?? null;

                return $toggle === null
                    ? []
                    : [
                        EventKind::Press->value =>
                            static function () use ($toggle): void {
                                $toggle(true);
                            },
                    ];
            }
            if ($target === 'BottomSheetPortal') {
                $inherited = [];
                $change = $events[EventKind::Change->value] ?? null;
                $native = $events[EventKind::Native->value] ?? null;
                $toggle = $events[EventKind::Toggle->value] ?? null;
                if ($change !== null) {
                    $inherited[EventKind::Change->value] = $change;
                }
                if ($native !== null || $toggle !== null) {
                    $inherited[EventKind::Native->value] =
                        static function (string $payload) use (
                            $native,
                            $toggle,
                        ): void {
                            $native?->__invoke($payload);
                            if (
                                $toggle !== null
                                && self::nativeEventAction($payload) === 1
                            ) {
                                $toggle(false);
                            }
                        };
                }

                return $inherited;
            }
        }
        if ($source === 'Select' && $target === 'SelectPortal') {
            $native = $events[EventKind::Native->value] ?? null;

            return $native === null
                ? []
                : [EventKind::Native->value => $native];
        }

        $sourceKind = EventKind::Change->value;
        $handler = $events[$sourceKind] ?? null;
        $semanticValue = self::semanticValue($targetProps);
        if ($handler === null || $semanticValue === null) {
            return [];
        }

        if ($source === 'Select' && $target === 'SelectItem') {
            return [
                EventKind::Press->value => self::scalarSelectionHandler(
                    $handler,
                    $semanticValue,
                ),
            ];
        }
        if ($source === 'Menu' && $target === 'MenuItem') {
            if (($sourceProps['selectionMode'] ?? 1) === 2) {
                return [
                    EventKind::Press->value => self::listSelectionHandler(
                        $handler,
                        $semanticValue,
                        self::menuSelectedValues($sourceProps),
                    ),
                ];
            }

            return [
                EventKind::Press->value => self::scalarSelectionHandler(
                    $handler,
                    $semanticValue,
                ),
            ];
        }
        if ($source === 'RadioGroup' && $target === 'Radio') {
            return [
                EventKind::Toggle->value => self::scalarSelectionHandler(
                    $handler,
                    $semanticValue,
                ),
            ];
        }
        if (
            $source === 'ChipGroup'
            && $target === 'Button'
            && $materialTarget === 'PChip'
        ) {
            return [
                EventKind::Press->value => self::flag($sourceProps, 'multiple')
                    ? self::listSelectionHandler(
                        $handler,
                        $semanticValue,
                        self::selectedValues($sourceProps),
                    )
                    : self::scalarSelectionHandler($handler, $semanticValue),
            ];
        }
        if (
            $source === 'HStack'
            && $materialSource === 'PBtnGroup'
            && $target === 'Button'
            && $materialTarget === 'PBtn'
        ) {
            if (self::flag($sourceProps, 'multiple')) {
                return [
                    EventKind::Press->value => self::listSelectionHandler(
                        $handler,
                        $semanticValue,
                        self::selectedValues($sourceProps),
                        !self::flag($sourceProps, 'mandatory'),
                    ),
                ];
            }

            return [
                EventKind::Press->value => self::nullableScalarSelectionHandler(
                    $handler,
                    $semanticValue,
                    $sourceProps['modelValue']
                        ?? $sourceProps['value']
                        ?? $sourceProps['defaultValue']
                        ?? null,
                    self::flag($sourceProps, 'mandatory'),
                ),
            ];
        }
        if (
            $source === 'HStack'
            && $materialSource === 'PBtnToggle'
            && $target === 'Button'
            && $materialTarget === 'PBtn'
        ) {
            return [
                EventKind::Press->value => self::flag($sourceProps, 'multiple')
                    ? self::listSelectionHandler(
                        $handler,
                        $semanticValue,
                        self::selectedValues($sourceProps),
                        !self::flag($sourceProps, 'mandatory'),
                    )
                    : self::nullableScalarSelectionHandler(
                        $handler,
                        $semanticValue,
                        $sourceProps['modelValue']
                            ?? $sourceProps['value']
                            ?? $sourceProps['defaultValue']
                            ?? null,
                        self::flag($sourceProps, 'mandatory'),
                    ),
            ];
        }
        if (
            $source === 'Tabs'
            && in_array($materialSource, ['PTabs', 'PSlideGroup'], true)
            && $target === 'TabsTrigger'
            && in_array($materialTarget, ['PTab', 'PSlideGroupItem'], true)
        ) {
            return [
                EventKind::Press->value => self::scalarSelectionHandler(
                    $handler,
                    $semanticValue,
                ),
            ];
        }
        if ($source === 'CheckboxGroup' && $target === 'Checkbox') {
            return [
                EventKind::Toggle->value => self::listSelectionHandler(
                    $handler,
                    $semanticValue,
                    self::selectedValues($sourceProps),
                ),
            ];
        }
        if ($source === 'Accordion' && $target === 'AccordionItem') {
            if ($materialSource === 'PStepperVertical') {
                return [
                    EventKind::Toggle->value =>
                        self::scalarSelectionHandler($handler, $semanticValue),
                ];
            }

            return [
                EventKind::Toggle->value => self::listSelectionHandler(
                    $handler,
                    $semanticValue,
                    self::selectedValues($sourceProps),
                    self::flag($sourceProps, 'isCollapsible', true),
                    ($sourceProps['type'] ?? 1) === 2,
                ),
            ];
        }

        return [];
    }

    /** @param array<string, mixed> $props */
    private static function accessibilityRole(
        string $part,
        array $props,
    ): AccessibilityRole {
        $explicit = array_key_exists('role', $props)
            ? $props['role']
            : ($props['accessibilityRole'] ?? null);
        $explicitRole = self::namedAccessibilityRole($explicit);
        if ($explicitRole !== null) {
            return $explicitRole;
        }
        if (
            self::flag($props, '__pamButtonGroupItem')
            || self::flag($props, '__pamButtonToggleItem')
        ) {
            return AccessibilityRole::ToggleButton;
        }
        if (($props['__materialComponent'] ?? null) === 'PItem') {
            return AccessibilityRole::Button;
        }

        $materialRole = match ($props['__materialComponent'] ?? null) {
            'PAppBar', 'PToolbar' => AccessibilityRole::Toolbar,
            'PAutocomplete', 'PCombobox', 'PSelect' => AccessibilityRole::ComboBox,
            'PBanner', 'PSnackbar' => AccessibilityRole::Alert,
            'PCalendar', 'PDatePicker' => AccessibilityRole::Grid,
            'PColorInput', 'PDateInput', 'POtpInput', 'PTextField', 'PTextarea' =>
                AccessibilityRole::Input,
            'PEmptyState' => AccessibilityRole::Summary,
            'PList', 'PStepper', 'PStepperVertical', 'PTimeline', 'PTreeview' =>
                AccessibilityRole::List,
            'PListItem', 'PStepperVerticalItem', 'PTimelineItem', 'PTreeviewItem' =>
                AccessibilityRole::ListItem,
            'PNumberInput' => AccessibilityRole::SpinButton,
            'PSlideGroup', 'PTabs' => AccessibilityRole::TabList,
            'PSpeedDial' => AccessibilityRole::Button,
            'PTooltip' => AccessibilityRole::Presentation,
            'PAvatar', 'PIcon', 'PSparkline' =>
                is_string($props['accessibilityLabel'] ?? null)
                && $props['accessibilityLabel'] !== ''
                    ? AccessibilityRole::Image
                    : AccessibilityRole::Generic,
            default => null,
        };
        if ($materialRole !== null) {
            return $materialRole;
        }

        $semanticRole = match ($part) {
            'Heading' => AccessibilityRole::Header,
            'Link' => AccessibilityRole::Link,
            'Alert',
            'PBanner',
            'PSnackbar' => AccessibilityRole::Alert,
            'Checkbox' => AccessibilityRole::Checkbox,
            'Radio' => AccessibilityRole::Radio,
            'RadioGroup' => AccessibilityRole::RadioGroup,
            'SelectTrigger',
            'PAutocomplete',
            'PCombobox',
            'PSelect' => AccessibilityRole::ComboBox,
            'PNumberInput' => AccessibilityRole::SpinButton,
            'PColorInput',
            'PDateInput',
            'POtpInput',
            'PTextField',
            'PTextarea' => AccessibilityRole::Input,
            'Menu' => AccessibilityRole::Menu,
            'MenuItem' => AccessibilityRole::MenuItem,
            'Progress', 'Spinner', 'ButtonSpinner' =>
                AccessibilityRole::ProgressBar,
            'Slider' => AccessibilityRole::Adjustable,
            'TabsList', 'PStepperHeader' => AccessibilityRole::TabList,
            'TabsTrigger' => AccessibilityRole::Tab,
            'Grid', 'Table' => AccessibilityRole::Grid,
            'FlatList',
            'VirtualizedList',
            'SectionList',
            'BottomSheetFlatList',
            'BottomSheetSectionList',
            'SelectFlatList',
            'SelectVirtualizedList',
            'SelectSectionList',
            
            'PList',
            
            'PStepper',
            'PTimeline',
            'PTreeview' => AccessibilityRole::List,
            'PListItem',
            'PTimelineItem',
            'PTreeviewItem' => AccessibilityRole::ListItem,
            'PAppBar', 'PToolbar' => AccessibilityRole::Toolbar,
            'PCalendarDay',
            'PSpeedDial' => AccessibilityRole::Button,
            'PEmptyState' => AccessibilityRole::Summary,
            'PTooltip' => AccessibilityRole::Presentation,
            'AccordionTrigger' => AccessibilityRole::ToggleButton,
            default => null,
        };
        if ($semanticRole !== null) {
            return $semanticRole;
        }

        if (self::isInput($part)) {
            return AccessibilityRole::Input;
        }
        if (self::isImage($part) || $part === 'ImageBackground') {
            return AccessibilityRole::Image;
        }
        if ($part === 'Switch') {
            return AccessibilityRole::Switch;
        }
        if (self::isPressable($part)) {
            return AccessibilityRole::Button;
        }

        return AccessibilityRole::Generic;
    }

    private static function namedAccessibilityRole(
        mixed $value,
    ): ?AccessibilityRole {
        if (is_int($value)) {
            return AccessibilityRole::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'generic' => AccessibilityRole::Generic,
            'button' => AccessibilityRole::Button,
            'input' => AccessibilityRole::Input,
            'image', 'img' => AccessibilityRole::Image,
            'switch' => AccessibilityRole::Switch,
            'adjustable', 'slider' => AccessibilityRole::Adjustable,
            'alert' => AccessibilityRole::Alert,
            'checkbox' => AccessibilityRole::Checkbox,
            'combobox' => AccessibilityRole::ComboBox,
            'header', 'heading' => AccessibilityRole::Header,
            'imagebutton' => AccessibilityRole::ImageButton,
            'keyboardkey' => AccessibilityRole::KeyboardKey,
            'link' => AccessibilityRole::Link,
            'menu' => AccessibilityRole::Menu,
            'menubar' => AccessibilityRole::MenuBar,
            'menuitem' => AccessibilityRole::MenuItem,
            'none' => AccessibilityRole::None,
            'presentation' => AccessibilityRole::Presentation,
            'progressbar' => AccessibilityRole::ProgressBar,
            'radio' => AccessibilityRole::Radio,
            'radiogroup' => AccessibilityRole::RadioGroup,
            'scrollbar' => AccessibilityRole::ScrollBar,
            'search', 'searchbox' => AccessibilityRole::Search,
            'spinbutton' => AccessibilityRole::SpinButton,
            'summary' => AccessibilityRole::Summary,
            'tab' => AccessibilityRole::Tab,
            'tablist' => AccessibilityRole::TabList,
            'text' => AccessibilityRole::Text,
            'timer' => AccessibilityRole::Timer,
            'togglebutton' => AccessibilityRole::ToggleButton,
            'toolbar' => AccessibilityRole::Toolbar,
            'grid' => AccessibilityRole::Grid,
            'list' => AccessibilityRole::List,
            'listitem' => AccessibilityRole::ListItem,
            default => null,
        };
    }

    /** @param array<string, mixed> $props */
    private static function hasAccessibilityMetadata(array $props): bool
    {
        foreach ($props as $name => $_value) {
            if (
                str_starts_with($name, 'accessibility')
                || str_starts_with($name, 'aria')
                || in_array($name, [
                    'accessible',
                    'importantForAccessibility',
                    'role',
                ], true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies the same accessibility contract used by regular rendered roots
     * to provider roots that intentionally return before primitive rendering.
     *
     * @param array<string, mixed> $props
     */
    private static function providerSemantics(
        string $part,
        array $props,
        Element $element,
    ): Element {
        if (array_key_exists('rtl', $props)) {
            $element = $element->property(
                PropKey::LayoutDirection,
                self::flag($props, 'rtl') ? 2 : 1,
            );
        }
        if (
            self::flag($props, 'disabled', self::flag($props, 'isDisabled'))
            || self::accessibilityStateBoolean($props, 'disabled') === true
        ) {
            $element = $element->enabled(false);
        }
        $label = $props['accessibilityLabel'] ?? $props['ariaLabel'] ?? null;
        if (is_string($label) && $label !== '') {
            $element = $element->accessibilityLabel($label);
        }
        $hint = $props['accessibilityHint'] ?? null;
        if (is_string($hint) && $hint !== '') {
            $element = $element->accessibilityHint($hint);
        }
        $role = self::accessibilityRole($part, $props);
        if (
            $role !== AccessibilityRole::Generic
            || self::hasAccessibilityMetadata($props)
        ) {
            $element = $element->accessibilityRole($role);
        }
        if (isset($props['testId']) && is_string($props['testId'])) {
            $element = $element->testId($props['testId']);
        }

        return self::accessibilityProperties($part, $props, $element);
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function accessibilityProperties(
        string $part,
        array $props,
        Element $element,
    ): Element {
        if (array_key_exists('accessible', $props)) {
            $element = $element->accessible(
                self::flag($props, 'accessible', true),
            );
        }

        $liveRegion = self::accessibilityLiveRegion(
            $props['ariaLive']
                ?? $props['aria-live']
                ?? $props['accessibilityLiveRegion']
                ?? null,
        );
        $liveRegion ??= match ($part) {
            'PAlert' => AccessibilityLiveRegion::Assertive,
            'PBanner',
            'PSnackbar' => AccessibilityLiveRegion::Polite,
            default => null,
        };
        $liveRegion ??= match ($props['__materialComponent'] ?? null) {
            'PAlert' => AccessibilityLiveRegion::Assertive,
            'PBanner', 'PSnackbar' => AccessibilityLiveRegion::Polite,
            default => null,
        };
        if ($liveRegion !== null) {
            $element = $element->accessibilityLiveRegion($liveRegion);
        }

        $importance = self::accessibilityImportance($props);
        if ($importance !== null) {
            $element = $element->accessibilityImportance($importance);
        }

        $selected = self::accessibilityStateBoolean($props, 'selected')
            ?? self::flag(
                $props,
                'selected',
                self::flag($props, 'isSelected'),
            );
        $accessibilityState = $props['accessibilityState'] ?? null;
        $hasSelectedState = array_key_exists('selected', $props)
            || array_key_exists('isSelected', $props)
            || array_key_exists('ariaSelected', $props)
            || array_key_exists('aria-selected', $props)
            || (
                is_array($accessibilityState)
                && array_key_exists('selected', $accessibilityState)
            );
        if ($hasSelectedState) {
            // Retained native nodes need the negative transition as well as
            // the positive one; omitting false leaves the previous selection
            // painted after a controlled model update.
            $element = $element->property(PropKey::Selected, $selected);
        }

        $checkedState = self::accessibilityCheckedState($part, $props);
        if ($checkedState !== null) {
            $element = $element
                ->property(
                    PropKey::Checked,
                    $checkedState === AccessibilityCheckedState::Checked,
                )
                ->accessibilityChecked($checkedState);
        }

        $loading = self::flag(
            $props,
            'loading',
            self::flag($props, 'isLoading'),
        );
        if ($loading) {
            $element = $element
                ->property(PropKey::Loading, true)
                ->property(PropKey::Enabled, false);
        }
        $busy = self::accessibilityStateBoolean($props, 'busy');
        $indeterminate = self::flag($props, 'indeterminate');
        if ($busy !== null || $loading || $indeterminate) {
            $element = $element->accessibilityBusy(
                $busy ?? ($loading || $indeterminate),
            );
        }

        $expanded = self::accessibilityStateBoolean($props, 'expanded');
        if ($expanded === null) {
            if (array_key_exists('expanded', $props)) {
                $expanded = self::flag($props, 'expanded');
            } elseif (array_key_exists('isExpanded', $props)) {
                $expanded = self::flag($props, 'isExpanded');
            }
        }
        if ($expanded !== null) {
            $element = $element->accessibilityExpanded($expanded);
        }

        $range = self::accessibilityRange($part, $props);
        foreach ([
            [PropKey::AccessibilityValueMin, $range['min']],
            [PropKey::AccessibilityValueMax, $range['max']],
            [PropKey::AccessibilityValueNow, $range['now']],
            [PropKey::AccessibilityValueText, $range['text']],
        ] as [$property, $value]) {
            if ($value !== null) {
                $element = $element->property($property, $value);
            }
        }

        return $element;
    }

    private static function accessibilityLiveRegion(
        mixed $value,
    ): ?AccessibilityLiveRegion {
        if (is_int($value)) {
            return AccessibilityLiveRegion::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'none', 'off' => AccessibilityLiveRegion::None,
            'polite' => AccessibilityLiveRegion::Polite,
            'assertive' => AccessibilityLiveRegion::Assertive,
            default => null,
        };
    }

    /** @param array<string, mixed> $props */
    private static function accessibilityImportance(
        array $props,
    ): ?AccessibilityImportance {
        if (
            self::booleanValue(
                $props['ariaHidden']
                    ?? $props['aria-hidden']
                    ?? $props['accessibilityElementsHidden']
                    ?? null,
            ) === true
        ) {
            return AccessibilityImportance::NoHideDescendants;
        }
        $value = $props['importantForAccessibility'] ?? null;
        if (is_int($value)) {
            return AccessibilityImportance::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'auto' => AccessibilityImportance::Auto,
            'yes' => AccessibilityImportance::Yes,
            'no' => AccessibilityImportance::No,
            'no-hide-descendants' => AccessibilityImportance::NoHideDescendants,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function accessibilityStateBoolean(
        array $props,
        string $name,
    ): ?bool {
        $pascal = ucfirst($name);
        foreach (["aria{$pascal}", "aria-{$name}"] as $alias) {
            if (array_key_exists($alias, $props)) {
                return self::booleanValue($props[$alias]);
            }
        }
        $state = $props['accessibilityState'] ?? null;
        if (is_array($state) && array_key_exists($name, $state)) {
            return self::booleanValue($state[$name]);
        }

        return null;
    }

    private static function booleanValue(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) && in_array(
                strtolower($value),
                ['1', 'true', 'yes', 'on'],
                true,
            ) => true,
            is_string($value) && in_array(
                strtolower($value),
                ['0', 'false', 'no', 'off'],
                true,
            ) => false,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function accessibilityCheckedState(
        string $part,
        array $props,
    ): ?AccessibilityCheckedState {
        $direct = $props['accessibilityCheckedState'] ?? null;
        if (is_int($direct)) {
            $state = AccessibilityCheckedState::tryFrom($direct);
            if ($state !== null) {
                return $state;
            }
        }
        if (is_string($direct)) {
            $state = match (strtolower($direct)) {
                'unchecked', 'false' => AccessibilityCheckedState::Unchecked,
                'checked', 'true' => AccessibilityCheckedState::Checked,
                'mixed' => AccessibilityCheckedState::Mixed,
                default => null,
            };
            if ($state !== null) {
                return $state;
            }
        }

        $state = $props['accessibilityState'] ?? null;
        $value = $props['ariaChecked']
            ?? $props['aria-checked']
            ?? (is_array($state) ? ($state['checked'] ?? null) : null)
            ?? $props['checked']
            ?? $props['isChecked']
            ?? (
                $part === 'Switch'
                    ? ($props['value'] ?? null)
                    : null
            );
        if (is_string($value) && strtolower($value) === 'mixed') {
            return AccessibilityCheckedState::Mixed;
        }
        $checked = self::booleanValue($value);
        if ($checked !== null) {
            return $checked
                ? AccessibilityCheckedState::Checked
                : AccessibilityCheckedState::Unchecked;
        }

        return in_array($part, ['Checkbox', 'Radio', 'Switch'], true)
            ? AccessibilityCheckedState::Unchecked
            : null;
    }

    /**
     * @param array<string, mixed> $props
     * @return array{lower: float, upper: float, min: float, max: float, step: float}|null
     */
    private static function normalizedRangeModel(
        array $props,
        mixed $model,
    ): ?array {
        if (!is_array($model)) {
            return null;
        }
        $values = array_values(array_filter(
            $model,
            static fn (mixed $value): bool => is_numeric($value),
        ));
        if ($values === []) {
            return null;
        }
        $minimum = is_numeric($props['min'] ?? $props['minValue'] ?? null)
            ? self::scalarFloat($props['min'] ?? $props['minValue'])
            : 0.0;
        $requestedMaximum = is_numeric(
            $props['max'] ?? $props['maxValue'] ?? null,
        )
            ? self::scalarFloat($props['max'] ?? $props['maxValue'])
            : 100.0;
        $maximum = max($minimum + 0.000001, $requestedMaximum);
        $step = is_numeric($props['step'] ?? null)
            ? max(0.000001, (float) $props['step'])
            : 1.0;
        $snap = static function (float $value) use (
            $minimum,
            $maximum,
            $step,
        ): float {
            $clamped = min($maximum, max($minimum, $value));

            return min(
                $maximum,
                max(
                    $minimum,
                    $minimum + round(($clamped - $minimum) / $step) * $step,
                ),
            );
        };
        $first = $snap((float) $values[0]);
        $second = $snap((float) ($values[1] ?? $values[0]));

        return [
            'lower' => min($first, $second),
            'upper' => max($first, $second),
            'min' => $minimum,
            'max' => $maximum,
            'step' => $step,
        ];
    }

    /**
     * @param array<string, mixed> $props
     * @return array{min: float|null, max: float|null, now: float|null, text: string|null}
     */
    private static function accessibilityRange(
        string $part,
        array $props,
    ): array {
        $value = $props['accessibilityValue'] ?? null;
        $range = is_array($value) ? $value : [];
        $material = $props['__materialComponent'] ?? null;
        if ($material === 'PRating') {
            $maximum = (float) min(
                20,
                max(1, self::integer($props, 'length', 5)),
            );
            $current = $props['modelValue']
                ?? $props['value']
                ?? $props['defaultValue']
                ?? 0;
            $current = is_numeric($current)
                ? min($maximum, max(0.0, (float) $current))
                : 0.0;

            return [
                'min' => 0.0,
                'max' => $maximum,
                'now' => $current,
                'text' => is_scalar($range['text'] ?? null)
                    ? (string) $range['text']
                    : null,
            ];
        }
        if ($material === 'PRangeSlider') {
            $model = $props['modelValue'] ?? $props['value'] ?? null;
            $normalized = self::normalizedRangeModel($props, $model);
            if ($normalized !== null) {
                $lower = $normalized['lower'];
                $upper = $normalized['upper'];

                return [
                    'min' => $normalized['min'],
                    'max' => $normalized['max'],
                    'now' => $upper,
                    'text' => is_scalar($range['text'] ?? null)
                        ? (string) $range['text']
                        : (string) $lower.' to '.(string) $upper,
                ];
            }
        }
        if ($part === 'Progress' && self::flag($props, 'indeterminate')) {
            $text = $props['ariaValueText']
                ?? $props['aria-valuetext']
                ?? $range['text']
                ?? 'In progress';

            return [
                'min' => null,
                'max' => null,
                'now' => null,
                'text' => is_scalar($text) ? (string) $text : 'In progress',
            ];
        }
        if (in_array($part, ['Progress', 'Slider'], true)) {
            $range['min'] ??= $props['minValue'] ?? $props['min'] ?? 0;
            $range['max'] ??= $props['maxValue'] ?? $props['max'] ?? 100;
            $range['now'] ??= $props['value'] ?? 0;
        }
        $range['min'] = $props['ariaValueMin']
            ?? $props['aria-valuemin']
            ?? $range['min']
            ?? null;
        $range['max'] = $props['ariaValueMax']
            ?? $props['aria-valuemax']
            ?? $range['max']
            ?? null;
        $range['now'] = $props['ariaValueNow']
            ?? $props['aria-valuenow']
            ?? $range['now']
            ?? null;
        $text = $props['ariaValueText']
            ?? $props['aria-valuetext']
            ?? $range['text']
            ?? null;

        return [
            'min' => is_numeric($range['min']) ? (float) $range['min'] : null,
            'max' => is_numeric($range['max']) ? (float) $range['max'] : null,
            'now' => is_numeric($range['now']) ? (float) $range['now'] : null,
            'text' => is_scalar($text) ? (string) $text : null,
        ];
    }

    /**
     * Forward React Native primitive props after recipe and inline styles so
     * explicit component props retain their native precedence.
     *
     * @param array<string, mixed> $props
     */
    private static function explicitNativeProperties(
        string $part,
        array $props,
        Element $element,
    ): Element {
        if (array_key_exists('collapsable', $props)) {
            $element = $element->collapsable(
                self::flag($props, 'collapsable', true),
            );
        }

        $pointerEvents = self::pointerEvents($props['pointerEvents'] ?? null);
        if ($pointerEvents !== null) {
            $element = $element->property(
                PropKey::PointerEvents,
                $pointerEvents->value,
            );
        }

        if (self::isText($part)) {
            if (array_key_exists('numberOfLines', $props)) {
                $element = $element->property(
                    PropKey::NumberOfLines,
                    max(0, self::integer($props, 'numberOfLines', 0)),
                );
            }
            if (array_key_exists('selectable', $props)) {
                $element = $element->property(
                    PropKey::TextSelectable,
                    self::flag($props, 'selectable'),
                );
            }
            $selectionColor = self::packedColor($props['selectionColor'] ?? null);
            if ($selectionColor !== null) {
                $element = $element->property(
                    PropKey::SelectionColor,
                    $selectionColor,
                );
            }
            $ellipsize = self::textEllipsizeMode($props['ellipsizeMode'] ?? null);
            if ($ellipsize !== null) {
                $element = $element->property(
                    PropKey::TextEllipsizeMode,
                    $ellipsize->value,
                );
            }
            if (array_key_exists('allowFontScaling', $props)) {
                $element = $element->property(
                    PropKey::TextAllowFontScaling,
                    self::flag($props, 'allowFontScaling', true),
                );
            }
            if (is_numeric($props['maxFontSizeMultiplier'] ?? null)) {
                $element = $element->property(
                    PropKey::TextMaxFontSizeMultiplier,
                    max(0.0, (float) $props['maxFontSizeMultiplier']),
                );
            }
            if (array_key_exists('adjustsFontSizeToFit', $props)) {
                $element = $element->property(
                    PropKey::TextAdjustsFontSizeToFit,
                    self::flag($props, 'adjustsFontSizeToFit'),
                );
            }
            if (is_numeric($props['minimumFontScale'] ?? null)) {
                $element = $element->property(
                    PropKey::TextMinimumFontScale,
                    min(
                        1.0,
                        max(0.01, (float) $props['minimumFontScale']),
                    ),
                );
            }
            $breakStrategy = self::textBreakStrategy(
                $props['textBreakStrategy'] ?? null,
            );
            if ($breakStrategy !== null) {
                $element = $element->property(
                    PropKey::TextBreakStrategy,
                    $breakStrategy->value,
                );
            }
            $hyphenation = self::textHyphenationFrequency(
                $props['android_hyphenationFrequency']
                    ?? $props['androidHyphenationFrequency']
                    ?? null,
            );
            if ($hyphenation !== null) {
                $element = $element->property(
                    PropKey::TextHyphenationFrequency,
                    $hyphenation->value,
                );
            }
            $detector = self::textDataDetectorType(
                $props['dataDetectorType'] ?? null,
            );
            if ($detector !== null) {
                $element = $element->property(
                    PropKey::TextDataDetectorType,
                    $detector->value,
                );
            }
        }

        if (!self::isInput($part)) {
            return $element;
        }

        $placeholderColor = self::packedColor(
            $props['placeholderTextColor']
                ?? $props['placeholderColor']
                ?? null,
        );
        if ($placeholderColor !== null) {
            $element = $element->property(
                PropKey::PlaceholderColor,
                $placeholderColor,
            );
        }

        $selectionColor = self::packedColor($props['selectionColor'] ?? null);
        if ($selectionColor !== null) {
            $element = $element->property(
                PropKey::SelectionColor,
                $selectionColor,
            );
        }

        $returnKey = self::returnKeyType($props['returnKeyType'] ?? null);
        if ($returnKey !== null) {
            $element = $element->property(
                PropKey::ReturnKeyType,
                $returnKey->value,
            );
        }

        return $element;
    }

    private static function pointerEvents(mixed $value): ?PointerEvents
    {
        if (is_int($value)) {
            return PointerEvents::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'auto' => PointerEvents::Auto,
            'none' => PointerEvents::None,
            'box-none', 'boxnone' => PointerEvents::BoxNone,
            'box-only', 'boxonly' => PointerEvents::BoxOnly,
            default => null,
        };
    }

    private static function textEllipsizeMode(
        mixed $value,
    ): ?TextEllipsizeMode {
        if (is_int($value)) {
            return TextEllipsizeMode::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'tail' => TextEllipsizeMode::Tail,
            'head' => TextEllipsizeMode::Head,
            'middle' => TextEllipsizeMode::Middle,
            'clip' => TextEllipsizeMode::Clip,
            default => null,
        };
    }

    private static function textBreakStrategy(
        mixed $value,
    ): ?TextBreakStrategy {
        if (is_int($value)) {
            return TextBreakStrategy::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'highquality', 'high-quality' => TextBreakStrategy::HighQuality,
            'simple' => TextBreakStrategy::Simple,
            'balanced' => TextBreakStrategy::Balanced,
            default => null,
        };
    }

    private static function textHyphenationFrequency(
        mixed $value,
    ): ?TextHyphenationFrequency {
        if (is_int($value)) {
            return TextHyphenationFrequency::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'none' => TextHyphenationFrequency::None,
            'normal' => TextHyphenationFrequency::Normal,
            'full' => TextHyphenationFrequency::Full,
            default => null,
        };
    }

    private static function textDataDetectorType(
        mixed $value,
    ): ?TextDataDetectorType {
        if (is_int($value)) {
            return TextDataDetectorType::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'none' => TextDataDetectorType::None,
            'phonenumber', 'phone-number' => TextDataDetectorType::PhoneNumber,
            'link' => TextDataDetectorType::Link,
            'email' => TextDataDetectorType::Email,
            'all' => TextDataDetectorType::All,
            default => null,
        };
    }

    private static function returnKeyType(mixed $value): ?ReturnKeyType
    {
        if (is_int($value)) {
            return ReturnKeyType::tryFrom($value);
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'default' => ReturnKeyType::Default,
            'done' => ReturnKeyType::Done,
            'go' => ReturnKeyType::Go,
            'next' => ReturnKeyType::Next,
            'search' => ReturnKeyType::Search,
            'send' => ReturnKeyType::Send,
            'none' => ReturnKeyType::None,
            'previous' => ReturnKeyType::Previous,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @return array{
     *     color: ?int,
     *     borderless: bool,
     *     radius: ?float,
     *     foreground: bool,
     *     alpha: float
     * }
     */
    private static function ripple(array $props): array
    {
        $ripple = $props['android_ripple']
            ?? $props['androidRipple']
            ?? null;
        $value = is_array($ripple)
            ? ($ripple['color'] ?? null)
            : ($props['rippleColor'] ?? $ripple);
        $radius = is_array($ripple) && is_numeric($ripple['radius'] ?? null)
            ? max(0.0, (float) $ripple['radius'])
            : null;
        $alpha = is_array($ripple) && is_numeric($ripple['alpha'] ?? null)
            ? min(1.0, max(0.0, (float) $ripple['alpha']))
            : 1.0;

        return [
            'color' => self::packedColor($value),
            'borderless' => is_array($ripple)
                && self::eventFlag($ripple['borderless'] ?? null, false),
            'radius' => $radius,
            'foreground' => is_array($ripple)
                && self::eventFlag($ripple['foreground'] ?? null, false),
            'alpha' => $alpha,
        ];
    }

    /**
     * @return array{left: float, top: float, right: float, bottom: float}
     */
    private static function edgeInsets(
        mixed $value,
        float $fallback,
        ?float $bottomFallback = null,
    ): array {
        $bottomFallback ??= $fallback;
        if (is_numeric($value)) {
            $all = max(0.0, (float) $value);

            return [
                'left' => $all,
                'top' => $all,
                'right' => $all,
                'bottom' => $all,
            ];
        }
        if (!is_array($value)) {
            return [
                'left' => $fallback,
                'top' => $fallback,
                'right' => $fallback,
                'bottom' => $bottomFallback,
            ];
        }

        $horizontal = is_numeric($value['horizontal'] ?? null)
            ? max(0.0, (float) $value['horizontal'])
            : $fallback;
        $vertical = is_numeric($value['vertical'] ?? null)
            ? max(0.0, (float) $value['vertical'])
            : $fallback;

        return [
            'left' => is_numeric($value['left'] ?? null)
                ? max(0.0, (float) $value['left'])
                : $horizontal,
            'top' => is_numeric($value['top'] ?? null)
                ? max(0.0, (float) $value['top'])
                : $vertical,
            'right' => is_numeric($value['right'] ?? null)
                ? max(0.0, (float) $value['right'])
                : $horizontal,
            'bottom' => is_numeric($value['bottom'] ?? null)
                ? max(0.0, (float) $value['bottom'])
                : (
                    array_key_exists('vertical', $value)
                        ? $vertical
                        : $bottomFallback
                ),
        ];
    }

    /** @param array<string, mixed> $props */
    private static function pressDelay(
        array $props,
        string $name,
        int $fallback,
    ): int {
        return is_numeric($props[$name] ?? null)
            ? min(60_000, max(0, (int) $props[$name]))
            : $fallback;
    }

    /** @param array<string, mixed> $props */
    private static function isClosed(array $props): bool
    {
        if (
            !array_key_exists('open', $props)
            && !array_key_exists('isOpen', $props)
            && !array_key_exists('defaultOpen', $props)
            && !array_key_exists('defaultIsOpen', $props)
        ) {
            return false;
        }

        return !self::flag(
            $props,
            'open',
            self::flag(
                $props,
                'isOpen',
                self::flag(
                    $props,
                    'defaultIsOpen',
                    self::flag($props, 'defaultOpen'),
                ),
            ),
        );
    }

    private static function hidesEntireRootWhenClosed(string $part): bool
    {
        return in_array($part, [
            'Modal',
            'Toast',
        ], true);
    }

    private static function isOverlayContent(string $part): bool
    {
        return in_array($part, [
            'BottomSheetBackdrop',
            'BottomSheetContent',
            'BottomSheetPortal',
            'ModalBackdrop',
            'ModalContent',
            'PopoverBackdrop',
            'PopoverContent',
            'Portal',
            'SelectBackdrop',
            'SelectContent',
            'SelectPortal',
            'TooltipContent',
        ], true);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function anchoredOverlayChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        $overlayPart = $part;
        if (
            !in_array($overlayPart, ['Menu', 'Popover', 'Tooltip'], true)
            || $children === []
        ) {
            return $children;
        }

        if ($overlayPart === 'Menu') {
            $hasTrigger = self::flag(
                $props,
                'hasTrigger',
                count($children) > 1
                    && $children[0]->kind() !== NodeKind::CustomView,
            );
            if (!$hasTrigger) {
                return $children;
            }

            $trigger = $children[0]->property(
                PropKey::Value,
                'pam:overlay-trigger',
            );
            $content = Column::make(...array_slice($children, 1))
                ->collapsable(false)
                ->style(new Style(
                    paddingVertical: 8.0,
                    gap: 8.0,
                ))
                ->property(PropKey::Value, 'pam:overlay-content')
                ->property(
                    PropKey::PositionType,
                    PositionType::Absolute->value,
                )
                ->property(PropKey::MinHeight, 88.0);
            return [$trigger, $content];
        }
        if ($overlayPart === 'Tooltip' && count($children) > 1) {
            $trigger = $children[0]->property(
                PropKey::Value,
                'pam:overlay-trigger',
            );
            $content = Column::make(...array_slice($children, 1))
                ->collapsable(false)
                ->style(new Style(
                    maxWidth: 320.0,
                    minHeight: 24.0,
                    paddingHorizontal: 8.0,
                    paddingVertical: 4.0,
                    backgroundColor: 0xFF1B2A3C,
                    borderRadius: 4.0,
                ))
                ->property(PropKey::Value, 'pam:overlay-content')
                ->property(
                    PropKey::PositionType,
                    PositionType::Absolute->value,
                );

            return [$trigger, $content];
        }
        if (($props['__materialComponent'] ?? null) === 'PSpeedDial') {
            $trigger = $children[0]->property(
                PropKey::Value,
                'pam:overlay-trigger',
            );
            $actions = array_map(
                static fn (Element $action): Element => $action->style(
                    new Style(
                        width: 160.0,
                        minWidth: 160.0,
                    ),
                ),
                array_slice($children, 1),
            );
            $content = Column::make(...$actions)
                ->collapsable(false)
                ->style(new Style(
                    width: 160.0,
                    minWidth: 160.0,
                    paddingVertical: 8.0,
                    gap: 8.0,
                ))
                ->property(PropKey::Value, 'pam:overlay-content')
                ->property(
                    PropKey::PositionType,
                    PositionType::Absolute->value,
                )
                ->property(PropKey::MinHeight, 144.0);

            return [$trigger, $content];
        }

        $triggerMarked = false;
        $markedChildren = [];
        foreach ($children as $child) {
            $tag = $child->properties()[PropKey::Value->value] ?? null;
            if (
                $tag === 'pam:overlay-content'
                || $tag === 'pam:overlay-backdrop'
                || (
                    is_string($tag)
                    && str_starts_with($tag, 'pam:overlay-backdrop:')
                )
            ) {
                $markedChildren[] = $child;
                continue;
            }
            if (!$triggerMarked) {
                $child = $child->property(
                    PropKey::Value,
                    'pam:overlay-trigger',
                );
                $triggerMarked = true;
            }
            $markedChildren[] = $child;
        }

        return $markedChildren;
    }

    private static function usesNativeWindow(string $part): bool
    {
        return in_array($part, [
            'BottomSheetPortal',
            'Modal',
            'Portal',
            'SelectPortal',
        ], true);
    }

    /** @param array<string, mixed> $props */
    private static function modalPresentation(
        string $part,
        array $props,
    ): ModalPresentation {
        // BottomSheetPortal and SelectPortal already host the complete sheet
        // geometry, scrim, snap points and gesture behavior inside the native
        // PAM Native UI host. The window around that host must only provide a
        // transparent full-screen portal. Making both layers a sheet creates
        // two independent rounded surfaces and two drag handles.
        if (in_array($part, ['BottomSheetPortal', 'SelectPortal'], true)) {
            return ModalPresentation::FullScreen;
        }
        if (
            ($props['__materialComponent'] ?? null) === 'PDialog'
            && self::flag($props, 'fullscreen')
        ) {
            return ModalPresentation::FullScreen;
        }
        $requested = $props['presentation'] ?? null;
        if ($requested === 1 || $requested === 'fullScreen') {
            return ModalPresentation::FullScreen;
        }
        if ($requested === 3 || $requested === 'sheet') {
            return ModalPresentation::Sheet;
        }

        return $part === 'Portal'
            ? ModalPresentation::FullScreen
            : ModalPresentation::Dialog;
    }

    /** @param array<string, mixed> $props */
    private static function modalAnimationType(
        string $part,
        array $props,
    ): ModalAnimationType {
        return match ($props['animationType'] ?? null) {
            2, 'slide' => ModalAnimationType::Slide,
            3, 'fade' => ModalAnimationType::Fade,
            default => in_array($part, ['Modal', 'Portal'], true)
                ? ModalAnimationType::Fade
                : ModalAnimationType::None,
        };
    }

    /** @param array<string, mixed> $props */
    private static function modalDismissible(array $props): bool
    {
        if (self::flag($props, 'persistent')) {
            return false;
        }

        return self::flag(
            $props,
            'dismissible',
            self::flag($props, 'isDismissable', true),
        );
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private static function materialOverlayProperties(array $props): array
    {
        if (self::flag($props, 'persistent')) {
            $props['dismissible'] = false;
            $props['isDismissable'] = false;
            $props['allowSwipeDismissal'] = false;
            $props['enablePanDownToClose'] = false;
        }

        return $props;
    }

    /** @param array<string, mixed> $props */
    private static function modalBackdropColor(array $props): int
    {
        $scrimValue = $props['scrim'] ?? null;
        if ($scrimValue === false) {
            return 0x00000000;
        }
        $scrim = self::packedColor($scrimValue);

        return $scrim
            ?? self::packedColor($props['backdropColor'] ?? null)
            ?? 0x66000000;
    }

    private static function isText(string $part): bool
    {
        if ($part === 'FormControlLabel') {
            return false;
        }

        return $part === 'Text'
            || $part === 'Heading'
            || $part === 'FormControlLabelAstrick'
            || str_ends_with($part, 'Text')
            || str_ends_with($part, 'Label')
            || str_ends_with($part, 'Title')
            || str_ends_with($part, 'Description')
            || str_ends_with($part, 'Caption')
            || in_array($part, [
                'AvatarFallback',
                'TableHead',
                'TableData',
            ], true);
    }

    private static function themedText(string $value): Text
    {
        return Text::make($value)->property(
            PropKey::TextColor,
            ThemeManager::current()->color(ColorToken::Foreground),
        );
    }

    /** @param list<Element> $children */
    private static function materialListItemLineCount(array $children): int
    {
        $textLines = 0;
        foreach ($children as $child) {
            $textLines += self::listItemTextLineCount($child);
            if ($textLines >= 3) {
                return 3;
            }
        }

        return max(1, $textLines);
    }

    private static function listItemTextLineCount(Element $element): int
    {
        if ($element->kind() === NodeKind::Text) {
            return 1;
        }
        if (!in_array(
            $element->kind(),
            [NodeKind::Column, NodeKind::Row, NodeKind::View],
            true,
        )) {
            return 0;
        }

        $count = 0;
        foreach ($element->children() as $child) {
            $count += self::listItemTextLineCount($child);
        }

        return $count;
    }

    private static function isInput(string $part): bool
    {
        return in_array($part, [
            'InputField',
            'TextareaInput',
            'DateTimePickerInput',
            'BottomSheetTextInput',
            'SelectInput',
        ], true);
    }

    private static function isImage(string $part): bool
    {
        return in_array($part, ['Image', 'AvatarImage'], true);
    }

    private static function isIcon(string $part): bool
    {
        return $part === 'Icon' || str_ends_with($part, 'Icon');
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string|int|float|bool>
     */
    private static function iconProperties(string $part, array $props): array
    {
        $requested = $props['name'] ?? $props['icon'] ?? $part;
        $materialIds = \Pam\MobileUi\Generated\MaterialComponentMap::IDS;
        $requestedName = is_string($requested)
            ? self::nativeIconName($requested)
            : null;
        $requestedIcon = $requestedName === null
            ? null
            : ($materialIds[$requestedName] ?? ComponentMap::IDS[$requestedName] ?? null);
        if (is_string($requested) && $requestedIcon === null) {
            throw new InvalidArgumentException(
                "Unknown native icon {$requested}; use a name from resources/icons.json.",
            );
        }
        $icon = $requestedIcon
            ?? $materialIds[$part]
            ?? ComponentMap::IDS[$part]
            ?? throw new InvalidArgumentException(
                "Component {$part} does not resolve to a native icon.",
            );
        return [
            'icon' => $icon,
            'color' => self::iconColor($props),
        ];
    }

    /** @param array<string, mixed> $props */
    private static function iconColor(array $props): int
    {
        $requestedColor = $props['color'] ?? $props['action'] ?? null;
        $theme = ThemeManager::current();
        $semanticColor = is_string($requestedColor)
            ? match (strtolower($requestedColor)) {
                'primary' => ColorToken::Primary,
                'secondary' => ColorToken::Secondary,
                'success' => ColorToken::Success,
                'info', 'information' => ColorToken::Info,
                'warning' => ColorToken::Warning,
                'error', 'destructive' => ColorToken::Destructive,
                default => null,
            }
            : null;

        return self::packedColor($requestedColor)
            ?? ($semanticColor === null
                ? null
                : $theme->color($semanticColor))
            ?? $theme->color(ColorToken::Foreground);
    }

    private static function applyButtonForeground(
        Element $child,
        int $foreground,
    ): Element {
        $styled = $child->style(
            new Style(textColor: $foreground, tintColor: $foreground),
        );
        $marker = $child->properties()[PropKey::Value->value] ?? null;
        if (
            !$styled instanceof CustomView
            || !is_string($marker)
            || !str_starts_with($marker, 'pam:icon:')
        ) {
            return $styled;
        }
        $icon = filter_var(
            substr($marker, strlen('pam:icon:')),
            FILTER_VALIDATE_INT,
        );
        if (!is_int($icon)) {
            return $styled;
        }

        return $styled->hostProperties([
            'icon' => $icon,
            'color' => $foreground,
        ]);
    }

    private static function applyForegroundTree(
        Element $element,
        int $foreground,
    ): Element {
        $marker = $element->properties()[PropKey::Value->value] ?? null;
        if (
            is_string($marker)
            && str_starts_with($marker, 'pam:preserve-foreground:')
        ) {
            return $element;
        }
        $styled = self::applyButtonForeground($element, $foreground);
        $children = $styled->children();
        $replaceChildren = self::retainedTreeChildrenMethod();
        if ($children === [] || !is_callable([$styled, $replaceChildren])) {
            return $styled;
        }

        // PAM Native added retained-tree replacement after the first
        // supported runtime. Older runtimes still receive the correctly
        // styled root instead of crashing while current runtimes propagate
        // the foreground through nested content.
        return $styled->{$replaceChildren}(array_map(
            static fn (Element $child): Element =>
                self::applyForegroundTree($child, $foreground),
            $children,
        ));
    }

    private static function applyInputEnabledTree(
        Element $element,
        bool $enabled,
    ): Element {
        $updated = $element->kind() === NodeKind::Input
            ? $element
                ->property(PropKey::Enabled, $enabled)
                ->property(PropKey::InputEditable, $enabled)
                ->property(PropKey::Accessible, $enabled)
            : $element;
        $children = $updated->children();
        $replaceChildren = self::retainedTreeChildrenMethod();
        if ($children === [] || !is_callable([$updated, $replaceChildren])) {
            return $updated;
        }

        return $updated->{$replaceChildren}(array_map(
            static fn (Element $child): Element =>
                self::applyInputEnabledTree($child, $enabled),
            $children,
        ));
    }

    private static function nativeIconName(string $requested): string
    {
        $normalized = strtolower(trim($requested));
        $aliases = [
            'chat' => 'MessageCircleIcon',
            'favorite' => 'FavouriteIcon',
            'format-align-center' => 'GripVerticalIcon',
            'format-align-left' => 'ChevronsLeftIcon',
            'format-align-right' => 'ChevronsRightIcon',
            'home' => 'GlobeIcon',
            'inbox' => 'MailIcon',
            'more-horiz' => 'ThreeDotsIcon',
            'more-vert' => 'ThreeDotsIcon',
            'notifications' => 'BellIcon',
            'palette' => 'SunIcon',
            'person' => 'CircleIcon',
            'schedule' => 'ClockIcon',
            'widgets' => 'GripVerticalIcon',
        ];
        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }
        if (str_ends_with($requested, 'Icon')) {
            return $requested;
        }
        $words = preg_split('/[^a-zA-Z0-9]+/', $requested) ?: [];
        $pascal = implode('', array_map(
            static fn (string $word): string => ucfirst(strtolower($word)),
            $words,
        ));

        return $pascal.'Icon';
    }

    private static function retainedTreeChildrenMethod(): string
    {
        return 'domWithChildren';
    }

    private static function isPressable(string $part): bool
    {
        return in_array($part, [
            'Button',
            'CalendarDay',
            'CalendarHeaderMonthSelect',
            'CalendarHeaderNextButton',
            'CalendarHeaderPrevButton',
            'CalendarHeaderYearSelect',
            'Fab',
            'Link',
            'Pressable',
        ], true)
            || str_ends_with($part, 'Trigger')
            || str_ends_with($part, 'CloseButton')
            || (
                str_ends_with($part, 'Item')
                && $part !== 'GridItem'
            );
    }

    private static function hasSemanticValue(string $part): bool
    {
        return $part === 'TabsTrigger'
            || in_array($part, [
                'AccordionItem',
                'Checkbox',
                'MenuItem',
                'Radio',
                'SelectItem',
            ], true);
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $parentProps
     * @return array<string, mixed>
     */
    private static function controlledItemState(
        string $part,
        array $props,
        array $parentProps,
    ): array {
        $buttonToggleItem = $part === 'PBtn'
            && ($parentProps['__pamParentComponent'] ?? null) === 'PBtnToggle';
        $buttonGroupItem = $part === 'PBtn'
            && ($parentProps['__pamParentComponent'] ?? null) === 'PBtnGroup';
        $chipGroupItem = $part === 'PChip'
            && ($parentProps['__pamParentComponent'] ?? null) === 'PChipGroup';
        if ($chipGroupItem) {
            // Android's native ChipGroup reserves its own vertical insets.
            // Keep the public 32 dp Material container after those insets are
            // applied instead of letting grouped chips collapse to ~26 dp.
            $props['__pamChipGroupItem'] = true;
            if (self::flag($parentProps, 'disabled')) {
                $props['disabled'] = true;
            }
        }
        if ($buttonToggleItem) {
            $props['__pamButtonToggleItem'] = true;
            if (self::flag($parentProps, 'disabled')) {
                $props['disabled'] = true;
            }
            foreach (['density', 'size', 'color'] as $name) {
                if (
                    !array_key_exists($name, $props)
                    && array_key_exists($name, $parentProps)
                ) {
                    $props[$name] = $parentProps[$name];
                }
            }
        }
        if ($buttonGroupItem) {
            $props['__pamButtonGroupItem'] = true;
            if (self::flag($parentProps, 'disabled')) {
                $props['disabled'] = true;
            }
            foreach (['density', 'size', 'color'] as $name) {
                if (
                    !array_key_exists($name, $props)
                    && array_key_exists($name, $parentProps)
                ) {
                    $props[$name] = $parentProps[$name];
                }
            }
        }
        $itemValue = self::semanticValue($props);
        if ($itemValue === null) {
            return $props;
        }
        if (
            in_array($part, ['Checkbox', 'Radio'], true)
            && !array_key_exists('value', $parentProps)
            && !array_key_exists('defaultValue', $parentProps)
        ) {
            return $props;
        }
        $hasControlledParent = match ($part) {
            'MenuItem' => array_key_exists('selectedKeys', $parentProps),
            'SelectItem' => array_key_exists('selectedValue', $parentProps)
                || array_key_exists('value', $parentProps),
            'AccordionItem', 'TabsTrigger' => array_key_exists('value', $parentProps)
                || array_key_exists('defaultValue', $parentProps),
            default => true,
        };
        if (!$hasControlledParent) {
            return $props;
        }

        $parentValue = match ($part) {
            'MenuItem' => $parentProps['selectedKeys'] ?? null,
            'SelectItem' => $parentProps['selectedValue'] ?? $parentProps['value'] ?? null,
            'AccordionItem' => $parentProps['value'] ?? $parentProps['defaultValue'] ?? null,
            'Checkbox', 'Radio' => $parentProps['value'] ?? $parentProps['defaultValue'] ?? null,
            default => $parentProps['modelValue']
                ?? $parentProps['value']
                ?? $parentProps['defaultValue']
                ?? null,
        };
        $selected = is_array($parentValue)
            ? in_array($itemValue, $parentValue, true)
            : self::sameScalar($parentValue, $itemValue);
        if (
            $part === 'MenuItem'
            && is_array($parentProps['disabledKeys'] ?? null)
            && in_array($itemValue, $parentProps['disabledKeys'], true)
        ) {
            $props['disabled'] = true;
        }

        return match ($part) {
            'AccordionItem' => [...$props, 'expanded' => $selected],
            'Checkbox', 'Radio', 'SelectItem' => [...$props, 'checked' => $selected],
            'MenuItem',
            'TabsTrigger' => [...$props, 'selected' => $selected],
            'PBtn' => $buttonToggleItem || $buttonGroupItem
                ? [...$props, 'selected' => $selected]
                : $props,
            'PChip' => $chipGroupItem
                ? [...$props, 'selected' => $selected]
                : $props,
            default => $props,
        };
    }

    private static function isTransparentProvider(string $part): bool
    {
        return false;
    }

    /**
     * React context providers do not create a native layout node. PAM returns
     * the only child directly and uses an Android-layout-only View as the
     * fragment carrier when a provider has multiple children.
     *
     * @param list<Element> $children
     */
    private static function transparentChildren(array $children): Element
    {
        return count($children) === 1
            ? $children[0]
            : View::make(...$children)->collapsable();
    }

    /**
     * Context-only roots create no native layout. PAM keeps triggers before
     * their window child so Android can flatten the carrier and restore focus.
     *
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function providerRootChildren(array $children): array
    {
        $triggers = [];
        $content = [];
        foreach ($children as $child) {
            if ($child->kind() === NodeKind::Modal) {
                $content[] = $child;
            } else {
                $triggers[] = $child;
            }
        }

        return [...$triggers, ...$content];
    }

    /** @param array<string, mixed> $props
     *  @return list<string|int|float|bool>
     */
    private static function menuSelectedValues(array $props): array
    {
        $selected = $props['selectedKeys'] ?? [];
        if (!is_array($selected)) {
            return is_scalar($selected) ? [$selected] : [];
        }

        return array_values(
            array_filter(
                $selected,
                static fn (mixed $item): bool => is_scalar($item),
            ),
        );
    }

    /** @param array<string, mixed> $props */
    private static function semanticValue(array $props): string|int|float|bool|null
    {
        foreach (['value', 'key', 'textValue', 'label'] as $name) {
            $value = $props[$name] ?? null;
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param Closure $handler
     * @return Closure(mixed...): void
     */
    private static function scalarSelectionHandler(
        Closure $handler,
        string|int|float|bool $value,
    ): Closure {
        return static function (mixed ...$arguments) use ($handler, $value): void {
            $handler($value);
        };
    }

    /**
     * @param Closure $handler
     * @return Closure(mixed...): void
     */
    private static function nullableScalarSelectionHandler(
        Closure $handler,
        string|int|float|bool $value,
        mixed $selected,
        bool $mandatory,
    ): Closure {
        return static function (mixed ...$arguments) use (
            $handler,
            $value,
            $selected,
            $mandatory,
        ): void {
            $active = self::sameScalar($selected, $value);
            $handler($active && !$mandatory ? null : $value);
        };
    }

    /**
     * @param Closure $handler
     * @param list<string|int|float|bool> $selected
     * @return Closure(mixed...): void
     */
    private static function listSelectionHandler(
        Closure $handler,
        string|int|float|bool $value,
        array $selected,
        bool $collapsible = true,
        bool $multiple = true,
    ): Closure {
        return static function (mixed ...$arguments) use (
            $handler,
            $value,
            $selected,
            $collapsible,
            $multiple,
        ): void {
            $active = in_array($value, $selected, true);
            $nextChecked = self::eventFlag($arguments[0] ?? null, !$active);
            if (!$multiple) {
                $next = $nextChecked
                    ? [$value]
                    : ($collapsible ? [] : $selected);
            } elseif ($nextChecked && !$active) {
                $next = [...$selected, $value];
            } elseif (!$nextChecked && $active) {
                $next = !$collapsible && count($selected) === 1
                    ? $selected
                    : array_values(
                        array_filter(
                            $selected,
                            static fn (string|int|float|bool $item): bool => $item !== $value,
                        ),
                    );
            } else {
                $next = $selected;
            }
            $handler($next);
        };
    }

    /** @param array<string, mixed> $props
     *  @return list<string|int|float|bool>
     */
    private static function selectedValues(array $props): array
    {
        $value = $props['modelValue']
            ?? $props['value']
            ?? $props['defaultValue']
            ?? [];
        if (!is_array($value)) {
            return is_scalar($value) ? [$value] : [];
        }

        return array_values(
            array_filter(
                $value,
                static fn (mixed $item): bool => is_scalar($item),
            ),
        );
    }

    private static function eventFlag(mixed $value, bool $fallback): bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => $fallback,
        };
    }

    /**
     * Applies the same event contract when a component originates from a
     * declarative `.pam` tag rather than the fluent PHP facade.
     *
     * @param array<string, mixed> $props
     */
    public static function adaptTemplateEvent(
        string $part,
        EventKind $kind,
        Closure $handler,
        array $props,
    ): Closure {
        $parent = is_array($props['__parentVariants'] ?? null)
            ? $props['__parentVariants']
            : [];
        $events = self::componentEvents(
            $part,
            [...$parent, ...$props],
            [$kind->value => $handler],
        );

        return $events[$kind->value] ?? $handler;
    }

    /**
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @return array<int, Closure>
     */
    private static function componentEvents(
        string $part,
        array $props,
        array $events,
    ): array {
        if (self::isImage($part) || $part === 'ImageBackground') {
            foreach ([
                EventKind::ImageLoadStart,
                EventKind::ImageProgress,
                EventKind::ImageLoad,
                EventKind::ImageError,
                EventKind::ImageLoadEnd,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler === null) {
                    continue;
                }
                $events[$kind->value] = match ($kind) {
                    EventKind::ImageLoadStart,
                    EventKind::ImageLoadEnd =>
                        static function (string $_payload) use ($handler): void {
                            $handler();
                        },
                    EventKind::ImageProgress =>
                        static function (string $payload) use ($handler): void {
                            $handler(ImageProgressEvent::fromPayload($payload));
                        },
                    EventKind::ImageLoad =>
                        static function (string $payload) use ($handler): void {
                            $handler(ImageLoadEvent::fromPayload($payload));
                        },
                    EventKind::ImageError =>
                        static function (string $payload) use ($handler): void {
                            $handler(ImageErrorEvent::fromPayload($payload));
                        },
                };
            }

            return $events;
        }
        if (self::isInput($part)) {
            foreach ([
                EventKind::InputSelectionChange,
                EventKind::InputContentSizeChange,
                EventKind::InputKeyPress,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler === null) {
                    continue;
                }
                $events[$kind->value] = match ($kind) {
                    EventKind::InputSelectionChange =>
                        static function (string $payload) use ($handler): void {
                            $handler(InputSelectionEvent::fromPayload($payload));
                        },
                    EventKind::InputContentSizeChange =>
                        static function (string $payload) use ($handler): void {
                            $handler(InputContentSizeEvent::fromPayload($payload));
                        },
                    EventKind::InputKeyPress =>
                        static function (string $payload) use ($handler): void {
                            $handler(InputKeyEvent::fromPayload($payload));
                        },
                };
            }

            return $events;
        }
        if (self::isPressable($part)) {
            foreach ([
                EventKind::PressIn,
                EventKind::PressOut,
                EventKind::PressMove,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler === null) {
                    continue;
                }
                $events[$kind->value] =
                    static function (string $payload) use ($handler): void {
                        $handler(PressEvent::fromPayload($payload));
                    };
            }
        }
        if (self::usesNativeWindow($part)) {
            $dismissHandler = $events[EventKind::ModalDismiss->value] ?? null;
            if ($dismissHandler !== null) {
                $nativeHandler = $events[EventKind::Native->value] ?? null;
                $events[EventKind::Native->value] =
                    static function (string $payload) use (
                        $dismissHandler,
                        $nativeHandler,
                    ): void {
                        $nativeHandler?->__invoke($payload);
                        try {
                            $event = Wire::decodeMap($payload);
                        } catch (\Throwable) {
                            return;
                        }
                        if (
                            ($event['dismissed'] ?? false) === true
                            || ($event['action'] ?? null) === 1
                        ) {
                            $dismissHandler();
                        }
                    };
            }
            foreach ([
                EventKind::ModalRequestClose,
                EventKind::ModalShow,
                EventKind::ModalDismiss,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler === null) {
                    continue;
                }
                $events[$kind->value] =
                    static function (string $_payload) use ($handler): void {
                        $handler();
                    };
            }
            $orientationHandler = $events[
                EventKind::ModalOrientationChange->value
            ] ?? null;
            if ($orientationHandler !== null) {
                $events[EventKind::ModalOrientationChange->value] =
                    static function (string $payload) use (
                        $orientationHandler,
                    ): void {
                        $orientationHandler(
                            ModalOrientation::fromPayload($payload),
                        );
                    };
            }
        }
        if (
            $part === 'PRangeSlider'
            || ($props['__materialComponent'] ?? null) === 'PRangeSlider'
        ) {
            foreach ([EventKind::Change, EventKind::Native] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler === null) {
                    continue;
                }
                $events[$kind->value] =
                    static function (string $payload) use ($handler): void {
                        $decoded = json_decode($payload, true);
                        $values = is_array($decoded)
                            ? array_values(array_filter(
                                $decoded,
                                static fn (mixed $value): bool => is_numeric($value),
                            ))
                            : [];
                        if (count($values) < 2) {
                            return;
                        }
                        $handler([
                            (float) $values[0],
                            (float) $values[1],
                        ]);
                    };
            }
        }
        if ($part !== 'Calendar') {
            return $events;
        }
        $handler = $events[EventKind::Change->value] ?? null;
        $multiple = $props['multiple'] ?? false;
        $mode = match (true) {
            $multiple === 'range' => ComponentMode::Range->value,
            self::flag($props, 'range') => ComponentMode::Range->value,
            self::flag($props, 'multiple') => ComponentMode::Multiple->value,
            default => $props['mode']
                ?? $props['type']
                ?? ComponentMode::Single->value,
        };
        $mode = is_numeric($mode) ? (int) $mode : ComponentMode::Single->value;
        if ($handler === null || $mode === 1) {
            return $events;
        }

        $events[EventKind::Change->value] = match ($mode) {
            2 => static function (string $payload) use ($handler): void {
                $values = str_starts_with($payload, "M\n")
                    ? array_values(
                        array_filter(
                            explode("\n", substr($payload, 2)),
                            static fn (string $value): bool => $value !== '',
                        ),
                    )
                    : [];
                $handler($values);
            },
            3 => static function (string $payload) use ($handler): void {
                $values = str_starts_with($payload, "R\n")
                    ? explode("\n", substr($payload, 2), 2)
                    : [];
                $handler([
                    'from' => $values[0] ?? '',
                    'to' => $values[1] ?? '',
                ]);
            },
            default => $handler,
        };

        return $events;
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function formControlChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        if (
            $part !== 'FormControlLabel'
            || !self::flag($props, 'required', self::flag($props, 'isRequired'))
            || self::taggedElement($children, 'pam:form-required') !== null
        ) {
            return $children;
        }

        $asterisk = Text::make('*')
            ->style(
                StyleResolver::resolve(
                    'FormControlLabelAstrick',
                    $props,
                    ThemeManager::current(),
                ),
            )
            ->property(PropKey::Value, 'pam:form-required');

        return [...$children, $asterisk];
    }

    /**
     * A callback authored on the compound root belongs to its native EditText.
     *
     * @param array<int, Closure> $events
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function inputEventChildren(
        string $part,
        array $events,
        array $children,
    ): array {
        if (!in_array($part, ['Input', 'Textarea'], true) || $events === []) {
            return $children;
        }

        $eventChildren = [];
        $inputDecorated = false;
        foreach ($children as $child) {
            if ($inputDecorated || $child->kind() !== NodeKind::Input) {
                $eventChildren[] = $child;
                continue;
            }
            foreach ([
                EventKind::Change,
                EventKind::Focus,
                EventKind::Blur,
                EventKind::Submit,
                EventKind::InputEndEditing,
                EventKind::InputSelectionChange,
                EventKind::InputContentSizeChange,
                EventKind::InputKeyPress,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler !== null) {
                    $child = $child->on($kind, $handler);
                }
            }
            $eventChildren[] = $child;
            $inputDecorated = true;
        }

        return $eventChildren;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string|int|float|bool>
     */
    private static function inputNativeProperties(
        string $part,
        NativeBehavior $behavior,
        array $props,
    ): array {
        if ($behavior === NativeBehavior::InputSlot) {
            $action = $props['slotAction'] ?? $props['action'] ?? null;
            $slotAction = match ($action) {
                InputSlotAction::Focus->value, 'focus' => InputSlotAction::Focus,
                InputSlotAction::Clear->value, 'clear' => InputSlotAction::Clear,
                InputSlotAction::TogglePassword->value,
                'toggle-password',
                'togglePassword' => InputSlotAction::TogglePassword,
                InputSlotAction::None->value, 'none' => InputSlotAction::None,
                default => self::flag($props, 'focusOnPress', true)
                    ? InputSlotAction::Focus
                    : InputSlotAction::None,
            };

            return [
                'slotAction' => $slotAction->value,
                'focusOnPress' => self::flag($props, 'focusOnPress', true),
            ];
        }

        $variant = $props['variant'] ?? null;
        $materialIndicatorOnly = is_string($props['__materialComponent'] ?? null)
            && !in_array($variant, [
                'outlined', 'outline', MaterialVariant::Outlined->value,
                'plain', MaterialVariant::Plain->value,
                'solo', MaterialVariant::Solo->value,
                'solo-inverted', MaterialVariant::SoloInverted->value,
                'solo-filled', MaterialVariant::SoloFilled->value,
            ], true);

        $readOnly = self::flag(
            $props,
            'readonly',
            self::flag($props, 'readOnly', self::flag($props, 'isReadOnly')),
        ) || (
            array_key_exists('editable', $props)
            && !self::flag($props, 'editable', true)
        );

        return [
            'disabled' => self::flag(
                $props,
                'disabled',
                self::flag($props, 'isDisabled'),
            ),
            'readOnly' => $readOnly,
            'invalid' => self::flag(
                $props,
                'invalid',
                self::flag($props, 'isInvalid'),
            ),
            'required' => self::flag(
                $props,
                'required',
                self::flag($props, 'isRequired'),
            ),
            'focusColor' => self::packedColor($props['focusColor'] ?? null)
                ?? ThemeManager::current()->color(ColorToken::Ring),
            'invalidColor' => self::packedColor($props['invalidColor'] ?? null)
                ?? ThemeManager::current()->color(ColorToken::Destructive),
            'outlineRadius' => is_numeric($props['outlineRadius'] ?? null)
                ? (float) $props['outlineRadius']
                : ($part === 'Textarea' ? 4.0 : 6.0),
            'outlineWidth' => is_numeric($props['outlineWidth'] ?? null)
                ? max(0.0, (float) $props['outlineWidth'])
                : 1.0,
            'indicatorOnly' => $materialIndicatorOnly,
        ];
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return array<string, string|int|float|bool>
     */
    private static function rangeNativeProperties(
        NativeBehavior $behavior,
        array $props,
        array $children,
        ?int $nativeBackground,
    ): array {
        $values = [];

        if ($behavior === NativeBehavior::SwitchControl) {
            $material = is_string($props['__materialComponent'] ?? null);
            $theme = ThemeManager::current();
            $semanticTrackColor = $material
                ? MaterialStyleResolver::semanticBackground($props, $theme)
                : null;
            $semanticThumbColor = $material
                ? MaterialStyleResolver::semanticForeground($props, $theme)
                : null;
            $trackColors = is_array($props['trackColor'] ?? null)
                ? $props['trackColor']
                : [];
            $offColor = self::packedColor(
                $trackColors['false']
                    ?? $trackColors[0]
                    ?? $props['ios_backgroundColor']
                    ?? ($material ? $theme->color(ColorToken::SurfaceSunken) : null),
            );
            $outlineColor = self::packedColor(
                $props['trackOutlineColor']
                    ?? ($material ? $theme->color(ColorToken::Outline) : null),
            );
            $onColor = self::packedColor(
                $trackColors['true']
                    ?? $trackColors[1]
                    ?? $semanticTrackColor
                    ?? ($material ? $theme->color(ColorToken::Primary) : null),
            );
            $thumbColor = self::packedColor(
                $props['thumbColor']
                    ?? ($material ? $theme->color(ColorToken::MutedForeground) : null),
            );
            $activeThumbColor = self::packedColor(
                $props['activeThumbColor']
                    ?? $props['thumbColor']
                    ?? $semanticThumbColor
                    ?? ($material ? $theme->color(ColorToken::PrimaryForeground) : null),
            );
            if ($offColor !== null) {
                $values['trackOffColor'] = $offColor;
            }
            if ($onColor !== null) {
                $values['trackOnColor'] = $onColor;
            }
            if ($outlineColor !== null) {
                $values['trackOutlineColor'] = $outlineColor;
            }
            if ($thumbColor !== null) {
                $values['thumbColor'] = $thumbColor;
            }
            if ($activeThumbColor !== null) {
                $values['activeThumbColor'] = $activeThumbColor;
            }

            return $values;
        }

        $modelValue = $props['modelValue']
            ?? $props['value']
            ?? $props['defaultValue']
            ?? null;
        if (is_numeric($modelValue)) {
            $values['value'] = (float) $modelValue;
        } elseif (is_array($modelValue)) {
            $range = self::normalizedRangeModel($props, $modelValue);
            if ($range !== null) {
                $values['range'] = true;
                $values['lowerValue'] = $range['lower'];
                $values['upperValue'] = $range['upper'];
                $values['value'] = $range['upper'];
            }
        }

        $minimum = $props['min'] ?? $props['minValue'] ?? null;
        $maximum = $props['max'] ?? $props['maxValue'] ?? null;
        if (is_numeric($minimum)) {
            $values['min'] = (float) $minimum;
        }
        if (is_numeric($maximum)) {
            $values['max'] = (float) $maximum;
        }
        if (is_numeric($props['step'] ?? null)) {
            $values['step'] = max(0.000001, (float) $props['step']);
        }
        if (($props['__materialComponent'] ?? null) === 'PRating') {
            $values['min'] = 0.0;
            $values['max'] = (float) min(
                20,
                max(1, self::integer($props, 'length', 5)),
            );
            $values['step'] = self::flag($props, 'halfIncrements')
                ? 0.5
                : max(0.000001, (float) ($values['step'] ?? 1.0));
        }

        $vertical = self::flag($props, 'vertical')
            || ($props['direction'] ?? null) === 'vertical'
            || ($props['orientation'] ?? null) === 2
            || ($props['orientation'] ?? null) === 'vertical';
        $values['orientation'] = $vertical ? 2 : 1;
        $values['reversed'] = self::flag(
            $props,
            'reverse',
            self::flag($props, 'reversed', self::flag($props, 'isReversed')),
        );
        $values['readOnly'] = self::flag(
            $props,
            'readonly',
            self::flag($props, 'readOnly', self::flag($props, 'isReadOnly')),
        );
        $ticks = $props['ticks']
            ?? $props['showTicks']
            ?? false;
        $values['showTicks'] = $ticks === true || $ticks === 'always';
        $values['alwaysShowTicks'] = $ticks === 'always'
            || self::flag($props, 'alwaysShowTicks');
        $thumbLabel = $props['thumbLabel']
            ?? $props['showThumbLabel']
            ?? false;
        $values['showThumbLabel'] = $thumbLabel === true
            || $thumbLabel === 'always';
        $values['alwaysShowThumbLabel'] = $thumbLabel === 'always'
            || self::flag($props, 'alwaysShowThumbLabel');
        $tickLabels = $props['tickLabels'] ?? null;
        if (is_array($tickLabels)) {
            $normalizedTickLabels = array_values(array_map(
                static fn (mixed $label): string => is_scalar($label)
                    ? (string) $label
                    : '',
                array_slice($tickLabels, 0, 101),
            ));
            if ($normalizedTickLabels !== []) {
                $encodedTickLabels = json_encode(
                    $normalizedTickLabels,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );
                if (is_string($encodedTickLabels)) {
                    $values['tickLabels'] = $encodedTickLabels;
                    $values['showTicks'] = true;
                    $values['alwaysShowTicks'] = true;
                }
            }
        }

        if ($behavior === NativeBehavior::Progress) {
            $values['thickness'] = is_numeric($props['thickness'] ?? null)
                ? max(1.0, (float) $props['thickness'])
                : MaterialTokens::PROGRESS_TRACK_THICKNESS;
            if ($nativeBackground !== null) {
                $values['trackColor'] = $nativeBackground;
            }
            $filled = self::taggedElement($children, 'pam:progress-filled-track');
            $fillColor = self::elementColor($filled);
            if ($fillColor !== null) {
                $values['fillColor'] = $fillColor;
            }

            return $values;
        }

        $track = self::taggedElement($children, 'pam:slider-track');
        $filled = self::taggedElement($children, 'pam:slider-filled-track');
        $thumb = self::taggedElement($children, 'pam:slider-thumb');
        $trackColor = self::elementColor($track);
        $fillColor = self::elementColor($filled);
        $thumbColor = self::elementColor($thumb);
        if ($trackColor !== null) {
            $values['trackColor'] = $trackColor;
        }
        if ($fillColor !== null) {
            $values['fillColor'] = $fillColor;
        }
        if ($thumbColor !== null) {
            $values['thumbColor'] = $thumbColor;
        }
        if (is_string($props['__materialComponent'] ?? null)) {
            $theme = ThemeManager::current();
            $values['activeTickColor'] = $theme->color(ColorToken::PrimaryForeground);
            $values['inactiveTickColor'] = $theme->color(ColorToken::OnSurface);
            $values['thumbLabelTextColor'] = $theme->color(ColorToken::PrimaryForeground);
            $values['tickLabelColor'] = $theme->color(ColorToken::MutedForeground);
            $values['thumbTrackGap'] = MaterialTokens::SLIDER_HANDLE_TRACK_GAP;
            $values['stateLayerSize'] = MaterialTokens::SLIDER_STATE_LAYER_SIZE;
            $values['stopIndicatorSize'] = MaterialTokens::SLIDER_STOP_INDICATOR_SIZE;
            $values['tickSize'] = MaterialTokens::SLIDER_TICK_SIZE;
        }

        $orientation = $values['orientation'];
        $trackDimension = $orientation === 2
            ? PropKey::Width
            : PropKey::Height;
        $trackThickness = is_numeric($props['sliderTrackHeight'] ?? null)
            ? (float) $props['sliderTrackHeight']
            : self::elementNumber($track, $trackDimension);
        $authoredThumbSize = is_numeric($props['thumbSize'] ?? null)
            ? (float) $props['thumbSize']
            : null;
        $thumbWidth = $authoredThumbSize
            ?? self::elementNumber($thumb, PropKey::Width);
        $thumbHeight = $authoredThumbSize
            ?? self::elementNumber($thumb, PropKey::Height);
        if (is_string($props['__materialComponent'] ?? null)) {
            $trackThickness ??= MaterialTokens::SLIDER_TRACK_HEIGHT;
            $thumbWidth ??= $orientation === 2
                ? MaterialTokens::SLIDER_HANDLE_HEIGHT
                : MaterialTokens::SLIDER_HANDLE_WIDTH;
            $thumbHeight ??= $orientation === 2
                ? MaterialTokens::SLIDER_HANDLE_WIDTH
                : MaterialTokens::SLIDER_HANDLE_HEIGHT;
        }
        if ($trackThickness !== null) {
            $values['trackThickness'] = $trackThickness;
        }
        if ($thumbWidth !== null && $thumbHeight !== null) {
            $values['thumbWidth'] = $thumbWidth;
            $values['thumbHeight'] = $thumbHeight;
        }

        return $values;
    }

    /**
     * @param list<Element> $children
     */
    private static function taggedElement(
        array $children,
        string $tag,
    ): ?Element {
        foreach ($children as $child) {
            if (($child->properties()[PropKey::Value->value] ?? null) === $tag) {
                return $child;
            }
            $match = self::taggedElement($child->children(), $tag);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    private static function elementColor(?Element $element): ?int
    {
        $color = $element?->properties()[PropKey::BackgroundColor->value] ?? null;

        return is_int($color) ? $color : null;
    }

    private static function elementNumber(
        ?Element $element,
        PropKey $property,
    ): ?float {
        $value = $element?->properties()[$property->value] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private static function packedColor(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        if (strtolower($value) === 'transparent') {
            return 0x00000000;
        }
        if (
            preg_match(
                '/^rgba?\(\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)(?:\s*,\s*(\d+(?:\.\d+)?%?))?\s*\)$/Di',
                $value,
                $channels,
            ) === 1
        ) {
            $red = min(255, max(0, (int) round((float) $channels[1])));
            $green = min(255, max(0, (int) round((float) $channels[2])));
            $blue = min(255, max(0, (int) round((float) $channels[3])));
            $alphaValue = $channels[4] ?? '1';
            $alpha = str_ends_with($alphaValue, '%')
                ? (int) round(
                    min(100.0, max(0.0, (float) substr($alphaValue, 0, -1)))
                    * 2.55,
                )
                : (int) round(
                    min(1.0, max(0.0, (float) $alphaValue)) * 255.0,
                );

            return ($alpha << 24) | ($red << 16) | ($green << 8) | $blue;
        }
        if (preg_match('/^#([0-9a-f]{3,8})$/Di', $value, $match) !== 1) {
            return null;
        }

        $hex = strtolower($match[1]);
        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $hex = implode(
                '',
                array_map(
                    static fn (string $channel): string => $channel.$channel,
                    str_split($hex),
                ),
            );
        }
        if (strlen($hex) === 6) {
            return 0xff000000 | hexdec($hex);
        }
        if (strlen($hex) !== 8) {
            return null;
        }

        $redGreenBlue = substr($hex, 0, 6);
        $alpha = substr($hex, 6, 2);

        return (hexdec($alpha) << 24) | hexdec($redGreenBlue);
    }

    /** @param array<string, mixed> $props */
    private static function imageFit(array $props): ImageFit
    {
        if (array_key_exists('cover', $props)) {
            return self::flag($props, 'cover')
                ? ImageFit::Cover
                : ImageFit::Contain;
        }

        return match (
            $props['resizeMode']
                ?? $props['fit']
                ?? $props['objectFit']
                ?? null
        ) {
            ImageFit::Contain->value, 'contain' => ImageFit::Contain,
            ImageFit::Fill->value, 'fill', 'stretch' => ImageFit::Fill,
            ImageFit::Center->value, 'center', 'none' => ImageFit::Center,
            ImageFit::Repeat->value, 'repeat' => ImageFit::Repeat,
            default => ImageFit::Cover,
        };
    }

    /** @param array<string, mixed> $props */
    private static function imageSource(array $props): string
    {
        $source = $props['src'] ?? $props['source'] ?? '';
        return self::imageSourceValue($source);
    }

    private static function imageSourceValue(mixed $source): string
    {
        if (is_scalar($source)) {
            return (string) $source;
        }
        if (!is_array($source)) {
            return '';
        }
        $uri = $source['uri'] ?? null;
        if (is_scalar($uri)) {
            return (string) $uri;
        }
        foreach ($source as $candidate) {
            if (!is_array($candidate) || !is_scalar($candidate['uri'] ?? null)) {
                continue;
            }

            return (string) $candidate['uri'];
        }

        return '';
    }

    /** @param array<string, mixed> $props */
    private static function imageSourceSet(array $props): ?string
    {
        if (is_scalar($props['srcSet'] ?? null)) {
            $value = trim((string) $props['srcSet']);

            return $value === '' ? null : $value;
        }
        $source = $props['source'] ?? null;
        if (!is_array($source) || array_key_exists('uri', $source)) {
            return null;
        }
        $candidates = [];
        foreach ($source as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $uri = self::imageSourceValue($candidate);
            if ($uri === '' || str_contains($uri, ',')) {
                continue;
            }
            if (is_numeric($candidate['scale'] ?? null)) {
                $descriptor = max(0.1, (float) $candidate['scale']).'x';
            } elseif (is_numeric($candidate['width'] ?? null)) {
                $descriptor = max(1, (int) $candidate['width']).'w';
            } else {
                continue;
            }
            $candidates[] = $uri.' '.$descriptor;
        }

        return $candidates === [] ? null : implode(', ', $candidates);
    }

    /** @param array<string, mixed> $props */
    private static function imageCachePolicy(
        array $props,
    ): ImageCachePolicy {
        $source = $props['source'] ?? null;
        $value = $props['cachePolicy']
            ?? $props['cache']
            ?? (is_array($source) ? ($source['cache'] ?? null) : null);

        return match ($value) {
            ImageCachePolicy::Reload->value, 'reload' =>
                ImageCachePolicy::Reload,
            ImageCachePolicy::ForceCache->value, 'force-cache', 'forceCache' =>
                ImageCachePolicy::ForceCache,
            ImageCachePolicy::OnlyIfCached->value,
            'only-if-cached',
            'onlyIfCached' => ImageCachePolicy::OnlyIfCached,
            default => ImageCachePolicy::Default,
        };
    }

    /** @param array<string, mixed> $props */
    private static function imageResizeMethod(
        array $props,
    ): ImageResizeMethod {
        return match ($props['resizeMethod'] ?? null) {
            ImageResizeMethod::Resize->value, 'resize' =>
                ImageResizeMethod::Resize,
            ImageResizeMethod::Scale->value, 'scale' =>
                ImageResizeMethod::Scale,
            ImageResizeMethod::None->value, 'none' =>
                ImageResizeMethod::None,
            default => ImageResizeMethod::Auto,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string>
     */
    private static function imageHeaders(array $props): array
    {
        $source = $props['source'] ?? null;
        $headers = $props['headers']
            ?? (is_array($source) ? ($source['headers'] ?? null) : null);
        if (!is_array($headers)) {
            return [];
        }
        $result = [];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $result[$name] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * @param Image|ImageBackground $image
     * @param array<string, mixed> $props
     * @return Image|ImageBackground
     */
    private static function configuredImage(
        Image|ImageBackground $image,
        array $props,
    ): Image|ImageBackground {
        $tint = self::packedColor($props['tintColor'] ?? null);
        if ($tint !== null) {
            $image = $image->tint($tint);
        }
        $defaultSource = self::imageSourceValue(
            $props['defaultSource'] ?? '',
        );
        if ($defaultSource !== '') {
            $image = $image->defaultSource($defaultSource);
        }
        $loadingSource = self::imageSourceValue(
            $props['loadingIndicatorSource'] ?? '',
        );
        if ($loadingSource !== '') {
            $image = $image->loadingIndicatorSource($loadingSource);
        }
        if (is_numeric($props['fadeDuration'] ?? null)) {
            $image = $image->fadeDuration(
                max(0, (int) $props['fadeDuration']),
            );
        }
        $image = $image
            ->resizeMethod(self::imageResizeMethod($props))
            ->resizeMultiplier(
                is_numeric($props['resizeMultiplier'] ?? null)
                    ? (float) $props['resizeMultiplier']
                    : 1.0,
            )
            ->progressiveRendering(
                self::flag($props, 'progressiveRenderingEnabled'),
            )
            ->cache(self::imageCachePolicy($props));
        $overlay = self::packedColor($props['overlayColor'] ?? null);
        if ($overlay !== null) {
            $image = $image->overlayColor($overlay);
        }
        $sourceSet = self::imageSourceSet($props);
        if ($sourceSet !== null) {
            $image = $image->sourceSet($sourceSet);
        }
        $headers = self::imageHeaders($props);
        if ($headers !== []) {
            $image = $image->headers($headers);
        }

        return $image;
    }

    /** @param array<string, mixed> $props */
    private static function keyboardAvoidingBehavior(
        array $props,
    ): KeyboardAvoidingBehavior {
        return match ($props['behavior'] ?? null) {
            KeyboardAvoidingBehavior::Pan->value,
            'pan',
            'position' => KeyboardAvoidingBehavior::Pan,
            KeyboardAvoidingBehavior::Padding->value,
            'padding' => KeyboardAvoidingBehavior::Padding,
            default => KeyboardAvoidingBehavior::Resize,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @return array{top: bool, right: bool, bottom: bool, left: bool}
     */
    private static function safeAreaEdges(array $props): array
    {
        $value = $props['edges'] ?? null;
        if ($value === null) {
            return [
                'top' => true,
                'right' => true,
                'bottom' => true,
                'left' => true,
            ];
        }
        if (is_string($value)) {
            $value = preg_split('/[\s,;|]+/', strtolower($value)) ?: [];
        }
        if (!is_array($value)) {
            return [
                'top' => true,
                'right' => true,
                'bottom' => true,
                'left' => true,
            ];
        }

        $associative = array_filter(
            array_keys($value),
            static fn (int|string $key): bool => is_string($key),
        ) !== [];
        if ($associative) {
            return [
                'top' => self::booleanValue($value['top'] ?? false) ?? false,
                'right' => self::booleanValue($value['right'] ?? false) ?? false,
                'bottom' => self::booleanValue($value['bottom'] ?? false) ?? false,
                'left' => self::booleanValue($value['left'] ?? false) ?? false,
            ];
        }

        $edges = array_map(
            static fn (mixed $edge): string => is_string($edge)
                ? strtolower($edge)
                : '',
            $value,
        );
        $all = in_array('all', $edges, true);

        return [
            'top' => $all || in_array('top', $edges, true),
            'right' => $all || in_array('right', $edges, true),
            'bottom' => $all || in_array('bottom', $edges, true),
            'left' => $all || in_array('left', $edges, true),
        ];
    }

    /** @param array<string, mixed> $props */
    private static function safeAreaMode(array $props): SafeAreaMode
    {
        return match ($props['mode'] ?? null) {
            SafeAreaMode::Margin->value, 'margin' => SafeAreaMode::Margin,
            default => SafeAreaMode::Padding,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @return list<int>
     */
    private static function refreshColors(array $props): array
    {
        $value = $props['colors'] ?? null;
        $values = is_array($value)
            ? $value
            : ($value === null ? [] : [$value]);
        if ($values === []) {
            $fallback = $props['tintColor'] ?? $props['color'] ?? null;
            if ($fallback !== null) {
                $values = [$fallback];
            }
        }

        $colors = [];
        foreach ($values as $candidate) {
            $color = self::packedColor($candidate);
            if ($color !== null) {
                $colors[] = $color;
            }
        }

        return $colors;
    }

    /** @param array<string, mixed> $props */
    private static function refreshIndicatorSize(
        array $props,
    ): RefreshIndicatorSize {
        return match ($props['size'] ?? null) {
            0, RefreshIndicatorSize::Large->value, 'large' =>
                RefreshIndicatorSize::Large,
            default => RefreshIndicatorSize::Default,
        };
    }

    /** @param array<string, mixed> $props */
    private static function activityIndicatorSize(array $props): float
    {
        $value = $props['size'] ?? 'small';

        return match ($value) {
            'large' => 36.0,
            'small' => 20.0,
            default => is_numeric($value)
                ? max(1.0, (float) $value)
                : 20.0,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @return array{float, float}
     */
    private static function scrollOffsets(
        array $props,
        bool $horizontal,
    ): array {
        $offset = $props['contentOffset'] ?? 0.0;
        $x = 0.0;
        $y = 0.0;
        if (is_array($offset)) {
            $x = is_numeric($offset['x'] ?? null)
                ? max(0.0, (float) $offset['x'])
                : 0.0;
            $y = is_numeric($offset['y'] ?? null)
                ? max(0.0, (float) $offset['y'])
                : 0.0;
        } elseif (is_numeric($offset)) {
            if ($horizontal) {
                $x = max(0.0, (float) $offset);
            } else {
                $y = max(0.0, (float) $offset);
            }
        }
        if (is_numeric($props['contentOffsetX'] ?? null)) {
            $x = max(0.0, (float) $props['contentOffsetX']);
        }
        if (is_numeric($props['contentOffsetY'] ?? null)) {
            $y = max(0.0, (float) $props['contentOffsetY']);
        }

        return [$x, $y];
    }

    /** @param array<string, mixed> $props */
    private static function scrollOverScrollMode(
        array $props,
    ): ScrollOverScrollMode {
        return match ($props['overScrollMode'] ?? null) {
            'always', ScrollOverScrollMode::Always->value =>
                ScrollOverScrollMode::Always,
            'never', ScrollOverScrollMode::Never->value =>
                ScrollOverScrollMode::Never,
            default => ScrollOverScrollMode::Auto,
        };
    }

    /** @param array<string, mixed> $props */
    private static function scrollKeyboardDismissMode(
        array $props,
    ): ScrollKeyboardDismissMode {
        return match ($props['keyboardDismissMode'] ?? null) {
            'on-drag', ScrollKeyboardDismissMode::OnDrag->value =>
                ScrollKeyboardDismissMode::OnDrag,
            'interactive', ScrollKeyboardDismissMode::Interactive->value =>
                ScrollKeyboardDismissMode::Interactive,
            default => ScrollKeyboardDismissMode::None,
        };
    }

    /** @param array<string, mixed> $props */
    private static function scrollDecelerationRate(array $props): float
    {
        $value = $props['decelerationRate'] ?? 0.985;

        return match ($value) {
            'fast' => 0.9,
            'normal' => 0.985,
            default => is_numeric($value)
                ? min(1.0, max(0.0, (float) $value))
                : 0.985,
        };
    }

    /** @param array<string, mixed> $props */
    private static function statusBarAppearance(
        array $props,
    ): StatusBarAppearance {
        return match ($props['barStyle'] ?? $props['appearance'] ?? null) {
            StatusBarAppearance::Light->value,
            'light',
            'light-content' => StatusBarAppearance::Light,
            default => StatusBarAppearance::Dark,
        };
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     */
    private static function horizontalScroll(
        array $props,
        array $children,
    ): Element {
        return self::configuredScroll($props, $children, true);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     */
    private static function configuredScroll(
        array $props,
        array $children,
        bool $horizontal,
    ): Scroll {
        $content = $horizontal
            ? Row::make(...$children)
            : self::oneChild($children);
        $indicator = $horizontal
            ? self::flag(
                $props,
                'showsHorizontalScrollIndicator',
                self::flag(
                    $props,
                    'showsScrollIndicator',
                    true,
                ),
            )
            : self::flag(
                $props,
                'showsVerticalScrollIndicator',
                self::flag(
                    $props,
                    'showsScrollIndicator',
                    true,
                ),
            );
        [$offsetX, $offsetY] = self::scrollOffsets($props, $horizontal);
        $scroll = Scroll::make($content)
            ->horizontal($horizontal)
            ->scrollEnabled(self::flag($props, 'scrollEnabled', true))
            ->showsIndicator($indicator)
            ->fillViewport(self::flag($props, 'fillViewport', true))
            ->nestedScrollEnabled(
                self::flag($props, 'nestedScrollEnabled', true),
            )
            ->overScrollMode(self::scrollOverScrollMode($props))
            ->persistentScrollbar(
                self::flag($props, 'persistentScrollbar'),
            )
            ->pagingEnabled(self::flag($props, 'pagingEnabled'))
            ->decelerationRate(self::scrollDecelerationRate($props))
            ->keyboardDismissMode(self::scrollKeyboardDismissMode($props))
            ->contentOffset($offsetX, $offsetY);

        if (is_numeric($props['fadingEdgeLength'] ?? null)) {
            $scroll = $scroll->fadingEdgeLength(
                max(0.0, (float) $props['fadingEdgeLength']),
            );
        }
        if (is_numeric($props['snapToInterval'] ?? null)) {
            $scroll = $scroll->snapToInterval(
                max(0.0, (float) $props['snapToInterval']),
            );
        }

        return $scroll;
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     */
    private static function grid(array $props, array $children): Element
    {
        $columns = self::responsiveGridValues(
            $props,
            ['columns', 'numColumns', 'gridColumns'],
            'grid-cols',
            12,
        );
        $columnGaps = self::responsiveGridGaps($props, 'x');
        $rowGaps = self::responsiveGridGaps($props, 'y');
        $direction = max(
            1,
            min(4, self::integer($props, 'flexDirection', 2)),
        );
        $grid = CustomView::make(
            'pam.mobile_ui.grid',
            [
                'columns' => implode(',', $columns),
                'columnGaps' => implode(',', $columnGaps),
                'rowGaps' => implode(',', $rowGaps),
                'direction' => $direction,
            ],
            ...$children,
        );
        if ($children === []) {
            return $grid->property(PropKey::Height, 0.0);
        }

        return $grid->property(
            PropKey::MinHeight,
            self::gridMinimumHeight(
                $children,
                $columns,
                $rowGaps,
                $direction,
            ),
        );
    }

    /** @param array<string, mixed> $props */
    private static function gridItemTag(array $props): string
    {
        return 'pam:grid-item:'.implode(
            ',',
            self::responsiveGridValues(
                $props,
                ['colSpan', 'span'],
                'col-span',
                1,
            ),
        );
    }

    /**
     * @param array<string, mixed> $props
     * @param list<string> $propertyNames
     * @return list<int>
     */
    private static function responsiveGridValues(
        array $props,
        array $propertyNames,
        string $classPrefix,
        int $default,
    ): array {
        $breakpoints = ['default', 'sm', 'md', 'lg', 'xl', '2xl'];
        /** @var array<string, int|null> $values */
        $values = array_fill_keys($breakpoints, null);
        $values['default'] = $default;
        $part = $classPrefix === 'grid-cols' ? 'Grid' : 'GridItem';
        $pattern = '/^(?:(sm|md|lg|xl|2xl):)?'
            .preg_quote($classPrefix, '/')
            .'-(\d+)$/';
        foreach (StyleRecipeResolver::classes($part, $props) ?? [] as $group) {
            foreach (preg_split('/\s+/', trim($group)) ?: [] as $token) {
                if (preg_match($pattern, $token, $match) !== 1) {
                    continue;
                }
                $breakpoint = ($match[1] ?? '') === ''
                    ? 'default'
                    : $match[1];
                $values[$breakpoint] = max(
                    1,
                    min(64, (int) $match[2]),
                );
            }
        }
        foreach ($propertyNames as $propertyName) {
            if (!array_key_exists($propertyName, $props)) {
                continue;
            }
            self::applyResponsiveGridProperty(
                $values,
                $breakpoints,
                $props[$propertyName],
                static fn (mixed $value): ?int => is_numeric($value)
                    ? max(1, min(64, (int) $value))
                    : null,
            );
            break;
        }

        return self::cascadeResponsiveIntegers(
            $values,
            $breakpoints,
            $default,
        );
    }

    /**
     * @param array<string, mixed> $props
     * @return list<float>
     */
    private static function responsiveGridGaps(
        array $props,
        string $axis,
    ): array {
        $breakpoints = ['default', 'sm', 'md', 'lg', 'xl', '2xl'];
        /** @var array<string, float|null> $values */
        $values = array_fill_keys($breakpoints, null);
        $values['default'] = 0.0;
        $pattern = '/^(?:(sm|md|lg|xl|2xl):)?gap(?:-(x|y))?'
            .'-(px|\d+(?:\.\d+)?)$/';
        foreach (StyleRecipeResolver::classes('Grid', $props) ?? [] as $group) {
            foreach (preg_split('/\s+/', trim($group)) ?: [] as $token) {
                if (preg_match($pattern, $token, $match) !== 1) {
                    continue;
                }
                $tokenAxis = $match[2];
                if ($tokenAxis !== '' && $tokenAxis !== $axis) {
                    continue;
                }
                $breakpoint = $match[1] === ''
                    ? 'default'
                    : $match[1];
                $values[$breakpoint] = $match[3] === 'px'
                    ? 1.0
                    : max(0.0, (float) $match[3] * 4.0);
            }
        }
        $specific = $axis === 'x' ? 'columnGap' : 'rowGap';
        foreach (['gap', $specific] as $propertyName) {
            if (!array_key_exists($propertyName, $props)) {
                continue;
            }
            self::applyResponsiveGridProperty(
                $values,
                $breakpoints,
                $props[$propertyName],
                static fn (mixed $value): ?float => is_numeric($value)
                    ? max(0.0, (float) $value)
                    : null,
            );
        }
        $resolved = [];
        $current = 0.0;
        foreach ($breakpoints as $breakpoint) {
            $current = $values[$breakpoint] ?? $current;
            $resolved[] = $current;
        }

        return $resolved;
    }

    /**
     * @template TValue of int|float
     * @param array<string, TValue|null> $target
     * @param list<string> $breakpoints
     * @param Closure(mixed): (TValue|null) $normalize
     */
    private static function applyResponsiveGridProperty(
        array &$target,
        array $breakpoints,
        mixed $property,
        Closure $normalize,
    ): void {
        if (!is_array($property)) {
            $normalized = $normalize($property);
            if ($normalized !== null) {
                $target['default'] = $normalized;
            }

            return;
        }
        foreach ($breakpoints as $index => $breakpoint) {
            $normalized = $normalize(
                $property[$breakpoint] ?? $property[$index] ?? null,
            );
            if ($normalized !== null) {
                $target[$breakpoint] = $normalized;
            }
        }
    }

    /**
     * @param array<string, int|null> $values
     * @param list<string> $breakpoints
     * @return list<int>
     */
    private static function cascadeResponsiveIntegers(
        array $values,
        array $breakpoints,
        int $default,
    ): array {
        $resolved = [];
        $current = $default;
        foreach ($breakpoints as $breakpoint) {
            $current = $values[$breakpoint] ?? $current;
            $resolved[] = $current;
        }

        return $resolved;
    }

    /**
     * @param list<Element> $children
     * @param list<int> $columns
     * @param list<float> $rowGaps
     */
    private static function gridMinimumHeight(
        array $children,
        array $columns,
        array $rowGaps,
        int $direction,
    ): float {
        if (in_array($direction, [1, 3], true)) {
            return array_sum(
                array_map(
                    static fn (Element $child): float =>
                        self::gridChildHeight($child),
                    $children,
                ),
            ) + ($rowGaps[0] ?? 0.0) * max(0, count($children) - 1)
                + 12.0 * count($children);
        }
        $maximum = 0.0;
        foreach ($columns as $breakpoint => $columnCount) {
            $rowSpan = 0;
            $rowHeight = 0.0;
            $height = 0.0;
            $rows = 0;
            foreach ($children as $child) {
                $span = min(
                    $columnCount,
                    self::gridChildSpans($child)[$breakpoint] ?? 1,
                );
                if ($rowSpan > 0 && $rowSpan + $span > $columnCount) {
                    $height += $rowHeight;
                    $rows++;
                    $rowSpan = 0;
                    $rowHeight = 0.0;
                }
                $rowSpan += $span;
                $rowHeight = max($rowHeight, self::gridChildHeight($child));
                if ($rowSpan >= $columnCount) {
                    $height += $rowHeight;
                    $rows++;
                    $rowSpan = 0;
                    $rowHeight = 0.0;
                }
            }
            if ($rowSpan > 0) {
                $height += $rowHeight;
                $rows++;
            }
            $height += ($rowGaps[$breakpoint] ?? 0.0) * max(0, $rows - 1);
            // Android text/icon metrics can exceed the platform-neutral
            // intrinsic estimate at large font scales. Reserve a compact
            // per-row guard so content never bleeds into the next section.
            $height += 12.0 * $rows;
            $maximum = max($maximum, $height);
        }

        return max(0.0, $maximum);
    }

    /** @return list<int> */
    private static function gridChildSpans(Element $child): array
    {
        $tag = $child->properties()[PropKey::Value->value] ?? null;
        if (!is_string($tag) || !str_starts_with($tag, 'pam:grid-item:')) {
            return [1, 1, 1, 1, 1, 1];
        }
        $values = array_map(
            static fn (string $value): int =>
                max(1, min(64, (int) $value)),
            explode(',', substr($tag, strlen('pam:grid-item:'))),
        );
        $resolved = [];
        $current = 1;
        for ($index = 0; $index < 6; $index++) {
            $current = $values[$index] ?? $current;
            $resolved[] = $current;
        }

        return $resolved;
    }

    private static function gridChildHeight(Element $child): float
    {
        return self::elementIntrinsicHeight($child);
    }

    /**
     * Estimate the same vertical intrinsic size that the native layout engine
     * will assign to a grid item. The grid host needs this value before Android
     * measures its rows; using a generic 48 dp fallback clips content-sized
     * cards and lets following siblings overlap them.
     */
    private static function elementIntrinsicHeight(Element $element): float
    {
        $properties = $element->properties();
        if (($properties[PropKey::Visible->value] ?? true) === false) {
            return 0.0;
        }

        $explicit = $properties[PropKey::Height->value] ?? null;
        if (is_numeric($explicit)) {
            return self::constrainElementHeight((float) $explicit, $properties);
        }

        if ($element->kind() === NodeKind::Text) {
            $fontSize = is_numeric($properties[PropKey::FontSize->value] ?? null)
                ? max(1.0, (float) $properties[PropKey::FontSize->value])
                : 14.0;
            $lineHeight = is_numeric($properties[PropKey::LineHeight->value] ?? null)
                ? max(1.0, (float) $properties[PropKey::LineHeight->value])
                : max(14.0, $fontSize * 1.4);
            $textValue = $properties[PropKey::Text->value] ?? '';
            $text = is_string($textValue) ? $textValue : '';
            $lines = max(1, substr_count($text, "\n") + 1);
            $limit = $properties[PropKey::NumberOfLines->value] ?? null;
            if (is_numeric($limit) && (int) $limit > 0) {
                $lines = min($lines, (int) $limit);
            }

            return self::constrainElementHeight(
                $lineHeight * $lines,
                $properties,
            );
        }

        $children = array_values(array_filter(
            $element->children(),
            static fn (Element $child): bool =>
                ($child->properties()[PropKey::Visible->value] ?? true) !== false,
        ));
        $contentSized = in_array($element->kind(), [
            NodeKind::Screen,
            NodeKind::Column,
            NodeKind::Row,
            NodeKind::View,
            NodeKind::Pressable,
            NodeKind::ImageBackground,
            NodeKind::KeyboardAvoidingView,
            NodeKind::SafeAreaView,
            NodeKind::InputAccessoryView,
        ], true);
        if ($children === [] || !$contentSized) {
            $leafHeight = match ($element->kind()) {
                NodeKind::Image, NodeKind::ImageBackground => 180.0,
                NodeKind::List,
                NodeKind::SectionList,
                NodeKind::Scroll,
                NodeKind::RefreshControl => 240.0,
                NodeKind::Spacer => 8.0,
                NodeKind::StatusBar => 0.0,
                default => 48.0,
            };

            return self::constrainElementHeight($leafHeight, $properties);
        }

        $padding = self::numericProperty($properties, PropKey::Padding);
        $paddingVertical = self::numericProperty(
            $properties,
            PropKey::PaddingVertical,
            $padding,
        );
        $paddingTop = self::numericProperty(
            $properties,
            PropKey::PaddingTop,
            $paddingVertical,
        );
        $paddingBottom = self::numericProperty(
            $properties,
            PropKey::PaddingBottom,
            $paddingVertical,
        );
        $directionValue = $properties[PropKey::FlexDirection->value] ?? null;
        $direction = is_int($directionValue)
            ? $directionValue
            : ($element->kind() === NodeKind::Row ? 2 : 1);
        $childHeights = array_map(
            static function (Element $child): float {
                $properties = $child->properties();
                $margin = self::numericProperty($properties, PropKey::Margin);
                $vertical = self::numericProperty(
                    $properties,
                    PropKey::MarginVertical,
                    $margin,
                );

                return self::elementIntrinsicHeight($child)
                    + self::numericProperty(
                        $properties,
                        PropKey::MarginTop,
                        $vertical,
                    )
                    + self::numericProperty(
                        $properties,
                        PropKey::MarginBottom,
                        $vertical,
                    );
            },
            $children,
        );
        $contentHeight = in_array($direction, [2, 4], true)
            ? max($childHeights)
            : array_sum($childHeights)
                + self::numericProperty($properties, PropKey::Gap)
                    * max(0, count($childHeights) - 1);

        return self::constrainElementHeight(
            $paddingTop + $contentHeight + $paddingBottom,
            $properties,
        );
    }

    /** @param array<int, string|int|float|bool|\Pam\Native\Internal\BinaryValue> $properties */
    private static function constrainElementHeight(
        float $height,
        array $properties,
    ): float {
        $minimum = $properties[PropKey::MinHeight->value] ?? null;
        $maximum = $properties[PropKey::MaxHeight->value] ?? null;
        if (is_numeric($minimum)) {
            $height = max($height, (float) $minimum);
        }
        if (is_numeric($maximum)) {
            $height = min($height, (float) $maximum);
        }

        return max(0.0, $height);
    }

    /**
     * @param array<int, string|int|float|bool|\Pam\Native\Internal\BinaryValue> $properties
     */
    private static function numericProperty(
        array $properties,
        PropKey $key,
        float $fallback = 0.0,
    ): float {
        $value = $properties[$key->value] ?? null;

        return is_numeric($value) ? (float) $value : $fallback;
    }

    /** @param array<string, mixed> $props */
    private static function calendarSelectLabel(
        string $part,
        array $props,
    ): string {
        $parent = is_array($props['__parentVariants'] ?? null)
            ? $props['__parentVariants']
            : [];
        if ($part === 'CalendarHeaderYearSelect') {
            $year = $props['selectedValue']
                ?? $props['year']
                ?? $parent['year']
                ?? null;

            return is_numeric($year) ? (string) (int) $year : 'Year';
        }
        $month = $props['selectedValue']
            ?? $props['month']
            ?? $parent['month']
            ?? null;
        if (!is_numeric($month)) {
            return 'Month';
        }
        $date = \DateTimeImmutable::createFromFormat(
            '!m',
            (string) max(1, min(12, (int) $month)),
        );

        return $date === false ? 'Month' : $date->format('F');
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string|int|float|bool>
     */
    private static function calendarNativeProperties(array $props): array
    {
        $values = [];
        $selected = $props['modelValue']
            ?? $props['value']
            ?? $props['defaultValue']
            ?? null;
        $rangeSelection = ($props['multiple'] ?? false) === 'range'
            || self::flag($props, 'range');
        if (is_array($selected)) {
            if (array_is_list($selected)) {
                $dates = array_map(
                    static fn (mixed $date): string => is_scalar($date)
                        ? (string) $date
                        : '',
                    $selected,
                );
                $dates = array_values(array_filter($dates));
                if ($rangeSelection) {
                    $values['rangeFrom'] = $dates[0] ?? '';
                    $values['rangeTo'] = $dates[1] ?? '';
                } else {
                    $values['selectedValues'] = implode("\n", $dates);
                }
            } else {
                $from = $selected['from'] ?? null;
                $to = $selected['to'] ?? null;
                $values['rangeFrom'] = is_scalar($from) ? (string) $from : '';
                $values['rangeTo'] = is_scalar($to) ? (string) $to : '';
            }
        }
        $disabled = $props['disabledDates'] ?? null;
        if (is_array($disabled)) {
            $dates = array_map(
                static fn (mixed $date): string => is_scalar($date)
                    ? (string) $date
                    : '',
                $disabled,
            );
            $values['disabledDates'] = implode("\n", array_filter($dates));
        }
        $visible = $props['visibleDate'] ?? $selected;
        if (is_array($visible)) {
            $visible = reset($visible);
        }
        if (is_scalar($visible)) {
            $date = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                substr((string) $visible, 0, 10),
            );
            if ($date !== false) {
                $values['year'] = (int) $date->format('Y');
                $values['month'] = (int) $date->format('n');
                $values['visibleDate'] = $date->format('Y-m-d');
                if (!is_array($selected) && is_scalar($selected)) {
                    $values['selectedValues'] = substr((string) $selected, 0, 10);
                }
            }
        }
        $values['showWeek'] = self::flag($props, 'showWeek');
        $values['showOutsideDays'] = self::flag(
            $props,
            'showAdjacentMonths',
            self::flag($props, 'showOutsideDays', true),
        );
        $multiple = $props['multiple'] ?? false;
        $values['mode'] = match (true) {
            $rangeSelection => ComponentMode::Range->value,
            self::flag($props, 'multiple') => ComponentMode::Multiple->value,
            default => is_numeric($props['mode'] ?? null)
                ? (int) $props['mode']
                : ComponentMode::Single->value,
        };

        return $values;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string|int|float|bool>
     */
    private static function sheetNativeProperties(array $props): array
    {
        $values = [];
        $snapPoints = $props['snapPoints'] ?? null;
        if (is_string($snapPoints)) {
            $snapPoints = preg_split('/[\s,;|]+/', trim($snapPoints)) ?: [];
        }
        if (is_array($snapPoints)) {
            $normalized = [];
            foreach ($snapPoints as $point) {
                if (is_string($point)) {
                    $point = rtrim(trim($point), '%');
                }
                if (!is_numeric($point)) {
                    continue;
                }
                $normalized[] = min(100.0, max(1.0, (float) $point));
            }
            if ($normalized !== []) {
                $values['snapPoints'] = implode("\n", $normalized);
            }
        }

        $index = $props['snapToIndex']
            ?? $props['defaultSnapIndex']
            ?? $props['index']
            ?? null;
        if (is_numeric($index)) {
            $values['snapToIndex'] = max(0, (int) $index);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string|int|float|bool>
     */
    private static function fileTreeNativeProperties(array $props): array
    {
        $controlled = array_key_exists('expanded', $props)
            || array_key_exists('expandedPaths', $props);
        $expanded = $controlled
            ? ($props['expanded'] ?? $props['expandedPaths'] ?? [])
            : ($props['defaultExpanded'] ?? []);
        if (is_string($expanded)) {
            $expanded = preg_split('/[\r\n,;|]+/', $expanded) ?: [];
        }
        if (!is_array($expanded)) {
            return [];
        }
        $paths = array_values(
            array_unique(
                array_filter(
                    $expanded,
                    static fn (mixed $path): bool =>
                        is_string($path)
                        && $path !== ''
                        && !str_contains($path, "\n"),
                ),
            ),
        );

        return [
            $controlled ? 'expandedPaths' : 'defaultExpandedPaths' =>
                implode("\n", $paths),
        ];
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @param array<int, Closure> $events
     * @return list<Element>
     */
    private static function fallbackChildren(
        string $part,
        array $props,
        array $children,
        array $events,
    ): array {
        if (
            $part === 'AccordionItem'
            && ($props['__materialComponent'] ?? null) === 'PStepperVerticalItem'
        ) {
            $header = array_shift($children)
                ?? self::themedText(self::text($props, 'title', 'Step'));
            $expanded = self::flag($props, 'expanded');
            $trigger = self::render(
                'AccordionTrigger',
                [
                    'expanded' => $expanded,
                    'disabled' => self::flag($props, 'disabled'),
                    'accessibilityLabel' => self::text(
                        $props,
                        'accessibilityLabel',
                        self::text($props, 'title', 'Step'),
                    ),
                ],
                [$header],
                [],
                null,
                null,
            );
            $content = self::render(
                'AccordionContent',
                ['expanded' => $expanded, 'hidden' => !$expanded],
                $children,
                [],
                new Style(
                    widthPercent: 100.0,
                    paddingLeft: 24.0,
                    paddingRight: 16.0,
                    paddingBottom: 16.0,
                ),
                null,
            );

            return [$trigger, $content];
        }

        if (
            $part === 'VirtualizedList'
            && ($props['__materialComponent'] ?? null) === 'PInfiniteScroll'
            && self::flag($props, 'loading')
        ) {
            $theme = ThemeManager::current();
            $children[] = Row::make(
                ActivityIndicator::make()->color(
                    $theme->color(ColorToken::Primary),
                ),
                self::themedText(
                    self::text($props, 'loadingText', 'Loading more'),
                ),
            )->style(new Style(
                widthPercent: 100.0,
                minHeight: 52.0,
                gap: 12.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))->accessibilityRole(AccessibilityRole::ProgressBar);
        }

        if ($children !== []) {
            return $children;
        }

        if (
            isset($props['text'])
            && (
                self::isPressable($part)
                || in_array($part, ['Badge', 'InputSlot'], true)
            )
        ) {
            return [self::themedText(self::text($props, 'text'))];
        }

        if (
            $part === 'DateTimePicker'
            && ($props['__materialComponent'] ?? null) === 'PTimePicker'
        ) {
            $theme = ThemeManager::current();
            $value = self::text(
                $props,
                'modelValue',
                self::text($props, 'value', 'Select time'),
            );

            return [
                Text::make($value === '' ? 'Select time' : $value)->style(
                    new Style(
                        widthPercent: 100.0,
                        textColor: $theme->color(ColorToken::OnSurface),
                        fontSize: 20.0,
                        lineHeight: 28.0,
                        fontWeight: 600,
                    ),
                ),
            ];
        }

        if (in_array($part, ['Input', 'Textarea'], true)) {
            return [self::input($part === 'Textarea' ? 'TextareaInput' : 'InputField', $props)];
        }
        if (
            in_array($part, ['Checkbox', 'Radio', 'Switch'], true)
            && in_array(
                $props['__materialComponent'] ?? null,
                [
                    'PCheckbox', 'PRadio', 'PSwitch', 'PItem',
                ],
                true,
            )
        ) {
            $label = self::text($props, 'label');
            if ($label !== '') {
                $theme = ThemeManager::current();
                if ($props['__materialComponent'] === 'PItem') {
                    return [
                        self::themedText($label)->style(new Style(
                            flexGrow: 1.0,
                            textColor: $theme->color(ColorToken::OnSurface),
                            fontSize: 16.0,
                            lineHeight: 24.0,
                        )),
                    ];
                }
                $indicatorSize = MaterialTokens::componentSize(
                    $props['size'] ?? null,
                    $part === 'Checkbox'
                        ? MaterialTokens::CHECKBOX_ICON_SIZE
                        : MaterialTokens::RADIO_ICON_SIZE,
                    4.0,
                );
                $switchWidth = MaterialTokens::componentSize(
                    $props['size'] ?? null,
                    52.0,
                );
                $switchHeight = MaterialTokens::componentSize(
                    $props['size'] ?? null,
                    32.0,
                );
                $indicator = View::make()->style(new Style(
                    width: $part === 'Switch' ? $switchWidth : $indicatorSize,
                    height: $part === 'Switch' ? $switchHeight : $indicatorSize,
                    minWidth: $part === 'Switch' ? $switchWidth : $indicatorSize,
                    minHeight: $part === 'Switch' ? $switchHeight : $indicatorSize,
                    backgroundColor: 0x00000000,
                    animationDurationMs: 200,
                    animateChanges: true,
                ))->property(
                    PropKey::Value,
                    $part === 'Switch'
                        ? 'pam:switch-track'
                        : 'pam:selection-indicator',
                );

                return [
                    $indicator,
                    self::themedText($label)->style(new Style(
                        flexGrow: 1.0,
                        textColor: $theme->color(ColorToken::OnSurface),
                        fontSize: 16.0,
                        lineHeight: 24.0,
                    )),
                ];
            }
        }
        if (
            $part === 'Slider'
            && in_array(
                $props['__materialComponent'] ?? null,
                ['PSlider', 'PRangeSlider'],
                true,
            )
        ) {
            return self::materialSliderChildren($props);
        }
        if (
            $part === 'FileTree'
            && ($props['__materialComponent'] ?? null) === 'PTreeview'
        ) {
            return self::materialTreeviewChildren($props, $events);
        }
        if (
            $part === 'Calendar'
            && in_array(
                $props['__materialComponent'] ?? null,
                [
                    'PCalendar',
                    'PDatePicker',
                ],
                true,
            )
        ) {
            return self::materialCalendarChildren($props);
        }
        if (
            $part === 'Table'
            && in_array(
                $props['__materialComponent'] ?? null,
                ['PDataTable', 'PDataTableVirtual'],
                true,
            )
        ) {
            return self::materialDataTableChildren($props, $events);
        }
        if (
            $part === 'Select'
            && in_array(
                $props['__materialComponent'] ?? null,
                ['PSelect', 'PAutocomplete', 'PCombobox'],
                true,
            )
        ) {
            return self::materialSelectChildren($props, $events);
        }
        if ($part === 'FileTreeFile') {
            return [self::themedText(
                self::text($props, 'name', self::text($props, 'path')),
            )];
        }
        if (in_array($part, ['CalendarWeekDay', 'CalendarWeekNumber'], true)) {
            return [self::themedText(self::text($props, 'text'))];
        }
        if (in_array($part, [
            'CalendarHeaderMonthSelect',
            'CalendarHeaderYearSelect',
        ], true)) {
            return [Text::make(self::calendarSelectLabel($part, $props))];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function chipChildren(
        array $props,
        array $events,
        array $children,
    ): array {
        if (($props['__materialComponent'] ?? null) !== 'PChip') {
            return $children;
        }

        $theme = ThemeManager::current();
        $iconName = $props['prependIcon'] ?? $props['icon'] ?? null;
        if (
            $iconName === null
            && self::flag($props, 'filter')
            && self::flag($props, 'selected')
        ) {
            $iconName = 'check';
        }
        if (is_string($iconName) && $iconName !== '') {
            $variant = $props['variant'] ?? null;
            $iconColor = $variant === MaterialVariant::Outlined->value
                || $variant === 'outlined'
                ? (MaterialStyleResolver::semanticBackground($props, $theme)
                    ?? $theme->color(ColorToken::Primary))
                : (MaterialStyleResolver::semanticForeground($props, $theme)
                    ?? $theme->color(ColorToken::SecondaryForeground));
            array_unshift($children, self::render(
                'Icon',
                ['icon' => $iconName, 'size' => 'small', 'color' => $iconColor],
                [],
                [],
                new Style(width: 18.0, height: 18.0),
                'chip-leading-icon',
            ));
        }

        if (!self::flag($props, 'closable')) {
            return $children;
        }

        $label = self::text($props, 'text');
        $closeLabel = self::text($props, 'closeLabel');
        if ($closeLabel === '') {
            $closeLabel = $label === '' ? 'Close chip' : 'Close '.$label;
        }
        $variant = $props['variant'] ?? null;
        $closeColor = $variant === MaterialVariant::Outlined->value
            || $variant === 'outlined'
            ? (MaterialStyleResolver::semanticBackground($props, $theme)
                ?? $theme->color(ColorToken::Primary))
            : (MaterialStyleResolver::semanticForeground($props, $theme)
                ?? $theme->color(ColorToken::SecondaryForeground));
        $close = Pressable::make(
            Text::make('×')->style(new Style(
                textColor: $closeColor,
                fontSize: 18.0,
                lineHeight: 20.0,
                textAlign: TextAlignment::Center,
            )),
        )
            ->style(new Style(
                width: 32.0,
                height: 32.0,
                borderRadius: 16.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))
            ->hitSlop(8.0)
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel($closeLabel);
        $handler = $events[EventKind::Native->value] ?? null;
        if ($handler !== null) {
            $close = $close->on(
                EventKind::Press,
                static function () use ($handler): void {
                    $handler('');
                },
            );
        }
        $children[] = $close;

        return $children;
    }

    /**
     * Shapes direct p-btn children as a Material button group. Standard groups
     * retain the normal pill shape and 8 dp rhythm; connected/divided groups
     * use asymmetric outer items, small inner corners and a checked pill.
     * Horizontal hit slop is removed on the children earlier in render() so
     * neighboring buttons never compete for the same touch coordinate.
     *
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function buttonGroupChildren(
        array $props,
        array $children,
    ): array {
        if (($props['__materialComponent'] ?? null) !== 'PBtnGroup') {
            return $children;
        }

        $connected = self::flag(
            $props,
            'connected',
            self::flag($props, 'divided'),
        );
        $fill = self::flag($props, 'block', self::flag($props, 'fullWidth'));
        $tile = self::flag($props, 'tile');
        $rounded = self::flag($props, 'rounded');
        $rtl = self::flag($props, 'rtl');
        $lastIndex = count($children) - 1;

        return array_map(
            static function (Element $child, int $index) use (
                $connected,
                $fill,
                $tile,
                $rounded,
                $rtl,
                $lastIndex,
            ): Element {
                $properties = $child->properties();
                $height = is_numeric($properties[PropKey::Height->value] ?? null)
                    ? (float) $properties[PropKey::Height->value]
                    : MaterialTokens::BUTTON_SMALL_HEIGHT;
                $outerRadius = $tile ? 0.0 : $height / 2.0;
                $innerRadius = $tile
                    ? 0.0
                    : min($outerRadius, $rounded ? 12.0 : 8.0);
                $selected = ($properties[PropKey::Selected->value] ?? false) === true;

                if (!$connected || $selected) {
                    $topLeft = $outerRadius;
                    $topRight = $outerRadius;
                    $bottomRight = $outerRadius;
                    $bottomLeft = $outerRadius;
                } else {
                    $leading = $rtl ? $index === $lastIndex : $index === 0;
                    $trailing = $rtl ? $index === 0 : $index === $lastIndex;
                    $topLeft = $leading ? $outerRadius : $innerRadius;
                    $bottomLeft = $leading ? $outerRadius : $innerRadius;
                    $topRight = $trailing ? $outerRadius : $innerRadius;
                    $bottomRight = $trailing ? $outerRadius : $innerRadius;
                }

                return $child->style(new Style(
                    minWidth: $fill ? 0.0 : null,
                    flexGrow: $fill ? 1.0 : null,
                    flexShrink: $fill ? 1.0 : null,
                    borderRadius: 0.0,
                    borderTopLeftRadius: $topLeft,
                    borderTopRightRadius: $topRight,
                    borderBottomRightRadius: $bottomRight,
                    borderBottomLeftRadius: $bottomLeft,
                ));
            },
            $children,
            array_keys($children),
        );
    }

    /**
     * Material segmented buttons share one outline. The engine uses physical
     * coordinates, so the left seam suppression must follow visual RTL order.
     *
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function buttonToggleChildren(
        array $props,
        array $children,
    ): array {
        if (($props['__materialComponent'] ?? null) !== 'PBtnToggle') {
            return $children;
        }

        $fill = self::flag($props, 'block', self::flag($props, 'fullWidth'));
        $tile = self::flag($props, 'tile');
        $rtl = self::flag($props, 'rtl');
        $lastIndex = count($children) - 1;

        return array_map(
            static function (Element $child, int $index) use (
                $fill,
                $tile,
                $rtl,
                $lastIndex,
            ): Element {
                $outerRadius = $tile ? 0.0 : 20.0;
                $leading = $rtl ? $index === $lastIndex : $index === 0;
                $trailing = $rtl ? $index === 0 : $index === $lastIndex;
                $overlapLeftEdge = $rtl
                    ? $index < $lastIndex
                    : $index > 0;

                return $child->style(new Style(
                    minWidth: $fill ? 0.0 : null,
                    flexGrow: $fill ? 1.0 : null,
                    flexShrink: $fill ? 1.0 : null,
                    borderRadius: 0.0,
                    borderTopLeftRadius: $leading ? $outerRadius : 0.0,
                    borderTopRightRadius: $trailing ? $outerRadius : 0.0,
                    borderBottomRightRadius: $trailing ? $outerRadius : 0.0,
                    borderBottomLeftRadius: $leading ? $outerRadius : 0.0,
                    // Material segmented buttons overlap neighboring outlines
                    // by one dp. Keeping every outline uniform preserves the
                    // rounded path on Android; a directional stroke would lose
                    // clipping at the outer corners.
                    marginLeft: $overlapLeftEdge ? -1.0 : 0.0,
                ));
            },
            $children,
            array_keys($children),
        );
    }

    /**
     * Adds the status and dismiss affordances that make an alert readable at a
     * glance without forcing application code to rebuild its native anatomy.
     *
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function alertChildren(
        array $props,
        array $events,
        array $children,
    ): array {
        if (($props['__materialComponent'] ?? null) !== 'PAlert') {
            return $children;
        }

        $theme = ThemeManager::current();
        $requestedIcon = $props['icon'] ?? true;
        if ($requestedIcon !== false) {
            $type = $props['type'] ?? $props['color'] ?? null;
            $iconName = is_string($requestedIcon) && $requestedIcon !== ''
                && $requestedIcon !== '1'
                ? $requestedIcon
                : match ($type) {
                    2, 'success' => 'check-circle',
                    3, 'warning' => 'alert-circle',
                    4, 'error', 'danger', 'destructive' => 'close-circle',
                    default => 'info',
                };
            $iconColor = MaterialStyleResolver::semanticBackground($props, $theme)
                ?? (($props['variant'] ?? null) === MaterialVariant::Tonal->value
                    || ($props['variant'] ?? null) === 'tonal'
                    || ($props['variant'] ?? null) === 'subtle'
                    ? $theme->color(ColorToken::Secondary)
                    : $theme->color(ColorToken::MutedForeground));
            $statusIcon = self::render(
                'Icon',
                ['icon' => $iconName, 'size' => 'xl', 'color' => $iconColor],
                [],
                [],
                new Style(marginTop: 2.0),
                'alert-status-icon',
            )->property(PropKey::Value, 'pam:preserve-foreground:alert-status');
            array_unshift($children, $statusIcon);
        }

        if (!self::flag($props, 'closable')) {
            return $children;
        }

        $closeLabel = self::text($props, 'closeLabel');
        if ($closeLabel === '') {
            $closeLabel = 'Close alert';
        }
        $closeIcon = self::render(
            'Icon',
            [
                'icon' => 'close',
                'size' => 'lg',
                'color' => $theme->color(ColorToken::Foreground),
            ],
            [],
            [],
            null,
            'alert-close-icon',
        );
        $close = Pressable::make($closeIcon)
            ->style(new Style(
                width: 40.0,
                height: 40.0,
                minWidth: 40.0,
                minHeight: 40.0,
                marginTop: -8.0,
                marginRight: -8.0,
                borderRadius: 20.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))
            ->hitSlop(4.0)
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel($closeLabel);
        $handler = $events[EventKind::Native->value] ?? null;
        if ($handler !== null) {
            $close = $close->on(
                EventKind::Press,
                static function () use ($handler): void {
                    $handler(false);
                },
            );
        }
        $children[] = $close;

        return $children;
    }

    /**
     * A prominent top app bar has two vertical zones: 64 dp for navigation and
     * actions, then a large headline anchored to the lower edge.
     *
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function appBarChildren(array $props, array $children): array
    {
        if (
            ($props['__materialComponent'] ?? null) !== 'PAppBar'
            || !self::flag($props, 'prominent')
        ) {
            return $children;
        }

        $positioned = [];
        $lastIndex = count($children) - 1;
        foreach ($children as $index => $child) {
            if ($child->kind() === NodeKind::Text) {
                $positioned[] = $child->style(new Style(
                        positionType: PositionType::Absolute,
                        left: 16.0,
                        right: 16.0,
                        bottom: 16.0,
                        fontSize: 32.0,
                        lineHeight: 40.0,
                        fontWeight: 400,
                        flexGrow: 0.0,
                        flexShrink: 1.0,
                    ));
                continue;
            }

            $positioned[] = $child->style(new Style(
                    positionType: PositionType::Absolute,
                    top: 4.0,
                    // PAppBar already owns the Material 4dp horizontal inset.
                    // Absolute children are positioned inside that padded box;
                    // adding another 4dp here visibly doubled the edge inset.
                    left: $index === 0 ? 0.0 : null,
                    right: $index === 0
                        ? null
                        : (($lastIndex - $index) * 48.0),
                    alignSelf: Align::Start,
                ));
        }

        return $positioned;
    }

    /**
     * Builds the complete native select anatomy for the compact material
     * facades. The portal remains mounted so Android and iOS can preserve
     * focus, sheet position and accessibility state between presentations.
     *
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @return list<Element>
     */
    private static function materialSelectChildren(
        array $props,
        array $events,
    ): array {
        $theme = ThemeManager::current();
        $componentValue = $props['__materialComponent'] ?? 'PSelect';
        $component = is_string($componentValue) ? $componentValue : 'PSelect';
        $compoundProps = $props;
        unset($compoundProps['__materialComponent']);
        $rawSelectedValue = $props['modelValue']
            ?? $props['selectedValue']
            ?? $props['value']
            ?? null;
        $multiple = self::flag($props, 'multiple');
        $selectedValues = is_array($rawSelectedValue)
            ? array_values(array_filter(
                $rawSelectedValue,
                static fn (mixed $value): bool => is_scalar($value),
            ))
            : (is_scalar($rawSelectedValue) ? [$rawSelectedValue] : []);
        $selectedValue = $multiple
            ? $selectedValues
            : ($selectedValues[0] ?? null);
        $isSelected = static function (mixed $candidate) use (
            $selectedValues,
        ): bool {
            foreach ($selectedValues as $selected) {
                if (self::sameScalar($candidate, $selected)) {
                    return true;
                }
            }

            return false;
        };

        $items = is_array($props['items'] ?? null) ? $props['items'] : [];
        $normalized = [];
        foreach ($items as $index => $item) {
            if (is_array($item)) {
                $value = $item['value']
                    ?? $item['id']
                    ?? $item['key']
                    ?? $item['title']
                    ?? $item['label']
                    ?? $index;
                $label = $item['title']
                    ?? $item['label']
                    ?? $item['name']
                    ?? $value;
                $disabled = (bool) ($item['disabled'] ?? false);
            } else {
                $value = $item;
                $label = $item;
                $disabled = false;
            }
            if (!is_scalar($value) || !is_scalar($label)) {
                continue;
            }
            $normalized[] = [
                'value' => $value,
                'label' => (string) $label,
                'disabled' => $disabled,
            ];
        }

        $displayLabels = [];
        foreach ($normalized as $item) {
            if ($isSelected($item['value'])) {
                $displayLabels[] = $item['label'];
            }
        }
        if ($displayLabels === [] && $selectedValues !== []) {
            $displayLabels = array_map(
                static fn (mixed $value): string => (string) $value,
                $selectedValues,
            );
        }
        $display = implode(', ', $displayLabels);
        if ($display === '') {
            $display = self::text(
                $props,
                'placeholder',
                self::text($props, 'label', 'Select an option'),
            );
        }

        $contentChildren = [];
        $change = $events[EventKind::Change->value] ?? null;
        $instanceIdentity = $props['id']
            ?? $props['name']
            ?? $props['instanceKey']
            ?? self::text($props, 'accessibilityLabel');
        $instanceDigest = substr(hash(
            'sha256',
            (string) $component."\0".self::scalarString($instanceIdentity),
        ), 0, 12);
        foreach ($normalized as $item) {
            $selected = $isSelected($item['value']);
            $itemProps = [
                ...$compoundProps,
                'label' => $item['label'],
                'value' => $item['value'],
                'checked' => $selected,
                'selected' => $selected,
                'disabled' => $item['disabled'],
                'closeOnSelect' => !$multiple,
                'accessibilityLabel' => $item['label'],
            ];
            $itemEvents = [];
            if ($change !== null && !$item['disabled']) {
                $itemEvents[EventKind::Press->value] = $multiple
                    ? self::listSelectionHandler(
                        $change,
                        $item['value'],
                        $selectedValues,
                    )
                    : self::scalarSelectionHandler($change, $item['value']);
            }
            $contentChildren[] = self::render(
                'SelectItem',
                $itemProps,
                [
                    Row::make(
                        self::themedText($item['label'])->style(new Style(
                            flexGrow: 1.0,
                            flexShrink: 1.0,
                            textColor: $theme->color(ColorToken::OnSurface),
                            fontSize: 16.0,
                            lineHeight: 24.0,
                        )),
                    )->style(new Style(
                        widthPercent: 100.0,
                        minHeight: 48.0,
                        paddingRight: 32.0,
                        alignItems: \Pam\Native\Align::Center,
                    )),
                ],
                $itemEvents,
                new Style(
                    widthPercent: 100.0,
                    minHeight: 56.0,
                    paddingHorizontal: 16.0,
                    paddingVertical: 4.0,
                    borderRadius: 12.0,
                    backgroundColor: $selected
                        ? $theme->color(ColorToken::SurfaceSunken)
                        : 0x00000000,
                    alignItems: \Pam\Native\Align::Center,
                    justifyContent: \Pam\Native\Justify::Center,
                ),
                'select-item-'.$instanceDigest.'-'.(string) $item['value'],
            );
        }
        // Searchable selection hosts own their empty state natively. Keeping a
        // protocol text node here would produce two announcements and, when
        // there are no sheet items to anchor the native layout pass, could
        // place both labels over the search field.
        $initiallyOpen = self::flag($props, 'open');
        $sheetProps = [
            ...$compoundProps,
            'selectedValue' => $selectedValue,
            'open' => true,
            'presentation' => 'sheet',
            'snapPoints' => $props['snapPoints']
                ?? ($component === 'PSelect' ? '50%' : '60%'),
            'enableDynamicSizing' => $props['enableDynamicSizing'] ?? true,
            'closeOnSelect' => !$multiple,
            'searchable' => $component !== 'PSelect',
            'allowCustomValue' => $component === 'PCombobox',
            'searchPlaceholder' => self::text(
                $props,
                'searchPlaceholder',
                'Search options',
            ),
            'searchBackgroundColor' => $theme->color(ColorToken::Surface),
            'searchTextColor' => $theme->color(ColorToken::OnSurface),
            'searchHintColor' => $theme->color(ColorToken::MutedForeground),
            'searchBorderColor' => $theme->color(ColorToken::Border),
            'customActionTextColor' => $theme->color(ColorToken::Primary),
            'customActionBackgroundColor' => $theme->color(ColorToken::Accent),
            'customActionPressedBackgroundColor' => $theme->color(
                ColorToken::SurfaceContainerHigh,
            ),
        ];
        $localModalKey = substr(hash(
            'sha256',
            (string) $component
                ."\0"
                .self::scalarString($props['id']
                    ?? $props['name']
                    ?? $props['instanceKey']
                    ?? self::text($props, 'accessibilityLabel'))
                ."\0"
                .self::text($props, 'label')
                ."\0"
                .json_encode($normalized, JSON_UNESCAPED_SLASHES),
        ), 0, 16);
        $dragIndicator = View::make()
            ->property(PropKey::Value, 'pam:sheet-drag-indicator')
            ->property(PropKey::PointerEvents, PointerEvents::None->value)
            ->style(new Style(
                width: MaterialTokens::BOTTOM_SHEET_HANDLE_WIDTH,
                height: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT,
                minWidth: MaterialTokens::BOTTOM_SHEET_HANDLE_WIDTH,
                minHeight: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT,
                backgroundColor: $theme->color(ColorToken::MutedForeground),
                borderRadius: MaterialTokens::BOTTOM_SHEET_HANDLE_HEIGHT / 2.0,
            ));
        $dragIndicatorWrapper = Row::make($dragIndicator)
            ->property(PropKey::Value, 'pam:sheet-drag-indicator-wrapper')
            ->property(PropKey::PointerEvents, PointerEvents::None->value)
            ->style(new Style(
                widthPercent: 100.0,
                height: 24.0,
                minHeight: 24.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ));
        $content = self::render(
            'SelectContent',
            $sheetProps,
            [
                $dragIndicatorWrapper,
                Column::make(...$contentChildren)->style(new Style(
                    widthPercent: 100.0,
                    paddingTop: 16.0,
                    paddingBottom: 16.0,
                    gap: 0.0,
                )),
            ],
            [],
            new Style(
                widthPercent: 100.0,
                minHeight: $component === 'PSelect' ? 112.0 : 172.0,
                paddingTop: 12.0,
                paddingBottom: 24.0,
                backgroundColor: $theme->color(ColorToken::Surface),
                borderTopLeftRadius: 28.0,
                borderTopRightRadius: 28.0,
            ),
            null,
        );
        $backdrop = self::render(
            'SelectBackdrop',
            $sheetProps,
            [],
            [],
            null,
            null,
        );
        $portalEvents = [];
        if ($change !== null && $component === 'PCombobox') {
            $portalEvents[EventKind::Change->value] = $change;
        }
        if (isset($events[EventKind::Native->value])) {
            $portalEvents[EventKind::Native->value] =
                $events[EventKind::Native->value];
        }
        $portal = self::render(
            'SelectPortal',
            $sheetProps,
            [$backdrop, $content],
            $portalEvents,
            null,
            'select-portal',
        )
            ->property(PropKey::Value, 'pam:local-modal:'.$localModalKey)
            ->property(PropKey::Visible, $initiallyOpen);

        $triggerEvents = [];
        $readOnly = self::flag(
            $props,
            'readonly',
            self::flag($props, 'readOnly'),
        );
        $toggle = $events[EventKind::Toggle->value] ?? null;
        if (!$readOnly && $toggle !== null) {
            $triggerEvents[EventKind::Press->value] =
                static fn () => $toggle(true);
        } elseif (!$readOnly && isset($events[EventKind::Press->value])) {
            $triggerEvents[EventKind::Press->value] =
                $events[EventKind::Press->value];
        }
        $disabled = self::flag($props, 'disabled');
        $trigger = View::make(
                Row::make(
                    self::themedText($display)->style(new Style(
                        flexGrow: 1.0,
                        textColor: $theme->color(
                            $display === self::text($props, 'placeholder')
                                ? ColorToken::MutedForeground
                                : ColorToken::OnSurface,
                        ),
                        fontSize: 16.0,
                        lineHeight: 24.0,
                    )),
                    self::flag($props, 'loading')
                        ? ActivityIndicator::make()
                            ->color($theme->color(ColorToken::Primary))
                            ->style(new Style(
                                width: 20.0,
                                height: 20.0,
                                minWidth: 20.0,
                                minHeight: 20.0,
                            ))
                        : ($readOnly
                            ? View::make()->style(new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                            ))
                            : self::render(
                            'Icon',
                            [
                                'icon' => 'chevron-down',
                                'accessibilityHidden' => true,
                                'reduceMotion' => self::flag($props, 'reduceMotion'),
                            ],
                            [],
                            [],
                            new Style(
                                width: 24.0,
                                height: 24.0,
                                minWidth: 24.0,
                                minHeight: 24.0,
                                tintColor: $theme->color(ColorToken::MutedForeground),
                                rotation: self::flag($props, 'open') ? 180.0 : 0.0,
                                alignItems: \Pam\Native\Align::Center,
                                justifyContent: \Pam\Native\Justify::Center,
                                animationDurationMs: 150,
                                animateChanges: true,
                            ),
                                'select-trigger-chevron-'.$instanceDigest,
                            )),
                )->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 32.0,
                    alignItems: \Pam\Native\Align::Center,
                    gap: 8.0,
                )),
            )->style(new Style(
                widthPercent: 100.0,
                minHeight: 32.0,
                padding: 0.0,
                backgroundColor: 0x00000000,
                borderWidth: 0.0,
                borderRadius: 0.0,
                elevation: 0.0,
                alignItems: \Pam\Native\Align::Center,
                justifyContent: \Pam\Native\Justify::Center,
            ));

        $hitTarget = Pressable::make()
            ->enabled(!$disabled && !$readOnly)
            ->accessibilityRole(AccessibilityRole::ComboBox)
            ->accessibilityLabel(self::text($props, 'label', $display))
            ->accessibilityHint($readOnly
                ? 'Read only'
                : ($component === 'PSelect'
                    ? 'Opens available options'
                    : 'Opens searchable options'))
            ->accessibilityExpanded(self::flag($props, 'open'))
            ->property(
                PropKey::Value,
                ($readOnly
                    ? 'pam:readonly-select-trigger:'
                    : 'pam:local-modal-trigger:').$localModalKey,
            );
        foreach ($triggerEvents as $event => $handler) {
            $hitTarget = $hitTarget->on(EventKind::from($event), $handler);
        }

        return [$trigger, $hitTarget, $portal];
    }

    /**
     * @param array<string, mixed> $props
     * @return list<Element>
     */
    private static function materialSliderChildren(array $props): array
    {
        $theme = ThemeManager::current();
        $vertical = ($props['direction'] ?? null) === 'vertical'
            || self::flag($props, 'vertical')
            || ($props['orientation'] ?? null) === 2
            || ($props['orientation'] ?? null) === 'vertical';
        $thumbLabel = $props['thumbLabel']
            ?? $props['showThumbLabel']
            ?? false;
        $showsThumbLabel = $thumbLabel === true
            || $thumbLabel === 'always'
            || self::flag($props, 'alwaysShowThumbLabel');
        $filled = self::render(
            'SliderFilledTrack',
            ['reduceMotion' => self::flag($props, 'reduceMotion')],
            [],
            [],
            new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                backgroundColor: $theme->color(ColorToken::Primary),
                borderRadius: 9999.0,
            ),
            'material-slider-fill',
        );
        $track = self::render(
            'SliderTrack',
            ['reduceMotion' => self::flag($props, 'reduceMotion')],
            [$filled],
            [],
            new Style(
                widthPercent: $vertical ? null : 100.0,
                heightPercent: $vertical ? 100.0 : null,
                width: $vertical ? MaterialTokens::SLIDER_TRACK_HEIGHT : null,
                height: $vertical ? null : MaterialTokens::SLIDER_TRACK_HEIGHT,
                minWidth: $vertical ? MaterialTokens::SLIDER_TRACK_HEIGHT : null,
                minHeight: $vertical ? null : MaterialTokens::SLIDER_TRACK_HEIGHT,
                marginLeft: $vertical
                    ? (
                        MaterialTokens::MINIMUM_TOUCH_TARGET
                        - MaterialTokens::SLIDER_TRACK_HEIGHT
                    ) / 2.0
                    : null,
                marginTop: $vertical
                    ? null
                    : ($showsThumbLabel ? 48.0 : 16.0),
                backgroundColor: $theme->color(ColorToken::SurfaceContainerHighest),
                borderRadius: 9999.0,
            ),
            'material-slider-track',
        );
        $thumb = self::render(
            'SliderThumb',
            ['reduceMotion' => self::flag($props, 'reduceMotion')],
            [],
            [],
            new Style(
                width: $vertical
                    ? MaterialTokens::SLIDER_HANDLE_HEIGHT
                    : MaterialTokens::SLIDER_HANDLE_WIDTH,
                height: $vertical
                    ? MaterialTokens::SLIDER_HANDLE_WIDTH
                    : MaterialTokens::SLIDER_HANDLE_HEIGHT,
                minWidth: $vertical
                    ? MaterialTokens::SLIDER_HANDLE_HEIGHT
                    : MaterialTokens::SLIDER_HANDLE_WIDTH,
                minHeight: $vertical
                    ? MaterialTokens::SLIDER_HANDLE_WIDTH
                    : MaterialTokens::SLIDER_HANDLE_HEIGHT,
                backgroundColor: $theme->color(ColorToken::Primary),
                borderRadius: MaterialTokens::SLIDER_HANDLE_WIDTH / 2.0,
            ),
            'material-slider-thumb',
        );

        return [$track, $thumb];
    }

    /**
     * @param array<string, mixed> $props
     * @return list<Element>
     */
    private static function materialCalendarChildren(array $props): array
    {
        $visible = $props['visibleDate']
            ?? $props['modelValue']
            ?? $props['value']
            ?? date('Y-m-d');
        if (is_array($visible)) {
            $visible = reset($visible);
        }
        $date = is_scalar($visible)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $visible, 0, 10))
            : false;
        $date = $date ?: new \DateTimeImmutable('first day of this month');

        $theme = ThemeManager::current();
        $locale = str_replace('_', '-', strtolower(self::scalarString($props['locale'] ?? 'en-US')));
        $language = explode('-', $locale)[0];
        $weekdayLabels = match ($language) {
            'pt' => ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'],
            'es' => ['D', 'L', 'M', 'X', 'J', 'V', 'S'],
            'fr' => ['D', 'L', 'M', 'M', 'J', 'V', 'S'],
            'de' => ['S', 'M', 'D', 'M', 'D', 'F', 'S'],
            'it' => ['D', 'L', 'M', 'M', 'G', 'V', 'S'],
            default => ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
        };
        $firstDayOfWeek = max(0, min(6, self::scalarInt($props['firstDayOfWeek'] ?? 0)));
        $weekdayLabels = array_merge(
            array_slice($weekdayLabels, $firstDayOfWeek),
            array_slice($weekdayLabels, 0, $firstDayOfWeek),
        );
        $showWeek = self::flag($props, 'showWeek');

        $icon = static fn (string $name, string $key): Element => self::render(
            'Icon',
            [
                'icon' => $name,
                'accessibilityHidden' => true,
                'reduceMotion' => self::flag($props, 'reduceMotion'),
            ],
            [],
            [],
            new Style(
                width: 24.0,
                height: 24.0,
                minWidth: 24.0,
                minHeight: 24.0,
                tintColor: $theme->color(ColorToken::MutedForeground),
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ),
            $key,
        );

        $previous = Pressable::make(
            $icon('chevron-left', 'material-calendar-previous-icon'),
        )
            ->property(PropKey::Value, 'pam:calendar-prev')
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Previous month')
            ->style(new Style(
                width: 48.0,
                height: 48.0,
                minWidth: 48.0,
                minHeight: 48.0,
                borderRadius: 24.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ));
        $monthSelect = Pressable::make(
            self::themedText($date->format('F'))->style(new Style(
                widthPercent: 100.0,
                fontSize: 16.0,
                lineHeight: 24.0,
                fontWeight: 600,
                textAlign: TextAlignment::Center,
            )),
        )
            ->property(PropKey::Value, 'pam:calendar-month-select')
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Select month')
            ->style(new Style(
                flexGrow: 1.0,
                minWidth: 0.0,
                height: 48.0,
                minHeight: 48.0,
                paddingHorizontal: 8.0,
                borderRadius: 24.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ));
        $yearSelect = Pressable::make(
            self::themedText($date->format('Y'))->style(new Style(
                widthPercent: 100.0,
                fontSize: 16.0,
                lineHeight: 24.0,
                fontWeight: 600,
                textAlign: TextAlignment::Center,
            )),
        )
            ->property(PropKey::Value, 'pam:calendar-year-select')
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Select year')
            ->style(new Style(
                width: 72.0,
                minWidth: 72.0,
                height: 48.0,
                minHeight: 48.0,
                paddingHorizontal: 8.0,
                borderRadius: 24.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ));
        $titleSelectors = Row::make($monthSelect, $yearSelect)->style(new Style(
            flexGrow: 1.0,
            minWidth: 0.0,
            height: 48.0,
            minHeight: 48.0,
            gap: 0.0,
            alignItems: Align::Center,
            justifyContent: Justify::Center,
        ));
        $next = Pressable::make(
            $icon('chevron-right', 'material-calendar-next-icon'),
        )
            ->property(PropKey::Value, 'pam:calendar-next')
            ->accessibilityRole(AccessibilityRole::Button)
            ->accessibilityLabel('Next month')
            ->style(new Style(
                width: 48.0,
                height: 48.0,
                minWidth: 48.0,
                minHeight: 48.0,
                borderRadius: 24.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ));
        $header = Row::make($previous, $titleSelectors, $next)
            ->style(new Style(
                widthPercent: 100.0,
                minHeight: 56.0,
                paddingHorizontal: 4.0,
                gap: 0.0,
                alignItems: Align::Center,
            ));
        $weekdayCells = [];
        if ($showWeek) {
            $weekdayCells[] = self::themedText('#')->style(new Style(
                flexGrow: 1.0,
                flexShrink: 1.0,
                minWidth: 0.0,
                fontSize: 12.0,
                lineHeight: 16.0,
                fontWeight: 500,
                textAlign: TextAlignment::Center,
                textColor: $theme->color(ColorToken::MutedForeground),
            ));
        }
        foreach ($weekdayLabels as $label) {
            $weekdayCells[] = self::themedText($label)->style(new Style(
                flexGrow: 1.0,
                flexShrink: 1.0,
                minWidth: 0.0,
                fontSize: 12.0,
                lineHeight: 16.0,
                fontWeight: 500,
                textAlign: TextAlignment::Center,
                textColor: $theme->color(ColorToken::MutedForeground),
            ));
        }
        $weekdays = Row::make(...$weekdayCells)
            ->style(new Style(
                widthPercent: 100.0,
                height: 32.0,
                minHeight: 32.0,
                alignItems: Align::Center,
            ))
            ->accessible(false)
            ->accessibilityImportance(AccessibilityImportance::NoHideDescendants);

        $firstDay = ((int) $date->format('N')) % 7;
        $startOffset = ($firstDay - $firstDayOfWeek + 7) % 7;
        $occupiedCells = $startOffset + (int) $date->format('t');
        $rows = self::flag($props, 'fixedWeeks')
            ? 6
            : max(4, min(6, (int) ceil($occupiedCells / 7)));
        $gridHeight = $rows * 48.0;
        $grid = View::make()
            ->property(PropKey::Value, 'pam:calendar-grid')
            ->style(new Style(
                widthPercent: 100.0,
                height: $gridHeight,
                minHeight: $gridHeight,
            ));

        return [$header, $weekdays, $grid];
    }

    /**
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @return list<Element>
     */
    private static function materialTreeviewChildren(
        array $props,
        array $events,
    ): array {
        $items = $props['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }
        $opened = $props['opened'] ?? $props['expanded'] ?? [];
        $opened = is_array($opened)
            ? array_map(
                static fn (mixed $value): string =>
                    is_scalar($value) ? (string) $value : '',
                $opened,
            )
            : [];
        $selected = $props['modelValue']
            ?? $props['selected']
            ?? $props['value']
            ?? null;
        $selectedValues = is_array($selected)
            ? array_map(
                static fn (mixed $value): string =>
                    is_scalar($value) ? (string) $value : '',
                $selected,
            )
            : (is_scalar($selected) ? [(string) $selected] : []);
        $change = $events[EventKind::Change->value] ?? null;

        $renderItems = function (
            array $source,
            string $parentPath = '',
        ) use (
            &$renderItems,
            $opened,
            $selectedValues,
            $change,
        ): array {
            $rendered = [];
            foreach (array_values($source) as $index => $item) {
                $item = is_array($item) ? $item : ['title' => $item];
                $title = (string) (
                    $item['title']
                    ?? $item['name']
                    ?? $item['label']
                    ?? $item['value']
                    ?? 'Item '.($index + 1)
                );
                $path = (string) (
                    $item['value']
                    ?? $item['path']
                    ?? $item['key']
                    ?? ($parentPath === ''
                        ? $title
                        : $parentPath.'/'.$title)
                );
                $nestedSource = $item['children'] ?? [];
                $nested = is_array($nestedSource)
                    ? $renderItems($nestedSource, $path)
                    : [];
                $itemEvents = [];
                if ($change !== null) {
                    $itemEvents[EventKind::Press->value] =
                        static fn () => $change($path);
                }
                $rendered[] = self::render(
                    $nested === [] ? 'FileTreeFile' : 'FileTreeFolder',
                    [
                        'path' => $path,
                        'name' => $title,
                        'expanded' => in_array($path, $opened, true),
                        'selected' => in_array($path, $selectedValues, true),
                    ],
                    $nested,
                    $itemEvents,
                    null,
                    'pam-tree-'.substr(sha1($path), 0, 16),
                );
            }

            return $rendered;
        };

        return $renderItems($items);
    }

    /**
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @return list<Element>
     */
    private static function materialDataTableChildren(
        array $props,
        array $events,
    ): array {
        $source = $props['items'] ?? [];
        if (is_string($source)) {
            $source = array_values(array_filter(
                array_map('trim', explode(',', $source)),
                static fn (string $item): bool => $item !== '',
            ));
        }
        if (!is_array($source)) {
            return [];
        }

        $density = $props['density'] ?? 'default';
        $rowHeight = match ($density) {
            'compact', 3 => 44.0,
            'comfortable', 2 => 48.0,
            default => 52.0,
        };
        $headers = [];
        $headerSource = $props['headers'] ?? [];
        if (is_array($headerSource)) {
            foreach ($headerSource as $header) {
                if (is_array($header)) {
                    $key = $header['key'] ?? $header['value'] ?? null;
                    $title = $header['title'] ?? $header['text'] ?? $key;
                    if (is_scalar($key) && is_scalar($title)) {
                        $headers[] = [
                            'key' => (string) $key,
                            'title' => (string) $title,
                        ];
                    }
                } elseif (is_scalar($header)) {
                    $headers[] = [
                        'key' => (string) $header,
                        'title' => ucfirst((string) $header),
                    ];
                }
            }
        }
        if ($headers === [] && isset($source[0]) && is_array($source[0])) {
            foreach (array_keys($source[0]) as $key) {
                $headers[] = [
                    'key' => (string) $key,
                    'title' => ucfirst(str_replace('_', ' ', (string) $key)),
                ];
            }
        }

        $rows = [];
        $showSelect = self::flag($props, 'showSelect');
        $selectionChange = $events[EventKind::Change->value] ?? null;
        $selectedValues = self::selectedValues($props);
        $itemValue = self::text($props, 'itemValue', 'id');
        $selectableValues = [];
        foreach (array_values($source) as $rowIndex => $item) {
            $candidate = is_array($item)
                ? ($item[$itemValue] ?? $item['value'] ?? $rowIndex)
                : $item;
            if (is_scalar($candidate)) {
                $selectableValues[] = $candidate;
            }
        }
        if ($headers !== []) {
            $headerCells = array_map(
                static fn (array $header): Element => self::materialDataTableCell(
                    $header['title'],
                    $rowHeight,
                    true,
                ),
                $headers,
            );
            if ($showSelect) {
                $allSelected = $selectableValues !== []
                    && array_diff($selectableValues, $selectedValues) === [];
                array_unshift(
                    $headerCells,
                    self::materialDataTableSelectionCell(
                        $allSelected,
                        $rowHeight,
                        'Select all rows',
                        $selectionChange === null
                            ? null
                            : static function () use (
                                $selectionChange,
                                $allSelected,
                                $selectableValues,
                            ): void {
                                $selectionChange(
                                    $allSelected ? [] : $selectableValues,
                                );
                            },
                    ),
                );
            }
            $rows[] = self::render(
                'TableRow',
                [
                    'columns' => count($headerCells),
                    'header' => true,
                    'accessibilityLabel' => 'Table header',
                ],
                $headerCells,
                [],
                new Style(
                    widthPercent: 100.0,
                    minHeight: $rowHeight,
                    backgroundColor: ThemeManager::current()->color(
                        ColorToken::SurfaceSunken,
                    ),
                    borderBottomWidth: 1.0,
                    borderColor: ThemeManager::current()->color(
                        ColorToken::Border,
                    ),
                ),
                null,
            );
        }
        $items = array_values($source);
        if (self::flag($props, 'loading')) {
            $rows[] = self::materialDataTableStateRow(
                self::text($props, 'loadingText', 'Loading items'),
                $rowHeight,
                true,
            );
        } elseif ($items === []) {
            $rows[] = self::materialDataTableStateRow(
                self::text($props, 'noDataText', 'No data available'),
                $rowHeight,
                false,
            );
        }
        if (($props['__materialComponent'] ?? null) !== 'PDataTableVirtual') {
            $items = array_slice($items, 0, 256);
        }
        foreach ($items as $rowIndex => $item) {
            $cells = $headers !== [] && is_array($item)
                ? array_map(
                    static fn (array $header): mixed => $item[$header['key']] ?? null,
                    $headers,
                )
                : (is_array($item) ? array_values($item) : [$item]);
            $children = [];
            foreach (array_slice($cells, 0, 16) as $cell) {
                if (!is_scalar($cell) && $cell !== null) {
                    continue;
                }
                $children[] = self::materialDataTableCell(
                    (string) ($cell ?? ''),
                    $rowHeight,
                    false,
                );
            }
            if ($showSelect) {
                $rowValue = is_array($item)
                    ? ($item[$itemValue] ?? $item['value'] ?? $rowIndex)
                    : $item;
                $rowSelected = is_scalar($rowValue)
                    && in_array($rowValue, $selectedValues, true);
                array_unshift(
                    $children,
                    self::materialDataTableSelectionCell(
                        $rowSelected,
                        $rowHeight,
                        'Select row '.($rowIndex + 1),
                        $selectionChange !== null && is_scalar($rowValue)
                            ? self::listSelectionHandler(
                                $selectionChange,
                                $rowValue,
                                $selectedValues,
                            )
                            : null,
                    ),
                );
            }
            if ($children === []) {
                continue;
            }
            $rows[] = self::render(
                'TableRow',
                ['columns' => count($children)],
                $children,
                [],
                new Style(
                    widthPercent: 100.0,
                    minHeight: $rowHeight,
                    backgroundColor: ThemeManager::current()->color(
                        self::flag($props, 'striped') && $rowIndex % 2 === 1
                            ? ColorToken::SurfaceSunken
                            : ColorToken::Surface,
                    ),
                    borderBottomWidth: 1.0,
                    borderColor: ThemeManager::current()->color(
                        ColorToken::Border,
                    ),
                ),
                null,
            );
        }

        return $rows;
    }

    private static function materialDataTableCell(
        string $value,
        float $height,
        bool $header,
    ): Element {
        return \Pam\Native\UI\Column::make(
            self::themedText($value)->style(new Style(
                widthPercent: 100.0,
                fontSize: 14.0,
                lineHeight: 20.0,
                fontWeight: $header ? 600 : 400,
                numberOfLines: 1,
            )),
        )->style(new Style(
            height: $height,
            minHeight: $height,
            paddingHorizontal: 16.0,
            justifyContent: \Pam\Native\Justify::Center,
            flexGrow: 1.0,
        ));
    }

    private static function materialDataTableSelectionCell(
        bool $selected,
        float $height,
        string $label,
        ?Closure $handler,
    ): Element {
        $cell = Pressable::make(
            self::themedText($selected ? '☑' : '☐')->style(new Style(
                width: 24.0,
                fontSize: 20.0,
                lineHeight: 24.0,
                textAlign: TextAlignment::Center,
            )),
        )
            ->style(new Style(
                width: 48.0,
                height: $height,
                minWidth: 48.0,
                minHeight: $height,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))
            ->accessibilityRole(AccessibilityRole::Checkbox)
            ->accessibilityLabel($label)
            ->accessibilityChecked(
                $selected
                    ? AccessibilityCheckedState::Checked
                    : AccessibilityCheckedState::Unchecked,
            );
        if ($handler !== null) {
            $cell = $cell->on(EventKind::Press, $handler);
        }

        return $cell;
    }

    private static function materialDataTableStateRow(
        string $message,
        float $height,
        bool $loading,
    ): Element {
        $children = $loading
            ? [
                ActivityIndicator::make()->color(
                    ThemeManager::current()->color(ColorToken::Primary),
                ),
                self::themedText($message),
            ]
            : [self::themedText($message)];
        $content = Row::make(...$children)
            ->style(new Style(
                widthPercent: 100.0,
                minHeight: $height,
                gap: 12.0,
                paddingHorizontal: 16.0,
                alignItems: Align::Center,
                justifyContent: Justify::Center,
            ))
            ->accessibilityRole(
                $loading
                    ? AccessibilityRole::ProgressBar
                    : AccessibilityRole::Summary,
            )
            ->accessibilityLabel($message);

        return self::render(
            'TableRow',
            ['columns' => 1],
            [$content],
            [],
            new Style(
                widthPercent: 100.0,
                minHeight: $height,
                backgroundColor: ThemeManager::current()->color(
                    ColorToken::Surface,
                ),
            ),
            null,
        );
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function fileTreeFolderChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        if ($part !== 'FileTreeFolder') {
            return $children;
        }
        $path = self::text($props, 'path');
        $name = self::text(
            $props,
            'name',
            $path === '' ? 'Folder' : basename($path),
        );
        $selected = self::flag($props, 'selected') || self::flag($props, 'active');
        $foreground = ThemeManager::current()->color(
            $selected
                ? ColorToken::AccentForeground
                : ColorToken::OnSurface,
        );

        return [
            Row::make(
                self::themedText('›')
                    ->style(new Style(
                        width: 24.0,
                        height: 24.0,
                        fontSize: 20.0,
                        lineHeight: 24.0,
                        textAlign: TextAlignment::Center,
                        textColor: $selected
                            ? $foreground
                            : ThemeManager::current()->color(ColorToken::MutedForeground),
                    ))
                    ->property(PropKey::Value, 'pam:file-tree-chevron'),
                self::themedText($name)
                    ->style(new Style(
                        flexGrow: 1.0,
                        fontSize: 15.0,
                        lineHeight: 20.0,
                        fontWeight: 500,
                        textColor: $foreground,
                    ))
                    ->property(PropKey::Value, 'pam:file-tree-name'),
            )
                ->style(new Style(
                    widthPercent: 100.0,
                    minHeight: 48.0,
                    gap: 8.0,
                    alignItems: Align::Center,
                ))
                ->property(PropKey::Value, 'pam:file-tree-header'),
            Column::make(...$children)
                ->style(new Style(
                    widthPercent: 100.0,
                    paddingLeft: 24.0,
                ))
                ->property(PropKey::Value, 'pam:file-tree-content'),
        ];
    }

    private static function nativeEventAction(string $payload): ?int
    {
        try {
            $values = \Pam\Native\Internal\Wire::decodeMap($payload);
        } catch (\Throwable) {
            return null;
        }
        $action = $values['action'] ?? null;

        return is_int($action) ? $action : null;
    }

    /** @param array<string, mixed> $props */
    private static function input(string $part, array $props): Input
    {
        $syncMode = match ($props['sync'] ?? $props['syncMode'] ?? null) {
            'native', 1 => InputSyncMode::Native,
            'immediate', 3 => InputSyncMode::Immediate,
            'blur', 4 => InputSyncMode::OnBlur,
            'submit', 5 => InputSyncMode::OnSubmit,
            default => InputSyncMode::Debounced,
        };
        $value = $part === 'DateTimePickerInput'
            ? self::dateTimeDisplayValue($props)
            : self::text(
                $props,
                'modelValue',
                self::text($props, 'value'),
            );
        $input = Input::make($value)
            ->placeholder(self::text($props, 'placeholder'))
            ->nativeState(
                $syncMode,
                self::integer($props, 'debounce', 48),
            );
        $accessibilityLabel = $props['accessibilityLabel']
            ?? $props['ariaLabel']
            ?? $props['label']
            ?? null;
        if (is_string($accessibilityLabel) && $accessibilityLabel !== '') {
            $input = $input->accessibilityLabel($accessibilityLabel);
        }

        if (
            $part === 'TextareaInput'
            || $part === 'BottomSheetTextInput'
            || self::flag($props, 'multiline')
        ) {
            $input = $input->multiline();
        }
        if (self::flag($props, 'secure', self::flag($props, 'secureTextEntry'))) {
            $input = $input->secure();
        }
        if (($props['type'] ?? null) === 'password') {
            $input = $input->secure();
        }
        if (isset($props['maxLength'])) {
            $input = $input->maxLength(self::integer($props, 'maxLength', 0));
        }
        if (self::flag($props, 'autoFocus')) {
            $input = $input->autoFocus();
        }

        $keyboard = $props['keyboardType'] ?? $props['keyboard'] ?? null;
        $keyboardType = match ($keyboard) {
            'email', 'email-address', 2 => KeyboardType::Email,
            'number', 'numeric', 'number-pad', 3 => KeyboardType::Number,
            'phone', 'phone-pad', 4 => KeyboardType::Phone,
            'decimal', 'decimal-pad', 5 => KeyboardType::Decimal,
            'url', 6 => KeyboardType::Url,
            default => KeyboardType::Text,
        };
        $input = $input->keyboard($keyboardType);
        $autoComplete = $props['autoComplete'] ?? null;
        if (is_string($autoComplete) && $autoComplete !== '') {
            $input = $input->property(PropKey::AutoComplete, $autoComplete);
        }

        $disabled = self::flag(
            $props,
            'disabled',
            self::flag($props, 'isDisabled'),
        );
        $readOnly = self::flag(
            $props,
            'readonly',
            self::flag($props, 'readOnly', self::flag($props, 'isReadOnly')),
        );
        $input = $input
            ->editable(
                self::flag($props, 'editable', true)
                    && !$disabled
                    && !$readOnly,
            )
            ->autoCorrect(self::flag($props, 'autoCorrect', true))
            ->autoCapitalize(match ($props['autoCapitalize'] ?? null) {
                InputAutoCapitalize::None->value,
                'none' => InputAutoCapitalize::None,
                InputAutoCapitalize::Words->value,
                'words' => InputAutoCapitalize::Words,
                InputAutoCapitalize::Characters->value,
                'characters' => InputAutoCapitalize::Characters,
                default => InputAutoCapitalize::Sentences,
            })
            ->caretHidden(self::flag($props, 'caretHidden'))
            ->contextMenuHidden(self::flag($props, 'contextMenuHidden'))
            ->disableFullscreenUi(
                self::flag(
                    $props,
                    'disableFullscreenUI',
                    isset($props['__materialComponent']),
                ),
            )
            ->autofillImportance(
                self::inputAutofillImportance($props),
            )
            ->selectTextOnFocus(
                self::flag($props, 'selectTextOnFocus'),
            )
            ->showSoftInputOnFocus(
                self::flag($props, 'showSoftInputOnFocus', true),
            )
            ->submitBehavior(self::inputSubmitBehavior($part, $props))
            ->textAlignVertical(self::inputTextAlignVertical($props))
            ->scrollEnabled(self::flag($props, 'scrollEnabled', true));

        if (array_key_exists('inputMode', $props)) {
            $input = $input->inputMode(self::inputMode($props));
        }
        $minimumLines = $props['rows'] ?? $props['minLines'] ?? null;
        if (is_numeric($minimumLines)) {
            $input = $input->minLines((int) $minimumLines);
        }
        $selection = $props['selection'] ?? null;
        if (is_array($selection) && is_numeric($selection['start'] ?? null)) {
            $input = $input->selection(
                (int) $selection['start'],
                is_numeric($selection['end'] ?? null)
                    ? (int) $selection['end']
                    : null,
            );
        }
        $returnLabel = $props['returnKeyLabel'] ?? null;
        if (is_scalar($returnLabel) && (string) $returnLabel !== '') {
            $input = $input->returnKeyLabel((string) $returnLabel);
        }
        $cursorColor = self::packedColor($props['cursorColor'] ?? null);
        if ($cursorColor !== null) {
            $input = $input->cursorColor($cursorColor);
        }
        $underlineColor = self::packedColor(
            $props['underlineColorAndroid'] ?? 0x00000000,
        );
        if ($underlineColor !== null) {
            $input = $input->underlineColor($underlineColor);
        }

        return $input;
    }

    /** @param array<string, mixed> $props */
    private static function inputAutofillImportance(
        array $props,
    ): InputAutofillImportance {
        return match ($props['importantForAutofill'] ?? null) {
            InputAutofillImportance::No->value,
            'no' => InputAutofillImportance::No,
            InputAutofillImportance::NoExcludeDescendants->value,
            'noExcludeDescendants' =>
                InputAutofillImportance::NoExcludeDescendants,
            InputAutofillImportance::Yes->value,
            'yes' => InputAutofillImportance::Yes,
            InputAutofillImportance::YesExcludeDescendants->value,
            'yesExcludeDescendants' =>
                InputAutofillImportance::YesExcludeDescendants,
            default => InputAutofillImportance::Auto,
        };
    }

    /** @param array<string, mixed> $props */
    private static function inputMode(array $props): InputMode
    {
        return match ($props['inputMode'] ?? null) {
            InputMode::None->value, 'none' => InputMode::None,
            InputMode::Decimal->value, 'decimal' => InputMode::Decimal,
            InputMode::Numeric->value, 'numeric' => InputMode::Numeric,
            InputMode::Tel->value, 'tel' => InputMode::Tel,
            InputMode::Search->value, 'search' => InputMode::Search,
            InputMode::Email->value, 'email' => InputMode::Email,
            InputMode::Url->value, 'url' => InputMode::Url,
            default => InputMode::Text,
        };
    }

    /** @param array<string, mixed> $props */
    private static function inputSubmitBehavior(
        string $part,
        array $props,
    ): InputSubmitBehavior {
        $value = $props['submitBehavior'] ?? null;
        if ($value === InputSubmitBehavior::Submit->value || $value === 'submit') {
            return InputSubmitBehavior::Submit;
        }
        if (
            $value === InputSubmitBehavior::Newline->value
            || $value === 'newline'
        ) {
            return InputSubmitBehavior::Newline;
        }
        if (
            $value === InputSubmitBehavior::BlurAndSubmit->value
            || $value === 'blurAndSubmit'
        ) {
            return InputSubmitBehavior::BlurAndSubmit;
        }

        return self::flag($props, 'multiline')
            || in_array(
                $part,
                ['TextareaInput', 'BottomSheetTextInput'],
                true,
            )
            ? InputSubmitBehavior::Newline
            : InputSubmitBehavior::BlurAndSubmit;
    }

    /** @param array<string, mixed> $props */
    private static function inputTextAlignVertical(
        array $props,
    ): InputTextAlignVertical {
        return match ($props['textAlignVertical'] ?? null) {
            InputTextAlignVertical::Top->value,
            'top' => InputTextAlignVertical::Top,
            InputTextAlignVertical::Center->value,
            'center' => InputTextAlignVertical::Center,
            InputTextAlignVertical::Bottom->value,
            'bottom' => InputTextAlignVertical::Bottom,
            default => InputTextAlignVertical::Auto,
        };
    }

    /** @param array<string, mixed> $props */
    private static function dateTimeDisplayValue(array $props): string
    {
        $value = self::text(
            $props,
            'modelValue',
            self::text($props, 'value'),
        );
        $format = self::text($props, 'format');
        if ($value === '' || $format === '') {
            return $value;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return $value;
        }

        return strtr($format, [
            'YYYY' => $date->format('Y'),
            'MM' => $date->format('m'),
            'DD' => $date->format('d'),
            'HH' => $date->format('H'),
            'mm' => $date->format('i'),
            'ss' => $date->format('s'),
        ]);
    }

    /** @param array<string, mixed> $props */
    private static function text(
        array $props,
        string $name,
        string $default = '',
    ): string {
        $value = $props[$name] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @param array<string, mixed> $props */
    private static function integer(array $props, string $name, int $default): int
    {
        $value = $props[$name] ?? $default;

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }

    /** @param array<string, mixed> $props */
    private static function number(
        array $props,
        string $name,
        float $default,
    ): float {
        $value = $props[$name] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    private static function numberInputValue(
        float $value,
        ?int $precision,
    ): string {
        if ($precision !== null) {
            return number_format($value, $precision, '.', '');
        }

        $formatted = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    /** @param array<string, mixed> $props */
    private static function flag(
        array $props,
        string $name,
        bool $default = false,
    ): bool {
        $value = $props[$name] ?? $default;

        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            default => $default,
        };
    }

    private static function sameScalar(mixed $left, mixed $right): bool
    {
        if (!is_scalar($left) || !is_scalar($right)) {
            return false;
        }

        return get_debug_type($left) === get_debug_type($right)
            ? $left === $right
            : (is_numeric($left) && is_numeric($right) && (float) $left === (float) $right);
    }

    /** @param list<Element> $children */
    private static function oneChild(array $children): Element
    {
        return match (count($children)) {
            0 => View::make(),
            1 => $children[0],
            default => Column::make(...$children),
        };
    }

    /** @param array<string, mixed> $props */
    private static function skeletonDurationMillis(array $props): int
    {
        $speed = self::integer($props, 'speed', 3);

        return match ($speed) {
            1 => 750,
            2 => 100,
            4 => 2_000,
            default => 1_500,
        };
    }

    /** @param array<string, mixed> $props */
    private static function configuredList(Element $list, array $props): Element
    {
        $list = $list->property(
            PropKey::TextColor,
            ThemeManager::current()->color(ColorToken::Foreground),
        );
        $rowHeight = $props['rowHeight']
            ?? $props['itemHeight']
            ?? $props['estimatedItemSize']
            ?? null;
        if (is_numeric($rowHeight)) {
            $list = $list->property(
                PropKey::ListRowHeight,
                max(1.0, (float) $rowHeight),
            );
        }
        $prefetch = $props['prefetch']
            ?? $props['maxToRenderPerBatch']
            ?? $props['initialNumToRender']
            ?? null;
        if (is_numeric($prefetch)) {
            $list = $list->property(
                PropKey::ListPrefetch,
                min(32, max(1, (int) $prefetch)),
            );
        }
        if (array_key_exists('horizontal', $props)) {
            $list = $list->property(
                PropKey::ListHorizontal,
                self::flag($props, 'horizontal'),
            );
        }
        if (is_numeric($props['numColumns'] ?? null)) {
            $list = $list->property(
                PropKey::ListNumColumns,
                max(1, (int) $props['numColumns']),
            );
        }
        if (array_key_exists('inverted', $props)) {
            $list = $list->property(
                PropKey::ListInverted,
                self::flag($props, 'inverted'),
            );
        }
        if (is_numeric($props['initialScrollIndex'] ?? null)) {
            $list = $list->property(
                PropKey::ListInitialScrollIndex,
                max(0, (int) $props['initialScrollIndex']),
            );
        }
        if (array_key_exists('removeClippedSubviews', $props)) {
            $list = $list->property(
                PropKey::ListRemoveClippedSubviews,
                self::flag($props, 'removeClippedSubviews', true),
            );
        }
        if (
            array_key_exists('scrollEnabled', $props)
            || array_key_exists('isScrollEnabled', $props)
        ) {
            $list = $list->property(
                PropKey::ScrollEnabled,
                self::flag(
                    $props,
                    'scrollEnabled',
                    self::flag($props, 'isScrollEnabled', true),
                ),
            );
        }
        if (
            array_key_exists('showsVerticalScrollIndicator', $props)
            || array_key_exists('showsScrollIndicator', $props)
        ) {
            $list = $list->property(
                PropKey::ShowsScrollIndicator,
                self::flag(
                    $props,
                    'showsVerticalScrollIndicator',
                    self::flag($props, 'showsScrollIndicator', true),
                ),
            );
        }
        $threshold = $props['onEndReachedThreshold']
            ?? $props['endReachedThreshold']
            ?? null;
        if (is_numeric($threshold)) {
            $list = $list->property(
                PropKey::EndReachedThreshold,
                min(1.0, max(0.0, (float) $threshold)),
            );
        }

        return $list;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
            $value,
        ));
    }

    private static function scalarString(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable
            ? (string) $value
            : '';
    }

    private static function scalarInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function scalarFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** @return array<string, list<string>> */
    private static function sections(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $sections = [];
        foreach ($value as $title => $items) {
            if (is_string($title) && is_array($items)) {
                $sections[$title] = self::stringList($items);
            }
        }

        return $sections;
    }
}
