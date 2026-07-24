<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum Placement: int
{
    case Top = 1;
    case TopStart = 2;
    case TopEnd = 3;
    case Bottom = 4;
    case BottomStart = 5;
    case BottomEnd = 6;
    case Left = 7;
    case LeftTop = 8;
    case LeftBottom = 9;
    case Right = 10;
    case RightTop = 11;
    case RightBottom = 12;
    case Center = 13;
}
