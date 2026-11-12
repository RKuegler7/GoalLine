<?php

use App\Services\EarlyCheckIns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Void check-ins made before kickoff (or on a GoalWeek that hadn't started), then re-score the settled league weeks
     * they touched. Safe to run again: a voided check-in is no longer checked off, so it's never counted twice.
     */
    public function up(): void
    {
        $counts = app(EarlyCheckIns::class)->void();

        Log::info('Voided check-ins made before kickoff.', $counts);

        if (app()->runningInConsole() && array_sum($counts) > 0 && ! app()->runningUnitTests()) {
            fwrite(STDERR, '  Voided before kickoff: '.json_encode($counts).PHP_EOL);
        }
    }

    /**
     * Voids are kept for history; nothing to undo automatically.
     */
    public function down(): void {}
};
