<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\User;

/**
 * Someone disputed a check-in. Friendly, never accusatory: proof or a short explanation settles it.
 */
class DisputeOpened extends GoalLineNotification
{
    public function __construct(public Dispute $dispute) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        $task = $this->dispute->task;
        $who = str($this->dispute->disputer->name)->before(' ');
        $why = $this->dispute->reason ? " They said: \u{201C}{$this->dispute->reason}\u{201D}." : '';

        return [
            'text' => "{$who} asked about your check-in \u{201C}{$task->description}\u{201D}.{$why} Add a photo or a quick explanation to settle it. Until it's settled, its points wait once the GoalWeek closes.",
            'url' => $this->dispute->league ? route('leagues.matchups', $this->dispute->league) : route('goals.show', $task->week->goal),
            'actor_id' => $this->dispute->disputer_id,
        ];
    }
}
