<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueMember;
use App\Services\LeagueStats;
use App\Services\LeagueTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueStandingsController extends Controller
{
    /**
     * The standings: "Table" (the default) is one swipeable table with every column, in the official order unless a
     * column is picked (?col=, highest first; the page also sorts in place without a reload). "League stats" is the
     * league-wide highlights and the viewer's season chart. Members only.
     */
    public function index(Request $request, League $league): View
    {
        $stats = $this->stats($league);
        $me = $league->memberFor($request->user());
        $view = $request->query('view') === 'league' ? 'league' : 'table';
        $column = array_key_exists((string) $request->query('col'), LeagueStats::columns()) && LeagueStats::columns()[$request->query('col')]['value'] !== null
            ? $request->query('col')
            : null;

        return view('leagues.standings', [
            'league' => $league,
            'stats' => $stats,
            'rows' => $stats->statsRows($column),
            'view' => $view,
            'column' => $column,
            'summary' => $stats->summary(),
            'series' => $stats->series($me),
            'me' => $me,
        ]);
    }

    /**
     * One member's season in this league: record and its close calls, points by kind, average percent of plan, best
     * week, weeks on the line, bands, plan status and every week's result. Only league members can open it, and it
     * shows nothing private: no notes, reflections, intake answers or email.
     */
    public function show(Request $request, League $league, LeagueMember $member): View
    {
        $stats = $this->stats($league);
        $row = $stats->standings()->first(fn (array $row) => $row['member']->is($member));

        return view($request->ajax() ? 'leagues.partials.player-season' : 'leagues.standings-player', [
            'league' => $league,
            'member' => $row['member'],
            'row' => $row,
            'player' => $row['stats'],
        ]);
    }

    private function stats(League $league): LeagueStats
    {
        $league->load('members.user', 'members.goal.weeks.tasks', 'members.goal.habits.logs', 'results');

        return new LeagueStats(new LeagueTable($league));
    }
}
