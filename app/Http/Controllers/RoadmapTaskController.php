<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoadmapTaskController extends Controller
{
    /**
     * Add a task of the person's own to a stretch.
     */
    public function store(Request $request, RoadmapWeek $week): RedirectResponse
    {
        $this->authorizeEditing($week);
        $description = $this->validatedDescription($request);

        $week->tasks()->create([
            'position' => ($week->tasks()->max('position') ?? 0) + 1,
            'description' => $description,
            // Tasks added once the GoalWeek is underway are extra work beyond the plan.
            'is_bonus' => $week->isCurrent(),
            // and land on today's gameplan.
            'planned_on' => $week->isCurrent() ? Clock::today() : null,
        ]);

        $week->goal->recordRoadmapChange("Added a task to {$this->label($week)}: \"{$description}\"");

        return $this->backTo($week);
    }

    /**
     * Reword a task.
     */
    public function update(Request $request, RoadmapTask $task): RedirectResponse
    {
        $week = $task->week;
        $this->authorizeEditing($week);
        $description = $this->validatedDescription($request);

        if ($description !== $task->description) {
            $week->goal->recordRoadmapChange("Changed a task in {$this->label($week)} from \"{$task->description}\" to \"{$description}\"");
            $task->update(['description' => $description]);
        }

        $this->updateTrackedNumber($request, $task);

        return $this->backTo($week);
    }

    /**
     * Set or clear the number a task tracks ("5 miles"). Only before its GoalWeek starts, so nobody can lower the
     * plan once the week is underway.
     */
    private function updateTrackedNumber(Request $request, RoadmapTask $task): void
    {
        if (! $request->has('track_target') || $task->week->isStarted()) {
            return;
        }

        $validated = $request->validate([
            'track_target' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'track_unit' => ['nullable', 'required_with:track_target', 'string', 'max:20'],
        ], [
            'track_unit.required_with' => 'Add a unit, like "miles", "pages", or "minutes".',
            'track_target.gt' => 'The plan has to be more than zero.',
        ]);

        filled($validated['track_target'] ?? null)
            ? $task->trackNumber((float) $validated['track_target'], (string) $validated['track_unit'])
            : $task->stopTrackingNumber();

        $task->save();
    }

    /**
     * Remove a task.
     */
    public function destroy(RoadmapTask $task): RedirectResponse
    {
        $week = $task->week;
        $this->authorizeEditing($week);

        $task->delete();
        $week->goal->recordRoadmapChange("Removed a task from {$this->label($week)}: \"{$task->description}\"");

        return $this->backTo($week);
    }

    /**
     * Only the owner of an active goal can change stretches that haven't ended.
     */
    private function authorizeEditing(RoadmapWeek $week): void
    {
        Gate::authorize('update', $week->goal);
        abort_unless($week->isEditable(), 403, 'Past GoalWeeks can no longer be changed.');
    }

    /**
     * The trimmed task text from the request.
     */
    private function validatedDescription(Request $request): string
    {
        return trim($request->validate(['description' => ['required', 'string', 'max:250']])['description']);
    }

    /**
     * The stretch's GoalWeek label, for describing the change to the coach.
     */
    private function label(RoadmapWeek $week): string
    {
        return $week->label();
    }

    /**
     * Return to the stretch on the goal page.
     */
    private function backTo(RoadmapWeek $week): RedirectResponse
    {
        return redirect()->to(route('goals.show', $week->goal).'#goalweek-'.$week->position);
    }
}
