<?php

namespace App\Jobs;

use App\Models\GoalResult;
use App\Services\GoalRecommender;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ask the Goal Recommender for next-goal ideas after a Goal Day check-in. The templates show until (and unless)
 * valid ideas come back; the recommender never throws.
 */
class RecommendNextGoals implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Each attempt is a paid API call, and the SDK already retries transient errors.
     */
    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public GoalResult $result) {}

    public function uniqueId(): string
    {
        return (string) $this->result->id;
    }

    public function handle(GoalRecommender $recommender): void
    {
        $recommender->generate($this->result);
    }
}
