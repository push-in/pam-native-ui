<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ComponentVariant: int
{
    case Default = 1;
    case Destructive = 2;
    case Outline = 3;
    case Secondary = 4;
    case Ghost = 5;
    case Link = 6;
    case Solid = 7;
    case Subtle = 8;
    case Accent = 9;
    case Filled = 10;
    case Rounded = 11;
    case Sharp = 12;
    case Underlined = 13;
    case Circular = 14;
    case Grid = 15;
    case Inline = 16;
    case List = 17;
}
