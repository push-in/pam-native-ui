<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Closure;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\StatusTone;
use Pam\MobileUi\Theme\DesignTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\AccessibilityRole;
use Pam\Native\MotionPreset;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\ActivityIndicator;
use Pam\Native\UI\Button;
use Pam\Native\UI\Column;
use Pam\Native\UI\Row;
use Pam\Native\UI\Text;

final readonly class StatusBanner implements Renderable
{
    private function __construct(
        private string $title,
        private ?string $message,
        private StatusTone $tone,
        private ?string $actionLabel,
        private ?Closure $action,
    ) {
    }

    public static function make(string $title, StatusTone $tone = StatusTone::Information): self
    {
        return new self($title, null, $tone, null, null);
    }

    /** @param array<string, mixed> $props
     *  @param list<Renderable> $_children
     */
    public static function fromTemplate(array $props, array $_children): self
    {
        $events = is_array($props['__pamComponentEvents'] ?? null)
            ? $props['__pamComponentEvents']
            : [];
        $action = $events['action'] ?? null;

        return new self(
            title: self::text($props['title'] ?? null),
            message: self::nullableText($props['message'] ?? null),
            tone: StatusTone::tryFrom(self::integer($props['tone'] ?? null, 1))
                ?? StatusTone::Information,
            actionLabel: self::nullableText($props['actionLabel'] ?? null),
            action: $action instanceof Closure ? $action : null,
        );
    }

    public function message(string $message): self
    {
        return new self($this->title, $message, $this->tone, $this->actionLabel, $this->action);
    }

    public function action(string $label, Closure $action): self
    {
        return new self($this->title, $this->message, $this->tone, $label, $action);
    }

    public function toElement(): \Pam\Native\Element
    {
        $theme = ThemeManager::current();
        [$surface, $foreground] = match ($this->tone) {
            StatusTone::Success => [ColorToken::Success, ColorToken::SuccessForeground],
            StatusTone::Warning => [ColorToken::Warning, ColorToken::WarningForeground],
            StatusTone::Error => [ColorToken::Destructive, ColorToken::DestructiveForeground],
            default => [ColorToken::Info, ColorToken::InfoForeground],
        };
        $content = [
            Text::make($this->title)->style(new Style(
                textColor: $theme->color($foreground),
                fontSize: 15.0,
                fontWeight: 700,
                lineHeight: 20.0,
            )),
        ];
        if ($this->message !== null) {
            $content[] = Text::make($this->message)->style(new Style(
                textColor: $theme->color($foreground),
                fontSize: DesignTokens::TEXT_LABEL,
                lineHeight: 19.0,
            ));
        }
        if ($this->action !== null && $this->actionLabel !== null) {
            $content[] = Button::make($this->actionLabel)
                ->onPress($this->action)
                ->style(new Style(
                    minHeight: 48.0,
                    paddingHorizontal: 16.0,
                    backgroundColor: $theme->color($foreground),
                    textColor: $theme->color($surface),
                    borderRadius: DesignTokens::RADIUS_MEDIUM,
                    fontWeight: 700,
                ));
        }

        $leading = $this->tone === StatusTone::Progress
            ? ActivityIndicator::make()->color($theme->color($foreground))
            : Column::make()->style(new Style(
                width: 4.0,
                minHeight: 48.0,
                backgroundColor: $theme->color($foreground),
                borderRadius: DesignTokens::RADIUS_FULL,
            ));

        return Row::make(
            $leading,
            Column::make(...$content)->style(new Style(flexGrow: 1.0, gap: 5.0)),
        )->style(new Style(
            widthPercent: 100.0,
            minHeight: 64.0,
            padding: 14.0,
            gap: 12.0,
            alignItems: \Pam\Native\Align::Center,
            backgroundColor: $theme->color($surface),
            borderRadius: DesignTokens::RADIUS_LARGE,
        ))
            ->motion(MotionPreset::FadeIn, DesignTokens::MOTION_STANDARD)
            ->accessibilityRole($this->tone === StatusTone::Error
                ? AccessibilityRole::Alert
                : AccessibilityRole::Summary)
            ->accessibilityLiveRegion($this->tone === StatusTone::Error
                ? AccessibilityLiveRegion::Assertive
                : AccessibilityLiveRegion::Polite)
            ->accessibilityBusy($this->tone === StatusTone::Progress)
            ->accessibilityLabel($this->accessibleLabel());
    }

    private function accessibleLabel(): string
    {
        $prefix = match ($this->tone) {
            StatusTone::Information => 'Information',
            StatusTone::Success => 'Success',
            StatusTone::Warning => 'Warning',
            StatusTone::Error => 'Error',
            StatusTone::Progress => 'In progress',
        };

        return implode('. ', array_filter([$prefix, $this->title, $this->message]));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function nullableText(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function integer(mixed $value, int $fallback): int
    {
        return is_int($value)
            ? $value
            : (is_string($value) && is_numeric($value) ? (int) $value : $fallback);
    }
}
