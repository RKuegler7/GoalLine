<?php

namespace App\Services;

use App\Models\GroupTask;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\GroupTaskDecided;
use App\Notifications\GroupTaskProposed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * GoalLeague group tasks: propose, vote for 24 hours, and, if two thirds of the league say yes, add the task to
 * every member's GoalWeek. Not enough yes votes in 24 hours means no task.
 */
class GroupTasks
{
    /**
     * How long voting stays open after a proposal.
     */
    public const VOTING_HOURS = 24;

    /**
     * Most group tasks that can pass for one league week.
     */
    public const MAX_PASSED_PER_WEEK = 2;

    /**
     * League weeks run Monday 12:00 a.m. to Sunday 11:59 p.m. in this time zone.
     */
    public const TIMEZONE = 'America/New_York';

    /**
     * Group tasks need someone to vote with: no solo leagues.
     */
    public function isEnabledFor(League $league): bool
    {
        return $league->members()->count() >= 2;
    }

    /**
     * When a league week starts: Monday 12:00 a.m. Eastern.
     */
    public function weekStartsAt(League $league, int $week): CarbonImmutable
    {
        return CarbonImmutable::parse($league->weekStartsOn($week)->toDateString(), self::TIMEZONE);
    }

    /**
     * League weeks a task can still be proposed for: ones that start after a vote opened now would close.
     *
     * @return list<int>
     */
    public function proposableWeeks(League $league): array
    {
        $voteEnds = CarbonImmutable::now()->addHours(self::VOTING_HOURS);

        return array_values(array_filter(
            range(1, $league->weeksCount()),
            fn (int $week) => $this->weekStartsAt($league, $week)->gte($voteEnds),
        ));
    }

    /**
     * Group task slots still open for a league week.
     */
    public function slotsLeft(League $league, int $week): int
    {
        $passed = GroupTask::where('league_id', $league->id)->where('week', $week)->where('status', GroupTask::PASSED)->count();

        return max(0, self::MAX_PASSED_PER_WEEK - $passed);
    }

    /**
     * Propose a group task. The proposer's yes counts right away.
     *
     * @param  array{title: string, description?: ?string, points: int, week: int}  $data
     *
     * @throws ValidationException
     */
    public function propose(League $league, User $proposer, array $data): GroupTask
    {
        $title = trim($data['title']);

        $problem = match (true) {
            ! $this->isEnabledFor($league) => 'Group tasks need at least two members in the league.',
            ! in_array($data['week'], $this->proposableWeeks($league), true) => 'Pick a week that starts after the 24-hour vote ends.',
            $this->slotsLeft($league, $data['week']) === 0 => "Week {$data['week']} already has ".self::MAX_PASSED_PER_WEEK.' group tasks.',
            GroupTask::where('league_id', $league->id)->where('week', $data['week'])->where('status', '!=', GroupTask::FAILED)
                ->whereRaw('lower(title) = ?', [mb_strtolower($title)])->exists() => 'That task is already proposed for that week.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['title' => $problem]);
        }

        $groupTask = $league->groupTasks()->create([
            'proposer_id' => $proposer->id,
            'title' => $title,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'points' => $data['points'],
            'week' => $data['week'],
            'closes_at' => now()->addHours(self::VOTING_HOURS),
        ]);
        $groupTask->votes()->create(['user_id' => $proposer->id, 'approve' => true]);

        Notification::send($this->membersOf($league)->reject(fn (User $member) => $member->is($proposer)), new GroupTaskProposed($groupTask));

        $this->settle($groupTask);

        return $groupTask;
    }

    /**
     * Vote yes or no. Votes can change until voting closes.
     */
    public function vote(GroupTask $groupTask, User $voter, bool $approve): void
    {
        $this->settle($groupTask);
        $groupTask->refresh();
        abort_unless($groupTask->isVoting(), 422, 'Voting on this group task has closed.');

        $groupTask->votes()->updateOrCreate(['user_id' => $voter->id], ['approve' => $approve]);
        $this->settle($groupTask->fresh());
    }

    /**
     * Decide a group task if it's ready: it passes the moment yes votes reach two thirds of the league, and it doesn't
     * pass if 24 hours go by without that.
     */
    public function settle(GroupTask $groupTask): void
    {
        DB::transaction(function () use ($groupTask) {
            $groupTask = GroupTask::lockForUpdate()->with('league', 'votes')->find($groupTask->id);

            if (! $groupTask?->isVoting()) {
                return;
            }

            $members = $groupTask->league->members()->count();
            $reachedYes = $groupTask->yesCount() >= GroupTask::requiredYes($members);
            $closed = $groupTask->closes_at->lte(now());

            if ($reachedYes && $this->slotsLeft($groupTask->league, $groupTask->week) > 0) {
                $this->decide($groupTask, GroupTask::PASSED);
                $this->addToEveryGoalWeek($groupTask);
            } elseif ($reachedYes) {
                $this->decide($groupTask, GroupTask::FAILED, "Week {$groupTask->week} already filled its ".self::MAX_PASSED_PER_WEEK.' group task slots.');
            } elseif ($closed) {
                $this->decide($groupTask, GroupTask::FAILED, 'Not enough yes votes in 24 hours.');
            }
        });
    }

    /**
     * Settle every vote whose 24 hours are up.
     *
     * @return int How many were settled.
     */
    public function closeDue(?League $league = null): int
    {
        $due = GroupTask::where('status', GroupTask::VOTING)
            ->where('closes_at', '<=', now())
            ->when($league, fn ($query) => $query->where('league_id', $league->id))
            ->get();

        $due->each(fn (GroupTask $groupTask) => $this->settle($groupTask));

        return $due->count();
    }

    private function decide(GroupTask $groupTask, string $status, ?string $note = null): void
    {
        $groupTask->forceFill(['status' => $status, 'outcome_note' => $note, 'decided_at' => now()])->save();

        DB::afterCommit(fn () => Notification::send($this->membersOf($groupTask->league), new GroupTaskDecided($groupTask)));
    }

    /**
     * Add the task to the GoalWeek each member's league goal has on the first day of the league week.
     */
    private function addToEveryGoalWeek(GroupTask $groupTask): void
    {
        $day = $groupTask->league->weekStartsOn($groupTask->week)->toDateString();

        LeagueMember::where('league_id', $groupTask->league_id)->whereNotNull('goal_id')->pluck('goal_id')
            ->each(function (int $goalId) use ($groupTask, $day) {
                $week = RoadmapWeek::where('goal_id', $goalId)->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->first();

                if (! $week || $week->tasks()->where('group_task_id', $groupTask->id)->exists()) {
                    return;
                }

                $week->tasks()->create([
                    'position' => ($week->tasks()->max('position') ?? 0) + 1,
                    'description' => $groupTask->title,
                ])->forceFill(['group_task_id' => $groupTask->id, 'points' => $groupTask->points])->save();
            });
    }

    /**
     * @return Collection<int, User>
     */
    private function membersOf(League $league): Collection
    {
        return User::whereIn('id', $league->members()->select('user_id'))->get();
    }
}
