<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

/** Keeps composed labels, styles and native field state on the same contract. */
final class MaterialFieldProps
{
    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public static function normalize(string $part, array $props): array
    {
        if (!in_array($part, [
            'PTextField', 'PTextarea', 'PNumberInput', 'POtpInput',
            'PColorInput', 'PDateInput', 'PPasswordField', 'PMaskedField',
            'PCurrencyField', 'PSelect', 'PAutocomplete', 'PCombobox',
            'PTagInput', 'PMultiSelect',
        ], true)) {
            return $props;
        }

        $message = $props['errorMessage'] ?? $props['errorMessages'] ?? $props['messages'] ?? '';
        $messages = is_array($message) ? $message : [$message];
        $lines = [];
        foreach ($messages as $line) {
            if (is_scalar($line) && trim((string) $line) !== '') {
                $lines[] = (string) $line;
            }
        }
        $props['errorMessage'] = implode("\n", $lines);
        $props['error'] = self::flag($props['error'] ?? $props['invalid'] ?? $props['isInvalid'] ?? false)
            || $props['errorMessage'] !== '';
        $props['invalid'] = $props['error'];
        $props['required'] = self::flag($props['required'] ?? $props['isRequired'] ?? false);

        return $props;
    }

    private static function flag(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            default => false,
        };
    }
}
