<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\PendingInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    /**
     * Show the log-in form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Log the user in.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->validated(), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those details don\'t match an account.',
            ]);
        }

        $request->session()->regenerate();

        // Signing in from a league invite: remember it with the account, in case the email still needs verifying.
        if ($request->session()->has('league_invite')) {
            PendingInvite::remember($request->user(), $request->session()->get('league_join_return'));
        }

        return redirect()->intended(PendingInvite::url($request->user()) ?? route('dashboard'));
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
