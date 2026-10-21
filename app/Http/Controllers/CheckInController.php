<?php

namespace App\Http\Controllers;

use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CheckInController extends Controller
{
    /**
     * Check a task off, or un-check it.
     */
    public function toggleTask(RoadmapTask $task): RedirectResponse
    {
        $week = $task->week;
        $this->authorizeCheckIn($week);

        $task->forceFill(['completed_at' => $task->completed_at ? null : now()])->save();

        return $this->backTo($week);
    }

    /**
     * Mark the GoalWeek's milestone as hit, or not.
     */
    public function toggleMilestone(RoadmapWeek $week): RedirectResponse
    {
        $this->authorizeCheckIn($week);

        $week->forceFill(['milestone_hit_at' => $week->milestone_hit_at ? null : now()])->save();

        return $this->backTo($week);
    }

    /**
     * Only the owner can check in, from the day a GoalWeek starts until its result locks.
     */
    private function authorizeCheckIn(RoadmapWeek $week): void
    {
        Gate::authorize('update', $week->goal);
        abort_unless($week->isCheckable(), 403, 'This GoalWeek is not open for check-ins.');
    }

    /**
     * Return to the GoalWeek on the goal page.
     */
    private function backTo(RoadmapWeek $week): RedirectResponse
    {
        return redirect()->to(route('goals.show', $week->goal).'#goalweek-'.$week->position);
    }
}
