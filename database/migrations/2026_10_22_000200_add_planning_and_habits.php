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
            $table->date('planned_on')->nullable()->after('is_bonus');
        });

        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('times_per_week');
            $table->json('days')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->timestamps();

            $table->unique(['habit_id', 'logged_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
        Schema::dropIfExists('habits');

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn('planned_on');
        });
    }
};
