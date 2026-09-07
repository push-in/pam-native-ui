<?php

declare(strict_types=1);

namespace App;

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\Align;
use Pam\Native\Component;
use Pam\Native\Renderable;
use Pam\Native\Style;
use Pam\Native\UI\Column;
use Pam\Native\UI\Row;
use Pam\Native\UI\Text;

/** @property (\stdClass&object{value: string, message: string}) $state */
final class ColorInputInteractionPreview extends Component
{
    private const array PALETTE = [
        '#6750A4' => 'Purple',
        '#006C4C' => 'Green',
        '#B3261E' => 'Red',
        '#3F5F90' => 'Blue',
        '#7D5260' => 'Rose',
    ];

    public function __construct(private readonly string $profile)
    {
    }

    /** @return array{value: string, message: string} */
    protected function initialState(): array
    {
        return [
            'value' => match ($this->profile) {
                'rgb' => '103, 80, 164',
                'hsl' => '256, 34%, 48%',
                'invalid' => '#12GG45',
                'disabled' => '#9E9E9E',
                default => '#6750A4',
            },
            'message' => '',
        ];
    }

    public function render(): Renderable
    {
        $input = MaterialComponentMap::TAGS['p-color-input'];
        $theme = ThemeManager::current();
        $value = (string) $this->state->value;
        $mode = in_array($this->profile, ['rgb', 'hsl'], true)
            ? $this->profile
            : 'hex';
        $invalid = !$this->valid($value, $mode);
        $disabled = $this->profile === 'disabled';

        $field = $input::make([
            'label' => strtoupper($mode).' value',
            'modelValue' => $value,
            'mode' => $mode,
            'variant' => 'outlined',
            'disabled' => $disabled,
            'error' => $invalid,
            'errorMessage' => $invalid ? 'Enter a valid '.strtoupper($mode).' color' : '',
            'helper' => $invalid ? '' : match ($mode) {
                'rgb' => 'Red, green and blue: 0–255',
                'hsl' => 'Hue, saturation and lightness',
                default => 'Six-digit hexadecimal color',
            },
            'accessibilityLabel' => strtoupper($mode).' color value',
        ])->onChange(function (string $next): bool {
            $this->state->value = strtoupper(trim($next));
            $this->state->message = 'Color updated';

            return true;
        });

        $previewColor = $this->displayColor($value, $mode);
        $preview = Row::make(
            Column::make(
                Text::make($invalid ? 'CHECK' : 'PAM')->style(new Style(
                    textColor: $invalid
                        ? $theme->color(ColorToken::Destructive)
                        : $previewColor,
                    fontSize: 14.0,
                    lineHeight: 20.0,
                    fontWeight: 800,
                )),
            )->style(new Style(
                width: 76.0,
                minWidth: 76.0,
                height: 40.0,
                minHeight: 40.0,
                alignItems: Align::Center,
                justifyContent: \Pam\Native\Justify::Center,
            )),
            Column::make(
                Text::make('Current color')->style(new Style(
                    fontSize: 12.0,
                    lineHeight: 16.0,
                    textColor: $theme->color(ColorToken::MutedForeground),
                )),
                Text::make($invalid ? 'Invalid value' : $value)->style(new Style(
                    fontSize: 16.0,
                    lineHeight: 24.0,
                    fontWeight: 600,
                    textColor: $invalid
                        ? $theme->color(ColorToken::Destructive)
                        : $previewColor,
                )),
            )->style(new Style(
                widthPercent: 65.0,
                flexGrow: 1.0,
                flexShrink: 1.0,
                gap: 0.0,
                alignItems: Align::Start,
            )),
        )->style(new Style(
            widthPercent: 100.0,
            gap: 16.0,
            alignItems: Align::Center,
        ));

        $children = [$preview, $field];
        if ($this->profile === 'palette') {
            $chip = MaterialComponentMap::TAGS['p-chip'];
            $group = MaterialComponentMap::TAGS['p-chip-group'];
            $swatches = [];
            foreach (self::PALETTE as $hex => $name) {
                $swatches[] = $chip::make([
                    'text' => $name,
                    'value' => $hex,
                    'filter' => true,
                    'variant' => 'outlined',
                    'accessibilityLabel' => 'Select '.$name,
                ]);
            }
            $palette = $group::make(
                [
                    'value' => $value,
                    'multiple' => false,
                    'direction' => 'vertical',
                ],
                ...$swatches,
            )->onChange(function (string|array $next): bool {
                if (!is_string($next) || !isset(self::PALETTE[$next])) {
                    return false;
                }
                $this->state->value = $next;
                $this->state->message = self::PALETTE[$next].' selected';

                return true;
            });
            $children[] = Column::make(
                Text::make('Brand palette')->style(new Style(
                    fontSize: 14.0,
                    lineHeight: 20.0,
                    fontWeight: 600,
                    textColor: $theme->color(ColorToken::OnSurface),
                )),
                $palette,
            )->style(new Style(gap: 8.0, alignItems: Align::Start));
        }
        if ((string) $this->state->message !== '') {
            $children[] = Text::make((string) $this->state->message)->style(new Style(
                fontSize: 12.0,
                lineHeight: 16.0,
                textColor: $theme->color(ColorToken::MutedForeground),
            ));
        }

        return Column::make(...$children)->style(new Style(
            widthPercent: 100.0,
            gap: 12.0,
            alignItems: Align::Start,
        ));
    }

    private function valid(string $value, string $mode): bool
    {
        return match ($mode) {
            'rgb' => preg_match('/^(?:25[0-5]|2[0-4]\\d|1?\\d?\\d),\\s*(?:25[0-5]|2[0-4]\\d|1?\\d?\\d),\\s*(?:25[0-5]|2[0-4]\\d|1?\\d?\\d)$/', $value) === 1,
            'hsl' => preg_match('/^(?:360|3[0-5]\\d|[12]?\\d?\\d),\\s*(?:100|\\d?\\d)%,\\s*(?:100|\\d?\\d)%$/', $value) === 1,
            default => preg_match('/^#[0-9A-F]{6}$/i', $value) === 1,
        };
    }

    private function displayColor(string $value, string $mode): int
    {
        if (!$this->valid($value, $mode)) {
            return 0xFFE6E1E5;
        }
        if ($mode === 'hex') {
            return self::hexColor($value);
        }
        preg_match_all('/\\d+/', $value, $matches);
        $channels = array_map('intval', $matches[0]);
        if ($mode === 'rgb') {
            return 0xFF000000
                | ($channels[0] << 16)
                | ($channels[1] << 8)
                | $channels[2];
        }

        [$hue, $saturation, $lightness] = $channels;
        $saturation /= 100;
        $lightness /= 100;
        $chroma = (1 - abs((2 * $lightness) - 1)) * $saturation;
        $segment = $hue / 60;
        $secondary = $chroma * (1 - abs(fmod($segment, 2) - 1));
        [$red, $green, $blue] = match ((int) floor($segment) % 6) {
            0 => [$chroma, $secondary, 0.0],
            1 => [$secondary, $chroma, 0.0],
            2 => [0.0, $chroma, $secondary],
            3 => [0.0, $secondary, $chroma],
            4 => [$secondary, 0.0, $chroma],
            default => [$chroma, 0.0, $secondary],
        };
        $offset = $lightness - ($chroma / 2);

        return 0xFF000000
            | ((int) round(($red + $offset) * 255) << 16)
            | ((int) round(($green + $offset) * 255) << 8)
            | (int) round(($blue + $offset) * 255);
    }

    private static function hexColor(string $value): int
    {
        return 0xFF000000 | (int) hexdec(ltrim($value, '#'));
    }
}
