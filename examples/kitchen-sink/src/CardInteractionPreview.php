<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Material\PBtn;
use Pam\MobileUi\Material\PCard;
use Pam\MobileUi\Material\PCardActions;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\Text;

/** Keeps card interaction state local so one press never rebuilds the catalog route. */
/** @property (\stdClass&object{mode: string|null}) $state */
final class CardInteractionPreview extends Component
{
    /** @param array<string, mixed> $props */
    public function __construct(private readonly array $props)
    {
    }

    /** @return array{mode: null|string} */
    protected function initialState(): array
    {
        return ['mode' => null];
    }

    public function render(): Renderable
    {
        $theme = ThemeManager::current();
        $mode = is_string($this->state->mode) ? $this->state->mode : null;
        $disabled = ($this->props['disabled'] ?? false) === true;
        $title = match ($mode) {
            'card' => 'Card activated',
            'details' => 'Details selected',
            'continue' => 'Continue selected',
            default => $disabled ? 'Archived workspace' : 'Native experience',
        };
        $description = match ($mode) {
            'card' => 'The complete card press target responded.',
            'details' => 'The secondary action responded independently.',
            'continue' => 'The primary action responded independently.',
            default => $disabled
                ? 'Actions and the parent surface are unavailable.'
                : 'Build polished products with platform-native views.',
        };

        $content = Column::make(
            Text::make($title)->style(new Style(
                fontSize: 20.0,
                lineHeight: 28.0,
                fontWeight: 500,
                textColor: $theme->color(ColorToken::OnSurface),
            )),
            Text::make($disabled ? 'Read only' : 'PAM Studio')->style(new Style(
                fontSize: 12.0,
                lineHeight: 16.0,
                fontWeight: 500,
                textColor: $theme->color(ColorToken::MutedForeground),
            )),
            Text::make($description)->style(new Style(
                marginTop: 8.0,
                fontSize: 14.0,
                lineHeight: 20.0,
                fontWeight: 400,
                textColor: $theme->color(ColorToken::OnSurface),
            )),
        )->style(new Style(
            widthPercent: 100.0,
            paddingHorizontal: 16.0,
            paddingTop: 16.0,
            paddingBottom: 8.0,
            gap: 4.0,
        ));

        return PCard::make(
            $this->props,
            $content,
            PCardActions::make(
                [],
                PBtn::make(
                    [
                        'variant' => 'text',
                        'disabled' => $disabled,
                        'accessibilityLabel' => 'Show card details',
                    ],
                    Text::make('Details'),
                )->onPress(function (): bool {
                    $this->state->mode = 'details';

                    return true;
                }),
                PBtn::make(
                    [
                        'variant' => 'tonal',
                        'disabled' => $disabled,
                        'accessibilityLabel' => 'Continue from card',
                    ],
                    Text::make('Continue'),
                )->onPress(function (): bool {
                    $this->state->mode = 'continue';

                    return true;
                }),
            ),
        )
            ->accessibilityRole(AccessibilityRole::Button)
            ->onPress(function () use ($mode): bool {
                $this->state->mode = $mode === null ? 'card' : null;

                return true;
            });
    }
}
