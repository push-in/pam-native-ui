<?php

declare(strict_types=1);

namespace Pam\MobileUi\Component;

use BackedEnum;
use Closure;
use Pam\MobileUi\Rendering\ComponentRenderer;
use Pam\MobileUi\Rendering\ValueNormalizer;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\MaterialDensity;
use Pam\MobileUi\Enum\MaterialMotion;
use Pam\MobileUi\Enum\MaterialShape;
use Pam\MobileUi\Enum\Placement;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\AccessibilityCheckedState;
use Pam\Native\AccessibilityImportance;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\Renderable;
use Pam\Native\Style;

abstract class UiComponent implements Renderable
{
    protected const string COMPONENT = '';

    final public static function componentName(): string
    {
        return static::COMPONENT;
    }

    /** @var array<string, mixed> */
    private array $props;

    /** @var list<Renderable> */
    private array $children;

    /** @var array<int, Closure> */
    private array $events = [];

    /**
     * @var array<
     *     string,
     *     array{props: array<string, mixed>, events: array<int, Closure>}
     * >
     */
    private array $eventContexts = [];

    private ?Style $styleOverride = null;
    private ?string $elementKey = null;

    /** @var array<string, mixed> */
    private array $parentVariants = [];

    /**
     * @param array<string, mixed> $props
     * @param list<Renderable> $children
     */
    final protected function __construct(array $props = [], array $children = [])
    {
        $this->props = ValueNormalizer::props($props);
        $this->children = $children;
    }

    /**
     * @param array<string, mixed>|string|int|float|bool|Renderable|null $content
     */
    final public static function make(
        array|string|int|float|bool|Renderable|null $content = null,
        Renderable ...$children,
    ): static {
        $props = [];

        if ($content instanceof Renderable) {
            array_unshift($children, $content);
        } elseif (is_array($content)) {
            $props = $content;
        } elseif ($content !== null) {
            $props['text'] = $content;
        }

        return new static(
            $props,
            array_values($children),
        );
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Element> $children
     */
    final public static function fromTemplate(array $props, array $children): static
    {
        $rawContexts = $props['__pamEventContexts'] ?? [];
        unset($props['__pamEventContexts']);
        $component = new static($props, $children);
        if (!is_array($rawContexts)) {
            return $component;
        }

        foreach ($rawContexts as $source => $context) {
            if (
                !is_string($source)
                || !isset(ComponentMap::IDS[$source])
                || !ComponentRenderer::forwardsEventsToDescendants($source)
                || !is_array($context)
                || !is_array($context['props'] ?? null)
                || !is_array($context['events'] ?? null)
            ) {
                continue;
            }
            $events = [];
            foreach ($context['events'] as $kind => $handler) {
                if (is_int($kind) && $handler instanceof Closure) {
                    $events[$kind] = $handler;
                }
            }
            if ($events === []) {
                continue;
            }
            $contextProps = [];
            $validProps = true;
            foreach ($context['props'] as $name => $value) {
                if (
                    !is_string($name)
                    || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/D', $name)
                        !== 1
                ) {
                    $validProps = false;
                    break;
                }
                $contextProps[$name] = $value;
            }
            if (!$validProps) {
                continue;
            }
            $component->eventContexts[$source] = [
                'props' => ComponentRenderer::withDefaults(
                    $source,
                    ValueNormalizer::props($contextProps),
                ),
                'events' => $events,
            ];
        }

        return $component;
    }

    final public function prop(string $name, mixed $value): static
    {
        $copy = clone $this;
        $copy->props[$name] = ValueNormalizer::value($name, $value);

        return $copy;
    }

    final public function variant(BackedEnum $variant): static
    {
        return $this->prop('variant', $variant);
    }

    final public function size(BackedEnum $size): static
    {
        return $this->prop('size', $size);
    }

    /** @param array<string, mixed> $defaults */
    final public function defaults(array $defaults): static
    {
        return $this->prop('defaults', $defaults);
    }

    final public function locale(string $locale): static
    {
        return $this->prop('locale', $locale);
    }

    final public function rtl(bool $rtl = true): static
    {
        return $this->prop('rtl', $rtl);
    }

    final public function theme(ThemeMode|string $theme): static
    {
        return $this->prop(
            'theme',
            $theme instanceof ThemeMode ? $theme->value : $theme,
        );
    }

    final public function color(ColorToken|int $color): static
    {
        return $this->prop('color', $color);
    }

    final public function density(MaterialDensity $density): static
    {
        return $this->prop('density', $density);
    }

    final public function elevation(int $elevation): static
    {
        return $this->prop('elevation', max(0, min(5, $elevation)));
    }

    final public function rounded(MaterialShape|bool $shape = true): static
    {
        return $this->prop('rounded', $shape);
    }

    final public function motion(MaterialMotion $motion): static
    {
        return $this->prop('motion', $motion);
    }

    final public function placement(Placement $placement): static
    {
        return $this->prop('placement', $placement);
    }

    final public function modelValue(mixed $value): static
    {
        return $this->prop('modelValue', $value);
    }

    final public function width(float $width): static
    {
        return $this->prop('width', max(0.0, $width));
    }

    final public function height(float $height): static
    {
        return $this->prop('height', max(0.0, $height));
    }

    final public function block(bool $block = true): static
    {
        return $this->prop('block', $block);
    }

    final public function icon(string|int|bool $icon = true): static
    {
        return $this->prop('icon', $icon);
    }

    final public function prependIcon(string|int $icon): static
    {
        return $this->prop('prependIcon', $icon);
    }

    final public function appendIcon(string|int $icon): static
    {
        return $this->prop('appendIcon', $icon);
    }

    final public function active(bool $active = true): static
    {
        return $this->prop('active', $active);
    }

    final public function clearable(bool $clearable = true): static
    {
        return $this->prop('clearable', $clearable);
    }

    final public function closable(bool $closable = true): static
    {
        return $this->prop('closable', $closable);
    }

    final public function persistent(bool $persistent = true): static
    {
        return $this->prop('persistent', $persistent);
    }

    final public function multiple(bool $multiple = true): static
    {
        return $this->prop('multiple', $multiple);
    }

    final public function indeterminate(bool $indeterminate = true): static
    {
        return $this->prop('indeterminate', $indeterminate);
    }

    final public function disabled(bool $disabled = true): static
    {
        return $this->prop('disabled', $disabled);
    }

    final public function loading(bool $loading = true): static
    {
        return $this->prop('loading', $loading);
    }

    final public function selected(bool $selected = true): static
    {
        return $this->prop('selected', $selected);
    }

    final public function checked(bool $checked = true): static
    {
        return $this->prop('checked', $checked);
    }

    final public function expanded(bool $expanded = true): static
    {
        return $this->prop('expanded', $expanded);
    }

    final public function invalid(bool $invalid = true): static
    {
        return $this->prop('invalid', $invalid);
    }

    final public function readOnly(bool $readOnly = true): static
    {
        return $this->prop('readOnly', $readOnly);
    }

    final public function required(bool $required = true): static
    {
        return $this->prop('required', $required);
    }

    final public function open(bool $open = true): static
    {
        return $this->prop('open', $open);
    }

    final public function value(string|int|float|bool $value): static
    {
        return $this->prop('value', $value);
    }

    final public function accessibilityLabel(string $label): static
    {
        return $this->prop('accessibilityLabel', $label);
    }

    final public function accessibilityHint(string $hint): static
    {
        return $this->prop('accessibilityHint', $hint);
    }

    final public function accessibilityRole(AccessibilityRole $role): static
    {
        return $this->prop('accessibilityRole', $role);
    }

    final public function accessible(bool $accessible = true): static
    {
        return $this->prop('accessible', $accessible);
    }

    final public function accessibilityLiveRegion(
        AccessibilityLiveRegion $region,
    ): static {
        return $this->prop('accessibilityLiveRegion', $region);
    }

    final public function importantForAccessibility(
        AccessibilityImportance $importance,
    ): static {
        return $this->prop('importantForAccessibility', $importance);
    }

    final public function accessibilityBusy(bool $busy = true): static
    {
        return $this->prop('accessibilityBusy', $busy);
    }

    final public function accessibilityChecked(
        AccessibilityCheckedState $state,
    ): static {
        return $this->prop('accessibilityCheckedState', $state);
    }

    final public function accessibilityValue(
        float $minimum,
        float $maximum,
        float $current,
        ?string $text = null,
    ): static {
        return $this->prop('accessibilityValue', array_filter(
            [
                'min' => $minimum,
                'max' => $maximum,
                'now' => $current,
                'text' => $text,
            ],
            static fn (mixed $value): bool => $value !== null,
        ));
    }

    final public function testId(string $testId): static
    {
        return $this->prop('testId', $testId);
    }

    final public function child(Renderable $child): static
    {
        $copy = clone $this;
        $copy->children[] = $child;

        return $copy;
    }

    final public function style(Style $style): static
    {
        $copy = clone $this;
        $copy->styleOverride = $style;

        return $copy;
    }

    final public function key(string $key): static
    {
        $copy = clone $this;
        $copy->elementKey = $key;

        return $copy;
    }

    final public function on(EventKind $event, Closure $handler): static
    {
        $copy = clone $this;
        $copy->events[$event->value] = $handler;

        return $copy;
    }

    final public function onPress(Closure $handler): static
    {
        return $this->on(EventKind::Press, $handler);
    }

    final public function onChange(Closure $handler): static
    {
        return $this->on(EventKind::Change, $handler);
    }

    /**
     * Registers the final value callback used by range controls.
     *
     * The native-event channel keeps this distinct from continuous CHANGE
     * callbacks without extending PAM's shared event enum.
     */
    final public function onChangeEnd(Closure $handler): static
    {
        return $this->on(
            EventKind::Native,
            static function (string $payload) use ($handler): void {
                $handler((float) $payload);
            },
        );
    }

    final public function onToggle(Closure $handler): static
    {
        return $this->on(EventKind::Toggle, $handler);
    }

    final public function onLongPress(Closure $handler): static
    {
        return $this->on(EventKind::LongPress, $handler);
    }

    final public function onPressIn(Closure $handler): static
    {
        return $this->on(EventKind::PressIn, $handler);
    }

    final public function onPressOut(Closure $handler): static
    {
        return $this->on(EventKind::PressOut, $handler);
    }

    final public function onPressMove(Closure $handler): static
    {
        return $this->on(EventKind::PressMove, $handler);
    }

    final public function onFocus(Closure $handler): static
    {
        return $this->on(EventKind::Focus, $handler);
    }

    final public function onBlur(Closure $handler): static
    {
        return $this->on(EventKind::Blur, $handler);
    }

    final public function onSubmit(Closure $handler): static
    {
        return $this->on(EventKind::Submit, $handler);
    }

    final public function onEndEditing(Closure $handler): static
    {
        return $this->on(EventKind::InputEndEditing, $handler);
    }

    final public function onSelectionChange(Closure $handler): static
    {
        return $this->on(EventKind::InputSelectionChange, $handler);
    }

    final public function onContentSizeChange(Closure $handler): static
    {
        return $this->on(EventKind::InputContentSizeChange, $handler);
    }

    final public function onKeyPress(Closure $handler): static
    {
        return $this->on(EventKind::InputKeyPress, $handler);
    }

    final public function onScroll(Closure $handler): static
    {
        return $this->on(EventKind::Scroll, $handler);
    }

    final public function onEndReached(Closure $handler): static
    {
        return $this->on(EventKind::EndReached, $handler);
    }

    final public function onLoadStart(Closure $handler): static
    {
        return $this->on(EventKind::ImageLoadStart, $handler);
    }

    final public function onProgress(Closure $handler): static
    {
        return $this->on(EventKind::ImageProgress, $handler);
    }

    final public function onLoad(Closure $handler): static
    {
        return $this->on(EventKind::ImageLoad, $handler);
    }

    final public function onError(Closure $handler): static
    {
        return $this->on(EventKind::ImageError, $handler);
    }

    final public function onLoadEnd(Closure $handler): static
    {
        return $this->on(EventKind::ImageLoadEnd, $handler);
    }

    final public function onNativeEvent(Closure $handler): static
    {
        return $this->on(EventKind::Native, $handler);
    }

    final public function onClose(Closure $handler): static
    {
        return $this->on(EventKind::Native, $handler);
    }

    final public function onRequestClose(Closure $handler): static
    {
        return $this->on(EventKind::ModalRequestClose, $handler);
    }

    final public function onShow(Closure $handler): static
    {
        return $this->on(EventKind::ModalShow, $handler);
    }

    final public function onDismiss(Closure $handler): static
    {
        return $this->on(EventKind::ModalDismiss, $handler);
    }

    final public function onOrientationChange(Closure $handler): static
    {
        return $this->on(EventKind::ModalOrientationChange, $handler);
    }

    final public function toElement(): Element
    {
        $inheritedDefaults = $this->parentVariants['__pamDefaults'] ?? [];
        $inheritedProps = self::providerProps($this->parentVariants);
        $componentProps = ComponentRenderer::withDefaults(
            static::COMPONENT,
            self::applyProviderDefaults(
                static::COMPONENT,
                [...$inheritedProps, ...$this->props],
                is_array($inheritedDefaults) ? $inheritedDefaults : [],
            ),
        );
        $componentProps = ComponentRenderer::withParentState(
            static::COMPONENT,
            $componentProps,
            $this->parentVariants,
        );
        $previousThemeMode = null;
        if (in_array(static::COMPONENT, ['PamUIProvider', 'PThemeProvider'], true)) {
            $previousThemeMode = ThemeManager::configuredMode();
            ThemeManager::mode(self::providerThemeMode(
                $componentProps['mode']
                    ?? $componentProps['theme']
                    ?? null,
            ));
        }

        try {
            $events = $this->events;
            foreach ($this->eventContexts as $source => $context) {
                $events = self::mergeEvents(
                    $events,
                    ComponentRenderer::inheritedEvents(
                        $source,
                        static::COMPONENT,
                        $componentProps,
                        $context['props'],
                        $context['events'],
                    ),
                );
            }
            $eventContexts = $this->eventContexts;
            if (
                $this->events !== []
                && ComponentRenderer::forwardsEventsToDescendants(static::COMPONENT)
            ) {
                $eventContexts[static::COMPONENT] = [
                    'props' => $componentProps,
                    'events' => $this->events,
                ];
            }
            $context = [
                ...$this->parentVariants,
                '__pamParentComponent' => static::COMPONENT,
                ...array_filter(
                    $componentProps,
                    static fn (mixed $value, string $name): bool =>
                        self::isContextValue($name, $value),
                    ARRAY_FILTER_USE_BOTH,
                ),
            ];
            $context = self::providerContext(
                static::COMPONENT,
                $componentProps,
                $context,
            );
            $children = array_map(
                static function (Renderable $child) use (
                    $context,
                    $eventContexts,
                ): Element {
                    if ($child instanceof UiComponent) {
                        $child = $child
                            ->withParentVariants($context)
                            ->withEventContexts($eventContexts);
                    }

                    return $child->toElement();
                },
                $this->children,
            );
            $props = $componentProps;

            if ($this->parentVariants !== []) {
                $props['__parentVariants'] = $this->parentVariants;
            }

            return ComponentRenderer::render(
                static::COMPONENT,
                $props,
                $children,
                $events,
                $this->styleOverride,
                $this->elementKey,
            );
        } finally {
            if ($previousThemeMode !== null) {
                ThemeManager::mode($previousThemeMode);
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function providerProps(array $context): array
    {
        $props = [];
        foreach (['locale', 'rtl'] as $name) {
            $key = '__pam'.ucfirst($name);
            if (array_key_exists($key, $context)) {
                $props[$name] = $context[$key];
            }
        }

        return $props;
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private static function applyProviderDefaults(
        string $component,
        array $props,
        array $defaults,
    ): array {
        $global = is_array($defaults['global'] ?? null)
            ? $defaults['global']
            : [];
        $componentDefaults = is_array($defaults[$component] ?? null)
            ? $defaults[$component]
            : [];

        return [...$global, ...$componentDefaults, ...$props];
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function providerContext(
        string $component,
        array $props,
        array $context,
    ): array {
        if ($component === 'PDefaultsProvider') {
            $provided = $props['defaults'] ?? $props['value'] ?? [];
            if (is_array($provided)) {
                $providedDefaults = [];
                foreach ($provided as $name => $values) {
                    if (is_string($name)) {
                        $providedDefaults[$name] = $values;
                    }
                }
                $current = is_array($context['__pamDefaults'] ?? null)
                    ? $context['__pamDefaults']
                    : [];
                $context['__pamDefaults'] = self::mergeProviderDefaults(
                    $current,
                    $providedDefaults,
                );
            }
        }
        if ($component === 'PLocaleProvider') {
            if (isset($props['locale']) && is_string($props['locale'])) {
                $context['__pamLocale'] = $props['locale'];
            }
            if (array_key_exists('rtl', $props)) {
                $context['__pamRtl'] = (bool) $props['rtl'];
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function mergeProviderDefaults(
        array $base,
        array $overrides,
    ): array {
        foreach ($overrides as $component => $values) {
            if (
                is_array($values)
                && is_array($base[$component] ?? null)
            ) {
                $base[$component] = [
                    ...$base[$component],
                    ...$values,
                ];
                continue;
            }
            $base[$component] = $values;
        }

        return $base;
    }

    private static function providerThemeMode(mixed $mode): ThemeMode
    {
        return match ($mode) {
            ThemeMode::Light->value, 'light' => ThemeMode::Light,
            ThemeMode::Dark->value, 'dark' => ThemeMode::Dark,
            default => ThemeMode::System,
        };
    }

    /**
     * @param array<string, mixed> $variants
     */
    private function withParentVariants(array $variants): static
    {
        $copy = clone $this;
        $copy->parentVariants = $variants;

        return $copy;
    }

    /**
     * @param array<
     *     string,
     *     array{props: array<string, mixed>, events: array<int, Closure>}
     * > $contexts
     */
    private function withEventContexts(array $contexts): static
    {
        $copy = clone $this;
        $copy->eventContexts = $contexts;

        return $copy;
    }

    /**
     * @param array<int, Closure> $own
     * @param array<int, Closure> $inherited
     * @return array<int, Closure>
     */
    private static function mergeEvents(array $own, array $inherited): array
    {
        $merged = $own;

        foreach ($inherited as $kind => $handler) {
            $local = $merged[$kind] ?? null;
            if ($local === null) {
                $merged[$kind] = $handler;
                continue;
            }
            $merged[$kind] = static function (mixed ...$arguments) use (
                $local,
                $handler,
            ): void {
                $local(...$arguments);
                $handler(...$arguments);
            };
        }

        return $merged;
    }

    private static function isContextValue(string $name, mixed $value): bool
    {
        if (is_scalar($value)) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }

        if ($name === 'images') {
            foreach ($value as $image) {
                if (is_string($image)) {
                    continue;
                }
                if (
                    !is_array($image)
                    || !is_string($image['url'] ?? null)
                ) {
                    return false;
                }
            }

            return true;
        }
        if (in_array($name, ['attachments', 'files', 'message'], true)) {
            return self::isSafeContextArray($value, 0);
        }

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<array-key, mixed> $value */
    private static function isSafeContextArray(array $value, int $depth): bool
    {
        if ($depth >= 4 || count($value) > 256) {
            return false;
        }
        foreach ($value as $item) {
            if (is_scalar($item) || $item === null) {
                continue;
            }
            if (
                !is_array($item)
                || !self::isSafeContextArray($item, $depth + 1)
            ) {
                return false;
            }
        }

        return true;
    }
}
