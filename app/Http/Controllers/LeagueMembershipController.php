<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueMember;
use App\PendingInvite;
use App\Rules\CleanName;
use App\Services\LeagueJoin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeagueMembershipController extends Controller
{
    /**
     * Look up a league from a typed invite code.
     */
    public function find(Request $request): RedirectResponse
    {
        $code = $this->normalize((string) $request->input('code'));

        if (! League::where('invite_code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'No league matches that code. Check it and try again.']);
        }

        return redirect()->route('leagues.join', $code);
    }

    /**
     * Show a league's invite from its code: who's in, the league goal, and the goal step. A full or finished league
     * gets a friendly page with other options.
     */
    public function create(Request $request, string $code): View|RedirectResponse
    {
        $league = League::where('invite_code', $this->normalize($code))->with('members.user', 'commissioner')->first();

        if (! $league || ! $league->commissioner->sharesWorldWith($request->user())) {
            return view('leagues.invite-problem', ['reason' => 'missing', 'league' => null]);
        }

        if ($league->memberFor($request->user())) {
            return redirect()->route('leagues.show', $league);
        }

        // Coming back here after setting a goal, or after verifying an email.
        $request->session()->put('league_join_return', route('leagues.join', $league->invite_code));
        PendingInvite::remember($request->user(), route('leagues.join', $league->invite_code));

        return view('leagues.join', [
            'league' => $league,
            'invite' => null,
            'joinAction' => route('leagues.join.store', $league->invite_code),
            'goalAction' => route('leagues.join.goal', $league->invite_code),
            'goals' => $request->user()->goals()->inPlay()->orderBy('target_date')->get(),
        ]);
    }

    /**
     * Join a league with one of the person's active goals.
     */
    public function store(Request $request, string $code, LeagueJoin $joiner): RedirectResponse
    {
        $league = League::where('invite_code', $this->normalize($code))->with('members', 'commissioner')->firstOrFail();
        abort_unless($league->commissioner->sharesWorldWith($request->user()), 404);

        if ($league->memberFor($request->user())) {
            return redirect()->route('leagues.show', $league);
        }

        if (LeagueJoin::blocker($league) !== null) {
            return redirect()->route('leagues.join', $league->invite_code);
        }

        $validated = $request->validate([
            'goal_id' => ['required', Rule::exists('goals', 'id')->where('user_id', $request->user()->id)->where('status', 'active')],
        ], [
            'goal_id.required' => 'Pick the goal you will play in this league.',
            'goal_id.exists' => 'Pick one of your active goals.',
        ]);

        if (! $joiner->join($league, $request->user(), Goal::find($validated['goal_id']))) {
            return redirect()->route('leagues.show', $league);
        }

        $status = LeagueJoin::welcome($league);

        // Joining during the welcome walkthrough returns to it.
        return $request->user()->onboarded_at
            ? redirect()->route('leagues.show', $league)->with('status', $status)
            : redirect()->route('welcome.crew')->with('status', $status);
    }

    /**
     * Switch the goal a member plays in the league, for example after completing one. Settled weeks don't change.
     */
    public function update(Request $request, League $league): RedirectResponse
    {
        $validated = $request->validate([
            'goal_id' => ['required', Rule::exists('goals', 'id')->where('user_id', $request->user()->id)->where('status', 'active')],
        ]);

        $league->memberFor($request->user())->update(['goal_id' => $validated['goal_id']]);

        return redirect()->route('leagues.show', $league)->with('status', 'League goal updated.');
    }

    /**
     * Set or clear your team name in this league (up to 24 characters, kept friendly). Blank goes back to your name.
     */
    public function teamName(Request $request, League $league): RedirectResponse
    {
        $member = $league->memberFor($request->user());
        abort_unless($member, 403);

        $validated = $request->validateWithBag('teamName', [
            'team_name' => ['nullable', 'string', 'max:'.LeagueMember::MAX_TEAM_NAME, new CleanName],
        ], [
            'team_name.max' => 'Team names can be up to '.LeagueMember::MAX_TEAM_NAME.' characters.',
        ]);

        $name = trim((string) ($validated['team_name'] ?? ''));
        $member->forceFill(['team_name' => $name === '' ? null : $name])->save();

        return redirect()->route('leagues.show', $league)->with('status', $name === '' ? 'Team name cleared. The scoreboard shows your name.' : "You're playing as {$name}.");
    }

    /**
     * Leave a league before kickoff.
     */
    public function destroy(Request $request, League $league): RedirectResponse
    {
        Gate::authorize('leave', $league);

        $league->memberFor($request->user())->delete();

        return redirect()->route('leagues.index')->with('status', 'You left '.$league->name.'.');
    }

    private function normalize(string $code): string
    {
        return League::normalizeInviteCode($code);
    }
}
