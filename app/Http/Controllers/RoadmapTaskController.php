<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Services\PlanEditor;
use App\Services\PlanFloor;
use App\Services\WeekPlanner;
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
            $before = $task->description;
            $task->update(['description' => $description]);
            $week->goal->recordRoadmapChange("Changed a task in {$this->label($week)} from \"{$before}\" to \"{$description}\"");
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

        // Plan lock: once a GoalWeek starts, planned work can be added to but not taken away.
        if ($week->isStarted()) {
            return $this->backTo($week)->with('status', "{$week->label()}'s plan locked when it started. You can add a personal task, but planned work stays.");
        }

        if (PlanFloor::wouldDropBelow($week->load('tasks', 'goal.habits.logs'), $task->points)) {
            return $this->backTo($week)->with('status', $this->belowFloorMessage($week));
        }

        $task->delete();
        $week->goal->recordRoadmapChange("Removed a task from {$this->label($week)}: \"{$task->description}\"");

        return $this->backTo($week);
    }

    /**
     * Reorder a task within its GoalWeek (up or down), or move one of the coach's tasks to another GoalWeek. Moving
     * between weeks only works between GoalWeeks that haven't started (or this one before any check-in), so nobody
     * shifts work out of a week that's underway.
     */
    public function move(Request $request, RoadmapTask $task, PlanEditor $editor): RedirectResponse
    {
        $week = $task->week;
        $this->authorizeEditing($week);
        $validated = $request->validate([
            'direction' => ['nullable', 'in:up,down'],
            'week_id' => ['nullable', 'integer'],
        ]);

        if ($validated['direction'] ?? null) {
            $tasks = $week->tasks()->get()->values();
            $index = $tasks->search(fn (RoadmapTask $candidate) => $candidate->is($task));
            $swap = $tasks->get($validated['direction'] === 'up' ? $index - 1 : $index + 1);

            if ($swap) {
                $tasks->splice($index, 1);
                $tasks->splice($validated['direction'] === 'up' ? $index - 1 : $index + 1, 0, [$task]);
                $tasks->each(fn (RoadmapTask $item, int $position) => $item->forceFill(['position' => $position + 1])->save());
                $week->goal->recordRoadmapChange("Reordered the tasks in {$this->label($week)}");
            }

            return $this->backTo($week);
        }

        $goal = $week->goal->load('weeks.tasks', 'habits.logs');
        $editable = $editor->editableWeeks($goal);
        $target = $editable->firstWhere('id', (int) ($validated['week_id'] ?? 0));
        abort_unless($target && $target->id !== $week->id && $editable->contains('id', $week->id) && PlanEditor::isPlanTask($task) && ! $task->completed_at, 403,
            'Tasks can move only between GoalWeeks that haven\'t started.');

        if (PlanFloor::wouldDropBelow($week->load('tasks', 'goal.habits.logs'), $task->points)) {
            return $this->backTo($week)->with('status', $this->belowFloorMessage($week));
        }

        $task->forceFill(['roadmap_week_id' => $target->id, 'position' => ($target->tasks->max('position') ?? 0) + 1, 'planned_on' => null])->save();
        app(WeekPlanner::class)->autoPlan($target->load('tasks'));
        $goal->recordRoadmapChange("Moved \"{$task->description}\" from {$this->label($week)} to {$this->label($target)}");

        return $this->backTo($target);
    }

    /**
     * Why a change was turned down: it would take the GoalWeek below its plan floor.
     */
    private function belowFloorMessage(RoadmapWeek $week): string
    {
        return "That would put {$week->label()} below the minimum pace your deadline needs ({$week->effectiveFloor()} points). Swap it for another task, or declare a lighter week.";
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
