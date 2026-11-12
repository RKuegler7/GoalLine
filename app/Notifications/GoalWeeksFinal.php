<?php

namespace App\Notifications;

use App\Models\User;
use App\Outbound;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Last GoalWeek's check-ins have closed: here's how each goal finished.
 */
class GoalWeeksFinal extends GoalLineNotification
{
    /**
     * @param  list<array{goal: string, week: string, result: string, points: int, url: string}>  $results
     */
    public function __construct(public array $results) {}

    /**
     * Paused with the GOALLINE_EMAIL_RECAPS switch.
     */
    public function emailStream(): string
    {
        return Outbound::RECAPS;
    }

    public function category(): string
    {
        return 'results';
    }

    protected function message(User $notifiable): array
    {
        $points = array_sum(array_column($this->results, 'points'));
        $first = $this->results[0];

        return [
            'text' => count($this->results) === 1
                ? "{$first['week']} of \u{201C}{$first['goal']}\u{201D} is final: {$first['result']}, {$first['points']} line ".str('star')->plural($first['points']).'.'
                : 'Last GoalWeek is final: '.$points.' line '.str('star')->plural($points).' across '.count($this->results).' goals.',
            'url' => count($this->results) === 1 ? $first['url'] : route('dashboard'),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        if (count($this->results) > 1) {
            foreach ($this->results as $result) {
                $mail->line("**{$result['goal']}**: {$result['result']}, {$result['points']} line ".str('star')->plural($result['points']));
            }
        }

        return $mail;
    }
}
