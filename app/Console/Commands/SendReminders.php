<?php

namespace App\Console\Commands;

use App\Clock;
use App\GoalWeekResult;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\DailyReminder;
use App\Notifications\GoalWeeksFinal;
use App\Services\TodaysGameplan;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs every 15 minutes. For each member it checks the time on their own clock and sends, at most once a
 * day each: the morning gameplan, the evening nudge (only if work is left), and last GoalWeek's final results.
 */
class SendReminders extends Command
{
    protected $signature = 'goalline:send-reminders';

    protected $description = "Send each member's morning gameplan, evening nudge, and final GoalWeek results on their own clock";

    /**
     * How long after the chosen time a reminder can still go out, in case the scheduler missed a run.
     */
    public const WINDOW_MINUTES = 90;

    public function handle(): int
    {
        $sent = 0;

        User::query()
            ->whereNotNull('email_verified_at')
            ->whereNotNull('onboarded_at')
            ->chunkById(200, function (Collection $users) use (&$sent) {
                foreach ($users as $user) {
                    $sent += Clock::for($user, fn () => $this->remind($user));
                }
            });

        $this->info("Sent {$sent} ".str('reminder')->plural($sent).'.');

        return self::SUCCESS;
    }

    /**
     * Everything due for one member right now, on their clock.
     */
    private function remind(User $user): int
    {
        $settings = $user->notificationSettings();
        $now = now(Clock::timezone());
        $sent = 0;
        $loaded = null;
        $goals = function () use (&$loaded, $user) {
            return $loaded ??= $user->goals()->active()->with(['weeks.tasks', 'habits.logs'])->orderBy('target_date')->get();
        };

        if ($this->isDue($settings->morningAt, $now)) {
            $sent += $this->sendFinalResults($user, $goals());

            if ($this->claim($user, 'morning')) {
                $sent += $this->sendMorning($user, $goals());
            }
        }

        if ($this->isDue($settings->eveningAt, $now) && $this->claim($user, 'evening')) {
            $sent += $this->sendEvening($user, $goals());
        }

        return $sent;
    }

    /**
     * Today's Gameplan, plus a heads-up when a new GoalWeek starts or last week's check-ins close tonight.
     *
     * @param  Collection<int, Goal>  $goals
     */
    private function sendMorning(User $user, Collection $goals): int
    {
        $gameplan = new TodaysGameplan($goals);
        $notes = [];

        if ($gameplan->today->isMonday() && $gameplan->entries->isNotEmpty()) {
            $notes[] = 'A new GoalWeek starts today. [Plan your week]('.route('planner.show').') so every task has a day.';
        }

        foreach ($this->closingTonight($goals) as $week) {
            $notes[] = "Last call: check-ins for {$week->label()} of \u{201C}{$week->goal->title}\u{201D} close tonight. You're at {$week->result()->label()}.";
        }

        if ($gameplan->total() === 0 && $notes === []) {
            return 0;
        }

        $headline = $gameplan->total() > 0
            ? "Today's Gameplan: ".$gameplan->total().' '.($gameplan->total() === 1 ? 'thing' : 'things').' on deck'
            : 'Last call on your GoalWeek check-ins';

        $user->notify(new DailyReminder('morning', $headline, $this->items($gameplan), $notes));

        return 1;
    }

    /**
     * A nudge only when something is still open today.
     *
     * @param  Collection<int, Goal>  $goals
     */
    private function sendEvening(User $user, Collection $goals): int
    {
        $gameplan = new TodaysGameplan($goals);
        $left = $gameplan->remaining();

        if ($left === 0) {
            return 0;
        }

        $closeCalls = $gameplan->entries
            ->filter(fn (array $entry) => $entry['week']->isCloseCall() || $entry['week']->result() === GoalWeekResult::Short)
            ->map(fn (array $entry) => "\u{201C}{$entry['goal']->title}\u{201D} is at {$entry['week']->result()->label()}. ".$entry['week']->tasksToSafety().' more check-ins gets you safely in line.')
            ->values()
            ->all();

        $user->notify(new DailyReminder(
            'evening',
            $left.' '.($left === 1 ? 'thing' : 'things').' left on today\'s gameplan',
            $this->items($gameplan, openOnly: true),
            $closeCalls,
        ));

        return 1;
    }

    /**
     * Once last GoalWeek's check-ins close, tell the member how each goal finished.
     *
     * @param  Collection<int, Goal>  $goals
     */
    private function sendFinalResults(User $user, Collection $goals): int
    {
        $lockedToday = Clock::today()->subDays(RoadmapWeek::CHECK_IN_GRACE_DAYS + 1);

        $results = $goals
            ->flatMap(fn (Goal $goal) => $goal->weeks->filter(fn (RoadmapWeek $week) => $week->ends_on->isSameDay($lockedToday)))
            ->filter(fn (RoadmapWeek $week) => $week->result() !== null)
            ->map(fn (RoadmapWeek $week) => [
                'goal' => $week->goal->title,
                'week' => $week->label(),
                'result' => $week->result()->label(),
                'points' => $week->result()->points(),
                'url' => route('goals.show', $week->goal).'#goalweek-'.$week->position,
            ])
            ->values()
            ->all();

        if ($results === [] || ! $this->claim($user, 'week-final')) {
            return 0;
        }

        $user->notify(new GoalWeeksFinal($results));

        return 1;
    }

    /**
     * Last week's GoalWeeks still open for check-ins on their final day.
     *
     * @param  Collection<int, Goal>  $goals
     * @return Collection<int, RoadmapWeek>
     */
    private function closingTonight(Collection $goals): Collection
    {
        $endedOn = Clock::today()->subDays(RoadmapWeek::CHECK_IN_GRACE_DAYS);

        return $goals
            ->flatMap(fn (Goal $goal) => $goal->weeks->filter(fn (RoadmapWeek $week) => $week->ends_on->isSameDay($endedOn)))
            ->filter(fn (RoadmapWeek $week) => $week->doneUnits() < $week->plannedUnits() || ! $week->milestone_hit_at)
            ->values();
    }

    /**
     * Today's tasks and habits as lines for the email.
     *
     * @return list<string>
     */
    private function items(TodaysGameplan $gameplan, bool $openOnly = false): array
    {
        return $gameplan->entries->flatMap(fn (array $entry) => [
            ...$entry['tasks']
                ->reject(fn (RoadmapTask $task) => $openOnly && $task->completed_at)
                ->map(fn (RoadmapTask $task) => ($task->completed_at ? '~~'.$task->description.'~~' : $task->description).($gameplan->isCarriedOver($task) ? ' (carried over)' : '')),
            ...$entry['habits']
                ->reject(fn (Habit $habit) => $openOnly && $habit->isDoneOn($gameplan->today))
                ->map(fn (Habit $habit) => $habit->title.' ('.$habit->doneIn($entry['week']).' of '.$habit->targetFor($entry['week']).' this week)'),
        ])->values()->all();
    }

    /**
     * Whether a reminder set for a time (like "07:30") is due now: at or after it, within the window.
     */
    private function isDue(?string $time, CarbonInterface $now): bool
    {
        if ($time === null) {
            return false;
        }

        $at = $now->copy()->setTimeFromTimeString($time);

        return $now->gte($at) && $now->lt($at->copy()->addMinutes(self::WINDOW_MINUTES));
    }

    /**
     * Record that a reminder went out today, on the member's clock. False if it already had.
     */
    private function claim(User $user, string $kind): bool
    {
        return DB::table('sent_reminders')->insertOrIgnore([
            'user_id' => $user->id,
            'kind' => $kind,
            'local_date' => Clock::today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
