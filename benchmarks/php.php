<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ButtonVariant;
use Pam\MobileUi\Material\PBtn;
use Pam\MobileUi\Material\PCard;
use Pam\MobileUi\Material\PChip;
use Pam\MobileUi\Material\PProgressLinear;
use Pam\MobileUi\Material\PTextField;
use Pam\Native\Internal\TreeEncoder;
use Pam\Native\Renderable;

require dirname(__DIR__).'/tests/bootstrap.php';

/**
 * Represents a realistic, styled component subtree. It deliberately creates
 * fresh immutable objects so the timing covers composition, recipe resolution,
 * style compilation, native element creation and binary encoding.
 */
$tree = static function (int $iteration): Renderable {
    return PCard::make(
        ['title' => 'Checkout', 'subtitle' => 'Native UI iteration '.$iteration],
        PTextField::make([
            'label' => 'Email',
            'value' => 'hello@example.com',
        ]),
        PProgressLinear::make(['value' => 64.0]),
        PChip::make(['text' => 'Ready']),
        PBtn::make([
            'text' => 'Continue',
            'variant' => ButtonVariant::Default,
        ]),
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
