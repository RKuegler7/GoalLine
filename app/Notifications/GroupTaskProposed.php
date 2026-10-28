<?php

namespace App\Notifications;

use App\Models\GroupTask;
use App\Models\User;

class GroupTaskProposed extends GoalLineNotification
{
    public function __construct(public GroupTask $groupTask) {}

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return [
            'text' => "{$this->groupTask->proposer?->name} proposed a group task in {$this->groupTask->league->name}: \u{201C}{$this->groupTask->title}\u{201D}. Vote within 24 hours.",
            'url' => route('leagues.show', $this->groupTask->league).'#group-tasks',
            'actor_id' => $this->groupTask->proposer_id,
        ];
    }
}
