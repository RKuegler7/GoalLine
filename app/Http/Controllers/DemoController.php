<?php

namespace App\Http\Controllers;

use App\Services\DemoWorld;
use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /**
     * One click into GoalLine: a fresh demo world (friends, two GoalLeagues mid-season, a challenge, posts) and
     * the visitor signed in to it. No invite code, no email, nothing that touches real members.
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

        $visitor = $world->create();
        Usage::track($visitor, 'demo-started');

        Auth::login($visitor);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome to the GoalLine demo. Look around: check off a task, cheer a post, or vote on the group task.');
    }
}
