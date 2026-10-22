<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoalRequest;
use App\Jobs\ReadPlanMaterial;
use App\Models\Goal;
use App\Models\PlanMaterial;
use App\RoadmapStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoalController extends Controller
{
    /**
     * Show the form for setting a new goal.
     */
    public function create(): View
    {
        return view('goals.create');
    }

    /**
     * Save a new goal for the current user.
     */
    public function store(GoalRequest $request): RedirectResponse
    {
        $goal = $request->user()->goals()->create($request->safe()->except(['material', 'material_note']));

        if ($request->hasFile('material')) {
            // Read the uploaded plan first, so the first roadmap is built around it.
            $material = PlanMaterial::storeUpload($goal, $request->file('material'), $request->validated('material_note'));
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Pending])->save();
            ReadPlanMaterial::dispatch($material, thenBuildRoadmap: true);
        } else {
            $goal->requestRoadmap();
        }

        if ($request->input('from') === 'welcome') {
            return redirect()->route('welcome.crew')->with('status', 'Goal set. Your roadmap is being built while you finish setting up.');
        }

        return redirect()->route('goals.show', $goal)->with('status', 'Goal set. You\'re in line.');
    }

    /**
     * Show a single goal.
     */
    public function show(Goal $goal): View
    {
        return view('goals.show', ['goal' => $goal->load('weeks.tasks', 'materials')]);
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
        $goal->update($request->safe()->except(['material', 'material_note']));

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
