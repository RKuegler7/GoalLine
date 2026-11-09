<?php

namespace App\Jobs;

use App\Models\RoadmapTask;
use App\Services\FairPlay;
use App\Services\LeagueTable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-score the league weeks a check-in counts in, after a dispute on it is settled (or proof settles it). It runs only
 * after the dispute's transaction commits, never inside it, and it's idempotent: it recomputes the week from the
 * check-ins as they are now and writes the same rows, so running it twice changes nothing. Duplicates waiting in the
 * queue for the same check-in collapse into one.
 */
class RescoreCheckIn implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** A deadlock or lock timeout gets more tries before giving up. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [2, 10];

    public function __construct(public int $taskId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }

    public function handle(FairPlay $fairPlay): void
    {
        $task = RoadmapTask::with('week.goal')->find($this->taskId);

        if (! $task) {
            return;
        }

        foreach ($fairPlay->leaguesFor($task->week) as $league) {
            (new LeagueTable($league->fresh()))->rescore($league->weekNumberOn($task->week->ends_on));
        }
    }
}
