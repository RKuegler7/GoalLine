<?php

namespace App\Http\Controllers;

use App\Services\BandProgress;
use App\Services\DailyStreak;
use App\Services\TodaysGameplan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TodayController extends Controller
{
    /**
     * Today: everything due today with one-tap check-offs and quick number entry, this week's habits as a grid
     * (days across, habits down), and how many days you've been on the line this week.
     */
    public function show(Request $request): View
    {
        return view('today', [
            'gameplan' => $this->gameplan($request),
            'streak' => new DailyStreak($request->user()),
        ]);
    }

    /**
     * "Mark all done": every simple check-off on today's list, in one tap. Anything that tracks a number still
     * needs its number.
     */
    public function markAllDone(Request $request): RedirectResponse
    {
        $gameplan = $this->gameplan($request);
        $open = $gameplan->simpleOpen();

        // Every GoalWeek this touches, before and after: the biggest band-up is celebrated.
        $weeks = collect($gameplan->entries)->pluck('week')->filter()->unique('id');
        $bandUp = BandProgress::aroundMany($weeks, function () use ($open, $gameplan) {
            foreach ($open['tasks'] as $task) {
                $task->forceFill(['completed_at' => now()])->save();
            }

            foreach ($open['habits'] as $habit) {
                $habit->logs()->create(['logged_on' => $gameplan->today]);
            }
        });

        $count = $open['tasks']->count() + $open['habits']->count();
        $status = $count > 0
            ? 'Marked '.$count.' '.Str::plural('thing', $count).' done. One more day on the line.'
            : 'Everything simple is already done. Anything with a number needs its number.';

        return ($request->input('return') === 'gameplan'
            ? redirect()->to(route('dashboard').'#gameplan')
            : redirect()->route('today'))->with('status', $status)->with('band_up', $bandUp);
    }

    private function gameplan(Request $request): TodaysGameplan
    {
        $goals = $request->user()->goals()->active()->with('weeks.tasks', 'habits.logs')->get();

        return new TodaysGameplan($goals, $request->user());
    }
}
