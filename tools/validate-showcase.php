<?php

declare(strict_types=1);

use App\Activity;
use App\AppTheme;
use App\Catalog;
use App\Orders;
use App\Overview;
use App\Profile;
use App\TypedCommunityCard;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\MobileUiPluginProvider;
use Pam\MobileUi\PamUI;
use Pam\Native\App;

$root = dirname(__DIR__);
require $root.'/tests/bootstrap.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $root.'/examples/kitchen-sink/src/'
        .str_replace('\\', '/', substr($class, strlen($prefix)))
        .'.php';
    if (is_file($path)) {
        require_once $path;
    }
});

(new MobileUiPluginProvider())->register();
App::views(
    $root.'/examples/kitchen-sink/resources/native',
    $root.'/examples/kitchen-sink/.pam-native/views',
);
AppTheme::install();
PamUI::mode(ThemeMode::Dark);
TypedCommunityCard::register();

$allowedApplicationTags = ['AppScreen' => true, 'ContentState' => true];
foreach (glob($root.'/examples/kitchen-sink/resources/native/*.pam') ?: [] as $template) {
    $source = file_get_contents($template);
    if (!is_string($source)) {
        throw new RuntimeException("Cannot read showcase template {$template}.");
    }
    if (preg_match('/<\/?v-[a-z]/', $source) === 1) {
        throw new RuntimeException("Vuetify v-* tag found in {$template}.");
    }
    preg_match_all('/<\/?([A-Z][A-Za-z0-9]*)(?=[\s\/>])/', $source, $matches);
    foreach ($matches[1] ?? [] as $tag) {
        if (!isset($allowedApplicationTags[$tag])) {
            throw new RuntimeException(
                "Legacy UI tag {$tag} found in {$template}; use p-*.",
            );
        }
    }
}

$screens = [
    Overview::class,
    Orders::class,
    Activity::class,
    Profile::class,
    Catalog::class,
];

foreach ($screens as $screen) {
    (new $screen())->toElement();
}

fwrite(
    STDOUT,
    sprintf(
        "Validated %d p-* showcase screens through the production template renderer.\n",
        count($screens),
    ),
);
