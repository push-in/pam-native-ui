<?php

declare(strict_types=1);

namespace App\Forms;

use Pam\Native\Forms\Attributes\Email;
use Pam\Native\Forms\Attributes\MinLength;
use Pam\Native\Forms\Attributes\Required;
use Pam\Native\Forms\NativeForm;
use Pam\NativeUi\Forms\Rules\Accepted;
use Pam\NativeUi\Forms\Rules\Between;
use Pam\NativeUi\Forms\Rules\OneOf;

final class SignUpForm extends NativeForm
{
    #[Required('Tell us your name.')]
    public string $name = '';

    #[Required('Enter your email.')]
    #[Email('Enter a valid email address.')]
    public string $email = '';

    #[Required('Choose a password.')]
    #[MinLength(8, 'Use at least 8 characters.')]
    public string $password = '';

    #[Required('Pick a role.')]
    #[OneOf(['developer', 'designer', 'founder'])]
    public ?string $role = null;

    #[Between(1, 500, 'Team size goes from 1 to 500.')]
    public int $team = 1;

    public ?string $start = null;

    public string $about = '';

    #[Accepted('Accept the terms to continue.')]
    public bool $terms = false;

    public bool $newsletter = true;
}
