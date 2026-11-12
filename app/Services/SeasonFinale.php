<?php

namespace App\Services;

use App\GoalWeekResult;
use App\HighlightKind;
use App\LeagueStatus;
use App\Models\Goal;
use App\Models\GoalResult;
use App\Models\Highlight;
use App\Models\League;
use App\Models\Medal;
use App\Models\RoadmapTask;
use Illuminate\Support\Collection;

/**
 * Everything the season finale shows for one goal, all computed by code from what's already stored: the Goal Day
 * answer, the table finish, the week-by-week replay, highlights, league news, medals and next-goal ideas.
 */
class SeasonFinale
{
    public ?GoalResult $result;

    public ?League $league;

    public function __construct(public Goal $goal)
    {
        $goal->loadMissing('result', 'user');
        $this->result = $goal->result;
        $this->league = app(GoalDay::class)->league($goal);
    }

    /**
     * The table finish in the league: rank, size, total stars and W–D–L. Null without a league or before any week is settled.
     *
     * @return array{rank: int, size: int, total: int, wins: int, ties: int, losses: int, final: bool}|null
     */
    public function finish(): ?array
    {
        if (! $this->league) {
            return null;
        }

        return once(function () {
            $standings = (new LeagueTable($this->league->loadMissing('results', 'members.user')))->standings()->values();
            $index = $standings->search(fn (array $row) => $row['member']->user_id === $this->goal->user_id);

            if ($index === false || $standings[$index]['played'] === 0) {
                return null;
            }

            $row = $standings[$index];

            return [
                'rank' => $index + 1,
                'size' => $standings->count(),
                'total' => $row['total'],
                'wins' => $row['wins'],
                'ties' => $row['ties'],
                'losses' => $row['losses'],
                'final' => $this->league->status() === LeagueStatus::Finished,
            ];
        });
    }

    /**
     * The replay: each finished GoalWeek's stars and percent of plan.
     *
     * @return Collection<int, array{label: string, number: int, stars: int, percent: int, result: GoalWeekResult|null}>
     */
    public function weeks(): Collection
    {
        return once(fn () => (new History($this->goal->user))->goalWeeks($this->goal)
            ->values()
            ->map(fn (array $row, int $index) => [
                'label' => $row['week']->label(),
                'number' => $index + 1,
                'stars' => $row['weeklyPoints'],
                'percent' => $row['percent'],
                'result' => $row['result'],
            ]));
    }

    /**
     * The season in numbers.
     *
     * @return array{weeks: int, totalStars: int, bestWeek: array{label: string, stars: int, percent: int}|null, longestStreak: int, bands: array<string, int>, tasksDone: int, proofPosts: int, cheers: int}
     */
    public function stats(): array
    {
        return once(function () {
            $weeks = $this->weeks();
            $best = $weeks->sortByDesc(fn (array $week) => [$week['stars'], $week['percent']])->first();
            $run = $longest = 0;

            foreach ($weeks as $week) {
                $run = $week['result'] && $week['result'] !== GoalWeekResult::Short ? $run + 1 : 0;
                $longest = max($longest, $run);
            }

            $tasks = RoadmapTask::query()->whereHas('week', fn ($query) => $query->where('goal_id', $this->goal->id))->whereNotNull('completed_at');

            return [
                'weeks' => $weeks->count(),
                'totalStars' => (int) $weeks->sum('stars'),
                'bestWeek' => $best && $best['stars'] > 0 ? ['label' => $best['label'], 'stars' => $best['stars'], 'percent' => $best['percent']] : null,
                'longestStreak' => $longest,
                'bands' => collect(GoalWeekResult::cases())->mapWithKeys(fn (GoalWeekResult $band) => [$band->label() => $weeks->where('result', $band)->count()])->all(),
                'tasksDone' => (clone $tasks)->count(),
                'proofPosts' => (clone $tasks)->whereNotNull('proof_path')->count(),
                'cheers' => (int) Highlight::where('goal_id', $this->goal->id)->withCount('cheers')->get()->sum('cheers_count'),
            ];
        });
    }

    /**
     * The highlights reel: this goal's posts with photos, most cheered first.
     *
     * @return Collection<int, Highlight>
     */
    public function highlights(int $limit = 6): Collection
    {
        return Highlight::where('goal_id', $this->goal->id)
            ->where('kind', '!=', HighlightKind::GoalDay)
            ->withCount('cheers')
            ->orderByDesc('cheers_count')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * League news: league mates' shared Goal Day results, members only. Private results never show.
     *
     * @return Collection<int, GoalResult>
     */
    public function leagueNews(): Collection
    {
        if (! $this->league) {
            return collect();
        }

        return self::newsFor($this->league)->reject(fn (GoalResult $result) => $result->user_id === $this->goal->user_id)->values();
    }

    /**
     * Shared Goal Day results in a league, achieved and verified first.
     *
     * @return Collection<int, GoalResult>
     */
    public static function newsFor(League $league): Collection
    {
        return GoalResult::where('league_id', $league->id)->where('shared', true)
            ->with('user', 'goal')
            ->get()
            ->sortBy(fn (GoalResult $result) => [$result->isAchieved() ? 0 : 1, $result->isVerified() ? 0 : 1, -$result->answered_at->timestamp])
            ->values();
    }

    /**
     * This goal's medals and its league season's medals, most special first.
     *
     * @return Collection<int, Medal>
     */
    public function medals(): Collection
    {
        return once(fn () => Medal::where('user_id', $this->goal->user_id)
            ->where(fn ($query) => $query->where('goal_id', $this->goal->id)->when($this->league, fn ($query) => $query->orWhere('league_id', $this->league->id)))
            ->get()
            ->sortBy(fn (Medal $medal) => $medal->rank())
            ->values());
    }

    public function bestMedal(): ?Medal
    {
        return $this->medals()->first();
    }

    /**
     * Next-goal ideas.
     *
     * @return list<array{title: string, category: string, weeks: int, why: string, kind: string, target_date: string}>
     */
    public function recommendations(): array
    {
        return app(GoalRecommender::class)->for($this->goal, $this->result);
    }

    /**
     * Whether the person said they reached it.
     */
    public function achieved(): bool
    {
        return (bool) $this->result?->isAchieved();
    }
}
