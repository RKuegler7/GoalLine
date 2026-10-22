<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Notifications\LeagueMemberJoined;
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

        if ($league->memberFor($request->user())) {
            return redirect()->route('leagues.show', $league);
        }

        return view('leagues.join', [
            'league' => $league,
            'goals' => $request->user()->goals()->active()->orderBy('target_date')->get(),
        ]);
    }

    /**
     * Join a league with one of the person's active goals.
     */
    public function store(Request $request, string $code): RedirectResponse
    {
        $league = League::where('invite_code', $this->normalize($code))->with('members')->firstOrFail();

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

        $league->members()->create(['user_id' => $request->user()->id, 'goal_id' => $validated['goal_id']]);
        $league->announce($request->user()->name.' joined the league.');
        if (! $league->commissioner->is($request->user())) {
            $league->commissioner->notify(new LeagueMemberJoined($league, $request->user()));
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
     * Leave a league before kickoff.
     */
    public function destroy(Request $request, League $league): RedirectResponse
    {
        Gate::authorize('leave', $league);

        $league->memberFor($request->user())->delete();

        return redirect()->route('leagues.index')->with('status', 'You left '.$league->name.'.');
    }

    /**
     * Invite codes are read aloud and typed by hand: ignore case, spaces, and a missing dash.
     */
    private function normalize(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return strlen($code) === 8 ? substr($code, 0, 4).'-'.substr($code, 4) : $code;
    }
}
