<?php

namespace App\Http\Controllers;

use App\Mentions;
use App\Models\Highlight;
use App\Models\HighlightComment;
use App\Models\User;
use App\Notifications\HighlightCommented;
use App\Notifications\Mentioned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class HighlightCommentController extends Controller
{
    /**
     * Comment on a highlight you can see. The author hears about it, and so does anyone @mentioned who can see it too.
     */
    public function store(Request $request, Highlight $highlight): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.HighlightComment::MAX_LENGTH],
        ], [
            'body.required' => 'Write a comment first.',
        ]);

        $author = $request->user();
        $comment = $highlight->comments()->create(['user_id' => $author->id, 'body' => trim($validated['body'])]);

        if (! $author->is($highlight->user)) {
            $highlight->user->notify(new HighlightCommented($comment));
        }

        $mentioned = Mentions::among($comment->body, User::whereIn('username', Mentions::usernames($comment->body))->get())
            ->reject(fn (User $user) => $user->is($author) || $user->is($highlight->user))
            ->filter(fn (User $user) => Gate::forUser($user)->allows('view', $highlight));

        Notification::send($mentioned, new Mentioned($author, 'in a comment', $comment->body, route('highlights.index').'#highlight-'.$highlight->id));

        return $this->backTo($highlight);
    }

    /**
     * Remove a comment: your own, or any comment on your highlight.
     */
    public function destroy(Request $request, HighlightComment $comment): RedirectResponse
    {
        $highlight = $comment->highlight;
        abort_unless($comment->user_id === $request->user()->id || $highlight->user_id === $request->user()->id, 403);

        $comment->delete();

        return $this->backTo($highlight);
    }

    private function backTo(Highlight $highlight): RedirectResponse
    {
        return redirect()->to(url()->previous(route('highlights.index')).'#highlight-'.$highlight->id);
    }
}
