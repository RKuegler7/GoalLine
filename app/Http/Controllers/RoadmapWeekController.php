<?php

namespace App\Http\Controllers;

use App\Models\RoadmapWeek;
use App\Services\PlanEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoadmapWeekController extends Controller
{
    /**
     * Edit a GoalWeek's focus and milestone. Only GoalWeeks that haven't started (or this one, before any check-in),
     * so nobody rewrites a milestone after the fact. The change is logged and the coach checks the plan.
     */
    public function update(Request $request, RoadmapWeek $week, PlanEditor $editor): RedirectResponse
    {
        $goal = $week->goal;
        Gate::authorize('update', $goal);
        abort_unless($editor->editableWeeks($goal->load('weeks.tasks', 'habits.logs'))->contains('id', $week->id), 403, 'This GoalWeek is underway, so its milestone is set.');

        $validated = $request->validateWithBag("weekPlan{$week->id}", [
            'focus' => ['required', 'string', 'max:250'],
            'milestone' => ['required', 'string', 'max:500'],
        ]);
        $before = [$week->focus, $week->milestone];
        $week->update(['focus' => trim($validated['focus']), 'milestone' => trim($validated['milestone'])]);

        if ($before !== [$week->focus, $week->milestone]) {
            $goal->recordRoadmapChange("Changed {$week->label()}'s focus and milestone to \"{$week->focus}\" / \"{$week->milestone}\"");
        }

        return redirect()->to(route('goals.show', $goal).'#goalweek-'.$week->position)->with('status', 'GoalWeek updated.');
    }
}
