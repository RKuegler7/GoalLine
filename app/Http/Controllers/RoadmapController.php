<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\RoadmapStatus;
use App\Services\PlanEditor;
use Illuminate\Http\RedirectResponse;

class RoadmapController extends Controller
{
    /**
     * Build the roadmap, or re-plan the GoalWeeks that haven't started. A week's plan locks when it starts, so a
     * re-plan never touches this week, except for a brand-new plan nobody has accepted or checked in on yet.
     * Building now skips any unanswered intake questions.
     */
    public function store(Goal $goal): RedirectResponse
    {
        $goal->intake?->skip();

        if ($goal->roadmap_status === RoadmapStatus::Pending) {
            return redirect()->route('goals.show', $goal);
        }

        if ($goal->roadmap_status !== RoadmapStatus::Ready) {
            $goal->requestRoadmap();

            return redirect()->route('goals.show', $goal);
        }

        $goal->load('weeks.tasks', 'habits.logs');
        $untouched = $goal->plan_accepted_at === null && ! $goal->weeks->contains(fn ($week) => PlanEditor::hasCheckIns($week));
        $from = $untouched ? 1 : $goal->upcomingPosition();

        if ($from === null) {
            return redirect()->route('goals.show', $goal)->with('status', 'Every GoalWeek left is underway, and a week\'s plan locks when it starts, so there\'s nothing left to re-plan.');
        }

        $goal->requestRoadmap($from > 1 ? $from : null);

        return redirect()->route('goals.show', $goal);
    }

    /**
     * Accept the plan after looking it over. Until then it's marked as a draft, and it can still be rebuilt from
     * scratch.
     */
    public function accept(Goal $goal): RedirectResponse
    {
        abort_unless($goal->roadmap_status === RoadmapStatus::Ready, 404);

        if ($goal->plan_accepted_at === null) {
            $goal->forceFill(['plan_accepted_at' => now()])->save();
            $goal->recordRoadmapChange('Accepted the plan');
        }

        return redirect()->route('goals.show', $goal)->with('status', 'Plan accepted. Trust the process: one GoalWeek at a time.');
    }
}
