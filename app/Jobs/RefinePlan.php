<?php

namespace App\Jobs;

use App\Clock;
use App\Models\PlanRefinement;
use App\Services\PlanEditor;
use App\Services\RoadmapPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefinePlan implements ShouldQueue
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

    public function __construct(public PlanRefinement $refinement) {}

    /**
     * Ask the coach for the refined GoalWeeks and hold them for the person's preview. Nothing changes on the roadmap
     * until they accept. A failure is logged and shown as a friendly "try again".
     */
    public function handle(RoadmapPlanner $planner, PlanEditor $editor): void
    {
        $goal = $this->refinement->goal->load('weeks.tasks', 'habits.logs', 'materials', 'intake');

        try {
            $refined = Clock::for($goal->user, fn () => $planner->refine($goal, (string) $this->refinement->instruction, $editor->editableWeeks($goal)));
            $this->refinement->forceFill(['status' => PlanRefinement::READY, 'explanation' => $refined['explanation'], 'proposal' => $refined['weeks']])->save();
        } catch (Throwable $exception) {
            Log::warning('Plan refinement failed.', ['goal_id' => $goal->id, 'error' => $exception->getMessage()]);
            $this->refinement->decide(PlanRefinement::FAILED);
        }
    }

    /**
     * If the job itself dies (a timeout), let the person try again.
     */
    public function failed(?Throwable $exception): void
    {
        $this->refinement->refresh()->decide(PlanRefinement::FAILED);
    }
}
