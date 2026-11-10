<?php

namespace App\Services\ResultCards;

use GdImage;
use RuntimeException;

/**
 * Draws a result card as a PNG with GD and the bundled Plus Jakarta Sans font files (resources/fonts, SIL Open Font
 * License). Used for downloads, sharing, Highlights posts and link previews when the server has GD with FreeType.
 */
class GdCanvas implements CardCanvas
{
    private GdImage $image;

    private const FONTS = [
        'medium' => 'PlusJakartaSans-Medium.ttf',
        'bold' => 'PlusJakartaSans-Bold.ttf',
        'extrabold' => 'PlusJakartaSans-ExtraBold.ttf',
    ];

    public function __construct(int $width, int $height)
    {
        if (! self::isAvailable()) {
            throw new RuntimeException('GD with FreeType and the card fonts are needed to draw PNG cards.');
        }

        $this->image = imagecreatetruecolor($width, $height);
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);
        imageantialias($this->image, true);
    }

    /**
     * Whether PNG cards can be drawn here: GD with FreeType, and the font files present.
     */
    public static function isAvailable(): bool
    {
        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            return false;
        }

        foreach (self::FONTS as $file) {
            if (! is_file(self::fontPath($file))) {
                return false;
            }
        }

        return true;
    }

    public function rect(float $x, float $y, float $width, float $height, string $fill, float $radius = 0, float $opacity = 1): void
    {
        $color = $this->color($fill, $opacity);
        $radius = min($radius, $width / 2, $height / 2);
        [$x1, $y1, $x2, $y2] = [(int) round($x), (int) round($y), (int) round($x + $width) - 1, (int) round($y + $height) - 1];

        if ($radius < 1) {
            imagefilledrectangle($this->image, $x1, $y1, $x2, $y2, $color);

            return;
        }

        $r = (int) round($radius);
        imagefilledrectangle($this->image, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($this->image, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
        imagefilledrectangle($this->image, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);

        foreach ([[$x1 + $r, $y1 + $r, 180], [$x2 - $r, $y1 + $r, 270], [$x2 - $r, $y2 - $r, 0], [$x1 + $r, $y2 - $r, 90]] as [$cx, $cy, $start]) {
            imagefilledarc($this->image, $cx, $cy, $r * 2, $r * 2, $start, $start + 90, $color, IMG_ARC_PIE);
        }
    }

    public function circle(float $cx, float $cy, float $radius, string $fill, float $opacity = 1): void
    {
        $diameter = (int) round($radius * 2);
        imagefilledellipse($this->image, (int) round($cx), (int) round($cy), $diameter, $diameter, $this->color($fill, $opacity));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $stroke, float $width, float $opacity = 1): void
    {
        // A thick line with round caps: a rectangle-like polygon plus a circle at each end.
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $dx = sin($angle) * $width / 2;
        $dy = cos($angle) * $width / 2;
        $this->polygon([[$x1 + $dx, $y1 - $dy], [$x2 + $dx, $y2 - $dy], [$x2 - $dx, $y2 + $dy], [$x1 - $dx, $y1 + $dy]], $stroke, $opacity);

        if ($opacity >= 1) {
            $this->circle($x1, $y1, $width / 2, $stroke);
            $this->circle($x2, $y2, $width / 2, $stroke);
        }
    }

    public function polygon(array $points, string $fill, float $opacity = 1): void
    {
        $flat = [];

        foreach ($points as [$px, $py]) {
            $flat[] = (int) round($px);
            $flat[] = (int) round($py);
        }

        imagefilledpolygon($this->image, $flat, $this->color($fill, $opacity));
    }

    public function text(float $x, float $y, string $text, float $size, string $weight, string $fill, string $anchor = 'start', float $opacity = 1): void
    {
        $font = self::fontPath(self::FONTS[$weight] ?? self::FONTS['bold']);
        // GD measures type in points at 96 dpi.
        $points = $size * 0.75;

        if ($anchor !== 'start') {
            $box = imagettfbbox($points, 0, $font, $text);
            $width = $box ? $box[2] - $box[0] : 0;
            $x -= $anchor === 'middle' ? $width / 2 : $width;
        }

        imagettftext($this->image, $points, 0, (int) round($x), (int) round($y), $this->color($fill, $opacity), $font, $text);
    }

    public function output(): string
    {
        ob_start();
        imagepng($this->image, null, 6);

        return (string) ob_get_clean();
    }

    public function mimeType(): string
    {
        return 'image/png';
    }

    private function color(string $hex, float $opacity): int
    {
        [$red, $green, $blue] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $alpha = (int) round((1 - max(0, min(1, $opacity))) * 127);

        return imagecolorallocatealpha($this->image, $red ?? 0, $green ?? 0, $blue ?? 0, $alpha);
    }

    private static function fontPath(string $file): string
    {
        return resource_path('fonts/'.$file);
    }
}
