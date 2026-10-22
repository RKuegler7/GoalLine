<?php

namespace App\Providers;

use Anthropic\Client;
use App\GoalStatus;
use App\Jobs\AwardBadges;
use App\Models\Cheer;
use App\Models\Goal;
use App\Models\HabitLog;
use App\Models\LeagueMember;
use App\Models\RoadmapTask;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Check for new badges whenever someone does something that could earn one.
        RoadmapTask::updated(function (RoadmapTask $task) {
            if ($task->wasChanged('completed_at') && $task->completed_at) {
                AwardBadges::dispatch($task->week->goal->user);
            }
        });
        HabitLog::created(fn (HabitLog $log) => AwardBadges::dispatch($log->habit->goal->user));
        Cheer::created(fn (Cheer $cheer) => AwardBadges::dispatch($cheer->user));
        LeagueMember::created(fn (LeagueMember $member) => AwardBadges::dispatch($member->user));
        Goal::updated(function (Goal $goal) {
            if ($goal->wasChanged('status') && $goal->status === GoalStatus::Completed) {
                AwardBadges::dispatch($goal->user);
            }
        });
    }
}
