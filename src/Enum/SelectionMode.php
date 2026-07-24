<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum SelectionMode: int
{
    case Single = 1;
    case Multiple = 2;
    case None = 3;
}
