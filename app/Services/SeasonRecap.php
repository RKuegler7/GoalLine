<?php

namespace App\Services;

use App\Badge;
use App\GoalWeekResult;
use App\LeagueStatus;
use App\MatchupOutcome;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueResult;
use App\Notifications\LeagueRecap;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * A GoalLeague's recaps: each settled week (scores, Player of the Week) and, once the season is over,
 * the season awards: Champion, MVP, Comeback Player, Iron Streak, and Smash Machine.
 */
class SeasonRecap
{
    public function __construct(public League $league, public LeagueTable $table) {}

    /**
     * Whether every week of a finished season has been settled.
     */
    public function isComplete(): bool
    {
        return $this->league->status() === LeagueStatus::Finished
            && $this->league->results->pluck('week')->unique()->count() >= $this->league->weeksCount();
    }

    /**
     * The season awards, worked out from the settled results. One member can win several.
     *
     * @return list<array{badge: Badge, member: LeagueMember, detail: string}>
     */
    public function awards(): array
    {
        $standings = $this->table->standings();

        if ($standings->isEmpty() || $standings->first()['total'] === 0) {
            return [];
        }

        $order = $standings->pluck('member.user_id')->flip();
        $results = $this->league->results->groupBy('user_id');
        $awards = [];

        // Champion: top of the table.
        $champion = $standings->first();
        $awards[] = [
            'badge' => Badge::Champion,
            'member' => $champion['member'],
            'detail' => "{$champion['total']} points, {$champion['wins']}–{$champion['ties']}–{$champion['losses']}",
        ];

        // The rest go to whoever leads each category; ties go to the higher finish in the table.
        $leader = function (callable $score, int $minimum) use ($standings, $order) {
            return $standings
                ->map(fn (array $row) => ['member' => $row['member'], 'score' => $score($row['member'])])
                ->filter(fn (array $entry) => $entry['score'] >= $minimum)
                ->sort(fn (array $a, array $b) => [$b['score'], $order[$a['member']->user_id]] <=> [$a['score'], $order[$b['member']->user_id]])
                ->first();
        };

        if ($mvp = $leader(fn (LeagueMember $member) => (int) $results->get($member->user_id, collect())->sum('result_points'), 1)) {
            $awards[] = ['badge' => Badge::Mvp, 'member' => $mvp['member'], 'detail' => $mvp['score'].' GoalWeek '.str('point')->plural($mvp['score'])];
        }

        $weeks = $this->league->weeksCount();

        if ($weeks >= 2 && $standings->count() >= 3) {
            $halfway = $this->ranksAfter(intdiv($weeks, 2));
            $final = $this->ranksAfter($weeks);

            if ($comeback = $leader(fn (LeagueMember $member) => $halfway[$member->user_id] - $final[$member->user_id], 1)) {
                $from = $halfway[$comeback['member']->user_id];
                $to = $final[$comeback['member']->user_id];
                $awards[] = ['badge' => Badge::ComebackPlayer, 'member' => $comeback['member'], 'detail' => 'Climbed from '.$this->ordinal($from).' to '.$this->ordinal($to)];
            }
        }

        if ($streak = $leader(fn (LeagueMember $member) => $this->longestInLineRun($results->get($member->user_id, collect())), min(3, $weeks))) {
            $awards[] = ['badge' => Badge::IronStreak, 'member' => $streak['member'], 'detail' => $streak['score'].' GoalWeeks in line in a row'];
        }

        if ($smasher = $leader(fn (LeagueMember $member) => $results->get($member->user_id, collect())->where('result', GoalWeekResult::Smashed)->count(), 1)) {
            $awards[] = ['badge' => Badge::SmashMachine, 'member' => $smasher['member'], 'detail' => $smasher['score'].' smashed '.str('GoalWeek')->plural($smasher['score'])];
        }

        return $awards;
    }

    /**
     * Once the season is complete, hand out the awards (once), post them in the chat, and tell everyone.
     */
    public function finalize(): void
    {
        if (! $this->isComplete() || $this->league->awards()->exists()) {
            return;
        }

        $awarder = app(BadgeAwarder::class);
        $awards = $this->awards();

        foreach ($awards as $award) {
            $awarder->award($award['member']->user, $award['badge'], $this->league, $award['detail']);
        }

        $named = collect($awards)->map(fn (array $award) => $award['badge']->label().': '.$award['member']->user->name)->join('. ');
        $this->league->announce('The season is over. '.($named !== '' ? $named.'. ' : '').'The full season recap is on the league page.');

        Notification::send(
            $this->league->members->pluck('user'),
            new LeagueRecap($this->league->withoutRelations(), "The {$this->league->name} season is over. See who won Champion, MVP, and the rest of the awards."),
        );
    }

    /**
     * A settled week: who played whom, and the Player of the Week.
     *
     * @return array{week: int, matchups: list<array{home: LeagueResult, away: LeagueResult|null}>, players: Collection<int, LeagueMember>, best: int}
     */
    public function week(int $week): array
    {
        $rows = $this->league->results->where('week', $week)->keyBy('user_id');
        $best = (int) $rows->max(fn (LeagueResult $row) => $row->result_points + $row->matchup_points);
        $seen = [];
        $matchups = [];

        foreach ($rows->sortByDesc(fn (LeagueResult $row) => $row->result_points + $row->matchup_points) as $row) {
            if (isset($seen[$row->user_id])) {
                continue;
            }

            $opponent = $row->opponent_id ? $rows->get($row->opponent_id) : null;
            $seen[$row->user_id] = true;

            if ($opponent) {
                $seen[$opponent->user_id] = true;
            }

            $matchups[] = ['home' => $row, 'away' => $opponent];
        }

        return [
            'week' => $week,
            'matchups' => $matchups,
            'players' => $best > 0
                ? $rows->filter(fn (LeagueResult $row) => $row->result_points + $row->matchup_points === $best)
                    ->map(fn (LeagueResult $row) => $this->member($row->user_id))->filter()->values()
                : collect(),
            'best' => $best,
        ];
    }

    /**
     * Tell each member how their week went once it's final.
     */
    public function notifyWeek(int $week): void
    {
        $recap = $this->week($week);
        $playerNames = $recap['players']->map(fn (LeagueMember $member) => $member->user->name)->join(', ', ' and ');

        foreach ($this->league->members as $member) {
            $mine = $this->league->results->where('week', $week)->firstWhere('user_id', $member->user_id);

            if (! $mine) {
                continue;
            }

            $opponent = $mine->vs_median ? 'the league median' : ($this->member($mine->opponent_id)?->user->name ?? 'a former member');
            $verb = match ($mine->outcome) {
                MatchupOutcome::Win => 'you beat',
                MatchupOutcome::Tie => 'you tied',
                MatchupOutcome::Loss => 'you lost to',
            };
            $points = $mine->result_points + $mine->matchup_points;
            $text = "{$this->league->name} week {$week} is final: {$verb} {$opponent}, +{$points} ".str('point')->plural($points).'.';

            if ($playerNames !== '') {
                $text .= ' Player of the Week: '.($recap['players']->contains(fn (LeagueMember $player) => $player->is($member)) && $recap['players']->count() === 1 ? 'you!' : $playerNames.'.');
            }

            $member->user->notify(new LeagueRecap($this->league->withoutRelations(), $text));
        }
    }

    /**
     * Each member's rank counting only results through a given week.
     *
     * @return array<int, int> user_id => rank
     */
    public function ranksAfter(int $week): array
    {
        $results = $this->league->results->where('week', '<=', $week)->groupBy('user_id');

        return $this->league->members
            ->map(function (LeagueMember $member) use ($results) {
                $mine = $results->get($member->user_id, collect());

                return [
                    'user_id' => $member->user_id,
                    'key' => [
                        (int) $mine->sum('result_points') + (int) $mine->sum('matchup_points'),
                        (int) $mine->sum('result_points'),
                        $mine->where('outcome', MatchupOutcome::Win)->count(),
                    ],
                ];
            })
            ->sort(fn (array $a, array $b) => $b['key'] <=> $a['key'])
            ->values()
            ->mapWithKeys(fn (array $row, int $index) => [$row['user_id'] => $index + 1])
            ->all();
    }

    /**
     * @param  Collection<int, LeagueResult>  $results
     */
    private function longestInLineRun(Collection $results): int
    {
        $best = $run = 0;
        $previous = null;

        foreach ($results->sortBy('week') as $result) {
            $inLine = $result->result !== GoalWeekResult::Short;
            $run = $inLine && ($previous === null || $result->week === $previous + 1) ? $run + 1 : ($inLine ? 1 : 0);
            $best = max($best, $run);
            $previous = $result->week;
        }

        return $best;
    }

    private function member(?int $userId): ?LeagueMember
    {
        return $userId ? $this->league->members->firstWhere('user_id', $userId) : null;
    }

    private function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }
}
