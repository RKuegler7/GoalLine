<?php

namespace App\Jobs;

use App\Models\Goal;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
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
     * Create a new job instance.
     */
    public function __construct(public Goal $goal) {}

    /**
     * Build the roadmap and replace any previous one.
     */
    public function handle(RoadmapPlanner $planner): void
    {
        $periods = $planner->periods($this->goal);
        $roadmap = $planner->plan($this->goal, $periods);

        DB::transaction(function () use ($periods, $roadmap) {
            $this->goal->weeks()->delete();

            foreach ($roadmap['periods'] as $index => $period) {
                $week = $this->goal->weeks()->create([
                    'position' => $index + 1,
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
            }

            $this->goal->forceFill([
                'roadmap_status' => RoadmapStatus::Ready,
                'roadmap_summary' => trim($roadmap['summary']),
                'roadmap_generated_at' => now(),
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
