<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\Align;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\Text;

/** @property (\stdClass&object{selected: bool, visible: bool, message: string}) $state */
final class ChipInteractionPreview extends Component
{
    public function __construct(private readonly string $profile)
    {
    }

    /** @return array{selected: bool, visible: bool, message: string} */
    protected function initialState(): array
    {
        return [
            'selected' => $this->profile === 'filter',
            'visible' => true,
            'message' => '',
        ];
    }

    public function render(): Renderable
    {
        $chip = MaterialComponentMap::TAGS['p-chip'];
        $button = MaterialComponentMap::TAGS['p-btn'];
        $theme = ThemeManager::current();
        $props = match ($this->profile) {
            'filter' => [
                'text' => 'In production',
                'variant' => 'outlined',
                'filter' => true,
                'selected' => (bool) $this->state->selected,
                'accessibilityLabel' => 'Filter production releases',
            ],
            'input' => [
                'text' => 'Android',
                'variant' => 'tonal',
                'closable' => true,
                'closeLabel' => 'Remove Android filter',
                'accessibilityLabel' => 'Android filter',
            ],
            'outlined' => [
                'text' => 'Design system',
                'variant' => 'outlined',
                'accessibilityLabel' => 'Design system tag',
            ],
            'disabled' => [
                'text' => 'Unavailable',
                'variant' => 'tonal',
                'disabled' => true,
                'accessibilityLabel' => 'Unavailable action',
            ],
            'long' => [
                'text' => 'Ready for accessibility review',
                'variant' => 'outlined',
                'accessibilityLabel' => 'Ready for accessibility review',
            ],
            default => [
                'text' => 'View release',
                'variant' => 'tonal',
                'icon' => 'arrow-right',
                'accessibilityLabel' => 'View release details',
            ],
        };

        if (!(bool) $this->state->visible) {
            return Column::make(
                Text::make('Android filter removed')->style(new Style(
                    fontSize: 14.0,
                    lineHeight: 20.0,
                    textColor: $theme->color(ColorToken::MutedForeground),
                )),
                $button::make(
                    ['variant' => 'text', 'size' => 'small'],
                    Text::make('Restore'),
                )->onPress(function (): bool {
                    $this->state->visible = true;
                    $this->state->message = '';

                    return true;
                }),
            )->style(new Style(
                minHeight: 48.0,
                gap: 4.0,
                alignItems: Align::Start,
            ));
        }

        $control = $chip::make($props);
        if ($this->profile === 'filter') {
            $control = $control->onPress(function (): bool {
                $this->state->selected = !(bool) $this->state->selected;
                $this->state->message = (bool) $this->state->selected
                    ? 'Filter selected'
                    : 'Filter cleared';

                return true;
            });
        } elseif ($this->profile === 'input') {
            $control = $control->onClose(function (): bool {
                $this->state->visible = false;
                $this->state->message = 'Filter removed';

                return true;
            });
        } elseif ($this->profile === 'assist') {
            $control = $control->onPress(function (): bool {
                $this->state->message = 'Release opened';

                return true;
            });
        }

        $children = [$control];
        if ((string) $this->state->message !== '') {
            $children[] = Text::make((string) $this->state->message)->style(new Style(
                fontSize: 12.0,
                lineHeight: 16.0,
                textColor: $theme->color(ColorToken::MutedForeground),
            ));
        }

        return Column::make(...$children)->style(new Style(
            minHeight: 48.0,
            gap: 4.0,
            alignItems: Align::Start,
        ));
    }
}
