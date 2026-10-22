<?php

namespace App\Services;

use App\Clock;
use App\GoalWeekResult;
use App\LeagueStatus;
use App\MatchupOutcome;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueTable
{
    public function __construct(public League $league)
    {
        $league->loadMissing(['members.user', 'members.goal.weeks.tasks', 'results']);
    }

    /**
     * This week's GoalGroups: round-robin pairs that repeat all season.
     * A member paired with null plays the league median.
     *
     * @return list<array{0: LeagueMember, 1: LeagueMember|null}>
     */
    public function pairings(int $week): array
    {
        $members = $this->league->members->values()->all();

        if (count($members) % 2 === 1) {
            $members[] = null;
        }

        $count = count($members);

        if ($count < 2) {
            return [];
        }

        // Circle method: the first seat stays put while the rest rotate one place each week.
        $round = ($week - 1) % ($count - 1);
        $rest = array_slice($members, 1);
        $rotated = [$members[0], ...array_slice($rest, count($rest) - $round), ...array_slice($rest, 0, count($rest) - $round)];

        $pairs = [];

        for ($seat = 0; $seat < $count / 2; $seat++) {
            [$home, $away] = [$rotated[$seat], $rotated[$count - 1 - $seat]];
            $pairs[] = $home === null ? [$away, null] : [$home, $away];
        }

        return $pairs;
    }

    /**
     * A member's GoalWeek result for a league week: the GoalWeek of their league goal containing that week's Sunday.
     *
     * @return array{result: GoalWeekResult|null, points: int, final: bool, goalWeek: RoadmapWeek|null}
     */
    public function memberResult(LeagueMember $member, int $week): array
    {
        $sunday = $this->league->weekEndsOn($week);
        $goalWeek = $member->goal?->weeks->first(fn (RoadmapWeek $candidate) => $candidate->starts_on->lte($sunday) && $candidate->ends_on->gte($sunday));

        if ($goalWeek === null) {
            // No GoalWeek to score counts as falling short, once check-ins for that week would have closed.
            return [
                'result' => null,
                'points' => 0,
                'final' => $sunday->copy()->addDays(RoadmapWeek::CHECK_IN_GRACE_DAYS)->lt(Clock::today()),
                'goalWeek' => null,
            ];
        }

        $result = $goalWeek->result();

        return ['result' => $result, 'points' => $result?->points() ?? 0, 'final' => $goalWeek->isLocked(), 'goalWeek' => $goalWeek];
    }

    /**
     * Every GoalGroup matchup for a league week, scored live until the week is final.
     *
     * @return list<array{home: LeagueMember, away: LeagueMember|null, homeResult: array, awayResult: array|null, median: float|null, homeOutcome: MatchupOutcome, awayOutcome: MatchupOutcome|null}>
     */
    public function matchups(int $week): array
    {
        $results = $this->league->members->mapWithKeys(fn (LeagueMember $member) => [$member->id => $this->memberResult($member, $week)]);

        return collect($this->pairings($week))->map(function (array $pair) use ($results) {
            [$home, $away] = $pair;
            $homeResult = $results[$home->id];

            if ($away === null) {
                $median = $this->median($results->except($home->id)->pluck('points'));

                return [
                    'home' => $home, 'away' => null,
                    'homeResult' => $homeResult, 'awayResult' => null,
                    'median' => $median,
                    'homeOutcome' => $median === null ? MatchupOutcome::Tie : MatchupOutcome::compare($homeResult['points'], $median),
                    'awayOutcome' => null,
                ];
            }

            $awayResult = $results[$away->id];

            return [
                'home' => $home, 'away' => $away,
                'homeResult' => $homeResult, 'awayResult' => $awayResult,
                'median' => null,
                'homeOutcome' => MatchupOutcome::compare($homeResult['points'], $awayResult['points']),
                'awayOutcome' => MatchupOutcome::compare($awayResult['points'], $homeResult['points']),
            ];
        })->all();
    }

    /**
     * Whether every member's result for a league week is final.
     */
    public function isFinal(int $week): bool
    {
        return $this->league->members->every(fn (LeagueMember $member) => $this->memberResult($member, $week)['final']);
    }

    /**
     * Store results for every finished league week that hasn't been stored yet. Stored results never change.
     */
    public function settle(): void
    {
        if ($this->league->status() === LeagueStatus::Forming) {
            return;
        }

        $settled = $this->league->results->pluck('week')->unique();
        $lastWeek = min($this->league->currentWeek() ?? $this->league->weeksCount(), $this->league->weeksCount());

        foreach (range(1, $lastWeek) as $week) {
            if ($settled->contains($week) || ! $this->isFinal($week)) {
                continue;
            }

            DB::transaction(function () use ($week) {
                foreach ($this->matchups($week) as $matchup) {
                    $this->store($week, $matchup['home'], $matchup['homeResult'], $matchup['away'], $matchup['homeOutcome'], $matchup['away'] === null);

                    if ($matchup['away']) {
                        $this->store($week, $matchup['away'], $matchup['awayResult'], $matchup['home'], $matchup['awayOutcome'], false);
                    }
                }

                $this->announceFinal($week);
            });
        }

        $this->league->load('results');
    }

    /**
     * Post a week's final results in the league chat, naming the top scorers.
     */
    private function announceFinal(int $week): void
    {
        $results = $this->league->results()->where('week', $week)->get();
        $best = $results->max(fn ($result) => $result->result_points + $result->matchup_points);

        if ($results->isEmpty() || $best === 0) {
            $this->league->announce("Week {$week} is final. Nobody crossed the line this time. New week, fresh start.");

            return;
        }

        $leaders = $results
            ->filter(fn ($result) => $result->result_points + $result->matchup_points === $best)
            ->map(fn ($result) => $this->league->members->firstWhere('user_id', $result->user_id)?->user->name)
            ->filter()
            ->values();

        $this->league->announce(sprintf(
            'Week %d is final. Top score: %s with %d %s.',
            $week,
            $leaders->join(', ', ' and '),
            $best,
            $best === 1 ? 'point' : 'points',
        ));
    }

    /**
     * The league table: total points (GoalWeek result points plus matchup points), then result points, then wins.
     *
     * @return Collection<int, array{member: LeagueMember, played: int, wins: int, ties: int, losses: int, resultPoints: int, matchupPoints: int, total: int}>
     */
    public function standings(): Collection
    {
        $results = $this->league->results->groupBy('user_id');

        return $this->league->members
            ->map(function (LeagueMember $member) use ($results) {
                $mine = $results->get($member->user_id, collect());

                return [
                    'member' => $member,
                    'played' => $mine->count(),
                    'wins' => $mine->where('outcome', MatchupOutcome::Win)->count(),
                    'ties' => $mine->where('outcome', MatchupOutcome::Tie)->count(),
                    'losses' => $mine->where('outcome', MatchupOutcome::Loss)->count(),
                    'resultPoints' => (int) $mine->sum('result_points'),
                    'matchupPoints' => (int) $mine->sum('matchup_points'),
                    'total' => (int) $mine->sum('result_points') + (int) $mine->sum('matchup_points'),
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['total'], $b['resultPoints'], $b['wins']] <=> [$a['total'], $a['resultPoints'], $a['wins']])
            ->values();
    }

    /**
     * The middle value of everyone else's result points, or null if nobody else played.
     *
     * @param  Collection<int, int>  $points
     */
    private function median(Collection $points): ?float
    {
        return $points->isEmpty() ? null : (float) $points->median();
    }

    /**
     * Record one member's settled result for a league week.
     *
     * @param  array{result: GoalWeekResult|null, points: int}  $result
     */
    private function store(int $week, LeagueMember $member, array $result, ?LeagueMember $opponent, MatchupOutcome $outcome, bool $vsMedian): void
    {
        $this->league->results()->firstOrCreate(
            ['user_id' => $member->user_id, 'week' => $week],
            [
                'result' => $result['result'] ?? GoalWeekResult::Short,
                'result_points' => $result['points'],
                'opponent_id' => $opponent?->user_id,
                'vs_median' => $vsMedian,
                'outcome' => $outcome,
                'matchup_points' => $outcome->points(),
            ],
        );
    }
}
