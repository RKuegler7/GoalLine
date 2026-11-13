<?php

namespace App\Jobs;

use App\AiFallback;
use App\Clock;
use App\Models\Goal;
use App\Models\PlanVersion;
use App\Models\RoadmapWeek;
use App\RoadmapStatus;
use App\Services\FallbackPlan;
use App\Services\PlanFloor;
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

        // The coach plans toward each stretch's minimum (the plan floor); code checks it afterward either way.
        $allPeriods = [...$kept->map(fn (RoadmapWeek $week) => ['starts_on' => $week->starts_on->toImmutable(), 'ends_on' => $week->ends_on->toImmutable()])->values()->all(), ...$periods];
        $floors = array_slice(PlanFloor::forPeriods($this->goal, $allPeriods), $kept->count());

        // If the coach fails or returns something unusable, a starter plan from the goal's category takes its place,
        // and the person is told plainly. Nothing unvalidated is ever saved.
        try {
            $roadmap = $planner->plan($this->goal, $periods, $kept, $floors);
            $source = 'coach';
        } catch (Throwable $exception) {
            AiFallback::note('plan', $exception, ['goal_id' => $this->goal->id]);
            $roadmap = FallbackPlan::for($this->goal, $periods);
            $source = 'fallback';
        }

        $firstPosition = $this->fromPosition ?? 1;

        $weekPlanner = app(WeekPlanner::class);
        $planFloor = app(PlanFloor::class);

        DB::transaction(function () use ($periods, $roadmap, $firstPosition, $weekPlanner, $planFloor, $source) {
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

            // Code, not the coach, has the last word on workload: every new GoalWeek gets its floor and is raised to it.
            $newWeeks = $this->goal->weeks()->where('position', '>=', $firstPosition)->get();
            $planFloor->setFloors($this->goal->load('habits.logs', 'intake'), $newWeeks);
            $newWeeks->each(fn (RoadmapWeek $week) => $planFloor->raise($week->refresh()));

            // A fresh plan starts with a clean coach check, and waits for the person to look it over and accept it.
            $this->goal->forceFill([
                'roadmap_status' => RoadmapStatus::Ready,
                'roadmap_summary' => trim($roadmap['summary']),
                'roadmap_generated_at' => now(),
                'plan_source' => $source,
                'plan_accepted_at' => null,
                'pending_changes' => null,
                'plan_check_status' => RoadmapStatus::None,
                'plan_verdict' => null,
                'plan_note' => null,
                'plan_suggestion' => null,
                'plan_fix' => null,
                'plan_dismissed_at' => null,
                'plan_checked_at' => null,
            ])->save();

            PlanVersion::record($this->goal, PlanVersion::BUILT, match (true) {
                $source === 'fallback' => 'A starter plan (the coach was unavailable)',
                $this->fromPosition > 1 => "Re-planned from GoalWeek {$this->fromPosition} on",
                default => 'The coach built the plan',
            });
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
