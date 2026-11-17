<?php

declare(strict_types=1);

namespace Pam\NativeUi\Forms\Rules;

use Attribute;
use InvalidArgumentException;
use Pam\Native\Forms\NativeForm;
use Pam\Native\Forms\ValidationRule;

/** The string must match a PCRE pattern (empty values pass; add Required). */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Pattern implements ValidationRule
{
    public function __construct(
        public string $pattern,
        public string $message = 'This value has an invalid format.',
    ) {
        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException("Invalid validation pattern {$pattern}.");
        }
    }

    public function validate(string $field, mixed $value, NativeForm $form): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && preg_match($this->pattern, $value) === 1 ? null : $this->message;
    }
}
