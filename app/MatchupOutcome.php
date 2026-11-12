<?php

namespace App;

/**
 * How a GoalWeek matchup went for one side. A higher band wins outright. In the same band it's a photo finish: the
 * higher credited percent of plan (compared at full precision) takes it, and exactly equal percents are a tie. A bye,
 * or a solo week against the line, has no opponent: line stars only, and no win, draw or loss.
 *
 * Matchup stars come from config('goalline.stars.matchup'). With line stars, the player with more total stars wins
 * the matchup, and equal total stars is a draw; that always agrees with these outcomes (see the tests).
 *
 * Stored values stay as they were ('close_win', 'close_loss') so past results keep their meaning.
 */
enum MatchupOutcome: string
{
    case Win = 'win';
    case CloseWin = 'close_win';
    case Tie = 'tie';
    case CloseLoss = 'close_loss';
    case Loss = 'loss';
    case Bye = 'bye';

    /**
     * Compare two sides: first by line stars (the band), then, in the same band, by credited percent of plan.
     */
    public static function compare(float $myLineStars, float $theirLineStars, float $myPercent, float $theirPercent): self
    {
        if ($myLineStars != $theirLineStars) {
            return $myLineStars > $theirLineStars ? self::Win : self::Loss;
        }

        // Credited percents carry hundredths of a percent; compare them exactly at that precision.
        $mine = (int) round($myPercent * 100);
        $theirs = (int) round($theirPercent * 100);

        return match (true) {
            $mine > $theirs => self::CloseWin,
            $mine < $theirs => self::CloseLoss,
            default => self::Tie,
        };
    }

    /**
     * Matchup stars, from config('goalline.stars.matchup').
     */
    public function points(): int
    {
        return (int) config('goalline.stars.matchup.'.match ($this) {
            self::Win => 'win',
            self::CloseWin => 'photo_finish_win',
            self::Tie => 'tie',
            self::CloseLoss, self::Loss => 'loss',
            self::Bye => 'bye',
        });
    }

    /**
     * A W in the record: a win or a photo finish win.
     */
    public function isWin(): bool
    {
        return $this === self::Win || $this === self::CloseWin;
    }

    /**
     * An L in the record: any loss.
     */
    public function isLoss(): bool
    {
        return $this === self::Loss || $this === self::CloseLoss;
    }

    /**
     * A D in the record: equal total stars.
     */
    public function isDraw(): bool
    {
        return $this === self::Tie;
    }

    /**
     * Whether the week counts in the W–D–L record at all (byes and solo weeks don't).
     */
    public function counts(): bool
    {
        return $this !== self::Bye;
    }

    /**
     * Whether both sides finished in the same band, so credited percent decided it.
     */
    public function isPhotoFinish(): bool
    {
        return in_array($this, [self::CloseWin, self::Tie, self::CloseLoss], true);
    }

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Win => 'Win',
            self::CloseWin => 'Photo finish win',
            self::Tie => 'Tie',
            self::CloseLoss, self::Loss => 'Loss',
            self::Bye => 'Bye',
        };
    }

    /**
     * One-letter form, as in a W–D–L record. Empty for a bye.
     */
    public function letter(): string
    {
        return match (true) {
            $this->isWin() => 'W',
            $this->isLoss() => 'L',
            $this->isDraw() => 'D',
            default => '',
        };
    }

    /**
     * A friendly line for a tie.
     */
    public static function tieEncouragement(): string
    {
        return 'You both did the whole plan. Go past the line to pull ahead.';
    }
}
