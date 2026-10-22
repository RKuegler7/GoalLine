<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email to the people who run GoalLine: errors, and AI spend nearing its limit.
 */
class AdminAlert extends Notification
{
    use Queueable;

    public function __construct(public string $headline, public string $details = '') {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[GoalLine] '.$this->headline)
            ->greeting('Heads up.')
            ->line($this->headline);

        if ($this->details !== '') {
            $mail->line($this->details);
        }

        return $mail->action('Open the admin page', route('admin.index'))->salutation(' ');
    }
}
