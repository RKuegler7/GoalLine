<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\Goal;
use App\Models\RoadmapTask;
use App\Services\WeekPlanner;
use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GameplanController extends Controller
{
    /**
     * The weekly planner: put this GoalWeek's tasks on days and tune habits, across every active goal.
     */
    public function show(Request $request, WeekPlanner $planner): View
    {
        $goals = $request->user()->goals()->active()->with(['weeks.tasks', 'habits.logs'])->orderBy('target_date')->get()
            ->filter(fn (Goal $goal) => $goal->currentWeek() !== null)
            ->values();

        return view('planner.show', [
            'goals' => $goals,
            'today' => Clock::today(),
            'planner' => $planner,
        ]);
    }

    /**
     * Save the day chosen for each task.
     */
    public function update(Request $request): RedirectResponse
    {
        $choices = $request->validate(['tasks' => ['array'], 'tasks.*' => ['nullable', 'date']])['tasks'] ?? [];
        $tasks = RoadmapTask::with('week.goal')->whereIn('id', array_keys($choices))->get();

        foreach ($tasks as $task) {
            Gate::authorize('update', $task->week->goal);
            $day = $choices[$task->id] ? Clock::today()->setDateFrom($choices[$task->id]) : null;

            if ($day && ! $day->between($task->week->starts_on, $task->week->ends_on)) {
                throw ValidationException::withMessages(["tasks.{$task->id}" => 'Pick a day inside this GoalWeek.']);
            }

            $task->update(['planned_on' => $day]);
        }

        Usage::track($request->user(), 'week-planned');

        return redirect()->route('planner.show')->with('status', 'Week planned.');
    }

    /**
     * Spread every unscheduled task across the rest of the week.
     */
    public function autoPlan(Request $request, WeekPlanner $planner): RedirectResponse
    {
        $scheduled = $request->user()->goals()->active()->with(['weeks.tasks', 'habits.logs'])->get()
            ->map(fn (Goal $goal) => $goal->currentWeek())
            ->filter()
            ->sum(fn ($week) => $planner->autoPlan($week));
        Usage::track($request->user(), 'week-planned');

        return redirect()->route('planner.show')->with('status', $scheduled > 0
            ? "Planned {$scheduled} ".($scheduled === 1 ? 'task' : 'tasks').' across the rest of your week.'
            : 'Everything is already on a day.');
    }

    /**
     * Push a task to tomorrow, as long as tomorrow is still in its GoalWeek.
     */
    public function moveToTomorrow(Request $request, RoadmapTask $task): RedirectResponse
    {
        Gate::authorize('update', $task->week->goal);
        $tomorrow = Clock::today()->addDay();

        if ($tomorrow->gt($task->week->ends_on)) {
            return redirect()->to(route('dashboard').'#gameplan')->with('status', "Today is the last day of this GoalWeek, so that task can't move to tomorrow.");
        }

        $task->update(['planned_on' => $tomorrow]);

        return redirect()->to(route('dashboard').'#gameplan')->with('status', 'Moved to tomorrow.');
    }
}
