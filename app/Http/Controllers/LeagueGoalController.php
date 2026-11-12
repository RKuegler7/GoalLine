<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\League;
use App\Models\LeagueMember;
use App\Notifications\LeagueGoalChanged;
use App\Rules\CleanName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class LeagueGoalController extends Controller
{
    /**
     * The owner edits the league goal: the template new members start from. Members are told in the app; their own
     * goals stay exactly as they are.
     */
    public function __invoke(Request $request, League $league): RedirectResponse
    {
        $validated = $request->validateWithBag('leagueGoal', [
            'goal_title' => ['required', 'string', 'max:120', new CleanName],
            'goal_target_date' => ['required', 'date', 'after_or_equal:'.Clock::today()->toDateString()],
            'goal_description' => ['nullable', 'string', 'max:280'],
            'goal_target_result' => ['nullable', 'string', 'max:80'],
        ], [
            'goal_title.required' => 'Name the league goal.',
            'goal_target_date.after_or_equal' => 'Pick today or a later date.',
        ]);

        $league->forceFill([
            'goal_title' => trim($validated['goal_title']),
            'goal_target_date' => $validated['goal_target_date'],
            'goal_description' => filled($validated['goal_description'] ?? null) ? trim($validated['goal_description']) : null,
            'goal_target_result' => filled($validated['goal_target_result'] ?? null) ? trim($validated['goal_target_result']) : null,
        ]);

        if (! $league->isDirty()) {
            return redirect()->route('leagues.show', $league);
        }

        $league->forceFill(['goal_updated_at' => now()])->save();
        $league->announce("{$request->user()->name} updated the league goal: {$league->goalSummary()}.");
        Notification::send(
            $league->members()->with('user')->where('user_id', '!=', $request->user()->id)->get()->map(fn (LeagueMember $member) => $member->user),
            new LeagueGoalChanged($league->withoutRelations()),
        );

        return redirect()->route('leagues.show', $league)->with('status', 'League goal updated. Members were told; their own goals stay as they are.');
    }
}
