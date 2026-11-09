<?php

namespace App\Http\Controllers;

use App\Atomic;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CheckInController extends Controller
{
    /**
     * What a check-in says, as a toast or a banner.
     */
    public const CHECKED_IN = 'Checked in. One more day on the line.';

    /**
     * Check a task off, or un-check it. A task that tracks a number asks for the result: out-of-range numbers are
     * rejected, and an unusually high one (over 1.5 times the plan, or a new personal best) gets one "Are you sure?".
     * Un-checking clears the result and any proof photo.
     */
    public function toggleTask(Request $request, RoadmapTask $task): RedirectResponse
    {
        $week = $task->week;
        $this->authorizeCheckIn($week);

        // A tap says which state it wants. If the task is already there (a double tap, or a second tab), nothing changes.
        if ($this->alreadyThere($request, (bool) $task->completed_at)) {
            return $this->backTo($week);
        }

        if ($task->completed_at) {
            // Locked and re-checked inside a short transaction; the photo is removed only after it commits.
            $removedProof = Atomic::run(function () use ($task) {
                $current = RoadmapTask::whereKey($task->id)->lockForUpdate()->first();

                if (! $current->completed_at) {
                    return false;
                }

                $proof = $current->proof_path;
                $current->forceFill(['completed_at' => null, 'result_value' => null, 'proof_path' => null, 'proof_mime_type' => null, 'proof_uploaded_at' => null])->save();

                return $proof;
            });

            if ($removedProof) {
                Storage::disk(RoadmapTask::proofDisk())->delete($removedProof);
            }

            return $this->backTo($week)->with('status', 'Check-in undone.');
        }

        if ($task->result_required) {
            $value = $this->validatedResult($request, $task);

            if (! $request->boolean('confirmed') && ($reason = $this->unusualResult($task, $value))) {
                return $this->backTo($week)
                    ->withInput()
                    ->with('confirm_result', ['task' => $task->id, 'value' => $value, 'reason' => $reason]);
            }

            $task->forceFill(['result_value' => $value]);
        }

        // A second tab may have checked it off a moment ago: lock, re-check, and write only if it's still open.
        $resultValue = $task->result_value;
        Atomic::run(function () use ($task, $resultValue) {
            $current = RoadmapTask::whereKey($task->id)->lockForUpdate()->first();

            if (! $current->completed_at) {
                $current->forceFill(['completed_at' => now(), 'result_value' => $task->result_required ? $resultValue : $current->result_value])->save();
            }
        });
        $task->refresh();

        // Offer "Show your work" right where they checked it off.
        return $this->backTo($week)->with('proof_prompt', $task->id)->with('status', self::CHECKED_IN);
    }

    /**
     * The result entered for a numeric task, within its allowed range.
     */
    private function validatedResult(Request $request, RoadmapTask $task): float
    {
        $range = $task->formatNumber($task->result_min).' to '.$task->formatNumber($task->result_max);

        $validated = $request->validate([
            'result' => ['required', 'numeric', 'min:'.$task->result_min, 'max:'.$task->result_max],
        ], [
            'result.required' => 'Enter how much you did, like '.$task->formatNumber($task->result_target).'.',
            'result.numeric' => 'Enter a number, like '.$task->formatNumber($task->result_target).'.',
            'result.min' => "That's outside this task's range ({$range}). Check the number and try again.",
            'result.max' => "That's outside this task's range ({$range}). Check the number and try again.",
        ]);

        return round((float) $validated['result'], 2);
    }

    /**
     * Why a result deserves a second look, or null for a normal entry.
     */
    private function unusualResult(RoadmapTask $task, float $value): ?string
    {
        if ($value > $task->result_target * RoadmapTask::UNUSUALLY_HIGH_MULTIPLE) {
            return $task->formatNumber($value).' is more than 1.5 times your plan of '.$task->formatNumber($task->result_target).'.';
        }

        $best = RoadmapTask::query()
            ->whereKeyNot($task->id)
            ->where('result_required', true)
            ->whereNotNull('completed_at')
            ->where('result_unit', $task->result_unit)
            ->whereHas('week', fn ($weeks) => $weeks->where('goal_id', $task->week->goal_id))
            ->max('result_value');

        if ($best !== null && $value > (float) $best) {
            return $task->formatNumber($value).' would be a new personal best (your best so far is '.$task->formatNumber((float) $best).').';
        }

        return null;
    }

    /**
     * Mark the GoalWeek's milestone as hit, or not.
     */
    public function toggleMilestone(RoadmapWeek $week): RedirectResponse
    {
        $this->authorizeCheckIn($week);

        if ($this->alreadyThere(request(), $week->milestone_hit_at !== null)) {
            return $this->backTo($week);
        }

        $week->forceFill(['milestone_hit_at' => $week->milestone_hit_at ? null : now()])->save();

        return $this->backTo($week)->with('status', $week->milestone_hit_at ? 'Milestone hit. That\'s the process paying off.' : 'Milestone unmarked.');
    }

    /**
     * Whether the tap asked for the state the item is already in ("done" when done, "open" when open).
     */
    private function alreadyThere(Request $request, bool $done): bool
    {
        return match ($request->input('intent')) {
            'done' => $done,
            'open' => ! $done,
            default => false,
        };
    }

    /**
     * Only the owner can check in, from the day a GoalWeek starts until its result locks.
     */
    private function authorizeCheckIn(RoadmapWeek $week): void
    {
        Gate::authorize('update', $week->goal);
        abort_unless($week->isCheckable(), 403, 'This GoalWeek is not open for check-ins.');
    }

    /**
     * Return to the GoalWeek on the goal page.
     */
    private function backTo(RoadmapWeek $week): RedirectResponse
    {
        // Check-ins can happen from the Clubhouse too; send people back where they clicked.
        return match (request()->input('return')) {
            'clubhouse' => redirect()->to(route('dashboard').'#goal-'.$week->goal_id),
            'gameplan' => redirect()->to(route('dashboard').'#gameplan'),
            'today' => redirect()->route('today'),
            'planner' => redirect()->route('planner.show'),
            default => redirect()->to(route('goals.show', $week->goal).'#goalweek-'.$week->position),
        };
    }
}
