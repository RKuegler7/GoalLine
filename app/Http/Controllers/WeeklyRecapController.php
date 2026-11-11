<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Services\RecapNarrator;
use App\Services\WeeklyRecap;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeeklyRecapController extends Controller
{
    /**
     * Your week in GoalLine. ?week=last shows the previous week, with its short summary at the top.
     */
    public function __invoke(Request $request, RecapNarrator $narrator): View
    {
        $today = CarbonImmutable::parse(Clock::today()->toDateString());
        $day = $request->query('week') === 'last' ? $today->subWeek() : null;
        $recap = new WeeklyRecap($request->user(), $day);

        return view('recap.week', [
            'recap' => $recap,
            'narration' => $recap->sunday->lt($today) ? $narrator->forRecap($recap) : null,
        ]);
    }
}
