<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\League;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GoalNextStepController extends Controller
{
    /**
     * Right after setting a goal: play it with friends in a GoalLeague, join one with a code, or keep it solo.
     */
    public function show(Goal $goal): View
    {
        return view('goals.next', ['goal' => $goal]);
    }

    /**
     * Go where the choice leads: a new league already filled in from the goal, a league's join page with this goal
     * picked, or the goal itself, played against the line.
     */
    public function choose(Request $request, Goal $goal): RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', Rule::in(['create', 'join', 'solo'])],
            'code' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validated['choice'] === 'create') {
            return redirect()->route('leagues.create', ['goal' => $goal->id]);
        }

        if ($validated['choice'] === 'join') {
            $code = League::normalizeInviteCode((string) ($validated['code'] ?? ''));

            if (! League::where('invite_code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'No league matches that code. Check it and try again.']);
            }

            return redirect()->route('leagues.join', ['code' => $code, 'goal' => $goal->id]);
        }

        // Remembered for the session, so the Clubhouse stops asking about this goal.
        $request->session()->put('solo_goals', collect($request->session()->get('solo_goals', []))->push($goal->id)->unique()->values()->all());
        $status = "Going solo: it's you against the line. You can start or join a GoalLeague any time.";

        return $request->input('from') === 'clubhouse'
            ? redirect()->route('dashboard')->with('status', $status)
            : redirect()->route('goals.show', $goal)->with('status', $status);
    }
}
