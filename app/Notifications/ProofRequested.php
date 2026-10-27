<?php

namespace App\Notifications;

use App\Models\RoadmapTask;
use App\Models\User;

/**
 * A league mate asked to see the work behind a check-in. It never changes the score; it's a friendly prompt.
 */
class ProofRequested extends GoalLineNotification
{
    public function __construct(public RoadmapTask $task, public User $requester) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        $week = $this->task->week;

        return [
            'text' => str($this->requester->name)->before(' ')." asked you to show your work on \u{201C}{$this->task->description}\u{201D}. Add a photo when you get a chance: it shows the process and keeps scoring fair.",
            'url' => route('goals.show', $week->goal).'#goalweek-'.$week->position,
            'actor_id' => $this->requester->id,
        ];
    }
}
