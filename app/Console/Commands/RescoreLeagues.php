<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Services\LeagueTable;
use Illuminate\Console\Command;

class RescoreLeagues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'goalline:rescore-leagues';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-score every settled GoalLeague week with the current rules (weekly stars, full-precision photo finishes, byes as line stars only). Safe to run again.';

    public function handle(): int
    {
        $weeks = 0;

        League::query()->whereHas('results')->with('results')->orderBy('id')->each(function (League $league) use (&$weeks) {
            $table = new LeagueTable($league);

            foreach ($league->results->pluck('week')->unique()->sort() as $week) {
                $table->rescore((int) $week);
                $weeks++;
            }
        });

        $this->info("Re-scored {$weeks} settled league ".str('week')->plural($weeks).'.');

        return self::SUCCESS;
    }
}
