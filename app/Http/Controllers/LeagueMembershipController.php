<?php

namespace App\Http\Controllers;

use App\Atomic;
use App\Models\League;
use App\Models\LeagueMember;
use App\Notifications\LeagueMemberJoined;
use App\Rules\CleanName;
use Illuminate\Database\UniqueConstraintViolationException;
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
     * Show a league's invite: who's in, when it kicks off, and which goal to bring.
     */
    public function create(Request $request, string $code): View|RedirectResponse
    {
        $league = League::where('invite_code', $this->normalize($code))->with('members.user', 'commissioner')->firstOrFail();
        abort_unless($league->commissioner->sharesWorldWith($request->user()), 404);

        if ($league->memberFor($request->user())) {
            return redirect()->route('leagues.show', $league);
        }

        // Coming back here after setting a goal.
        $request->session()->put('league_join_return', route('leagues.join', $league->invite_code));

        return view('leagues.join', [
            'league' => $league,
            'invite' => null,
            'joinAction' => route('leagues.join.store', $league->invite_code),
            'goals' => $request->user()->goals()->active()->orderBy('target_date')->get(),
        ]);
    }

    /**
     * Join a league with one of the person's active goals.
     */
    public function store(Request $request, string $code): RedirectResponse
    {
        $league = League::where('invite_code', $this->normalize($code))->with('members', 'commissioner')->firstOrFail();
        abort_unless($league->commissioner->sharesWorldWith($request->user()), 404);

        if ($league->memberFor($request->user())) {
            return redirect()->route('leagues.show', $league);
        }

        abort_unless($league->isJoinable(), 403, 'This league has already kicked off or is full.');

        $validated = $request->validate([
            'goal_id' => ['required', Rule::exists('goals', 'id')->where('user_id', $request->user()->id)->where('status', 'active')],
        ], [
            'goal_id.required' => 'Pick the goal you will play in this league.',
            'goal_id.exists' => 'Pick one of your active goals.',
        ]);

        // Locked on the league and re-checked, so a double submit joins once (and announces once).
        try {
            $joined = Atomic::run(function () use ($league, $request, $validated) {
                League::whereKey($league->id)->lockForUpdate()->first();

                if ($league->members()->where('user_id', $request->user()->id)->exists()) {
                    return false;
                }

                $league->members()->create(['user_id' => $request->user()->id, 'goal_id' => $validated['goal_id']]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $joined = false;
        }

        if (! $joined) {
            return redirect()->route('leagues.show', $league);
        }

        $league->announce($request->user()->name.' joined the league.');
        if (! $league->commissioner->is($request->user())) {
            $league->commissioner->notify(new LeagueMemberJoined($league->withoutRelations(), $request->user()));
        }

        $status = "You're in ".$league->name.'. Kickoff is '.$league->starts_on->format('l, F j').'.';

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
