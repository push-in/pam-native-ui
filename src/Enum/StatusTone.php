<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum StatusTone: int
{
    case Information = 1;
    case Success = 2;
    case Warning = 3;
    case Error = 4;
    case Progress = 5;
}
