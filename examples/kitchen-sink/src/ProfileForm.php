<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Forms\Attributes\Email;
use Pam\Native\Forms\Attributes\MinLength;
use Pam\Native\Forms\Attributes\Required;
use Pam\Native\Forms\NativeForm;

final class ProfileForm extends NativeForm
{
    #[Required]
    #[MinLength(2)]
    public string $name = 'David Balbino';

    #[Required]
    #[Email]
    public string $email = 'david@pam.dev';

    #[MinLength(3)]
    public string $company = 'Pushin';
}
