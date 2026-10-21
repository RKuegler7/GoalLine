<?php

namespace App\Http\Controllers;

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

        return $this->backTo($week);
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
        return $week->label($week->goal->weeksPerStretch());
    }

    /**
     * Return to the stretch on the goal page.
     */
    private function backTo(RoadmapWeek $week): RedirectResponse
    {
        return redirect()->to(route('goals.show', $week->goal).'#goalweek-'.$week->position);
    }
}
