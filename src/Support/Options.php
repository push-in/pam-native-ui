<?php

declare(strict_types=1);

namespace Pam\NativeUi\Support;

use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

/**
 * Normalizes the `options` of choice components. Accepted shapes:
 *
 *  - a list of scalars: ['Free', 'Pro']
 *  - a value => label map: ['free' => 'Free', 'pro' => 'Pro']
 *  - a list of rows: [['value' => 'free', 'label' => 'Free', 'description' => '…', 'disabled' => false]]
 *  - a list of backed enum cases (label: a `label()` method when present, else the case name)
 *
 * @internal
 */
final class Options
{
    private function __construct()
    {
    }

    /**
     * @param iterable<mixed> $options
     * @return list<array{key: string, value: string|int|float|bool, label: string, description: ?string, disabled: bool}>
     */
    public static function normalize(iterable $options): array
    {
        $rows = [];
        $list = is_array($options) && array_is_list($options);
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? $key;
                $label = $option['label'] ?? (is_scalar($value) ? (string) $value : '');
                $row = [
                    'value' => self::scalar($value),
                    'label' => (string) $label,
                    'description' => isset($option['description']) ? (string) $option['description'] : null,
                    'disabled' => (bool) ($option['disabled'] ?? false),
                ];
            } elseif ($option instanceof BackedEnum) {
                $row = [
                    'value' => $option->value,
                    'label' => method_exists($option, 'label') ? (string) $option->label() : $option->name,
                    'description' => null,
                    'disabled' => false,
                ];
            } elseif ($option instanceof UnitEnum) {
                $row = ['value' => $option->name, 'label' => $option->name, 'description' => null, 'disabled' => false];
            } elseif (is_scalar($option)) {
                $row = [
                    'value' => $list ? $option : self::scalar($key),
                    'label' => (string) $option,
                    'description' => null,
                    'disabled' => false,
                ];
            } else {
                throw new InvalidArgumentException('Options must be scalars, value => label pairs, rows or backed enum cases.');
            }
            $row['key'] = 'o'.count($rows).'-'.self::key($row['value']);
            $rows[] = $row;
        }

        return $rows;
    }

    /** The label of the option holding `$value`, or null. */
    public static function label(iterable $options, mixed $value): ?string
    {
        foreach (self::normalize($options) as $row) {
            if (self::same($row['value'], $value)) {
                return $row['label'];
            }
        }

        return null;
    }

    /** Loose for numeric strings (a select bound to an int property), strict otherwise. */
    public static function same(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if ($a instanceof BackedEnum) {
            $a = $a->value;
        }
        if ($b instanceof BackedEnum) {
            $b = $b->value;
        }

        return is_scalar($a) && is_scalar($b) && !is_bool($a) && !is_bool($b) && (string) $a === (string) $b;
    }

    private static function scalar(mixed $value): string|int|float|bool
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Option values must be scalars or backed enum cases.');
        }

        return $value;
    }

    private static function key(string|int|float|bool $value): string
    {
        return substr(hash('xxh3', var_export($value, true)), 0, 8);
    }
}
