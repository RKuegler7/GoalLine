<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoadmapTaskController extends Controller
{
    /**
     * Add a personal task to a GoalWeek. It counts in the plan like any other task, so it's capped: three a week,
     * 1 to 5 points each, 15% of the AI-planned points in all, and only until Tuesday 11:59 p.m. of the week.
     * The league sees it labeled "Added by".
     */
    public function store(Request $request, RoadmapWeek $week): RedirectResponse
    {
        $this->authorizeEditing($week);
        $validated = $request->validateWithBag("week{$week->id}", [
            'description' => ['required', 'string', 'max:250'],
            'points' => ['nullable', 'integer', 'min:1', 'max:'.RoadmapWeek::PERSONAL_TASK_MAX_POINTS],
        ]);
        $description = trim($validated['description']);
        $points = (int) ($validated['points'] ?? 1);
        $week->load('tasks');

        $problem = match (true) {
            ! $week->acceptsPersonalTasks() => 'Personal tasks can be added until Tuesday 11:59 p.m. of the GoalWeek.',
            $week->personalTasksLeft() === 0 => 'You can add up to '.RoadmapWeek::PERSONAL_TASK_LIMIT.' personal tasks a GoalWeek.',
            $points > $week->personalPointsLeft() => $week->personalPointsLeft() === 0
                ? 'No personal points left this GoalWeek. Personal tasks can add up to 15% of the planned points.'
                : 'Only '.$week->personalPointsLeft().' personal '.Str::plural('point', $week->personalPointsLeft()).' left this GoalWeek (15% of the planned points).',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['description' => $problem])->errorBag("week{$week->id}");
        }

        $week->tasks()->create([
            'position' => ($week->tasks()->max('position') ?? 0) + 1,
            'description' => $description,
            'planned_on' => $week->isCurrent() ? Clock::today() : null,
        ])->forceFill(['is_personal' => true, 'points' => $points])->save();

        $week->goal->recordRoadmapChange("Added a personal task ({$points} ".Str::plural('point', $points).") to {$this->label($week)}: \"{$description}\"");

        return $this->backTo($week);
    }

    /**
     * Reword a task.
     */
    public function update(Request $request, RoadmapTask $task): RedirectResponse
    {
        $week = $task->week;
        $this->authorizeEditing($week);
        abort_if($task->isGroupTask(), 403, 'Group tasks are set by a league vote.');
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
        abort_if($task->isGroupTask(), 403, 'Group tasks are set by a league vote.');

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
