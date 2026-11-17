<?php

declare(strict_types=1);

namespace Pam\NativeUi\Forms\Rules;

use Attribute;
use InvalidArgumentException;
use Pam\Native\Forms\NativeForm;
use Pam\Native\Forms\ValidationRule;

/** A number between min and max, inclusive (empty values pass; add Required). */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Between implements ValidationRule
{
    public function __construct(
        public int|float $min,
        public int|float $max,
        public ?string $message = null,
    ) {
        if ($min > $max) {
            throw new InvalidArgumentException('Between minimum cannot exceed its maximum.');
        }
    }

    public function validate(string $field, mixed $value, NativeForm $form): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || $value < $this->min || $value > $this->max) {
            return $this->message ?? "Enter a value from {$this->min} to {$this->max}.";
        }

        return null;
    }
}
