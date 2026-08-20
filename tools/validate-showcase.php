<?php

declare(strict_types=1);

use App\Activity;
use App\AppTheme;
use App\Catalog;
use App\ComponentGallery;
use App\Orders;
use App\Overview;
use App\Profile;
use App\ShowcaseRoute;
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

$allowedApplicationTags = [
    'AppScreen' => true,
    'ContentState' => true,
    'StatusBanner' => true,
    'DrawerLayoutAndroid' => true,
    'Column' => true,
    'Row' => true,
    'Spacer' => true,
    'Text' => true,
];
$renderedMaterialTags = [];
foreach (glob($root.'/examples/kitchen-sink/resources/native/*.pam') ?: [] as $template) {
    $source = file_get_contents($template);
    if (!is_string($source)) {
        throw new RuntimeException("Cannot read showcase template {$template}.");
    }
    if (preg_match('/<\/?v-[a-z]/', $source) === 1) {
        throw new RuntimeException("Vuetify v-* tag found in {$template}.");
    }
    preg_match_all('/<(p-[a-z0-9-]+)(?=[\s\/>])/', $source, $materialMatches);
    foreach ($materialMatches[1] ?? [] as $tag) {
        $renderedMaterialTags[$tag] = true;
    }
    preg_match_all('/<\/?([A-Z][A-Za-z0-9]*)(?=[\s\/>])/', $source, $matches);
    foreach ($matches[1] ?? [] as $tag) {
        if (!isset($allowedApplicationTags[$tag])) {
            throw new RuntimeException(
                "Unsupported application or PAM Native tag {$tag} found in {$template}.",
            );
        }
    }
}

/** @var list<array{components: list<string>}> $materialModules */
$materialModules = require $root.'/resources/pam-material-components.php';
$expectedMaterialTags = [];
foreach ($materialModules as $module) {
    if (($module['mobile'] ?? true) !== true) {
        continue;
    }
    foreach ($module['components'] as $component) {
        $tag = strtolower(
            preg_replace('/(?<!^)[A-Z]/', '-$0', $component) ?? $component,
        );
        $expectedMaterialTags[$tag] = true;
    }
}
$missingMaterialTags = array_keys(
    array_diff_key($expectedMaterialTags, $renderedMaterialTags),
);
sort($missingMaterialTags);
if ($missingMaterialTags !== []) {
    throw new RuntimeException(
        'Showcase is missing p-* components: '.implode(', ', $missingMaterialTags),
    );
}

$screens = [
    Overview::class,
    Orders::class,
    Activity::class,
    Profile::class,
    Catalog::class,
    ComponentGallery::class,
];

foreach ($screens as $screen) {
    (new $screen())->toElement();
}

$showcaseRoutes = [
    'showcase-home',
    'showcase-actions',
    'showcase-forms',
    'showcase-data',
    'showcase-overlays',
];
foreach ($showcaseRoutes as $view) {
    (new ShowcaseRoute($view))->toElement();
}

fwrite(
    STDOUT,
    sprintf(
        "Validated %d p-* showcase screens and %d/%d material components through the production template renderer.\n",
        count($screens) + count($showcaseRoutes),
        count($expectedMaterialTags),
        count($expectedMaterialTags),
    ),
);
