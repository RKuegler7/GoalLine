<?php

namespace App\Http\Controllers;

use App\Jobs\CoachWeekReview;
use App\Models\RoadmapWeek;
use App\Models\WeekReview;
use App\RoadmapStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WeekReviewController extends Controller
{
    /**
     * The end-of-week review: how it went, a quick reflection, and the coach's tip for the week ahead.
     */
    public function show(RoadmapWeek $week): View|RedirectResponse
    {
        Gate::authorize('update', $week->goal);
        $week->load('tasks', 'review', 'goal.habits.logs');

        if (! $week->review && ! $week->isReviewable()) {
            return redirect()->to(route('goals.show', $week->goal).'#goalweek-'.$week->position)
                ->with('status', 'The review opens on the last weekend of the GoalWeek.');
        }

        return view('weeks.review', ['week' => $week, 'review' => $week->review]);
    }

    /**
     * Save the reflection and ask the coach for a tip. Saving again (or retrying) asks again.
     */
    public function store(Request $request, RoadmapWeek $week): RedirectResponse
    {
        Gate::authorize('update', $week->goal);
        abort_unless($week->isReviewable() || $week->review, 403, 'This GoalWeek is not open for review.');

        $validated = $request->validate([
            'feeling' => ['nullable', 'integer', 'between:1,5'],
            'went_well' => ['nullable', 'string', 'max:'.WeekReview::MAX_LENGTH],
            'in_the_way' => ['nullable', 'string', 'max:'.WeekReview::MAX_LENGTH],
        ]);

        $review = $week->review()->firstOrNew();

        if (! $request->boolean('retry')) {
            $review->fill([
                'feeling' => $validated['feeling'] ?? null,
                'went_well' => filled($validated['went_well'] ?? null) ? trim($validated['went_well']) : null,
                'in_the_way' => filled($validated['in_the_way'] ?? null) ? trim($validated['in_the_way']) : null,
            ]);
        }

        $review->forceFill(['coach_status' => RoadmapStatus::Pending])->save();
        CoachWeekReview::dispatch($review);

        return redirect()->route('goalweeks.review', $week);
    }
}
