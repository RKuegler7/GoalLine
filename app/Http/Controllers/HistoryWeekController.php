<?php

namespace App\Http\Controllers;

use App\Models\LeagueResult;
use App\Models\RoadmapWeek;
use App\Services\History;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryWeekController extends Controller
{
    /**
     * One finished GoalWeek, read-only: its band, percent of plan, points, match result, and what got done. Only its
     * owner can open it; anyone else gets a plain "not found".
     */
    public function __invoke(Request $request, RoadmapWeek $week): View
    {
        $week->load('goal.weeks', 'goal.habits.logs', 'tasks');
        abort_unless($week->goal->user_id === $request->user()->id, 404);

        $row = (new History($request->user()))->week($week);
        abort_if($row === null, 404);

        /** @var LeagueResult|null $match */
        $match = $row['match'];

        return view('history.week', [
            'week' => $week,
            'row' => $row,
            'match' => $match,
            'matchupUrl' => $match ? route('leagues.matchups', [$match->league, $match->week]) : null,
        ]);
    }
}
