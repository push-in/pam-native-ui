<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Navigation\DrawerNavigator;
use Pam\Native\Renderable;
use Pam\Native\View;

final class ShowcaseRoute extends Component
{
    public DrawerNavigator $drawer;
    private bool $dialogOpen = false;
    private bool $sheetOpen = false;
    private bool $snackbarOpen = false;
    private bool $loadingOverlayOpen = false;
    private string $feedback = 'Choose an overlay to inspect its native behavior.';

    public function __construct(private readonly string $view)
    {
    }

    public function render(): Renderable
    {
        return View::make($this->view);
    }

    public function openDrawer(): void
    {
        $this->drawer->openDrawer();
    }

    public function closeDrawer(): void
    {
        $this->drawer->closeDrawer();
    }

    public function goOverview(): void
    {
        $this->navigate('components');
    }

    public function goActions(): void
    {
        $this->navigate('showcase-actions');
    }

    public function goForms(): void
    {
        $this->navigate('showcase-forms');
    }

    public function goData(): void
    {
        $this->navigate('showcase-data');
    }

    public function goOverlays(): void
    {
        $this->navigate('showcase-overlays');
    }

    public function goAll(): void
    {
        $this->navigate('all-components');
    }

    public function openDialog(): void
    {
        $this->dialogOpen = true;
    }

    public function closeDialog(): void
    {
        $this->dialogOpen = false;
        $this->feedback = 'Dialog dismissed with native focus restoration.';
    }

    public function openSheet(): void
    {
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
        $this->feedback = 'Bottom sheet dismissed.';
    }

    public function showSnackbar(): void
    {
        $this->snackbarOpen = true;
        $this->feedback = 'Snackbar announced through the native live region.';
    }

    public function closeSnackbar(): void
    {
        $this->snackbarOpen = false;
    }

    public function showLoadingOverlay(): void
    {
        $this->loadingOverlayOpen = true;
    }

    public function closeLoadingOverlay(): void
    {
        $this->loadingOverlayOpen = false;
        $this->feedback = 'Loading overlay completed without shifting layout.';
    }

    public function inspectMenuItem(): void
    {
        $this->feedback = 'Inspect selected from the anchored native menu.';
    }

    public function shareMenuItem(): void
    {
        $this->feedback = 'Share selected from the anchored native menu.';
    }

    /**
     * @return array{
     *     dialogOpen: bool,
     *     sheetOpen: bool,
     *     snackbarOpen: bool,
     *     loadingOverlayOpen: bool,
     *     feedback: string
     * }
     */
    public function templateState(): array
    {
        return [
            'dialogOpen' => $this->dialogOpen,
            'sheetOpen' => $this->sheetOpen,
            'snackbarOpen' => $this->snackbarOpen,
            'loadingOverlayOpen' => $this->loadingOverlayOpen,
            'feedback' => $this->feedback,
        ];
    }

    private function navigate(string $route): void
    {
        $this->drawer->navigate(match ($route) {
            'components' => 'overview',
            'showcase-actions' => 'actions',
            'showcase-forms' => 'forms',
            'showcase-data' => 'data',
            'showcase-overlays' => 'overlays',
            'all-components' => 'all',
            default => $route,
        });
    }
}
