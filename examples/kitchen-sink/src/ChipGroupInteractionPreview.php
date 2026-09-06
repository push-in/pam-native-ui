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

/** @property (\stdClass&object{multiple: list<string>, single: string, message: string}) $state */
final class ChipGroupInteractionPreview extends Component
{
    public function __construct(private readonly string $profile)
    {
    }

    /** @return array{single: string, multiple: list<string>, message: string} */
    protected function initialState(): array
    {
        return [
            'single' => 'android',
            'multiple' => ['design', 'android'],
            'message' => '',
        ];
    }

    public function render(): Renderable
    {
        $group = MaterialComponentMap::TAGS['p-chip-group'];
        $chip = MaterialComponentMap::TAGS['p-chip'];
        $theme = ThemeManager::current();
        $multiple = $this->profile === 'multiple';
        $vertical = $this->profile === 'vertical';
        $disabled = $this->profile === 'disabled';
        $value = $multiple ? $this->state->multiple : $this->state->single;

        $control = $group::make(
            [
                'value' => $value,
                'multiple' => $multiple,
                'direction' => $vertical ? 'vertical' : 'horizontal',
                'disabled' => $disabled,
            ],
            $chip::make([
                'text' => $vertical ? 'Design review' : 'Design',
                'value' => 'design',
                'filter' => true,
                'variant' => 'outlined',
                'accessibilityLabel' => 'Design filter',
            ]),
            $chip::make([
                'text' => $vertical ? 'Android release' : 'Android',
                'value' => 'android',
                'filter' => true,
                'variant' => 'outlined',
                'accessibilityLabel' => 'Android filter',
            ]),
            $chip::make([
                'text' => $vertical ? 'Documentation' : 'Docs',
                'value' => 'docs',
                'filter' => true,
                'variant' => 'outlined',
                'accessibilityLabel' => 'Documentation filter',
            ]),
        )->onChange(function (string|array $next) use ($multiple): bool {
            if ($multiple) {
                $this->state->multiple = is_array($next) ? array_values($next) : [];
                $this->state->message = count($this->state->multiple).' filters selected';
            } else {
                $this->state->single = is_string($next) ? $next : '';
                $this->state->message = 'Selected '.(string) $this->state->single;
            }

            return true;
        });

        $children = [$control];
        if ((string) $this->state->message !== '') {
            $children[] = Text::make((string) $this->state->message)->style(new Style(
                fontSize: 12.0,
                lineHeight: 16.0,
                textColor: $theme->color(ColorToken::MutedForeground),
            ));
        }

        return Column::make(...$children)->style(new Style(
            minHeight: $vertical ? 112.0 : 48.0,
            gap: 4.0,
            alignItems: Align::Start,
        ));
    }
}
