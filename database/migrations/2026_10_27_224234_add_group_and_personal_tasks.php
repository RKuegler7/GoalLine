<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group tasks a GoalLeague votes on, and personal tasks members add to their own GoalWeek.
     */
    public function up(): void
    {
        Schema::create('group_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('points')->default(5);
            $table->unsignedSmallInteger('week');
            $table->string('status', 20)->default('voting');
            $table->string('outcome_note')->nullable();
            $table->timestamp('closes_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'week', 'status']);
            $table->index(['status', 'closes_at']);
        });

        Schema::create('group_task_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('approve');
            $table->timestamps();

            $table->unique(['group_task_id', 'user_id']);
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->foreignId('group_task_id')->nullable()->after('roadmap_week_id')->constrained()->nullOnDelete();
            $table->boolean('is_personal')->default(false)->after('is_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_task_id');
            $table->dropColumn('is_personal');
        });

        Schema::dropIfExists('group_task_votes');
        Schema::dropIfExists('group_tasks');
    }
};
