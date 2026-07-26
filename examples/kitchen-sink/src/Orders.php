<?php

declare(strict_types=1);

namespace App;

use Pam\Native\AsyncStatus;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\View;

final class Orders extends Component
{
    public int $status = 2;

    public function render(): Renderable
    {
        return View::make('orders');
    }

    public function showLoading(): void
    {
        $this->status = AsyncStatus::Loading->value;
    }

    public function showEmpty(): void
    {
        $this->status = AsyncStatus::Empty->value;
    }

    public function showError(): void
    {
        $this->status = AsyncStatus::Error->value;
    }

    public function retry(): void
    {
        $this->status = AsyncStatus::Content->value;
    }
}
