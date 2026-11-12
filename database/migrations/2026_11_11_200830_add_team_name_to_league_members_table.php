<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional team name per GoalLeague membership, shown on the matchup scoreboard and in the standings.
     */
    public function up(): void
    {
        if (Schema::hasColumn('league_members', 'team_name')) {
            return;
        }

        Schema::table('league_members', function (Blueprint $table) {
            $table->string('team_name', 24)->nullable()->after('goal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_members', function (Blueprint $table) {
            $table->dropColumn('team_name');
        });
    }
};
