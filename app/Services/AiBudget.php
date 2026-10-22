<?php

namespace App\Services;

use App\Exceptions\AiLimitReached;
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Spend limits for Claude API calls: a daily cap per member, plus daily and monthly dollar budgets for
 * the whole app. Every call is logged with its token counts and estimated cost.
 */
class AiBudget
{
    /**
     * Stop here if a limit has been reached.
     *
     * @throws AiLimitReached
     */
    public function ensureAvailable(?User $user): void
    {
        if ($reason = $this->blockedReason($user)) {
            throw new AiLimitReached($reason);
        }
    }

    /**
     * Why AI is paused for this member right now, or null if it isn't.
     */
    public function blockedReason(?User $user): ?string
    {
        $config = config('goalline.ai');

        return match (true) {
            $this->spentThisMonth() >= $config['monthly_budget_usd'] => 'GoalLine has reached its AI budget for this month.',
            $this->spentToday() >= $config['daily_budget_usd'] => 'GoalLine has reached its AI budget for today.',
            $user !== null && $this->requestsToday($user) >= $config['daily_requests_per_member'] => "You've used today's AI coaching. It resets tomorrow.",
            default => null,
        };
    }

    /**
     * Log a call and its estimated cost, and warn the admins once a day when spend nears the daily budget.
     */
    public function record(?User $user, string $kind, int $inputTokens, int $outputTokens): void
    {
        $config = config('goalline.ai');
        $cost = ($inputTokens * $config['input_usd_per_million_tokens'] + $outputTokens * $config['output_usd_per_million_tokens']) / 1_000_000;

        DB::table('ai_requests')->insert([
            'user_id' => $user?->id,
            'kind' => $kind,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => round($cost, 6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $threshold = $config['daily_budget_usd'] * $config['alert_at_share'];

        if ($this->spentToday() >= $threshold && Cache::add('ai-budget-alert:'.today()->toDateString(), true, now()->endOfDay())) {
            Notification::route('mail', config('goalline.admin_emails'))->notify(new AdminAlert(
                'AI spend is at $'.number_format($this->spentToday(), 2).' of the $'.number_format($config['daily_budget_usd'], 2).' daily budget.',
                'AI features pause for everyone when the budget is reached and resume tomorrow. Change the limits with AI_DAILY_BUDGET_USD and AI_MONTHLY_BUDGET_USD.',
            ));
        }
    }

    public function spentToday(): float
    {
        return (float) DB::table('ai_requests')->where('created_at', '>=', today())->sum('cost_usd');
    }

    public function spentThisMonth(): float
    {
        return (float) DB::table('ai_requests')->where('created_at', '>=', today()->startOfMonth())->sum('cost_usd');
    }

    public function requestsToday(User $user): int
    {
        return DB::table('ai_requests')->where('user_id', $user->id)->where('created_at', '>=', today())->count();
    }
}
