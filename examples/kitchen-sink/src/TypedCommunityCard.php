<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Component\Badge;
use Pam\MobileUi\Component\BadgeText;
use Pam\MobileUi\Component\Card;
use Pam\MobileUi\Component\Heading;
use Pam\MobileUi\Component\Text;
use Pam\MobileUi\Component\VStack;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\ComponentVariant;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\TemplateRegistry;

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
        return Card::make(
            null,
            VStack::make(
                ['space' => 'sm'],
                Badge::make(
                    ['variant' => ComponentVariant::Secondary],
                    BadgeText::make('Typed PHP'),
                ),
                Heading::make($this->title)
                    ->size(ComponentSize::Large),
                Text::make($this->description),
            ),
        )->style(new Style(padding: 20.0, gap: 12.0));
    }
}
