<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\Nudged;
use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NudgeController extends Controller
{
    /**
     * Nudge a friend or league mate to check in. Once a day per person.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $sender = $request->user();
        abort_unless($sender->canNudge($user), 403, 'You can nudge friends and GoalLeague mates.');

        $name = str($user->name)->before(' ');

        if ($sender->recentlyNudged($user)) {
            return back()->with('status', "You already nudged {$name} today.");
        }

        DB::table('nudges')->insert(['sender_id' => $sender->id, 'recipient_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $user->notify(new Nudged($sender));
        Usage::track($sender, 'nudge');

        return back()->with('status', "Nudged {$name}. Nice teammate move.");
    }
}
