<?php

namespace App\Notifications;

use App\Models\GroupTask;
use App\Models\User;

class GroupTaskDecided extends GoalLineNotification
{
    public function __construct(public GroupTask $groupTask) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        $task = $this->groupTask;

        return [
            'text' => $task->status === GroupTask::PASSED
                ? "Group task passed in {$task->league->name}: \u{201C}{$task->title}\u{201D} is on everyone's GoalWeek {$task->week} for {$task->points} points."
                : "Group task did not pass in {$task->league->name}: \u{201C}{$task->title}\u{201D}. {$task->outcome_note}",
            'url' => route('leagues.show', $task->league).'#group-tasks',
        ];
    }
}
