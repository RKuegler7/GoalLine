<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            // Scoring: what a task is worth toward the GoalWeek's planned points.
            $table->unsignedTinyInteger('points')->default(1)->after('is_bonus');

            // Optional numbers ("Run 5 miles"): the plan, the allowed range, and what was actually done.
            $table->boolean('result_required')->default(false)->after('points');
            $table->decimal('result_target', 10, 2)->nullable()->after('result_required');
            $table->string('result_unit', 20)->nullable()->after('result_target');
            $table->decimal('result_min', 10, 2)->nullable()->after('result_unit');
            $table->decimal('result_max', 10, 2)->nullable()->after('result_min');
            $table->decimal('result_value', 10, 2)->nullable()->after('result_max');

            // "Show your work": a photo or screenshot of the check-in, with location data stripped.
            $table->string('proof_path')->nullable()->after('result_value');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_path');
        });

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            // The matchup opponent's OK for a Smashed week without proof.
            $table->foreignId('smashed_confirmed_by')->nullable()->after('milestone_hit_at')->constrained('users')->nullOnDelete();
            $table->timestamp('smashed_confirmed_at')->nullable()->after('smashed_confirmed_by');
        });

        // "Show your work" requests: one per league mate per check-in.
        Schema::create('proof_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['roadmap_task_id', 'requester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proof_requests');

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('smashed_confirmed_by');
            $table->dropColumn('smashed_confirmed_at');
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn(['points', 'result_required', 'result_target', 'result_unit', 'result_min', 'result_max', 'result_value', 'proof_path', 'proof_uploaded_at']);
        });
    }
};
