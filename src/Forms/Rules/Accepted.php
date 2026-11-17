<?php

declare(strict_types=1);

namespace Pam\NativeUi\Forms\Rules;

use Attribute;
use Pam\Native\Forms\NativeForm;
use Pam\Native\Forms\ValidationRule;

/** The field must be true (a terms checkbox). */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Accepted implements ValidationRule
{
    public function __construct(public string $message = 'Please accept to continue.')
    {
    }

    public function validate(string $field, mixed $value, NativeForm $form): ?string
    {
        return $value === true ? null : $this->message;
    }
}
