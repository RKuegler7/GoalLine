<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeInvite;
use App\Models\User;
use App\Notifications\ChallengeInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * In-app invites to a Challenge, by @username (the same as a GoalLeague's username invite). They land in the person's
 * inbox and Invites tab; joining still shows the rules first.
 */
class ChallengeInviteController extends Controller
{
    public function store(Request $request, Challenge $challenge): RedirectResponse
    {
        $who = ltrim(trim((string) $request->validateWithBag('invite', ['who' => ['required', 'string', 'max:255']], [
            'who.required' => 'Enter a username.',
        ])['who']), '@');
        $person = User::whereRaw('lower(username) = ?', [strtolower($who)])->where('demo_sandbox', $request->user()->demo_sandbox)->first();

        $problem = match (true) {
            $challenge->isOver() => 'This Challenge is over.',
            ! $person => 'No one has that username. Check the spelling.',
            $challenge->members()->where('user_id', $person->id)->exists() => "{$person->name} is already in.",
            $challenge->isFull() => 'This Challenge is full.',
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['who' => $problem])->errorBag('invite');
        }

        $invite = $challenge->invites()->firstOrNew(['invitee_id' => $person->id]);
        $invite->forceFill(['created_by' => $request->user()->id, 'declined_at' => null, 'accepted_at' => null])->save();
        $person->notify(new ChallengeInvited($challenge->withoutRelations(), $request->user()));

        return redirect()->to(route('challenges.show', $challenge).'#invite')->with('status', "Invited {$person->name}. They'll see it in their inbox.");
    }

    /**
     * Not for me: the invite leaves the Invites tab.
     */
    public function decline(Request $request, ChallengeInvite $invite): RedirectResponse
    {
        abort_unless($invite->invitee_id === $request->user()->id, 404);
        $invite->forceFill(['declined_at' => now()])->save();

        return redirect()->route('leagues.index', ['tab' => 'invites'])->with('status', 'Invite declined.');
    }
}
