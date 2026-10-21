<?php

namespace App\Jobs;

use App\Models\PlanMaterial;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReadPlanMaterial implements ShouldQueue
{
    use Queueable;

    /**
     * Each attempt is a paid API call, and the SDK already retries transient errors.
     */
    public int $tries = 1;

    /**
     * Longer than the SDK's own request timeout.
     */
    public int $timeout = 660;

    /**
     * Skip the job if the material was removed while it waited.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance. Set $thenBuildRoadmap when the goal's first roadmap is waiting on this file.
     */
    public function __construct(public PlanMaterial $material, public bool $thenBuildRoadmap = false) {}

    /**
     * Read the file once and keep its digest. The roadmap is built afterward either way.
     */
    public function handle(RoadmapPlanner $planner): void
    {
        try {
            $this->material->forceFill([
                'digest' => $planner->readMaterial($this->material),
                'status' => RoadmapStatus::Ready,
            ])->save();
        } catch (Throwable $exception) {
            $this->material->forceFill(['status' => RoadmapStatus::Failed])->save();
            report($exception);
        } finally {
            if ($this->thenBuildRoadmap) {
                GenerateRoadmap::dispatch($this->material->goal);
            }
        }
    }
}
