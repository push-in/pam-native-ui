<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum ColorToken: int
{
    case Primary = 1;
    case PrimaryForeground = 2;
    case Card = 3;
    case Secondary = 4;
    case SecondaryForeground = 5;
    case Background = 6;
    case Popover = 7;
    case PopoverForeground = 8;
    case Muted = 9;
    case MutedForeground = 10;
    case Destructive = 11;
    case Foreground = 12;
    case Border = 13;
    case Input = 14;
    case Ring = 15;
    case Accent = 16;
    case AccentForeground = 17;
    case DestructiveForeground = 18;
    case Surface = 19;
    case SurfaceElevated = 20;
    case SurfaceSunken = 21;
    case Success = 22;
    case SuccessForeground = 23;
    case Warning = 24;
    case WarningForeground = 25;
    case Info = 26;
    case InfoForeground = 27;
    case Focus = 28;
    case Overlay = 29;
    case OnSurface = 30;
}
