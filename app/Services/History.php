<?php

namespace App\Services;

use App\Clock;
use App\GoalStatus;
use App\GoalWeekResult;
use App\LeagueStatus;
use App\MatchupOutcome;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueResult;
use App\Models\RoadmapWeek;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A member's history, newest first: finished GoalLeagues with their final place, goals that are done, retired or past
 * their date, and finished GoalWeeks with their band, percent of plan and match result. Every number comes from the
 * scoring and league code; this only gathers them.
 */
class History
{
    /**
     * How many past GoalWeeks the History tab lists.
     */
    public const WEEKS_SHOWN = 24;

    public function __construct(public User $user) {}

    /**
     * Finished GoalLeagues: name, dates, final place, the goal played and whether it was achieved.
     *
     * @return Collection<int, array{league: League, rank: int, ordinal: string, size: int, goal: Goal|null, achieved: bool, record: array{wins: int, ties: int, losses: int}}>
     */
    public function leagues(): Collection
    {
        return $this->user->leagues()
            ->whereDate('ends_on', '<', Clock::today()->toDateString())
            ->with('members.goal.weeks.tasks', 'members.goal.habits.logs', 'members.user')
            ->orderByDesc('ends_on')
            ->get()
            ->filter(fn (League $league) => $league->status() === LeagueStatus::Finished)
            ->map(function (League $league) {
                $snapshot = new LeagueSnapshot($league, $this->user);
                $goal = $snapshot->me->goal;

                return [
                    'league' => $league,
                    'rank' => $snapshot->rank(),
                    'ordinal' => $snapshot->ordinal(),
                    'size' => $league->members->count(),
                    'goal' => $goal,
                    'achieved' => $goal?->status === GoalStatus::Completed,
                    'record' => $snapshot->record(),
                ];
            })
            ->values();
    }

    /**
     * Goals that are no longer in play: completed, retired, or active but past their date.
     *
     * @return Collection<int, array{goal: Goal, state: string, label: string, on: CarbonInterface}>
     */
    public function goals(): Collection
    {
        return $this->user->goals()->get()
            ->map(fn (Goal $goal) => match (true) {
                $goal->status === GoalStatus::Completed => ['goal' => $goal, 'state' => 'completed', 'label' => 'Crossed the line', 'on' => Clock::local($goal->completed_at)],
                $goal->status === GoalStatus::Retired => ['goal' => $goal, 'state' => 'retired', 'label' => 'Retired', 'on' => Clock::local($goal->retired_at)],
                $goal->hasEndedTimeline() => ['goal' => $goal, 'state' => 'ended', 'label' => 'Date reached', 'on' => $goal->target_date],
                default => null,
            })
            ->filter()
            ->sortByDesc(fn (array $row) => $row['on']?->timestamp ?? 0)
            ->values();
    }

    /**
     * Finished GoalWeeks across every goal, newest first, with the GoalLeague result when the week was played in one.
     *
     * @return Collection<int, array{week: RoadmapWeek, result: GoalWeekResult|null, percent: int, linePoints: int, match: LeagueResult|null, weeklyPoints: int}>
     */
    public function weeks(?int $limit = self::WEEKS_SHOWN): Collection
    {
        $weeks = RoadmapWeek::query()
            ->whereHas('goal', fn ($goals) => $goals->where('user_id', $this->user->id))
            ->whereDate('ends_on', '<', Clock::today()->toDateString())
            ->with(['goal.weeks', 'goal.habits.logs', 'tasks'])
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();

        return $this->rows($weeks);
    }

    /**
     * One finished GoalWeek of this member's, described like the History list; null if it isn't theirs or isn't over.
     *
     * @return array{week: RoadmapWeek, result: GoalWeekResult|null, percent: int, linePoints: int, match: LeagueResult|null, weeklyPoints: int}|null
     */
    public function week(RoadmapWeek $week): ?array
    {
        if ($week->goal->user_id !== $this->user->id || ! $week->isPast()) {
            return null;
        }

        return $this->rows(collect([$week]))->first();
    }

    /**
     * @param  Collection<int, RoadmapWeek>  $weeks
     * @return Collection<int, array{week: RoadmapWeek, result: GoalWeekResult|null, percent: int, linePoints: int, match: LeagueResult|null, weeklyPoints: int}>
     */
    private function rows(Collection $weeks): Collection
    {
        $results = $this->leagueResults($weeks);

        return $weeks->map(function (RoadmapWeek $week) use ($results) {
            $result = $week->result();
            $match = $results->get($week->id);
            $linePoints = $result?->points() ?? 0;

            return [
                'week' => $week,
                'result' => $result,
                'percent' => $week->creditedPercent(),
                'linePoints' => $linePoints,
                'match' => $match,
                'weeklyPoints' => $match ? $match->result_points + $match->matchup_points : $linePoints,
            ];
        });
    }

    /**
     * The settled GoalLeague result for each week, keyed by roadmap week id: the league whose goal it was, and the
     * league week its last day falls in.
     *
     * @param  Collection<int, RoadmapWeek>  $weeks
     * @return Collection<int, LeagueResult>
     */
    private function leagueResults(Collection $weeks): Collection
    {
        $memberships = $this->user->leagues()->get();
        $settled = LeagueResult::query()->where('user_id', $this->user->id)->with('opponent')->get()
            ->keyBy(fn (LeagueResult $result) => "{$result->league_id}:{$result->week}");

        return $weeks->mapWithKeys(function (RoadmapWeek $week) use ($memberships, $settled) {
            foreach ($memberships->where('pivot.goal_id', $week->goal_id) as $league) {
                $number = $league->weekNumberOn($week->ends_on);
                if ($number !== null && $settled->has("{$league->id}:{$number}")) {
                    return [$week->id => $settled->get("{$league->id}:{$number}")->setRelation('league', $league)];
                }
            }

            return [];
        });
    }

    /**
     * The match result's words, like "Close win vs Red".
     */
    public static function matchLabel(LeagueResult $result): string
    {
        $outcome = $result->outcome instanceof MatchupOutcome ? $result->outcome->label() : (string) $result->outcome;
        $against = $result->vs_median ? 'the league median' : ($result->opponent ? str($result->opponent->name)->before(' ') : 'a former member');

        return "{$outcome} vs {$against}";
    }
}
