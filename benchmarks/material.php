<?php

declare(strict_types=1);

use Pam\MobileUi\Enum\ThemeMode;
use Pam\MobileUi\Generated\MaterialComponentMap;
use Pam\MobileUi\PamUI;
use Pam\Native\Internal\TreeEncoder;

require dirname(__DIR__).'/tests/bootstrap.php';

$iterations = 100;
$components = 0;
$largestFrameBytes = 0;

for ($warmup = 0; $warmup < 3; $warmup++) {
    foreach (MaterialComponentMap::TAGS as $class) {
        (new TreeEncoder())->encode($class::make(['text' => 'PAM'])->toElement());
    }
}

$started = hrtime(true);
for ($iteration = 0; $iteration < $iterations; $iteration++) {
    PamUI::mode($iteration % 2 === 0 ? ThemeMode::Light : ThemeMode::Dark);
    foreach (MaterialComponentMap::TAGS as $class) {
        $encoded = (new TreeEncoder())->encode($class::make([
            'text' => 'PAM',
            'value' => $iteration + 1,
            'accessibilityLabel' => 'PAM component',
        ])->toElement());
        $frame = $encoded['frame'];
        if (!is_string($frame) || !str_starts_with($frame, 'PNT1')) {
            throw new RuntimeException('Material benchmark produced an invalid native frame.');
        }
        $largestFrameBytes = max($largestFrameBytes, strlen($frame));
        $components++;
    }
}
$elapsedNanoseconds = hrtime(true) - $started;
$componentsPerSecond = $components / ($elapsedNanoseconds / 1_000_000_000);
$peakMemoryBytes = memory_get_peak_usage(true);

$minimumComponentsPerSecond = 5_000;
$maximumFrameBytes = 1_048_576;
$maximumPeakMemoryBytes = 33_554_432;

if ($componentsPerSecond < $minimumComponentsPerSecond) {
    throw new RuntimeException(sprintf(
        'Material throughput %.1f/s is below the %d/s release budget.',
        $componentsPerSecond,
        $minimumComponentsPerSecond,
    ));
}
if ($largestFrameBytes > $maximumFrameBytes) {
    throw new RuntimeException('A Material component exceeded the one-megabyte frame budget.');
}
if ($peakMemoryBytes > $maximumPeakMemoryBytes) {
    throw new RuntimeException('Material benchmark exceeded the 32-megabyte peak-memory budget.');
}

echo json_encode(
    [
        'benchmark' => 'material-facade-and-binary-encode',
        'inventory' => count(MaterialComponentMap::TAGS),
        'components' => $components,
        'elapsedMs' => round($elapsedNanoseconds / 1_000_000, 3),
        'componentsPerSecond' => round($componentsPerSecond, 1),
        'largestFrameBytes' => $largestFrameBytes,
        'peakMemoryBytes' => $peakMemoryBytes,
        'budgets' => [
            'minimumComponentsPerSecond' => $minimumComponentsPerSecond,
            'maximumFrameBytes' => $maximumFrameBytes,
            'maximumPeakMemoryBytes' => $maximumPeakMemoryBytes,
        ],
        'php' => PHP_VERSION,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
)."\n";
