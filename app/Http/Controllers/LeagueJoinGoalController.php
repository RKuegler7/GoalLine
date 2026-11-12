<?php

namespace App\Http\Controllers;

use App\Models\GoalIntake;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Services\LeagueGoalForm;
use App\Services\LeagueJoin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeagueJoinGoalController extends Controller
{
    public function __construct(private LeagueGoalForm $form) {}

    /**
     * The goal step from an invite link.
     */
    public function invite(Request $request, string $token, LeagueJoin $joiner): RedirectResponse
    {
        $invite = LeagueInvite::findUsable($token);
        $league = $invite?->league->load('members', 'commissioner');

        if (! $invite || ! $league->commissioner->sharesWorldWith($request->user())) {
            return redirect()->route('leagues.invite.show', $token);
        }

        return $this->join($request, $league, $invite, route('leagues.invite.show', $token), $joiner);
    }

    /**
     * The goal step from a typed code.
     */
    public function code(Request $request, string $code, LeagueJoin $joiner): RedirectResponse
    {
        $league = League::where('invite_code', League::normalizeInviteCode($code))->with('members', 'commissioner')->firstOrFail();
        abort_unless($league->commissioner->sharesWorldWith($request->user()), 404);

        return $this->join($request, $league, null, route('leagues.join', $league->invite_code), $joiner);
    }

    /**
     * Set the goal to play (the league goal, made your own, or a different one), join, and go straight into the
     * personalized intake questions; the plan is built from the answers like any new goal, with the same plan floor
     * and safety rules.
     */
    private function join(Request $request, League $league, ?LeagueInvite $invite, string $back, LeagueJoin $joiner): RedirectResponse
    {
        $user = $request->user();

        if ($league->memberFor($user)) {
            return redirect()->route('leagues.show', $league);
        }

        if (LeagueJoin::blocker($league) !== null) {
            return redirect()->to($back);
        }

        $goal = $this->form->create($request, $league, $user);

        if (! $joiner->join($league, $user, $goal, $invite)) {
            $goal->delete();

            return redirect()->route('leagues.show', $league);
        }

        GoalIntake::begin($goal);
        $request->session()->forget(['league_invite', 'league_join_return']);
        $user->onboarded_at ?? $user->forceFill(['onboarded_at' => now()])->save();

        return redirect()->route('goals.intake', ['goal' => $goal, 'from' => 'joined'])
            ->with('status', LeagueJoin::welcome($league).' A few questions about your goal, then your plan.');
    }
}
