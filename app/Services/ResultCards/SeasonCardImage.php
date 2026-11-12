<?php

namespace App\Services\ResultCards;

use App\Models\GoalResult;
use App\Services\MedalAwarder;
use App\Services\SeasonFinale;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * "Share your season": one card for a whole goal, drawn by code from the finale's numbers (feed 1080×1350, story
 * 1080×1920). An achieved goal gets the gold card. Anything else reads "Season complete" with the weeks shown up for:
 * the card never says a goal was missed.
 */
class SeasonCardImage
{
    private const CLUB = '#176b45';

    private const GRASS = '#43b96a';

    private const IVORY = '#fafaf5';

    private const INK = '#1e2922';

    private const GOLD = '#d4ac58';

    private const TAGLINE = 'Get in line with your goals.';

    /**
     * What the card shows, from the finale. Only first name, goal, outcome and numbers: never notes or proof.
     *
     * @return array{name: string, initials: string, goal: string, achieved: bool, verified: bool, outcome: string, weeks: int, stars: int, rank: string|null, league: string|null, medal: string|null}
     */
    public static function payload(SeasonFinale $finale): array
    {
        $user = $finale->goal->user;
        $finish = $finale->finish();
        $stats = $finale->stats();
        $result = $finale->result;
        $medal = MedalAwarder::best($finale->medals());

        return [
            'name' => Str::before($user->name, ' '),
            'initials' => Str::upper(collect(explode(' ', $user->name))->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join('')),
            'goal' => $finale->goal->title,
            'achieved' => $finale->achieved(),
            'verified' => (bool) $result?->isVerified(),
            'outcome' => $finale->achieved() ? 'Goal achieved' : 'Season complete',
            'weeks' => $stats['weeks'],
            'stars' => $stats['totalStars'],
            'rank' => $finish ? self::ordinal($finish['rank']).' of '.$finish['size'] : null,
            'league' => $finale->league?->name,
            'medal' => $medal?->title(),
        ];
    }

    /**
     * Alt text and link-preview description.
     *
     * @param  array<string, mixed>  $p
     */
    public static function describe(array $p): string
    {
        return "{$p['name']}'s season: {$p['goal']}. {$p['outcome']}".($p['verified'] ? ' (Verified)' : '')
            .", {$p['stars']} stars over {$p['weeks']} ".Str::plural('week', $p['weeks'])
            .($p['rank'] ? ", finished {$p['rank']}" : '').'.';
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{body: string, type: string}
     */
    public function render(array $p, string $size = 'feed', string $format = 'svg'): array
    {
        if (! isset(ResultCardImage::SIZES[$size]) || ! in_array($format, ResultCardImage::FORMATS, true)) {
            throw new InvalidArgumentException('Unknown card size or format.');
        }

        [$width, $height] = ResultCardImage::SIZES[$size];
        $canvas = $format === 'png' ? new GdCanvas($width, $height) : new SvgCanvas($width, $height, self::describe($p));

        return ['body' => $this->draw($p, $size, $canvas), 'type' => $format === 'png' ? 'image/png' : 'image/svg+xml'];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function draw(array $p, string $size, CardCanvas $canvas): string
    {
        [$width, $height] = ResultCardImage::SIZES[$size];
        $story = $size === 'story';
        $gap = $story ? 1.45 : 1.0;
        $pad = 90;
        $right = $width - $pad;
        $bg = $p['achieved'] ? self::GOLD : self::CLUB;
        $fg = $p['achieved'] ? self::INK : self::IVORY;

        $canvas->rect(0, 0, $width, $height, $bg);

        for ($x = 0; $x < $width; $x += 180) {
            $canvas->rect($x, 0, 90, $height, '#ffffff', 0, 0.06);
        }

        // A burst behind the outcome for an achieved goal.
        $burstY = $story ? 820 : 640;
        if ($p['achieved']) {
            foreach (range(0, 15) as $ray) {
                $angle = $ray * M_PI / 8;
                $canvas->polygon([
                    [$width / 2, $burstY],
                    [$width / 2 + cos($angle - 0.08) * 900, $burstY + sin($angle - 0.08) * 900],
                    [$width / 2 + cos($angle + 0.08) * 900, $burstY + sin($angle + 0.08) * 900],
                ], '#ffffff', 0.12);
            }
        }

        $y = $story ? 300 : 140;
        $canvas->text($pad, $y, 'GoalLine', 64, 'extrabold', $fg);
        $canvas->text($right, $y, 'Season finale', 34, 'bold', $fg, 'end', 0.8);
        $y += 48;
        $canvas->line($pad, $y, $right, $y, $fg, 8, 0.9);
        $canvas->line($pad, $y + 8, $right, $y + 8, self::GRASS, 3, 0.9);

        $y += 110 * $gap;
        $canvas->circle($pad + 56, $y, 56, $fg);
        $canvas->text($pad + 56, $y + 16, $p['initials'], 42, 'extrabold', $bg, 'middle');
        $canvas->text($pad + 140, $y - 6, $p['name'], 52, 'bold', $fg);
        $canvas->text($pad + 140, $y + 42, ResultCardImage::fit($p['league'] ?? 'Against my own plan', 32, $right - $pad - 140), 32, 'medium', $fg, 'start', 0.8);

        $y += 56 + 80 * $gap;
        foreach (ResultCardImage::wrap($p['goal'], 54, $right - $pad, 3) as $line) {
            $canvas->text($pad, $y, $line, 54, 'bold', $fg);
            $y += 64;
        }

        // The outcome, big.
        $y = max($y + 120 * $gap, $burstY + 40);
        $canvas->text($pad, $y, $p['outcome'], 108, 'extrabold', $fg);

        if ($p['verified']) {
            $y += 40;
            $label = 'Verified';
            $chip = ResultCardImage::estimateWidth($label, 40) + 72;
            $canvas->rect($pad, $y, $chip, 84, $fg, 42);
            $canvas->text($pad + 36, $y + 56, $label, 40, 'bold', $bg);
            $y += 84;
        } elseif (! $p['achieved']) {
            $canvas->text($pad, $y + 70, 'Showed up for '.$p['weeks'].' '.Str::plural('week', $p['weeks']).'.', 40, 'medium', $fg, 'start', 0.9);
        }

        // Three numbers pinned above the footer: total stars, table finish, best medal.
        $footer = $height - ($story ? 200 : 60);
        $statsY = $footer - 174;
        $columns = [
            ['TOTAL STARS', (string) $p['stars'], $p['weeks'].' '.Str::plural('week', $p['weeks'])],
            ['FINISH', $p['rank'] ?? 'Solo', $p['rank'] ? 'in the table' : 'against my plan'],
            ['BEST MEDAL', $p['medal'] ? Str::before($p['medal'], ' · ') : '—', $p['medal'] ? (Str::after($p['medal'], ' · ') !== $p['medal'] ? Str::after($p['medal'], ' · ') : 'Season medal') : 'Next season'],
        ];
        $columnWidth = ($right - $pad) / 3;

        foreach ($columns as $index => [$label, $value, $sub]) {
            $x = $pad + $index * $columnWidth;
            $canvas->text($x, $statsY, $label, 26, 'bold', $fg, 'start', 0.75);
            $canvas->text($x, $statsY + 60, ResultCardImage::fit($value, 46, $columnWidth - 20), 46, 'extrabold', $fg);
            $canvas->text($x, $statsY + 104, ResultCardImage::fit($sub, 28, $columnWidth - 20), 28, 'medium', $fg, 'start', 0.8);
        }

        $canvas->text($pad, $footer, self::TAGLINE, 32, 'bold', $fg, 'start', 0.9);

        return $canvas->output();
    }

    private static function ordinal(int $number): string
    {
        return $number.match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };
    }

    /**
     * Whether a Goal Day result's public season link is on.
     */
    public static function isPublic(?GoalResult $result): bool
    {
        return $result !== null && $result->share_token !== null && $result->public_at !== null && $result->revoked_at === null;
    }
}
