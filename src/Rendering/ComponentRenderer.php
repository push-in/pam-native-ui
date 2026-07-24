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
use Pam\Native\UI\Toggle;
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
        $runtimeProps = [...$parentProps, ...$props];
        $style = StyleResolver::resolve($part, $props, ThemeManager::current());
        $element = self::primitive($part, $runtimeProps, $children)
            ->style($style);

        if ($part === 'TabsTrigger' && isset($props['value']) && is_scalar($props['value'])) {
            $element = $element->property(PropKey::Value, $props['value']);
        } elseif ($part === 'CalendarGrid') {
            $element = $element->property(PropKey::Value, 'pam:calendar-grid');
        }

        if ($styleOverride !== null) {
            $element = $element->style($styleOverride);
        }
        if (
            self::flag($props, 'disabled', self::flag($props, 'isDisabled'))
            || self::flag($props, 'readOnly', self::flag($props, 'isReadOnly'))
        ) {
            $element = $element->enabled(false);
        }
        $shouldHide = (
            self::flag($props, 'hidden')
            || (
                $part === 'TabsContent'
                && array_key_exists('value', $parentProps)
                && array_key_exists('value', $props)
                && !self::sameScalar($parentProps['value'], $props['value'])
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
        if ($part === 'Switch') {
            return Toggle::make(self::flag($props, 'checked', self::flag($props, 'isChecked')));
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
                self::nativeProperties($part, $behavior, $props),
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
                ->accessibilityRole(AccessibilityRole::Button);
        }

        return View::make(...$children);
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, string|int|float|bool>
     */
    private static function nativeProperties(
        string $part,
        NativeBehavior $behavior,
        array $props,
    ): array {
        $values = [
            'part' => ComponentMap::IDS[$part],
            'component' => ComponentMap::IDS[$part],
            'behavior' => $behavior->value,
            'theme' => ThemeManager::resolvedMode()->value,
            'trackColor' => ThemeManager::current()->color(ColorToken::Muted),
            'fillColor' => ThemeManager::current()->color(ColorToken::Primary),
            'foregroundColor' => ThemeManager::current()->color(ColorToken::Foreground),
        ];

        foreach ($props as $name => $value) {
            if (
                preg_match('/^[A-Za-z][A-Za-z0-9_]{0,254}$/D', $name) === 1
                && (is_string($value) || is_int($value) || is_float($value) || is_bool($value))
            ) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    public static function nativeBehavior(string $part): NativeBehavior
    {
        return match ($part) {
            'AccordionItem' => NativeBehavior::Accordion,
            'Actionsheet',
            'BottomSheetPortal',
            'SelectPortal' => NativeBehavior::BottomSheet,
            'Slider' => NativeBehavior::Slider,
            'Tabs' => NativeBehavior::Tabs,
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

    public static function eventProxyPart(string $part): ?string
    {
        return match ($part) {
            'BottomSheet' => 'BottomSheetPortal',
            'ModelSelector' => 'ModelSelectorContent',
            'Select' => 'SelectPortal',
            default => null,
        };
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
        $input = Input::make(self::text($props, 'value'))
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
