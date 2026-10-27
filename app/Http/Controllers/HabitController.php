<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HabitController extends Controller
{
    /**
     * How many days back a check-in can be logged: yesterday, and no earlier.
     */
    public const BACKFILL_DAYS = 1;

    /**
     * Add a habit to a goal. Your coach checks it still keeps you in line.
     */
    public function store(Request $request, Goal $goal): RedirectResponse
    {
        $habit = $goal->habits()->create($this->validated($request));
        $goal->recordRoadmapChange("Added the habit \"{$habit->title}\" ({$habit->cadence()})");

        return $this->back($request, $goal, 'Habit added.');
    }

    /**
     * Change a habit's name, frequency, or preferred days.
     */
    public function update(Request $request, Habit $habit): RedirectResponse
    {
        Gate::authorize('update', $habit->goal);
        $before = "{$habit->title} ({$habit->cadence()})";

        $habit->update($this->validated($request));
        $habit->goal->recordRoadmapChange("Changed the habit \"{$before}\" to \"{$habit->title}\" ({$habit->cadence()})");

        return $this->back($request, $habit->goal, 'Habit updated.');
    }

    /**
     * Retire a habit. It stops counting from now on; past GoalWeeks keep their results.
     */
    public function destroy(Request $request, Habit $habit): RedirectResponse
    {
        Gate::authorize('update', $habit->goal);

        $habit->forceFill(['archived_at' => now()])->save();
        $habit->goal->recordRoadmapChange("Retired the habit \"{$habit->title}\" ({$habit->cadence()})");

        return $this->back($request, $habit->goal, 'Habit retired.');
    }

    /**
     * Mark a habit done for a day, or undo it. Works for any day of a GoalWeek still open for check-ins.
     */
    public function log(Request $request, Habit $habit): RedirectResponse
    {
        Gate::authorize('update', $habit->goal);
        abort_if($habit->archived_at !== null, 403, 'This habit is retired.');

        $day = $request->filled('date') ? Clock::today()->setDateFrom($request->date('date')) : Clock::today();
        abort_if($day->gt(Clock::today()), 403, "You can't check in for a day that hasn't happened yet.");

        // Fair play: check-ins are for today or yesterday, never further back.
        if ($day->lt(Clock::today()->subDays(self::BACKFILL_DAYS))) {
            return $this->back($request, $habit->goal, 'Check-ins are for today or yesterday. Earlier days stay as they are: one more day on the line starts now.');
        }

        $week = $habit->goal->weeks()->with('goal')->get()->first(fn (RoadmapWeek $week) => $day->between($week->starts_on, $week->ends_on));
        abort_unless($week?->isCheckable(), 403, 'Check-ins for that day are closed.');

        $existing = $habit->logs()->whereDate('logged_on', $day)->first();
        $existing ? $existing->delete() : $habit->logs()->create(['logged_on' => $day]);

        return $this->back($request, $habit->goal);
    }

    /**
     * Validate a habit's fields.
     *
     * @return array{title: string, times_per_week: int, days: list<int>|null}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'times_per_week' => ['required', 'integer', 'between:1,7'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:1,7'],
        ], [
            'title.required' => 'Name the habit, like "Run" or "Study for 2 hours".',
        ]);

        $days = collect($validated['days'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all();

        return [
            'title' => trim($validated['title']),
            'times_per_week' => (int) $validated['times_per_week'],
            'days' => $days ?: null,
        ];
    }

    /**
     * Go back to the Clubhouse, the weekly planner, or the goal page, wherever the change was made.
     */
    private function back(Request $request, Goal $goal, ?string $status = null): RedirectResponse
    {
        $redirect = match ($request->input('return')) {
            'clubhouse' => redirect()->to(route('dashboard').'#goal-'.$goal->id),
            'gameplan' => redirect()->to(route('dashboard').'#gameplan'),
            'planner' => redirect()->route('planner.show'),
            default => redirect()->to(route('goals.show', $goal).'#habits'),
        };

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
