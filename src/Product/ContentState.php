<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Closure;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Theme\DesignTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\AccessibilityRole;
use Pam\Native\AsyncStatus;
use Pam\Native\AsyncValue;
use Pam\Native\MotionPreset;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\ActivityIndicator;
use Pam\Native\UI\Button;
use Pam\Native\UI\Column;
use Pam\Native\UI\Text;

final readonly class ContentState implements Renderable
{
    /** @param list<Renderable> $content */
    private function __construct(
        private AsyncValue $state,
        private array $content,
        private ?Closure $retry,
    ) {
    }

    public static function make(AsyncValue $state, Renderable ...$content): self
    {
        return new self($state, array_values($content), null);
    }

    /** @param array<string, mixed> $props
     *  @param list<Renderable> $children
     */
    public static function fromTemplate(array $props, array $children): self
    {
        $state = $props['state'] ?? null;
        if (!$state instanceof AsyncValue) {
            $status = AsyncStatus::tryFrom(self::integer($props['status'] ?? null, 1))
                ?? AsyncStatus::Loading;
            $message = is_scalar($props['message'] ?? null) ? (string) $props['message'] : null;
            $state = match ($status) {
                AsyncStatus::Content => AsyncValue::content(true),
                AsyncStatus::Empty => AsyncValue::empty($message),
                AsyncStatus::Error => AsyncValue::error($message ?? 'Something went wrong.'),
                AsyncStatus::Offline => AsyncValue::offline(message: $message ?? 'You are offline.'),
                AsyncStatus::Stale => AsyncValue::stale(true, $message ?? 'Showing saved content.'),
                default => AsyncValue::loading(),
            };
        }
        $events = is_array($props['__pamComponentEvents'] ?? null)
            ? $props['__pamComponentEvents']
            : [];
        $retry = $events['retry'] ?? null;

        return new self($state, $children, $retry instanceof Closure ? $retry : null);
    }

    public function onRetry(Closure $retry): self
    {
        return new self($this->state, $this->content, $retry);
    }

    public function toElement(): \Pam\Native\Element
    {
        if ($this->state->status === AsyncStatus::Content) {
            return Column::make(...$this->content)->toElement();
        }
        if (
            in_array($this->state->status, [AsyncStatus::Offline, AsyncStatus::Stale], true)
            && $this->state->hasData()
        ) {
            return Column::make(
                $this->feedback(compact: true),
                ...$this->content,
            )->style(new Style(gap: 12.0))->toElement();
        }
        if ($this->state->status === AsyncStatus::Loading && $this->state->hasData()) {
            return Column::make(
                $this->feedback(compact: true),
                ...$this->content,
            )->style(new Style(gap: 12.0))->toElement();
        }

        return $this->feedback(compact: false)->toElement();
    }

    private function feedback(bool $compact): Column
    {
        $theme = ThemeManager::current();
        if ($this->state->status === AsyncStatus::Loading) {
            return Column::make(
                ActivityIndicator::make()->color($theme->color(ColorToken::Primary)),
                Text::make('Loading content')->style(new Style(
                    textColor: $theme->color(ColorToken::MutedForeground),
                    fontSize: 15.0,
                )),
            )
                ->style(new Style(
                    minHeight: $compact ? 56.0 : 220.0,
                    alignItems: \Pam\Native\Align::Center,
                    justifyContent: \Pam\Native\Justify::Center,
                    gap: 12.0,
                ))
                ->accessibilityBusy()
                ->accessibilityLiveRegion(AccessibilityLiveRegion::Polite);
        }
        $title = match ($this->state->status) {
            AsyncStatus::Empty => 'Nothing here yet',
            AsyncStatus::Offline => 'Offline',
            AsyncStatus::Stale => 'Saved content',
            default => 'Could not load content',
        };
        $message = $this->state->message ?? match ($this->state->status) {
            AsyncStatus::Empty => 'New content will appear here when it is available.',
            AsyncStatus::Offline => 'Check your connection and try again.',
            AsyncStatus::Stale => 'Reconnect to refresh this information.',
            default => 'Please try again in a moment.',
        };
        $children = [
            Text::make($title)
                ->style(new Style(
                    textColor: $theme->color(ColorToken::OnSurface),
                    fontSize: $compact ? 15.0 : DesignTokens::TEXT_TITLE,
                    fontWeight: 700,
                ))
                ->accessibilityRole(AccessibilityRole::Header),
            Text::make($message)->style(new Style(
                textColor: $theme->color(ColorToken::MutedForeground),
                fontSize: $compact ? 13.0 : 15.0,
                lineHeight: 22.0,
                textAlign: \Pam\Native\TextAlignment::Center,
            )),
        ];
        if ($this->retry !== null && $this->state->retryable) {
            $children[] = Button::make('Try again')
                ->onPress($this->retry)
                ->style(new Style(
                    minWidth: 120.0,
                    minHeight: 48.0,
                    backgroundColor: $theme->color(ColorToken::Primary),
                    textColor: $theme->color(ColorToken::PrimaryForeground),
                    borderRadius: DesignTokens::RADIUS_MEDIUM,
                ));
        }

        return Column::make(...$children)
            ->style(new Style(
                minHeight: $compact ? 72.0 : 220.0,
                padding: $compact ? 12.0 : 24.0,
                backgroundColor: $theme->color(
                    $compact ? ColorToken::SurfaceSunken : ColorToken::Surface,
                ),
                borderColor: $theme->color(ColorToken::Border),
                borderWidth: 1.0,
                borderRadius: DesignTokens::RADIUS_LARGE,
                alignItems: \Pam\Native\Align::Center,
                justifyContent: \Pam\Native\Justify::Center,
                gap: $compact ? 4.0 : 12.0,
            ))
            ->motion(MotionPreset::FadeIn, DesignTokens::MOTION_STANDARD)
            ->accessibilityLiveRegion(AccessibilityLiveRegion::Polite);
    }

    private static function integer(mixed $value, int $fallback): int
    {
        return is_int($value)
            ? $value
            : (is_string($value) && is_numeric($value) ? (int) $value : $fallback);
    }
}
