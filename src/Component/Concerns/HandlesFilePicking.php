<?php

declare(strict_types=1);

namespace Pam\MobileUi\Component\Concerns;

use Closure;
use Pam\Native\FileReference;
use Pam\Native\MediaPickerType;
use Pam\Native\System\Files;

trait HandlesFilePicking
{
    /** @param Closure(FileReference|list<FileReference>|null): void $handler */
    final public function onPick(
        Closure $handler,
        MediaPickerType $type = MediaPickerType::Any,
        bool $multiple = false,
        int $limit = 10,
    ): static {
        return $this->onPress(
            static function () use ($handler, $type, $multiple, $limit): void {
                if ($multiple) {
                    Files::pickMany($type, $handler, max(1, min(50, $limit)));

                    return;
                }
                Files::pick($type, $handler);
            },
        );
    }
}
