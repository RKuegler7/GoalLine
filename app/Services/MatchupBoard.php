<?php

namespace App\Services;

use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;
use App\Percent;

/**
 * A league week's matchups, ready to show: each side's share of their plan, band, points to the next band, and the
 * stars they'd take if the week ended now, plus a plain-English verdict on who's ahead.
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

                // Whole percents, with decimals only when they'd otherwise look equal.
                if ($away && $home['percent'] !== null && $away['percent'] !== null) {
                    [$home['percentLabel'], $away['percentLabel']] = Percent::pair($home['precise'], $away['precise']);
                }

                return [
                    'home' => $home,
                    'away' => $away,
                    'median' => $matchup['median'],
                    'verdict' => self::verdict($home, $away, $matchup['median'], $final, $matchup['medianPercent']),
                    'medianPercent' => $matchup['medianPercent'],
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
            'percent' => $goalWeek ? $result['percent'] : null,
            'precise' => $goalWeek ? $result['precise'] : null,
            'percentLabel' => $goalWeek ? Percent::whole($result['precise']) : null,
            'earned' => $goalWeek ? round($goalWeek->earnedPoints(), 1) : 0,
            'planned' => $goalWeek?->plannedPoints() ?? 0,
            'next' => $goalWeek ? self::nextBand($goalWeek) : null,
            'targets' => $goalWeek ? self::targets($goalWeek) : null,
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
     * Plain-language targets for one player's week, from the same scoring rules as nextBand(): points still needed
     * for In line and for Past the line (0 once there), each as "about N tasks" from the points of the tasks still
     * open, plus how many planned tasks are done and left.
     *
     * The task estimate never promises more tasks than are left: when the open tasks can't cover it alone, "more" says
     * habit days or the milestone are part of the way there.
     *
     * @return array{inLine: array{points: int, tasks: int, more: bool}, past: array{points: int, tasks: int, more: bool}, done: int, left: int}
     */
    public static function targets(RoadmapWeek $week): array
    {
        $share = round($week->completionShare(), 4);
        $planned = $week->tasks->reject->is_bonus;
        $open = $planned->whereNull('completed_at');
        $openPoints = (float) $open->sum('points');
        $perTask = $open->isEmpty() ? 1.0 : max(0.5, $openPoints / $open->count());
        $inLine = $share >= GoalWeekResult::IN_LINE_AT ? 0 : $week->pointsTo(GoalWeekResult::IN_LINE_AT);
        // Past the line means more than the plan: one point beyond 100%, as in nextBand().
        $past = $share > GoalWeekResult::PAST_ABOVE ? 0 : max(1, (int) floor($week->plannedPoints() - $week->earnedPoints() + 0.0001) + 1);
        $estimate = fn (int $points) => [
            'points' => $points,
            'tasks' => (int) min($open->count(), ceil($points / $perTask)),
            'more' => $points > $openPoints,
        ];

        return [
            'inLine' => $estimate($inLine),
            'past' => $estimate($past),
            'done' => $planned->whereNotNull('completed_at')->count(),
            'left' => $open->count(),
        ];
    }

    /**
     * Where a share sits on the line meter, from 0 to 100 percent of its width.
     */
    public static function meterPosition(?float $share): float
    {
        return round(min(max($share ?? 0, 0), self::METER_MAX) / self::METER_MAX * 100, 1);
    }

    /**
     * "Red leads: Past the line beats In line.", or, in the same band, "Photo finish: both In line. Red leads, 98% to
     * 94%.", or a tie with a word of encouragement. A bye: line stars only.
     *
     * @param  array<string, mixed>  $home
     * @param  array<string, mixed>|null  $away
     */
    public static function verdict(array $home, ?array $away, ?float $median, bool $final, ?float $medianPercent = null): string
    {
        $name = fn (array $side) => $side['member']->user->name;
        $band = fn (array $side) => $side['result']?->label() ?? 'Short of the line';
        $outcome = $home['outcome'];

        if ($away === null) {
            return $name($home).' has a bye this week: line stars only, and it doesn\'t count as a win, draw or loss.';
        }

        if (! $outcome->isPhotoFinish()) {
            [$leader, $trailer] = $outcome === MatchupOutcome::Win ? [$home, $away] : [$away, $home];

            return $name($leader).($final ? ' won: ' : ' leads: ').$band($leader).' beats '.$band($trailer).'.';
        }

        $photo = 'Photo finish: both '.$band($home).'. ';

        if ($outcome === MatchupOutcome::Tie) {
            $whole = ($home['precise'] ?? 0) >= 100;

            return $photo.($final ? 'Tie' : 'Tied').' at '.($home['percentLabel'] ?? Percent::whole($home['precise'] ?? 0)).'. '
                .($whole ? MatchupOutcome::tieEncouragement() : 'Dead level. One more check-in pulls ahead.');
        }

        [$leader, $trailer] = $outcome === MatchupOutcome::CloseWin ? [$home, $away] : [$away, $home];

        return $photo.$name($leader).($final ? ' takes the photo finish, ' : ' leads, ').$leader['percentLabel'].' to '.$trailer['percentLabel'].'.';
    }
}
