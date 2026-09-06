<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Navigation\DrawerNavigator;
use Pam\Native\Renderable;
use Pam\Native\StatusBarAppearance;
use Pam\Native\Style;
use Pam\Native\System\Linking;
use Pam\Native\UI\Screen;
use Pam\Native\UI\StatusBar;

/**
 * Root for the public component catalog.
 *
 * Explicit pam-showcase:// links make every catalog state reproducible from
 * Android tooling without adding capture-only controls to the shipped UI.
 */
final class ShowcaseApp extends Component
{
    private ?int $linkSubscription = null;

    /** @param array<string, string> $componentRoutes */
    public function __construct(
        private readonly DrawerNavigator $drawer,
        private readonly array $componentRoutes,
        private readonly array $componentAuditRoutes,
    ) {
    }

    public function render(): Renderable
    {
        // The catalog deliberately uses a light theme regardless of the
        // device theme. Author its system bar explicitly so a phone in dark
        // mode never renders white icons over the light showcase surface.
        return Screen::make(
            StatusBar::make(0xFFF8FAFC, StatusBarAppearance::Dark),
            $this->drawer->toElement()->style(new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                flexGrow: 1.0,
            )),
        )->style(new Style(
            widthPercent: 100.0,
            heightPercent: 100.0,
            flexGrow: 1.0,
            backgroundColor: 0xFFF8FAFC,
        ));
    }

    public function mount(): void
    {
        Linking::initial(function (?string $url): void {
            if ($url !== null) {
                $this->open($url);
            }
        });
        $this->linkSubscription = Linking::listen(
            function (string $url): void {
                $this->open($url);
            },
        );
    }

    public function unmount(): void
    {
        if ($this->linkSubscription !== null) {
            Linking::unsubscribe($this->linkSubscription);
            $this->linkSubscription = null;
        }
    }

    private function open(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'pam-showcase') {
            return false;
        }
        $kind = $parts['host'] ?? '';
        $target = rawurldecode(ltrim((string) ($parts['path'] ?? ''), '/'));
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $auditScenario = is_string($query['scenario'] ?? null)
            ? (string) $query['scenario']
            : 'interactive';
        $route = match ($kind) {
            'component', 'audit' => $this->componentRoutes[$target] ?? null,
            'screen' => match ($target) {
                'overview' => 'overview',
                'actions' => 'actions',
                'forms' => 'forms',
                'data' => 'data',
                'overlays' => 'overlays',
                'all' => 'all',
                default => null,
            },
            default => null,
        };
        if (!is_string($route)) {
            return false;
        }
        $componentRoute = $this->componentAuditRoutes[$target] ?? null;
        if ($componentRoute instanceof ComponentRoute) {
            $componentRoute->useAuditScenario(
                $kind === 'audit' ? $auditScenario : null,
            );
        }
        $this->drawer->navigate($route);

        return true;
    }
}
