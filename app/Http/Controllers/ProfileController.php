<?php

namespace App\Http\Controllers;

use App\GoalStatus;
use App\Http\Requests\ProfileRequest;
use App\Models\Highlight;
use App\Models\User;
use App\Services\ProfileStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
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
        $following = $viewer->follows($user);

        return view('profile.show', [
            'stats' => new ProfileStats($user),
            'isOwn' => $viewer->is($user),
            'following' => $following,
            'isFriend' => $following && $user->follows($viewer),
            'friends' => $user->friends(),
            'highlights' => $user->highlights()->with(['user', 'cheers.user'])->limit(4)->get()
                ->filter(fn (Highlight $highlight) => Gate::allows('view', $highlight))
                ->values(),
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
        $user->fill($request->safe()->only(['name', 'username', 'email', 'bio', 'location', 'timezone', 'profile_visibility']));
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->featured_goal_id = $request->validated('featured_goal_id');
        $user->save();

        if ($request->hasFile('avatar')) {
            $user->replaceAvatar($request->file('avatar')->store('avatars', User::AVATAR_DISK));
        } elseif ($request->boolean('remove_avatar')) {
            $user->replaceAvatar(null);
        }

        if ($request->hasFile('cover')) {
            $user->replaceCover($request->file('cover')->store('covers', User::AVATAR_DISK));
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
    public function avatar(User $user): StreamedResponse
    {
        abort_unless($user->avatar_path, 404);

        return $this->photo($user->avatar_path);
    }

    /**
     * Serve a member's cover photo.
     */
    public function cover(User $user): StreamedResponse
    {
        abort_unless($user->cover_path, 404);

        return $this->photo($user->cover_path);
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

    /**
     * Stream a stored photo with a week of private caching.
     */
    private function photo(string $path): StreamedResponse
    {
        return Storage::disk(User::AVATAR_DISK)->response($path, headers: [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
