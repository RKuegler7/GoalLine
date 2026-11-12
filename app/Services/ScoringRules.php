<?php

namespace App\Services;

use App\Badge;
use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\RoadmapWeek;

/**
 * The scoring rules in a form a page can show, read from the same code that scores every GoalWeek: the band
 * thresholds in GoalWeekResult, the star values in config('goalline.stars') (read by GoalWeekResult and
 * MatchupOutcome), and the milestone's points in RoadmapWeek. The worked example is run through the real scoring functions, so the About page can't drift from
 * the code.
 */
class ScoringRules
{
    /**
     * The band thresholds, as shares of the plan (1.0 is exactly the plan).
     *
     * @return array{in_line: float, past_above: float, smashed: float, safely_in_line: float}
     */
    protected function thresholds(): array
    {
        return [
            'in_line' => GoalWeekResult::IN_LINE_AT,
            'past_above' => GoalWeekResult::PAST_ABOVE,
            'smashed' => GoalWeekResult::SMASHED_AT,
            'safely_in_line' => GoalWeekResult::SAFELY_IN_LINE_AT,
        ];
    }

    protected function linePoints(GoalWeekResult $result): int
    {
        return $result->points();
    }

    protected function matchPoints(MatchupOutcome $outcome): int
    {
        return $outcome->points();
    }

    public function milestonePoints(): int
    {
        return RoadmapWeek::MILESTONE_POINTS;
    }

    /**
     * The four weekly results, lowest first, with their ranges and line points.
     *
     * @return list<array{result: GoalWeekResult, label: string, from: int, to: int|null, range: string, points: int}>
     */
    public function bands(): array
    {
        $inLine = $this->percent('in_line');
        $past = $this->percent('past_above');
        $smashed = $this->percent('smashed');

        return [
            $this->band(GoalWeekResult::Short, 0, $inLine, "under {$inLine}%"),
            $this->band(GoalWeekResult::InLine, $inLine, $past, "{$inLine}% to {$past}%"),
            $this->band(GoalWeekResult::Past, $past, $smashed, "over {$past}%, under {$smashed}%"),
            $this->band(GoalWeekResult::Smashed, $smashed, null, "{$smashed}% and up"),
        ];
    }

    /**
     * The bands as segments of a meter from 0% to a little past Smashed, for the scoring picture.
     *
     * @return list<array{result: GoalWeekResult, label: string, width: float, marker: int|null}>
     */
    public function meter(): array
    {
        $end = $this->percent('smashed') + 50;

        return array_map(fn (array $band) => [
            'result' => $band['result'],
            'label' => $band['label'],
            'width' => round(((($band['to'] ?? $end) - $band['from']) / $end) * 100, 2),
            'marker' => $band['from'] > 0 ? $band['from'] : null,
        ], $this->bands());
    }

    /**
     * An In line week under this percent shows as a close call.
     */
    public function closeCallBelow(): int
    {
        return $this->percent('safely_in_line');
    }

    /**
     * An unverified Smashed week is credited as Past the line, so its percent is capped here.
     */
    public function unverifiedSmashedCap(): int
    {
        return $this->percent('smashed') - 1;
    }

    /**
     * The matchup outcomes and their matchup stars, best first. Any loss is 0; a tie gives both players its stars.
     *
     * @return list<array{outcome: MatchupOutcome, label: string, points: int, when: string}>
     */
    public function outcomes(): array
    {
        $when = [
            MatchupOutcome::Win->value => 'Your band is higher than theirs',
            MatchupOutcome::CloseWin->value => 'Same band, your credited percent is higher (photo finish)',
            MatchupOutcome::Tie->value => 'Same band, exactly the same credited percent: both players get it',
            MatchupOutcome::Loss->value => 'Any loss',
        ];

        return array_map(fn (MatchupOutcome $outcome) => [
            'outcome' => $outcome,
            'label' => $outcome->label(),
            'points' => $this->matchPoints($outcome),
            'when' => $when[$outcome->value],
        ], [MatchupOutcome::Win, MatchupOutcome::CloseWin, MatchupOutcome::Tie, MatchupOutcome::Loss]);
    }

    /**
     * The fewest and most stars a GoalWeek can be worth in a league: line stars plus matchup stars.
     *
     * @return array{min: int, max: int}
     */
    public function weekRange(): array
    {
        $line = array_column($this->bands(), 'points');
        $match = array_column($this->outcomes(), 'points');

        return ['min' => min($line) + min($match), 'max' => max($line) + max($match)];
    }

    /**
     * How the league table is ordered (see LeagueTable::standings()).
     *
     * @return list<string>
     */
    public function tableOrder(): array
    {
        return ['Total stars (line stars plus matchup stars)', 'Then line stars', 'Then Past-the-Line weeks (Past the line or Smashed)'];
    }

    /**
     * The season awards, from the badges themselves.
     *
     * @return list<array{name: string, description: string}>
     */
    public function seasonAwards(): array
    {
        return array_map(fn (Badge $badge) => ['name' => $badge->label(), 'description' => $badge->description()], Badge::leagueAwards());
    }

    /**
     * One GoalWeek for two players, scored by the real functions: a photo finish, then what a higher band would do.
     *
     * @return array{players: list<array<string, mixed>>, photoFinish: bool, whatIf: array<string, mixed>}
     */
    public function example(): array
    {
        $avery = $this->player('Avery', tasks: 5, habitDays: 5, tasksDone: 4, habitDaysDone: 5, bonusDone: 0);
        $sam = $this->player('Sam', tasks: 10, habitDays: 8, tasksDone: 9, habitDaysDone: 8, bonusDone: 0);
        [$avery, $sam] = $this->match($avery, $sam);

        $averyBonus = $this->player('Avery', tasks: 5, habitDays: 5, tasksDone: 5, habitDaysDone: 5, bonusDone: 1);
        [$averyBonus, $samAgain] = $this->match($averyBonus, $this->player('Sam', tasks: 10, habitDays: 8, tasksDone: 9, habitDaysDone: 8, bonusDone: 0));

        return [
            'players' => [$avery, $sam],
            'photoFinish' => $avery['outcome']->isPhotoFinish(),
            'whatIf' => ['avery' => $averyBonus, 'sam' => $samAgain],
        ];
    }

    /**
     * @return array{result: GoalWeekResult, label: string, from: int, to: int|null, range: string, points: int}
     */
    private function band(GoalWeekResult $result, int $from, ?int $to, string $range): array
    {
        return ['result' => $result, 'label' => $result->label(), 'from' => $from, 'to' => $to, 'range' => $range, 'points' => $this->linePoints($result)];
    }

    private function percent(string $threshold): int
    {
        return (int) round($this->thresholds()[$threshold] * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function player(string $name, int $tasks, int $habitDays, int $tasksDone, int $habitDaysDone, int $bonusDone): array
    {
        $milestone = $this->milestonePoints();
        $planned = $tasks + $habitDays + $milestone;
        $earned = $tasksDone + $bonusDone + $habitDaysDone + $milestone;
        $share = $earned / $planned;
        $result = GoalWeekResult::score($share);

        return [
            'name' => $name,
            'tasks' => $tasks,
            'habitDays' => $habitDays,
            'tasksDone' => $tasksDone,
            'habitDaysDone' => $habitDaysDone,
            'bonusDone' => $bonusDone,
            'milestone' => $milestone,
            'planned' => $planned,
            'earned' => $earned,
            'percent' => GoalWeekResult::creditedPercent($share),
            'precise' => GoalWeekResult::preciseCreditedPercent($share),
            'result' => $result,
            'closeCall' => $result === GoalWeekResult::InLine && GoalWeekResult::creditedPercent($share) < $this->closeCallBelow(),
            'linePoints' => $this->linePoints($result),
        ];
    }

    /**
     * @param  array<string, mixed>  $mine
     * @param  array<string, mixed>  $theirs
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function match(array $mine, array $theirs): array
    {
        return [$this->against($mine, $theirs), $this->against($theirs, $mine)];
    }

    /**
     * @param  array<string, mixed>  $side
     * @param  array<string, mixed>  $other
     * @return array<string, mixed>
     */
    private function against(array $side, array $other): array
    {
        $side['outcome'] = MatchupOutcome::compare($side['linePoints'], $other['linePoints'], $side['precise'], $other['precise']);
        $side['matchPoints'] = $this->matchPoints($side['outcome']);
        $side['total'] = $side['linePoints'] + $side['matchPoints'];

        return $side;
    }
}
