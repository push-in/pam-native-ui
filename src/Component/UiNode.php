<?php

declare(strict_types=1);

namespace Pam\MobileUi\Component;

use Closure;
use InvalidArgumentException;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Rendering\ComponentRenderer;
use Pam\Native\Component;
use Pam\Native\EventKind;
use Pam\Native\Renderable;

final class UiNode extends Component
{
    /** @var array<string, string|int|float|bool> */
    private array $properties = [];

    /** @var list<Renderable> */
    private array $children;

    private ?Closure $pressHandler = null;
    private ?Closure $changeHandler = null;
    private ?Closure $toggleHandler = null;
    private ?Closure $nativeEventHandler = null;

    private function __construct(
        private readonly string $tag,
        Renderable ...$children,
    ) {
        if (!isset(ComponentMap::TAGS[$tag])) {
            throw new InvalidArgumentException("Unknown PAM Mobile UI tag {$tag}.");
        }
        $this->children = array_values($children);
    }

    public static function make(string $tag, Renderable ...$children): self
    {
        return new self($tag, ...$children);
    }

    public function property(
        string $name,
        string|int|float|bool $value,
    ): self {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $name) !== 1) {
            throw new InvalidArgumentException('Property names must be safe identifiers.');
        }
        $copy = clone $this;
        $copy->properties[$name] = $value;

        return $copy;
    }

    public function onPress(Closure $handler): self
    {
        $copy = clone $this;
        $copy->pressHandler = $handler;

        return $copy;
    }

    public function onNativeEvent(Closure $handler): self
    {
        $copy = clone $this;
        $copy->nativeEventHandler = $handler;

        return $copy;
    }

    public function onChange(Closure $handler): self
    {
        $copy = clone $this;
        $copy->changeHandler = $handler;

        return $copy;
    }

    public function onToggle(Closure $handler): self
    {
        $copy = clone $this;
        $copy->toggleHandler = $handler;

        return $copy;
    }

    public function render(): Renderable
    {
        $children = array_map(
            static fn (Renderable $child): \Pam\Native\Element => $child->toElement(),
            $this->children,
        );
        $events = [];
        if ($this->pressHandler !== null) {
            $events[EventKind::Press->value] = $this->pressHandler;
        }
        if ($this->changeHandler !== null) {
            $events[EventKind::Change->value] = $this->changeHandler;
        }
        if ($this->toggleHandler !== null) {
            $events[EventKind::Toggle->value] = $this->toggleHandler;
        }
        if ($this->nativeEventHandler !== null) {
            $events[EventKind::Native->value] = $this->nativeEventHandler;
        }

        return ComponentRenderer::render(
            $this->tag,
            $this->properties,
            $children,
            $events,
            null,
            null,
        );
    }
}
