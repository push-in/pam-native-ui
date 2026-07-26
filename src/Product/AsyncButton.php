<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Closure;
use Pam\MobileUi\Enum\ActionStatus;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\InterfaceDensity;
use Pam\MobileUi\Theme\DesignTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\MotionPreset;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Button;

final readonly class AsyncButton implements Renderable
{
    private function __construct(
        private string $label,
        private ActionStatus $status,
        private ?Closure $press,
        private InterfaceDensity $density,
    ) {
    }

    public static function make(string $label): self
    {
        return new self($label, ActionStatus::Idle, null, InterfaceDensity::Comfortable);
    }

    /** @param array<string, mixed> $props
     *  @param list<Renderable> $_children
     */
    public static function fromTemplate(array $props, array $_children): self
    {
        $rawLabel = $props['label'] ?? $props['text'] ?? null;

        return new self(
            is_scalar($rawLabel)
                ? (string) $rawLabel
                : 'Continue',
            ActionStatus::tryFrom(self::integer($props['status'] ?? null, 1))
                ?? ActionStatus::Idle,
            null,
            InterfaceDensity::tryFrom(self::integer($props['density'] ?? null, 2))
                ?? InterfaceDensity::Comfortable,
        );
    }

    public function status(ActionStatus $status): self
    {
        return new self($this->label, $status, $this->press, $this->density);
    }

    public function onPress(Closure $press): self
    {
        return new self($this->label, $this->status, $press, $this->density);
    }

    public function toElement(): \Pam\Native\Element
    {
        $theme = ThemeManager::current();
        $label = match ($this->status) {
            ActionStatus::Loading => 'Working...',
            ActionStatus::Success => 'Completed',
            ActionStatus::Error => 'Try again',
            default => $this->label,
        };
        $background = match ($this->status) {
            ActionStatus::Success => ColorToken::Success,
            ActionStatus::Error => ColorToken::Destructive,
            default => ColorToken::Primary,
        };
        $foreground = match ($this->status) {
            ActionStatus::Success => ColorToken::SuccessForeground,
            ActionStatus::Error => ColorToken::DestructiveForeground,
            default => ColorToken::PrimaryForeground,
        };
        $button = Button::make($label)
            ->loading($this->status === ActionStatus::Loading)
            ->enabled($this->status !== ActionStatus::Loading)
            ->style(new Style(
                minHeight: DesignTokens::controlHeight($this->density),
                paddingHorizontal: 20.0,
                backgroundColor: $theme->color($background),
                textColor: $theme->color($foreground),
                borderRadius: DesignTokens::RADIUS_MEDIUM,
                fontSize: DesignTokens::TEXT_BODY,
                fontWeight: 700,
            ))
            ->accessibilityBusy($this->status === ActionStatus::Loading);
        if ($this->status === ActionStatus::Success) {
            $button = $button->motion(MotionPreset::Success, 300);
        } elseif ($this->status === ActionStatus::Error) {
            $button = $button->motion(MotionPreset::Shake, 260);
        }

        return $this->press === null ? $button : $button->onPress($this->press);
    }

    private static function integer(mixed $value, int $fallback): int
    {
        return is_int($value)
            ? $value
            : (is_string($value) && is_numeric($value) ? (int) $value : $fallback);
    }
}
