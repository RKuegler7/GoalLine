<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class InboxController extends Controller
{
    /**
     * Everything that's happened around you: cheers, follows, friends' highlights, GoalLeague news, and results.
     * Opening the Inbox marks it all read; what was new stays highlighted for this visit.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(30);
        $actors = User::whereIn('id', $notifications->pluck('data.actor_id')->filter()->unique())->get()->keyBy('id');

        $view = view('inbox.index', [
            'notifications' => $notifications,
            'actors' => $actors,
            'unreadIds' => $notifications->filter(fn (DatabaseNotification $notification) => $notification->unread())->pluck('id'),
        ]);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return $view;
    }
}
