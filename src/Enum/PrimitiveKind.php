<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum PrimitiveKind: int
{
    case View = 1;
    case Column = 2;
    case Row = 3;
    case Text = 4;
    case Pressable = 5;
    case Input = 6;
    case Image = 7;
    case ImageBackground = 8;
    case Scroll = 9;
    case FlatList = 10;
    case SectionList = 11;
    case Spinner = 12;
    case Switch = 13;
    case RefreshControl = 14;
    case SafeAreaView = 15;
    case KeyboardAvoidingView = 16;
    case StatusBar = 17;
    case InputAccessoryView = 18;
    case NativeHost = 19;
    case Modal = 20;
    case Spacer = 21;
}
