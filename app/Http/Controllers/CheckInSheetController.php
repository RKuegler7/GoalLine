<?php

namespace App\Http\Controllers;

use App\Services\TodaysGameplan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckInSheetController extends Controller
{
    /**
     * The "Check in" sheet's contents: today's tasks and habits with one-tap check-offs, as a fragment the sheet loads
     * over whatever page is open. Its forms refresh from here, so checking in never leaves the page.
     */
    public function __invoke(Request $request): View
    {
        $goals = $request->user()->goals()->active()->with('weeks.tasks', 'habits.logs')->get();

        return view('partials.check-in-sheet', ['gameplan' => new TodaysGameplan($goals, $request->user())]);
    }
}
