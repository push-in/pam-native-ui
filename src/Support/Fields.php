<?php

declare(strict_types=1);

namespace Pam\NativeUi\Support;

/**
 * Pure helpers shared by the field components (no state, no I/O).
 *
 * @internal
 */
final class Fields
{
    private function __construct()
    {
    }

    public static function keyboard(string $type): string
    {
        return match ($type) {
            'email' => 'email',
            'number', 'integer' => 'number',
            'decimal' => 'decimal',
            'tel', 'phone' => 'phone',
            'url' => 'url',
            'search' => 'web-search',
            default => 'default',
        };
    }

    public static function capitalize(string $type): string
    {
        return match ($type) {
            'email', 'password', 'url', 'username', 'search' => 'none',
            'name' => 'words',
            default => 'sentences',
        };
    }

    public static function autoComplete(string $type): string
    {
        return match ($type) {
            'email' => 'email',
            'password' => 'password',
            'tel', 'phone' => 'tel',
            'name' => 'name',
            'username' => 'username',
            'url' => 'url',
            default => 'off',
        };
    }

    public static function autoCorrect(string $type): bool
    {
        return !in_array($type, ['email', 'password', 'url', 'username', 'number', 'integer', 'decimal', 'tel', 'phone'], true);
    }

    public static function boxClass(bool $focused, ?string $error, bool $disabled): string
    {
        return match (true) {
            $disabled => 'p-field-box-disabled',
            $error !== null && $error !== '' => $focused ? 'p-field-box-focused p-field-box-invalid' : 'p-field-box-invalid',
            $focused => 'p-field-box-focused',
            default => '',
        };
    }

    public static function counter(string $value, ?int $max): string
    {
        $length = mb_strlen($value);

        return $max === null ? (string) $length : $length.'/'.$max;
    }

    public static function accessibilityLabel(string $label, string $placeholder, bool $required): string
    {
        $text = $label !== '' ? $label : $placeholder;

        return $required && $text !== '' ? $text.', required' : $text;
    }

    public static function accessibilityHint(?string $error, ?string $helper): string
    {
        if ($error !== null && $error !== '') {
            return $error;
        }

        return $helper ?? '';
    }
}
