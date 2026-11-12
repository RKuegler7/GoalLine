<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The league goal: a shareable template (title, date, optional description and target result) copied from the
     * owner's goal when the league is made, so later edits to that personal goal never change it silently. Also the
     * league week each member joined in, so late joiners only play from then on. Safe to run twice.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leagues', 'goal_title')) {
            Schema::table('leagues', function (Blueprint $table) {
                $table->string('goal_title', 120)->nullable()->after('category');
                $table->date('goal_target_date')->nullable()->after('goal_title');
                $table->string('goal_description', 280)->nullable()->after('goal_target_date');
                $table->string('goal_target_result', 80)->nullable()->after('goal_description');
                $table->timestamp('goal_updated_at')->nullable()->after('goal_target_result');
            });
        }

        if (! Schema::hasColumn('league_members', 'joined_week')) {
            Schema::table('league_members', function (Blueprint $table) {
                $table->unsignedSmallInteger('joined_week')->nullable()->after('team_name');
            });
        }

        // Existing leagues: the owner's league goal (title and date only) becomes the template.
        DB::table('leagues')->whereNull('goal_title')->orderBy('id')->each(function (object $league) {
            $goal = DB::table('league_members')
                ->join('goals', 'goals.id', '=', 'league_members.goal_id')
                ->where('league_members.league_id', $league->id)
                ->where('league_members.user_id', $league->commissioner_id)
                ->first(['goals.title', 'goals.target_date']);

            if ($goal) {
                DB::table('leagues')->where('id', $league->id)->update(['goal_title' => $goal->title, 'goal_target_date' => $goal->target_date]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('league_members', function (Blueprint $table) {
            $table->dropColumn('joined_week');
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['goal_title', 'goal_target_date', 'goal_description', 'goal_target_result', 'goal_updated_at']);
        });
    }
};
