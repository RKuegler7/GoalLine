<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    /**
     * Step 1: who you are on GoalLine.
     */
    public function you(Request $request): View
    {
        return view('welcome.you', [
            'user' => $request->user(),
            'timezones' => ProfileController::timezoneOptions(),
        ]);
    }

    /**
     * Save step 1.
     */
    public function saveYou(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['username' => strtolower(ltrim((string) $request->input('username'), '@'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._]+$/', Rule::unique('users')->ignore($user)],
            'timezone' => ['required', 'timezone:all'],
            'profile_visibility' => ['required', Rule::enum(ProfileVisibility::class)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'username.regex' => 'Use lowercase letters, numbers, dots, and underscores only.',
            'username.unique' => 'That username is taken.',
        ]);

        $user->update(collect($validated)->except('avatar')->all());

        if ($request->hasFile('avatar')) {
            $user->replaceAvatar($request->file('avatar')->store('avatars', User::AVATAR_DISK));
        }

        return redirect()->route('welcome.goal');
    }

    /**
     * Step 2: the first goal.
     */
    public function goal(Request $request): View
    {
        return view('welcome.goal', ['existing' => $request->user()->goals()->latest()->first()]);
    }

    /**
     * Step 3: the people to play with.
     */
    public function crew(Request $request): View
    {
        return view('welcome.crew', ['user' => $request->user()]);
    }

    /**
     * Step 4: how GoalWeeks and scoring work.
     */
    public function rules(): View
    {
        return view('welcome.rules');
    }

    /**
     * Finish the walkthrough and head into the Clubhouse.
     */
    public function finish(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['onboarded_at' => now()])->save();

        return redirect()->route('dashboard')->with('status', "You're in line. Welcome to the Clubhouse.");
    }
}
