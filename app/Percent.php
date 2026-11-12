<?php

namespace App;

/**
 * Credited percents for people to read. Whole numbers normally, rounded down (so a percent never shows a higher
 * band's number). When two whole numbers would look the same but the credited percents differ, both get just enough
 * decimals to show the difference, so a tie is only ever shown when the percents are truly equal.
 */
class Percent
{
    public static function whole(float $percent): string
    {
        return self::format($percent, 0);
    }

    /**
     * Two percents side by side.
     *
     * @return array{0: string, 1: string}
     */
    public static function pair(float $first, float $second): array
    {
        $first = round($first, 2);
        $second = round($second, 2);

        foreach ([0, 1, 2] as $decimals) {
            $a = self::format($first, $decimals);
            $b = self::format($second, $decimals);

            if ($a !== $b || $first === $second) {
                return [$a, $b];
            }
        }

        return [self::format($first, 2), self::format($second, 2)];
    }

    private static function format(float $percent, int $decimals): string
    {
        $factor = 10 ** $decimals;
        $value = floor(round($percent * $factor, 6)) / $factor;

        return number_format($value, $decimals, '.', '').'%';
    }
}
