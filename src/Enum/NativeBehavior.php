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
    case Glass = 9;
    case Checkbox = 10;
    case Radio = 11;
    case Toast = 12;
    case ImageViewer = 13;
    case Chat = 14;
    case Progress = 15;
    case Drawer = 16;
    case Modal = 17;
    case AlertDialog = 18;
    case Popover = 19;
    case Menu = 20;
    case Tooltip = 21;
    case DateTimePicker = 22;
    case Portal = 23;
    case AccordionGroup = 24;
    case CheckboxGroup = 25;
    case RadioGroup = 26;
    case SwitchControl = 27;
    case TabsTrigger = 28;
    case SheetItem = 29;
    case MenuItem = 30;
    case OverlayDismiss = 31;
    case InputGroup = 32;
    case InputSlot = 33;
    case FormControl = 34;
    case Table = 35;
    case TableRow = 36;
    case ImageViewerControl = 37;
    case MessageBranch = 38;
    case MessageBranchControl = 39;
    case PromptInput = 40;
    case PromptInputSubmit = 41;
    case ConversationScrollButton = 42;
    case FileTree = 43;
    case FileTreeFolder = 44;
    case FileTreeFile = 45;
    case Transition = 46;
    case Parallax = 47;
    case Sparkline = 48;
    case Hotkey = 49;
    case Hover = 50;
}
