<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum MetricTrend: int
{
    case Neutral = 1;
    case Positive = 2;
    case Negative = 3;
    case Warning = 4;
}
