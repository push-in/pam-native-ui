<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum VerificationStatus: int
{
    case Planned = 1;
    case Implemented = 2;
    case Verified = 3;
    case NotApplicable = 4;
}
