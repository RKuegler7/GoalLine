<?php

namespace App\Http\Controllers;

use App\Badge;
use App\Models\League;
use App\Services\LeagueTable;
use App\Services\SeasonRecap;
use Illuminate\View\View;

class LeagueRecapController extends Controller
{
    /**
     * The league's recaps: season awards and the final table once it's over, and every settled week.
     */
    public function __invoke(League $league): View
    {
        $table = new LeagueTable($league);
        $table->settle();
        $recap = new SeasonRecap($league, $table);

        return view('leagues.recap', [
            'league' => $league,
            'table' => $table,
            'recap' => $recap,
            'complete' => $recap->isComplete(),
            'awards' => $league->awards()->with('user')->get()->sortBy(fn ($award) => array_search($award->badge, Badge::leagueAwards(), true))->values(),
            'weeks' => $league->results->pluck('week')->unique()->sortDesc()->values()->map(fn (int $week) => $recap->week($week)),
        ]);
    }
}
