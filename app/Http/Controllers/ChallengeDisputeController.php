<?php

namespace App\Http\Controllers;

use App\Atomic;
use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\ChallengeDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Disputes on Challenge check-ins: any other member can question a check-in from the last week with a short reason;
 * the Challenge owner decides. Upheld takes the check-in off (voided); dismissed keeps it. People decide, never the AI.
 */
class ChallengeDisputeController extends Controller
{
    public const WINDOW_DAYS = 7;

    public function store(Request $request, Challenge $challenge, ChallengeCheckIn $checkIn): RedirectResponse
    {
        $user = $request->user();
        abort_unless($checkIn->challenge_id === $challenge->id, 404);
        abort_unless($challenge->members()->where('user_id', $user->id)->exists(), 403);
        abort_if($checkIn->user_id === $user->id, 403, 'You can\'t dispute your own check-in.');
        abort_if($checkIn->status !== ChallengeCheckIn::DONE, 403, 'Only check-ins that count can be disputed.');
        abort_if($checkIn->checked_on->lt(Challenge::today()->subDays(self::WINDOW_DAYS)), 403, 'Disputes are open for a week after a check-in.');
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        $checkIn->disputes()->firstOrCreate(['disputer_id' => $user->id], ['reason' => $validated['reason'] ?? null]);

        return back()->with('status', 'Dispute sent. The Challenge owner will take a look.');
    }

    public function resolve(Request $request, Challenge $challenge, ChallengeDispute $dispute): RedirectResponse
    {
        abort_unless($dispute->checkIn->challenge_id === $challenge->id && $dispute->isOpen(), 404);
        $resolution = $request->validate(['resolution' => ['required', 'in:'.ChallengeDispute::UPHELD.','.ChallengeDispute::DISMISSED]])['resolution'];

        Atomic::run(function () use ($dispute, $resolution, $request) {
            $dispute->forceFill(['status' => ChallengeDispute::RESOLVED, 'resolution' => $resolution, 'resolved_by' => $request->user()->id, 'resolved_at' => now()])->save();

            if ($resolution === ChallengeDispute::UPHELD) {
                $dispute->checkIn->forceFill(['status' => ChallengeCheckIn::VOIDED])->save();
                // Everyone else's disputes on the same check-in are settled by this one.
                $dispute->checkIn->disputes()->where('status', ChallengeDispute::OPEN)
                    ->update(['status' => ChallengeDispute::RESOLVED, 'resolution' => $resolution, 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
            }
        });

        return redirect()->to(route('challenges.show', $challenge).'#disputes')->with('status', $resolution === ChallengeDispute::UPHELD
            ? 'Upheld. That check-in no longer counts.'
            : 'Dismissed. The check-in stands.');
    }
}
