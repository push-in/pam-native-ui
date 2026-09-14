<?php

declare(strict_types=1);

namespace Pam\MobileUi\Enum;

/** Existing native FileTree event discriminator; shared by Android and UIKit. */
enum FileTreeAction: int
{
    case Expanded = 1;
}
