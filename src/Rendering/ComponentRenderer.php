<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Closure;
use InvalidArgumentException;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\NativeBehavior;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\InputSyncMode;
use Pam\Native\KeyboardAvoidingBehavior;
use Pam\Native\KeyboardType;
use Pam\Native\ModalPresentation;
use Pam\Native\PositionType;
use Pam\Native\PropKey;
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

        $children = self::fallbackChildren($part, $props, $children);
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
        $style = StyleResolver::resolve($part, $props, ThemeManager::current());
        $nativeBackground = $style->backgroundColor;
        if ($styleOverride !== null && $styleOverride->backgroundColor !== null) {
            $nativeBackground = $styleOverride->backgroundColor;
        }
        $element = self::primitive(
            $part,
            $runtimeProps,
            $children,
            $nativeBackground,
        )
            ->style($style);

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
        }

        if ($styleOverride !== null) {
            $element = $element->style($styleOverride);
        }
        $disabled = self::flag($props, 'disabled', self::flag($props, 'isDisabled'));
        $readOnly = self::flag($props, 'readOnly', self::flag($props, 'isReadOnly'));
        $nativeReadOnly = in_array(
            $part,
            ['Calendar', 'Checkbox', 'CheckboxGroup', 'Radio', 'RadioGroup'],
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
        if (self::isText($part)) {
            return Text::make(self::text($props, 'text'));
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
            return Image::make(self::text($props, 'source', self::text($props, 'src')));
        }
        if ($part === 'ImageBackground') {
            return ImageBackground::make(self::text($props, 'source'), ...$children);
        }
        if ($part === 'Spinner' || $part === 'ButtonSpinner') {
            return ActivityIndicator::make(($props['visible'] ?? true) !== false);
        }
        if (in_array($part, ['FlatList', 'VirtualizedList'], true)) {
            return FlatList::make(self::stringList($props['items'] ?? []));
        }
        if ($part === 'SectionList') {
            return SectionList::make(self::sections($props['sections'] ?? []));
        }
        if (in_array($part, [
            'ScrollView',
            'ActionsheetScrollView',
            'BottomSheetScrollView',
            'SelectScrollView',
        ], true)) {
            return Scroll::make(self::oneChild($children));
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
                KeyboardAvoidingBehavior::Resize,
            );
        }
        if ($part === 'InputAccessoryView') {
            return InputAccessoryView::make(...$children);
        }
        if ($part === 'StatusBar') {
            return StatusBar::make();
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

        return $values;
    }

    public static function nativeBehavior(string $part): NativeBehavior
    {
        return match ($part) {
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
        if (
            ($source === 'BottomSheet' && $target === 'BottomSheetPortal')
            || ($source === 'ModelSelector' && $target === 'ModelSelectorContent')
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
            || ($source === 'Menu' && $target === 'MenuItem')
        ) {
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
        if (!array_key_exists('open', $props) && !array_key_exists('isOpen', $props)) {
            return false;
        }

        return !self::flag(
            $props,
            'open',
            self::flag($props, 'isOpen'),
        );
    }

    private static function hidesEntireRootWhenClosed(string $part): bool
    {
        return in_array($part, [
            'Actionsheet',
            'AlertDialog',
            'Drawer',
            'Menu',
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
            'SelectBackdrop',
            'SelectContent',
            'SelectPortal',
            'TooltipContent',
        ], true);
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
        return $part === 'Text'
            || $part === 'Heading'
            || str_ends_with($part, 'Text')
            || str_ends_with($part, 'Label')
            || str_ends_with($part, 'Title')
            || str_ends_with($part, 'Description')
            || str_ends_with($part, 'Caption')
            || in_array($part, ['TableHead', 'TableData', 'MessageResponse'], true);
    }

    private static function isInput(string $part): bool
    {
        return in_array($part, [
            'InputField',
            'TextareaInput',
            'DateTimePickerInput',
            'BottomSheetTextInput',
            'PromptInputTextarea',
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
        return in_array($part, ['Button', 'Fab', 'Link', 'Pressable'], true)
            || str_ends_with($part, 'Trigger')
            || str_ends_with($part, 'CloseButton')
            || str_ends_with($part, 'Item');
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

        return match ($part) {
            'AccordionItem' => [...$props, 'expanded' => $selected],
            'Checkbox', 'Radio', 'SelectItem' => [...$props, 'checked' => $selected],
            'MenuItem', 'TabsTrigger' => [...$props, 'selected' => $selected],
            default => $props,
        };
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

        return [];
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
