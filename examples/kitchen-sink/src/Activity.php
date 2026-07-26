<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\View;

final class Activity extends Component
{
    public function render(): Renderable
    {
        return View::make('activity');
    }
}
