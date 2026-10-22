<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The morning gameplan or the evening nudge, sent on the member's own clock.
 */
class DailyReminder extends GoalLineNotification
{
    /**
     * @param  'morning'|'evening'  $moment
     * @param  list<string>  $items  What's on (or still left on) today's gameplan.
     * @param  list<string>  $notes  Extra lines, like a new GoalWeek starting or check-ins closing tonight.
     */
    public function __construct(
        public string $moment,
        public string $headline,
        public array $items,
        public array $notes = [],
    ) {}

    public function category(): string
    {
        return 'reminders';
    }

    protected function showsInInbox(): bool
    {
        return false;
    }

    protected function message(User $notifiable): array
    {
        return ['text' => $this->headline, 'url' => route('dashboard').'#gameplan'];
    }

    /**
     * The morning push leads with what's next; the evening one with what's left.
     *
     * @return array{title: string, body: string, url: string, tag: string|null}
     */
    public function toWebPush(User $notifiable): array
    {
        $preview = array_slice($this->items, 0, 3);
        $more = count($this->items) - count($preview);

        return [
            'title' => $this->headline,
            'body' => $preview === [] ? implode(' ', $this->notes) : implode(' · ', $preview).($more > 0 ? " · +{$more} more" : ''),
            'url' => route('dashboard').'#gameplan',
            'tag' => 'gameplan-'.$this->moment,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->headline)
            ->greeting(($this->moment === 'morning' ? 'Good morning, ' : 'Evening, ').str($notifiable->name)->before(' ').'.');

        foreach ($this->notes as $note) {
            $mail->line($note);
        }

        if ($this->items !== []) {
            $mail->line($this->moment === 'morning' ? "**Today's Gameplan**" : '**Still on deck today**');

            foreach (array_slice($this->items, 0, 10) as $item) {
                $mail->line('- '.$item);
            }

            if (count($this->items) > 10) {
                $mail->line('- and '.(count($this->items) - 10).' more');
            }
        }

        return $mail
            ->action($this->moment === 'morning' ? "Open today's gameplan" : 'Check in now', route('dashboard').'#gameplan')
            ->salutation(' ')
            ->line('[Change your reminder times]('.route('settings.notifications').')');
    }
}
