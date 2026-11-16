<?php

namespace App\Services;

use App\Atomic;
use App\GoalWeekResult;
use App\MatchupOutcome;
use App\MedalType;
use App\Models\Cheer;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LeagueResult;
use App\Models\Medal;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Awards medals from settled results, by code, using the rules in config/medals.php. Idempotent: every medal has a key
 * (type + league or goal + person), so running it again changes nothing. League medals are worked out once the season
 * is complete and again only when a dispute re-scores a settled week, which can add or take away medals.
 *
 * Awarding never runs inside another transaction: it waits until the surrounding one commits, then runs under a
 * per-league (or per-goal) lock so one request does it at a time. A deadlock or duplicate that survives the retries
 * means another request already wrote the same medals, so it's logged as a warning, never shown or emailed.
 */
class MedalAwarder
{
    /**
     * While true (building a demo world), nothing is awarded: the demo writes its medals itself, as seeded data.
     */
    private static bool $seeding = false;

    /**
     * Run demo-building work with awarding switched off.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function whileSeeding(Closure $callback): mixed
    {
        $was = self::$seeding;
        self::$seeding = true;

        try {
            return $callback();
        } finally {
            self::$seeding = $was;
        }
    }

    /**
     * Goal medals: Goal Achieved (Verified with proof or a confirm) and Proof Pro.
     */
    public function forGoal(Goal $goal): void
    {
        $this->afterCommit('goal-medals:'.$goal->id, function () use ($goal) {
            if ($goal = $goal->fresh()) {
                $this->sync(Medal::where('goal_id', $goal->id)->whereNull('league_id'), $this->desiredForGoal($goal));
            }
        });
    }

    /**
     * The goal medals a goal has earned right now.
     *
     * @return list<array<string, mixed>>
     */
    public function desiredForGoal(Goal $goal): array
    {
        $desired = [];
        $result = $goal->result;

        if ($result?->isAchieved()) {
            $desired[] = $this->goalMedal($goal, MedalType::GoalAchieved, $result->isVerified() ? 'verified' : null, [
                'tier_label' => $result->isVerified() ? 'Verified' : null,
                'detail' => $goal->title,
            ]);
        }

        $proofs = $goal->weeks()->withCount(['tasks' => fn ($tasks) => $tasks->whereNotNull('proof_path')->whereNotNull('completed_at')])->get()->sum('tasks_count');

        if ($proofs >= config('medals.proof_pro_checkins')) {
            $desired[] = $this->goalMedal($goal, MedalType::ProofPro, null, ['detail' => "{$proofs} check-ins with proof on {$goal->title}"]);
        }

        return $desired;
    }

    /**
     * League medals for a complete season.
     */
    public function forLeague(League $league): void
    {
        $this->afterCommit('league-season:'.$league->id, function () use ($league) {
            $desired = $this->desiredForLeague($league);

            if ($desired !== null) {
                $this->sync(Medal::where('league_id', $league->id), $desired);
            }
        });
    }

    /**
     * Write medals worked out for brand-new data (a demo world being built): a plain insert, nothing to reconcile.
     *
     * @param  list<array<string, mixed>>  $desired
     */
    public function seed(array $desired): void
    {
        if ($desired !== []) {
            Medal::insert($this->rows($desired));
        }
    }

    /**
     * The league medals a complete season has earned, or null while the season isn't complete.
     *
     * @return list<array<string, mixed>>|null
     */
    public function desiredForLeague(League $league): ?array
    {
        $league->loadMissing('results', 'members.user');
        $table = new LeagueTable($league);
        $recap = new SeasonRecap($league, $table);

        if (! $recap->isComplete()) {
            return null;
        }

        $standings = $table->standings()->values();
        $results = $league->results->groupBy('user_id');
        $weeks = $league->weeksCount();
        $desired = [];
        $add = function (LeagueMember $member, MedalType $type, ?string $tier, array $meta) use ($league, &$desired) {
            $desired[] = [
                'user_id' => $member->user_id,
                'type' => $type,
                'tier' => $tier,
                'league_id' => $league->id,
                'goal_id' => $member->goal_id,
                'season' => 1,
                'award_key' => "{$type->value}:L{$league->id}:U{$member->user_id}",
                'metadata' => array_filter($meta, fn ($value) => $value !== null),
            ];
        };

        // Nobody wins a table where nobody scored.
        $anyScore = $standings->isNotEmpty() && $standings->first()['total'] > 0;

        foreach ($standings as $index => $row) {
            $place = $index + 1;

            if (! $anyScore) {
                break;
            }

            if ($place === 1) {
                $add($row['member'], MedalType::TableChampion, 'gold', ['detail' => "{$row['total']} stars, {$row['wins']}–{$row['ties']}–{$row['losses']}"]);
            }

            if ($place <= config('medals.podium_places') && $standings->count() >= 2) {
                $tier = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$place] ?? 'bronze';
                $add($row['member'], MedalType::Podium, $tier, ['tier_label' => $this->ordinal($place), 'detail' => "Finished {$this->ordinal($place)} with {$row['total']} stars"]);
            }
        }

        $maxWeek = app(ScoringRules::class)->weekRange()['max'];
        $bestWeek = (int) $league->results->max(fn (LeagueResult $result) => $result->result_points + $result->matchup_points);
        $halfway = $weeks >= 2 && $standings->count() >= 3 ? $recap->ranksAfter(intdiv($weeks, 2)) : null;
        $final = $halfway ? $recap->ranksAfter($weeks) : null;

        foreach ($league->members as $member) {
            /** @var Collection<int, LeagueResult> $mine */
            $mine = $results->get($member->user_id, collect())->sortBy('week')->values();

            if ($mine->isEmpty()) {
                continue;
            }

            $perfect = $mine->filter(fn (LeagueResult $result) => $result->result_points + $result->matchup_points >= $maxWeek);

            if ($perfect->isNotEmpty()) {
                $add($member, MedalType::PerfectWeek, null, ['detail' => "{$maxWeek} stars in Week {$perfect->first()->week}", 'weeks' => $perfect->pluck('week')->all()]);
            }

            $smashed = $mine->where('result', GoalWeekResult::Smashed)->count();

            if ($smashed >= 1) {
                $many = $smashed >= config('medals.smashed_many');
                $add($member, MedalType::SmashedIt, $many ? 'many' : 'first', [
                    'tier_label' => $many ? config('medals.smashed_many').'+' : 'First',
                    'detail' => $smashed.' Smashed '.str('GoalWeek')->plural($smashed),
                ]);
            }

            $run = $this->longestInLineRun($mine);
            $streakTier = collect(config('medals.streak_tiers'))->filter(fn ($tier, $needed) => $run >= $needed)->keys()->max();

            if ($streakTier) {
                $add($member, MedalType::Streak, config('medals.streak_tiers')[$streakTier], [
                    'tier_label' => "{$streakTier} weeks",
                    'detail' => "{$run} GoalWeeks In Line or better in a row",
                ]);
            }

            $inLine = $mine->filter(fn (LeagueResult $result) => $result->result !== GoalWeekResult::Short)->count();

            if ($mine->count() >= config('medals.consistency_min_weeks') && $inLine / $mine->count() >= config('medals.consistency_share')) {
                $add($member, MedalType::Consistency, null, ['detail' => "In Line or better in {$inLine} of {$mine->count()} weeks"]);
            }

            $photoWins = $mine->where('outcome', MatchupOutcome::CloseWin)->count();

            if ($photoWins >= config('medals.photo_finish_wins')) {
                $add($member, MedalType::PhotoFinishWinner, null, ['detail' => "{$photoWins} photo finish wins"]);
            }

            if ($halfway && ($halfway[$member->user_id] ?? 0) - ($final[$member->user_id] ?? 0) >= 1) {
                $add($member, MedalType::Comeback, null, ['detail' => 'Climbed from '.$this->ordinal($halfway[$member->user_id]).' to '.$this->ordinal($final[$member->user_id])]);
            }

            if ($bestWeek > 0 && $mine->contains(fn (LeagueResult $result) => $result->result_points + $result->matchup_points === $bestWeek)) {
                $week = $mine->first(fn (LeagueResult $result) => $result->result_points + $result->matchup_points === $bestWeek)->week;
                $add($member, MedalType::BestWeek, null, ['detail' => "{$bestWeek} stars in Week {$week}, the league's best"]);
            }

            $cheers = $this->cheersGiven($league, $member);

            if ($cheers >= config('medals.encourager_cheers')) {
                $add($member, MedalType::Encourager, null, ['detail' => "{$cheers} cheers for league mates"]);
            }
        }

        return $desired;
    }

    /**
     * Someone's most special medal, for the share card and the standings sheet.
     *
     * @param  Collection<int, Medal>  $medals
     */
    public static function best(Collection $medals): ?Medal
    {
        return $medals->sortBy(fn (Medal $medal) => $medal->rank())->first();
    }

    /**
     * Make the stored medals match the desired list: add new ones, update tiers, remove ones no longer earned.
     *
     * Safe when two requests do it at once, and safe to run again: one INSERT that skips medals already there (the
     * unique award_key decides, never a check-then-insert), updates by award_key, and deletes by primary key only. No
     * statement scans a range of the medals index, so none takes the gap locks that let two of them deadlock, and
     * each runs on its own, retried if MySQL still picks it as a deadlock victim.
     *
     * @param  Builder<Medal>  $scope
     * @param  list<array<string, mixed>>  $desired
     */
    private function sync($scope, array $desired): void
    {
        $now = now();
        $keys = array_column($desired, 'award_key');

        if ($desired !== []) {
            $this->insertMedals($this->rows($desired));
        }

        // Tiers and details can change after a re-score: update the ones that differ, by their unique key.
        $stored = Medal::whereIn('award_key', $keys)->get()->keyBy('award_key');

        foreach ($desired as $medal) {
            $current = $stored->get($medal['award_key']);

            if ($current && ($current->tier !== $medal['tier'] || $current->metadata != $medal['metadata'])) {
                Atomic::run(fn () => Medal::whereKey($current->id)->update(['tier' => $medal['tier'], 'metadata' => json_encode($medal['metadata']), 'updated_at' => $now]));
            }
        }

        // Medals no longer earned (a dispute changed a week): found first, then removed by id.
        $gone = (clone $scope)->whereNotIn('award_key', $keys)->pluck('id');

        if ($gone->isNotEmpty()) {
            Atomic::run(fn () => Medal::whereKey($gone->all())->delete());
        }
    }

    /**
     * One INSERT that skips medals already there (the unique award_key decides), retried on a deadlock.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    protected function insertMedals(array $rows): void
    {
        Atomic::run(fn () => Medal::insertOrIgnore($rows));
    }

    /**
     * Award once the surrounding transaction (if any) has committed, one request at a time per key. Medals are
     * idempotent: if a deadlock or duplicate survives the retries, another request already wrote them, so it's a
     * logged warning, not an error.
     *
     * @param  Closure(): void  $work
     */
    private function afterCommit(string $key, Closure $work): void
    {
        if (self::$seeding) {
            return;
        }

        DB::afterCommit(function () use ($key, $work) {
            try {
                Atomic::exclusively($key, $work, wait: 15);
            } catch (Throwable $exception) {
                if (! Atomic::isConcurrencyError($exception) && ! $exception instanceof UniqueConstraintViolationException) {
                    throw $exception;
                }

                Atomic::noteContention('medals-already-awarded', $exception, $key);
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $desired
     * @return list<array<string, mixed>>
     */
    private function rows(array $desired): array
    {
        $now = now();

        return array_map(fn (array $medal) => [
            ...$medal,
            'type' => $medal['type'] instanceof MedalType ? $medal['type']->value : $medal['type'],
            'metadata' => json_encode($medal['metadata']),
            'awarded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $desired);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function goalMedal(Goal $goal, MedalType $type, ?string $tier, array $meta): array
    {
        return [
            'user_id' => $goal->user_id,
            'type' => $type,
            'tier' => $tier,
            'league_id' => null,
            'goal_id' => $goal->id,
            'season' => 1,
            'award_key' => "{$type->value}:G{$goal->id}",
            'metadata' => array_filter($meta, fn ($value) => $value !== null),
        ];
    }

    /**
     * Cheers this member gave to league mates' posts during the season.
     */
    private function cheersGiven(League $league, LeagueMember $member): int
    {
        $mates = $league->members->pluck('user_id')->reject(fn ($id) => $id === $member->user_id);

        return Cheer::where('user_id', $member->user_id)
            ->whereBetween('created_at', [$league->starts_on->copy()->startOfDay(), $league->ends_on->copy()->endOfDay()])
            ->whereHas('highlight', fn ($highlight) => $highlight->whereIn('user_id', $mates))
            ->count();
    }

    /**
     * @param  Collection<int, LeagueResult>  $results
     */
    private function longestInLineRun(Collection $results): int
    {
        $best = $run = 0;
        $previous = null;

        foreach ($results as $result) {
            $inLine = $result->result !== GoalWeekResult::Short;
            $consecutive = $previous === null || $result->week === $previous + 1;
            $run = $inLine ? ($consecutive ? $run + 1 : 1) : 0;
            $best = max($best, $run);
            $previous = $result->week;
        }

        return $best;
    }

    private function ordinal(int $number): string
    {
        return $number.match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };
    }
}
