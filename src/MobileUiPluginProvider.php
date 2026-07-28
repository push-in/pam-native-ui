<?php

declare(strict_types=1);

namespace Pam\MobileUi;

use Closure;
use Pam\MobileUi\Component\UiComponent;
use Pam\MobileUi\Generated\ComponentMap;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Rendering\ComponentRenderer;
use Pam\MobileUi\Rendering\TailwindStyleCompiler;
use Pam\MobileUi\Theme\ThemeClassRegistry;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\MobileUi\Theme\Themes;
use Pam\MobileUi\Product\AppScreen;
use Pam\MobileUi\Product\AsyncButton;
use Pam\MobileUi\Product\ContentState;
use Pam\MobileUi\Product\FormField;
use Pam\Native\Element;
use Pam\Native\EventKind;
use Pam\Native\Plugin\PluginProvider;
use Pam\Native\PropKey;
use Pam\Native\TemplateRegistry;

final class MobileUiPluginProvider implements PluginProvider
{
    public function register(): void
    {
        TemplateRegistry::component(
            'AppScreen',
            static fn (array $props, array $children, ?object $_scope): AppScreen =>
                AppScreen::fromTemplate($props, $children),
        );
        TemplateRegistry::component(
            'ContentState',
            static fn (array $props, array $children, ?object $_scope): ContentState =>
                ContentState::fromTemplate($props, $children),
        );
        TemplateRegistry::component(
            'AsyncButton',
            static fn (array $props, array $children, ?object $_scope): AsyncButton =>
                AsyncButton::fromTemplate($props, $children),
        );
        TemplateRegistry::component(
            'FormField',
            static fn (array $props, array $children, ?object $_scope): FormField =>
                FormField::fromTemplate($props, $children),
        );

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
            TemplateRegistry::eventAdapter(
                $tag,
                static fn (
                    EventKind $kind,
                    Closure $handler,
                    array $props,
                ): Closure => ComponentRenderer::adaptTemplateEvent(
                    $tag,
                    $kind,
                    $handler,
                    $props,
                ),
            );
        }

        foreach (MaterialComponentMap::TAGS as $tag => $component) {
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
            TemplateRegistry::eventAdapter(
                $tag,
                static fn (
                    EventKind $kind,
                    Closure $handler,
                    array $props,
                ): Closure => ComponentRenderer::adaptTemplateEvent(
                    $component::componentName(),
                    $kind,
                    $handler,
                    $props,
                ),
            );
        }

        $theme = ThemeManager::current();
        ThemeClassRegistry::apply($theme);
        TemplateRegistry::styleResolver(
            static function (string $class): ?array {
                $theme = ThemeManager::current();
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
