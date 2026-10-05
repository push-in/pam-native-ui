<?php

declare(strict_types=1);

use Certification\FixtureScreen;
use Pam\Native\App;

require __DIR__.'/vendor/autoload.php';

App::views(__DIR__.'/resources/native', __DIR__.'/.pam-native/views');
App::run(new FixtureScreen());
