<?php

namespace App\Providers;

use Anthropic\Client;
use App\EmailVerificationLink;
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
use App\Services\CaptionWriter;
use App\Usage;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The Claude client, with a hard timeout and a retry limit. The SDK leaves timeouts to the HTTP client it's
        // given, so it gets a Guzzle client that enforces them. Planning runs in queued jobs and can take minutes;
        // the caption helper answers a tap in the browser, so it gets a short one. Every caller has a fallback.
        $this->app->singleton(Client::class, fn () => self::claudeClient(
            (float) config('services.anthropic.timeout'), (int) config('services.anthropic.max_retries'),
        ));
        $this->app->when(CaptionWriter::class)->needs(Client::class)->give(fn () => self::claudeClient(
            (float) config('services.anthropic.quick_timeout'), 1,
        ));

        $this->app->bind(WebPush::class, fn () => new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]));
    }

    /**
     * A Claude client that gives up after $timeout seconds per attempt and retries at most $retries times.
     */
    public static function claudeClient(float $timeout, int $retries): Client
    {
        $transport = new GuzzleClient(['timeout' => $timeout, 'connect_timeout' => 10]);

        return new Client(
            apiKey: config('services.anthropic.key'),
            requestOptions: ['timeout' => $timeout, 'maxRetries' => $retries, 'transporter' => $transport],
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Never show stack traces in production, even if APP_DEBUG is set by mistake: turn it off and say so in the log.
        if ($this->app->isProduction() && config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG was true in production. It has been turned off for this request; set it to false in the environment properties.');
        }

        $this->rateLimits();

        // In production GoalLine sits behind CloudFront, which talks to the server over plain HTTP.
        // Build every link from APP_URL, so they're https and use the public address.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceRootUrl(rtrim((string) config('app.url'), '/'));
            URL::forceScheme('https');
        }

        // Verification emails use path-signed links that a CDN or click-tracker can't break.
        VerifyEmail::createUrlUsing(fn (User $user) => EmailVerificationLink::for($user));

        // After a deploy changes the built CSS or JS, Turbo does one full load so nobody runs old code.
        Vite::useScriptTagAttributes(['data-turbo-track' => 'reload']);
        Vite::useStyleTagAttributes(['data-turbo-track' => 'reload']);

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

    /**
     * Rate limits for signed-out pages, keyed so people sharing one network (a campus, a judging session, a phone
     * carrier) don't block each other: by the account being used plus the address, with a roomier cap for the address
     * alone. Signed-in limits are already per member.
     */
    private function rateLimits(): void
    {
        $who = fn (Request $request) => Str::lower((string) $request->input('email')).'|'.$request->ip();

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login:'.$who($request)),
            Limit::perMinute(100)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(30)->by('register-ip:'.$request->ip()),
        ]);
        RateLimiter::for('password', fn (Request $request) => [
            Limit::perMinute(5)->by('password:'.$who($request)),
            Limit::perMinute(60)->by('password-ip:'.$request->ip()),
        ]);
        RateLimiter::for('verification-resend', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('resend:'.$who($request)),
            Limit::perHour(100)->by('resend-ip:'.$request->ip()),
        ]);
    }
}
