<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Theme\Color;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\App;
use Pam\Native\EventKind;
use Pam\Native\Internal\Runtime;
use Pam\Native\Internal\Wire;
use Pam\Native\UI\Text;
use Pam\Native\UserInterfaceAppearance;

$vendorAutoload = dirname(__DIR__).'/vendor/autoload.php';
require is_file($vendorAutoload) ? $vendorAutoload : __DIR__.'/bootstrap.php';

function expectTheme(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

ThemeManager::reset();

App::run(Text::make('System appearance probe'));
Runtime::dispatchEvent(0, EventKind::Dimensions->value, Wire::map([
    'width' => 412.0,
    'height' => 915.0,
    'density' => 3.0,
    'appearance' => UserInterfaceAppearance::Dark->value,
]));
expectTheme(
    ThemeManager::resolvedMode() === ThemeMode::Dark,
    'The UI theme must follow the native system appearance without an environment bridge.',
);
Runtime::shutdown();
ThemeManager::reset();

$accessiblePairs = [
    [ColorToken::Foreground, ColorToken::Background],
    [ColorToken::OnSurface, ColorToken::Surface],
    [ColorToken::PopoverForeground, ColorToken::Popover],
    [ColorToken::PrimaryForeground, ColorToken::Primary],
    [ColorToken::SecondaryForeground, ColorToken::Secondary],
    [ColorToken::AccentForeground, ColorToken::Accent],
    [ColorToken::DestructiveForeground, ColorToken::Destructive],
    [ColorToken::SuccessForeground, ColorToken::Success],
    [ColorToken::WarningForeground, ColorToken::Warning],
    [ColorToken::InfoForeground, ColorToken::Info],
];

foreach ([Themes::pamLight(), Themes::pamDark()] as $theme) {
    foreach ($accessiblePairs as [$foreground, $background]) {
        expectTheme(
            $theme->contrastRatio($foreground, $background) >= 4.5,
            "PAM theme pair {$foreground->name}/{$background->name} must meet WCAG AA.",
        );
    }

    expectTheme(
        $theme->contrastRatio(ColorToken::MutedForeground, ColorToken::Background) >= 4.5,
        'Muted PAM theme text must remain readable against the application background.',
    );
}

expectTheme(
    abs(Color::rgb(0, 0, 0)->contrastRatio(Color::rgb(255, 255, 255)) - 21.0) < 0.001,
    'WCAG contrast calculation must preserve the canonical black/white ratio.',
);
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
