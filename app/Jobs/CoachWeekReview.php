<?php

namespace App\Jobs;

use App\Clock;
use App\Models\WeekReview;
use App\Notifications\CoachTipReady;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Ask the coach for next week's tip once a member has reviewed their GoalWeek.
 */
class CoachWeekReview implements ShouldQueue
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

    public function __construct(public WeekReview $review) {}

    /**
     * Coach on the member's clock, so "today" in the brief is their today.
     */
    public function handle(RoadmapPlanner $planner): void
    {
        $user = $this->review->week->goal->user;

        Clock::for($user, function () use ($planner, $user) {
            $this->review->load('week.goal.weeks.tasks', 'week.goal.habits.logs', 'week.tasks');
            $tip = $planner->coachTip($this->review);

            $this->review->forceFill(['coach_tip' => $tip, 'coach_status' => RoadmapStatus::Ready])->save();
            $user->notify(new CoachTipReady($this->review));
        });
    }

    /**
     * Show that the tip couldn't be written; the review itself is kept.
     */
    public function failed(?Throwable $exception): void
    {
        $this->review->forceFill(['coach_status' => RoadmapStatus::Failed])->save();
    }
}
