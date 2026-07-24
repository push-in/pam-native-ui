<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum MessageRole: int
{
    case User = 1;
    case Assistant = 2;
    case System = 3;
}
