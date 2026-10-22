<?php

namespace App\Notifications;

use App\Models\User;
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
                ? "{$first['week']} of \u{201C}{$first['goal']}\u{201D} is final: {$first['result']}, {$first['points']} ".str('point')->plural($first['points']).'.'
                : 'Last GoalWeek is final: '.$points.' '.str('point')->plural($points).' across '.count($this->results).' goals.',
            'url' => count($this->results) === 1 ? $first['url'] : route('dashboard'),
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        if (count($this->results) > 1) {
            foreach ($this->results as $result) {
                $mail->line("**{$result['goal']}**: {$result['result']}, {$result['points']} ".str('point')->plural($result['points']));
            }
        }

        return $mail;
    }
}
