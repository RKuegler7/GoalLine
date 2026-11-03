<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\RoadmapTask;
use App\Services\Disputes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    /**
     * Dispute a check-in, as its matchup opponent or a league owner, with an optional reason.
     */
    public function store(Request $request, RoadmapTask $task, Disputes $disputes): RedirectResponse
    {
        abort_unless($disputes->canDispute($request->user(), $task), 403);
        $reason = $request->validateWithBag('dispute', ['reason' => ['nullable', 'string', 'max:'.Dispute::MAX_REASON]])['reason'] ?? null;

        try {
            $disputes->open($task, $request->user(), $reason);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('dispute');
        }

        return back()->with('status', 'Disputed. They\'ll get a friendly note to add proof or explain. Its points wait while it\'s open after the GoalWeek closes.');
    }

    /**
     * The owner of the check-in explains it; the explanation settles the dispute.
     */
    public function explain(Request $request, Dispute $dispute, Disputes $disputes): RedirectResponse
    {
        abort_unless($dispute->isOpen() && $dispute->task->week->goal->user_id === $request->user()->id, 403);
        $note = $request->validateWithBag('dispute', ['note' => ['required', 'string', 'max:'.Dispute::MAX_NOTE]], [
            'note.required' => 'Add a short explanation, like where and when you did it.',
        ])['note'];

        $disputes->resolve($dispute, 'note', $request->user(), $note);

        return back()->with('status', 'Thanks. Your explanation settled the dispute.');
    }

    /**
     * The disputer takes it back.
     */
    public function withdraw(Request $request, Dispute $dispute, Disputes $disputes): RedirectResponse
    {
        abort_unless($dispute->isOpen() && $dispute->disputer_id === $request->user()->id, 403);

        $disputes->resolve($dispute, 'withdrawn', $request->user());

        return back()->with('status', 'Dispute withdrawn. The check-in counts.');
    }

    /**
     * The league owner rules: the check-in counts, or it doesn't. A league owner can't rule on their own check-in.
     */
    public function rule(Request $request, Dispute $dispute, Disputes $disputes): RedirectResponse
    {
        abort_unless($dispute->isOpen() && $dispute->league?->commissioner_id === $request->user()->id && $dispute->task->week->goal->user_id !== $request->user()->id, 403);
        $outcome = $request->validate(['outcome' => ['required', Rule::in(['counts', 'out'])]])['outcome'];

        $disputes->resolve($dispute, $outcome === 'counts' ? 'ruled_counts' : 'ruled_out', $request->user());

        return back()->with('status', $outcome === 'counts' ? 'Ruled: it counts.' : 'Ruled: it doesn\'t count. Its points are out of that GoalWeek.');
    }
}
