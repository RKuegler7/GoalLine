<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoalRequest;
use App\Models\Goal;
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
        $goal = $request->user()->goals()->create($request->validated());
        $goal->requestRoadmap();

        return redirect()->route('goals.show', $goal)->with('status', 'Goal set. You\'re in line.');
    }

    /**
     * Show a single goal.
     */
    public function show(Goal $goal): View
    {
        return view('goals.show', ['goal' => $goal->load('weeks.tasks')]);
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
        $goal->update($request->validated());

        return redirect()->route('goals.show', $goal)->with('status', 'Goal updated.');
    }

    /**
     * Remove a goal.
     */
    public function destroy(Goal $goal): RedirectResponse
    {
        $goal->delete();

        return redirect()->route('dashboard')->with('status', 'Goal removed.');
    }
}
