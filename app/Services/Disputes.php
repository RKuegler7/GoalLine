<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Notifications\DisputeResolved;
use Illuminate\Validation\ValidationException;

/**
 * Disputes on check-ins. The matchup opponent or the league owner can dispute any check-in, with an optional reason.
 * The owner of the check-in settles it with proof or an explanation, the disputer can withdraw it, or the league
 * owner rules on it. While a dispute is open after the GoalWeek closes, the check-in's points are held out of the
 * score; once it's settled, every league week it touches re-scores on its own. The AI never decides a dispute.
 */
class Disputes
{
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

        if ($task->disputes()->where('disputer_id', $disputer->id)->where('status', Dispute::OPEN)->exists()) {
            throw ValidationException::withMessages(['reason' => 'You already have an open dispute on this check-in.']);
        }

        $open = Dispute::query()
            ->where('disputer_id', $disputer->id)
            ->where('status', Dispute::OPEN)
            ->whereIn('roadmap_task_id', $week->tasks()->select('id'))
            ->count();

        if ($open >= Dispute::MAX_OPEN_PER_MATCHUP) {
            throw ValidationException::withMessages(['reason' => 'You can have up to '.Dispute::MAX_OPEN_PER_MATCHUP.' open disputes per matchup each GoalWeek. Let one settle first.']);
        }

        $league = $this->fairPlay->leaguesFor($week)->first(fn (League $league) => $league->commissioner_id === $disputer->id)
            ?? $this->fairPlay->leaguesFor($week)->first(fn (League $league) => $league->members->contains('user_id', $disputer->id));

        $dispute = $task->disputes()->create([
            'disputer_id' => $disputer->id,
            'league_id' => $league?->id,
            'reason' => filled($reason) ? trim($reason) : null,
        ]);

        $this->sync($task);
        $week->goal->user->notify(new DisputeOpened($dispute));

        return $dispute;
    }

    /**
     * Settle a dispute. "counts" outcomes (proof, an explanation, a withdrawal, or the league owner's OK) give the
     * points back; the league owner ruling it out removes them for good.
     */
    public function resolve(Dispute $dispute, string $resolution, User $by, ?string $note = null): void
    {
        if (! $dispute->isOpen()) {
            return;
        }

        $dispute->forceFill([
            'status' => match ($resolution) {
                'withdrawn' => Dispute::WITHDRAWN,
                'ruled_out' => Dispute::UPHELD,
                default => Dispute::RESOLVED,
            },
            'resolution' => $resolution,
            'owner_note' => $note !== null ? trim($note) : $dispute->owner_note,
            'resolved_by' => $by->id,
            'resolved_at' => now(),
        ])->save();

        $task = $dispute->task;
        $this->sync($task);
        $this->rescore($task);

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

    /**
     * Re-score the league weeks this check-in counts in, if they were already final.
     */
    private function rescore(RoadmapTask $task): void
    {
        $week = $task->week()->first();

        foreach ($this->fairPlay->leaguesFor($week) as $league) {
            (new LeagueTable($league->fresh()))->rescore($league->weekNumberOn($week->ends_on));
        }
    }
}
