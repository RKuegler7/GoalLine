<?php

namespace App;

/**
 * Showing points: whole numbers as-is, partial points (from numeric results) to one decimal.
 */
final class Points
{
    public static function format(float $points): string
    {
        return fmod($points, 1.0) === 0.0 ? (string) (int) $points : number_format($points, 1);
    }
}
