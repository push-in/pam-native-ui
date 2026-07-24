<?php

declare(strict_types=1);

namespace Pam\MobileUi;

use Pam\MobileUi\Component\UiComponent;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Rendering\TailwindStyleCompiler;
use Pam\MobileUi\Theme\ThemeClassRegistry;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;
use Pam\Native\Element;
use Pam\Native\Plugin\PluginProvider;
use Pam\Native\PropKey;
use Pam\Native\TemplateRegistry;

final class MobileUiPluginProvider implements PluginProvider
{
    public function register(): void
    {
        foreach (ComponentMap::TAGS as $tag => $component) {
            TemplateRegistry::component(
                $tag,
                static function (
                    array $props,
                    array $children,
                    ?object $_scope,
                ) use ($component): UiComponent {
                    return $component::fromTemplate($props, $children);
                },
            );
        }

        $theme = ThemeManager::current();
        ThemeClassRegistry::apply($theme);
        TemplateRegistry::styleResolver(
            static function (string $class) use ($theme): ?array {
                if (
                    preg_match(
                        '/^(?:(?:sm|md|lg|xl|2xl):)?'
                            .'(?:grid-cols|col-span)-\d+$/',
                        $class,
                    ) === 1
                ) {
                    return [];
                }
                if (
                    TailwindStyleCompiler::unsupportedUtilities(
                        [$class],
                        [],
                        $theme,
                    ) !== []
                ) {
                    return null;
                }

                return TailwindStyleCompiler::compile(
                    [$class],
                    [],
                    $theme,
                )->properties();
            },
        );
        TemplateRegistry::style('ui-surface', [
            PropKey::BackgroundColor->value => $theme->color(\Pam\MobileUi\Enum\ColorToken::Background),
            PropKey::TextColor->value => $theme->color(\Pam\MobileUi\Enum\ColorToken::Foreground),
        ]);
        TemplateRegistry::style('ui-muted', [
            PropKey::BackgroundColor->value => $theme->color(\Pam\MobileUi\Enum\ColorToken::Muted),
            PropKey::TextColor->value => $theme->color(\Pam\MobileUi\Enum\ColorToken::MutedForeground),
        ]);
        TemplateRegistry::style('ui-touch-target', [
            PropKey::MinWidth->value => 48.0,
            PropKey::MinHeight->value => 48.0,
        ]);
    }

    public function boot(): void
    {
        Themes::light();
        Themes::dark();
    }
}
