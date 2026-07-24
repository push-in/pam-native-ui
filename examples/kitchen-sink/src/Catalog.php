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
    private bool $galleryOpen = false;
    private int $galleryIndex = 0;
    private int $responseBranch = 0;
    private string $framework = 'Laravel';
    private string $email = '';
    private string $plan = 'starter';
    private string $selectedDate = '2026-07-23';
    private string $selectedFile = '/src/App.php';
    private string $notice = 'All interactions use semantic native events.';
    /** @var list<string> */
    private array $nativePackages = [
        'pushinbr/pam-native',
        'pushinbr/pam-mobile-ui',
        'pushinbr/pam-plugin-api',
        'pushinbr/pam-cli',
    ];
    /** @var list<array{url: string, alt: string}> */
    private array $gallery = [
        [
            'url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?w=1600',
            'alt' => 'Mountains under a bright sky',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1470252649378-9c29740c9fa8?w=1600',
            'alt' => 'Sunrise over a green landscape',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=1600',
            'alt' => 'Sunlight crossing a forest',
        ],
    ];
    /** @var list<string> */
    private array $expandedFolders = ['/src'];

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

    public function setEmail(string $email): void
    {
        $this->email = $email;
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

    public function setGalleryOpen(bool $open): void
    {
        $this->galleryOpen = $open;
    }

    public function selectGalleryImage(string $payload): void
    {
        $this->galleryIndex = max(
            0,
            min(count($this->gallery) - 1, (int) $payload),
        );
    }

    public function selectResponseBranch(string $payload): void
    {
        $this->responseBranch = max(0, min(1, (int) $payload));
    }

    public function sendPrompt(string $prompt): void
    {
        $this->notice = $prompt === ''
            ? 'Submitted prompt with attachments.'
            : "Submitted prompt: {$prompt}";
    }

    public function selectFile(string $path): void
    {
        $this->selectedFile = $path;
        $this->notice = "Selected file: {$path}";
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
     *     galleryOpen: bool,
     *     galleryIndex: int,
     *     gallery: list<array{url: string, alt: string}>,
     *     responseBranch: int,
     *     framework: string,
     *     email: string,
     *     plan: string,
     *     selectedDate: string,
     *     selectedFile: string,
     *     expandedFolders: list<string>,
     *     nativePackages: list<string>,
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
            'galleryOpen' => $this->galleryOpen,
            'galleryIndex' => $this->galleryIndex,
            'gallery' => $this->gallery,
            'responseBranch' => $this->responseBranch,
            'framework' => $this->framework,
            'email' => $this->email,
            'plan' => $this->plan,
            'selectedDate' => $this->selectedDate,
            'selectedFile' => $this->selectedFile,
            'expandedFolders' => $this->expandedFolders,
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
