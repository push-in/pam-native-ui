<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum BackdropPressBehavior: int
{
    case Close = 1;
    case Collapse = 2;
    case None = 3;
}
