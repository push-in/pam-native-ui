<?php

declare(strict_types=1);

use App\AppTheme;
use App\Activity;
use App\Catalog;
use App\ComponentGallery;
use App\ComponentRoute;
use App\Orders;
use App\Overview;
use App\Profile;
use App\ShowcaseRoute;
use App\ShowcaseApp;
use App\TypedCommunityCard;
use Pam\MobileUi\Material\PIcon;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\App;
use Pam\Native\Navigation\Router;
use Pam\Native\Navigation\DrawerType;

require __DIR__.'/vendor/autoload.php';

App::views(
    __DIR__.'/resources/native',
    __DIR__.'/.pam-native/views',
);
AppTheme::install();
PamUI::mode(ThemeMode::Light);
TypedCommunityCard::register();
$catalog = new Catalog();
$gallery = new ComponentGallery();
$showcaseRoutes = [
    'components' => new ShowcaseRoute('showcase-home'),
    'showcase-actions' => new ShowcaseRoute('showcase-actions'),
    'showcase-forms' => new ShowcaseRoute('showcase-forms'),
    'showcase-data' => new ShowcaseRoute('showcase-data'),
    'showcase-overlays' => new ShowcaseRoute('showcase-overlays'),
];
$drawer = Router::drawer('overview')
    ->route('overview', 'Overview', fn () => $showcaseRoutes['components'])
    ->route('actions', 'Actions', fn () => $showcaseRoutes['showcase-actions'])
    ->route('forms', 'Forms', fn () => $showcaseRoutes['showcase-forms'])
    ->route('data', 'Data', fn () => $showcaseRoutes['showcase-data'])
    ->route('overlays', 'Overlays', fn () => $showcaseRoutes['showcase-overlays'])
    ->route('all', 'All components', fn () => $gallery);
$componentRoutes = [];
$componentDeepLinks = [];
$componentAuditRoutes = [];
$catalogHidden = [];
$catalogGroup = static fn (string $tag): string => match (true) {
    preg_match('/(btn|fab|chip|icon|avatar|badge|rating)/', $tag) === 1 =>
        'Actions and identity',
    preg_match('/(checkbox|radio|switch|slider|item-group)/', $tag) === 1 =>
        'Selection controls',
    preg_match('/(field|input|select|autocomplete|combobox|form|otp|textarea)/', $tag) === 1 =>
        'Forms and inputs',
    preg_match('/(date-picker|time-picker|calendar)/', $tag) === 1 =>
        'Pickers and editing',
    preg_match('/(data|table|list|tree|virtual|infinite|timeline|calendar)/', $tag) === 1 =>
        'Data display',
    preg_match('/(img|lazy|skeleton|sparkline)/', $tag) === 1 =>
        'Media and motion',
    preg_match('/(dialog|menu|overlay|sheet|snackbar|tooltip|alert|banner|progress)/', $tag) === 1 =>
        'Feedback and overlays',
    preg_match('/(app-bar|toolbar|navigation|tabs|tab|slide-group|speed-dial|breadcrumbs|pagination|stepper|window|carousel)/', $tag) === 1 =>
        'Navigation',
    default => 'Layout and content',
};
$catalogTitle = static function (string $tag): string {
    $terms = [
        'btn' => 'Button',
        'fab' => 'FAB',
        'img' => 'Image',
        'otp' => 'OTP',
        'ui' => 'UI',
    ];
    $words = explode('-', substr($tag, 2));

    return implode(' ', array_map(
        static fn (string $word): string => $terms[$word] ?? ucfirst($word),
        $words,
    ));
};
foreach (MaterialComponentMap::TAGS as $tag => $component) {
    if (in_array($tag, $catalogHidden, true)) {
        continue;
    }
    $title = $catalogTitle($tag);
    $route = new ComponentRoute($tag, $title, $component);
    $componentRoutes[] = $route;
    $componentDeepLinks[$tag] = 'catalog-'.$tag;
    $componentAuditRoutes[$tag] = $route;
    $drawer = $drawer->route(
        'catalog-'.$tag,
        $title,
        fn () => $route,
        group: $catalogGroup($tag),
    );
}
$showcase = $drawer
    ->presentation(DrawerType::Front)
    ->responsive(840.0)
    ->appearance(
        0xFFFFFFFF,
        0xFF1867C0,
        0x99000000,
        0xFFE3F2FD,
        0x33000000,
        0x1F000000,
        256.0,
    )
    ->persistence('pam-component-drawer-v2')
    ->build();
$studio = Router::stack('studio')
    ->route('studio', fn () => $catalog)
    ->route('components', fn () => $showcase)
    ->persistence('pam-studio-stack')
    ->build();
$catalog->navigator = $studio;
$gallery->drawer = $showcase;
foreach ($showcaseRoutes as $showcaseRoute) {
    $showcaseRoute->drawer = $showcase;
}
foreach ($componentRoutes as $componentRoute) {
    $componentRoute->drawer = $showcase;
}
// The catalog wraps its drawer in ShowcaseApp so it can handle deep links.
// Forward window changes explicitly; otherwise the Android view adapts to a
// permanent drawer while the PHP navigation state still believes it is a
// compact front drawer, leaving redundant toggle controls on expanded screens.
App::onDimensions($showcase->dimensions(...));

$tabs = Router::tabs('overview')
    ->tab('overview', 'Overview', new Overview(), PIcon::make(['icon' => 'star']))
    ->tab('orders', 'Orders', new Orders(), PIcon::make(['icon' => 'bell']))
    ->tab('activity', 'Activity', new Activity(), PIcon::make(['icon' => 'clock']))
    ->tab('profile', 'Profile', new Profile(), PIcon::make(['icon' => 'message-circle']))
    ->tab('lab', 'Studio', $studio, PIcon::make(['icon' => 'settings']))
    ->appearance(0xFF091526, 0xFF4C8DFF, 0xFF7F93B0, 0xFF253952)
    ->persistence('premium-showcase')
    ->build();
App::run(new ShowcaseApp($showcase, $componentDeepLinks, $componentAuditRoutes));
