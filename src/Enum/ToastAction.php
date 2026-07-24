<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ToastAction: int
{
    case Muted = 1;
    case Success = 2;
    case Warning = 3;
    case Error = 4;
    case Info = 5;
    case Attention = 6;
}
