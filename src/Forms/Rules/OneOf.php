<?php

declare(strict_types=1);

namespace Pam\NativeUi\Forms\Rules;

use Attribute;
use Pam\Native\Forms\NativeForm;
use Pam\Native\Forms\ValidationRule;

/** The value must be one of the allowed values (a select or radio group). */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class OneOf implements ValidationRule
{
    /** @param list<string|int|float> $values */
    public function __construct(
        public array $values,
        public string $message = 'Choose one of the options.',
    ) {
    }

    public function validate(string $field, mixed $value, NativeForm $form): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array($value, $this->values, false) ? null : $this->message;
    }
}
