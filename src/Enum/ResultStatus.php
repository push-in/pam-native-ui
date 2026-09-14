<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

use InvalidArgumentException;

enum ResultStatus: int
{
    case Success = 1;
    case Error = 2;
    case Warning = 3;
    case Empty = 4;

    public static function resolve(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_int($value)) {
            return self::tryFrom($value)
                ?? throw new InvalidArgumentException('Unknown result status.');
        }
        // Compatibility at the public UI boundary; new callers use integer codes.
        return match ($value) {
            null, 'success' => self::Success,
            'error' => self::Error,
            'warning' => self::Warning,
            'empty' => self::Empty,
            default => throw new InvalidArgumentException('Unknown result status.'),
        };
    }
}
