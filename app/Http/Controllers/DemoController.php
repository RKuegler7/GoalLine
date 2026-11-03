<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DemoWorld;
use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return $this->signIn($request, $world->create(), 'Welcome to the GoalLine demo. Look around: check off a task, cheer a post, or vote on the group task.');
    }

    /**
     * "Reset demo": throw away this visitor's world and build them a fresh copy.
     */
    public function reset(Request $request, DemoWorld $world): RedirectResponse
    {
        abort_unless(config('goalline.demo.enabled') && $request->user()?->isDemo(), 404);
        $sandbox = $request->user()->demo_sandbox;

        Auth::logout();
        User::where('demo_sandbox', $sandbox)->get()->each->delete();
        DemoWorld::pruneExpired();

        return $this->signIn($request, $world->create(), 'Your demo is reset: a fresh copy of the same world.');
    }

    private function signIn(Request $request, User $visitor, string $status): RedirectResponse
    {
        Usage::track($visitor, 'demo-started');

        Auth::login($visitor);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', $status);
    }
}
