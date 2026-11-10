<?php

namespace App\Notifications;

use App\Models\LeagueInvite;
use App\Models\User;
use App\Outbound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A league invite emailed to an address, whether or not it belongs to a GoalLine account yet.
 */
class LeagueInviteEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LeagueInvite $invite, public User $inviter) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Transactional (the member chose to send it), so no switch applies; a demo account never sends one.
        return ! $this->inviter->isDemo() && Outbound::mayEmail($notifiable) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $league = $this->invite->league;

        return (new MailMessage)
            ->subject("{$this->inviter->name} invited you to {$league->name} on GoalLine")
            ->greeting('Hi there,')
            ->line("{$this->inviter->name} invited you to play {$league->name}, a GoalLeague on GoalLine. Each week your goal's progress goes head-to-head with a friend's. Get in line with your goals. We get more done together.")
            ->action('See the invite', $this->invite->url())
            ->line('This invite works once and expires in '.LeagueInvite::DEFAULT_EXPIRY_DAYS.' days.')
            ->salutation(' ');
    }
}
