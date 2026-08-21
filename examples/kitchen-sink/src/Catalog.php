<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Navigation\Navigator;
use Pam\Native\Renderable;
use Pam\Native\View;

final class Catalog extends Component
{
    public Navigator $navigator;
    private bool $checked = true;
    private float $progress = 64.0;
    private bool $modalOpen = false;
    private bool $sheetOpen = false;
    private bool $drawerOpen = false;
    private bool $selectOpen = false;
    private string $framework = 'Laravel';
    private string $email = '';
    private string $plan = 'starter';
    private string $selectedDate = '2026-07-23';
    private string $notice = 'All interactions use semantic native events.';
    /** @var list<string> */
    private array $nativePackages = [
        'pushinbr/pam-native',
        'pushinbr/pam-native-ui',
        'pushinbr/pam-plugin-api',
        'pushinbr/pam-cli',
    ];
    /** @var list<string> */
    private array $expandedFolders = ['/src'];
    /** @var list<string> */
    private array $expandedAccordions = ['performance', 'freedom'];

    public function render(): Renderable
    {
        return View::make('catalog');
    }

    public function openComponents(): void
    {
        $this->navigator->push('components');
    }

    public function toggle(): void
    {
        $this->checked = !$this->checked;
    }

    public function submit(): void
    {
        $this->notice = 'Profile saved through one compact native event.';
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
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
     *     modalOpen: bool,
     *     sheetOpen: bool,
     *     drawerOpen: bool,
     *     selectOpen: bool,
     *     framework: string,
     *     email: string,
     *     plan: string,
     *     selectedDate: string,
     *     expandedFolders: list<string>,
     *     expandedAccordions: list<string>,
     *     nativePackages: list<string>,
     *     notice: string
     * }
     */
    public function templateState(): array
    {
        return [
            'checked' => $this->checked,
            'progress' => $this->progress,
            'modalOpen' => $this->modalOpen,
            'sheetOpen' => $this->sheetOpen,
            'drawerOpen' => $this->drawerOpen,
            'selectOpen' => $this->selectOpen,
            'framework' => $this->framework,
            'email' => $this->email,
            'plan' => $this->plan,
            'selectedDate' => $this->selectedDate,
            'expandedFolders' => $this->expandedFolders,
            'expandedAccordions' => $this->expandedAccordions,
            'nativePackages' => $this->nativePackages,
            'notice' => $this->notice,
        ];
    }

    public function loadMorePackages(): void
    {
        $this->notice = 'The recycled native list reached its loading boundary.';
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
