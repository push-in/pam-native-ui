<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ColorToken;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\Rendering\MaterialFieldProps;
use Pam\MobileUi\Rendering\MaterialStyleResolver;
use Pam\MobileUi\Theme\ThemeManager;
use Pam\Native\Internal\BinaryValue;
use Pam\Native\Internal\Wire;
use Pam\Native\PropKey;

require_once __DIR__.'/bootstrap.php';

(static function (): void {
    $parts = ['PTextField', 'PTextarea', 'PNumberInput', 'POtpInput',
        'PColorInput', 'PDateInput', 'PPasswordField', 'PMaskedField',
        'PCurrencyField', 'PSelect', 'PAutocomplete', 'PCombobox', 'PTagInput', 'PMultiSelect'];
    foreach ($parts as $part) {
        foreach ([['isInvalid' => true], ['invalid' => true], ['error' => true],
            ['errorMessages' => ['First problem', 'Second problem']],
            ['errorMessage' => 'Fix this value']] as $case) {
            $props = ['__materialComponent' => $part, 'label' => 'Field', 'isRequired' => true, ...$case];
            $normalized = MaterialFieldProps::normalize($part, $props);
            if ($normalized['error'] !== true || $normalized['invalid'] !== true || $normalized['required'] !== true) {
                throw new RuntimeException($part.' must normalize field-state aliases.');
            }
            $style = MaterialStyleResolver::resolve($props, ThemeManager::current());
            if ($part !== 'POtpInput' && $style?->borderColor !== ThemeManager::current()->color(ColorToken::Destructive)) {
                throw new RuntimeException($part.' must style the same invalid state as its native host.');
            }
            $class = 'Pam\\MobileUi\\Material\\'.$part;
            $tag = array_search($class, MaterialComponentMap::TAGS, true);
            if ($tag === false) {
                throw new RuntimeException('Missing public field '.$part);
            }
            $element = MaterialComponentMap::TAGS[$tag]::make($props)->toElement();
            $nativeInvalid = false;
            $texts = [];
            $errorBorder = false;
            $stack = [$element];
            while ($stack !== []) {
                $node = array_pop($stack);
                $host = $node->properties()[PropKey::HostProperties->value] ?? null;
                $nativeInvalid = $nativeInvalid || ($host instanceof BinaryValue
                    && (Wire::decodeMap($host->bytes)['invalid'] ?? null) === true);
                $text = $node->properties()[PropKey::Text->value] ?? null;
                if (is_string($text)) {
                    $texts[] = $text;
                }
                $errorBorder = $errorBorder || ($node->properties()[PropKey::BorderColor->value] ?? null)
                    === ThemeManager::current()->color(ColorToken::Destructive);
                array_push($stack, ...$node->children());
            }
            if (!$nativeInvalid) {
                throw new RuntimeException($part.' must expose invalid state to its native host.');
            }
            if (!$errorBorder) {
                throw new RuntimeException($part.' must render its error border on the field or OTP slots.');
            }
            if ($part !== 'POtpInput' && !in_array('Field *', $texts, true)) {
                throw new RuntimeException($part.' must display its required indicator for isRequired.');
            }
            if (isset($case['errorMessages']) && !in_array("First problem\nSecond problem", $texts, true)) {
                throw new RuntimeException($part.' must display every supplied error message.');
            }
        }
        foreach ([['error' => false, 'invalid' => true], ['invalid' => false, 'isInvalid' => true],
            ['errorMessages' => []], ['errorMessages' => ''], ['errorMessages' => null]] as $case) {
            $props = MaterialFieldProps::normalize($part, ['required' => false, 'isRequired' => true, ...$case]);
            if ($props['error'] !== false || $props['required'] !== false) {
                throw new RuntimeException($part.' must respect explicit false and empty messages.');
            }
        }
    }
})();
