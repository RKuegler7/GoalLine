<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
        $goal->recordRoadmapChange("Added the habit \"{$habit->label()}\" ({$habit->cadence()})");

        return $this->back($request, $goal, 'Habit added.');
    }

    /**
     * Change a habit's name, frequency, or preferred days.
     */
    public function update(Request $request, Habit $habit): RedirectResponse
    {
        Gate::authorize('update', $habit->goal);
        $before = "{$habit->label()} ({$habit->cadence()})";
        $validated = $this->validated($request);

        // Plan lock: a lower target would make this week's plan lighter after it locked.
        if ($validated['times_per_week'] < $habit->times_per_week && ($habit->logs()->exists() || $habit->created_at->lt(Clock::today()->startOfWeek()))) {
            return $this->back($request, $habit->goal, "This week's plan is locked, so a habit's target can't go down. Retire it and add the lighter version: this week keeps its plan, and the new one sets the pace from here.");
        }

        $habit->update($validated);
        $habit->goal->recordRoadmapChange("Changed the habit \"{$before}\" to \"{$habit->label()}\" ({$habit->cadence()})");

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

        // A check-off toggles. A number habit logs (or updates) the number, and an empty number undoes the day.
        // A tap says which state it wants; a double tap (or a second tab) changes nothing.
        if (! $habit->takesNumber() && (($request->input('intent') === 'done' && $existing) || ($request->input('intent') === 'open' && ! $existing))) {
            return $this->back($request, $habit->goal);
        }

        if (! $habit->takesNumber() || ! $request->filled('value')) {
            if (! $existing && $habit->takesNumber()) {
                return $this->back($request, $habit->goal, "Log a number for \"{$habit->title}\": it counts at {$habit->ruleLabel()}.");
            }

            $existing ? $existing->delete() : $habit->logs()->create(['logged_on' => $day]);

            return $this->back($request, $habit->goal, $existing ? 'Check-in undone.' : CheckInController::CHECKED_IN);
        }

        $value = (float) $request->validate(['value' => ['numeric', 'min:0', 'max:100000']], [
            'value.numeric' => 'Enter a number, like 2.5.',
        ])['value'];
        $met = $habit->meets($value);
        $existing
            ? $existing->update(['value' => $value, 'met' => $met])
            : $habit->logs()->create(['logged_on' => $day, 'value' => $value, 'met' => $met]);

        $logged = $habit->formatNumber($value).' '.$habit->unitLabel();

        return $this->back($request, $habit->goal, $met
            ? "Logged {$logged}. It counts: one more day on the line."
            : "Logged {$logged}. Your rule is {$habit->ruleLabel()}, so it doesn't count yet. Update it if you do more today.");
    }

    /**
     * Validate a habit's fields: its cadence (every day, specific days, or X times a week) and its rule for what
     * counts (a check-off, or a number or minutes with a threshold).
     *
     * @return array{title: string, cadence: string, times_per_week: int, days: list<int>|null, rule: string, threshold: float|null, unit: string|null}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'cadence' => ['nullable', Rule::in([Habit::DAILY, Habit::DAYS, Habit::WEEKLY])],
            'times_per_week' => ['required_unless:cadence,'.Habit::DAILY.','.Habit::DAYS, 'nullable', 'integer', 'between:1,7'],
            'days' => ['required_if:cadence,'.Habit::DAYS, 'nullable', 'array'],
            'days.*' => ['integer', 'between:1,7'],
            'rule' => ['nullable', Rule::in([Habit::CHECK, Habit::NUMBER, Habit::MINUTES])],
            'threshold' => ['required_if:rule,'.Habit::NUMBER.','.Habit::MINUTES, 'nullable', 'numeric', 'gt:0', 'max:100000'],
            'unit' => ['required_if:rule,'.Habit::NUMBER, 'nullable', 'string', 'max:20'],
        ], [
            'title.required' => 'Name the habit, like "Run" or "Study for 2 hours".',
            'days.required_if' => 'Pick the days it happens.',
            'threshold.required_if' => 'Set what counts, like 2 for "2+ miles".',
            'threshold.gt' => 'What counts has to be more than zero.',
            'unit.required_if' => 'Add a unit, like "miles" or "questions".',
        ]);

        $days = collect($validated['days'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all();
        $rule = $validated['rule'] ?? Habit::CHECK;
        $cadence = $validated['cadence'] ?? ((int) ($validated['times_per_week'] ?? 0) >= 7 ? Habit::DAILY : Habit::WEEKLY);

        return [
            'title' => trim($validated['title']),
            'cadence' => $cadence,
            // The weekly target: 7 every day, one per chosen day, or the number picked.
            'times_per_week' => match ($cadence) {
                Habit::DAILY => 7,
                Habit::DAYS => count($days),
                default => (int) $validated['times_per_week'],
            },
            'days' => $cadence === Habit::DAILY ? null : ($days ?: null),
            'rule' => $rule,
            'threshold' => $rule === Habit::CHECK ? null : (float) $validated['threshold'],
            'unit' => $rule === Habit::NUMBER ? trim((string) $validated['unit']) : null,
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
            'today' => redirect()->route('today'),
            'planner' => redirect()->route('planner.show'),
            default => redirect()->to(route('goals.show', $goal).'#habits'),
        };

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
