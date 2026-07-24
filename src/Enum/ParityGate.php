<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ParityGate: int
{
    case Php = 1;
    case Tags = 2;
    case Android = 3;
    case Variants = 4;
    case States = 5;
    case Accessibility = 6;
    case Themes = 7;
    case Examples = 8;
    case Tests = 9;
    case Performance = 10;
}
