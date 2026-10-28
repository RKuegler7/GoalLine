<?php

namespace App\Http\Controllers;

use App\Models\GroupTask;
use App\Models\League;
use App\Services\GroupTasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupTaskController extends Controller
{
    /**
     * Propose a group task for a coming GoalWeek. Your yes counts right away; the league has 24 hours to vote.
     */
    public function store(Request $request, League $league, GroupTasks $groupTasks): RedirectResponse
    {
        $validated = $request->validateWithBag('groupTask', [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'points' => ['required', 'integer', 'min:'.GroupTask::MIN_POINTS, 'max:'.GroupTask::MAX_POINTS],
            'week' => ['required', 'integer'],
        ]);

        try {
            $groupTask = $groupTasks->propose($league, $request->user(), [...$validated, 'points' => (int) $validated['points'], 'week' => (int) $validated['week']]);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('groupTask');
        }

        return redirect()->to(route('leagues.show', $league).'#group-tasks')->with('status', $groupTask->fresh()->status === GroupTask::PASSED
            ? "\u{201C}{$groupTask->title}\u{201D} passed and is on everyone's GoalWeek {$groupTask->week}."
            : 'Proposed. The league has 24 hours to vote, and it needs '.GroupTask::requiredYes($league->members()->count()).' yes votes.');
    }

    /**
     * Vote yes or no. You can change your vote until voting closes.
     */
    public function vote(Request $request, GroupTask $groupTask, GroupTasks $groupTasks): RedirectResponse
    {
        abort_unless($groupTask->league->memberFor($request->user()), 403);
        $approve = $request->validate(['approve' => ['required', 'boolean']])['approve'];

        $groupTasks->vote($groupTask, $request->user(), (bool) $approve);

        return redirect()->to(route('leagues.show', $groupTask->league).'#group-tasks')->with('status', match ($groupTask->fresh()->status) {
            GroupTask::PASSED => "\u{201C}{$groupTask->title}\u{201D} passed. It's on everyone's GoalWeek {$groupTask->week}.",
            default => 'Vote saved. You can change it until voting closes.',
        });
    }
}
