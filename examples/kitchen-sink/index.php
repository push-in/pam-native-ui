<?php

declare(strict_types=1);

use App\AppTheme;
use App\Activity;
use App\Catalog;
use App\ComponentGallery;
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
PamUI::mode(ThemeMode::Dark);
TypedCommunityCard::register();
$catalog = new Catalog();
$gallery = new ComponentGallery();
$studio = Router::stack('studio')
    ->route('studio', fn () => $catalog)
    ->route('components', fn () => $gallery)
    ->persistence('pam-studio-stack')
    ->build();
$catalog->navigator = $studio;
$gallery->navigator = $studio;

$tabs = Router::tabs('overview')
    ->tab('overview', 'Overview', new Overview(), StarIcon::make())
    ->tab('orders', 'Orders', new Orders(), BellIcon::make())
    ->tab('activity', 'Activity', new Activity(), ClockIcon::make())
    ->tab('profile', 'Profile', new Profile(), MessageCircleIcon::make())
    ->tab('lab', 'Studio', $studio, SettingsIcon::make())
    ->appearance(0xFF091526, 0xFF4C8DFF, 0xFF7F93B0, 0xFF253952)
    ->persistence('premium-showcase')
    ->build();
App::run($tabs);
