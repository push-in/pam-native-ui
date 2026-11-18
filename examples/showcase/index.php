<?php

declare(strict_types=1);

use App\Screens\Catalog;
use App\Screens\Controls;
use App\Screens\Events;
use App\Screens\Feedback;
use App\Screens\Forms;
use App\Screens\Lists;
use App\Screens\Overlays;
use Pam\Native\App;
use Pam\Native\Routing\Route;

require __DIR__.'/vendor/autoload.php';

App::components(__DIR__.'/src', __DIR__.'/.pam-native/components');

App::run(Route::stack(
    name: 'showcase',
    initial: 'catalog',
    routes: static function (): void {
        Route::screen('catalog', Catalog::class);
        Route::screen('forms', Forms::class);
        Route::screen('controls', Controls::class);
        Route::screen('events', Events::class);
        Route::screen('lists', Lists::class);
        Route::screen('feedback', Feedback::class);
        Route::screen('overlays', Overlays::class);
    },
));
