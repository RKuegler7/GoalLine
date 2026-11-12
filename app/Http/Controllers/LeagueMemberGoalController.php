<?php

namespace App\Http\Controllers;

use App\Models\GoalIntake;
use App\Models\League;
use App\Services\LeagueGoalForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueMemberGoalController extends Controller
{
    /**
     * "Set your goal for <league>": for a member who is in a league without a goal to play (or whose goal ended).
     * The same goal step as joining, with the league goal ready to use. A goal still being set up resumes its questions.
     */
    public function show(Request $request, League $league): View|RedirectResponse
    {
        $member = $league->memberFor($request->user());
        $goal = $member->goal;

        if ($goal?->isActive() && $goal->intake?->isOpen()) {
            return redirect()->route('goals.intake', ['goal' => $goal, 'from' => 'joined']);
        }

        return view('leagues.my-goal', [
            'league' => $league->load('members.user', 'commissioner'),
            'goalAction' => route('leagues.my-goal.store', $league),
            'joinAction' => route('leagues.membership.update', $league),
            'joinMethod' => 'PUT',
            'goals' => $request->user()->goals()->inPlay()->whereKeyNot($member->goal_id ?? 0)->orderBy('target_date')->get(),
        ]);
    }

    /**
     * Set the goal, attach it to the membership, and go into the intake questions and the plan.
     */
    public function store(Request $request, League $league, LeagueGoalForm $form): RedirectResponse
    {
        $goal = $form->create($request, $league, $request->user());
        $league->memberFor($request->user())->update(['goal_id' => $goal->id]);
        GoalIntake::begin($goal);

        return redirect()->route('goals.intake', ['goal' => $goal, 'from' => 'joined'])
            ->with('status', "Your goal for {$league->name} is set. A few questions, then your plan.");
    }
}
