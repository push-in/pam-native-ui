<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum InputSlotAction: int
{
    case Focus = 1;
    case Clear = 2;
    case TogglePassword = 3;
    case None = 4;
}
