<?php

use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\Announcement;
use App\Services\ChallengeWrapUp;
use App\Services\DemoWorld;
use App\Services\LeagueTable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Morning gameplans, evening nudges, and final GoalWeek results, each on the member's own clock.
Schedule::command('goalline:send-reminders')->everyFifteenMinutes()->withoutOverlapping();

// Settle finished GoalLeague weeks, send weekly recaps, hand out season awards, and wrap up challenges.
Schedule::command('goalline:settle-leagues')->hourly()->withoutOverlapping();

// Demo worlds are deleted after DEMO_TTL_HOURS.
Schedule::call(fn () => DemoWorld::pruneExpired())->name('prune-demos')->hourly()->withoutOverlapping();

// Group task votes run 24 hours; settle the ones whose time is up.
Schedule::command('goalline:close-group-votes')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('goalline:announce {message : The news, in one line} {--url= : Where it links to}', function (string $message) {
    $count = 0;

    User::whereNotNull('email_verified_at')->chunkById(200, function ($users) use ($message, &$count) {
        Notification::send($users, new Announcement($message, $this->option('url')));
        $count += $users->count();
    });

    $this->info("Announced to {$count} members.");
})->purpose('Send GoalLine news to every member');

Artisan::command('goalline:settle-leagues', function () {
    League::where('starts_on', '<=', today())
        ->where('ends_on', '>=', today()->subDays(LeagueTable::RECAP_NEWS_DAYS + RoadmapWeek::CHECK_IN_GRACE_DAYS))
        ->each(fn (League $league) => (new LeagueTable($league))->settle());

    app(ChallengeWrapUp::class)->run();
})->purpose('Settle finished GoalLeague weeks, wrap up finished challenges, and hand out recaps and awards');

Artisan::command('goalline:vapid-keys', function () {
    $keys = VAPID::createVapidKeys();

    $this->line('Add these to .env (and to the production environment):');
    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
})->purpose('Make the VAPID key pair that web push needs');
