<?php

namespace App\Console\Commands;

use App\Services\EarlyCheckIns;
use Illuminate\Console\Command;

class EarlyCheckInsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'goalline:early-check-ins {--dry-run : Count them without changing anything}';

    /**
     * @var string
     */
    protected $description = 'Void check-ins made before kickoff (or on a GoalWeek that had not started) and re-score the weeks they touched';

    public function handle(EarlyCheckIns $earlyCheckIns): int
    {
        $counts = $earlyCheckIns->void($this->option('dry-run'));

        $this->info(($this->option('dry-run') ? 'Would void' : 'Voided').": {$counts['tasks']} task check-ins ({$counts['proofs']} with proof), {$counts['milestones']} milestones, {$counts['habit_logs']} habit logs.");

        if (! $this->option('dry-run')) {
            $this->info("Re-scored {$counts['rescored_weeks']} settled league weeks.");
        }

        return self::SUCCESS;
    }
}
