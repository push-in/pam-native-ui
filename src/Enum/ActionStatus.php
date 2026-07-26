<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ActionStatus: int
{
    case Idle = 1;
    case Loading = 2;
    case Success = 3;
    case Error = 4;
}
