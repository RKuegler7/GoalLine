<?php

namespace App\Http\Controllers;

use App\Models\RoadmapWeek;
use App\Services\FairPlay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SmashedConfirmationController extends Controller
{
    /**
     * The matchup opponent vouches for a Smashed week that has no proof, so it counts in full.
     */
    public function store(Request $request, RoadmapWeek $week, FairPlay $fairPlay): RedirectResponse
    {
        abort_unless($fairPlay->canConfirmSmashed($request->user(), $week), 403);
        abort_if($week->isLocked(), 403, 'This GoalWeek is final.');

        $week->forceFill(['smashed_confirmed_by' => $request->user()->id, 'smashed_confirmed_at' => now()])->save();

        return back()->with('status', 'Confirmed. Their Smashed week counts in full. Good sport.');
    }
}
