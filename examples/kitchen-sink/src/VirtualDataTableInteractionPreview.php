<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\Text;

/** @property (\stdClass&object{selected: list<int>, message: string}) $state */
final class VirtualDataTableInteractionPreview extends Component
{
    public function __construct(private readonly string $profile)
    {
    }

    /** @return array{selected: list<int>, message: string} */
    protected function initialState(): array
    {
        return ['selected' => [1], 'message' => ''];
    }

    public function render(): Renderable
    {
        $table = MaterialComponentMap::TAGS['p-data-table-virtual'];
        $theme = ThemeManager::current();
        $selectable = $this->profile === 'selectable';
        $props = [
            'headers' => $this->profile === 'mobile'
                ? [
                    ['title' => 'Package', 'key' => 'name'],
                    ['title' => 'State', 'key' => 'status'],
                ]
                : [
                    ['title' => 'Package', 'key' => 'name'],
                    ['title' => 'State', 'key' => 'status'],
                    ['title' => 'Build', 'key' => 'build'],
                ],
            'items' => $this->profile === 'empty' ? [] : $this->rows(),
            'itemValue' => 'id',
            'modelValue' => $selectable ? $this->state->selected : [],
            'showSelect' => $selectable,
            'density' => $this->profile === 'compact' ? 'compact' : 'default',
            'fixedHeader' => $this->profile === 'fixed',
            'striped' => $this->profile === 'striped',
            'loading' => $this->profile === 'loading',
            'loadingText' => 'Refreshing builds',
            'noDataText' => 'No builds found',
            'mobile' => $this->profile === 'mobile',
            'height' => 280,
            'prefetch' => 6,
            'removeClippedSubviews' => true,
            'showsScrollIndicator' => true,
            'endReachedThreshold' => 0.25,
            'accessibilityLabel' => ucfirst($this->profile).' virtual table',
        ];

        $control = $table::make($props)
            ->onEndReached(function (): bool {
                $this->state->message = 'End of build list reached';

                return true;
            });
        if ($selectable) {
            $control = $control->onChange(function (array $next): bool {
                $this->state->selected = array_values(array_map('intval', $next));
                $this->state->message = count($this->state->selected).' builds selected';

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
            widthPercent: 100.0,
            gap: 8.0,
        ));
    }

    /** @return list<array{id: int, name: string, status: string, build: string}> */
    private function rows(): array
    {
        $rows = [];
        for ($index = 1; $index <= 40; $index++) {
            $rows[] = [
                'id' => $index,
                'name' => 'Package '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => $index % 5 === 0 ? 'Review' : 'Ready',
                'build' => '#'.(2100 + $index),
            ];
        }

        return $rows;
    }
}
