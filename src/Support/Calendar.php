<?php

declare(strict_types=1);

namespace Pam\NativeUi\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Month grid math for the date input. Dates are ISO `Y-m-d` strings, which
 * compare correctly as strings.
 *
 * @internal
 */
final class Calendar
{
    private const array WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    private static ?string $today = null;

    private function __construct()
    {
    }

    /** @internal Test hook: pin "today". */
    public static function pinToday(?string $date): void
    {
        self::$today = $date;
    }

    public static function today(): string
    {
        return self::$today ?? date('Y-m-d');
    }

    public static function parse(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date {$date}; expected Y-m-d.");
        }

        return $parsed;
    }

    public static function monthOf(string $date): string
    {
        return self::parse($date)->format('Y-m-01');
    }

    public static function shift(string $month, int $months): string
    {
        return self::parse($month)->modify(($months >= 0 ? '+' : '').$months.' month')->format('Y-m-01');
    }

    public static function allowed(string $date, ?string $min, ?string $max): bool
    {
        return ($min === null || $date >= $min) && ($max === null || $date <= $max);
    }

    public static function clamp(string $date, ?string $min, ?string $max): string
    {
        if ($min !== null && $date < $min) {
            return $min;
        }
        if ($max !== null && $date > $max) {
            return $max;
        }

        return $date;
    }

    public static function title(string $month): string
    {
        return self::parse($month)->format('F Y');
    }

    public static function format(string $date, string $format): string
    {
        return self::parse($date)->format($format);
    }

    /** @return list<string> */
    public static function weekdays(int $firstDay): array
    {
        $first = (($firstDay % 7) + 7) % 7;

        return [...array_slice(self::WEEKDAYS, $first), ...array_slice(self::WEEKDAYS, 0, $first)];
    }

    /**
     * Six-or-fewer weeks covering the month, padded with the neighbouring
     * months' days.
     *
     * @return list<list<array{key: string, date: string, day: string, inMonth: bool, allowed: bool}>>
     */
    public static function weeks(string $month, int $firstDay, ?string $min, ?string $max): array
    {
        $start = self::parse($month);
        $first = (($firstDay % 7) + 7) % 7;
        $offset = ((int) $start->format('w') - $first + 7) % 7;
        $cursor = $start->modify('-'.$offset.' day');
        $monthKey = $start->format('Y-m');
        $weeks = [];
        do {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->format('Y-m-d');
                $week[] = [
                    'key' => $date,
                    'date' => $date,
                    'day' => $cursor->format('j'),
                    'inMonth' => $cursor->format('Y-m') === $monthKey,
                    'allowed' => self::allowed($date, $min, $max),
                ];
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        } while ($cursor->format('Y-m') === $monthKey);

        return $weeks;
    }
}
