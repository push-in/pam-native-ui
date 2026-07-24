<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ButtonVariant: int
{
    case Default = 1;
    case Destructive = 2;
    case Outline = 3;
    case Secondary = 4;
    case Ghost = 5;
    case Link = 6;
}
