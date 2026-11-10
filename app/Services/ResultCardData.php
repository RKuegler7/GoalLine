<?php

namespace App\Services;

use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;
use Illuminate\Support\Str;

/**
 * What a weekly result card shows, worked out by the scoring code (never by the AI): the GoalWeek's band and credited
 * percent, its line points, the GoalLeague matchup result if the goal is in a league, and the run of weeks on the
 * line. Only the owner's own summary: no email, notes, proof photos or opponent names.
 */
class ResultCardData
{
    public const VERSION = 1;

    /**
     * @return array{v: int, name: string, initials: string, weekLabel: string, goal: string, percent: int, band: string, bandLabel: string, needsProof: bool, linePoints: int, final: bool, match: array{outcome: string, label: string, points: int, photoFinish: bool, bye: bool}|null, weeklyPoints: int, onTheLine: int, league: string|null}
     */
    public static function for(RoadmapWeek $week): array
    {
        $week->loadMissing('goal.user', 'goal.weeks.tasks', 'goal.habits.logs', 'tasks');
        $goal = $week->goal;
        $user = $goal->user;
        $band = $week->result() ?? GoalWeekResult::Short;
        [$league, $match, $leagueWeek] = self::matchup($week);
        $linePoints = $band->points();

        return [
            'v' => self::VERSION,
            'name' => Str::before(trim($user->name), ' ') ?: $user->username,
            'initials' => self::initials($user->name),
            'weekLabel' => $leagueWeek !== null ? "Week {$leagueWeek}" : $week->label(),
            'goal' => $goal->title,
            'percent' => $week->creditedPercent(),
            'band' => $band->value,
            'bandLabel' => $band->label(),
            'needsProof' => $week->needsProofForSmashed(),
            'linePoints' => $linePoints,
            'final' => $week->isLocked(),
            'match' => $match,
            'weeklyPoints' => $linePoints + ($match['points'] ?? 0),
            'onTheLine' => self::weeksOnTheLine($week),
            'league' => $league?->name,
        ];
    }

    /**
     * The goal's GoalLeague matchup for the league week holding this GoalWeek's Sunday, from the league's own table.
     *
     * @return array{0: League|null, 1: array{outcome: string, label: string, points: int, photoFinish: bool, bye: bool}|null, 2: int|null}
     */
    private static function matchup(RoadmapWeek $week): array
    {
        $member = self::membershipFor($week);

        if ($member === null) {
            return [null, null, null];
        }

        $league = $member->league;
        $leagueWeek = $league->weekNumberOn($week->ends_on);
        $row = collect((new LeagueTable($league))->matchups($leagueWeek))
            ->first(fn (array $row) => $row['home']->is($member) || $row['away']?->is($member));

        if ($row === null) {
            return [$league, null, $leagueWeek];
        }

        /** @var MatchupOutcome $outcome */
        $outcome = $row['home']->is($member) ? $row['homeOutcome'] : $row['awayOutcome'];

        return [$league, [
            'outcome' => $outcome->value,
            'label' => $outcome->label(),
            'points' => $outcome->points(),
            'photoFinish' => $outcome->isPhotoFinish(),
            'bye' => $row['away'] === null,
        ], $leagueWeek];
    }

    /**
     * The GoalLeague membership this GoalWeek plays in: the goal's league whose season includes the week's Sunday.
     */
    public static function membershipFor(RoadmapWeek $week): ?LeagueMember
    {
        return LeagueMember::query()->where('goal_id', $week->goal_id)->with('league.members.goal.weeks.tasks', 'league.members.goal.habits.logs')->latest('id')->get()
            ->first(fn (LeagueMember $member) => $member->league->weekNumberOn($week->ends_on) !== null);
    }

    /**
     * GoalWeeks in a row on the line (In line or better) for this goal, up to and including this one.
     */
    private static function weeksOnTheLine(RoadmapWeek $week): int
    {
        $run = 0;

        foreach ($week->goal->weeks->where('position', '<=', $week->position)->sortByDesc('position') as $earlier) {
            if (! $earlier->isStarted() || $earlier->result() === GoalWeekResult::Short) {
                break;
            }

            $run++;
        }

        return $run;
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last) ?: 'G';
    }
}
