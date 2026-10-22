<?php

namespace App\Http\Controllers;

use App\Mentions;
use App\Models\League;
use App\Models\LeagueMessage;
use App\Notifications\Mentioned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

class LeagueMessageController extends Controller
{
    /**
     * Messages posted after the one the chat last showed, as ready-to-insert HTML.
     */
    public function index(Request $request, League $league): Response
    {
        $messages = $league->messages()
            ->where('id', '>', (int) $request->query('after', 0))
            ->with('user')
            ->limit(100)
            ->get();

        $this->markRead($request, $league);

        return response()
            ->view('leagues.partials.messages', ['league' => $league, 'messages' => $messages])
            ->header('X-Last-Message-Id', (string) ($messages->last()?->id ?? $request->query('after', 0)));
    }

    /**
     * Post a message to the league chat.
     */
    public function store(Request $request, League $league): Response|RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.LeagueMessage::MAX_LENGTH],
        ], [
            'body.required' => 'Write a message first.',
        ]);

        $message = $league->messages()->create(['user_id' => $request->user()->id, 'body' => trim($validated['body'])]);

        // Let anyone @mentioned in the league know.
        Notification::send(
            Mentions::among($message->body, $league->members()->with('user')->get()->pluck('user')->filter())->reject(fn ($user) => $user->is($request->user())),
            new Mentioned($request->user(), 'in '.$league->name, $message->body, route('leagues.show', $league).'#chat'),
        );

        if ($request->ajax()) {
            return $this->index($request, $league);
        }

        return redirect()->to(route('leagues.show', $league).'#chat');
    }

    /**
     * Remove a message: your own, or any message if you run the league.
     */
    public function destroy(Request $request, LeagueMessage $message): Response|RedirectResponse
    {
        $league = $message->league;
        abort_unless($message->user_id === $request->user()->id || $league->commissioner_id === $request->user()->id, 403);

        $message->delete();

        return $request->ajax() ? response()->noContent() : redirect()->to(route('leagues.show', $league).'#chat');
    }

    /**
     * Note that the member has seen everything up to the latest message.
     */
    private function markRead(Request $request, League $league): void
    {
        $league->memberFor($request->user())?->update(['last_read_message_id' => $league->messages()->max('id')]);
    }
}
