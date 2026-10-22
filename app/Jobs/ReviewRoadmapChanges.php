<?php

namespace App\Jobs;

use App\Clock;
use App\Models\Goal;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReviewRoadmapChanges implements ShouldBeUniqueUntilProcessing, ShouldQueue
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
     * Skip the job if the goal was removed while it waited.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Release the uniqueness lock if a job somehow never runs.
     */
    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public Goal $goal) {}

    /**
     * One waiting review per goal: edits made while it waits are picked up when it runs.
     * The lock lifts when the review starts, so edits made during it queue the next one.
     */
    public function uniqueId(): string
    {
        return (string) $this->goal->id;
    }

    /**
     * Review on the goal owner's clock, so "today" in the brief is their today.
     */
    public function handle(RoadmapPlanner $planner): void
    {
        Clock::for($this->goal->user, fn () => $this->review($planner));
    }

    /**
     * Review every change recorded so far, then clear the ones reviewed.
     */
    private function review(RoadmapPlanner $planner): void
    {
        $this->goal->refresh()->load('weeks.tasks', 'materials');
        $changes = $this->goal->pending_changes ?? [];

        if ($changes === []) {
            return;
        }

        $review = $planner->review($this->goal, $changes);

        // Keep any edits made during the review for the next one.
        $remaining = array_slice($this->goal->fresh()->pending_changes ?? [], count($changes));

        $this->goal->forceFill([
            'pending_changes' => $remaining ?: null,
            'plan_check_status' => $remaining ? RoadmapStatus::Pending : RoadmapStatus::Ready,
            'plan_verdict' => $review['verdict'],
            'plan_note' => $review['note'],
            'plan_suggestion' => $review['suggestion'] ?: null,
            'plan_fix' => $review['fix'] ?? null,
            'plan_dismissed_at' => null,
            'plan_checked_at' => now(),
        ])->save();
    }

    /**
     * Fall back quietly: the edits are saved either way.
     */
    public function failed(?Throwable $exception): void
    {
        $this->goal->forceFill(['plan_check_status' => RoadmapStatus::Failed])->save();
    }
}
