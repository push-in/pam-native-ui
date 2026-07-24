<?php

declare(strict_types=1);

namespace Pam\MobileUi\Component;

use BackedEnum;
use Closure;
use Pam\MobileUi\Rendering\ComponentRenderer;
use Pam\MobileUi\Rendering\ValueNormalizer;
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

    final public function onNativeEvent(Closure $handler): static
    {
        return $this->on(EventKind::Native, $handler);
    }

    final public function toElement(): Element
    {
        $context = [
            ...$this->parentVariants,
            ...array_filter(
                $this->props,
                static fn (mixed $value): bool => is_scalar($value),
            ),
        ];
        $children = array_map(
            static function (Renderable $child) use ($context): Element {
                if ($child instanceof UiComponent) {
                    $child = $child->withParentVariants($context);
                }

                return $child->toElement();
            },
            $this->children,
        );
        $props = $this->props;

        if ($this->parentVariants !== []) {
            $props['__parentVariants'] = $this->parentVariants;
        }

        return ComponentRenderer::render(
            static::COMPONENT,
            $props,
            $children,
            $this->events,
            $this->styleOverride,
            $this->elementKey,
        );
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
}
