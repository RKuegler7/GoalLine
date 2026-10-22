<?php

use App\Models\User;
use App\Notifications\Announcement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Morning gameplans, evening nudges, and final GoalWeek results, each on the member's own clock.
Schedule::command('goalline:send-reminders')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('goalline:announce {message : The news, in one line} {--url= : Where it links to}', function (string $message) {
    $count = 0;

    User::whereNotNull('email_verified_at')->chunkById(200, function ($users) use ($message, &$count) {
        Notification::send($users, new Announcement($message, $this->option('url')));
        $count += $users->count();
    });

    $this->info("Announced to {$count} members.");
})->purpose('Send GoalLine news to every member');
