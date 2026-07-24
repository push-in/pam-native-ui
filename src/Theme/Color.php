<?php

declare(strict_types=1);

namespace Pam\MobileUi\Theme;

use InvalidArgumentException;

final readonly class Color
{
    private function __construct(public int $argb)
    {
    }

    public static function rgb(int $red, int $green, int $blue, int $alpha = 255): self
    {
        foreach ([$red, $green, $blue, $alpha] as $channel) {
            if ($channel < 0 || $channel > 255) {
                throw new InvalidArgumentException('Color channels must be between 0 and 255.');
            }
        }

        return new self(
            (($alpha & 0xff) << 24)
            | (($red & 0xff) << 16)
            | (($green & 0xff) << 8)
            | ($blue & 0xff),
        );
    }
}
