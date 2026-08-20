<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\MetricTrend;
use Pam\MobileUi\Theme\DesignTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\MotionPreset;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\Row;
use Pam\Native\UI\Text;

final readonly class MetricCard implements Renderable
{
    private function __construct(
        private string $label,
        private string $value,
        private ?string $delta,
        private ?string $supporting,
        private MetricTrend $trend,
    ) {
    }

    public static function make(string $label, string $value): self
    {
        return new self($label, $value, null, null, MetricTrend::Neutral);
    }

    /** @param array<string, mixed> $props
     *  @param list<Renderable> $_children
     */
    public static function fromTemplate(array $props, array $_children): self
    {
        return new self(
            label: self::text($props['label'] ?? null),
            value: self::text($props['value'] ?? null),
            delta: self::nullableText($props['delta'] ?? null),
            supporting: self::nullableText($props['supporting'] ?? null),
            trend: MetricTrend::tryFrom(self::integer($props['trend'] ?? null, 1))
                ?? MetricTrend::Neutral,
        );
    }

    public function delta(string $delta, MetricTrend $trend = MetricTrend::Neutral): self
    {
        return new self($this->label, $this->value, $delta, $this->supporting, $trend);
    }

    public function supporting(string $supporting): self
    {
        return new self($this->label, $this->value, $this->delta, $supporting, $this->trend);
    }

    public function toElement(): \Pam\Native\Element
    {
        $theme = ThemeManager::current();
        [$badge, $badgeText] = match ($this->trend) {
            MetricTrend::Positive => [ColorToken::Success, ColorToken::SuccessForeground],
            MetricTrend::Negative => [ColorToken::Destructive, ColorToken::DestructiveForeground],
            MetricTrend::Warning => [ColorToken::Warning, ColorToken::WarningForeground],
            default => [ColorToken::Secondary, ColorToken::SecondaryForeground],
        };

        $heading = [
            Text::make($this->label)->style(new Style(
                textColor: $theme->color(ColorToken::MutedForeground),
                fontSize: DesignTokens::TEXT_LABEL,
                fontWeight: 600,
                lineHeight: 18.0,
            )),
        ];
        if ($this->delta !== null) {
            $heading[] = Text::make($this->delta)->style(new Style(
                paddingHorizontal: 9.0,
                paddingVertical: 4.0,
                backgroundColor: $theme->color($badge),
                textColor: $theme->color($badgeText),
                borderRadius: DesignTokens::RADIUS_FULL,
                fontSize: 12.0,
                fontWeight: 700,
                lineHeight: 16.0,
            ));
        }

        $content = [
            Row::make(...$heading)->style(new Style(
                gap: 8.0,
                alignItems: \Pam\Native\Align::Center,
                justifyContent: \Pam\Native\Justify::SpaceBetween,
            )),
            Text::make($this->value)->style(new Style(
                textColor: $theme->color(ColorToken::OnSurface),
                fontSize: DesignTokens::TEXT_HEADLINE,
                fontWeight: 700,
                lineHeight: 34.0,
            )),
        ];
        if ($this->supporting !== null) {
            $content[] = Text::make($this->supporting)->style(new Style(
                textColor: $theme->color(ColorToken::MutedForeground),
                fontSize: DesignTokens::TEXT_LABEL,
                lineHeight: 18.0,
            ));
        }

        return Column::make(...$content)->style(new Style(
            minWidth: 104.0,
            minHeight: 116.0,
            flexGrow: 1.0,
            padding: 16.0,
            gap: 8.0,
            backgroundColor: $theme->color(ColorToken::Surface),
            borderColor: $theme->color(ColorToken::Border),
            borderWidth: 1.0,
            borderRadius: DesignTokens::RADIUS_LARGE,
        ))
            ->motion(MotionPreset::FadeIn, DesignTokens::MOTION_STANDARD)
            ->accessibilityRole(AccessibilityRole::Summary)
            ->accessibilityLabel($this->accessibleLabel());
    }

    private function accessibleLabel(): string
    {
        return implode('. ', array_filter([
            $this->label,
            $this->value,
            $this->delta === null ? null : $this->trendLabel().': '.$this->delta,
            $this->supporting,
        ]));
    }

    private function trendLabel(): string
    {
        return match ($this->trend) {
            MetricTrend::Positive => 'Positive trend',
            MetricTrend::Negative => 'Negative trend',
            MetricTrend::Warning => 'Attention',
            MetricTrend::Neutral => 'No trend',
        };
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
