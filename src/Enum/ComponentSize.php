<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ComponentSize: int
{
    case ExtraSmall = 1;
    case Small = 2;
    case Medium = 3;
    case Large = 4;
    case ExtraLarge = 5;
    case TwoExtraLarge = 6;
    case ThreeExtraLarge = 7;
    case FourExtraLarge = 8;
    case Icon = 9;
    case TwoExtraSmall = 10;
    case FiveExtraLarge = 11;
    case SixExtraLarge = 12;
    case Full = 13;
    case None = 14;
}
