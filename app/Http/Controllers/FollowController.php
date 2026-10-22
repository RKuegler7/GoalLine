<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FollowController extends Controller
{
    /**
     * Follow someone whose locker you can see.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, "You can't follow yourself.");
        Gate::authorize('viewProfile', $user);

        $request->user()->following()->syncWithoutDetaching([$user->id]);

        return back()->with('status', "You're following ".$user->name.'. Their highlights will show in your feed.');
    }

    /**
     * Stop following someone.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->following()->detach($user->id);

        return back()->with('status', 'Unfollowed '.$user->name.'.');
    }
}
