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

    public function render(): Renderable
    {
        return View::make('catalog');
    }

    public function toggle(): void
    {
        $this->checked = !$this->checked;
    }

    public function selectTab(string $payload): void
    {
        $this->tab = max(1, min(3, (int) $payload));
    }

    public function updateProgress(string $payload): void
    {
        $this->progress = max(0.0, min(100.0, (float) $payload));
    }

    /** @return array{checked: bool, progress: float, tab: int} */
    public function templateState(): array
    {
        return [
            'checked' => $this->checked,
            'progress' => $this->progress,
            'tab' => $this->tab,
        ];
    }
}
