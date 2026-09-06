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
final class DataTableInteractionPreview extends Component
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
        $table = MaterialComponentMap::TAGS['p-data-table'];
        $theme = ThemeManager::current();
        $selectable = $this->profile === 'selectable';
        $empty = $this->profile === 'empty';
        $props = [
            'headers' => $this->profile === 'mobile'
                ? [
                    ['title' => 'Product', 'key' => 'name'],
                    ['title' => 'Status', 'key' => 'status'],
                ]
                : [
                    ['title' => 'Product', 'key' => 'name'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Price', 'key' => 'price'],
                ],
            'items' => $empty ? [] : $this->rows(),
            'itemValue' => 'id',
            'modelValue' => $selectable ? $this->state->selected : [],
            'showSelect' => $selectable,
            'density' => match ($this->profile) {
                'compact' => 'compact',
                'comfortable' => 'comfortable',
                default => 'default',
            },
            'striped' => $this->profile === 'striped',
            'loading' => $this->profile === 'loading',
            'loadingText' => 'Syncing inventory',
            'noDataText' => 'No products found',
            'mobile' => $this->profile === 'mobile',
            'accessibilityLabel' => ucfirst($this->profile).' product table',
        ];

        $control = $table::make($props);
        if ($selectable) {
            $control = $control->onChange(function (array $next): bool {
                $this->state->selected = array_values(array_map('intval', $next));
                $count = count($this->state->selected);
                $this->state->message = $count === 1
                    ? '1 product selected'
                    : $count.' products selected';

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

    /** @return list<array{id: int, name: string, status: string, price: string}> */
    private function rows(): array
    {
        return [
            ['id' => 1, 'name' => 'PAM Studio', 'status' => 'Live', 'price' => '$48'],
            ['id' => 2, 'name' => 'Native Kit', 'status' => 'Beta', 'price' => '$24'],
            ['id' => 3, 'name' => 'UI Audit', 'status' => 'Ready', 'price' => '$12'],
        ];
    }
}
