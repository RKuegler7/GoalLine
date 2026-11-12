<?php

namespace App\Services;

use App\Clock;
use App\GoalCategory;
use App\GoalExamples;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The goal step's form, shared by joining a league and by setting a goal for a league you're already in: the league
 * goal or your own version of it (date inside the season unless you choose otherwise, optional target result,
 * description, chips and why), or a different goal. Creates the goal; the caller attaches it.
 */
class LeagueGoalForm
{
    public function create(Request $request, League $league, User $user): Goal
    {
        $category = GoalCategory::tryFrom((string) $request->input('category')) ?? $league->category ?? GoalCategory::Other;
        $seasonEnd = $league->ends_on->toDateString();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(GoalCategory::class)],
            'target_date' => ['required', 'date', 'after:'.Clock::today()->toDateString()],
            'target_result' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:280'],
            'why' => ['nullable', 'string', 'max:1000'],
            'chips' => ['nullable', 'array'],
            'chips.*' => ['string', Rule::in(array_keys(GoalExamples::chips($category)))],
            'past_season' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Name the goal you\'ll play.',
            'target_date.after' => 'Pick a date after today.',
        ]);

        // A date past the season is fine, as long as they chose it knowing the matchups stop when the season does.
        if ($validated['target_date'] > $seasonEnd && ! $request->boolean('past_season')) {
            throw ValidationException::withMessages([
                'target_date' => "The league season ends {$league->ends_on->format('F j, Y')}. Pick a date by then, or tick \"Keep my later date\": your matchups end with the season, and you keep going against the line after it.",
            ]);
        }

        $context = collect([trim((string) ($validated['description'] ?? ''))])
            ->merge(collect($validated['chips'] ?? [])->map(fn (string $chip) => GoalExamples::chips($category)[$chip]))
            ->filter()
            ->implode(' ');

        return $user->goals()->create([
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'target_date' => $validated['target_date'],
            'why' => filled($validated['why'] ?? null) ? trim($validated['why']) : null,
            'target_result' => filled($validated['target_result'] ?? null) ? trim($validated['target_result']) : null,
            'context' => $context === '' ? null : Str::limit($context, 600, ''),
        ]);
    }
}
