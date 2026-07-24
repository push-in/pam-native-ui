<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ComponentCategory: int
{
    case Typography = 1;
    case Layout = 2;
    case Feedback = 3;
    case DataDisplay = 4;
    case Forms = 5;
    case Overlay = 6;
    case Disclosure = 7;
    case Media = 8;
    case System = 9;
    case Ai = 10;
}
