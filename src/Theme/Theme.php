<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use InvalidArgumentException;
use Pam\MobileUi\Enum\ColorToken;

final readonly class Theme
{
    /** @var array<int, int> */
    private array $colors;

    /**
     * @param array<int, int> $colors
     */
    public function __construct(array $colors)
    {
        foreach (ColorToken::cases() as $token) {
            if (!isset($colors[$token->value])) {
                throw new InvalidArgumentException("Theme color {$token->name} is missing.");
            }
        }

        $this->colors = $colors;
    }

    public function color(ColorToken $token): int
    {
        return $this->colors[$token->value];
    }

    /**
     * @param array<int, Color|int> $overrides
     */
    public function withColors(array $overrides): self
    {
        $colors = $this->colors;

        foreach ($overrides as $id => $color) {
            ColorToken::from($id);
            $colors[$id] = $color instanceof Color ? $color->argb : $color;
        }

        return new self($colors);
    }
}
