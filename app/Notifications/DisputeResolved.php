<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\User;

/**
 * A dispute was settled: with proof, an explanation, a withdrawal, or the league owner's ruling.
 */
class DisputeResolved extends GoalLineNotification
{
    public function __construct(public Dispute $dispute) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        $task = $this->dispute->task;
        $points = $this->dispute->status === Dispute::UPHELD ? 'Its points are out.' : 'Its points count.';

        return [
            'text' => "The dispute on \u{201C}{$task->description}\u{201D} is settled: {$this->dispute->outcomeLabel()}. {$points}",
            'url' => $this->dispute->league ? route('leagues.matchups', $this->dispute->league) : route('goals.show', $task->week->goal),
            'actor_id' => $this->dispute->resolved_by,
        ];
    }
}
