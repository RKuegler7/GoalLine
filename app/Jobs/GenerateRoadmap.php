<?php

namespace App\Jobs;

use App\Clock;
use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use App\Services\WeekPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class GenerateRoadmap implements ShouldQueue
{
    use Queueable;

    /**
     * Each attempt is a paid API call, and the SDK already retries transient errors.
     */
    public int $tries = 1;

    /**
     * Longer than the SDK's own request timeout, so the SDK reports timeouts first.
     */
    public int $timeout = 660;

    /**
     * Skip the job if the goal was removed while it waited.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance. With a position, earlier stretches are kept and only the rest re-planned.
     */
    public function __construct(public Goal $goal, public ?int $fromPosition = null) {}

    /**
     * Build on the goal owner's clock, so GoalWeek dates match their calendar.
     */
    public function handle(RoadmapPlanner $planner): void
    {
        Clock::for($this->goal->user, fn () => $this->build($planner));
    }

    /**
     * Build the roadmap, replacing the stretches being re-planned.
     */
    private function build(RoadmapPlanner $planner): void
    {
        $this->goal->load('weeks.tasks', 'materials', 'habits');

        $kept = $this->fromPosition
            ? $this->goal->weeks->filter(fn (RoadmapWeek $week) => $week->position < $this->fromPosition)
            : collect();

        $periods = $this->fromPosition
            ? $this->goal->weeks
                ->filter(fn (RoadmapWeek $week) => $week->position >= $this->fromPosition)
                ->map(fn (RoadmapWeek $week) => ['starts_on' => $week->starts_on->toImmutable(), 'ends_on' => $week->ends_on->toImmutable()])
                ->values()
                ->all()
            : $planner->periods($this->goal);

        $roadmap = $planner->plan($this->goal, $periods, $kept);
        $firstPosition = $this->fromPosition ?? 1;

        $weekPlanner = app(WeekPlanner::class);

        DB::transaction(function () use ($periods, $roadmap, $firstPosition, $weekPlanner) {
            $this->goal->weeks()->where('position', '>=', $firstPosition)->delete();

            foreach ($roadmap['periods'] as $index => $period) {
                $week = $this->goal->weeks()->create([
                    'position' => $firstPosition + $index,
                    'starts_on' => $periods[$index]['starts_on'],
                    'ends_on' => $periods[$index]['ends_on'],
                    'focus' => Str::limit(trim($period['focus']), 250),
                    'milestone' => trim($period['milestone']),
                ]);

                $tasks = array_values(array_filter($period['tasks'], fn ($task) => is_string($task) && trim($task) !== ''));

                foreach ($tasks as $position => $task) {
                    $week->tasks()->create([
                        'position' => $position + 1,
                        'description' => Str::limit(trim($task), 250),
                    ]);
                }

                // Spread the GoalWeek's tasks across its days; people can move them in the weekly planner.
                $weekPlanner->autoPlan($week->load('tasks'));
            }

            // Suggested habits are added only when the goal has none, so a rebuild never replaces the person's own.
            if ($this->goal->currentHabits()->isEmpty()) {
                foreach ($roadmap['habits'] ?? [] as $habit) {
                    $this->goal->habits()->create($habit);
                }
            }

            // A fresh plan starts with a clean coach check.
            $this->goal->forceFill([
                'roadmap_status' => RoadmapStatus::Ready,
                'roadmap_summary' => trim($roadmap['summary']),
                'roadmap_generated_at' => now(),
                'pending_changes' => null,
                'plan_check_status' => RoadmapStatus::None,
                'plan_verdict' => null,
                'plan_note' => null,
                'plan_suggestion' => null,
                'plan_fix' => null,
                'plan_dismissed_at' => null,
                'plan_checked_at' => null,
            ])->save();
        });
    }

    /**
     * Let the person know the roadmap could not be built.
     */
    public function failed(?Throwable $exception): void
    {
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Failed])->save();
    }
}
