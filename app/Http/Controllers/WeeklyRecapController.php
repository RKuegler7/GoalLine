<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Services\WeeklyRecap;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeeklyRecapController extends Controller
{
    /**
     * Your week in GoalLine. ?week=last shows the previous week.
     */
    public function __invoke(Request $request): View
    {
        $day = $request->query('week') === 'last' ? CarbonImmutable::parse(Clock::today()->toDateString())->subWeek() : null;

        return view('recap.week', ['recap' => new WeeklyRecap($request->user(), $day)]);
    }
}
