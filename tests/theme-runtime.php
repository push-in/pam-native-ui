<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;

require dirname(__DIR__).'/vendor/autoload.php';

function expectTheme(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ThemeManager::reset();
ThemeManager::mode(ThemeMode::System);
ThemeManager::systemDark(false);
expectTheme(
    ThemeManager::resolvedMode() === ThemeMode::Light,
    'System light appearance must resolve to the light theme.',
);
expectTheme(
    ThemeManager::current()->color(ColorToken::Background)
        === Themes::light()->color(ColorToken::Background),
    'System light appearance must expose light tokens.',
);

ThemeManager::systemDark(true);
expectTheme(
    ThemeManager::resolvedMode() === ThemeMode::Dark,
    'System dark appearance must resolve to the dark theme.',
);
expectTheme(
    ThemeManager::current()->color(ColorToken::Background)
        === Themes::dark()->color(ColorToken::Background),
    'System dark appearance must expose dark tokens.',
);

$customDark = Themes::dark()->withColors([
    ColorToken::Primary->value => Color::rgb(76, 141, 255),
]);
ThemeManager::customize(dark: $customDark);
expectTheme(
    ThemeManager::current()->color(ColorToken::Primary)
        === Color::rgb(76, 141, 255)->argb,
    'Runtime appearance changes must preserve custom theme overrides.',
);

ThemeManager::mode(ThemeMode::Light);
ThemeManager::systemDark(true);
expectTheme(
    ThemeManager::resolvedMode() === ThemeMode::Light,
    'An explicit light mode must not be overridden by system appearance.',
);

ThemeManager::reset();
