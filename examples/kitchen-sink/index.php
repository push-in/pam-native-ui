<?php

declare(strict_types=1);

use App\AppTheme;
use App\Catalog;
use App\TypedCommunityCard;
use Pam\MobileUi\MobileUi;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\Native\App;

require __DIR__.'/vendor/autoload.php';

App::views(
    __DIR__.'/resources/native',
    __DIR__.'/.pam-native/views',
);
AppTheme::install();
MobileUi::mode(ThemeMode::System);
TypedCommunityCard::register();
App::run(new Catalog());
