<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum MaterialVariant: int
{
    case Elevated = 1;
    case Flat = 2;
    case Tonal = 3;
    case Outlined = 4;
    case Text = 5;
    case Plain = 6;
    case Underlined = 7;
    case Filled = 8;
    case Solo = 9;
    case SoloInverted = 10;
    case SoloFilled = 11;
}
