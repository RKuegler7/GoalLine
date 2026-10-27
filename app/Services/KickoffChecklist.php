<?php

namespace App\Services;

use App\Models\HabitLog;
use App\Models\RoadmapTask;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Support\Facades\DB;

/**
 * A new member's setup steps on the Clubhouse, checked off from what they've actually done.
 * Shown for a member's first weeks until they finish it or hide it.
 */
class KickoffChecklist
{
    /**
     * How long after joining the checklist is offered.
     */
    public const SHOW_FOR_DAYS = 30;

    public function __construct(public User $user) {}

    /**
     * Each step: what to do, a short why, whether it's done, and where to do it.
     *
     * @return list<array{key: string, label: string, hint: string, done: bool, url: string}>
     */
    public function steps(): array
    {
        $user = $this->user;
        $goals = $user->goals();
        $events = DB::table('usage_events')->where('user_id', $user->id)->distinct()->pluck('name');

        return [
            [
                'key' => 'goal',
                'label' => 'Set your first goal',
                'hint' => 'Name it and pick a date.',
                'done' => (clone $goals)->exists(),
                'url' => route('goals.create'),
            ],
            [
                'key' => 'roadmap',
                'label' => 'Get your roadmap',
                'hint' => 'Your coach turns the goal into GoalWeeks.',
                'done' => (clone $goals)->where('roadmap_status', RoadmapStatus::Ready)->exists(),
                'url' => route('dashboard'),
            ],
            [
                'key' => 'plan',
                'label' => 'Plan your week',
                'hint' => 'Put each task on a day.',
                'done' => $events->contains('week-planned'),
                'url' => route('planner.show'),
            ],
            [
                'key' => 'check-in',
                'label' => 'Make your first check-in',
                'hint' => 'Check off a task or log a habit.',
                'done' => RoadmapTask::whereNotNull('completed_at')->whereHas('week.goal', fn ($query) => $query->where('user_id', $user->id))->exists()
                    || HabitLog::whereHas('habit.goal', fn ($query) => $query->where('user_id', $user->id))->exists(),
                'url' => route('dashboard').'#gameplan',
            ],
            [
                'key' => 'habit',
                'label' => 'Add a habit',
                'hint' => 'The repeat work that wins goals.',
                'done' => $user->goals()->whereHas('habits')->exists(),
                'url' => ($goal = (clone $goals)->active()->first()) ? route('goals.show', $goal).'#habits' : route('goals.create'),
            ],
            [
                'key' => 'notifications',
                'label' => 'Turn on notifications',
                'hint' => 'Your morning gameplan, on your phone.',
                'done' => $user->pushSubscriptions()->exists(),
                'url' => route('settings.notifications'),
            ],
            [
                'key' => 'install',
                'label' => 'Add GoalLine to your home screen',
                'hint' => 'One tap to today, like any app.',
                'done' => $events->contains('app-installed'),
                'url' => route('dashboard'),
            ],
            [
                'key' => 'crew',
                'label' => 'Bring in your crew',
                'hint' => 'Join a GoalLeague or challenge, or add a friend.',
                'done' => $user->leagues()->exists() || $user->challenges()->exists() || $user->friendships()->exists(),
                'url' => route('leagues.index'),
            ],
        ];
    }

    public function doneCount(): int
    {
        return collect($this->steps())->where('done', true)->count();
    }

    /**
     * Whether to show the checklist: a newish member who hasn't finished or hidden it.
     */
    public function isVisible(): bool
    {
        return $this->user->kickoff_dismissed_at === null
            && $this->user->created_at->gte(now()->subDays(self::SHOW_FOR_DAYS))
            && $this->doneCount() < count($this->steps());
    }
}
