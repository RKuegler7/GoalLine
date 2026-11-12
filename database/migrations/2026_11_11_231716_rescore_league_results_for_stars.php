<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Weekly stars replace match points: settled league weeks are re-scored with the new rules (win 2, photo finish win
     * 1, tie 1 each, any loss 0, byes line stars only; photo finishes compared at full precision). No schema change.
     * Safe to run twice; the same command can be run by hand.
     */
    public function up(): void
    {
        Artisan::call('goalline:rescore-leagues');
    }

    /**
     * Re-scoring isn't undone; restore a backup to go back.
     */
    public function down(): void {}
};
