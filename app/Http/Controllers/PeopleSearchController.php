<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PeopleSearchController extends Controller
{
    /**
     * Most results shown for one search.
     */
    public const MAX_RESULTS = 20;

    /**
     * Find people by name or username, or by exact email. People who turned off "Let people find me by name" only
     * turn up for their exact username or email. Results never include anyone's email.
     */
    public function index(Request $request): View
    {
        $me = $request->user();
        $query = trim((string) $request->query('q'));

        return view('people.search', [
            'query' => $query,
            'results' => self::search($me, $query),
            'inviteLeague' => $request->filled('league') ? $me->leagues()->with('members')->find($request->integer('league')) : null,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    public static function search(User $viewer, string $query): Collection
    {
        if (mb_strlen($query) < 2) {
            return new Collection;
        }

        $handle = mb_strtolower(ltrim($query, '@'));
        $like = '%'.addcslashes($handle, '%_\\').'%';

        return User::query()
            ->whereKeyNot($viewer->id)
            ->where(fn (Builder $match) => $match
                ->whereRaw('lower(username) = ?', [$handle])
                ->orWhereRaw('lower(email) = ?', [mb_strtolower($query)])
                ->orWhere(fn (Builder $byName) => $byName
                    ->where('discoverable', true)
                    ->where(fn (Builder $text) => $text->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(username) like ?', [$like]))))
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get(['id', 'name', 'username', 'avatar_path']);
    }

    /**
     * The viewer's leagues that can still take new members, for "Invite to league".
     *
     * @return Collection<int, League>
     */
    public static function invitableLeagues(User $viewer): Collection
    {
        return $viewer->leagues()->with('members')->get()->filter->isJoinable()->values();
    }
}
