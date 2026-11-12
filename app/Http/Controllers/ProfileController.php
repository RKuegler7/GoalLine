<?php

namespace App\Http\Controllers;

use App\Clock;
use App\GoalStatus;
use App\Http\Requests\ProfileRequest;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\Services\ActivityHeatmap;
use App\Services\LeagueStats;
use App\Services\LeagueTable;
use App\Services\ProfileStats;
use App\Services\StoredPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /** A week of private caching for profile photos. */
    private const CACHE = ['Cache-Control' => 'private, max-age=604800'];

    /**
     * The signed-in person's own profile.
     */
    public function show(Request $request): View
    {
        return $this->showUser($request, $request->user());
    }

    /**
     * Anyone's profile, if their visibility setting lets the viewer see it.
     */
    public function showUser(Request $request, User $user): View
    {
        if (Gate::denies('viewProfile', $user)) {
            return view('profile.private', ['user' => $user]);
        }

        $viewer = $request->user();

        return view('profile.show', [
            'stats' => new ProfileStats($user),
            // Days on the locker owner's clock, wherever the viewer is.
            'heatmap' => Clock::for($user, fn () => new ActivityHeatmap($user)),
            'isOwn' => $viewer->is($user),
            'leagueStats' => $this->leagueStats($request, $viewer, $user),
            'friendCount' => $user->friendIds()->count(),
            'mutualFriends' => $viewer->is($user) ? 0 : $viewer->friendIds()->intersect($user->friendIds())->count(),
            'canSeeFriends' => Gate::allows('viewFriends', $user),
            // GoalLeagues you both play in, with the goal they play there (titles only).
            'mutualLeagues' => $viewer->is($user) ? collect() : $user->leagues()->whereIn('leagues.id', $viewer->leagues()->select('leagues.id'))->get()
                ->map(fn ($league) => ['league' => $league, 'goal' => Goal::find($league->pivot->goal_id)?->title]),
            'highlights' => $user->highlights()->visibleTo($viewer)->with(['user', 'cheers.user', 'comments.user', 'task', 'league'])->limit(2)->get(),
        ]);
    }

    /**
     * Show the profile editing form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'featurableGoals' => $user->goals()->whereIn('status', [GoalStatus::Active, GoalStatus::Completed])->latest()->get(),
            'timezones' => self::timezoneOptions(),
        ]);
    }

    /**
     * Save profile details, settings, and photos.
     */
    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'username', 'email', 'bio', 'location', 'timezone', 'profile_visibility', 'discoverable', 'public_cards', 'friends_list_visibility']));
        if ($user->isDemo()) {
            $user->email = $user->getOriginal('email');
        }
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->featured_goal_id = $request->validated('featured_goal_id');
        $user->save();

        if ($request->hasFile('avatar')) {
            $user->replaceAvatar($request->file('avatar')->store('avatars', User::AVATAR_DISK), $request->file('avatar')->getMimeType());
        } elseif ($request->boolean('remove_avatar')) {
            $user->replaceAvatar(null);
        }

        if ($request->hasFile('cover')) {
            $user->replaceCover($request->file('cover')->store('covers', User::AVATAR_DISK), $request->file('cover')->getMimeType());
        } elseif ($request->boolean('remove_cover')) {
            $user->replaceCover(null);
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('profile.show')->with('status', $emailChanged ? 'Profile updated. Check your new email for a verification link.' : 'Profile updated.');
    }

    /**
     * Serve a member's profile photo.
     */
    public function avatar(User $user, StoredPhoto $photos): Response
    {
        // A page cached before the photo was removed still asks for it: show the initials, never a broken image.
        if (! $user->avatar_path) {
            return response(StoredPhoto::initialsSvg($user->initials()), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store']);
        }

        return $photos->serve(User::AVATAR_DISK, $user->avatar_path, $user->avatar_mime_type, self::CACHE, $user->initials(),
            fn () => $user->forceFill(['avatar_path' => null, 'avatar_mime_type' => null])->save());
    }

    /**
     * Serve a member's cover photo.
     */
    public function cover(User $user, StoredPhoto $photos): Response
    {
        abort_unless($user->cover_path, 404);

        return $photos->serve(User::AVATAR_DISK, $user->cover_path, $user->cover_mime_type, self::CACHE,
            forget: fn () => $user->forceFill(['cover_path' => null, 'cover_mime_type' => null])->save());
    }

    /**
     * The profile's Stats section: one league's numbers for this person, from the same services as the standings.
     * Only leagues the viewer is in too (or all of them on your own locker), picked with ?league=.
     *
     * @return array{leagues: Collection<int, League>, league: ?League, row: ?array<string, mixed>}
     */
    private function leagueStats(Request $request, User $viewer, User $user): array
    {
        $leagues = $viewer->is($user)
            ? $user->leagues()->orderByDesc('starts_on')->get()
            : $user->leagues()->whereIn('leagues.id', $viewer->leagues()->select('leagues.id'))->orderByDesc('starts_on')->get();
        $league = $leagues->firstWhere('id', $request->integer('league')) ?? $leagues->first();

        if (! $league) {
            return ['leagues' => $leagues, 'league' => null, 'row' => null];
        }

        $row = (new LeagueStats(new LeagueTable($league)))->standings()->first(fn (array $row) => $row['member']->user_id === $user->id);

        return ['leagues' => $leagues, 'league' => $league, 'row' => $row];
    }

    /**
     * Time zones for the picker: common US zones first, then every zone by region.
     *
     * @return array<string, list<string>>
     */
    public static function timezoneOptions(): array
    {
        $common = ['America/New_York', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu'];

        $all = collect(\DateTimeZone::listIdentifiers())
            ->groupBy(fn (string $zone) => str_contains($zone, '/') ? strstr($zone, '/', true) : 'Other')
            ->map(fn ($zones) => $zones->values()->all())
            ->all();

        return ['Common in the US' => $common] + $all;
    }
}
