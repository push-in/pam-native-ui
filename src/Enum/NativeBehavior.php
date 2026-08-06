<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

enum NativeBehavior: int
{
    case Container = 1;
    case Accordion = 2;
    case BottomSheet = 3;
    case Overlay = 4;
    case Slider = 5;
    case Tabs = 6;
    case Calendar = 7;
    case Skeleton = 8;
    case Checkbox = 9;
    case Radio = 10;
    case Toast = 11;
    case Progress = 12;
    case Modal = 13;
    case Popover = 14;
    case Menu = 15;
    case Tooltip = 16;
    case DateTimePicker = 17;
    case Portal = 18;
    case AccordionGroup = 19;
    case CheckboxGroup = 20;
    case RadioGroup = 21;
    case SwitchControl = 22;
    case TabsTrigger = 23;
    case SheetItem = 24;
    case MenuItem = 25;
    case OverlayDismiss = 26;
    case InputGroup = 27;
    case InputSlot = 28;
    case FormControl = 29;
    case Table = 30;
    case TableRow = 31;
    case FileTree = 32;
    case FileTreeFolder = 33;
    case FileTreeFile = 34;
    case Sparkline = 35;
    case ChipGroup = 36;
    case ListItem = 37;
    case Timeline = 38;
    case TimelineItem = 39;
}
