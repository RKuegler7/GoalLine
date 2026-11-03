<?php

namespace App\Services;

use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;

/**
 * A league week's matchups, ready to show: each side's share of their plan, band, points to the next band, and the
 * points they'd take if the week ended now, plus a plain-English verdict on who's ahead.
 */
class MatchupBoard
{
    /**
     * The top of the line meter, as a share of the plan. Anything past it is pinned to the end.
     */
    public const METER_MAX = 1.6;

    public function __construct(public LeagueTable $table, public int $week) {}

    /**
     * Every matchup this week, the viewer's first.
     *
     * @return list<array{home: array<string, mixed>, away: array<string, mixed>|null, median: float|null, verdict: string, mine: bool}>
     */
    public function cards(?LeagueMember $viewer): array
    {
        $final = $this->table->isFinal($this->week);

        return collect($this->table->matchups($this->week))
            ->map(function (array $matchup) use ($viewer, $final) {
                $home = self::side($matchup['home'], $matchup['homeResult'], $matchup['homeOutcome']);
                $away = $matchup['away'] ? self::side($matchup['away'], $matchup['awayResult'], $matchup['awayOutcome']) : null;

                return [
                    'home' => $home,
                    'away' => $away,
                    'median' => $matchup['median'],
                    'verdict' => self::verdict($home, $away, $matchup['median'], $final),
                    'mine' => $viewer !== null && ($matchup['home']->is($viewer) || $matchup['away']?->is($viewer)),
                    'matchup' => $matchup,
                ];
            })
            ->sortByDesc('mine')
            ->values()
            ->all();
    }

    /**
     * One side of a matchup.
     *
     * @param  array{result: GoalWeekResult|null, points: int, final: bool, goalWeek: RoadmapWeek|null}  $result
     * @return array<string, mixed>
     */
    public static function side(LeagueMember $member, array $result, MatchupOutcome $outcome): array
    {
        $goalWeek = $result['goalWeek'];

        return [
            'member' => $member,
            'result' => $result['result'],
            'goalWeek' => $goalWeek,
            'share' => $goalWeek?->completionShare(),
            'earned' => $goalWeek ? round($goalWeek->earnedPoints(), 1) : 0,
            'planned' => $goalWeek?->plannedPoints() ?? 0,
            'next' => $goalWeek ? self::nextBand($goalWeek) : null,
            'notStarted' => $goalWeek?->isNotStarted() ?? false,
            'needsProof' => $goalWeek?->needsProofForSmashed() ?? false,
            'held' => $goalWeek ? round($goalWeek->heldPoints(), 1) : 0,
            'linePoints' => $result['points'],
            'outcome' => $outcome,
            'matchPoints' => $outcome->points(),
            'total' => $result['points'] + $outcome->points(),
        ];
    }

    /**
     * The next band up and the points it takes to get there, or null at Smashed.
     *
     * @return array{band: GoalWeekResult, points: int}|null
     */
    public static function nextBand(RoadmapWeek $week): ?array
    {
        $share = round($week->completionShare(), 4);

        return match (true) {
            $share < GoalWeekResult::IN_LINE_AT => ['band' => GoalWeekResult::InLine, 'points' => $week->pointsTo(GoalWeekResult::IN_LINE_AT)],
            // Past the line means more than the plan: one point beyond 100%.
            $share <= GoalWeekResult::PAST_ABOVE => ['band' => GoalWeekResult::Past, 'points' => max(1, (int) floor($week->plannedPoints() - $week->earnedPoints() + 0.0001) + 1)],
            $share < GoalWeekResult::SMASHED_AT => ['band' => GoalWeekResult::Smashed, 'points' => $week->pointsTo(GoalWeekResult::SMASHED_AT)],
            default => null,
        };
    }

    /**
     * Where a share sits on the line meter, from 0 to 100 percent of its width.
     */
    public static function meterPosition(?float $share): float
    {
        return round(min(max($share ?? 0, 0), self::METER_MAX) / self::METER_MAX * 100, 1);
    }

    /**
     * "Red leads: Past the line beats In line." or "Level: both In line."
     *
     * @param  array<string, mixed>  $home
     * @param  array<string, mixed>|null  $away
     */
    public static function verdict(array $home, ?array $away, ?float $median, bool $final): string
    {
        $name = fn (array $side) => $side['member']->user->name;
        $band = fn (array $side) => $side['result']?->label() ?? 'no GoalWeek';

        if ($away === null) {
            $against = 'the league median ('.($median === null ? 'no one else yet' : round($median, 1).' line '.($median == 1 ? 'point' : 'points')).')';

            return match ($home['outcome']) {
                MatchupOutcome::Win => $name($home).($final ? ' beat ' : ' is ahead of ').$against.'.',
                MatchupOutcome::Tie => $name($home).($final ? ' drew with ' : ' is level with ').$against.'.',
                MatchupOutcome::Loss => $name($home).($final ? ' fell short of ' : ' is behind ').$against.'.',
            };
        }

        if ($home['outcome'] === MatchupOutcome::Tie) {
            return ($final ? 'A draw: both ' : 'Level: both ').$band($home).'.';
        }

        [$leader, $trailer] = $home['outcome'] === MatchupOutcome::Win ? [$home, $away] : [$away, $home];

        return $name($leader).($final ? ' won: ' : ' leads: ').$band($leader).' beats '.$band($trailer).'.';
    }
}
