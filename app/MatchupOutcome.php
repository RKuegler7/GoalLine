<?php

namespace App;

/**
 * How a GoalWeek matchup went for one side. The higher band wins outright (3–0). In the same band it's a photo finish:
 * the higher credited percent of plan takes a close win (2–1), and an exact tie is worth 1 each.
 */
enum MatchupOutcome: string
{
    case Win = 'win';
    case CloseWin = 'close_win';
    case Tie = 'tie';
    case CloseLoss = 'close_loss';
    case Loss = 'loss';

    /**
     * Compare two sides: first by line points (the band), then, in the same band, by credited percent of plan.
     * The league median is compared the same way, with the median line points and the median credited percent.
     */
    public static function compare(float $myLinePoints, float $theirLinePoints, float $myPercent, float $theirPercent): self
    {
        if ($myLinePoints != $theirLinePoints) {
            return $myLinePoints > $theirLinePoints ? self::Win : self::Loss;
        }

        return match (true) {
            $myPercent > $theirPercent => self::CloseWin,
            $myPercent < $theirPercent => self::CloseLoss,
            default => self::Tie,
        };
    }

    /**
     * Match points: 3 for a win, 2 for a close win, 1 for a tie or a close loss, 0 for a loss.
     * With line points (0–3), a week is worth 0–6.
     */
    public function points(): int
    {
        return match ($this) {
            self::Win => 3,
            self::CloseWin => 2,
            self::Tie, self::CloseLoss => 1,
            self::Loss => 0,
        };
    }

    /**
     * Whether it counts as a win in a W–T–L record.
     */
    public function isWin(): bool
    {
        return $this === self::Win || $this === self::CloseWin;
    }

    /**
     * Whether it counts as a loss in a W–T–L record.
     */
    public function isLoss(): bool
    {
        return $this === self::Loss || $this === self::CloseLoss;
    }

    /**
     * Whether both sides finished in the same band, so credited percent decided it.
     */
    public function isPhotoFinish(): bool
    {
        return ! in_array($this, [self::Win, self::Loss], true);
    }

    /**
     * The label shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Win => 'Win',
            self::CloseWin => 'Close win',
            self::Tie => 'Tie',
            self::CloseLoss => 'Close loss',
            self::Loss => 'Loss',
        };
    }

    /**
     * One-letter form, as in a W–T–L record.
     */
    public function letter(): string
    {
        return match (true) {
            $this->isWin() => 'W',
            $this->isLoss() => 'L',
            default => 'T',
        };
    }
}
