<?php

namespace App\Services\ResultCards;

/**
 * Draws a result card as SVG: no extensions needed, crisp at any size. Text uses Plus Jakarta Sans where the page has
 * it (the share page shows the SVG inline), and a system sans-serif otherwise. Everything written is escaped.
 */
class SvgCanvas implements CardCanvas
{
    /** @var list<string> */
    private array $parts = [];

    private const WEIGHTS = ['medium' => 500, 'bold' => 700, 'extrabold' => 800];

    public function __construct(private int $width, private int $height, private string $title) {}

    public function rect(float $x, float $y, float $width, float $height, string $fill, float $radius = 0, float $opacity = 1): void
    {
        $this->parts[] = sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"%s/>',
            $this->n($x), $this->n($y), $this->n($width), $this->n($height), $this->n($radius), $this->color($fill), $this->opacity($opacity));
    }

    public function circle(float $cx, float $cy, float $radius, string $fill, float $opacity = 1): void
    {
        $this->parts[] = sprintf('<circle cx="%s" cy="%s" r="%s" fill="%s"%s/>', $this->n($cx), $this->n($cy), $this->n($radius), $this->color($fill), $this->opacity($opacity));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $stroke, float $width, float $opacity = 1): void
    {
        $this->parts[] = sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" stroke-linecap="round"%s/>',
            $this->n($x1), $this->n($y1), $this->n($x2), $this->n($y2), $this->color($stroke), $this->n($width), $this->opacity($opacity, 'stroke-opacity'));
    }

    public function polygon(array $points, string $fill, float $opacity = 1): void
    {
        $list = implode(' ', array_map(fn (array $point) => $this->n($point[0]).','.$this->n($point[1]), $points));
        $this->parts[] = sprintf('<polygon points="%s" fill="%s"%s/>', $list, $this->color($fill), $this->opacity($opacity));
    }

    public function text(float $x, float $y, string $text, float $size, string $weight, string $fill, string $anchor = 'start', float $opacity = 1): void
    {
        $this->parts[] = sprintf('<text x="%s" y="%s" font-size="%s" font-weight="%d" fill="%s" text-anchor="%s"%s>%s</text>',
            $this->n($x), $this->n($y), $this->n($size), self::WEIGHTS[$weight] ?? 700, $this->color($fill),
            in_array($anchor, ['start', 'middle', 'end'], true) ? $anchor : 'start', $this->opacity($opacity), $this->escape($text));
    }

    public function output(): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="%s" font-family="\'Plus Jakarta Sans\', ui-sans-serif, system-ui, -apple-system, \'Segoe UI\', sans-serif">%s</svg>',
            $this->width, $this->height, $this->width, $this->height, $this->escape($this->title), implode('', $this->parts),
        );
    }

    public function mimeType(): string
    {
        return 'image/svg+xml';
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function color(string $hex): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $hex) ? $hex : '#000000';
    }

    private function opacity(float $opacity, string $attribute = 'opacity'): string
    {
        return $opacity < 1 ? sprintf(' %s="%s"', $attribute, $this->n(max(0, $opacity))) : '';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
