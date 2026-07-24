<?php

declare(strict_types=1);

use Pam\MobileUi\Component\Badge;
use Pam\MobileUi\Component\BadgeText;
use Pam\MobileUi\Component\Button;
use Pam\MobileUi\Component\ButtonText;
use Pam\MobileUi\Component\Card;
use Pam\MobileUi\Component\Heading;
use Pam\MobileUi\Component\Input;
use Pam\MobileUi\Component\InputField;
use Pam\MobileUi\Component\Progress;
use Pam\MobileUi\Component\ProgressFilledTrack;
use Pam\MobileUi\Component\Text;
use Pam\MobileUi\Component\VStack;
use Pam\MobileUi\Enum\ComponentSize;
use Pam\MobileUi\Enum\ComponentVariant;
use Pam\Native\Internal\TreeEncoder;
use Pam\Native\Renderable;

require dirname(__DIR__).'/tests/bootstrap.php';

/**
 * Represents a realistic, styled component subtree. It deliberately creates
 * fresh immutable objects so the timing covers composition, recipe resolution,
 * style compilation, native element creation and binary encoding.
 */
$tree = static function (int $iteration): Renderable {
    return Card::make(
        VStack::make(
            ['space' => 'md'],
            Heading::make('Checkout')->size(ComponentSize::ExtraLarge),
            Text::make('Native UI iteration '.$iteration),
            Input::make(
                ['variant' => ComponentVariant::Outline],
                InputField::make(['value' => 'hello@example.com']),
            ),
            Progress::make(
                ['value' => 64.0],
                ProgressFilledTrack::make(),
            ),
            Badge::make(
                ['variant' => ComponentVariant::Secondary],
                BadgeText::make('Ready'),
            ),
            Button::make(
                ['variant' => ComponentVariant::Default],
                ButtonText::make('Continue'),
            ),
        ),
    );
};

for ($iteration = 0; $iteration < 100; $iteration++) {
    (new TreeEncoder())->encode($tree($iteration)->toElement());
}

$iterations = 2_000;
$started = hrtime(true);
$frameBytes = 0;

for ($iteration = 0; $iteration < $iterations; $iteration++) {
    $encoded = (new TreeEncoder())->encode($tree($iteration)->toElement());
    $frame = $encoded['frame'];
    if (!is_string($frame) || !str_starts_with($frame, 'PNT1')) {
        throw new RuntimeException('Benchmark did not produce a complete PAM binary tree.');
    }
    $frameBytes = strlen($frame);
}

$elapsedNanoseconds = hrtime(true) - $started;
$elapsedSeconds = $elapsedNanoseconds / 1_000_000_000;

echo json_encode(
    [
        'benchmark' => 'php-compose-and-binary-encode',
        'iterations' => $iterations,
        'elapsedMs' => round($elapsedNanoseconds / 1_000_000, 3),
        'treesPerSecond' => round($iterations / $elapsedSeconds, 1),
        'lastFrameBytes' => $frameBytes,
        'peakMemoryBytes' => memory_get_peak_usage(true),
        'php' => PHP_VERSION,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";
