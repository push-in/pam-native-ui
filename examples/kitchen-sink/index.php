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
use App\TypedCommunityCard;
use Pam\MobileUi\Component\BellIcon;
use Pam\MobileUi\Component\ClockIcon;
use Pam\MobileUi\Component\MessageCircleIcon;
use Pam\MobileUi\Component\SettingsIcon;
use Pam\MobileUi\Component\StarIcon;
use Pam\MobileUi\PamUI;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\Native\App;
use Pam\Native\Navigation\Router;
use Pam\Native\Navigation\DrawerType;
use Pam\Native\UserInterfaceAppearance;
use Pam\Native\WindowMetrics;

require __DIR__.'/vendor/autoload.php';

App::views(
    __DIR__.'/resources/native',
    __DIR__.'/.pam-native/views',
);
AppTheme::install();
PamUI::mode(ThemeMode::System);
App::onDimensions(static function (WindowMetrics $metrics): void {
    PamUI::systemDark(
        $metrics->appearance === UserInterfaceAppearance::Dark,
    );
});
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
$catalogPrimitives = [
    'p-app',
    'p-col',
    'p-container',
    'p-footer',
    'p-layout',
    'p-layout-item',
    'p-main',
    'p-responsive',
    'p-row',
    'p-spacer',
];
$catalogProviders = [
    'p-defaults-provider',
    'p-locale-provider',
    'p-theme-provider',
    'p-validation',
];
$catalogInternalParts = [
    'p-app-bar-nav-icon',
    'p-app-bar-title',
    'p-alert-title',
    'p-banner-actions',
    'p-banner-text',
    'p-breadcrumbs-divider',
    'p-breadcrumbs-item',
    'p-calendar-day',
    'p-calendar-header',
    'p-calendar-interval',
    'p-card-actions',
    'p-card-item',
    'p-card-subtitle',
    'p-card-text',
    'p-card-title',
    'p-carousel-item',
    'p-checkbox-btn',
    'p-color-picker-canvas',
    'p-color-picker-edit',
    'p-color-picker-preview',
    'p-color-picker-swatches',
    'p-counter',
    'p-date-picker-controls',
    'p-date-picker-header',
    'p-date-picker-month',
    'p-date-picker-months',
    'p-date-picker-years',
    'p-expansion-panel-text',
    'p-expansion-panel-title',
    'p-field-label',
    'p-file-upload-item',
    'p-item',
    'p-label',
    'p-list-group',
    'p-list-item-subtitle',
    'p-list-item-title',
    'p-list-subheader',
    'p-messages',
    'p-picker',
    'p-picker-title',
    'p-selection-control',
    'p-selection-control-group',
    'p-slide-group-item',
    'p-stepper-actions',
    'p-stepper-header',
    'p-stepper-item',
    'p-stepper-vertical-actions',
    'p-stepper-vertical-item',
    'p-stepper-window',
    'p-stepper-window-item',
    'p-time-picker-clock',
    'p-time-picker-controls',
    'p-timeline-item',
    'p-toolbar-items',
    'p-toolbar-title',
    'p-treeview-item',
    'p-window-item',
];
$catalogNativeInfrastructure = [
    // PAM Native already owns the system insets and status bar lifecycle.
    'p-system-bar',
];
$catalogHidden = array_merge(
    $catalogPrimitives,
    $catalogProviders,
    $catalogInternalParts,
    $catalogNativeInfrastructure,
);
$catalogGroup = static fn (string $tag): string => match (true) {
    preg_match('/(btn|fab|chip|icon|avatar|badge|rating)/', $tag) === 1 =>
        'Actions and identity',
    preg_match('/(field|input|select|autocomplete|combobox|checkbox|radio|switch|slider|form|otp|file)/', $tag) === 1 =>
        'Forms and selection',
    preg_match('/(app-bar|toolbar|navigation|tabs|tab|breadcrumbs|pagination|stepper|window|carousel)/', $tag) === 1 =>
        'Navigation',
    preg_match('/(data|table|list|tree|virtual|infinite|timeline|calendar)/', $tag) === 1 =>
        'Data display',
    preg_match('/(dialog|menu|overlay|sheet|snackbar|tooltip|alert|banner)/', $tag) === 1 =>
        'Feedback and overlays',
    default => 'Layout and content',
};
foreach (MaterialComponentMap::TAGS as $tag => $component) {
    if (in_array($tag, $catalogHidden, true)) {
        continue;
    }
    $title = ucwords(str_replace('-', ' ', substr($tag, 2)));
    $route = new ComponentRoute($tag, $title, $component);
    $componentRoutes[] = $route;
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
        0xFF212121,
        0xFF2196F3,
        0xB3FFFFFF,
        0xFF2F3E49,
        0x33000000,
        0x1FFFFFFF,
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

$tabs = Router::tabs('overview')
    ->tab('overview', 'Overview', new Overview(), StarIcon::make())
    ->tab('orders', 'Orders', new Orders(), BellIcon::make())
    ->tab('activity', 'Activity', new Activity(), ClockIcon::make())
    ->tab('profile', 'Profile', new Profile(), MessageCircleIcon::make())
    ->tab('lab', 'Studio', $studio, SettingsIcon::make())
    ->appearance(0xFF091526, 0xFF4C8DFF, 0xFF7F93B0, 0xFF253952)
    ->persistence('premium-showcase')
    ->build();
App::run($showcase);
