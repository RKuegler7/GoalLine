<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan honesty and disputes: each GoalWeek's minimum workload (plan floor) and any declared lighter week, tasks
     * added to reach the floor, a check-in's dispute status, where a plan came from and when it was accepted, and the
     * disputes themselves.
     */
    public function up(): void
    {
        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor_points')->nullable()->after('milestone');
            $table->string('floor_note')->nullable()->after('floor_points');
            $table->string('lighter_reason', 120)->nullable()->after('floor_note');
            $table->timestamp('lighter_at')->nullable()->after('lighter_reason');
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->boolean('is_floor')->default(false)->after('is_personal');
            $table->string('dispute_status', 10)->nullable()->after('is_floor');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('plan_source', 10)->nullable()->after('roadmap_generated_at');
            $table->timestamp('plan_accepted_at')->nullable()->after('plan_source');
        });

        // Plans people are already following count as accepted.
        DB::table('goals')->where('roadmap_status', 'ready')->update(['plan_accepted_at' => now(), 'plan_source' => 'coach']);

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disputer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 200)->nullable();
            $table->string('status', 10)->default('open');
            $table->string('resolution', 20)->nullable();
            $table->string('owner_note', 300)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['roadmap_task_id', 'status']);
            $table->index(['disputer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['plan_source', 'plan_accepted_at']);
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn(['is_floor', 'dispute_status']);
        });

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->dropColumn(['floor_points', 'floor_note', 'lighter_reason', 'lighter_at']);
        });
    }
};
