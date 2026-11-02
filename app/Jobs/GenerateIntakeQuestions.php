<?php

namespace App\Jobs;

use App\Clock;
use App\Models\GoalIntake;
use App\Services\RoadmapPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateIntakeQuestions implements ShouldQueue
{
    use Queueable;

    /**
     * Each attempt is a paid API call, and the person is waiting: fall back instead of retrying.
     */
    public int $tries = 1;

    /**
     * The person waits at most a minute before the built-in questions take over.
     */
    public int $timeout = 120;

    /**
     * Skip the job if the goal was removed while it waited.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public GoalIntake $intake) {}

    /**
     * Ask the coach for questions specific to the goal. Any failure falls back to the built-in questions, and the
     * person never sees the error.
     */
    public function handle(RoadmapPlanner $planner): void
    {
        $goal = $this->intake->goal;

        try {
            $questions = Clock::for($goal->user, fn () => $planner->intakeQuestions($goal));
            $this->intake->useQuestions($questions, GoalIntake::SOURCE_AI);
        } catch (Throwable $exception) {
            Log::warning('Intake questions fell back to the built-in set.', ['goal_id' => $goal->id, 'error' => $exception->getMessage()]);
            $this->intake->useFallback();
        }
    }

    /**
     * If the job itself dies (a timeout), the built-in questions take over.
     */
    public function failed(?Throwable $exception): void
    {
        $this->intake->refresh()->useFallback();
    }
}
