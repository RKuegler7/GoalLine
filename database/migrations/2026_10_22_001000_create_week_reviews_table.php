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
        // The end-of-GoalWeek reflection, and the coach's tip for the week ahead.
        Schema::create('week_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_week_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('feeling')->nullable();
            $table->text('went_well')->nullable();
            $table->text('in_the_way')->nullable();
            $table->text('coach_tip')->nullable();
            $table->string('coach_status', 20)->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_reviews');
    }
};
