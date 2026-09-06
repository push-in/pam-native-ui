<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum CalendarDayState: int
{
    case Default = 1;
    case Selected = 2;
    case Disabled = 3;
    case OutsideMonth = 4;
    case Today = 5;
    case RangeStart = 6;
    case RangeMiddle = 7;
    case RangeEnd = 8;
}
