<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ImplementationKind: int
{
    case Composition = 1;
    case NativeHost = 2;
    case CorePrimitive = 3;
    case Service = 4;
}
