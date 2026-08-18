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

    public static function fromArgb(int $argb): self
    {
        if ($argb < 0 || $argb > 0xffffffff) {
            throw new InvalidArgumentException('ARGB colors must be unsigned 32-bit integers.');
        }

        return new self($argb);
    }

    public function contrastRatio(self $other): float
    {
        $lighter = max($this->relativeLuminance(), $other->relativeLuminance());
        $darker = min($this->relativeLuminance(), $other->relativeLuminance());

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public function relativeLuminance(): float
    {
        $channel = static function (int $value): float {
            $normalized = $value / 255;

            return $normalized <= 0.04045
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        };

        $red = $channel(($this->argb >> 16) & 0xff);
        $green = $channel(($this->argb >> 8) & 0xff);
        $blue = $channel($this->argb & 0xff);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
