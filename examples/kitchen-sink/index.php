<?php

declare(strict_types=1);

use App\Catalog;
use Pam\MobileUi\MobileUi;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\Native\App;

require __DIR__.'/vendor/autoload.php';

App::views(
    __DIR__.'/resources/native',
    __DIR__.'/.pam-native/views',
);
MobileUi::mode(ThemeMode::System);
App::run(new Catalog());
