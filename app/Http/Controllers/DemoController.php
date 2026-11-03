<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DemoWorld;
use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DemoController extends Controller
{
    /**
     * One click into GoalLine: a fresh demo world (friends, two GoalLeagues mid-season, a challenge, posts) and
     * the visitor signed in to it. No invite code, no email, nothing that touches real members. Expired demo worlds
     * are cleaned up right here, so cleanup never depends on the scheduler.
     */
    public function start(Request $request, DemoWorld $world): RedirectResponse
    {
        abort_unless(config('goalline.demo.enabled'), 404);
        $current = $request->user();

        if ($current && ! $current->isDemo()) {
            return redirect()->route('dashboard')->with('status', "You're signed in to your own account. Log out first to try the demo.");
        }

        if ($current) {
            Auth::logout();
        }

        DemoWorld::pruneExpired();

        return $this->build($request, $world, 'Welcome to the GoalLine demo. Look around: check off a task, cheer a post, or vote on the group task.');
    }

    /**
     * "Reset demo": throw away this visitor's world and build them a fresh copy.
     */
    public function reset(Request $request, DemoWorld $world): RedirectResponse
    {
        abort_unless(config('goalline.demo.enabled') && $request->user()?->isDemo(), 404);
        $sandbox = $request->user()->demo_sandbox;

        Auth::logout();
        User::where('demo_sandbox', $sandbox)->get()->each(fn (User $user) => DemoWorld::remove($user));
        DemoWorld::pruneExpired();

        return $this->build($request, $world, 'Your demo is reset: a fresh copy of the same world.');
    }

    /**
     * Build a demo world and sign the visitor in. If building fails, the error is logged for the admins and the
     * visitor gets a friendly note instead of an error page.
     */
    private function build(Request $request, DemoWorld $world, string $status): RedirectResponse
    {
        try {
            $visitor = $world->create();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->with('status', 'The demo couldn\'t start just now. Please try again in a minute.');
        }

        return $this->signIn($request, $visitor, $status);
    }

    private function signIn(Request $request, User $visitor, string $status): RedirectResponse
    {
        Usage::track($visitor, 'demo-started');

        Auth::login($visitor);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', $status);
    }
}
