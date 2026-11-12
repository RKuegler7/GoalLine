<?php

namespace App\Services;

use App\GoalWeekResult;
use App\LeagueStatus;
use App\MatchupOutcome;
use App\Models\LeagueMember;
use App\Models\LeagueResult;
use App\Models\RoadmapWeek;
use Illuminate\Support\Collection;

/**
 * The numbers behind the standings: each member's record with its close wins, close losses and photo finishes,
 * points by kind, average percent of plan, best week, weeks on the line and band counts, plus league-wide highlights
 * and a season-so-far series. Everything comes from the settled league results (LeagueTable::settle) and the
 * scoring code's credited percent; this only adds them up. The views just show them.
 */
class LeagueStats
{
    public const SORTS = ['points' => 'Points', 'line' => 'Line points', 'percent' => 'Avg % of plan', 'streak' => 'Weeks on the line'];

    public const FORM_WEEKS = 5;

    /**
     * Per-member stats, keyed by member id, worked out once.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $members = null;

    public function __construct(public LeagueTable $table) {}

    /**
     * The league weeks with settled results, in order.
     *
     * @return list<int>
     */
    public function settledWeeks(): array
    {
        return $this->table->league->results->pluck('week')->unique()->sort()->values()->all();
    }

    /**
     * The standings in the official order (total points, then line points, then wins), or re-sorted by another stat
     * with the official order breaking ties. Each row adds the member's stats to LeagueTable's row.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function standings(string $sort = 'points'): Collection
    {
        $official = $this->table->standings()->values()->map(fn (array $row, int $index) => $row + ['rank' => $index + 1, 'stats' => $this->member($row['member'])]);

        $key = match ($sort) {
            'line' => fn (array $row) => $row['resultPoints'],
            'percent' => fn (array $row) => $row['stats']['avgPercent'] ?? -1,
            'streak' => fn (array $row) => $row['stats']['weeksOnLine'],
            default => null,
        };

        return $key === null
            ? $official
            : $official->sort(fn (array $a, array $b) => [$key($b), $a['rank']] <=> [$key($a), $b['rank']])->values();
    }

    /**
     * One member's stats.
     *
     * @return array{weeks: list<array<string, mixed>>, wins: int, ties: int, losses: int, closeWins: int, closeLosses: int, photoFinishes: int, total: int, matchPoints: int, linePoints: int, avgPercent: int|null, bestWeek: array<string, mixed>|null, weeksOnLine: int, bestWeeksOnLine: int, bands: array<string, int>, form: list<GoalWeekResult|null>, goalStatus: GoalWeekResult|null, planStatus: string|null}
     */
    public function member(LeagueMember $member): array
    {
        $this->members ??= collect();

        return $this->members[$member->id] ??= $this->compute($member);
    }

    /**
     * League-wide highlights for the panel above the table. Nulls until there's something to show.
     *
     * @return array{leader: array<string, mixed>|null, mostSmashed: array{member: LeagueMember, count: int}|null, longestOnLine: array{member: LeagueMember, weeks: int}|null, bestWeek: array{member: LeagueMember, week: int, points: int, percent: int}|null, avgPercent: int|null, weeksRemaining: int, played: int}
     */
    public function summary(): array
    {
        $rows = $this->standings();
        $played = count($this->settledWeeks());
        $all = $rows->map(fn (array $row) => ['member' => $row['member']] + $row['stats']);

        $smashed = $all->sortByDesc(fn (array $stats) => $stats['bands'][GoalWeekResult::Smashed->value])->first();
        $onLine = $all->sortByDesc('bestWeeksOnLine')->first();
        $best = $all->filter(fn (array $stats) => $stats['bestWeek'] !== null)
            ->sortByDesc(fn (array $stats) => [$stats['bestWeek']['weeklyPoints'], $stats['bestWeek']['percent']])
            ->first();
        $percents = $all->flatMap(fn (array $stats) => collect($stats['weeks'])->pluck('percent'));

        $league = $this->table->league;

        return [
            'leader' => $played > 0 ? $rows->first() : null,
            'mostSmashed' => $smashed && $smashed['bands'][GoalWeekResult::Smashed->value] > 0
                ? ['member' => $smashed['member'], 'count' => $smashed['bands'][GoalWeekResult::Smashed->value]]
                : null,
            'longestOnLine' => $onLine && $onLine['bestWeeksOnLine'] > 0 ? ['member' => $onLine['member'], 'weeks' => $onLine['bestWeeksOnLine']] : null,
            'bestWeek' => $best ? ['member' => $best['member'], 'week' => $best['bestWeek']['week'], 'points' => $best['bestWeek']['weeklyPoints'], 'percent' => $best['bestWeek']['percent']] : null,
            'avgPercent' => $percents->isEmpty() ? null : (int) round($percents->avg()),
            'weeksRemaining' => match ($league->status()) {
                LeagueStatus::Forming => $league->weeksCount(),
                LeagueStatus::Active => max(0, $league->weeksCount() - $league->currentWeek()),
                LeagueStatus::Finished => 0,
            },
            'played' => $played,
        ];
    }

    /**
     * Season so far for one member: their credited percent each settled week next to the league's average.
     *
     * @return list<array{week: int, mine: int|null, league: int|null}>
     */
    public function series(LeagueMember $member): array
    {
        $mine = collect($this->member($member)['weeks'])->keyBy('week');

        return array_map(function (int $week) use ($mine) {
            $percents = $this->table->league->members->map(fn (LeagueMember $each) => collect($this->member($each)['weeks'])->firstWhere('week', $week)['percent'] ?? null)->filter(fn ($percent) => $percent !== null);

            return [
                'week' => $week,
                'mine' => $mine->get($week)['percent'] ?? null,
                'league' => $percents->isEmpty() ? null : (int) round($percents->avg()),
            ];
        }, $this->settledWeeks());
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(LeagueMember $member): array
    {
        $league = $this->table->league;
        /** @var Collection<int, LeagueResult> $results */
        $results = $league->results->where('user_id', $member->user_id)->sortBy('week')->values();
        $membersByUser = $league->members->keyBy('user_id');

        $weeks = $results->map(function (LeagueResult $result) use ($league, $member, $membersByUser) {
            $opponent = $result->opponent_id ? $membersByUser->get($result->opponent_id) : null;

            return [
                'week' => $result->week,
                'band' => $result->result,
                'linePoints' => $result->result_points,
                'outcome' => $result->outcome,
                'matchPoints' => $result->matchup_points,
                'weeklyPoints' => $result->result_points + $result->matchup_points,
                'percent' => $this->table->memberResult($member, $result->week)['percent'],
                'opponent' => $opponent,
                'opponentName' => $result->vs_median ? 'League median' : ($opponent?->displayName() ?? 'A former member'),
                'opponentPercent' => $opponent ? $this->table->memberResult($opponent, $result->week)['percent'] : null,
                'opponentBand' => $opponent ? $league->results->first(fn (LeagueResult $theirs) => $theirs->user_id === $opponent->user_id && $theirs->week === $result->week)?->result : null,
            ];
        })->all();

        $onLine = fn (array $week) => $week['band'] !== null && $week['band'] !== GoalWeekResult::Short;
        $current = 0;
        foreach (array_reverse($weeks) as $week) {
            if (! $onLine($week)) {
                break;
            }
            $current++;
        }
        $best = $run = 0;
        foreach ($weeks as $week) {
            $run = $onLine($week) ? $run + 1 : 0;
            $best = max($best, $run);
        }

        $outcomes = collect($weeks)->pluck('outcome');
        $live = $league->status() === LeagueStatus::Active ? $this->table->memberResult($member, $league->currentWeek()) : null;
        /** @var RoadmapWeek|null $liveWeek */
        $liveWeek = $live['goalWeek'] ?? null;

        return [
            'weeks' => $weeks,
            'wins' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isWin())->count(),
            'ties' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome === MatchupOutcome::Tie)->count(),
            'losses' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isLoss())->count(),
            'closeWins' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome === MatchupOutcome::CloseWin)->count(),
            'closeLosses' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome === MatchupOutcome::CloseLoss)->count(),
            'photoFinishes' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isPhotoFinish())->count(),
            'total' => (int) collect($weeks)->sum('weeklyPoints'),
            'matchPoints' => (int) collect($weeks)->sum('matchPoints'),
            'linePoints' => (int) collect($weeks)->sum('linePoints'),
            'avgPercent' => $weeks === [] ? null : (int) round(collect($weeks)->avg('percent')),
            'bestWeek' => collect($weeks)->sortByDesc(fn (array $week) => [$week['weeklyPoints'], $week['percent']])->first(),
            'weeksOnLine' => $current,
            'bestWeeksOnLine' => $best,
            'bands' => collect(GoalWeekResult::cases())->mapWithKeys(fn (GoalWeekResult $band) => [
                $band->value => collect($weeks)->filter(fn (array $week) => ($week['band'] ?? GoalWeekResult::Short) === $band)->count(),
            ])->all(),
            'form' => collect($weeks)->take(-self::FORM_WEEKS)->pluck('band')->values()->all(),
            'goalStatus' => $live['result'] ?? null,
            'planStatus' => $liveWeek ? RoadmapWeek::planStatusLabel($liveWeek->planStatus()) : null,
        ];
    }
}
