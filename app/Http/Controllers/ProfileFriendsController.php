<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Someone's friends list, with search and pages. Friends are mutual (both agree), so there's one list, never separate
 * Followers and Following lists, which would be identical. Respects "Who can see my friends list", hides people who
 * turned off "Let people find me by name" (unless you know them), and never shows an email.
 */
class ProfileFriendsController extends Controller
{
    public const PER_PAGE = 20;

    public function __invoke(Request $request, User $user): View
    {
        $viewer = $request->user();
        abort_unless(Gate::allows('viewFriends', $user), 403, 'Their friends list is private.');

        $search = trim((string) $request->query('q'));
        $mine = $viewer->friendIds();

        $friends = User::query()
            ->whereIn('id', $user->friendIds())
            ->when(! $viewer->is($user), fn ($query) => $query->where(fn ($visible) => $visible->where('discoverable', true)->orWhereIn('id', $mine)->orWhere('id', $viewer->id)))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('name', 'like', '%'.addcslashes($search, '%_').'%')
                ->orWhere('username', 'like', '%'.addcslashes(ltrim($search, '@'), '%_').'%')))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view($request->ajax() || $request->boolean('partial') ? 'profile.partials.friends-list' : 'profile.friends', [
            'user' => $user,
            'friends' => $friends,
            'mutual' => $mine->flip(),
            'search' => $search,
            'isOwn' => $viewer->is($user),
        ]);
    }
}
