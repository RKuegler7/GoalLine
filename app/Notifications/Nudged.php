<?php

namespace App\Notifications;

use App\Clock;
use App\Models\User;
use App\Services\DailyStreak;
use App\Services\TodaysGameplan;

/**
 * A friend or league mate nudged you to check in. The message points at what's actually open for you today.
 */
class Nudged extends GoalLineNotification
{
    public function __construct(public User $sender) {}

    public function category(): string
    {
        return 'nudges';
    }

    protected function message(User $notifiable): array
    {
        // Everything about "today" is on the recipient's clock.
        [$left, $atRisk, $days] = Clock::for($notifiable, function () use ($notifiable) {
            $streak = new DailyStreak($notifiable);

            return [
                (new TodaysGameplan($notifiable->goals()->active()->with(['weeks.tasks', 'habits.logs'])->get(), $notifiable))->remaining(),
                $streak->isAtRisk(),
                $streak->current(),
            ];
        });
        $name = str($this->sender->name)->before(' ');

        $text = match (true) {
            $left > 0 => "{$name} nudged you: {$left} ".($left === 1 ? 'thing' : 'things')." left on today's gameplan. You've got this.",
            $atRisk => "{$name} nudged you: one check-in keeps your {$days}-day streak alive.",
            default => "{$name} nudged you to check in. Stay in line!",
        };

        return ['text' => $text, 'url' => route('dashboard').'#gameplan', 'actor_id' => $this->sender->id];
    }
}
