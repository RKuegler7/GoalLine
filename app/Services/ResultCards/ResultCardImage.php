<?php

namespace App\Services\ResultCards;

use App\Models\ResultCard;
use App\Services\ResultCardData;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * The weekly result card, laid out once and drawn as SVG or PNG. Feed is 1080×1350, story 1080×1920. Each band has its
 * own treatment: Smashed a blue diamond, Past gold, In line green, and Short a calm ivory card that says "Showed up.
 * Back on the line next week." (never red, never shaming). PNGs are cached by a hash of what the card shows.
 */
class ResultCardImage
{
    public const SIZES = ['feed' => [1080, 1350], 'story' => [1080, 1920]];

    public const FORMATS = ['svg', 'png'];

    private const CLUB = '#176b45';

    private const GRASS = '#43b96a';

    private const MINT = '#e5f3e8';

    private const IVORY = '#fafaf5';

    private const INK = '#1e2922';

    private const GOLD = '#d4ac58';

    private const BLUE = '#0369a1';

    private const SKY = '#7dd3fc';

    private const TAGLINE = 'Get in line with your goals.';

    /**
     * Whether PNGs can be made on this server (GD with FreeType and the font files). Without it, cards are SVG and
     * the share sheet makes the PNG in the browser.
     */
    public static function canMakePng(): bool
    {
        return GdCanvas::isAvailable();
    }

    /**
     * The card's image bytes and media type. PNGs are cached on the local disk by content hash.
     *
     * @return array{body: string, type: string}
     */
    public function render(ResultCard $card, string $size = 'feed', string $format = 'svg'): array
    {
        if (! isset(self::SIZES[$size]) || ! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException('Unknown card size or format.');
        }

        if ($format === 'svg') {
            return ['body' => $this->draw($card->payload, $size, $this->svgCanvas($card->payload, $size)), 'type' => 'image/svg+xml'];
        }

        $path = "result-cards/{$card->content_hash}-{$size}-v".ResultCardData::VERSION.'.png';
        $disk = Storage::disk('local');

        try {
            if ($disk->exists($path)) {
                return ['body' => $disk->get($path), 'type' => 'image/png'];
            }
        } catch (Throwable) {
            // A cache miss is fine; draw it again.
        }

        [$width, $height] = self::SIZES[$size];
        $body = $this->draw($card->payload, $size, new GdCanvas($width, $height));

        try {
            $disk->put($path, $body);
        } catch (Throwable) {
            // Caching is a nicety: the card still goes out.
        }

        return ['body' => $body, 'type' => 'image/png'];
    }

    /**
     * The SVG markup, for showing the card inline (where it picks up the page's own fonts).
     *
     * @param  array<string, mixed>  $payload
     */
    public function svg(array $payload, string $size = 'feed'): string
    {
        return $this->draw($payload, $size, $this->svgCanvas($payload, $size));
    }

    /**
     * A one-line text description of the card, for alt text and link previews.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function describe(array $payload): string
    {
        $band = $payload['band'] === 'short' ? 'Showed up' : $payload['bandLabel'];
        $match = $payload['match'] ? ', '.$payload['match']['label'].($payload['match']['photoFinish'] && ! in_array($payload['match']['outcome'], ['win', 'loss'], true) ? ' in a photo finish' : '') : '';

        return "{$payload['name']}'s {$payload['weekLabel']}: {$payload['percent']}% of plan, {$band}{$match}. {$payload['goal']}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function svgCanvas(array $payload, string $size): SvgCanvas
    {
        [$width, $height] = self::SIZES[$size];

        return new SvgCanvas($width, $height, self::describe($payload));
    }

    /**
     * Lay the card out on a canvas and return the image.
     *
     * @param  array<string, mixed>  $p
     */
    private function draw(array $p, string $size, CardCanvas $canvas): string
    {
        [$width, $height] = self::SIZES[$size];
        $story = $size === 'story';
        $gap = $story ? 1.45 : 1.0;
        $pad = 90;
        $right = $width - $pad;
        $theme = self::theme($p['band']);

        $canvas->rect(0, 0, $width, $height, $theme['bg']);

        // Header: the wordmark and the line under it, the week on the right.
        $y = ($story ? 300 : 140);
        $canvas->text($pad, $y, 'GoalLine', 64, 'extrabold', $theme['fg']);
        $canvas->text($right, $y, $p['weekLabel'].($p['final'] ? '' : ' · so far'), 34, 'bold', $theme['fg'], 'end', 0.8);
        $y += 48;
        $canvas->line($pad, $y, $right, $y, $theme['line'], 8);
        $canvas->line($pad, $y + 8, $right, $y + 8, self::GRASS, 3, 0.9);

        // The player: initials, first name, and league.
        $y += 110 * $gap;
        $canvas->circle($pad + 56, $y, 56, $theme['avatarBg']);
        $canvas->text($pad + 56, $y + 16, $p['initials'], 42, 'extrabold', $theme['avatarFg'], 'middle');
        $canvas->text($pad + 140, $y - 6, $p['name'], 52, 'bold', $theme['fg']);
        $canvas->text($pad + 140, $y + 42, $p['league'] ?? 'Against my own plan', 32, 'medium', $theme['fg'], 'start', 0.78);

        // The goal, up to three lines.
        $y += 56 + 80 * $gap;
        foreach (self::wrap($p['goal'], 54, $right - $pad, 3) as $line) {
            $canvas->text($pad, $y, $line, 54, 'bold', $theme['fg']);
            $y += 64;
        }

        // The big number: percent of plan.
        $y += 150 * $gap;
        $percent = $p['percent'].'%';
        $canvas->text($pad, $y, $percent, 200, 'extrabold', $theme['fg']);
        $canvas->text($pad + self::estimateWidth($percent, 200) * 0.97 + 28, $y - 18, 'of plan', 40, 'medium', $theme['fg'], 'start', 0.78);

        // The band chip.
        $y += 40;
        $short = $p['band'] === 'short';
        $chipLabel = $short ? 'Showed up' : $p['bandLabel'];
        $chipWidth = self::estimateWidth($chipLabel, 44) + ($p['band'] === 'smashed' ? 120 : 72);
        $canvas->rect($pad, $y, $chipWidth, 96, $theme['chipBg'], 48);
        $textX = $pad + 36;

        if ($p['band'] === 'smashed') {
            $this->diamond($canvas, $pad + 56, $y + 48, 22, self::SKY);
            $textX = $pad + 92;
        }

        $canvas->text($textX, $y + 63, $chipLabel, 44, 'bold', $theme['chipFg']);
        $y += 96;

        if ($short) {
            $canvas->text($pad, $y + 60, 'Back on the line next week.', 38, 'medium', $theme['fg'], 'start', 0.85);
            $y += 56;
        } elseif ($p['needsProof']) {
            // Beside the chip, so it never crowds the track.
            $canvas->text($pad + $chipWidth + 24, $y - 36, self::fit('Smashed pace. Proof locks it in.', 30, $right - $pad - $chipWidth - 24), 30, 'medium', $theme['fg'], 'start', 0.8);
        }

        // The bottom is pinned: the tagline, the three numbers above it, and the track above those, so a long goal
        // title never pushes anything off the card. The track follows the content when there's room.
        $footer = $height - ($story ? 200 : 60);
        $statsY = $footer - 174;
        $trackY = min($y + 90 * $gap, $statsY - 112);

        // The track: 0 to 175% of plan, ticks at the band lines, the dot at this week's percent.
        $this->track($canvas, $pad, $right, $trackY, (int) $p['percent'], $theme, $p['band']);

        // Three numbers: the match, the week's points, and weeks on the line.
        $y = min($trackY + 110 * $gap, $statsY);
        $columns = [];

        if ($p['match']) {
            $sub = match (true) {
                $p['match']['bye'] => 'Bye week',
                $p['match']['photoFinish'] && ! in_array($p['match']['outcome'], ['win', 'loss'], true) => 'Photo finish',
                default => 'Matchup',
            };
            $columns[] = ['MATCH', $p['match']['label'], $sub.' · +'.$p['match']['points']];
        } else {
            $columns[] = ['LINE POINTS', '+'.$p['linePoints'], 'Against the line'];
        }

        $columns[] = ['WEEK POINTS', (string) $p['weeklyPoints'], 'of '.($p['match'] ? 6 : 3)];
        $columns[] = ['ON THE LINE', $p['onTheLine'].' '.($p['onTheLine'] === 1 ? 'week' : 'weeks'), 'in a row'];

        $columnWidth = ($right - $pad) / 3;

        foreach ($columns as $index => [$label, $value, $sub]) {
            $x = $pad + $index * $columnWidth;
            $canvas->text($x, $y, $label, 26, 'bold', $theme['fg'], 'start', 0.7);
            $canvas->text($x, $y + 60, self::fit($value, 46, $columnWidth - 20), 46, 'extrabold', $theme['fg']);
            $canvas->text($x, $y + 104, self::fit($sub, 28, $columnWidth - 20), 28, 'medium', $theme['fg'], 'start', 0.75);
        }

        // Footer: the tagline.
        $canvas->text($pad, $footer, self::TAGLINE, 32, 'bold', $theme['fg'], 'start', 0.85);

        return $canvas->output();
    }

    /**
     * @param  array<string, string>  $theme
     */
    private function track(CardCanvas $canvas, float $left, float $right, float $y, int $percent, array $theme, string $band): void
    {
        $scale = fn (float $value) => $left + ($right - $left) * min($value, 175) / 175;

        $canvas->line($left, $y, $right, $y, $theme['fg'], 12, 0.2);
        $canvas->line($left, $y, max($left + 1, $scale($percent)), $y, $theme['accent'], 12);

        foreach ([90, 100, 150] as $mark) {
            $x = $scale($mark);
            $canvas->line($x, $y - 22, $x, $y + 22, $theme['fg'], 4, 0.55);
            $canvas->text($x, $y + 62, (string) $mark, 24, 'bold', $theme['fg'], 'middle', 0.6);
        }

        $dot = $scale($percent);
        $canvas->circle($dot, $y, 20, $theme['fg']);
        $canvas->circle($dot, $y, 10, $theme['accent']);

        if ($band === 'smashed') {
            $this->diamond($canvas, $right + 2, $y, 26, self::SKY);
        }
    }

    private function diamond(CardCanvas $canvas, float $cx, float $cy, float $radius, string $fill): void
    {
        $canvas->polygon([[$cx, $cy - $radius], [$cx + $radius, $cy], [$cx, $cy + $radius], [$cx - $radius, $cy]], $fill);
    }

    /**
     * Colors for each band.
     *
     * @return array{bg: string, fg: string, line: string, accent: string, chipBg: string, chipFg: string, avatarBg: string, avatarFg: string}
     */
    private static function theme(string $band): array
    {
        $green = ['bg' => self::CLUB, 'fg' => self::IVORY, 'line' => '#ffffff', 'avatarBg' => self::MINT, 'avatarFg' => self::CLUB];

        return match ($band) {
            'smashed' => $green + ['accent' => self::SKY, 'chipBg' => self::BLUE, 'chipFg' => '#ffffff'],
            'past' => $green + ['accent' => self::GOLD, 'chipBg' => self::GOLD, 'chipFg' => self::INK],
            'in_line' => $green + ['accent' => self::GRASS, 'chipBg' => self::GRASS, 'chipFg' => self::INK],
            default => [
                'bg' => self::IVORY, 'fg' => self::CLUB, 'line' => self::CLUB, 'accent' => self::GRASS,
                'chipBg' => self::MINT, 'chipFg' => self::CLUB, 'avatarBg' => self::CLUB, 'avatarFg' => self::IVORY,
            ],
        };
    }

    /**
     * Break text into lines that fit, adding an ellipsis if it runs past the last line.
     *
     * @return list<string>
     */
    public static function wrap(string $text, float $size, float $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (self::estimateWidth($candidate, $size) <= $maxWidth || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        $lines = array_map(fn (string $line) => self::fit($line, $size, $maxWidth), $lines);

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = self::fit($lines[$maxLines - 1].'…', $size, $maxWidth, force: true);
        }

        return $lines;
    }

    /**
     * Shorten one line with an ellipsis until it fits.
     */
    public static function fit(string $text, float $size, float $maxWidth, bool $force = false): string
    {
        if (! $force && self::estimateWidth($text, $size) <= $maxWidth) {
            return $text;
        }

        $base = rtrim(mb_substr($text, 0, mb_strlen($text) - ($force ? 1 : 0)), ' …');

        while ($base !== '' && self::estimateWidth($base.'…', $size) > $maxWidth) {
            $base = rtrim(mb_substr($base, 0, -1));
        }

        return $base.'…';
    }

    /**
     * A careful estimate of how wide text is in Plus Jakarta Sans Bold, so the SVG and PNG wrap the same way. It
     * leans wide, so text never runs off the card.
     */
    public static function estimateWidth(string $text, float $size): float
    {
        $width = 0.0;

        foreach (mb_str_split($text) as $character) {
            $width += match (true) {
                $character === ' ' => 0.28,
                str_contains('iljI.,:;!|\'’', $character) => 0.3,
                str_contains('ftr()', $character) => 0.42,
                str_contains('mwMW%', $character) => 0.92,
                ctype_digit($character) => 0.64,
                ctype_upper($character) => 0.72,
                default => 0.6,
            };
        }

        return $width * $size;
    }
}
