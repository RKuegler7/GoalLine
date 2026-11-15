<?php

namespace App;

use Carbon\CarbonImmutable;

/**
 * The dates of Lent for a year, worked out from Easter (never hard-coded): Lent runs from Ash Wednesday, 46 days
 * before Easter Sunday, to Holy Saturday, the day before Easter. Western (Gregorian) Easter, by the anonymous
 * Gregorian algorithm (Meeus/Jones/Butcher).
 */
class Lent
{
    public static function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    public static function ashWednesday(int $year): CarbonImmutable
    {
        return self::easter($year)->subDays(46);
    }

    public static function holySaturday(int $year): CarbonImmutable
    {
        return self::easter($year)->subDay();
    }

    /**
     * The next Lent that hasn't ended by the given day: this year's, or next year's once it's over.
     */
    public static function yearFor(CarbonImmutable $today): int
    {
        return $today->gt(self::holySaturday($today->year)) ? $today->year + 1 : $today->year;
    }
}
