<?php

namespace App\Services;

use App\Atomic;
use App\Jobs\RescoreCheckIn;
use App\Models\Dispute;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Notifications\DisputeResolved;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Disputes on check-ins. The matchup opponent or the league owner can dispute any check-in, with an optional reason.
 * The owner of the check-in settles it with proof or an explanation, the disputer can withdraw it, or the league
 * owner rules on it. While a dispute is open after the GoalWeek closes, the check-in's points are held out of the
 * score; once it's settled, every league week it touches re-scores on its own. The AI never decides a dispute.
 */
class Disputes
{
    public const ALREADY_OPEN = 'You already have an open dispute on this check-in.';

    public function __construct(private FairPlay $fairPlay) {}

    /**
     * Whether someone can dispute a check-in: its matchup opponent or a league owner, not the person who did it.
     */
    public function canDispute(User $viewer, RoadmapTask $task): bool
    {
        $week = $task->week;

        return $task->completed_at !== null
            && $week->goal->user_id !== $viewer->id
            && ($this->fairPlay->opponentsOf($week)->contains(fn (User $opponent) => $opponent->is($viewer))
                || $this->fairPlay->leaguesFor($week)->contains(fn (League $league) => $league->commissioner_id === $viewer->id));
    }

    /**
     * Open a dispute. At most three open per matchup per GoalWeek, and one per check-in per person.
     *
     * @throws ValidationException When the cap is reached or this person already disputed the check-in.
     */
    public function open(RoadmapTask $task, User $disputer, ?string $reason): Dispute
    {
        $week = $task->week;

        // Read-only lookups happen before the transaction, so it stays short.
        $league = $this->fairPlay->leaguesFor($week)->first(fn (League $league) => $league->commissioner_id === $disputer->id)
            ?? $this->fairPlay->leaguesFor($week)->first(fn (League $league) => $league->members->contains('user_id', $disputer->id));

        try {
            $dispute = Atomic::run(function () use ($task, $week, $disputer, $reason, $league) {
                // Lock order everywhere: the check-in's task row first, then its disputes.
                RoadmapTask::whereKey($task->id)->lockForUpdate()->first();

                if ($task->disputes()->where('disputer_id', $disputer->id)->where('status', Dispute::OPEN)->exists()) {
                    throw ValidationException::withMessages(['reason' => self::ALREADY_OPEN]);
                }

                $open = Dispute::query()
                    ->where('disputer_id', $disputer->id)
                    ->where('status', Dispute::OPEN)
                    ->whereIn('roadmap_task_id', $week->tasks()->select('id'))
                    ->count();

                if ($open >= Dispute::MAX_OPEN_PER_MATCHUP) {
                    throw ValidationException::withMessages(['reason' => 'You can have up to '.Dispute::MAX_OPEN_PER_MATCHUP.' open disputes per matchup each GoalWeek. Let one settle first.']);
                }

                $dispute = $task->disputes()->make([
                    'disputer_id' => $disputer->id,
                    'league_id' => $league?->id,
                    'reason' => filled($reason) ? trim($reason) : null,
                ]);
                $dispute->forceFill(['open_key' => Dispute::openKey($disputer->id, $task->id)])->save();

                $this->sync($task);

                return $dispute;
            });
        } catch (UniqueConstraintViolationException) {
            // A double tap or a second tab got there first: the database keeps just one open dispute.
            throw ValidationException::withMessages(['reason' => self::ALREADY_OPEN]);
        }

        // After the commit: the email and inbox note (queued, never inside the transaction).
        $week->goal->user->notify(new DisputeOpened($dispute));

        return $dispute;
    }

    /**
     * Settle a dispute. "counts" outcomes (proof, an explanation, a withdrawal, or the league owner's OK) give the
     * points back; the league owner ruling it out removes them for good.
     */
    public function resolve(Dispute $dispute, string $resolution, User $by, ?string $note = null): void
    {
        $settled = Atomic::run(function () use ($dispute, $resolution, $by, $note) {
            // Same lock order as opening: the task row, then the dispute.
            RoadmapTask::whereKey($dispute->roadmap_task_id)->lockForUpdate()->first();
            $current = Dispute::whereKey($dispute->id)->lockForUpdate()->first();

            // Already settled (by a second tab, or proof a moment earlier): nothing to do.
            if (! $current?->isOpen()) {
                return false;
            }

            $current->forceFill([
                'status' => match ($resolution) {
                    'withdrawn' => Dispute::WITHDRAWN,
                    'ruled_out' => Dispute::UPHELD,
                    default => Dispute::RESOLVED,
                },
                'open_key' => null,
                'resolution' => $resolution,
                'owner_note' => $note !== null ? trim($note) : $current->owner_note,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
            ])->save();

            $this->sync($current->task);

            return true;
        });

        if (! $settled) {
            return;
        }

        $dispute->refresh();
        $task = $dispute->task;

        // Re-scoring runs after the commit, as its own idempotent job.
        RescoreCheckIn::dispatch($task->id);

        if (! $dispute->disputer->is($by)) {
            $dispute->disputer->notify(new DisputeResolved($dispute));
        }

        if (in_array($resolution, ['ruled_counts', 'ruled_out'], true)) {
            $task->week->goal->user->notify(new DisputeResolved($dispute));
        }
    }

    /**
     * Proof on a check-in settles its open disputes.
     */
    public function settleWithProof(RoadmapTask $task): void
    {
        $task->disputes()->where('status', Dispute::OPEN)->get()
            ->each(fn (Dispute $dispute) => $this->resolve($dispute, 'proof', $task->week->goal->user));
    }

    /**
     * Keep the check-in's dispute status in step: ruled out, open, or clear.
     */
    private function sync(RoadmapTask $task): void
    {
        $statuses = $task->disputes()->pluck('status');

        $task->forceFill(['dispute_status' => match (true) {
            $statuses->contains(Dispute::UPHELD) => RoadmapTask::DISPUTE_UPHELD,
            $statuses->contains(Dispute::OPEN) => RoadmapTask::DISPUTE_OPEN,
            default => null,
        }])->save();
    }
}
