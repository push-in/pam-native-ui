<?php

declare(strict_types=1);

namespace Certification;

use Pam\Native\Component;
use Pam\Native\View;

final class FixtureScreen extends Component
{
    public function render(): View
    {
        return View::make('fixtures', [
            'autocompleteItems' => ['Button', 'Card', 'Dialog'],
            'comboboxItems' => ['Payments', 'Realtime', 'Media'],
        ]);
    }
}
