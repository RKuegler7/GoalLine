<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\RoadmapWeek;
use App\Services\PlanEditor;
use App\Services\PlanFloor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoadmapWeekController extends Controller
{
    /**
     * Edit a GoalWeek's focus and milestone. Only GoalWeeks that haven't started (a week's plan locks when it starts),
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

    /**
     * Declare an upcoming GoalWeek lighter, with a one-line reason the league sees: its minimum drops to 70% of the
     * plan floor. Two a season, and only before the week starts.
     */
    public function lighter(Request $request, RoadmapWeek $week): RedirectResponse
    {
        $goal = $week->goal;
        Gate::authorize('update', $goal);
        abort_unless($goal->isActive() && $week->starts_on->gt(Clock::today()), 403, 'A lighter week has to be declared before it starts.');

        $reason = trim($request->validateWithBag("lighter{$week->id}", [
            'reason' => ['required', 'string', 'max:120'],
        ], [
            'reason.required' => 'Add a one-line reason, like "Finals week" or "Traveling for work".',
        ])['reason']);

        if (! $week->isLighter() && $goal->weeks()->whereNotNull('lighter_at')->count() >= RoadmapWeek::LIGHTER_WEEKS_PER_SEASON) {
            return $this->backTo($week, 'You\'ve used both lighter weeks this season. Swap tasks between weeks instead, or keep the pace.');
        }

        $week->forceFill(['lighter_at' => $week->lighter_at ?? now(), 'lighter_reason' => $reason])->save();
        $goal->recordRoadmapChange("Lighter week declared for {$week->label()}: {$reason}");

        return $this->backTo($week, "{$week->label()} is a lighter week: its minimum is now {$week->effectiveFloor()} points. Your league sees the reason.");
    }

    /**
     * Take back a lighter week before it starts. Its full minimum applies again.
     */
    public function steady(RoadmapWeek $week, PlanFloor $planFloor): RedirectResponse
    {
        Gate::authorize('update', $week->goal);
        abort_unless($week->isLighter() && $week->starts_on->gt(Clock::today()), 403);

        $week->forceFill(['lighter_at' => null, 'lighter_reason' => null])->save();
        $planFloor->raise($week);
        $week->goal->recordRoadmapChange("{$week->label()} is back to the full pace");

        return $this->backTo($week, "{$week->label()} is back to the full pace.");
    }

    private function backTo(RoadmapWeek $week, string $status): RedirectResponse
    {
        return redirect()->to(route('goals.show', $week->goal).'#goalweek-'.$week->position)->with('status', $status);
    }
}
