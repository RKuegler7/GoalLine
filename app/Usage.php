<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * First-party usage tracking: which features members use, and when. Event names only, never content,
 * so the admin page can show what's working during the beta.
 */
final class Usage
{
    /**
     * The events GoalLine records, with how the admin page describes them.
     *
     * @var array<string, string>
     */
    public const EVENTS = [
        'active' => 'Opened the app',
        'goal-created' => 'Set a goal',
        'check-in' => 'Checked off a task',
        'habit-log' => 'Logged a habit',
        'week-planned' => 'Planned their week',
        'week-reviewed' => 'Reviewed a GoalWeek',
        'highlight-shared' => 'Shared a highlight',
        'cheer' => 'Cheered',
        'comment' => 'Commented',
        'chat-message' => 'Posted in league chat',
        'nudge' => 'Nudged someone',
        'league-joined' => 'Joined a GoalLeague',
        'challenge-joined' => 'Joined a challenge',
        'challenge-check-in' => 'Checked in on a challenge',
        'push-enabled' => 'Turned on push',
        'app-installed' => 'Opened from the home screen',
        'feedback' => 'Sent beta feedback',
        'demo-started' => 'Started the demo',
    ];

    public static function track(?User $user, string $name): void
    {
        // Demo worlds stay out of the numbers; only the visit itself is counted, as demo-started.
        if ($user?->isDemo() && $name !== 'demo-started') {
            return;
        }

        DB::table('usage_events')->insert(['user_id' => $user?->id, 'name' => $name, 'created_at' => now()]);
    }

    /**
     * Record that a member opened the app, at most once a day.
     */
    public static function trackActive(User $user): void
    {
        if (Cache::add("usage-active:{$user->id}:".today()->toDateString(), true, now()->endOfDay())) {
            self::track($user, 'active');
        }
    }
}
