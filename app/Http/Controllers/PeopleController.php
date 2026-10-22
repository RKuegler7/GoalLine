<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeopleController extends Controller
{
    /**
     * Find people to follow by name or username. Only lockers the searcher may see show up.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $search = trim((string) $request->query('q', ''));
        $mates = $viewer->leagueMateIds();

        $people = $search === '' ? collect() : User::query()
            ->whereKeyNot($viewer->id)
            ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('username', 'like', '%'.ltrim($search, '@').'%'))
            ->where(fn ($query) => $query
                ->where('profile_visibility', ProfileVisibility::Everyone)
                ->orWhere(fn ($query) => $query->whereIn('id', $mates)->where('profile_visibility', ProfileVisibility::Leagues)))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return view('people.index', [
            'search' => $search,
            'people' => $people,
            'followingIds' => $viewer->following()->pluck('users.id'),
        ]);
    }
}
