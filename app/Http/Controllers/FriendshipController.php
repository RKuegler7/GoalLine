<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendAccepted;
use App\Notifications\FriendRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FriendshipController extends Controller
{
    /**
     * Your friends, requests waiting on you, requests you've sent, and a way to add someone by their exact
     * username or email. There's no directory to browse.
     */
    public function index(Request $request): View
    {
        $me = $request->user();
        $pending = $me->friendships()->where('status', Friendship::PENDING)->with('requester', 'addressee')->latest()->get();

        return view('friends.index', [
            'friends' => $me->friends(),
            'incoming' => $pending->where('addressee_id', $me->id)->values(),
            'outgoing' => $pending->where('requester_id', $me->id)->values(),
            'friendships' => $me->friendships()->where('status', Friendship::ACCEPTED)->get()
                ->keyBy(fn (Friendship $friendship) => $friendship->requester_id === $me->id ? $friendship->addressee_id : $friendship->requester_id),
        ]);
    }

    /**
     * Send a request to whoever has exactly this username or email.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['who' => ['required', 'string', 'max:255']], [
            'who.required' => 'Enter their exact username or email.',
        ]);

        $who = strtolower(ltrim(trim($validated['who']), '@'));
        $person = User::whereRaw('lower(username) = ?', [$who])->orWhereRaw('lower(email) = ?', [$who])->first();

        if (! $person || $person->is($request->user())) {
            return back()->withErrors(['who' => "No one with that exact username or email. Check the spelling, or ask them for their username (it's on their locker)."])->withInput();
        }

        return $this->request($request, $person);
    }

    /**
     * Send a request to someone (from their locker, or the lookup above).
     */
    public function request(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        abort_if($me->is($user), 422, "You can't add yourself.");
        $name = str($user->name)->before(' ');
        $existing = $me->friendshipWith($user);

        if ($existing?->isAccepted()) {
            return back()->with('status', "You and {$name} are already friends.");
        }

        if ($existing && $existing->requester_id === $me->id) {
            return back()->with('status', "Your request to {$name} is waiting on them.");
        }

        // They already asked you: saying yes back is accepting.
        if ($existing) {
            return $this->accept($request, $existing);
        }

        $friendship = Friendship::create(['requester_id' => $me->id, 'addressee_id' => $user->id]);
        $user->notify(new FriendRequested($me));

        return back()->with('status', "Friend request sent to {$name}.");
    }

    /**
     * Accept a request someone sent you.
     */
    public function accept(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->addressee_id === $request->user()->id && ! $friendship->isAccepted(), 403);

        $friendship->forceFill(['status' => Friendship::ACCEPTED, 'accepted_at' => now()])->save();
        $friendship->requester->notify(new FriendAccepted($request->user()));

        return back()->with('status', "You and {$friendship->requester->name} are friends now.");
    }

    /**
     * Decline a request, cancel one you sent, or remove a friend.
     */
    public function destroy(Request $request, Friendship $friendship): RedirectResponse
    {
        $me = $request->user();
        abort_unless(in_array($me->id, [$friendship->requester_id, $friendship->addressee_id], true), 403);

        $other = $friendship->otherThan($me);
        $message = match (true) {
            $friendship->isAccepted() => "Removed {$other->name} from your friends.",
            $friendship->requester_id === $me->id => 'Request cancelled.',
            default => 'Request declined.',
        };

        $friendship->delete();

        return back()->with('status', $message);
    }
}
