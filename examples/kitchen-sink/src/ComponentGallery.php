<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Navigation\DrawerNavigator;
use Pam\Native\Renderable;
use Pam\Native\View;

final class ComponentGallery extends Component
{
    public DrawerNavigator $drawer;
    private bool $checked = true;
    private bool $dialogOpen = false;
    private bool $sheetOpen = false;
    private float $range = 68.0;
    private string $selection = 'native';

    public function render(): Renderable
    {
        return View::make('components');
    }

    public function back(): void
    {
        $this->drawer->openDrawer();
    }

    public function toggle(): void
    {
        $this->checked = !$this->checked;
    }

    public function openDialog(): void
    {
        $this->dialogOpen = true;
    }

    public function closeDialog(): void
    {
        $this->dialogOpen = false;
    }

    public function openSheet(): void
    {
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function changeRange(string $value): void
    {
        $this->range = max(0.0, min(100.0, (float) $value));
    }

    public function select(string $value): void
    {
        $this->selection = $value;
    }

    public function selectNative(): void
    {
        $this->select('native');
    }

    public function selectTyped(): void
    {
        $this->select('typed');
    }

    /**
     * @return array{
     *     checked: bool,
     *     dialogOpen: bool,
     *     sheetOpen: bool,
     *     range: float,
     *     selection: string
     * }
     */
    public function templateState(): array
    {
        return [
            'checked' => $this->checked,
            'dialogOpen' => $this->dialogOpen,
            'sheetOpen' => $this->sheetOpen,
            'range' => $this->range,
            'selection' => $this->selection,
        ];
    }
}
