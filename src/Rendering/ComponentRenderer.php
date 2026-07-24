<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Closure;
use InvalidArgumentException;
use Pam\MobileUi\Enum\BranchControlAction;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ImageViewerControlAction;
use Pam\MobileUi\Enum\InputSlotAction;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Enum\SelectionMode;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\InputSyncMode;
use Pam\Native\ImageFit;
use Pam\Native\KeyboardAvoidingBehavior;
use Pam\Native\KeyboardType;
use Pam\Native\ModalPresentation;
use Pam\Native\NodeKind;
use Pam\Native\PositionType;
use Pam\Native\PropKey;
use Pam\Native\StatusBarAppearance;
use Pam\Native\Style;
use Pam\Native\UI\ActivityIndicator;
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
        if (!isset(ComponentMap::IDS[$part])) {
            throw new InvalidArgumentException("Unknown PAM Mobile UI component {$part}.");
        }

        $props = self::withDefaults($part, $props);
        $children = self::fallbackChildren($part, $props, $children);
        if (self::isTransparentProvider($part)) {
            $provider = self::transparentChildren($children);
            if ($elementKey !== null) {
                $provider = $provider->key($elementKey);
            }

            return $provider;
        }
        if ($part === 'ModelSelector') {
            $selector = View::make(...self::modelSelectorRootChildren($children))
                ->collapsable();
            if ($elementKey !== null) {
                $selector = $selector->key($elementKey);
            }

            return $selector;
        }
        if (
            in_array($part, ['Skeleton', 'SkeletonText'], true)
            && self::flag($props, 'isLoaded')
        ) {
            $loaded = self::oneChild($children);
            if ($children === []) {
                $loaded = $loaded->visible(false);
            }
            if ($elementKey !== null) {
                $loaded = $loaded->key($elementKey);
            }

            return $loaded;
        }
        if (in_array($part, ['Skeleton', 'SkeletonText'], true)) {
            $children = [];
        }
        $children = self::imageViewerChildren($part, $props, $children);
        $children = self::messageBranchChildren($part, $children);
        $children = self::messageResponseChildren($part, $props, $children);
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
        $runtimeProps = [...$parentProps, ...$props];
        $events = self::componentEvents($part, $runtimeProps, $events);
        $children = self::modelSelectorChildren(
            $part,
            $runtimeProps,
            $children,
        );
        $children = self::formControlChildren($part, $runtimeProps, $children);
        $children = self::inputEventChildren($part, $events, $children);
        $children = self::messageResponseEventChildren($part, $events, $children);
        $style = StyleResolver::resolve(
            $part === 'ModelSelectorContent' ? 'ModalContent' : $part,
            $props,
            ThemeManager::current(),
        );
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
            $runtimeProps['outlineWidth'] = max(
                1.0,
                $overrideWidth
                    ?? $style->borderWidth
                    ?? 1.0,
            );
        }
        $styleAppliedToContent = false;
        $rootStyle = $style;
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
        $element = $part === 'SkeletonText'
            && self::integer(
                $runtimeProps,
                '_lines',
                self::integer($runtimeProps, 'lines', 1),
            ) > 1
                ? self::skeletonText(
                    $runtimeProps,
                    $rootStyle,
                    $styleOverride,
                    $nativeBackground,
                )
                : self::primitive(
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
            $element = $element->property(PropKey::Value, 'pam:selection-indicator');
        } elseif ($part === 'CheckboxIcon' || $part === 'RadioIcon') {
            $element = $element->property(
                PropKey::Value,
                self::flag($props, 'forceMount')
                    ? 'pam:selection-icon-force'
                    : 'pam:selection-icon',
            );
        } elseif (in_array($part, [
            'ActionsheetBackdrop',
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
            'ActionsheetContent',
            'AttachmentHoverCardContent',
            'BottomSheetContent',
            'PopoverContent',
            'PromptInputActionMenuContent',
            'SelectContent',
            'TooltipContent',
        ], true)) {
            $element = $element->property(PropKey::Value, 'pam:overlay-content');
        } elseif ($part === 'PopoverArrow') {
            $element = $element->property(PropKey::Value, 'pam:overlay-arrow');
        } elseif (in_array($part, [
            'ActionsheetDragIndicator',
            'BottomSheetDragIndicator',
            'SelectDragIndicator',
        ], true)) {
            $element = $element->property(PropKey::Value, 'pam:sheet-drag-indicator');
        } elseif (in_array($part, [
            'ActionsheetDragIndicatorWrapper',
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
                ->property(PropKey::PositionType, PositionType::Absolute->value)
                ->property(PropKey::Left, 0.0)
                ->property(PropKey::Top, 0.0);
        } elseif ($part === 'SliderTrack') {
            $element = $element->property(PropKey::Value, 'pam:slider-track');
        } elseif ($part === 'SliderFilledTrack') {
            $element = $element->property(PropKey::Value, 'pam:slider-filled-track');
        } elseif ($part === 'SliderThumb') {
            $element = $element->property(PropKey::Value, 'pam:slider-thumb');
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
        } elseif ($part === 'ConversationContent') {
            $element = $element->property(
                PropKey::Value,
                'pam:conversation-content',
            );
        } elseif ($part === 'Message') {
            $role = self::integer($runtimeProps, 'role', 2);
            $element = $element->property(
                PropKey::Value,
                'pam:message:'.max(1, min(3, $role)),
            );
        } elseif ($part === 'MessageBranchSelector') {
            $element = $element->property(
                PropKey::Value,
                'pam:message-branch-selector',
            );
        } elseif ($part === 'MessageBranchPage') {
            $element = $element->property(
                PropKey::Value,
                'pam:message-branch-counter',
            );
        } elseif ($part === 'Attachment') {
            $element = $element->property(
                PropKey::Value,
                'pam:prompt-attachment',
            );
        } elseif ($part === 'GridItem') {
            $element = $element->property(
                PropKey::Value,
                self::gridItemTag($props),
            );
        }

        if ($styleOverride !== null && !$styleAppliedToContent) {
            $element = $element->style($styleOverride);
        }
        $disabled = self::flag($props, 'disabled', self::flag($props, 'isDisabled'));
        $readOnly = self::flag(
            $props,
            'readOnly',
            self::flag($props, 'isReadOnly'),
        ) || (
            array_key_exists('editable', $props)
            && !self::flag($props, 'editable', true)
        );
        $nativeReadOnly = in_array(
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
        if ($disabled || ($readOnly && !$nativeReadOnly)) {
            $element = $element->enabled(false);
        }
        $shouldHide = (
            self::flag($props, 'hidden')
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
                $part === 'MessageToolbar'
                && (
                    self::integer($runtimeProps, 'role', 2) === 1
                    || self::messageHasNoText($runtimeProps)
                )
            )
            || (
                self::isClosed($runtimeProps)
                && (
                    self::hidesEntireRootWhenClosed($part)
                    || self::isOverlayContent($part)
                )
            )
        );
        if ($shouldHide) {
            $element = $element->visible(false);
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
        if ($role !== AccessibilityRole::Generic) {
            $element = $element->accessibilityRole($role);
        }
        if (isset($props['testId']) && is_string($props['testId'])) {
            $element = $element->testId($props['testId']);
        }
        if (self::flag($props, 'selected', self::flag($props, 'isSelected'))) {
            $element = $element->property(PropKey::Selected, true);
        }
        if (self::flag($props, 'checked', self::flag($props, 'isChecked'))) {
            $element = $element->property(PropKey::Checked, true);
        }
        if (self::flag($props, 'loading', self::flag($props, 'isLoading'))) {
            $element = $element->property(PropKey::Loading, true);
        }
        foreach ($events as $event => $handler) {
            $kind = EventKind::from($event);
            $element = $element->on($kind, $handler);
        }

        if (self::usesNativeWindow($part)) {
            $element = NativeModal::make(
                $element,
                !$shouldHide,
                self::modalPresentation($part, $runtimeProps),
            );
            $nativeHandler = $events[EventKind::Native->value] ?? null;
            if ($nativeHandler !== null) {
                $element = $element->on(EventKind::Native, $nativeHandler);
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
        if ($part === 'SkeletonText') {
            return CustomView::make(
                'pam.mobile_ui.host',
                self::nativeProperties(
                    $part,
                    NativeBehavior::Skeleton,
                    $props,
                    [],
                    $nativeBackground,
                ),
            );
        }
        if (self::isText($part)) {
            return Text::make(
                self::text(
                    $props,
                    'text',
                    $part === 'MessageBranchPage' ? '1 of 1' : '',
                ),
            );
        }
        if (self::isInput($part)) {
            return self::input($part, $props);
        }
        if (self::isIcon($part)) {
            return CustomView::make(
                'pam.mobile_ui.icon',
                self::iconProperties($part, $props),
            );
        }
        if (self::isImage($part)) {
            $image = Image::make(
                self::text($props, 'source', self::text($props, 'src')),
            )->fit(self::imageFit($props));
            $tint = self::packedColor($props['tintColor'] ?? null);

            return $tint === null ? $image : $image->tint($tint);
        }
        if ($part === 'ImageBackground') {
            return ImageBackground::make(
                self::text($props, 'source', self::text($props, 'src')),
                ...$children,
            )->fit(self::imageFit($props));
        }
        if ($part === 'Spinner' || $part === 'ButtonSpinner') {
            $spinner = ActivityIndicator::make(($props['visible'] ?? true) !== false);
            $color = self::packedColor($props['color'] ?? null);

            return $color === null ? $spinner : $spinner->color($color);
        }
        if (
            $part === 'Attachments'
            || (
                in_array($part, [
                    'ScrollView',
                    'ActionsheetScrollView',
                    'BottomSheetScrollView',
                    'SelectScrollView',
                ], true)
                && self::flag($props, 'horizontal')
            )
        ) {
            return self::horizontalScroll($part, $props, $children);
        }
        if (in_array($part, [
            'FlatList',
            'VirtualizedList',
            'ActionsheetFlatList',
            'ActionsheetVirtualizedList',
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
            'ActionsheetSectionList',
            'BottomSheetSectionList',
            'SelectSectionList',
        ], true)) {
            return self::configuredList(
                SectionList::make(self::sections($props['sections'] ?? [])),
                $props,
            );
        }
        if ($part === 'MessageResponse') {
            return count($children) === 1
                ? $children[0]
                : Column::make(...$children);
        }
        if ($part === 'ConversationContent') {
            return Scroll::make(self::oneChild($children))
                ->scrollEnabled(self::flag($props, 'scrollEnabled', true))
                ->showsIndicator(
                    self::flag(
                        $props,
                        'showsVerticalScrollIndicator',
                        false,
                    ),
                );
        }
        if (in_array($part, [
            'ScrollView',
            'ActionsheetScrollView',
            'BottomSheetScrollView',
            'SelectScrollView',
        ], true)) {
            return Scroll::make(self::oneChild($children))
                ->scrollEnabled(self::flag($props, 'scrollEnabled', true))
                ->showsIndicator(
                    self::flag(
                        $props,
                        'showsVerticalScrollIndicator',
                        self::flag($props, 'showsScrollIndicator', true),
                    ),
                );
        }
        if ($part === 'RefreshControl') {
            return RefreshControl::make(
                self::oneChild($children),
                self::flag($props, 'refreshing'),
            );
        }
        if ($part === 'SafeAreaView') {
            return SafeAreaView::make(...$children);
        }
        if ($part === 'KeyboardAvoidingView') {
            return KeyboardAvoidingView::make(
                self::oneChild($children),
                self::keyboardAvoidingBehavior($props),
            );
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
            );
        }
        if ($part === 'Grid') {
            return self::grid($props, $children);
        }
        if (
            $part === 'ModelSelectorTrigger'
            && self::flag($props, 'asChild')
            && count($children) === 1
        ) {
            return $children[0];
        }

        $behavior = self::nativeBehavior($part);
        if ($behavior !== NativeBehavior::Container) {
            return CustomView::make(
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
        }
        if ($part === 'HStack' || $part === 'ButtonGroup' || $part === 'AvatarGroup') {
            return Row::make(...$children);
        }
        if ($part === 'VStack' || $part === 'Conversation' || $part === 'FileTree') {
            return Column::make(...$children);
        }
        if (self::isPressable($part)) {
            $theme = ThemeManager::current();

            return Pressable::make(...$children)
                ->ripple($theme->color(ColorToken::Accent))
                ->pressedOpacity(0.88)
                ->property(
                    PropKey::HitSlop,
                    max(0, self::integer($props, 'hitSlop', 8)),
                )
                ->accessibilityRole(AccessibilityRole::Button);
        }

        return View::make(...$children);
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
            'part' => ComponentMap::IDS[$part],
            'component' => ComponentMap::IDS[$part],
            'behavior' => $behavior->value,
            'theme' => ThemeManager::resolvedMode()->value,
            'trackColor' => ThemeManager::current()->color(ColorToken::Muted),
            'fillColor' => ThemeManager::current()->color(ColorToken::Primary),
            'foregroundColor' => ThemeManager::current()->color(ColorToken::Foreground),
            'selectedForegroundColor' => ThemeManager::current()->color(
                ColorToken::PrimaryForeground,
            ),
        ];

        foreach ($props as $name => $value) {
            if (
                preg_match('/^[A-Za-z][A-Za-z0-9_]{0,254}$/D', $name) === 1
                && (is_string($value) || is_int($value) || is_float($value) || is_bool($value))
            ) {
                $values[$name] = $value;
            }
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
            $values['pulseDuration'] = self::skeletonDurationMillis($part, $props);
            $values['lines'] = max(
                1,
                self::integer(
                    $props,
                    '_lines',
                    self::integer($props, 'lines', 1),
                ),
            );
        }
        if ($behavior === NativeBehavior::MessageBranchControl) {
            $values['navigationAction'] = match ($part) {
                'MessageBranchNext' => BranchControlAction::Next->value,
                default => BranchControlAction::Previous->value,
            };
        }
        if ($behavior === NativeBehavior::FileTree) {
            $values = [...$values, ...self::fileTreeNativeProperties($props)];
        }

        return $values;
    }

    public static function nativeBehavior(string $part): NativeBehavior
    {
        return match ($part) {
            'Conversation' => NativeBehavior::Chat,
            'ConversationScrollButton' => NativeBehavior::ConversationScrollButton,
            'FileTree' => NativeBehavior::FileTree,
            'FileTreeFolder' => NativeBehavior::FileTreeFolder,
            'FileTreeFile' => NativeBehavior::FileTreeFile,
            'MessageBranch' => NativeBehavior::MessageBranch,
            'MessageBranchPrevious',
            'MessageBranchNext' => NativeBehavior::MessageBranchControl,
            'PromptInput' => NativeBehavior::PromptInput,
            'PromptInputSubmit' => NativeBehavior::PromptInputSubmit,
            'AttachmentHoverCard' => NativeBehavior::Tooltip,
            'PromptInputActionMenu' => NativeBehavior::Menu,
            'Accordion' => NativeBehavior::AccordionGroup,
            'AccordionItem' => NativeBehavior::Accordion,
            'CheckboxGroup' => NativeBehavior::CheckboxGroup,
            'RadioGroup' => NativeBehavior::RadioGroup,
            'Switch' => NativeBehavior::SwitchControl,
            'Actionsheet',
            'BottomSheetPortal',
            'SelectPortal' => NativeBehavior::BottomSheet,
            'Slider' => NativeBehavior::Slider,
            'Tabs' => NativeBehavior::Tabs,
            'TabsTrigger' => NativeBehavior::TabsTrigger,
            'ActionsheetItem',
            'BottomSheetItem',
            'SelectItem' => NativeBehavior::SheetItem,
            'MenuItem' => NativeBehavior::MenuItem,
            'AlertDialogCloseButton',
            'DrawerCloseButton',
            'ImageViewerCloseButton',
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
            'Skeleton', 'SkeletonText' => NativeBehavior::Skeleton,
            'GlassView', 'GlassContainer' => NativeBehavior::Glass,
            'Checkbox' => NativeBehavior::Checkbox,
            'Radio' => NativeBehavior::Radio,
            'Toast' => NativeBehavior::Toast,
            'ImageViewerContent' => NativeBehavior::ImageViewer,
            'Progress' => NativeBehavior::Progress,
            'Drawer' => NativeBehavior::Drawer,
            'Modal', 'ModelSelectorContent' => NativeBehavior::Modal,
            'AlertDialog' => NativeBehavior::AlertDialog,
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
        if ($part === 'TableHeader') {
            $props['isHeaderRow'] = true;
        } elseif ($part === 'TableFooter') {
            $props['isFooterRow'] = true;
        }
        if ($part === 'GluestackUIProvider' && !array_key_exists('mode', $props)) {
            $props['mode'] = 'system';
        }
        if ($part === 'ModelSelector') {
            if (
                !array_key_exists('open', $props)
                && !array_key_exists('isOpen', $props)
                && !array_key_exists('defaultIsOpen', $props)
            ) {
                $props['defaultIsOpen'] = false;
            }
            if (!array_key_exists('size', $props)) {
                $props['size'] = 3;
            }
        } elseif ($part === 'ModelSelectorContent') {
            if (!array_key_exists('title', $props)) {
                $props['title'] = 'Model Selector';
            }
            if (!array_key_exists('accessibilityLabel', $props)) {
                $props['accessibilityLabel'] = self::text(
                    $props,
                    'title',
                    'Model Selector',
                );
            }
        }
        if ($part === 'Conversation' && !array_key_exists('autoScroll', $props)) {
            $props['autoScroll'] = true;
        } elseif ($part === 'SelectInput') {
            if (!array_key_exists('editable', $props)) {
                $props['editable'] = false;
            }
        } elseif ($part === 'ModelSelectorInput') {
            if (!array_key_exists('placeholder', $props)) {
                $props['placeholder'] = 'Search models...';
            }
        } elseif ($part === 'MessageBranch') {
            if (
                !array_key_exists('branch', $props)
                && !array_key_exists('currentBranch', $props)
                && !array_key_exists('defaultBranch', $props)
            ) {
                $props['defaultBranch'] = 0;
            }
            if (!array_key_exists('loop', $props)) {
                $props['loop'] = true;
            }
        } elseif ($part === 'PromptInput') {
            if (!array_key_exists('clearOnSubmit', $props)) {
                $props['clearOnSubmit'] = true;
            }
            if (!array_key_exists('trimOnSubmit', $props)) {
                $props['trimOnSubmit'] = true;
            }
        }
        if (!in_array(
            $part,
            [
                'AttachmentHoverCard',
                'Menu',
                'Popover',
                'PromptInputActionMenu',
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
                'Menu', 'PromptInputActionMenu' => Placement::BottomStart->value,
                default => Placement::Bottom->value,
            };
        }
        if (
            in_array($part, ['Menu', 'PromptInputActionMenu'], true)
            && !array_key_exists('selectionMode', $props)
        ) {
            $props['selectionMode'] = array_key_exists('selectedKeys', $props)
                ? SelectionMode::Single->value
                : SelectionMode::None->value;
        }
        if (
            in_array($part, ['AttachmentHoverCard', 'Tooltip'], true)
            && !array_key_exists('trapFocus', $props)
        ) {
            $props['trapFocus'] = false;
        }

        return $props;
    }

    public static function forwardsEventsToDescendants(string $part): bool
    {
        return in_array($part, [
            'Accordion',
            'BottomSheet',
            'CheckboxGroup',
            'Menu',
            'ModelSelector',
            'RadioGroup',
            'Select',
            'ImageViewer',
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
        if ($source === 'ImageViewer') {
            if ($target === 'ImageViewerTrigger') {
                $toggle = $events[EventKind::Toggle->value] ?? null;
                $press = $events[EventKind::Press->value] ?? null;
                if ($toggle === null && $press === null) {
                    return [];
                }

                return [
                    EventKind::Press->value => static function () use (
                        $toggle,
                        $press,
                    ): void {
                        $press?->__invoke();
                        $toggle?->__invoke(true);
                    },
                ];
            }
            if ($target === 'ImageViewerContent') {
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
        if ($source === 'ModelSelector') {
            if ($target === 'ModelSelectorTrigger') {
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
            if ($target === 'ModelSelectorContent') {
                $inherited = $events;
                $native = $events[EventKind::Native->value] ?? null;
                $toggle = $events[EventKind::Toggle->value] ?? null;
                if ($toggle !== null) {
                    $inherited[EventKind::Native->value] =
                        static function (string $payload) use (
                            $native,
                            $toggle,
                        ): void {
                            $native?->__invoke($payload);
                            if (self::nativeEventAction($payload) === 1) {
                                $toggle(false);
                            }
                        };
                }

                return $inherited;
            }
        }
        if (
            ($source === 'BottomSheet' && $target === 'BottomSheetPortal')
        ) {
            return $events;
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

        if (
            ($source === 'Select' && $target === 'SelectItem')
            || ($source === 'ModelSelector' && $target === 'ModelSelectorItem')
        ) {
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
        $explicit = $props['accessibilityRole'] ?? null;
        if (is_int($explicit)) {
            return AccessibilityRole::tryFrom($explicit) ?? AccessibilityRole::Generic;
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

    /** @param array<string, mixed> $props */
    private static function isClosed(array $props): bool
    {
        if (
            !array_key_exists('open', $props)
            && !array_key_exists('isOpen', $props)
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
                self::flag($props, 'defaultIsOpen'),
            ),
        );
    }

    private static function hidesEntireRootWhenClosed(string $part): bool
    {
        return in_array($part, [
            'Actionsheet',
            'AlertDialog',
            'Drawer',
            'Modal',
            'Toast',
        ], true);
    }

    private static function isOverlayContent(string $part): bool
    {
        return in_array($part, [
            'ActionsheetBackdrop',
            'ActionsheetContent',
            'AlertDialogBackdrop',
            'AlertDialogContent',
            'AttachmentHoverCardContent',
            'BottomSheetBackdrop',
            'BottomSheetContent',
            'BottomSheetPortal',
            'DrawerBackdrop',
            'DrawerContent',
            'ImageViewerContent',
            'ModalBackdrop',
            'ModalContent',
            'ModelSelectorContent',
            'PopoverBackdrop',
            'PopoverContent',
            'Portal',
            'PromptInputActionMenuContent',
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
        $overlayPart = match ($part) {
            'AttachmentHoverCard' => 'Tooltip',
            'PromptInputActionMenu' => 'Menu',
            default => $part,
        };
        if (
            !in_array($overlayPart, ['Menu', 'Popover', 'Tooltip'], true)
            || $children === []
        ) {
            return $children;
        }

        $closed = self::isClosed($props);
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
                ->property(PropKey::Value, 'pam:overlay-content');
            if ($closed) {
                $content = $content->visible(false);
            }

            return [$trigger, $content];
        }

        $triggerMarked = false;
        foreach ($children as $index => $child) {
            $tag = $child->properties()[PropKey::Value->value] ?? null;
            if (
                $tag === 'pam:overlay-content'
                || $tag === 'pam:overlay-backdrop'
                || (
                    is_string($tag)
                    && str_starts_with($tag, 'pam:overlay-backdrop:')
                )
            ) {
                if ($closed) {
                    $children[$index] = $child->visible(false);
                }
                continue;
            }
            if (!$triggerMarked) {
                $children[$index] = $child->property(
                    PropKey::Value,
                    'pam:overlay-trigger',
                );
                $triggerMarked = true;
            }
        }

        return $children;
    }

    private static function usesNativeWindow(string $part): bool
    {
        return in_array($part, [
            'Actionsheet',
            'AlertDialog',
            'BottomSheetPortal',
            'Drawer',
            'ImageViewerContent',
            'Modal',
            'ModelSelectorContent',
            'Portal',
            'SelectPortal',
        ], true);
    }

    /** @param array<string, mixed> $props */
    private static function modalPresentation(
        string $part,
        array $props,
    ): ModalPresentation {
        $requested = $props['presentation'] ?? null;
        if ($requested === 1 || $requested === 'fullScreen') {
            return ModalPresentation::FullScreen;
        }
        if ($requested === 3 || $requested === 'sheet') {
            return ModalPresentation::Sheet;
        }

        return match ($part) {
            'Actionsheet',
            'BottomSheetPortal',
            'SelectPortal' => ModalPresentation::Sheet,
            'Drawer',
            'ImageViewerContent',
            'Portal' => ModalPresentation::FullScreen,
            default => ModalPresentation::Dialog,
        };
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
                'MessageBranchPage',
                'ModelSelectorName',
                'TableHead',
                'TableData',
            ], true);
    }

    private static function isInput(string $part): bool
    {
        return in_array($part, [
            'InputField',
            'TextareaInput',
            'DateTimePickerInput',
            'BottomSheetTextInput',
            'ModelSelectorInput',
            'PromptInputTextarea',
            'SelectInput',
        ], true);
    }

    private static function isImage(string $part): bool
    {
        return in_array($part, ['Image', 'AvatarImage', 'ImageViewerImage'], true);
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
        $icon = is_string($requested) && isset(ComponentMap::IDS[$requested])
            ? ComponentMap::IDS[$requested]
            : ComponentMap::IDS[$part];
        $color = $props['color'] ?? ThemeManager::current()->color(ColorToken::Foreground);

        return [
            'icon' => $icon,
            'color' => is_int($color) ? $color : ThemeManager::current()->color(ColorToken::Foreground),
        ];
    }

    private static function isPressable(string $part): bool
    {
        return in_array($part, [
            'AttachmentRemove',
            'Button',
            'CalendarDay',
            'CalendarHeaderMonthSelect',
            'CalendarHeaderNextButton',
            'CalendarHeaderPrevButton',
            'CalendarHeaderYearSelect',
            'ConversationDownload',
            'Fab',
            'Link',
            'MessageAction',
            'Pressable',
            'PromptInputButton',
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
                'BottomSheetItem',
                'Checkbox',
                'MenuItem',
                'ModelSelectorItem',
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

        $parentValue = match ($part) {
            'MenuItem' => $parentProps['selectedKeys'] ?? null,
            'SelectItem' => $parentProps['selectedValue'] ?? $parentProps['value'] ?? null,
            'AccordionItem' => $parentProps['value'] ?? $parentProps['defaultValue'] ?? null,
            'Checkbox', 'Radio' => $parentProps['value'] ?? $parentProps['defaultValue'] ?? null,
            default => $parentProps['value'] ?? $parentProps['defaultValue'] ?? null,
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
            'ModelSelectorItem',
            'TabsTrigger' => [...$props, 'selected' => $selected],
            default => $props,
        };
    }

    private static function isTransparentProvider(string $part): bool
    {
        return in_array($part, [
            'BlankContext',
            'BlankProvider',
            'PromptInputProvider',
        ], true);
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
     * Upstream extracts modal content from the trigger fragment and mounts it
     * last. Keeping the same order also restores focus to a mounted trigger.
     *
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function modelSelectorRootChildren(array $children): array
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

    /**
     * Synthesizes the same fixed anatomy as gluestack's ModelSelectorContent
     * and ModelSelectorGroup while keeping authored list/items untouched.
     *
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function modelSelectorChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        if ($part === 'ModelSelectorGroup') {
            $heading = self::text($props, 'heading');
            if ($heading === '') {
                return $children;
            }

            $headingElement = Text::make($heading)
                ->style(
                    StyleResolver::resolve(
                        'Text',
                        [
                            'className' =>
                                'px-4 py-2 text-sm font-semibold text-muted-foreground',
                        ],
                        ThemeManager::current(),
                    ),
                )
                ->property(PropKey::Value, 'pam:model-selector-heading');

            return [$headingElement, ...$children];
        }
        if ($part !== 'ModelSelectorContent') {
            return $children;
        }

        $title = self::text($props, 'title', 'Model Selector');
        $titleElement = Text::make($title)
            ->style(
                StyleResolver::resolve(
                    'Text',
                    ['className' => 'sr-only'],
                    ThemeManager::current(),
                ),
            );
        $closeIcon = self::render(
            'CloseIcon',
            ['size' => 4],
            [],
            [],
            null,
            null,
        );
        $close = self::render(
            'ModalCloseButton',
            ['accessibilityLabel' => 'Close model selector'],
            [$closeIcon],
            [],
            null,
            null,
        );
        $header = self::render(
            'ModalHeader',
            [],
            [$titleElement, $close],
            [],
            null,
            null,
        );
        $body = self::render(
            'ModalBody',
            [],
            $children,
            [],
            null,
            null,
        );
        $scroll = Scroll::make($body)
            ->showsIndicator(true)
            ->style(new Style(maxHeight: 500.0));

        return [$header, $scroll];
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
                $next = array_values(
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
        $value = $props['value'] ?? $props['defaultValue'] ?? [];
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
     * @param array<string, mixed> $props
     * @param array<int, Closure> $events
     * @return array<int, Closure>
     */
    private static function componentEvents(
        string $part,
        array $props,
        array $events,
    ): array {
        if ($part !== 'Calendar') {
            return $events;
        }
        $handler = $events[EventKind::Change->value] ?? null;
        $mode = $props['mode'] ?? $props['type'] ?? 1;
        if ($handler === null || !is_int($mode) || $mode === 1) {
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

        foreach ($children as $index => $child) {
            if ($child->kind() !== NodeKind::Input) {
                continue;
            }
            foreach ([
                EventKind::Change,
                EventKind::Focus,
                EventKind::Blur,
                EventKind::Submit,
            ] as $kind) {
                $handler = $events[$kind->value] ?? null;
                if ($handler !== null) {
                    $child = $child->on($kind, $handler);
                }
            }
            $children[$index] = $child;
            break;
        }

        return $children;
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

        $readOnly = self::flag(
            $props,
            'readOnly',
            self::flag($props, 'isReadOnly'),
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
                ? max(1.0, (float) $props['outlineWidth'])
                : 1.0,
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
            $trackColors = is_array($props['trackColor'] ?? null)
                ? $props['trackColor']
                : [];
            $offColor = self::packedColor(
                $trackColors['false']
                    ?? $trackColors[0]
                    ?? $props['ios_backgroundColor']
                    ?? null,
            );
            $onColor = self::packedColor(
                $trackColors['true']
                    ?? $trackColors[1]
                    ?? null,
            );
            $thumbColor = self::packedColor($props['thumbColor'] ?? null);
            $activeThumbColor = self::packedColor(
                $props['activeThumbColor'] ?? $props['thumbColor'] ?? null,
            );
            if ($offColor !== null) {
                $values['trackOffColor'] = $offColor;
            }
            if ($onColor !== null) {
                $values['trackOnColor'] = $onColor;
            }
            if ($thumbColor !== null) {
                $values['thumbColor'] = $thumbColor;
            }
            if ($activeThumbColor !== null) {
                $values['activeThumbColor'] = $activeThumbColor;
            }

            return $values;
        }

        if (
            !array_key_exists('value', $props)
            && is_numeric($props['defaultValue'] ?? null)
        ) {
            $values['value'] = (float) $props['defaultValue'];
        }
        if (is_numeric($props['minValue'] ?? null)) {
            $values['min'] = (float) $props['minValue'];
        }
        if (is_numeric($props['maxValue'] ?? null)) {
            $values['max'] = (float) $props['maxValue'];
        }

        if ($behavior === NativeBehavior::Progress) {
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

        $orientation = $props['orientation'] ?? 1;
        $trackDimension = $orientation === 2
            ? PropKey::Width
            : PropKey::Height;
        $thumbDimension = $orientation === 2
            ? PropKey::Height
            : PropKey::Width;
        $trackThickness = is_numeric($props['sliderTrackHeight'] ?? null)
            ? (float) $props['sliderTrackHeight']
            : self::elementNumber($track, $trackDimension);
        $thumbSize = is_numeric($props['thumbSize'] ?? null)
            ? (float) $props['thumbSize']
            : self::elementNumber($thumb, $thumbDimension);
        if ($trackThickness !== null) {
            $values['trackThickness'] = $trackThickness;
        }
        if ($thumbSize !== null) {
            $values['thumbSize'] = $thumbSize;
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
        return match (
            $props['resizeMode']
                ?? $props['fit']
                ?? $props['objectFit']
                ?? null
        ) {
            ImageFit::Contain->value, 'contain' => ImageFit::Contain,
            ImageFit::Fill->value, 'fill', 'stretch' => ImageFit::Fill,
            ImageFit::Center->value, 'center', 'none' => ImageFit::Center,
            default => ImageFit::Cover,
        };
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
        string $part,
        array $props,
        array $children,
    ): Element {
        $listAttachments = $part === 'Attachments'
            && self::integer($props, 'variant', 15) === 17;
        $content = $listAttachments
            ? Column::make(...$children)
            : Row::make(...$children);

        return CustomView::make(
            'pam.mobile_ui.horizontal_scroll',
            [
                'scrollEnabled' => self::flag($props, 'scrollEnabled', true),
                'showsIndicator' => self::flag(
                    $props,
                    'showsHorizontalScrollIndicator',
                    self::flag(
                        $props,
                        'showsScrollIndicator',
                        $part !== 'Attachments',
                    ),
                ),
                'fillViewport' => self::flag($props, 'fillViewport', true),
                'nestedScrollEnabled' => self::flag(
                    $props,
                    'nestedScrollEnabled',
                    true,
                ),
                'contentOffset' => max(
                    0.0,
                    self::number($props, 'contentOffset', 0.0),
                ),
                'overScrollMode' => self::text(
                    $props,
                    'overScrollMode',
                    'auto',
                ),
            ],
            $content,
        );
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
            ) + ($rowGaps[0] ?? 0.0) * max(0, count($children) - 1);
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
        $properties = $child->properties();
        $height = $properties[PropKey::Height->value]
            ?? $properties[PropKey::MinHeight->value]
            ?? 48.0;

        return is_numeric($height) ? max(0.0, (float) $height) : 48.0;
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
        $selected = $props['value'] ?? $props['defaultValue'] ?? null;
        if (is_array($selected)) {
            if (array_is_list($selected)) {
                $dates = array_map(
                    static fn (mixed $date): string => is_scalar($date)
                        ? (string) $date
                        : '',
                    $selected,
                );
                $values['selectedValues'] = implode("\n", array_filter($dates));
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
        $expanded = $props['expanded']
            ?? $props['expandedPaths']
            ?? $props['defaultExpanded']
            ?? [];
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

        return ['expandedPaths' => implode("\n", $paths)];
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function fallbackChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        if ($children !== []) {
            return $children;
        }

        if (
            isset($props['text'])
            && in_array($part, ['Button', 'Badge', 'Fab', 'Link', 'MenuItem', 'SelectItem'], true)
        ) {
            return [Text::make(self::text($props, 'text'))];
        }

        if (in_array($part, ['Input', 'Textarea'], true)) {
            return [self::input($part === 'Textarea' ? 'TextareaInput' : 'InputField', $props)];
        }
        if ($part === 'ImageViewerNavigation') {
            return self::imageViewerNavigationChildren();
        }
        if ($part === 'ImageViewerCounter') {
            return [
                Text::make('1 / 1')
                    ->property(PropKey::Value, 'pam:image-viewer-counter'),
            ];
        }
        if ($part === 'MessageBranchPrevious') {
            return [Text::make('‹')];
        }
        if ($part === 'MessageBranchNext') {
            return [Text::make('›')];
        }
        if ($part === 'PromptInputSubmit') {
            return [Text::make('↑')];
        }
        if ($part === 'PromptInputActionMenuTrigger') {
            return [Text::make('+')];
        }
        if ($part === 'ConversationScrollButton') {
            return [Text::make('↓')];
        }
        if ($part === 'ConversationDownload') {
            return [
                CustomView::make(
                    'pam.mobile_ui.icon',
                    self::iconProperties('DownloadIcon', []),
                ),
            ];
        }
        if ($part === 'AttachmentRemove') {
            return [
                CustomView::make(
                    'pam.mobile_ui.icon',
                    self::iconProperties('RemoveIcon', []),
                ),
            ];
        }
        if ($part === 'FileTreeFile') {
            return [Text::make(self::text($props, 'name', self::text($props, 'path')))];
        }
        if ($part === 'AttachmentPreview') {
            return self::attachmentPreviewChildren($props);
        }
        if ($part === 'AttachmentEmpty') {
            return [Text::make('No attachments')];
        }
        if ($part === 'ModelSelectorEmpty') {
            return [Text::make('No models found.')];
        }
        if ($part === 'ModelSelectorLogo') {
            $provider = self::text($props, 'provider', '?');
            $initials = function_exists('mb_substr')
                ? mb_substr($provider, 0, 2)
                : substr($provider, 0, 2);

            return [Text::make(strtoupper($initials))];
        }
        if (in_array($part, ['CalendarWeekDay', 'CalendarWeekNumber'], true)) {
            return [Text::make(self::text($props, 'text'))];
        }
        if (in_array($part, [
            'CalendarHeaderMonthSelect',
            'CalendarHeaderYearSelect',
        ], true)) {
            return [Text::make(self::calendarSelectLabel($part, $props))];
        }
        if ($part === 'ConversationEmptyState') {
            return [
                Column::make(
                    Text::make(self::text($props, 'title', 'Start a conversation')),
                    Text::make(
                        self::text(
                            $props,
                            'description',
                            'Type a message below to begin chatting',
                        ),
                    ),
                ),
            ];
        }

        return [];
    }

    /**
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function messageBranchChildren(
        string $part,
        array $children,
    ): array {
        if ($part !== 'MessageBranchContent') {
            return $children;
        }

        return array_map(
            static fn (Element $child, int $index): Element =>
                Column::make($child)->property(
                    PropKey::Value,
                    'pam:message-branch-page:'.$index,
                ),
            $children,
            array_keys($children),
        );
    }

    /**
     * Builds each text part as one intrinsic native Markdown view and keeps
     * file parts on PAM's native image pipeline.
     *
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function messageResponseChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        if ($part !== 'MessageResponse') {
            return $children;
        }
        if ($children !== []) {
            $text = [];
            foreach ($children as $child) {
                $value = $child->properties()[PropKey::Text->value] ?? null;
                if ($child->kind() !== NodeKind::Text || !is_string($value)) {
                    return $children;
                }
                $text[] = $value;
            }

            return [self::markdownView(implode("\n", $text), $props)];
        }

        $message = is_array($props['message'] ?? null)
            ? $props['message']
            : [];
        $parts = is_array($message['parts'] ?? null)
            ? $message['parts']
            : null;
        if ($parts === null) {
            $source = self::text(
                $props,
                'text',
                is_scalar($message['content'] ?? null)
                    ? (string) $message['content']
                    : '',
            );

            return [self::markdownView($source, $props)];
        }

        $rendered = [];
        foreach ($parts as $messagePart) {
            if (!is_array($messagePart)) {
                continue;
            }
            if (
                ($messagePart['type'] ?? null) === 'text'
                && is_scalar($messagePart['text'] ?? null)
            ) {
                $rendered[] = self::markdownView(
                    (string) $messagePart['text'],
                    $props,
                );
                continue;
            }
            if (($messagePart['type'] ?? null) !== 'file') {
                continue;
            }
            $uri = is_string($messagePart['url'] ?? null)
                ? $messagePart['url']
                : '';
            if (
                $uri === ''
                && is_string($messagePart['data'] ?? null)
                && is_string($messagePart['mimeType'] ?? null)
            ) {
                $uri = 'data:'.$messagePart['mimeType'].';base64,'
                    .$messagePart['data'];
            }
            if ($uri === '') {
                continue;
            }
            $label = is_string($messagePart['filename'] ?? null)
                ? $messagePart['filename']
                : 'Message attachment';
            $rendered[] = Image::make($uri)
                ->fit(ImageFit::Cover)
                ->accessibilityLabel($label)
                ->accessibilityRole(AccessibilityRole::Image);
        }

        return $rendered === []
            ? [Text::make(self::text($props, 'pendingText', 'Thinking...'))]
            : $rendered;
    }

    /** @param array<string, mixed> $props */
    private static function markdownView(string $source, array $props): Element
    {
        $theme = ThemeManager::current();

        return CustomView::make(
            'pam.mobile_ui.markdown',
            [
                'source' => $source,
                'foregroundColor' => $theme->color(ColorToken::Foreground),
                'mutedColor' => $theme->color(ColorToken::MutedForeground),
                'linkColor' => $theme->color(ColorToken::Primary),
                'codeBackgroundColor' => $theme->color(ColorToken::Muted),
                'codeForegroundColor' => $theme->color(ColorToken::Foreground),
                'selectable' => self::flag($props, 'selectable', true),
            ],
        );
    }

    /**
     * A link originates in the intrinsic Markdown child, not in its optional
     * multi-part container.
     *
     * @param array<int, Closure> $events
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function messageResponseEventChildren(
        string $part,
        array $events,
        array $children,
    ): array {
        $handler = $events[EventKind::Native->value] ?? null;
        if ($part !== 'MessageResponse' || $handler === null) {
            return $children;
        }

        foreach ($children as $index => $child) {
            if (
                $child->kind() === NodeKind::CustomView
                && ($child->properties()[PropKey::HostName->value] ?? null)
                    === 'pam.mobile_ui.markdown'
            ) {
                $children[$index] = $child->on(EventKind::Native, $handler);
            }
        }

        return $children;
    }

    /** @param array<string, mixed> $props */
    private static function messageHasNoText(array $props): bool
    {
        if (array_key_exists('hasText', $props)) {
            return !self::flag($props, 'hasText');
        }
        $message = $props['message'] ?? null;
        if (!is_array($message)) {
            return false;
        }
        $parts = $message['parts'] ?? null;
        if (!is_array($parts)) {
            $content = $message['content'] ?? null;

            return is_scalar($content) && trim((string) $content) === '';
        }
        foreach ($parts as $messagePart) {
            if (
                is_array($messagePart)
                && ($messagePart['type'] ?? null) === 'text'
                && is_scalar($messagePart['text'] ?? null)
                && trim((string) $messagePart['text']) !== ''
            ) {
                return false;
            }
        }

        return true;
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

        return [
            Row::make(
                Text::make('›')->property(
                    PropKey::Value,
                    'pam:file-tree-chevron',
                ),
                Text::make($name)->property(
                    PropKey::Value,
                    'pam:file-tree-name',
                ),
            )->property(PropKey::Value, 'pam:file-tree-header'),
            Column::make(...$children)->property(
                PropKey::Value,
                'pam:file-tree-content',
            ),
        ];
    }

    /**
     * @param array<string, mixed> $props
     * @return list<Element>
     */
    private static function attachmentPreviewChildren(array $props): array
    {
        $parent = is_array($props['__parentVariants'] ?? null)
            ? $props['__parentVariants']
            : [];
        $data = $props['data'] ?? $parent['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        $url = is_string($data['url'] ?? null) ? $data['url'] : '';
        $mediaType = is_string($data['mediaType'] ?? null)
            ? $data['mediaType']
            : '';
        $filename = is_string($data['filename'] ?? null)
            ? $data['filename']
            : 'Attachment';
        if ($url !== '' && str_starts_with($mediaType, 'image/')) {
            return [
                Image::make($url)
                    ->fit(ImageFit::Cover)
                    ->accessibilityLabel($filename)
                    ->accessibilityRole(AccessibilityRole::Image),
            ];
        }
        $icon = match (true) {
            ($data['type'] ?? null) === 'source-document' => 'GlobeIcon',
            str_starts_with($mediaType, 'video/') => 'PlayIcon',
            str_starts_with($mediaType, 'application/'),
            str_starts_with($mediaType, 'text/') => 'PaperclipIcon',
            default => 'PaperclipIcon',
        };
        if (!isset(ComponentMap::IDS[$icon])) {
            $icon = 'PaperclipIcon';
        }

        return [
            CustomView::make(
                'pam.mobile_ui.icon',
                self::iconProperties($icon, []),
            )->accessibilityLabel($filename),
        ];
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     * @return list<Element>
     */
    private static function imageViewerChildren(
        string $part,
        array $props,
        array $children,
    ): array {
        if ($part !== 'ImageViewerContent') {
            return $children;
        }
        $parent = is_array($props['__parentVariants'] ?? null)
            ? $props['__parentVariants']
            : [];
        $images = $props['images'] ?? $parent['images'] ?? [];
        if (!is_array($images)) {
            return $children;
        }
        $generated = [];
        foreach (array_values($images) as $index => $image) {
            $url = is_string($image)
                ? $image
                : (is_array($image) && is_string($image['url'] ?? null)
                    ? $image['url']
                    : null);
            if ($url === null || $url === '') {
                continue;
            }
            $alt = is_array($image) && is_string($image['alt'] ?? null)
                ? $image['alt']
                : 'Image '.($index + 1);
            $generated[] = Image::make($url)
                ->fit(ImageFit::Contain)
                ->property(PropKey::Value, 'pam:image-viewer-image:'.$index)
                ->property(PropKey::PositionType, PositionType::Absolute->value)
                ->property(PropKey::Left, 0.0)
                ->property(PropKey::Top, 0.0)
                ->property(PropKey::WidthPercent, 100.0)
                ->property(PropKey::HeightPercent, 100.0)
                ->accessibilityLabel($alt)
                ->accessibilityRole(AccessibilityRole::Image);
        }

        return $generated === [] ? $children : [...$generated, ...$children];
    }

    /** @return list<Element> */
    private static function imageViewerNavigationChildren(): array
    {
        $component = ComponentMap::IDS['ImageViewerNavigation'];
        $control = static function (
            ImageViewerControlAction $action,
            string $label,
            string $glyph,
        ) use ($component): Element {
            return CustomView::make(
                'pam.mobile_ui.host',
                [
                    'part' => $component,
                    'component' => $component,
                    'behavior' => NativeBehavior::ImageViewerControl->value,
                    'navigationAction' => $action->value,
                ],
                Text::make($glyph),
            )
                ->style(new Style(
                    width: 48.0,
                    height: 48.0,
                    borderRadius: 24.0,
                    alignItems: \Pam\Native\Align::Center,
                    justifyContent: \Pam\Native\Justify::Center,
                ))
                ->accessibilityLabel($label)
                ->accessibilityRole(AccessibilityRole::Button);
        };

        return [
            $control(
                ImageViewerControlAction::Previous,
                'Previous image',
                '‹',
            ),
            $control(
                ImageViewerControlAction::Next,
                'Next image',
                '›',
            ),
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
            : self::text($props, 'value');
        $input = Input::make($value)
            ->placeholder(self::text($props, 'placeholder'))
            ->nativeState(
                $syncMode,
                self::integer($props, 'debounce', 48),
            );

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
            'number', 'numeric', 3 => KeyboardType::Number,
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

        return $input;
    }

    /** @param array<string, mixed> $props */
    private static function dateTimeDisplayValue(array $props): string
    {
        $value = self::text($props, 'value');
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
    private static function skeletonDurationMillis(string $part, array $props): int
    {
        $speed = self::integer($props, 'speed', 3);

        return match ($speed) {
            1 => 750,
            2 => $part === 'SkeletonText' ? 1_000 : 100,
            4 => 2_000,
            default => 1_500,
        };
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function skeletonText(
        array $props,
        Style $style,
        ?Style $styleOverride,
        ?int $nativeBackground,
    ): Element {
        $lines = min(
            100,
            max(
                2,
                self::integer(
                    $props,
                    '_lines',
                    self::integer($props, 'lines', 2),
                ),
            ),
        );
        $color = $nativeBackground
            ?? ThemeManager::current()->color(ColorToken::Muted);
        $lineStyle = new Style(
            minHeight: 16.0,
            backgroundColor: $color,
            borderRadius: 4.0,
        );
        $children = [];
        for ($line = 0; $line < $lines; $line++) {
            $children[] = View::make()
                ->style($lineStyle)
                ->property(PropKey::WidthPercent, $line === $lines - 1 ? 80.0 : 100.0);
        }
        $host = CustomView::make(
            'pam.mobile_ui.host',
            self::nativeProperties(
                'SkeletonText',
                NativeBehavior::Skeleton,
                $props,
                $children,
                $nativeBackground,
            ),
            ...$children,
        )
            ->style($style)
            ->property(
                PropKey::Gap,
                max(0.0, (float) self::integer($props, 'gap', 2) * 4.0),
            );

        return $styleOverride === null ? $host : $host->style($styleOverride);
    }

    /** @param array<string, mixed> $props */
    private static function configuredList(Element $list, array $props): Element
    {
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
