<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Models\User;
use App\Services\ProfileStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Show the person's profile: stats, form, trophy case, and goals.
     */
    public function show(Request $request): View
    {
        return view('profile.show', ['stats' => new ProfileStats($request->user())]);
    }

    /**
     * Show the profile editing form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Save profile details and photo.
     */
    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update($request->safe()->only(['name', 'username', 'bio', 'location']));

        if ($request->hasFile('avatar')) {
            $user->replaceAvatar($request->file('avatar')->store('avatars', User::AVATAR_DISK));
        } elseif ($request->boolean('remove_avatar')) {
            $user->replaceAvatar(null);
        }

        return redirect()->route('profile.show')->with('status', 'Profile updated.');
    }

    /**
     * Serve a member's profile photo.
     */
    public function avatar(User $user): StreamedResponse
    {
        abort_unless($user->avatar_path, 404);

        return Storage::disk(User::AVATAR_DISK)->response($user->avatar_path, headers: [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
