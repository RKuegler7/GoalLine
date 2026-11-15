<?php

namespace App\Http\Controllers;

use App\Services\PublicListings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The full lists behind "Find a public GoalLeague" and "Find a public Challenge" in the GoalLeagues tab, with search
 * and filters. The same listings as Explore (PublicListings); rate limited.
 */
class PublicListingController extends Controller
{
    public function leagues(Request $request): View
    {
        $filters = PublicListings::filters($request);
        $viewer = $request->user();

        return view('leagues.public', [
            'leagues' => PublicListings::leagues($viewer, $filters, PublicListings::PAGE),
            'memberOf' => $viewer->leagues()->pluck('leagues.id')->flip(),
            'filters' => $filters,
            'example' => $viewer->isDemo(),
        ]);
    }

    public function challenges(Request $request): View
    {
        $filters = PublicListings::filters($request, challenges: true);
        $viewer = $request->user();

        return view('challenges.public', [
            'challenges' => PublicListings::challenges($viewer, $filters, PublicListings::PAGE),
            'memberOf' => $viewer->challenges()->pluck('challenges.id')->flip(),
            'filters' => $filters,
            'example' => $viewer->isDemo(),
        ]);
    }
}
