<?php

declare(strict_types=1);

namespace Pam\MobileUi\Rendering;

use Pam\MobileUi\Generated\StyleRecipes;

final class StyleRecipeResolver
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $props
     * @return list<string>|null
     */
    public static function classes(string $part, array $props): ?array
    {
        $recipeId = StyleRecipes::COMPONENTS[$part] ?? null;
        $recipe = $recipeId === null ? null : StyleRecipes::RECIPES[$recipeId];

        if (!is_array($recipe)) {
            return null;
        }

        $classes = [];
        self::append($classes, $recipe['base']);
        $defaults = $recipe['defaultVariants'];
        $variants = $recipe['variants'];

        foreach ($variants as $axis => $options) {
            if (!is_string($axis) || !is_array($options)) {
                continue;
            }
            $selected = $props[$axis]
                ?? $defaults[$axis]
                ?? (array_key_exists('default', $options) ? 'default' : null);
            self::appendSelected($classes, $axis, $selected, $options);
        }

        $parent = self::parentVariants($props);
        $parentVariants = $recipe['parentVariants'];

        foreach ($parentVariants as $axis => $options) {
            if (!is_string($axis) || !is_array($options)) {
                continue;
            }
            $selected = $parent[$axis]
                ?? $props[$axis]
                ?? $defaults[$axis]
                ?? (array_key_exists('default', $options) ? 'default' : null);
            self::appendSelected($classes, $axis, $selected, $options);
        }

        self::appendCompounds(
            $classes,
            $recipe['compoundVariants'],
            $props,
            $defaults,
        );
        self::appendCompounds(
            $classes,
            $recipe['parentCompoundVariants'],
            [...$props, ...$parent],
            $defaults,
        );

        if (is_string($props['className'] ?? null)) {
            self::append($classes, $props['className']);
        }

        return $classes;
    }

    /**
     * @param list<string> $classes
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $selected
     * @param array<string, mixed> $defaults
     */
    private static function appendCompounds(
        array &$classes,
        array $rules,
        array $selected,
        array $defaults,
    ): void {
        foreach ($rules as $rule) {
            $matches = true;

            foreach ($rule as $axis => $option) {
                if ($axis === 'class') {
                    continue;
                }
                $value = $selected[$axis] ?? $defaults[$axis] ?? null;
                if (
                    !is_string($option)
                    && !is_int($option)
                    && !is_bool($option)
                ) {
                    $matches = false;
                    break;
                }
                if (!self::matches($axis, $value, $option)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                self::append($classes, $rule['class'] ?? null);
            }
        }
    }

    /**
     * @param list<string> $classes
     * @param array<array-key, mixed> $options
     */
    private static function appendSelected(
        array &$classes,
        string $axis,
        mixed $selected,
        array $options,
    ): void {
        foreach ($options as $option => $classNames) {
            if (self::matches($axis, $selected, $option)) {
                self::append($classes, $classNames);
                return;
            }
        }
    }

    private static function matches(
        string $axis,
        mixed $selected,
        string|int|bool $option,
    ): bool {
        if (is_bool($selected)) {
            if (is_bool($option)) {
                return $selected === $option;
            }

            return ($selected ? 'true' : 'false') === (string) $option;
        }
        if (is_int($selected)) {
            if (is_numeric($option) && $selected === (int) $option) {
                return true;
            }

            return ValueNormalizer::value($axis, (string) $option) === $selected;
        }

        return is_scalar($selected) && (string) $selected === (string) $option;
    }

    /**
     * @param list<string> $classes
     */
    private static function append(array &$classes, mixed $classNames): void
    {
        if (is_string($classNames) && trim($classNames) !== '') {
            $classes[] = trim($classNames);
        }
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    private static function parentVariants(array $props): array
    {
        $parent = $props['__parentVariants'] ?? null;

        return is_array($parent) ? $parent : [];
    }
}
