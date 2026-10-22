<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Ask the person to confirm, with their password, that they want their account gone.
     */
    public function confirmDelete(): View
    {
        return view('account.delete');
    }

    /**
     * Delete the account and everything in it: goals, roadmaps, uploads, photos, highlights, and memberships.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'That password is not right.',
        ]);

        $user = $request->user();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
