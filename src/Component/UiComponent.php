<?php

declare(strict_types=1);

namespace Pam\MobileUi\Component;

use BackedEnum;
use Closure;
use Pam\MobileUi\Rendering\ComponentRenderer;
use Pam\MobileUi\Rendering\ValueNormalizer;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\Renderable;
use Pam\Native\Style;

abstract class UiComponent implements Renderable
{
    protected const string COMPONENT = '';

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
        return new static($props, $children);
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

    final public function onScroll(Closure $handler): static
    {
        return $this->on(EventKind::Scroll, $handler);
    }

    final public function onEndReached(Closure $handler): static
    {
        return $this->on(EventKind::EndReached, $handler);
    }

    final public function onNativeEvent(Closure $handler): static
    {
        return $this->on(EventKind::Native, $handler);
    }

    final public function toElement(): Element
    {
        $componentProps = ComponentRenderer::withDefaults(
            static::COMPONENT,
            $this->props,
        );
        $previousThemeMode = null;
        if (static::COMPONENT === 'GluestackUIProvider') {
            $previousThemeMode = ThemeManager::configuredMode();
            ThemeManager::mode(self::providerThemeMode($componentProps['mode'] ?? null));
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
                ...array_filter(
                    $componentProps,
                    static fn (mixed $value, string $name): bool =>
                        self::isContextValue($name, $value),
                    ARRAY_FILTER_USE_BOTH,
                ),
            ];
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
