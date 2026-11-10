<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check-ins are looked up by GoalWeek and when they were checked off (weekly recaps, streaks, the matchup
     * timeline). Safe to run again: it skips an index that's already there.
     */
    public function up(): void
    {
        if (Schema::hasIndex('roadmap_tasks', 'roadmap_tasks_week_completed_index')) {
            return;
        }

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->index(['roadmap_week_id', 'completed_at'], 'roadmap_tasks_week_completed_index');
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropIndex('roadmap_tasks_week_completed_index');
        });
    }
};
