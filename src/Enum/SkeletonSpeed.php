<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum SkeletonSpeed: int
{
    case Slow = 1;
    case Normal = 2;
    case Fast = 3;
    case VeryFast = 4;
}
