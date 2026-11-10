<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Outbound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Something worth telling a member about. It lands in their Inbox, and on their phone (push) and in
 * their email too when they've asked for that kind of notification there.
 */
abstract class GoalLineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * A failed send (mail server down, push service slow) is retried a few times, then given up; the Inbox copy is
     * already saved separately.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * Which setting controls this notification: one of NotificationSettings::CATEGORIES.
     */
    abstract public function category(): string;

    /**
     * The one-line message, where it links to, and who it's from.
     *
     * @return array{text: string, url: string, actor_id?: int|null}
     */
    abstract protected function message(User $notifiable): array;

    /**
     * Whether it belongs in the Inbox. Reminders go to email only; the Clubhouse already shows today's work.
     */
    protected function showsInInbox(): bool
    {
        return true;
    }

    /**
     * Which outbound email switch covers it (App\Outbound). Most notifications are "notifications".
     */
    public function emailStream(): string
    {
        return Outbound::NOTIFICATIONS;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $channels = $this->showsInInbox() ? ['database'] : [];

        // Demo accounts have made-up addresses and no devices: inbox only.
        if ($notifiable->isDemo()) {
            return $channels;
        }

        $settings = $notifiable->notificationSettings();

        if (Outbound::pushEnabled() && $settings->wantsPush($this->category()) && WebPushChannel::isConfigured() && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($notifiable->hasVerifiedEmail() && $settings->wantsEmail($this->category())) {
            if (Outbound::mayEmail($notifiable, $this->emailStream())) {
                $channels[] = 'mail';
            } elseif (! in_array('database', $channels, true)) {
                // Email for this kind is paused: the message lands in the Inbox instead, so nothing is lost.
                $channels[] = 'database';
            }
        }

        return $channels;
    }

    /**
     * What's stored for the Inbox.
     *
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return ['category' => $this->category(), 'actor_id' => null, ...$this->message($notifiable)];
    }

    /**
     * The phone notification: a title, one line, and the screen it opens.
     *
     * @return array{title: string, body: string, url: string, tag: string|null}
     */
    public function toWebPush(User $notifiable): array
    {
        $message = $this->message($notifiable);

        return ['title' => 'GoalLine', 'body' => $message['text'], 'url' => $message['url'], 'tag' => null];
    }

    /**
     * A short email: the message and a button back into the app.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $message = $this->message($notifiable);

        return (new MailMessage)
            ->subject($message['text'])
            ->greeting('Hi '.str($notifiable->name)->before(' ').',')
            ->line($message['text'])
            ->action('Open GoalLine', $message['url'])
            ->salutation(' ')
            ->line('[Change what GoalLine emails you]('.route('settings.notifications').')');
    }
}
