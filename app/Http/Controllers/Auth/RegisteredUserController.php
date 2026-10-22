<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Show the sign-up form.
     */
    public function create(Request $request): View
    {
        return view('auth.register', [
            'inviteOnly' => config('goalline.beta.invite_only'),
            'invite' => $request->query('invite'),
        ]);
    }

    /**
     * Create the account and log the new user in.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except('invite'));

            if ($request->needsInvite()) {
                // Claim a use of the code atomically, so a code can't be used past its limit.
                $invite = Invite::usable($request->input('invite'));
                $claimed = $invite && Invite::whereKey($invite->id)->whereNull('revoked_at')->whereColumn('uses', '<', 'max_uses')->increment('uses');

                if (! $claimed) {
                    throw ValidationException::withMessages(['invite' => 'That invite code was just used up. Ask for a new one.']);
                }

                $user->forceFill(['invite_id' => $invite->id])->save();
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('welcome.show');
    }
}
