<?php

declare(strict_types=1);

use App\AppTheme;
use App\Activity;
use App\Catalog;
use App\Orders;
use App\Overview;
use App\Profile;
use App\TypedCommunityCard;
use Pam\MobileUi\Component\BellIcon;
use Pam\MobileUi\Component\ClockIcon;
use Pam\MobileUi\Component\MessageCircleIcon;
use Pam\MobileUi\Component\SettingsIcon;
use Pam\MobileUi\Component\StarIcon;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\Native\App;
use Pam\Native\Navigation\Router;

require __DIR__.'/vendor/autoload.php';

App::views(
    __DIR__.'/resources/native',
    __DIR__.'/.pam-native/views',
);
AppTheme::install();
PamUI::mode(ThemeMode::System);
TypedCommunityCard::register();
$tabs = Router::tabs('overview')
    ->tab('overview', 'Overview', new Overview(), StarIcon::make())
    ->tab('orders', 'Orders', new Orders(), BellIcon::make())
    ->tab('activity', 'Activity', new Activity(), ClockIcon::make())
    ->tab('profile', 'Profile', new Profile(), MessageCircleIcon::make())
    ->tab('lab', 'Studio', new Catalog(), SettingsIcon::make())
    ->appearance(0xFF0F172A, 0xFF60A5FA, 0xFF94A3B8, 0xFF1E293B)
    ->persistence('premium-showcase')
    ->build();
App::run($tabs);
