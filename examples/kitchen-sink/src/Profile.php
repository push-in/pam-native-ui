<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Enum\ActionStatus;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\View;

final class Profile extends Component
{
    public ProfileForm $form;
    public int $actionStatus = 1;

    public function __construct()
    {
        $this->form = new ProfileForm();
        $this->form->restoreDraft('showcase-profile');
    }

    public function render(): Renderable
    {
        return View::make('profile');
    }

    public function save(): void
    {
        $this->actionStatus = ActionStatus::Loading->value;
        if (!$this->form->beginSubmit()) {
            $this->actionStatus = ActionStatus::Error->value;
            return;
        }
        $this->form->saveDraft('showcase-profile');
        $this->form->succeed();
        $this->actionStatus = ActionStatus::Success->value;
    }
}
