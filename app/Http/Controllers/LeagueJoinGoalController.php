<?php

namespace App\Http\Controllers;

use App\Clock;
use App\GoalCategory;
use App\GoalExamples;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Services\LeagueJoin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeagueJoinGoalController extends Controller
{
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

        $category = GoalCategory::tryFrom((string) $request->input('category')) ?? $league->category ?? GoalCategory::Other;
        $seasonEnd = $league->ends_on->toDateString();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(GoalCategory::class)],
            'target_date' => ['required', 'date', 'after:'.Clock::today()->toDateString()],
            'target_result' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:280'],
            'why' => ['nullable', 'string', 'max:1000'],
            'chips' => ['nullable', 'array'],
            'chips.*' => ['string', Rule::in(array_keys(GoalExamples::chips($category)))],
            'past_season' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Name the goal you\'ll play.',
            'target_date.after' => 'Pick a date after today.',
        ]);

        // A date past the season is fine, as long as they chose it knowing the matchups stop when the season does.
        if ($validated['target_date'] > $seasonEnd && ! $request->boolean('past_season')) {
            throw ValidationException::withMessages([
                'target_date' => "The league season ends {$league->ends_on->format('F j, Y')}. Pick a date by then, or tick \"Keep my later date\": your matchups end with the season, and you keep going against the line after it.",
            ]);
        }

        $context = collect([trim((string) ($validated['description'] ?? ''))])
            ->merge(collect($validated['chips'] ?? [])->map(fn (string $chip) => GoalExamples::chips($category)[$chip]))
            ->filter()
            ->implode(' ');

        $goal = $user->goals()->create([
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'target_date' => $validated['target_date'],
            'why' => filled($validated['why'] ?? null) ? trim($validated['why']) : null,
            'target_result' => filled($validated['target_result'] ?? null) ? trim($validated['target_result']) : null,
            'context' => $context === '' ? null : Str::limit($context, 600, ''),
        ]);

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
