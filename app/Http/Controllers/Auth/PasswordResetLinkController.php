<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Ask for the email address to send a reset link to.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a reset link. The response is the same whether or not the account exists, so emails can't be probed.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that email has a GoalLine account, a reset link is on its way. It works for 60 minutes.');
    }
}
