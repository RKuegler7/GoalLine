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
 * The numbers behind the standings: each member's W–D–L record with its photo finish wins, photo finishes and byes,
 * points by kind, average percent of plan, best week, weeks on the line and band counts, plus league-wide highlights
 * and a season-so-far series. Everything comes from the settled league results (LeagueTable::settle) and the
 * scoring code's credited percent; this only adds them up. The views just show them.
 */
class LeagueStats
{
    public const SORTS = ['points' => 'Stars', 'line' => 'Line stars', 'percent' => 'Avg % of plan', 'streak' => 'Weeks on the line'];

    public const FORM_WEEKS = 5;

    /**
     * Columns that are for interest only and never decide a place in the table.
     */
    public const INFORMATIONAL = ['for', 'against', 'plus_minus'];

    /**
     * Every table column, left to right: its heading, a one-line explanation, and how a row sorts by it (null: not
     * sortable). All numbers come from LeagueTable and member(); nothing here scores anything.
     *
     * @return array<string, array{label: string, title: string, value: (callable(array<string, mixed>): int|null)|null}>
     */
    public static function columns(): array
    {
        $band = fn (string $key) => fn (array $row) => $row['stats']['bands'][$key] ?? 0;

        return [
            'total' => ['label' => 'Stars', 'title' => 'Stars = Line ★ + Matchup ★. The table is ranked by this.', 'value' => fn (array $row) => $row['total']],
            'record' => ['label' => 'W-D-L', 'title' => 'Matchups won, drawn and lost.', 'value' => fn (array $row) => $row['wins'] * 2 + $row['ties']],
            'line' => ['label' => 'Line ★', 'title' => 'Line ★: earned by your own plan each week.', 'value' => fn (array $row) => $row['resultPoints']],
            'match' => ['label' => 'Matchup ★', 'title' => 'Matchup ★: earned against your opponent each week.', 'value' => fn (array $row) => $row['matchupPoints']],
            'best_week' => ['label' => 'Best week', 'title' => 'Most stars in one week.', 'value' => fn (array $row) => $row['stats']['bestWeek']['weeklyPoints'] ?? 0],
            'form' => ['label' => 'Last '.self::FORM_WEEKS, 'title' => 'The last five weeks: the color is the band, the letter the Matchup result.', 'value' => null],
            'avg' => ['label' => 'Avg %', 'title' => 'Average percent of plan, over final weeks.', 'value' => fn (array $row) => $row['stats']['avgPercent'] ?? -1],
            'streak' => ['label' => 'On the line', 'title' => 'Weeks in a row at In Line or better, right now.', 'value' => fn (array $row) => $row['stats']['weeksOnLine']],
            'best_streak' => ['label' => 'Best run', 'title' => 'Longest run of weeks at In Line or better.', 'value' => fn (array $row) => $row['stats']['bestWeeksOnLine']],
            'photo_wins' => ['label' => 'Photo wins', 'title' => 'Matchups won in a photo finish.', 'value' => fn (array $row) => $row['stats']['photoFinishWins']],
            'smashed' => ['label' => 'Smashed', 'title' => 'Weeks Smashed.', 'value' => $band(GoalWeekResult::Smashed->value)],
            'past' => ['label' => 'Past', 'title' => 'Weeks Past the Line.', 'value' => $band(GoalWeekResult::Past->value)],
            'in_line' => ['label' => 'In Line', 'title' => 'Weeks In Line.', 'value' => $band(GoalWeekResult::InLine->value)],
            'short' => ['label' => 'Short', 'title' => 'Weeks Short.', 'value' => $band(GoalWeekResult::Short->value)],
            'for' => ['label' => '★ For', 'title' => '★ For: stars you earned in weeks with an opponent. For interest only, not ranking.', 'value' => fn (array $row) => $row['stats']['starsFor']],
            'against' => ['label' => '★ Against', 'title' => '★ Against: stars your opponents earned in those weeks. For interest only, not ranking.', 'value' => fn (array $row) => $row['stats']['starsAgainst']],
            'plus_minus' => ['label' => '+/−', 'title' => '+/−: ★ For minus ★ Against. For interest only, not ranking.', 'value' => fn (array $row) => $row['stats']['plusMinus']],
        ];
    }

    /**
     * The one-letter Matchup result for the Last 5 circles: W, D or L, or a dash for a bye.
     */
    public static function outcomeLetter(MatchupOutcome $outcome): string
    {
        return match (true) {
            $outcome->isWin() => 'W',
            $outcome->isDraw() => 'D',
            $outcome->isLoss() => 'L',
            default => '–',
        };
    }

    /**
     * The short band name used in the Last 5 text alternative.
     */
    public static function bandShortName(?GoalWeekResult $band): string
    {
        return match ($band) {
            GoalWeekResult::Smashed => 'Smashed',
            GoalWeekResult::Past => 'Past',
            GoalWeekResult::InLine => 'In Line',
            default => 'Short',
        };
    }

    /**
     * The Last 5 text alternative, oldest first: "Last 5: W Past, L Short, D In Line".
     *
     * @param  list<array{week: int, band: GoalWeekResult|null, letter: string}>  $formWeeks
     */
    public static function formLabel(array $formWeeks): string
    {
        if ($formWeeks === []) {
            return 'Last '.self::FORM_WEEKS.': no final weeks yet';
        }

        return 'Last '.self::FORM_WEEKS.': '.collect($formWeeks)
            ->map(fn (array $week) => ($week['letter'] === '–' ? 'Bye' : $week['letter']).' '.self::bandShortName($week['band']))
            ->implode(', ');
    }

    /**
     * The Stats view's rows: the official order, or sorted by one column (highest first) with the official order
     * breaking ties.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function statsRows(?string $column = null): Collection
    {
        $official = $this->standings();
        $value = self::columns()[$column ?? '']['value'] ?? null;

        return $value === null
            ? $official
            : $official->sort(fn (array $a, array $b) => [$value($b), $a['rank']] <=> [$value($a), $b['rank']])->values();
    }

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
     * The standings in the official order (total stars, then line stars, then Past-the-Line weeks), or re-sorted by another stat
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
     * @return array{weeks: list<array<string, mixed>>, wins: int, ties: int, losses: int, photoFinishWins: int, photoFinishes: int, byes: int, total: int, matchPoints: int, linePoints: int, avgPercent: int|null, bestWeek: array<string, mixed>|null, weeksOnLine: int, bestWeeksOnLine: int, bands: array<string, int>, form: list<GoalWeekResult|null>, goalStatus: GoalWeekResult|null, planStatus: string|null}
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
                'opponentName' => $result->vs_median || $result->outcome === MatchupOutcome::Bye ? 'Bye' : ($opponent?->displayName() ?? 'A former member'),
                'opponentPercent' => $opponent ? $this->table->memberResult($opponent, $result->week)['percent'] : null,
                'opponentResult' => $theirs = $opponent ? $league->results->first(fn (LeagueResult $theirs) => $theirs->user_id === $opponent->user_id && $theirs->week === $result->week) : null,
                'opponentBand' => $theirs?->result,
                // A real head-to-head (not a bye or a week against the league median): ★ For and ★ Against count it.
                'headToHead' => $theirs !== null && ! $result->vs_median && $result->outcome->counts(),
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
        $headToHead = collect($weeks)->where('headToHead', true);
        $starsFor = (int) $headToHead->sum('weeklyPoints');
        $starsAgainst = (int) $headToHead->sum(fn (array $week) => $week['opponentResult']->result_points + $week['opponentResult']->matchup_points);
        $live = $league->status() === LeagueStatus::Active ? $this->table->memberResult($member, $league->currentWeek()) : null;
        /** @var RoadmapWeek|null $liveWeek */
        $liveWeek = $live['goalWeek'] ?? null;

        return [
            'weeks' => $weeks,
            'wins' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isWin())->count(),
            'ties' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isDraw())->count(),
            'losses' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isLoss())->count(),
            'photoFinishWins' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome === MatchupOutcome::CloseWin)->count(),
            'photoFinishes' => $outcomes->filter(fn (MatchupOutcome $outcome) => $outcome->isPhotoFinish())->count(),
            'byes' => $outcomes->filter(fn (MatchupOutcome $outcome) => ! $outcome->counts())->count(),
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
            'formWeeks' => collect($weeks)->take(-self::FORM_WEEKS)->map(fn (array $week) => [
                'week' => $week['week'],
                'band' => $week['band'],
                'letter' => self::outcomeLetter($week['outcome']),
            ])->values()->all(),
            'starsFor' => $starsFor,
            'starsAgainst' => $starsAgainst,
            'plusMinus' => $starsFor - $starsAgainst,
            'goalStatus' => $live['result'] ?? null,
            'planStatus' => $liveWeek ? RoadmapWeek::planStatusLabel($liveWeek->planStatus()) : null,
        ];
    }
}
