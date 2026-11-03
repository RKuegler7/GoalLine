<?php

namespace App\Console\Commands;

use App\Clock;
use App\Models\Goal;
use App\RoadmapStatus;
use App\Services\PlanFloor;
use Illuminate\Console\Command;

class SetPlanFloors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'goalline:plan-floors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give current and upcoming GoalWeeks from before plan floors their minimum pace, and raise upcoming weeks that fall short. Safe to run again.';

    /**
     * Execute the console command.
     */
    public function handle(PlanFloor $planFloor): int
    {
        $weeks = 0;

        Goal::query()->active()->where('roadmap_status', RoadmapStatus::Ready)->with('user', 'intake')->each(function (Goal $goal) use ($planFloor, &$weeks) {
            $weeks += Clock::for($goal->user, fn () => $planFloor->backfill($goal));
        });

        $this->info("Set the plan floor on {$weeks} GoalWeeks.");

        return self::SUCCESS;
    }
}
