<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use Pam\MobileUi\Enum\ColorToken;
use Pam\Native\PropKey;
use Pam\Native\TemplateRegistry;

final class ThemeClassRegistry
{
    private function __construct()
    {
    }

    public static function apply(Theme $theme): void
    {
        foreach (self::tokens($theme) as $name => $properties) {
            TemplateRegistry::style($name, $properties);
        }
    }

    /** @return array<string, array<int, int|float>> */
    private static function tokens(Theme $theme): array
    {
        return [
            'ui-bg' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Background),
            ],
            'ui-surface' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Background),
                PropKey::TextColor->value => $theme->color(ColorToken::Foreground),
            ],
            'ui-muted' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Muted),
                PropKey::TextColor->value => $theme->color(ColorToken::MutedForeground),
            ],
            'ui-card' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Card),
                PropKey::BorderColor->value => $theme->color(ColorToken::Border),
                PropKey::BorderWidth->value => 1.0,
                PropKey::BorderRadius->value => 12.0,
            ],
            'pam-surface' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Surface),
                PropKey::TextColor->value => $theme->color(ColorToken::OnSurface),
            ],
            'pam-surface-elevated' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::SurfaceElevated),
                PropKey::TextColor->value => $theme->color(ColorToken::OnSurface),
                PropKey::BorderColor->value => $theme->color(ColorToken::Border),
                PropKey::BorderWidth->value => 1.0,
                PropKey::BorderRadius->value => DesignTokens::RADIUS_LARGE,
                PropKey::Elevation->value => 3.0,
            ],
            'pam-surface-sunken' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::SurfaceSunken),
                PropKey::TextColor->value => $theme->color(ColorToken::OnSurface),
            ],
            'pam-success' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Success),
                PropKey::TextColor->value => $theme->color(ColorToken::SuccessForeground),
            ],
            'pam-warning' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Warning),
                PropKey::TextColor->value => $theme->color(ColorToken::WarningForeground),
            ],
            'pam-info' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Info),
                PropKey::TextColor->value => $theme->color(ColorToken::InfoForeground),
            ],
            'ui-text' => [
                PropKey::TextColor->value => $theme->color(ColorToken::Foreground),
            ],
            'ui-text-muted' => [
                PropKey::TextColor->value => $theme->color(ColorToken::MutedForeground),
            ],
            'ui-primary' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Primary),
                PropKey::TextColor->value => $theme->color(ColorToken::PrimaryForeground),
            ],
            'ui-secondary' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Secondary),
                PropKey::TextColor->value => $theme->color(ColorToken::SecondaryForeground),
            ],
            'ui-destructive' => [
                PropKey::BackgroundColor->value => $theme->color(ColorToken::Destructive),
            ],
            'ui-focus' => [
                PropKey::BorderColor->value => $theme->color(ColorToken::Ring),
                PropKey::BorderWidth->value => 2.0,
            ],
            'ui-disabled' => [
                PropKey::Opacity->value => 0.4,
            ],
        ];
    }
}
