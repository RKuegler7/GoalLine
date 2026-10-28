<?php

namespace App\Console\Commands;

use App\Services\GroupTasks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('goalline:close-group-votes')]
#[Description('Settle group task votes whose 24 hours are up: no task without two thirds saying yes')]
class CloseGroupTaskVotes extends Command
{
    public function handle(GroupTasks $groupTasks): int
    {
        $this->info("Settled {$groupTasks->closeDue()} group task votes.");

        return self::SUCCESS;
    }
}
