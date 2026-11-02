<?php

namespace App\Http\Controllers;

use App\LeagueStatus;
use App\Models\League;
use App\Services\LeagueTable;
use App\Services\MatchupBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueMatchupController extends Controller
{
    /**
     * "GoalWeek X Matchup": every matchup for a league week as cards to swipe through, the viewer's first, scored
     * live until the week is final. Defaults to this week (or the last week once the season is over).
     */
    public function show(Request $request, League $league, ?int $week = null): View|RedirectResponse
    {
        if ($league->status() === LeagueStatus::Forming) {
            return redirect()->route('leagues.show', $league)->with('status', 'Matchups start at kickoff, '.$league->starts_on->format('l, F j').'.');
        }

        $latest = min($league->currentWeek() ?? $league->weeksCount(), $league->weeksCount());
        $week ??= $latest;
        abort_unless($week >= 1 && $week <= $latest, 404);

        $table = new LeagueTable($league);
        $table->settle();
        $board = new MatchupBoard($table, $week);

        return view('leagues.matchups', [
            'league' => $league,
            'week' => $week,
            'latest' => $latest,
            'final' => $table->isFinal($week),
            'cards' => $board->cards($league->memberFor($request->user())),
        ]);
    }
}
