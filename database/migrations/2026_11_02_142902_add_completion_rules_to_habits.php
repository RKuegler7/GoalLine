<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Objective recurring tasks: a clear cadence (every day, specific days, or X times a week), a completion rule
     * (a check-off, a number, or minutes, with a threshold), and the number logged each day. Existing habits keep
     * counting exactly as before: they're check-offs, and every existing log counts.
     */
    public function up(): void
    {
        Schema::table('habits', function (Blueprint $table) {
            $table->string('cadence', 10)->default('weekly')->after('times_per_week');
            $table->string('rule', 10)->default('check')->after('days');
            $table->decimal('threshold', 10, 2)->nullable()->after('rule');
            $table->string('unit', 20)->nullable()->after('threshold');
        });

        Schema::table('habit_logs', function (Blueprint $table) {
            $table->decimal('value', 10, 2)->nullable()->after('logged_on');
            $table->boolean('met')->default(true)->after('value');
        });

        // Every day, or exactly the chosen days; anything else stays "X times a week".
        DB::table('habits')->orderBy('id')->each(function (object $habit) {
            $days = json_decode((string) $habit->days, true) ?: [];
            $cadence = match (true) {
                $habit->times_per_week >= 7 => 'daily',
                $days !== [] && count($days) === (int) $habit->times_per_week => 'days',
                default => 'weekly',
            };

            DB::table('habits')->where('id', $habit->id)->update(['cadence' => $cadence]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habit_logs', function (Blueprint $table) {
            $table->dropColumn(['value', 'met']);
        });

        Schema::table('habits', function (Blueprint $table) {
            $table->dropColumn(['cadence', 'rule', 'threshold', 'unit']);
        });
    }
};
