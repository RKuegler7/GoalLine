<?php

namespace App\Providers;

use Anthropic\Client;
use App\GoalStatus;
use App\Jobs\AwardBadges;
use App\Models\ChallengeCheckIn;
use App\Models\ChallengeMember;
use App\Models\Cheer;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\Highlight;
use App\Models\HighlightComment;
use App\Models\LeagueMember;
use App\Models\LeagueMessage;
use App\Models\PushSubscription;
use App\Models\RoadmapTask;
use App\Models\User;
use App\Models\WeekReview;
use App\Usage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: config('services.anthropic.key'),
        ));

        $this->app->bind(WebPush::class, fn () => new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        $this->trackUsage();

        // Check for new badges whenever someone does something that could earn one.
        RoadmapTask::updated(function (RoadmapTask $task) {
            if ($task->wasChanged('completed_at') && $task->completed_at) {
                AwardBadges::dispatch($task->week->goal->user);
            }
        });
        HabitLog::created(fn (HabitLog $log) => AwardBadges::dispatch($log->habit->goal->user));
        ChallengeCheckIn::created(fn (ChallengeCheckIn $checkIn) => AwardBadges::dispatch($checkIn->user));
        Cheer::created(fn (Cheer $cheer) => AwardBadges::dispatch($cheer->user));
        LeagueMember::created(fn (LeagueMember $member) => AwardBadges::dispatch($member->user));
        Goal::updated(function (Goal $goal) {
            if ($goal->wasChanged('status') && $goal->status === GoalStatus::Completed) {
                AwardBadges::dispatch($goal->user);
            }
        });
    }

    /**
     * Record feature use for the admin page: event names only, never content.
     */
    private function trackUsage(): void
    {
        Goal::created(fn (Goal $goal) => Usage::track($goal->user, 'goal-created'));
        RoadmapTask::updated(function (RoadmapTask $task) {
            if ($task->wasChanged('completed_at') && $task->completed_at) {
                Usage::track($task->week->goal->user, 'check-in');
            }
        });
        HabitLog::created(fn (HabitLog $log) => Usage::track($log->habit->goal->user, 'habit-log'));
        WeekReview::created(fn (WeekReview $review) => Usage::track($review->week->goal->user, 'week-reviewed'));
        Highlight::created(fn (Highlight $highlight) => Usage::track($highlight->user, 'highlight-shared'));
        Cheer::created(fn (Cheer $cheer) => Usage::track($cheer->user, 'cheer'));
        HighlightComment::created(fn (HighlightComment $comment) => Usage::track($comment->user, 'comment'));
        LeagueMember::created(fn (LeagueMember $member) => Usage::track($member->user, 'league-joined'));
        PushSubscription::created(fn (PushSubscription $subscription) => Usage::track($subscription->user, 'push-enabled'));
        ChallengeMember::created(fn (ChallengeMember $member) => Usage::track($member->user, 'challenge-joined'));
        ChallengeCheckIn::created(fn (ChallengeCheckIn $checkIn) => Usage::track($checkIn->user, 'challenge-check-in'));
        LeagueMessage::created(function (LeagueMessage $message) {
            if (! $message->is_system) {
                Usage::track($message->user, 'chat-message');
            }
        });
    }
}
