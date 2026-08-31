<?php

declare(strict_types=1);

namespace App;

use Pam\Native\Component;
use Pam\Native\Navigation\DrawerNavigator;
use Pam\Native\Renderable;
use Pam\Native\System\Linking;

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
    ) {
    }

    public function render(): Renderable
    {
        return $this->drawer;
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
        $route = match ($kind) {
            'component' => $this->componentRoutes[$target] ?? null,
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
        $this->drawer->navigate($route);

        return true;
    }
}
