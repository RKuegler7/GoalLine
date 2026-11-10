<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\User;
use App\Outbound;

/**
 * A challenge ended: where you placed and whether you hit the target.
 */
class ChallengeFinished extends GoalLineNotification
{
    public function __construct(public Challenge $challenge, public string $text) {}

    /**
     * Paused with the GOALLINE_EMAIL_RECAPS switch.
     */
    public function emailStream(): string
    {
        return Outbound::RECAPS;
    }

    public function category(): string
    {
        return 'leagues';
    }

    protected function message(User $notifiable): array
    {
        return ['text' => $this->text, 'url' => route('challenges.show', $this->challenge)];
    }
}
