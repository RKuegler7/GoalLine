<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoalRequest;
use App\Jobs\ReadPlanMaterial;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\PlanMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    /**
     * Show the form for setting a new goal, optionally to play in one of the person's GoalLeagues.
     */
    public function create(Request $request): View
    {
        return view('goals.create', ['league' => $request->user()->leagues()->find($request->integer('league'))]);
    }

    /**
     * Save a new goal for the current user, then ask the goal-specific questions its roadmap is built from.
     * Set from a GoalLeague, it becomes the person's goal in that league.
     */
    public function store(GoalRequest $request): RedirectResponse
    {
        $goal = $request->user()->goals()->create($request->safe()->except(['material', 'material_note', 'league_id']));
        $league = $request->user()->leagues()->find($request->validated('league_id'));
        $league?->memberFor($request->user())->update(['goal_id' => $goal->id]);

        if ($request->hasFile('material')) {
            // Read the uploaded plan while the questions are answered, so the first roadmap is built around both.
            $material = PlanMaterial::storeUpload($goal, $request->file('material'), $request->validated('material_note'));
            ReadPlanMaterial::dispatch($material, thenBuildRoadmap: true);
        }

        GoalIntake::begin($goal);

        return redirect()->route('goals.intake', [
            'goal' => $goal,
            'from' => $league ? 'league_home' : (in_array($request->input('from'), ['welcome', 'league'], true) ? $request->input('from') : 'new'),
        ]);
    }

    /**
     * Show a single goal.
     */
    public function show(Goal $goal): View
    {
        return view('goals.show', ['goal' => $goal->load('weeks.tasks', 'weeks.review', 'materials', 'habits.logs', 'intake')]);
    }

    /**
     * Show the form for editing a goal.
     */
    public function edit(Goal $goal): View
    {
        return view('goals.edit', ['goal' => $goal]);
    }

    /**
     * Save changes to a goal.
     */
    public function update(GoalRequest $request, Goal $goal): RedirectResponse
    {
        $goal->update($request->safe()->except(['material', 'material_note', 'league_id']));

        return redirect()->route('goals.show', $goal)->with('status', 'Goal updated.');
    }

    /**
     * Ask whether the person really wants to leave this goal.
     */
    public function leave(Goal $goal): View
    {
        return view('goals.leave', ['goal' => $goal->load('weeks')]);
    }

    /**
     * Stop pursuing a locked-in goal while keeping its history.
     */
    public function retire(Goal $goal): RedirectResponse
    {
        $goal->retire();

        return redirect()->route('dashboard')->with('status', 'Goal retired. Its history is still in your clubhouse.');
    }

    /**
     * Remove a goal during its trial.
     */
    public function destroy(Goal $goal): RedirectResponse
    {
        $goal->delete();

        return redirect()->route('dashboard')->with('status', 'Goal removed.');
    }
}
