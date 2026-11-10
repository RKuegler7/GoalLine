<?php

namespace App\Services\ResultCards;

/**
 * A tiny drawing surface for result cards, so one layout can be drawn as SVG (always available) or as a PNG with GD.
 * Coordinates are pixels; text y is the baseline. Colors are #rrggbb.
 */
interface CardCanvas
{
    public function rect(float $x, float $y, float $width, float $height, string $fill, float $radius = 0, float $opacity = 1): void;

    public function circle(float $cx, float $cy, float $radius, string $fill, float $opacity = 1): void;

    public function line(float $x1, float $y1, float $x2, float $y2, string $stroke, float $width, float $opacity = 1): void;

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public function polygon(array $points, string $fill, float $opacity = 1): void;

    /**
     * @param  'medium'|'bold'|'extrabold'  $weight
     * @param  'start'|'middle'|'end'  $anchor
     */
    public function text(float $x, float $y, string $text, float $size, string $weight, string $fill, string $anchor = 'start', float $opacity = 1): void;

    /**
     * The finished image's bytes.
     */
    public function output(): string;

    /**
     * The image's media type.
     */
    public function mimeType(): string;
}
