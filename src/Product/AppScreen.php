<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityRole;
use Pam\Native\KeyboardAvoidingBehavior;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\KeyboardAvoidingView;
use Pam\Native\UI\Row;
use Pam\Native\UI\SafeAreaView;
use Pam\Native\UI\ScrollView;
use Pam\Native\UI\Text;

final readonly class AppScreen implements Renderable
{
    /** @param list<Renderable> $content
     *  @param list<Renderable> $actions
     *  @param list<Renderable> $bottom
     */
    private function __construct(
        private string $title,
        private ?string $subtitle,
        private array $content,
        private array $actions,
        private array $bottom,
        private bool $scrollable,
        private bool $keyboardSafe,
    ) {
    }

    public static function make(
        string $title,
        Renderable ...$content,
    ): self {
        return new self($title, null, array_values($content), [], [], true, true);
    }

    /** @param array<string, mixed> $props
     *  @param list<Renderable> $children
     */
    public static function fromTemplate(array $props, array $children): self
    {
        $slots = is_array($props['__pamSlots'] ?? null) ? $props['__pamSlots'] : [];

        return new self(
            title: is_scalar($props['title'] ?? null) ? (string) $props['title'] : '',
            subtitle: is_scalar($props['subtitle'] ?? null) ? (string) $props['subtitle'] : null,
            content: $children,
            actions: self::renderables($slots['actions'] ?? []),
            bottom: self::renderables($slots['bottom'] ?? []),
            scrollable: self::flag($props['scrollable'] ?? true),
            keyboardSafe: self::flag($props['keyboardSafe'] ?? true),
        );
    }

    public function subtitle(string $subtitle): self
    {
        return new self(
            $this->title,
            $subtitle,
            $this->content,
            $this->actions,
            $this->bottom,
            $this->scrollable,
            $this->keyboardSafe,
        );
    }

    public function actions(Renderable ...$actions): self
    {
        return new self(
            $this->title,
            $this->subtitle,
            $this->content,
            array_values($actions),
            $this->bottom,
            $this->scrollable,
            $this->keyboardSafe,
        );
    }

    public function bottom(Renderable ...$bottom): self
    {
        return new self(
            $this->title,
            $this->subtitle,
            $this->content,
            $this->actions,
            array_values($bottom),
            $this->scrollable,
            $this->keyboardSafe,
        );
    }

    public function toElement(): \Pam\Native\Element
    {
        $theme = ThemeManager::current();
        $heading = Text::make($this->title)
            ->style(new Style(
                textColor: $theme->color(ColorToken::OnSurface),
                fontSize: 22.0,
                fontWeight: 700,
                lineHeight: 28.0,
            ))
            ->accessibilityRole(AccessibilityRole::Header);
        $titles = [$heading];
        if ($this->subtitle !== null && $this->subtitle !== '') {
            $titles[] = Text::make($this->subtitle)->style(new Style(
                textColor: $theme->color(ColorToken::MutedForeground),
                fontSize: 14.0,
                lineHeight: 20.0,
            ));
        }
        $header = Row::make(
            Column::make(...$titles)->style(new Style(flexGrow: 1, gap: 4.0)),
            ...$this->actions,
        )->style(new Style(
            minHeight: 64.0,
            paddingLeft: 16.0,
            paddingTop: 10.0,
            paddingRight: 16.0,
            paddingBottom: 8.0,
            gap: 8.0,
            alignItems: \Pam\Native\Align::Center,
        ));
        $content = Column::make(...$this->content)->style(new Style(
            flexGrow: 1.0,
            paddingHorizontal: 16.0,
            paddingTop: 8.0,
            paddingBottom: $this->bottom === [] ? 16.0 : 12.0,
            gap: 12.0,
            backgroundColor: $theme->color(ColorToken::Background),
        ));
        $body = ($this->scrollable
            ? ScrollView::make($content)->key('app-screen-scroll:'.$this->title)
            : $content)
            ->style(new Style(
                flexGrow: 1.0,
                flexShrink: 1.0,
                backgroundColor: $theme->color(ColorToken::Background),
            ));
        $body = $this->keyboardSafe
            ? KeyboardAvoidingView::make($body, KeyboardAvoidingBehavior::Resize)
                ->style(new Style(flexGrow: 1.0, flexShrink: 1.0))
            : $body;

        return SafeAreaView::make(
            Column::make(
                $header,
                $body,
                ...$this->bottom,
            )->style(new Style(
                widthPercent: 100.0,
                heightPercent: 100.0,
                flexGrow: 1.0,
                backgroundColor: $theme->color(ColorToken::Background),
            )),
        )->edges()->style(new Style(
            widthPercent: 100.0,
            heightPercent: 100.0,
            flexGrow: 1.0,
            backgroundColor: $theme->color(ColorToken::Background),
        ));
    }

    /** @return list<Renderable> */
    private static function renderables(mixed $items): array
    {
        return is_array($items)
            ? array_values(array_filter($items, static fn (mixed $item): bool => $item instanceof Renderable))
            : [];
    }

    private static function flag(mixed $value): bool
    {
        return is_bool($value) ? $value : !in_array($value, [0, '0', 'false', 'off'], true);
    }
}
