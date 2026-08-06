<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum MaterialMotion: int
{
    case Fade = 1;
    case Scale = 2;
    case ExpandX = 3;
    case ExpandY = 4;
    case SlideX = 5;
    case SlideY = 6;
    case ScrollX = 7;
    case ScrollY = 8;
    case DialogTop = 9;
    case DialogBottom = 10;
    case Fab = 11;
    case CarouselForward = 12;
    case CarouselReverse = 13;
    case TabIndicator = 14;
    case Snackbar = 15;
    case MenuOrigin = 16;
    case AppBar = 17;
    case Window = 18;
}
