<?php

namespace App\Http\Controllers\Auth;

use App\EmailVerificationLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Explain that the email needs verifying before social and GoalLeague features unlock.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : view('auth.verify-email');
    }

    /**
     * The link from the verification email: expiry and signature in the path, so nothing between the inbox and the
     * app can strip them. Works signed in or out, in any browser.
     */
    public function confirm(Request $request, string $id, string $hash, string $expires, string $signature): RedirectResponse|Response
    {
        return $this->complete($request, $id, $hash, EmailVerificationLink::check($id, $hash, $expires, $signature));
    }

    /**
     * Links sent before the path-signed format (expiry and signature in the query string) keep working when intact.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse|Response
    {
        $status = match (true) {
            ! URL::hasCorrectSignature($request) => EmailVerificationLink::INVALID,
            ! URL::signatureHasNotExpired($request) => EmailVerificationLink::EXPIRED,
            default => EmailVerificationLink::VALID,
        };

        return $this->complete($request, $id, $hash, $status);
    }

    /**
     * Send the verification email again (signed in, from the "Verify your email" banner or page).
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard')->with('status', 'Your email is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link is on its way to '.$request->user()->email.'.');
    }

    /**
     * Send a new link from the "That link didn't work" page, signed out. The answer is the same whether or not the
     * address has an account, so it can't be used to find out who's signed up.
     */
    public function resendByEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']], ['email.required' => 'Enter the email you signed up with.']);
        $user = User::where('email', strtolower(trim($validated['email'])))->first();

        if ($user && ! $user->hasVerifiedEmail() && ! $user->isDemo()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('login')->with('status', 'If that address has a GoalLine account waiting to be verified, a new link is on its way. It works for 48 hours.');
    }

    /**
     * Verify the person in the link (by id and email hash, as Laravel does), then send them on: to the Clubhouse if
     * they're signed in as that person, otherwise to log in. A bad, expired or used-up link gets a friendly page.
     */
    private function complete(Request $request, string $id, string $hash, string $status): RedirectResponse|Response
    {
        $user = ctype_digit($id) ? User::find((int) $id) : null;
        $matches = $user && hash_equals(sha1($user->getEmailForVerification()), $hash);
        $signedIn = $request->user();

        if ($status === EmailVerificationLink::VALID && $matches) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                event(new Verified($user));
                $message = 'Email verified. GoalLeagues, Highlights, and friends are unlocked.';
            } else {
                $message = 'Your email is already verified. You\'re all set.';
            }

            return $signedIn?->is($user)
                ? redirect()->route('dashboard')->with('status', $message)
                : redirect()->route('login')->with('status', $message.' Log in to keep going.')->withInput(['email' => $user->email]);
        }

        // Already verified (an old link, a second click): nothing to fix.
        if ($matches && $user->hasVerifiedEmail()) {
            return $signedIn?->is($user)
                ? redirect()->route('dashboard')->with('status', 'Your email is already verified. You\'re all set.')
                : redirect()->route('login')->with('status', 'Your email is already verified. Log in to keep going.');
        }

        return response()->view('auth.link-problem', [
            'expired' => $status === EmailVerificationLink::EXPIRED && $matches,
            'signedInUnverified' => $signedIn && ! $signedIn->hasVerifiedEmail(),
            'email' => $matches ? $user->email : null,
        ], $status === EmailVerificationLink::EXPIRED ? 410 : 403);
    }
}
