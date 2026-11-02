<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The coach's intake questions for a goal and the person's answers, which shape the roadmap.
     */
    public function up(): void
    {
        Schema::create('goal_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('generating');
            $table->string('source', 20)->nullable();
            $table->json('questions')->nullable();
            $table->json('answers')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_intakes');
    }
};
