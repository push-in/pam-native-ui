<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ComponentMode: int
{
    case Single = 1;
    case Multiple = 2;
    case Range = 3;
    case Date = 4;
    case Time = 5;
    case DateTime = 6;
}
