<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Material\PBadge;
use Pam\MobileUi\Material\PCard;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\TemplateRegistry;
use Pam\Native\UI\Column;
use Pam\Native\UI\Text;

final class TypedCommunityCard extends Component
{
    public function __construct(
        private readonly string $title,
        private readonly string $description,
    ) {
    }

    public static function register(): void
    {
        TemplateRegistry::component(
            'TypedCommunityCard',
            static function (
                array $props,
                array $_children,
                ?object $_scope,
            ): self {
                $title = $props['title'] ?? null;
                $description = $props['description'] ?? null;

                return new self(
                    title: is_string($title)
                        ? $title
                        : 'Typed PHP component',
                    description: is_string($description)
                        ? $description
                        : 'Reusable classes and declarative tags share one native tree.',
                );
            },
        );
    }

    public function render(): Renderable
    {
        return PCard::make(
            [],
            Column::make(
                PBadge::make(['text' => 'Typed PHP', 'variant' => 'tonal']),
                Text::make($this->title)->style(new Style(
                    fontSize: 20.0,
                    lineHeight: 28.0,
                    fontWeight: 600,
                )),
                Text::make($this->description)->style(new Style(
                    fontSize: 16.0,
                    lineHeight: 24.0,
                )),
            )->style(new Style(gap: 8.0)),
        )->style(new Style(padding: 20.0, gap: 12.0));
    }
}
