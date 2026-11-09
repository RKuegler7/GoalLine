<?php

namespace App\Services;

use App\Atomic;
use App\Clock;
use App\GoalWeekResult;
use App\LeagueStatus;
use App\MatchupOutcome;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueResult;
use App\Models\RoadmapWeek;
use Illuminate\Support\Collection;

class LeagueTable
{
    /**
     * Weekly recaps older than this aren't sent, for example when old weeks settle all at once.
     */
    public const RECAP_NEWS_DAYS = 14;

    public function __construct(public League $league)
    {
        $league->loadMissing(['members.user', 'members.goal.weeks.tasks', 'members.goal.habits.logs', 'results']);
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
     * The percent is the credited percent of plan as a whole number, which settles a photo finish in the same band.
     *
     * @return array{result: GoalWeekResult|null, points: int, percent: int, final: bool, goalWeek: RoadmapWeek|null}
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
                'percent' => 0,
                'final' => $sunday->copy()->addDays(RoadmapWeek::CHECK_IN_GRACE_DAYS)->lt(Clock::today()),
                'goalWeek' => null,
            ];
        }

        $result = $goalWeek->result();

        return [
            'result' => $result,
            'points' => $result?->points() ?? 0,
            'percent' => $goalWeek->creditedPercent(),
            'final' => $goalWeek->isLocked(),
            'goalWeek' => $goalWeek,
        ];
    }

    /**
     * Every GoalGroup matchup for a league week, scored live until the week is final. The higher band wins; in the
     * same band, the higher credited percent of plan wins a photo finish. A member without an opponent plays the
     * league median: the middle line points, and in a photo finish the middle credited percent.
     *
     * @return list<array{home: LeagueMember, away: LeagueMember|null, homeResult: array, awayResult: array|null, median: float|null, medianPercent: float|null, homeOutcome: MatchupOutcome, awayOutcome: MatchupOutcome|null}>
     */
    public function matchups(int $week): array
    {
        $results = $this->league->members->mapWithKeys(fn (LeagueMember $member) => [$member->id => $this->memberResult($member, $week)]);

        return collect($this->pairings($week))->map(function (array $pair) use ($results) {
            [$home, $away] = $pair;
            $homeResult = $results[$home->id];

            if ($away === null) {
                $median = $this->median($results->except($home->id)->pluck('points'));
                $medianPercent = $this->median($results->except($home->id)->pluck('percent'));

                return [
                    'home' => $home, 'away' => null,
                    'homeResult' => $homeResult, 'awayResult' => null,
                    'median' => $median,
                    'medianPercent' => $medianPercent,
                    'homeOutcome' => $median === null
                        ? MatchupOutcome::Tie
                        : MatchupOutcome::compare($homeResult['points'], $median, $homeResult['percent'], $medianPercent),
                    'awayOutcome' => null,
                ];
            }

            $awayResult = $results[$away->id];

            return [
                'home' => $home, 'away' => $away,
                'homeResult' => $homeResult, 'awayResult' => $awayResult,
                'median' => null,
                'medianPercent' => null,
                'homeOutcome' => MatchupOutcome::compare($homeResult['points'], $awayResult['points'], $homeResult['percent'], $awayResult['percent']),
                'awayOutcome' => MatchupOutcome::compare($awayResult['points'], $homeResult['points'], $awayResult['percent'], $homeResult['percent']),
            ];
        })->all();
    }

    /**
     * The middle member's planned points for a league week, so points like a group task's can be shown as a share
     * of a typical GoalWeek. Null when nobody has a GoalWeek then.
     */
    public function typicalPlannedPoints(int $week): ?int
    {
        $planned = $this->league->members
            ->map(fn (LeagueMember $member) => $this->memberResult($member, $week)['goalWeek']?->plannedPoints())
            ->filter();

        return $planned->isEmpty() ? null : (int) round($planned->median());
    }

    /**
     * Whether every member's result for a league week is final.
     */
    public function isFinal(int $week): bool
    {
        return $this->league->members->every(fn (LeagueMember $member) => $this->memberResult($member, $week)['final']);
    }

    /**
     * Store results for every finished league week that hasn't been stored yet. Stored results change only when a dispute on one of their check-ins is settled (see rescore()).
     * Then send the latest week's recap, and the season awards once the last week is in.
     */
    public function settle(): void
    {
        if ($this->league->status() === LeagueStatus::Forming) {
            return;
        }

        $settled = $this->league->results->pluck('week')->unique();
        $lastWeek = min($this->league->currentWeek() ?? $this->league->weeksCount(), $this->league->weeksCount());
        $newlySettled = [];

        foreach (range(1, $lastWeek) as $week) {
            if ($settled->contains($week) || ! $this->isFinal($week)) {
                continue;
            }

            // Work out the rows first (reads only), then write them in one short transaction. Pages that settle the
            // same week at the same moment can't both win: rows already stored are skipped, and only the request that
            // actually stored the week posts its final results.
            $rows = $this->rows($week);
            $stored = Atomic::run(function () use ($rows, $week) {
                $inserted = LeagueResult::insertOrIgnore($rows);

                if ($inserted > 0) {
                    $this->announceFinal($week);
                }

                return $inserted;
            });

            if ($stored > 0) {
                $newlySettled[] = $week;
            }
        }

        $this->league->load('results');
        $recap = new SeasonRecap($this->league, $this);

        // Only the latest week gets a recap notification, and only while it's news.
        $latest = $newlySettled === [] ? null : max($newlySettled);

        if ($latest && $this->league->weekEndsOn($latest)->gte(Clock::today()->subDays(self::RECAP_NEWS_DAYS))) {
            $recap->notifyWeek($latest);
        }

        $recap->finalize();
    }

    /**
     * Recompute a week that was already final, after a dispute on one of its check-ins was settled. Held points come
     * back (or, if the league owner ruled a check-in out, stay out), and matchup outcomes follow.
     */
    public function rescore(?int $week): void
    {
        if ($week === null || ! $this->league->results()->where('week', $week)->exists()) {
            return;
        }

        // Idempotent: recomputed from the check-ins as they are now and written in one upsert, in a fixed order.
        $rows = $this->rows($week);
        Atomic::run(fn () => LeagueResult::upsert($rows, ['league_id', 'user_id', 'week'], ['result', 'result_points', 'opponent_id', 'vs_median', 'outcome', 'matchup_points', 'updated_at']));

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
                    'wins' => $mine->filter(fn ($result) => $result->outcome->isWin())->count(),
                    'ties' => $mine->where('outcome', MatchupOutcome::Tie)->count(),
                    'losses' => $mine->filter(fn ($result) => $result->outcome->isLoss())->count(),
                    'resultPoints' => (int) $mine->sum('result_points'),
                    'matchupPoints' => (int) $mine->sum('matchup_points'),
                    'total' => (int) $mine->sum('result_points') + (int) $mine->sum('matchup_points'),
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['total'], $b['resultPoints'], $b['wins']] <=> [$a['total'], $a['resultPoints'], $a['wins']])
            ->values();
    }

    /**
     * The middle value of everyone else's line points (or credited percents), or null if nobody else played.
     *
     * @param  Collection<int, int>  $points
     */
    private function median(Collection $points): ?float
    {
        return $points->isEmpty() ? null : (float) $points->median();
    }

    /**
     * Every member's settled result for a league week, as rows ready to store, sorted by member so concurrent
     * writers always take row locks in the same order.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(int $week): array
    {
        $rows = [];

        foreach ($this->matchups($week) as $matchup) {
            $rows[] = $this->row($week, $matchup['home'], $matchup['homeResult'], $matchup['away'], $matchup['homeOutcome'], $matchup['away'] === null);

            if ($matchup['away']) {
                $rows[] = $this->row($week, $matchup['away'], $matchup['awayResult'], $matchup['home'], $matchup['awayOutcome'], false);
            }
        }

        usort($rows, fn (array $a, array $b) => $a['user_id'] <=> $b['user_id']);

        return $rows;
    }

    /**
     * One member's settled result for a league week.
     *
     * @param  array{result: GoalWeekResult|null, points: int}  $result
     * @return array<string, mixed>
     */
    private function row(int $week, LeagueMember $member, array $result, ?LeagueMember $opponent, MatchupOutcome $outcome, bool $vsMedian): array
    {
        $now = now();

        return [
            'league_id' => $this->league->id,
            'user_id' => $member->user_id,
            'week' => $week,
            'result' => ($result['result'] ?? GoalWeekResult::Short)->value,
            'result_points' => $result['points'],
            'opponent_id' => $opponent?->user_id,
            'vs_median' => $vsMedian,
            'outcome' => $outcome->value,
            'matchup_points' => $outcome->points(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
