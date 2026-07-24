<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\View;

final class Catalog extends Component
{
    private bool $checked = true;
    private float $progress = 64.0;
    private int $tab = 1;
    private bool $modalOpen = false;
    private bool $sheetOpen = false;
    private bool $drawerOpen = false;
    private bool $popoverOpen = false;
    private bool $selectOpen = false;
    private string $framework = 'Laravel';
    private string $plan = 'starter';
    private string $selectedDate = '2026-07-23';
    private string $notice = 'All interactions use semantic native events.';

    public function render(): Renderable
    {
        return View::make('catalog');
    }

    public function toggle(): void
    {
        $this->checked = !$this->checked;
    }

    public function submit(): void
    {
        $this->notice = 'Profile saved through one compact native event.';
    }

    public function selectTab(string $payload): void
    {
        $this->tab = max(1, min(3, (int) $payload));
    }

    public function updateProgress(string $payload): void
    {
        $this->progress = max(0.0, min(100.0, (float) $payload));
    }

    public function toggleModal(): void
    {
        $this->modalOpen = !$this->modalOpen;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function toggleSheet(): void
    {
        $this->sheetOpen = !$this->sheetOpen;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function toggleDrawer(): void
    {
        $this->drawerOpen = !$this->drawerOpen;
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
    }

    public function togglePopover(): void
    {
        $this->popoverOpen = !$this->popoverOpen;
    }

    public function chooseMenu(string $action): void
    {
        $this->notice = "Native menu action: {$action}.";
    }

    public function toggleSelect(): void
    {
        $this->selectOpen = !$this->selectOpen;
    }

    public function closeSelect(): void
    {
        $this->selectOpen = false;
    }

    public function selectDate(string $payload): void
    {
        if ($payload !== '') {
            $this->selectedDate = $payload;
        }
    }

    /**
     * @return array{
     *     checked: bool,
     *     progress: float,
     *     tab: int,
     *     modalOpen: bool,
     *     sheetOpen: bool,
     *     drawerOpen: bool,
     *     popoverOpen: bool,
     *     selectOpen: bool,
     *     framework: string,
     *     plan: string,
     *     selectedDate: string,
     *     notice: string
     * }
     */
    public function templateState(): array
    {
        return [
            'checked' => $this->checked,
            'progress' => $this->progress,
            'tab' => $this->tab,
            'modalOpen' => $this->modalOpen,
            'sheetOpen' => $this->sheetOpen,
            'drawerOpen' => $this->drawerOpen,
            'popoverOpen' => $this->popoverOpen,
            'selectOpen' => $this->selectOpen,
            'framework' => $this->framework,
            'plan' => $this->plan,
            'selectedDate' => $this->selectedDate,
            'notice' => $this->notice,
        ];
    }

    public function chooseFramework(string $framework): void
    {
        $this->framework = $framework;
        $this->selectOpen = false;
    }

    public function choosePlan(string $plan): void
    {
        $this->plan = $plan;
    }
}
