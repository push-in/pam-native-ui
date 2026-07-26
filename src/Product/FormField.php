<?php

declare(strict_types=1);

namespace Pam\MobileUi\Product;

use Closure;
use InvalidArgumentException;
use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Theme\DesignTokens;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\AccessibilityLiveRegion;
use Pam\Native\AccessibilityRole;
use Pam\Native\Forms\NativeForm;
use Pam\Native\InputSyncMode;
use Pam\Native\KeyboardType;
use Pam\Native\Renderable;
use Pam\Native\ReturnKeyType;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\Input;
use Pam\Native\UI\Text;

final readonly class FormField implements Renderable
{
    private function __construct(
        private NativeForm $form,
        private string $field,
        private string $label,
        private ?string $helper,
        private ?string $placeholder,
        private KeyboardType $keyboard,
        private bool $secure,
        private bool $readOnly,
        private ?string $autoComplete,
        private ?Closure $normalizer,
        private ReturnKeyType $returnKey,
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $field) !== 1) {
            throw new InvalidArgumentException('Form fields require safe property names.');
        }
        $form->value($field);
    }

    public static function make(
        NativeForm $form,
        string $field,
        string $label,
    ): self {
        return new self(
            $form,
            $field,
            $label,
            null,
            null,
            KeyboardType::Text,
            false,
            false,
            null,
            null,
            ReturnKeyType::Next,
        );
    }

    /** @param array<string, mixed> $props
     *  @param list<Renderable> $_children
     */
    public static function fromTemplate(array $props, array $_children): self
    {
        $form = $props['form'] ?? null;
        if (!$form instanceof NativeForm) {
            throw new InvalidArgumentException('FormField requires a NativeForm instance.');
        }
        $field = is_scalar($props['field'] ?? null) ? (string) $props['field'] : '';
        $label = is_scalar($props['label'] ?? null) ? (string) $props['label'] : $field;
        $keyboard = KeyboardType::tryFrom(self::integer($props['keyboard'] ?? null, 1))
            ?? KeyboardType::Text;
        $returnKey = ReturnKeyType::tryFrom(self::integer($props['returnKey'] ?? null, 2))
            ?? ReturnKeyType::Next;

        return new self(
            $form,
            $field,
            $label,
            self::nullableText($props['helper'] ?? null),
            self::nullableText($props['placeholder'] ?? null),
            $keyboard,
            self::flag($props['secure'] ?? false),
            self::flag($props['readOnly'] ?? false),
            self::nullableText($props['autoComplete'] ?? null),
            null,
            $returnKey,
        );
    }

    public function helper(string $helper): self
    {
        return $this->copy(helper: $helper);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->copy(placeholder: $placeholder);
    }

    public function keyboard(KeyboardType $keyboard): self
    {
        return $this->copy(keyboard: $keyboard);
    }

    public function secure(bool $secure = true): self
    {
        return $this->copy(secure: $secure);
    }

    public function readOnly(bool $readOnly = true): self
    {
        return $this->copy(readOnly: $readOnly);
    }

    public function autoComplete(string $autoComplete): self
    {
        return $this->copy(autoComplete: $autoComplete);
    }

    public function normalize(Closure $normalizer): self
    {
        return $this->copy(normalizer: $normalizer);
    }

    public function returnKey(ReturnKeyType $returnKey): self
    {
        return $this->copy(returnKey: $returnKey);
    }

    public function toElement(): \Pam\Native\Element
    {
        $theme = ThemeManager::current();
        $error = $this->form->error($this->field);
        $value = $this->form->value($this->field);
        $input = Input::make(is_scalar($value) ? (string) $value : '')
            ->placeholder($this->placeholder ?? '')
            ->keyboard($this->keyboard)
            ->secure($this->secure)
            ->editable(!$this->readOnly)
            ->nativeState(InputSyncMode::Debounced, 48)
            ->returnKey($this->returnKey)
            ->autoFocus(
                $error !== null
                && $this->form->firstErrorField() === $this->field,
            )
            ->onChange(function (string $value): void {
                $normalized = $this->normalizer === null
                    ? $value
                    : ($this->normalizer)($value);
                $this->form->set($this->field, $normalized);
            })
            ->onBlur(fn (): bool => $this->form->validate($this->field))
            ->style(new Style(
                minHeight: 52.0,
                paddingHorizontal: 14.0,
                backgroundColor: $this->readOnly
                    ? $theme->color(ColorToken::SurfaceSunken)
                    : $theme->color(ColorToken::Surface),
                textColor: $theme->color(ColorToken::OnSurface),
                placeholderColor: $theme->color(ColorToken::MutedForeground),
                borderColor: $theme->color(
                    $error === null ? ColorToken::Input : ColorToken::Destructive,
                ),
                borderWidth: $error === null ? 1.0 : 2.0,
                borderRadius: DesignTokens::RADIUS_MEDIUM,
                fontSize: DesignTokens::TEXT_BODY,
            ))
            ->accessibilityLabel($this->label)
            ->accessibilityHint($error ?? $this->helper ?? '');
        if ($this->autoComplete !== null) {
            $input = $input->property(
                \Pam\Native\PropKey::AutoComplete,
                $this->autoComplete,
            );
        }
        $support = null;
        if ($error !== null) {
            $support = Text::make($error)
                ->style(new Style(
                    textColor: $theme->color(ColorToken::Destructive),
                    fontSize: DesignTokens::TEXT_LABEL,
                    lineHeight: 18.0,
                ))
                ->accessibilityRole(AccessibilityRole::Alert)
                ->accessibilityLiveRegion(AccessibilityLiveRegion::Assertive);
        } elseif ($this->helper !== null) {
            $support = Text::make($this->helper)->style(new Style(
                textColor: $theme->color(ColorToken::MutedForeground),
                fontSize: DesignTokens::TEXT_LABEL,
                lineHeight: 18.0,
            ));
        }

        return Column::make(
            Text::make($this->label)->style(new Style(
                textColor: $theme->color(ColorToken::OnSurface),
                fontSize: 14.0,
                fontWeight: 600,
            )),
            $input,
            ...($support === null ? [] : [$support]),
        )->style(new Style(gap: 7.0));
    }

    private function copy(
        ?string $helper = null,
        ?string $placeholder = null,
        ?KeyboardType $keyboard = null,
        ?bool $secure = null,
        ?bool $readOnly = null,
        ?string $autoComplete = null,
        ?Closure $normalizer = null,
        ?ReturnKeyType $returnKey = null,
    ): self {
        return new self(
            $this->form,
            $this->field,
            $this->label,
            $helper ?? $this->helper,
            $placeholder ?? $this->placeholder,
            $keyboard ?? $this->keyboard,
            $secure ?? $this->secure,
            $readOnly ?? $this->readOnly,
            $autoComplete ?? $this->autoComplete,
            $normalizer ?? $this->normalizer,
            $returnKey ?? $this->returnKey,
        );
    }

    private static function nullableText(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function flag(mixed $value): bool
    {
        return is_bool($value) ? $value : in_array($value, [1, '1', 'true', 'on'], true);
    }

    private static function integer(mixed $value, int $fallback): int
    {
        return is_int($value)
            ? $value
            : (is_string($value) && is_numeric($value) ? (int) $value : $fallback);
    }
}
